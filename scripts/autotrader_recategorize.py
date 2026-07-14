#!/usr/bin/env python3
"""Recovery: the fast-enrich (no --channel context) re-routed ~101k channel rows
into Cars/Commercial via the body-style router. The rows are intact (incl. full
galleries) and identifiable by updated_at>='2026-07-13'. This re-runs the SEARCH
per channel (curl_cffi, which passes Cloudflare), maps advertId->channel, and
UPDATEs ONLY category_id — preserving the enriched galleries.

Run:  python scripts/autotrader_recategorize.py
"""
import json, os, time, threading
from concurrent.futures import ThreadPoolExecutor
from curl_cffi import requests
import pymysql

CHANNELS = ["vans", "bikes", "motorhomes", "caravans", "farm", "plant", "trucks"]
GATEWAY = "https://www.autotrader.co.uk/at-gateway?opname=SearchResultsListingsGridQuery"
QUERY = open("resources/graphql/autotraderuk-search.graphql").read()
HEAD = {"Content-Type": "application/json", "Origin": "https://www.autotrader.co.uk",
        "Referer": "https://www.autotrader.co.uk/", "x-sauron-app-name": "product-page-web",
        "x-sauron-app-version": "1.0.0"}

_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        _tl.s = requests.Session(impersonate="chrome")
        try: _tl.s.get("https://www.autotrader.co.uk/", timeout=20)
        except Exception: pass
    return _tl.s

def fetch(channel, page, min_p, max_p):
    filters = [{"filter": "postcode", "selected": ["SW1A 1AA"]},
               {"filter": "price_search_type", "selected": ["total"]}]
    if min_p is not None: filters.append({"filter": "min_price", "selected": [str(min_p)]})
    if max_p is not None: filters.append({"filter": "max_price", "selected": [str(max_p)]})
    body = [{"operationName": "SearchResultsListingsGridQuery", "variables": {
        "filters": filters, "channel": channel, "page": page, "sortBy": "relevance",
        "listingType": None, "searchId": "00000000-0000-0000-0000-000000000000",
        "featureFlags": []}, "query": QUERY}]
    for _ in range(3):
        try:
            r = sess().post(GATEWAY, headers=HEAD, data=json.dumps(body), timeout=30)
            if r.status_code == 200:
                j = r.json()[0]
                lst = (j.get("data", {}).get("searchResults") or {}).get("listings", [])
                return [x.get("advertId") for x in lst if x.get("advertId")]
        except Exception:
            time.sleep(0.5)
    return []

def bands_for(ch):
    p = f"db-export/atuk-{ch}-shards.json"
    if os.path.exists(p):
        return [b for b in json.load(open(p))["bands"] if b.get("count", 0) > 0 or b.get("min") is None]
    return [{"min": None, "max": None, "count": 2400}]   # plant etc. fit under the cap

id2ch = {}
_lock = threading.Lock()

def sweep_band(ch, b):
    """Paginate a band until an empty page (crawl's own stop condition), cap 100."""
    local = []
    for pg in range(1, 101):
        ids = fetch(ch, pg, b.get("min"), b.get("max"))
        if not ids:
            break
        local += ids
    with _lock:
        for i in local:
            id2ch[i] = ch

for ch in CHANNELS:
    bands = bands_for(ch)
    with ThreadPoolExecutor(max_workers=8) as ex:
        list(ex.map(lambda b: sweep_band(ch, b), bands))
    print(f"{ch}: {sum(1 for v in id2ch.values() if v == ch)} advert ids", flush=True)

# UPDATE category_id per channel (only the recent channel rows; preserves galleries)
conn = pymysql.connect(host="127.0.0.1", port=3307, user="root", db="supreme_motors", charset="utf8mb4")
cur = conn.cursor()
catid = {}
for ch in CHANNELS:
    cur.execute("SELECT id FROM categories WHERE cat_title=%s AND type='category' LIMIT 1", (ch.capitalize(),))
    r = cur.fetchone(); catid[ch] = r[0] if r else None

total = 0
for ch in CHANNELS:
    if not catid[ch]:
        print(f"  !! no category for {ch}"); continue
    links = [f"https://www.autotrader.co.uk/car-details/{i}" for i, c in id2ch.items() if c == ch]
    for i in range(0, len(links), 5000):
        batch = links[i:i + 5000]
        ph = ",".join(["%s"] * len(batch))
        cur.execute(f"UPDATE products SET category_id=%s WHERE website='autotraderuk' "
                    f"AND updated_at>='2026-07-13' AND product_link IN ({ph})", [catid[ch]] + batch)
        total += cur.rowcount
    conn.commit()
    print(f"  {ch}: category restored", flush=True)
print(f"RECATEGORIZE DONE — {total} rows fixed")
conn.close()
