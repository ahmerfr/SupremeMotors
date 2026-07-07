#!/usr/bin/env python3
"""Warm one manifest shard into Bunny perma-cache. For each image URL: download
it (goo-net direct; autowini needs Referer) and PUT the bytes into the pull-zone's
perma-cache storage path, so Bunny serves it FOREVER even if the source vanishes.

  path served at  https://<zone>.b-cdn.net/<PATH>
  stored at       <STORAGE_ZONE>/__bcdn_perma_cache__/<PZDIR>/<PATH>

Env: BUNNY_STORAGE_KEY (secret), STORAGE_ZONE, PZDIR, [REFERER].  Arg1: manifest.gz."""
import gzip, os, sys, threading, time
from urllib.parse import urlparse
from concurrent.futures import ThreadPoolExecutor
from curl_cffi import requests

SHARD = sys.argv[1]
POOL = int(sys.argv[2]) if len(sys.argv) > 2 else 25
KEY = os.environ["BUNNY_STORAGE_KEY"]
ZONE = os.environ.get("STORAGE_ZONE", "suprememotors-media")
PZDIR = os.environ["PZDIR"]
REFERER = os.environ.get("REFERER", "")
STORAGE = "https://storage.bunnycdn.com"

urls = [u for u in gzip.open(SHARD, "rt", encoding="utf-8").read().split("\n") if u]
print(f"shard {SHARD}: {len(urls)} images, pool {POOL}, pz {PZDIR}", flush=True)

_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        _tl.s = requests.Session(impersonate="chrome")
    return _tl.s

def warm(url):
    path = urlparse(url).path.lstrip("/")
    dst = f"{STORAGE}/{ZONE}/__bcdn_perma_cache__/{PZDIR}/{path}"
    hdr = {"Referer": REFERER} if REFERER else {}
    for _ in range(3):
        try:
            r = sess().get(url, headers=hdr, timeout=30)
            if r.status_code == 200 and len(r.content) > 512:
                p = sess().put(dst, data=r.content,
                               headers={"AccessKey": KEY, "Content-Type": r.headers.get("Content-Type", "image/jpeg")},
                               timeout=40)
                return 1 if p.status_code in (200, 201) else 0
            if r.status_code == 404:
                return 0
            time.sleep(0.4)
        except Exception:
            time.sleep(0.5)
    return 0

done = ok = 0
with ThreadPoolExecutor(max_workers=POOL) as ex:
    for i in range(0, len(urls), 5000):
        for res in ex.map(warm, urls[i:i + 5000]):
            done += 1; ok += res
        print(f"  {done}/{len(urls)} warmed={ok}", flush=True)
print(f"DONE {done} warmed {ok}", flush=True)
