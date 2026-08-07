#!/usr/bin/env python3
"""Print atvhunt live listing ids to stdout, one per line. One shard of the harvest.

  python atvhunt_ids.py sitemap 0 18 1.5 > ids-0.txt
  python atvhunt_ids.py facets  0 18 1.5 > ids-0.txt

argv: mode(sitemap|facets)  part  total  rps

sitemap: robots.txt Sitemap: lines + the usual paths, recurse ONE level into child
         sitemaps, take this shard's slice of the children, extract /l/{id}/.
facets:  walk /atv-utv-for-sale/{State}?typeid={6,7}&page=N until a page adds no new ids.
         NO hard page cap: a cap silently truncates the big states, which is a completeness
         break, not a slowdown. Instead we compare ids collected against the facet's OWN
         reported result count and warn on stderr if we stop short.
"""
import sys, re, time, gzip, io
from curl_cffi import requests as rq

mode, part, total, rps = sys.argv[1], int(sys.argv[2]), int(sys.argv[3]), float(sys.argv[4])
A = "https://atvhunt.com"
S = rq.Session(impersonate="chrome")     # ponytail: no header overrides, see atvhunt_probe.py
D = 1.0 / rps
ID = re.compile(r"/l/(\d+)/")

STATES = ("Alabama Alaska Arizona Arkansas California Colorado Connecticut Delaware Florida "
          "Georgia Hawaii Idaho Illinois Indiana Iowa Kansas Kentucky Louisiana Maine "
          "Maryland Massachusetts Michigan Minnesota Mississippi Missouri Montana Nebraska "
          "Nevada New-Hampshire New-Jersey New-Mexico New-York North-Carolina North-Dakota "
          "Ohio Oklahoma Oregon Pennsylvania Rhode-Island South-Carolina South-Dakota "
          "Tennessee Texas Utah Vermont Virginia Washington West-Virginia Wisconsin Wyoming"
          ).split()
assert len(STATES) == 50, len(STATES)


def get(u):
    """Paced GET with a bounded 429 backoff. Returns '' on give-up (loud on stderr)."""
    for attempt in range(5):
        time.sleep(D)
        try:
            r = S.get(u, timeout=(8, 25), impersonate="chrome")
        except Exception as e:
            sys.stderr.write(f"ERR {u}: {e!r}\n"); continue
        if r.status_code == 429:
            time.sleep(20 * (attempt + 1)); continue
        if r.status_code in (401, 403):
            sys.exit(f"FATAL 403 on {u} - this runner's IP is geo/WAF blocked")
        if r.status_code != 200:
            return ""
        if u.endswith(".gz"):
            try:
                return gzip.GzipFile(fileobj=io.BytesIO(r.content)).read().decode("utf-8", "replace")
            except Exception:
                return r.text
        return r.text
    sys.stderr.write(f"GIVEUP {u}\n")
    return ""


seen = set()

if mode == "statemap":
    # Each facet serves exactly 24 server-rendered ids and there is NO compliant way to
    # page deeper (no rel=next, no page=; start=/n=//api/ are all robots Disallow-ed). So
    # widen sideways instead of deeper: cross the ~2,518 facets atvhunt declares in
    # sitemap-search.xml with all 50 states -> ~125,900 facet URLs x 24 slots. Every URL
    # here is robots-permitted (only typeid= and a state path segment).
    body = get(f"{A}/sitemap-search.xml")
    facets = [u for u in re.findall(r"<loc>([^<]+)</loc>", body) if "/l/" not in u]
    urls = []
    for u in facets:
        q = u.split("?", 1)
        if len(q) == 2 and "/atv-utv-for-sale" in q[0]:
            urls += [f"{A}/atv-utv-for-sale/{s}?{q[1]}" for s in STATES]
    sys.stderr.write(f"facets={len(facets)} -> state-crossed urls={len(urls)}\n")
    for u in urls[part::total]:
        seen.update(ID.findall(get(u)))

elif mode == "searchmap":
    # The site's OWN declared enumeration surface: sitemap-search.xml lists ~2,518 facet
    # URLs (typeid=6, typeid=6_Recreational, ...). Fetch this shard's slice and take the
    # 24 server-rendered ids each exposes. This is the widest route robots.txt permits --
    # paging past 24 needs start=/n=//api/, all of which are Disallow-ed.
    body = get(f"{A}/sitemap-search.xml")
    facets = [u for u in re.findall(r"<loc>([^<]+)</loc>", body) if "/l/" not in u]
    sys.stderr.write(f"sitemap-search facets: {len(facets)}\n")
    for u in facets[part::total]:
        seen.update(ID.findall(get(u)))

elif mode == "sitemap":
    roots = re.findall(r"(?im)^\s*sitemap:\s*(\S+)", get(f"{A}/robots.txt"))
    roots += [f"{A}/sitemap.xml", f"{A}/sitemap_index.xml", f"{A}/sitemap-listings.xml"]
    kids = []
    for u in list(dict.fromkeys(roots)):
        body = get(u)
        seen.update(ID.findall(body))                       # a flat sitemap ends here
        kids += [k for k in re.findall(r"<loc>([^<]+)</loc>", body)
                 if k.endswith((".xml", ".xml.gz"))]
    kids = list(dict.fromkeys(kids))
    sys.stderr.write(f"child sitemaps: {len(kids)}\n")
    for k in kids[part::total]:
        seen.update(ID.findall(get(k)))
else:
    roots = [f"{A}/atv-utv-for-sale/{s}?typeid={t}" for s in STATES for t in (6, 7)]
    for root in roots[part::total]:
        got, page, target = set(), 1, None
        while True:
            body = get(f"{root}&page={page}")
            if target is None:
                c = [int(x.replace(",", "")) for x in
                     re.findall(r"([\d,]{2,})\s*(?:ATVs?|UTVs?|results?|listings?|vehicles?)", body)]
                target = max(c) if c else 0
            new = set(ID.findall(body)) - got
            if not new:
                break                                        # facet exhausted
            got |= new
            page += 1
        if target and len(got) < target * 0.9:
            # ponytail: loud, not fatal. A capped facet needs a subtype/radius split;
            # the merge step's coverage line is where this shows up as a real gap.
            sys.stderr.write(f"WARN {root}: got {len(got)} of a reported {target} - facet capped?\n")
        seen |= got

sys.stderr.write(f"part {part}/{total} -> {len(seen)} ids\n")
print("\n".join(sorted(seen, key=int)))
