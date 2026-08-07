#!/usr/bin/env python3
"""Isolate site-vs-code: fire N browse requests with C threads and NO tree logic.

The partitioner achieved 0.53 req/s per runner with 32 in-flight and I blamed the site --
but the same run logged only 3 x 429 across the whole fleet, which is not a throttled
fleet. This measures the raw achievable rate so the bottleneck is attributed by
measurement instead of assumption.
"""
import sys, time, threading, json
from concurrent.futures import ThreadPoolExecutor
from curl_cffi import requests as rq

CONC = int(sys.argv[1]) if len(sys.argv) > 1 else 32
N = int(sys.argv[2]) if len(sys.argv) > 2 else 300
A = "https://atvhunt.com/atv-utv-for-sale"
STATES = ("Alabama Alaska Arizona Arkansas California Colorado Connecticut Delaware Florida "
          "Georgia Idaho Illinois Indiana Iowa Kansas Kentucky Louisiana Maine Maryland "
          "Michigan Minnesota Missouri Montana Nebraska Nevada Ohio Oklahoma Oregon Texas "
          "Utah Vermont Virginia Washington Wisconsin Wyoming").split()

_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        _tl.s = rq.Session(impersonate="chrome")
    return _tl.s

codes, lock = {}, threading.Lock()
lat = []

def one(i):
    u = f"{A}/{STATES[i % len(STATES)]}?typeid={6 + i % 2}&year_from={1990 + i % 35}&year_to={1990 + i % 35}"
    t = time.time()
    try:
        r = sess().get(u, timeout=(8, 25), impersonate="chrome")
        c = r.status_code
    except Exception as e:
        c = type(e).__name__
    d = time.time() - t
    with lock:
        codes[str(c)] = codes.get(str(c), 0) + 1
        lat.append(d)

t0 = time.time()
with ThreadPoolExecutor(max_workers=CONC) as ex:
    list(ex.map(one, range(N)))
el = time.time() - t0
lat.sort()
out = {"conc": CONC, "n": N, "seconds": round(el, 1), "req_per_s": round(N / el, 2),
       "codes": codes,
       "latency_p50": round(lat[len(lat)//2], 2) if lat else None,
       "latency_p95": round(lat[int(len(lat)*0.95)], 2) if lat else None,
       "latency_max": round(lat[-1], 2) if lat else None}
print(json.dumps(out, indent=1))
open("rate.json", "w").write(json.dumps(out))
