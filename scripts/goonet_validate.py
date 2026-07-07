#!/usr/bin/env python3
"""Live-site validation harness for the council.

Samples cars (stratified by make) from our scraped clean.jsonl, RE-FETCHES each
one live from goo-net (curl_cffi Chrome impersonation, passes the WAF), and
independently re-extracts ground truth WITHOUT reusing the crawler's field
parser: the full <dt>/<dd> spec table (every pair, generically), the JSON-LD
object, and the on-page gallery. It then emits, per car, our STORED row beside
the LIVE page facts plus automated numeric diffs, so the council can judge
field-by-field whether our data faithfully represents the live listing.

Usage: python scripts/goonet_validate.py <clean.jsonl> <out.jsonl> [N] [seed]
"""
import json, re, sys, html as _html, time, threading, collections
from concurrent.futures import ThreadPoolExecutor
from curl_cffi import requests
# reuse the crawler's OWN normalizers so categorical fields are compared on a
# like-for-like basis (else "GASOLINE" vs normalized "Petrol" false-flags).
sys.path.insert(0, "scripts")
from goonet_crawl import _norm_fuel, _norm_trans, _norm_drive, _norm_steer, _title_case

INP = sys.argv[1]
OUT = sys.argv[2]
N   = int(sys.argv[3]) if len(sys.argv) > 3 else 250
SEED = int(sys.argv[4]) if len(sys.argv) > 4 else 12345

# ---- deterministic stratified sample (no Math.random equivalent needed) -------
rows = [json.loads(l) for l in open(INP, encoding="utf-8") if l.strip()]
by_make = collections.defaultdict(list)
for i, r in enumerate(rows):
    by_make[r.get("make") or "?"].append(i)
# round-robin across makes for coverage, deterministic order
order = []
makes = sorted(by_make)
mi = 0
# simple LCG for reproducible pseudo-shuffle of within-make picks
def lcg(x): return (1103515245 * x + 12345) & 0x7fffffff
state = SEED
picked = []
seen = set()
while len(picked) < min(N, len(rows)) and makes:
    m = makes[mi % len(makes)]
    lst = by_make[m]
    if lst:
        state = lcg(state)
        j = lst.pop(state % len(lst))
        if j not in seen:
            seen.add(j); picked.append(j)
    else:
        makes.remove(m); mi -= 1
    mi += 1
print(f"sampling {len(picked)} of {len(rows)} cars across {len(by_make)} makes", flush=True)

# ---- live fetch ---------------------------------------------------------------
SITE = "https://www.goo-net-exchange.com"
_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        _tl.s = requests.Session(impersonate="chrome")
        _tl.s.headers.update({"Referer": SITE, "Accept-Language": "en"})
    return _tl.s

def fetch(url):
    for _ in range(3):
        try:
            r = sess().get(url, timeout=30)
            if r.status_code == 200:
                return 200, r.text
            if r.status_code == 404:
                return 404, ""
            time.sleep(0.5)
        except Exception:
            time.sleep(0.5)
    return 0, ""

# ---- independent extractors (NOT the crawler's parser) ------------------------
def clean(s):
    return re.sub(r"\s+", " ", _html.unescape(re.sub(r"<[^>]+>", "", s or ""))).strip()

def spec_table(h):
    """Every <dt>label</dt><dd>value</dd> pair on the page, raw."""
    out = {}
    for m in re.finditer(r"<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>", h, re.S | re.I):
        k = clean(m.group(1)); v = clean(m.group(2))
        if k and k not in out:
            out[k] = v
    return out

def jsonld(h):
    for m in re.finditer(r'<script[^>]+application/ld\+json[^>]*>(.*?)</script>', h, re.S | re.I):
        try:
            obj = json.loads(m.group(1).strip())
            objs = obj if isinstance(obj, list) else [obj]
            for o in objs:
                if isinstance(o, dict) and (o.get("@type") in ("Car", "Vehicle", "Product") or "price" in json.dumps(o)):
                    return o
        except Exception:
            pass
    return {}

def gallery(h):
    urls = re.findall(r'picture\d?\.goo-net\.com/[a-z0-9]+/[a-z0-9]+/J/\d+A\d+[a-z]\d+\.jpg', h, re.I)
    # de-dup preserving order
    seen = set(); out = []
    for u in urls:
        if u not in seen:
            seen.add(u); out.append("https://" + u if not u.startswith("http") else u)
    return out

def to_int(s):
    m = re.search(r"[\d,]+", s or "")
    return int(m.group(0).replace(",", "")) if m else None

STORE_KEYS = ["stock_id","title","make","model","grade","year","mileage_km","fuel","transmission",
              "color","body_style","engine_cc","drive_type","doors","steering","price_jpy","price_usd",
              "country","product_link","front_image"]

def validate(idx):
    r = rows[idx]
    url = r.get("product_link")
    st, h = fetch(url)
    rec = {"stock_id": r.get("stock_id"), "url": url, "http": st,
           "stored": {k: r.get(k) for k in STORE_KEYS},
           "stored_img_count": len(r.get("images", []))}
    if st != 200:
        rec["status"] = "sold_or_gone" if st == 404 else "fetch_failed"
        return rec
    spec = spec_table(h); ld = jsonld(h); gal = gallery(h)
    rec["live_spec"] = spec
    rec["live_jsonld_price"] = ld.get("offers", {}).get("price") if isinstance(ld.get("offers"), dict) else ld.get("price")
    rec["live_jsonld_name"] = ld.get("name")
    rec["live_gallery_count"] = len(gal)
    rec["live_gallery_head"] = gal[:3]
    # automated diffs on the reliably-comparable numeric/text fields
    diffs = {}
    ly = spec.get("Month/Year") or spec.get("Model Year") or ""
    lyr = re.search(r"(19|20)\d{2}", ly)
    if lyr and r.get("year") and int(lyr.group(0)) != r.get("year"):
        diffs["year"] = {"stored": r.get("year"), "live": int(lyr.group(0)), "live_raw": ly}
    lm = to_int(spec.get("Mileage", ""))
    if lm is not None and r.get("mileage_km") is not None and abs(lm - r["mileage_km"]) > max(1, r["mileage_km"] * 0.02):
        diffs["mileage_km"] = {"stored": r.get("mileage_km"), "live": lm, "live_raw": spec.get("Mileage")}
    lp = to_int(str(rec["live_jsonld_price"] or ""))
    if lp and r.get("price_jpy") and abs(lp - r["price_jpy"]) > max(1, r["price_jpy"] * 0.02):
        diffs["price_jpy"] = {"stored": r.get("price_jpy"), "live": lp}
    # categorical: normalize the LIVE value the SAME way the crawler does, then compare
    for fld, lbl, norm in [("fuel","Fuel",_norm_fuel), ("transmission","Transmission",_norm_trans),
                           ("drive_type","Drive System",_norm_drive), ("steering","Steering",_norm_steer)]:
        lv = spec.get(lbl)
        if lv:
            expect = norm(lv)
            if expect and str(r.get(fld)) != str(expect):
                diffs[fld] = {"stored": r.get(fld), "live_raw": lv, "normalized_expected": expect}
    # identity: make/model should match the URL path (goo-net's own canonical tokens)
    um = re.search(r"/usedcars/([A-Z0-9_]+)/([A-Za-z0-9_%-]+)/", url or "")
    if um:
        exp_make = _title_case(um.group(1).replace("_", " "))
        exp_model = _title_case(um.group(2).replace("_", " ").replace("%20", " "))
        if str(r.get("make")) != str(exp_make):
            diffs["make"] = {"stored": r.get("make"), "expected": exp_make}
        if str(r.get("model")) != str(exp_model):
            diffs["model"] = {"stored": r.get("model"), "expected": exp_model}
    # gallery integrity: how many of the live gallery images did we keep, and is cover present
    live_set = set(gal)
    kept = [u for u in r.get("images", []) if u in live_set]
    rec["gallery_check"] = {"live_count": len(gal), "stored_count": len(r.get("images", [])),
                            "kept_from_live": len(kept),
                            "cover_is_00": bool(r.get("images") and re.search(r"00\.jpg$", r["images"][0]))}
    rec["auto_diffs"] = diffs
    rec["status"] = "checked_with_diffs" if diffs else "checked_clean"
    return rec

results = []
with ThreadPoolExecutor(max_workers=12) as ex:
    for i, rec in enumerate(ex.map(validate, picked)):
        results.append(rec)
        if (i + 1) % 25 == 0:
            print(f"  validated {i+1}/{len(picked)}", flush=True)

with open(OUT, "w", encoding="utf-8") as f:
    for rec in results:
        f.write(json.dumps(rec, ensure_ascii=False) + "\n")

# ---- summary ------------------------------------------------------------------
c = collections.Counter(r["status"] for r in results)
field_hits = collections.Counter()
for r in results:
    for k in (r.get("auto_diffs") or {}):
        field_hits[k] += 1
print("\n=== VALIDATION SUMMARY ===")
print("status:", dict(c))
print("field diffs:", dict(field_hits))
print(f"report -> {OUT}")
