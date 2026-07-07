#!/usr/bin/env python3
"""Re-enrich runner: for each product_link in a gz shard, GET the detail page,
re-parse with the IMPROVED parser (captures Repaired, fixed drive/doors/body,
cleaned make, FULL gallery), then HEAD the first-30 gallery images for their
byte sizes (for promo-stripping at cap 30). Distinct runner IP -> no throttle.
Outputs: rows-NN.jsonl (enriched) + sizes-NN.tsv (url<TAB>size)."""
import gzip, sys, json, time, threading
from concurrent.futures import ThreadPoolExecutor
sys.path.insert(0, "scripts")
from curl_cffi import requests
from goonet_crawl import parse_detail

SHARD = sys.argv[1]; ROWS_OUT = sys.argv[2]; SIZES_OUT = sys.argv[3]
POOL = int(sys.argv[4]) if len(sys.argv) > 4 else 24
CAP = 30
SITE = "https://www.goo-net-exchange.com"
links = [u for u in gzip.open(SHARD, "rt", encoding="utf-8").read().split("\n") if u]
print(f"shard {SHARD}: {len(links)} links, pool {POOL}", flush=True)

_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        _tl.s = requests.Session(impersonate="chrome")
        _tl.s.headers.update({"Referer": SITE, "Accept-Language": "en"})
    return _tl.s

def get(u):
    for _ in range(3):
        try:
            r = sess().get(u, timeout=30)
            if r.status_code == 200: return r.text
            if r.status_code == 404: return None
            time.sleep(0.5)
        except Exception:
            time.sleep(0.5)
    return None

def head(u):
    for _ in range(2):
        try:
            r = sess().head(u, timeout=15)
            if r.status_code == 200:
                cl = r.headers.get("Content-Length"); return int(cl) if cl else 0
        except Exception:
            time.sleep(0.3)
    return 0

def work(link):
    h = get(link)
    if not h:
        return (None, [])
    row = parse_detail(h, link)
    if not row:
        return (None, [])
    sizes = []
    for u in row.get("images", [])[1:CAP + 1]:
        sizes.append((u, head(u)))
    return (row, sizes)

done = 0
with open(ROWS_OUT, "w", encoding="utf-8") as rf, open(SIZES_OUT, "w", encoding="utf-8") as sf, \
     ThreadPoolExecutor(max_workers=POOL) as ex:
    for i in range(0, len(links), 2000):
        for row, sizes in ex.map(work, links[i:i + 2000]):
            if row:
                rf.write(json.dumps(row, ensure_ascii=False) + "\n")
                for u, s in sizes:
                    sf.write(f"{u}\t{s}\n")
            done += 1
        rf.flush(); sf.flush()
        print(f"  {done}/{len(links)}", flush=True)
print(f"DONE {done}", flush=True)
