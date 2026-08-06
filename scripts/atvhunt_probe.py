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
            else:
                surfaces(rep); shapes(rep); ladder(rep); recovery(rep)
    finally:
        os.makedirs(os.path.dirname(outp) or ".", exist_ok=True)
        json.dump(rep, open(outp, "w"), indent=1, default=str)
        print(json.dumps(rep, indent=1, default=str)[:4000])
    sys.exit(rc)


if __name__ == "__main__":
    main()
