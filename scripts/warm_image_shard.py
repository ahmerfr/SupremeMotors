#!/usr/bin/env python3
"""Warm one manifest shard by GETting each image THROUGH its Bunny pull-zone.

The correct durability mechanism: a GET to sm-<src>.b-cdn.net/<path> makes Bunny
pull the origin (goo-net directly; autowini via a Referer edge rule on the zone)
and — because Perma-Cache is enabled on the zone — persist a permanent copy into
the linked storage zone. After warming, images serve from YOUR storage forever,
surviving even if goo-net/autowini delete them.

(The earlier PUT-into-perma-cache approach did NOT work: Bunny only serves files
it perma-cached itself during a pull, not files manually PUT to that path.)

Env: CDN_HOST (e.g. sm-autowini.b-cdn.net).  Arg1: manifest.gz  Arg2: pool."""
import gzip, os, sys, re, threading, time
from concurrent.futures import ThreadPoolExecutor
from curl_cffi import requests

SHARD = sys.argv[1]
POOL = int(sys.argv[2]) if len(sys.argv) > 2 else 25
CDN_HOST = os.environ["CDN_HOST"]

urls = [u for u in gzip.open(SHARD, "rt", encoding="utf-8").read().split("\n") if u]
print(f"shard {SHARD}: {len(urls)} images -> {CDN_HOST}", flush=True)

_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        _tl.s = requests.Session(impersonate="chrome")
    return _tl.s

def warm(u):
    cdn = re.sub(r"https://[^/]+/", f"https://{CDN_HOST}/", u, count=1)
    for _ in range(4):
        try:
            r = sess().get(cdn, timeout=40)
            if r.status_code == 200 and len(r.content) > 512:
                return 1
            if r.status_code in (404, 410):
                return 0
            time.sleep(0.5)
        except Exception:
            time.sleep(0.6)
    return 0

done = ok = 0
with ThreadPoolExecutor(max_workers=POOL) as ex:
    for i in range(0, len(urls), 5000):
        for res in ex.map(warm, urls[i:i + 5000]):
            done += 1; ok += res
        print(f"  {done}/{len(urls)} ok={ok}", flush=True)
print(f"DONE {done} ok {ok}", flush=True)
