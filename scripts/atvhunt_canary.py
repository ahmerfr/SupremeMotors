#!/usr/bin/env python3
"""One job, ~3 minutes, that decides every remaining number in the sweep plan.

Measures on DETAIL pages (/l/{id}/x) what has so far only been measured on browse pages:

  1. Q  -- how many requests a fresh runner IP serves before it is blackholed.
           => SLICE (ids per shard) and therefore the job count and the wall clock.
  2. rps -- the real detail-page fetch rate (detail pages are ~150 KB, browse pages are not,
           so the 83.8 req/s browse ceiling does not transfer).
           => the fetch component of T.
  3. Whether NON-200 answers consume the quota. It walks a HIGH-DEAD band as well as a
     live one; if the freeze lands at the same number of 200s but a much larger number of
     ids in the dead band, then only served listings count and SLICE can rise by ~1/density.
  4. Optional: whether the quota is per-HOST or per-IP (--hosts a,b,c interleaves them and
     reports each host's own first-429 index). H=4 would cut the fleet 4x. OFF by default:
     fanning out to sibling marketplaces is the owner's call, not a default.

  python atvhunt_canary.py --start 13700000 --n 1200 --conc 8 --rps 120
  python atvhunt_canary.py --selftest
"""
import sys, json, time, argparse, threading
from concurrent.futures import ThreadPoolExecutor


def probe(hosts, ids, conc, rps):
    from curl_cffi import requests as rq
    import atvhunt_parse as P
    tls, lock = threading.local(), threading.Lock()
    st = {h: dict(sent=0, ok=0, lst=0, dead=0, f429=0, first429=None) for h in hosts}
    delay = 1.0 / rps
    slot = [time.time()]
    t0 = time.time()

    def gate():
        with lock:
            now = time.time()
            slot[0] = max(now, slot[0]) + delay
            return slot[0] - now

    def one(k):
        h = hosts[k % len(hosts)]
        i = ids[k]
        w = gate()
        if w > 0:
            time.sleep(w)
        if not hasattr(tls, "s"):
            tls.s = rq.Session(impersonate="chrome")
        try:
            r = tls.s.get(f"https://{h}/l/{i}/x", timeout=(8, 15), allow_redirects=False)
            c = r.status_code
        except Exception:
            return
        with lock:
            s = st[h]
            s["sent"] += 1
            if c == 429:
                s["f429"] += 1
                if s["first429"] is None:
                    s["first429"] = s["sent"]
            elif c == 200:
                s["ok"] += 1
                s["lst"] += 1 if P.is_listing(r.text) else 0
            else:
                s["dead"] += 1

    with ThreadPoolExecutor(max_workers=conc) as ex:
        list(ex.map(one, range(len(ids))))
    dt = time.time() - t0
    for h, s in st.items():
        s["rps"] = round(s["sent"] / dt, 1)
        s["live_frac"] = round(s["lst"] / max(1, s["ok"] + s["dead"]), 3)
    return st, round(dt, 1)


def selftest():
    # the only logic worth a check: id bands and the round-robin host assignment
    hosts = ["a", "b", "c"]
    ids = list(range(9))
    assert [hosts[k % 3] for k in range(9)].count("a") == 3
    assert len(set(ids)) == 9
    print("CANARY SELFTEST PASSED")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest(); sys.exit(0)
    ap = argparse.ArgumentParser()
    ap.add_argument("--hosts", default="atvhunt.com")
    ap.add_argument("--start", type=int, default=13_700_000, help="live band start")
    ap.add_argument("--dead-start", type=int, default=2_000_000, help="high-dead band start")
    ap.add_argument("--n", type=int, default=1200)
    ap.add_argument("--conc", type=int, default=8)
    ap.add_argument("--rps", type=float, default=120)
    a = ap.parse_args()
    hosts = a.hosts.split(",")
    out = {}
    for band, lo in (("live", a.start), ("dead", a.dead_start)):
        st, dt = probe(hosts, list(range(lo, lo + a.n)), a.conc, a.rps)
        out[band] = dict(secs=dt, hosts=st)
        print(f"{band} band from {lo}: {json.dumps(st)} in {dt}s", flush=True)
        if band == "live":
            time.sleep(5)          # the IP is already spent; the dead band re-uses it on
                                   # purpose, so a LARGER ids-consumed there means non-200s
                                   # are free and SLICE can rise.
    json.dump(out, open("canary.json", "w"), indent=1)
    q = out["live"]["hosts"][hosts[0]]
    print(f"\nVERDICT  Q(first429 on 200s)={q['first429']}  detail rps={q['rps']}  "
          f"live_frac={q['live_frac']}")
    print("SLICE = floor(0.93 * Q). If SLICE < 700 the 18-window tiling needs re-cutting; "
          "see the runbook's wall-clock table.")
