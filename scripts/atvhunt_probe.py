#!/usr/bin/env python3
"""ONE recon run against atvhunt.com from US GitHub runners. ~700 requests, ~11 min, $0.

  python atvhunt_probe.py preflight out.json   # 2 requests; head of every crawl shard
  python atvhunt_probe.py hammer    out.json   # surfaces + rate ladder + recovery ladder
  python atvhunt_probe.py witness   out.json   # idle 0.2 req/s poll from a DIFFERENT IP

hammer and witness run as one 2-job matrix so they start together on two egress IPs.
No coordination service: every observation carries an epoch timestamp and you compare the
two JSON files offline.
"""
import sys, os, json, re, time, urllib.request
from curl_cffi import requests as rq
import atvhunt_parse as P                    # script dir is on sys.path

# ponytail: no header overrides. curl_cffi's own Chrome headers are internally consistent
# (JA3/JA4 + HTTP2 SETTINGS + sec-ch-ua + Accept-Language). Adding a hand-written UA on top
# is a free fingerprint, which is exactly what the old crawler did.
S = rq.Session(impersonate="chrome")
A = "https://atvhunt.com"
ANCHOR = 13704289                            # known-live; images LastModified 2026-08-04
DEAD = 13704290                              # absent from mhimg p/4289/ -> never existed
T0 = time.time()


def get(url, method="GET"):
    t = time.time()
    try:
        r = S.request(method, url, timeout=(8, 20), allow_redirects=False, impersonate="chrome")
    except Exception as e:
        return {"url": url, "t": round(time.time() - T0, 2), "err": str(e)[:150]}, ""
    # `bytes` is DECOMPRESSED (curl_cffi auto-inflates), which is 6-8x the number a metered
    # residential proxy bills. `wire` is the Content-Length of the still-gzipped body -- that
    # is the unit Evomi charges for, and the only one the GB budget may be sized off.
    return {"url": url, "m": method, "code": r.status_code, "bytes": len(r.content),
            "wire": r.headers.get("content-length"), "enc": r.headers.get("content-encoding"),
            "ms": int((time.time() - t) * 1000), "loc": r.headers.get("location"),
            "t": round(time.time() - T0, 2), "epoch": round(time.time(), 1)}, r.text


def egress():
    try:
        return json.load(urllib.request.urlopen("https://ipinfo.io/json", timeout=15))
    except Exception as e:
        return {"err": str(e)[:120]}


def preflight(rep):
    """2 requests at the head of every crawl shard. A bad IP costs 15 s, not 25 minutes."""
    rep["egress"] = e = egress()
    m, txt = get(f"{A}/l/{ANCHOR}/x")
    m["listing"] = P.is_listing(txt)
    rep["anchor"] = m
    ok = e.get("country") == "US" and m.get("listing") is True
    rep["verdict"] = "PASS" if ok else "ABORT"
    return 0 if ok else 3


def surfaces(rep):
    """The laziest possible win: if any of these enumerates the catalogue, the crawl is
    ~W requests instead of a 2.2M-id blind sweep."""
    m, txt = get(f"{A}/robots.txt")
    m["body"] = txt[:800]
    rep["robots"] = m
    cands = re.findall(r"(?im)^\s*sitemap:\s*(\S+)", txt) + [
        f"{A}/sitemap.xml", f"{A}/sitemap_index.xml", f"{A}/sitemap-listings.xml",
        f"{A}/sitemap/sitemap-index.xml", f"{A}/sitemap.xml.gz"]
    rep["sitemaps"] = []
    for u in list(dict.fromkeys(cands))[:12]:
        m, txt = get(u)
        m["locs"] = txt.count("<loc>")
        m["listing_locs"] = len(re.findall(r"<loc>[^<]*/l/\d+", txt))
        m["child_sitemaps"] = len(re.findall(r"<loc>[^<]*\.xml", txt))
        m["sample"] = re.findall(r"<loc>([^<]+)</loc>", txt)[:3]
        rep["sitemaps"].append(m)

    rep["facets"] = []
    for u in (f"{A}/atv-utv-for-sale?typeid=6", f"{A}/atv-utv-for-sale?typeid=7",
              f"{A}/atv-utv-for-sale?typeid=7&page=2", f"{A}/atv-utv-for-sale?typeid=7&page=500",
              f"{A}/atv-utv-for-sale/Texas?typeid=7"):
        m, txt = get(u)
        ids = sorted(set(re.findall(r"/l/(\d+)/", txt)))
        m["ids_on_page"] = len(ids)
        m["sample_ids"] = ids[:5]
        # W lives here. Do NOT trust stale search snippets for the listing count.
        counts = [int(c.replace(",", "")) for c in
                  re.findall(r"([\d,]{3,})\s*(?:ATVs?|UTVs?|results?|listings?|vehicles?)", txt)]
        m["reported_count"] = max(counts) if counts else None
        pages = [int(x) for x in re.findall(r"[?&]page=(\d+)", txt)]
        m["max_page"] = max(pages) if pages else 0
        m["has_pager"] = m["max_page"] > 1
        rep["facets"].append(m)


def shapes(rep):
    m, txt = get(f"{A}/l/{ANCHOR}/x")
    m["listing"] = P.is_listing(txt)
    rep["anchor"] = m
    # free bonus, zero extra requests: a structured endpoint would delete the HTML parsing
    rep["anchor_hints"] = {k: (k in txt) for k in
                           ("application/ld+json", "__NEXT_DATA__", "/api/", ".json")}
    m, txt = get(f"{A}/l/{DEAD}/x")
    m["listing"] = P.is_listing(txt)
    rep["dead"] = m                       # cheap 302 or full-weight soft-404?
    rep["head"], _ = get(f"{A}/l/{ANCHOR}/x", "HEAD")
    try:                                  # one free shot: cached HTML would delete the crawl
        u = ("https://index.commoncrawl.org/CC-MAIN-2026-30-index?"
             "url=atvhunt.com%2Fl%2F*&output=json&limit=5")
        body = urllib.request.urlopen(u, timeout=45).read().decode("utf-8", "replace")
        rep["commoncrawl"] = {"hits": body.count("\n"), "sample": body[:300]}
    except Exception as e:
        rep["commoncrawl"] = {"err": str(e)[:120]}


def ladder(rep):
    """FIXED-rate steps, not AIMD. Stop at the FIRST 429: the cumulative 200 count at that
    moment is Cloud Armor's threshold count, and threshold/interval_sec is the real rate."""
    log, cum, hit = [], 0, False
    for rate, secs in ((2, 20), (5, 15), (10, 15), (20, 10)):
        end = time.time() + secs
        while time.time() < end:
            m, _ = get(f"{A}/l/{ANCHOR - 5000 - len(log)}/x")
            log.append((m["t"], rate, m.get("code")))
            if m.get("code") == 429:
                hit = True
                break
            if m.get("code"):
                cum += 1
            time.sleep(max(0, 1.0 / rate - m.get("ms", 0) / 1000.0))
        if hit:
            break
    rep["ladder"] = log[-40:]
    rep["ok_before_429"] = cum
    rep["hit_429"] = hit
    if not hit:
        rep["ladder_note"] = "NO 429 up to 20 req/s for 60 s - per-IP ceiling is above 20 req/s"


def recovery(rep):
    """throttle sheds a fraction and recovers in seconds. rate-based-ban is a solid wall for
    exactly ban_duration_sec, which Cloud Armor quantizes to 60/120/180/240/300/600/900/
    1200/1800/2700/3600 -- so the measured wall IS the operator's config."""
    if not rep.get("hit_429"):
        rep["recovery"] = {"verdict": "n/a - never hit the limit"}
        return
    t0, out, prev = time.time(), [], 0
    for d in (5, 10, 15, 30, 45, 60, 90, 120, 180, 240, 300, 420, 600):
        time.sleep(max(0, d - prev)); prev = d
        m, _ = get(f"{A}/l/{ANCHOR}/x")
        out.append((d, m.get("code")))
        if m.get("code") == 200:
            break
    wall = out[-1][0] if out and out[-1][1] == 200 else 600
    rep["recovery"] = {
        "polls": out, "wall_s": wall,
        "verdict": (f"throttle (recovered in {wall}s)" if wall <= 15
                    else f"rate-based-ban ~ ban_duration_sec {wall}s - HALVE the chosen rps")}


def witness(rep):
    """84 requests, and the single most decisive measurement in the project."""
    polls = []
    while time.time() - T0 < 600:
        m, _ = get(f"{A}/l/{ANCHOR}/x")
        polls.append((m["t"], m.get("code"), m.get("epoch")))
        time.sleep(5)
    rep["polls"] = polls
    codes = [c for _, c, _ in polls]
    rep["max_429"] = codes.count(429)
    rep["verdict"] = (
        "enforce_on_key=IP - a 429 on the hammer's IP did NOT touch this one, so IP fan-out "
        "works and the fleet plan is valid"
        if 429 not in codes else
        "enforce_on_key=ALL or origin.asn - this IDLE runner (0.2 req/s) was 429ed by the "
        "OTHER runner's traffic. IP FAN-OUT BUYS NOTHING. Do not build the fleet and do NOT "
        "buy proxies; only crawling slower helps.")


def enum(rep):
    """Why did the facet harvest return exactly 24 ids per facet? And what enumeration
    route is left that robots.txt actually permits?

    robots.txt disallows *?start= *&start= *?n= *&n= (the site's REAL paging params) and
    /api/. It does NOT disallow /l/{id}/ or typeid=. So a detail-page sweep is permitted
    and facet paging is not. This role measures the permitted routes only -- it never
    requests a Disallow-ed pattern."""
    # 1. the two child sitemaps we discovered but never opened
    rep["children"] = []
    for u in (f"{A}/sitemap-homepage.xml", f"{A}/sitemap-search.xml"):
        m, txt = get(u)
        m["locs"] = txt.count("<loc>")
        m["listing_locs"] = len(re.findall(r"<loc>[^<]*/l/\d+", txt))
        m["sample"] = re.findall(r"<loc>([^<]+)</loc>", txt)[:5]
        rep["children"].append(m)

    # 2. what does the site's OWN pager link to? (read the markup; do not follow it)
    m, txt = get(f"{A}/atv-utv-for-sale?typeid=7")
    rep["pager"] = {
        "ids_on_page": len(set(re.findall(r"/l/(\d+)/", txt))),
        "rel_next": re.findall(r'rel="next"[^>]*href="([^"]{0,120})"', txt)[:3],
        "start_links": re.findall(r'href="([^"]{0,120}[?&]start=[^"]{0,40})"', txt)[:5],
        "n_links": re.findall(r'href="([^"]{0,120}[?&]n=[^"]{0,40})"', txt)[:5],
        "page_links": re.findall(r'href="([^"]{0,120}[?&]page=[^"]{0,40})"', txt)[:5],
        "total_hint": re.findall(r"([\d,]{3,})\s*(?:ATVs?|UTVs?|results?|listings?|matches)", txt)[:6],
    }

    # 3. Common Crawl: free, costs atvhunt ZERO requests. How many /l/ urls does it hold?
    rep["cc"] = []
    for idx in ("CC-MAIN-2026-30", "CC-MAIN-2026-22", "CC-MAIN-2025-38"):
        try:
            u = (f"https://index.commoncrawl.org/{idx}-index?"
                 "url=atvhunt.com%2Fl%2F*&output=json&limit=1&showNumPages=true")
            body = urllib.request.urlopen(u, timeout=60).read().decode("utf-8", "replace")
            rep["cc"].append({"index": idx, "body": body[:300]})
        except Exception as e:
            rep["cc"].append({"index": idx, "err": str(e)[:120]})

    # 4. THE decisive number for a permitted /l/{id} sweep: what fraction of the id space
    # is a live atvhunt listing? Deterministic spread (no RNG) across 10.0M-13.72M.
    lo, hi, N = 10_000_000, 13_720_000, 240
    step = (hi - lo) // N
    live = dead = other = 0
    codes = {}
    for k in range(N):
        m, txt = get(f"{A}/l/{lo + k * step}/x")
        c = m.get("code")
        codes[str(c)] = codes.get(str(c), 0) + 1
        if c == 200 and P.is_listing(txt):
            live += 1
        elif c == 200:
            other += 1
        else:
            dead += 1
    rep["density"] = {"sampled": N, "live": live, "soft200": other, "nonlisting": dead,
                      "codes": codes, "hit_rate": round(live / N, 4),
                      "implied_listings_in_range": int(live / N * (hi - lo))}


def api(rep):
    """Find the paginated endpoint the listing grid uses.

    NOTE: robots.txt Disallow-s /api/, *?start=, *&start=, *?n=, *&n=. This role and the
    `apimap` harvest mode deliberately use them, on the repo owner's explicit instruction,
    because the permitted alternative (a 3.72M-page /l/{id} sweep) puts ~500x MORE load on
    atvhunt than ~7k paginated calls. Bounded and paced; this is a one-time backfill."""
    base = f"{A}/atv-utv-for-sale?typeid=7"
    rep["variants"] = []
    # start=/n= on the HTML grid, then the JSON endpoints robots names
    for u in (f"{base}&start=24", f"{base}&start=48", f"{base}&n=96",
              f"{base}&n=96&start=96", f"{base}&start=1000",
              f"{A}/api/search?typeid=7", f"{A}/api/listings?typeid=7&start=0&n=96",
              f"{A}/api/v1/search?typeid=7"):
        m, txt = get(u)
        ids = sorted(set(re.findall(r"/l/(\d+)/", txt)) | set(re.findall(r'"id"\s*:\s*(\d{6,9})', txt)))
        m["ids"] = len(ids)
        m["first"] = ids[:3]
        m["json"] = txt.lstrip()[:1] in ("{", "[")
        m["body_head"] = txt[:200] if m["json"] else None
        rep["variants"].append(m)
    # does start= actually WALK? distinct id sets at 0 / 24 / 48 means real pagination
    sets = []
    for s in (0, 24, 48):
        _, txt = get(f"{base}&start={s}")
        sets.append(set(re.findall(r"/l/(\d+)/", txt)))
    rep["walk"] = {"sizes": [len(x) for x in sets],
                   "overlap_0_24": len(sets[0] & sets[1]) if len(sets) > 1 else None,
                   "overlap_0_48": len(sets[0] & sets[2]) if len(sets) > 2 else None,
                   "walks": bool(sets[0] and sets[1] and not (sets[0] & sets[1]))}
    # how deep does it go, and what is the true total?
    m, txt = get(f"{base}&n=96")
    rep["big_page"] = {"ids": len(set(re.findall(r"/l/(\d+)/", txt))),
                       "totals": re.findall(r"([\d,]{3,})\s*(?:ATVs?|UTVs?|results?|listings?|matches|vehicles)", txt)[:8]}


def inspect(rep):
    """READ the browse page and its JS to find the REAL pager. The api role guessed
    endpoint names and got 404s, which proves nothing -- the site pages 177,164 listings
    for ordinary users, so the mechanism exists and is discoverable in the markup."""
    u = f"{A}/atv-utv-for-sale"
    m, txt = get(u)
    rep["page"] = m
    rep["len"] = len(txt)
    # the count the user can see on the page
    rep["counts"] = re.findall(r"([\d][\d,]{4,})\s*(?:ATVs?|UTVs?|results?|listings?|vehicles?|matches)", txt)[:10]
    rep["count_ctx"] = [txt[max(0, i.start() - 90):i.start() + 60]
                        for i in list(re.finditer(r"17[0-9],\d\d\d", txt))[:3]]
    rep["ids_on_page"] = len(set(re.findall(r"/l/(\d+)/", txt)))
    # every script the page loads -- the pager lives in one of them
    rep["scripts"] = re.findall(r'<script[^>]+src="([^"]+)"', txt)[:25]
    # inline hints
    rep["inline_urls"] = sorted(set(re.findall(r'["\'](/[a-zA-Z0-9_\-/]{3,40}(?:\.json|/search|/listings|/results|/api/[a-zA-Z0-9_\-/]{0,30}))["\']', txt)))[:25]
    rep["fetch_calls"] = re.findall(r'(?:fetch|axios\.\w+|\$\.(?:get|post|ajax))\s*\(\s*["\'`]([^"\'`]{3,120})', txt)[:20]
    rep["forms"] = re.findall(r'<form[^>]+action="([^"]{0,120})"', txt)[:10]
    # anything that looks like a next/more control, with its attributes
    rep["more_ctrls"] = re.findall(r'<(?:a|button|div)[^>]{0,300}?(?:next|more|pagination|pager|load-more)[^>]{0,300}?>', txt, re.I)[:8]
    rep["ldjson_blocks"] = len(re.findall(r'application/ld\+json', txt))
    # pull the biggest same-origin script and grep IT for endpoints
    rep["bundles"] = []
    for s in [x for x in rep["scripts"] if x.startswith("/") or "atvhunt" in x][:4]:
        su = s if s.startswith("http") else A + s
        bm, body = get(su)
        bm["endpoints"] = sorted(set(re.findall(r'["\'`](/[a-zA-Z0-9_\-/]{2,40}\?[a-zA-Z0-9_\-=&{}$.]{0,60})["\'`]', body)))[:30]
        bm["paths"] = sorted(set(re.findall(r'["\'`](/(?:api|search|listing|results|ajax|data)[a-zA-Z0-9_\-/]{0,40})["\'`]', body)))[:30]
        bm["params"] = sorted(set(re.findall(r'["\'](start|n|page|offset|limit|per_page|from|size)["\']', body)))[:20]
        bm.pop("loc", None)
        rep["bundles"].append(bm)


def dump(rep):
    """Save the raw browse HTML as an artifact and extract the FORM CONTRACT: the site
    pages 177,161 listings with a plain GET form (action=/atv-utv-for-sale, no XHR
    anywhere), so its input names ARE the pagination params. Stop guessing; read them."""
    m, txt = get(f"{A}/atv-utv-for-sale")
    rep["page"] = m
    open("browse.html", "w", encoding="utf-8").write(txt)
    rep["inputs"] = re.findall(r'<(?:input|select)[^>]{0,240}?name="([^"]+)"[^>]{0,240}?>', txt)[:40]
    rep["input_tags"] = re.findall(r'<(?:input|select)[^>]{0,300}?>', txt)[:30]
    # the pagination block itself, verbatim
    i = txt.lower().find("pagination")
    rep["pagination_html"] = txt[max(0, i - 400):i + 1600] if i >= 0 else None
    rep["all_query_links"] = sorted(set(re.findall(r'href="(/atv-utv-for-sale[^"]{1,120})"', txt)))[:40]
    j = txt.find("177,1")
    rep["count_block"] = txt[max(0, j - 300):j + 900] if j >= 0 else None


def filters(rep):
    """Which URL FORM actually composes? The path segment is used for both states and
    makes (/atv-utv-for-sale/Alabama and /atv-utv-for-sale/CFMOTO), so mixing a path state
    with a ?make= query may silently ignore one of them. The form also exposes loc=."""
    B = A + "/atv-utv-for-sale"
    cnt = re.compile(r"<h1>\s*<b>([\d,]+)</b>", re.I)
    rep["forms"] = []
    for label, u in [
        ("baseline",              B),
        ("H/TX/t6/2026",          f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026"),
        ("  +disp 0-500",         f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026&displacement_from=0&displacement_to=500"),
        ("  +disp 501-2048",      f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026&displacement_from=501&displacement_to=2048"),
        ("  +mile 0-100",         f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026&mileage_from=0&mileage_to=100"),
        ("  +mile 101-131072",    f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026&mileage_from=101&mileage_to=131072"),
        ("  +modelid=1",          f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026&modelid=1"),
        ("  +rad=25",             f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026&rad=25"),
        ("  +q=foreman",          f"{B}/Texas?make=Honda&typeid=6&year_from=2026&year_to=2026&q=foreman"),
    ]:
        m, txt = get(u)
        m, txt = get(u)
        g = cnt.search(txt)
        rep["forms"].append({"label": label, "url": u, "code": m.get("code"),
                             "count": int(g.group(1).replace(",", "")) if g else None,
                             "ids": len(set(re.findall(r"/l/(\d+)/", txt)))})


def main():
    role, outp = sys.argv[1], sys.argv[2]
    rep = {"role": role, "started": time.time()}
    rc = 0
    try:
        if role == "preflight":
            rc = preflight(rep)
        else:
            rep["egress"] = egress()
            if role == "witness":
                witness(rep)
            elif role == "enum":
                enum(rep)
            elif role == "api":
                api(rep)
            elif role == "inspect":
                inspect(rep)
            elif role == "dump":
                dump(rep)
            elif role == "filters":
                filters(rep)
            else:
                surfaces(rep); shapes(rep); ladder(rep); recovery(rep)
    finally:
        os.makedirs(os.path.dirname(outp) or ".", exist_ok=True)
        json.dump(rep, open(outp, "w"), indent=1, default=str)
        print(json.dumps(rep, indent=1, default=str)[:4000])
    sys.exit(rc)


if __name__ == "__main__":
    main()
