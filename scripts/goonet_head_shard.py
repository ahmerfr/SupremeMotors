#!/usr/bin/env python3
"""Runner-side HEAD sizer. Reads a gzipped URL list, HEADs each via curl_cffi
(Chrome TLS impersonation, so the image CDN doesn't 429 on fingerprint), writes
`url<TAB>size` lines. Each runner is a distinct egress IP -> no single-IP throttle."""
import gzip, sys, time, threading
from concurrent.futures import ThreadPoolExecutor
from curl_cffi import requests

SHARD = sys.argv[1]              # scripts/size-shards/shard-NN.txt.gz
OUT   = sys.argv[2]              # sizes-NN.tsv
POOL  = int(sys.argv[3]) if len(sys.argv) > 3 else 64

urls = [u for u in gzip.open(SHARD, "rt", encoding="utf-8").read().split("\n") if u]
print(f"shard {SHARD}: {len(urls)} urls, pool {POOL}", flush=True)

_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        _tl.s = requests.Session(impersonate="chrome")
    return _tl.s

def hd(u):
    for _ in range(3):
        try:
            r = sess().head(u, timeout=15)
            if r.status_code == 200:
                cl = r.headers.get("Content-Length")
                return (u, int(cl) if cl else 0)
            time.sleep(0.3)
        except Exception:
            time.sleep(0.3)
    return (u, 0)

done = 0
with open(OUT, "w", encoding="utf-8") as fh, ThreadPoolExecutor(max_workers=POOL) as ex:
    for i in range(0, len(urls), 5000):
        for u, s in ex.map(hd, urls[i:i+5000]):
            fh.write(f"{u}\t{s}\n"); done += 1
        fh.flush()
        print(f"  {done}/{len(urls)}", flush=True)
print(f"DONE {done}", flush=True)
