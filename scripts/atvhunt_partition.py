#!/usr/bin/env python3
"""Enumerate atvhunt's full catalogue by ADAPTIVE FILTER PARTITIONING.

atvhunt never paginates. There is no page/start/n/offset/limit param, no pager markup and
no XHR -- the browse form exposes filters only, and any filtered URL shows at most 24
listings. But it also reports its own result count:

    <h1> <b>177,161</b> ATVs and UTVs for sale</h1>

So the catalogue is enumerable by recursive subdivision: ask a slice how big it is; if it
is <= 24, take its ids; otherwise split it on the next filter dimension and recurse. Every
dimension used here is permitted by robots.txt (it disallows price_from/price_to/new/used/
sort/pricedeals/owner/dealer/start/n and /api/ -- none of which are needed).

  python atvhunt_partition.py out.txt [--part 0 --total 1] [--rps 5]
  python atvhunt_partition.py --selftest

Prints ids to out.txt, one per line. Emits progress + any slice it could not split below
25 (an under-count that would silently lose listings) to stderr.
"""
import sys, re, time, argparse, threading
from concurrent.futures import ThreadPoolExecutor

A = "https://atvhunt.com"
BROWSE = A + "/atv-utv-for-sale"
PAGE_CAP = 24                                  # server renders at most this many
COUNT_RE = re.compile(r"<h1>\s*<b>([\d,]+)</b>", re.I)
ID_RE = re.compile(r"/l/(\d+)/")

# Robots-permitted split dimensions, coarsest first. Each entry yields (param, [values]).
MAKES = ["Polaris", "Can-Am", "Honda", "Kawasaki", "CFMOTO", "Yamaha", "Suzuki", "Kayo",
         "Kymco", "SSR", "Segway", "Arctic Cat", "Argo", "Apollo", "Bennche", "DRR",
         "Hisun Motors", "Odes", "Tao Motor", "Tracker Off Road", "Trailmaster", "Vitacci"]
STATES = ("Alabama Alaska Arizona Arkansas California Colorado Connecticut Delaware Florida "
          "Georgia Guam Hawaii Idaho Illinois Indiana Iowa Kansas Kentucky Louisiana Maine "
          "Maryland Massachusetts Michigan Minnesota Mississippi Missouri Montana Nebraska "
          "Nevada New-Hampshire New-Jersey New-Mexico New-York North-Carolina North-Dakota "
          "Ohio Oklahoma Oregon Pennsylvania Puerto-Rico Rhode-Island South-Carolina "
          "South-Dakota Tennessee Texas Utah Vermont Virginia Washington West-Virginia "
          "Wisconsin Wyoming").split()
TYPEIDS = ["6", "7"]


def qs(f):
    return "&".join(f"{k}={v}" for k, v in sorted(f.items()) if v not in (None, ""))


def url_of(f):
    state = f.get("_state")
    base = f"{BROWSE}/{state}" if state else BROWSE
    q = qs({k: v for k, v in f.items() if not k.startswith("_")})
    return base + ("?" + q if q else "")


class Partitioner:
    def __init__(self, get, rps=5.0, cap=PAGE_CAP):
        self.get = get
        self.cap = cap
        self.lock = threading.Lock()
        self.ids = set()
        self.reqs = 0
        self.leaks = []          # slices still > cap with no dimension left to split

    def probe(self, f):
        body = self.get(url_of(f))
        with self.lock:
            self.reqs += 1
        m = COUNT_RE.search(body)
        n = int(m.group(1).replace(",", "")) if m else None
        found = set(ID_RE.findall(body))
        if found:
            with self.lock:
                self.ids |= found
        return n, found

    def dims(self, f):
        """Split dimensions still available for this slice, coarsest first."""
        out = []
        if "make" not in f:
            out.append(("make", MAKES))
        if "_state" not in f:
            out.append(("_state", STATES))
        if "typeid" not in f:
            out.append(("typeid", TYPEIDS))
        if "seatsid" not in f:
            out.append(("seatsid", ["1", "2", "3", "4", "5", "6"]))
        return out

    def walk(self, f, depth=0):
        n, found = self.probe(f)
        if n is None:
            return
        if n <= self.cap:
            return                              # ids already banked by probe()
        # numeric bisection on YEAR is unbounded-depth, so prefer it once coarse dims run out
        d = self.dims(f)
        if d:
            k, vals = d[0]
            for v in vals:
                self.walk({**f, k: v}, depth + 1)
            return
        lo, hi = int(f.get("year_from", 1980)), int(f.get("year_to", 2030))
        if lo < hi:
            mid = (lo + hi) // 2
            self.walk({**f, "year_from": lo, "year_to": mid}, depth + 1)
            self.walk({**f, "year_from": mid + 1, "year_to": hi}, depth + 1)
            return
        with self.lock:                          # nothing left to split: record the loss
            self.leaks.append((url_of(f), n))
        sys.stderr.write(f"LEAK {url_of(f)} still {n} > {self.cap}\n")


def selftest():
    """Synthetic catalogue that filters like the real site: every listing carries a make,
    state, typeid, seats and year, and EVERY filter actually narrows. A correct
    partitioner must recover all of them while asking far fewer questions than there are
    filter combinations."""
    mks, sts, yrs = MAKES[:4], STATES[:5], [2019, 2020, 2021]
    cat = []                                     # (id, make, state, typeid, seats, year)
    for i, (mk, st, t, se, yr) in enumerate(
            [(mk, st, t, se, yr) for mk in mks for st in sts for t in TYPEIDS
             for se in ("1", "2") for yr in yrs for _ in range(9)], start=1):
        cat.append((str(i), mk, st, t, se, yr))
    total = len(cat)

    def match(u):
        mk = re.search(r"make=([^&]+)", u)
        st = re.search(r"/atv-utv-for-sale/([A-Za-z\-]+)", u)
        t = re.search(r"typeid=(\d)", u)
        se = re.search(r"seatsid=(\d)", u)
        yf = re.search(r"year_from=(\d+)", u)
        yt = re.search(r"year_to=(\d+)", u)
        out = []
        for row in cat:
            _i, rmk, rst, rt, rse, ryr = row
            if mk and rmk.replace(" ", "+") != mk.group(1) and rmk != mk.group(1):
                continue
            if st and rst != st.group(1):
                continue
            if t and rt != t.group(1):
                continue
            if se and rse != se.group(1):
                continue
            if yf and ryr < int(yf.group(1)):
                continue
            if yt and ryr > int(yt.group(1)):
                continue
            out.append(_i)
        return out

    def fake_get(u):
        s = match(u)
        return (f"<h1> <b>{len(s):,}</b> ATVs and UTVs for sale</h1>"
                + "".join(f'<a href="/l/{i}/x">' for i in sorted(s, key=int)[:PAGE_CAP]))

    p = Partitioner(fake_get, cap=PAGE_CAP)
    p.walk({})
    want = {r[0] for r in cat}
    missed = want - p.ids
    assert not missed, f"missed {len(missed)} of {total} ids"
    assert not p.leaks, f"{len(p.leaks)} unsplittable slices"
    # The real cost bound is the FULL dimension product the walker can traverse (it does
    # not know which of the 22 makes / 52 states are empty until it asks). What must hold
    # is that it never approaches that, because an empty or small slice terminates at once.
    worst = len(MAKES) * len(STATES) * len(TYPEIDS) * 6
    assert p.reqs < worst / 4, f"{p.reqs} requests vs worst-case {worst} - not pruning"
    print(f"PARTITION SELFTEST PASSED ({len(p.ids)}/{total} ids recovered, 0 leaks, "
          f"{p.reqs} requests vs {worst} worst-case)")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest(); sys.exit(0)
    ap = argparse.ArgumentParser()
    ap.add_argument("out")
    ap.add_argument("--part", type=int, default=0)
    ap.add_argument("--total", type=int, default=1)
    ap.add_argument("--rps", type=float, default=5.0)
    a = ap.parse_args()

    from curl_cffi import requests as rq
    S = rq.Session(impersonate="chrome")
    delay = 1.0 / a.rps
    gate = threading.Lock()
    nxt = [0.0]

    def get(u):
        for _ in range(4):
            with gate:                           # shared token bucket -> steady global rate
                now = time.time()
                nxt[0] = max(now, nxt[0]) + delay
                wait = nxt[0] - now
            if wait > 0:
                time.sleep(wait)
            try:
                r = S.get(u, timeout=(8, 25), impersonate="chrome")
            except Exception:
                time.sleep(1); continue
            if r.status_code == 429:
                time.sleep(15); continue
            if r.status_code in (401, 403):
                sys.exit(f"FATAL 403 on {u} - this runner's IP is blocked")
            return r.text if r.status_code == 200 else ""
        return ""

    p = Partitioner(get, rps=a.rps)
    roots = [{"make": m} for m in MAKES] if a.total > 1 else [{}]
    for f in roots[a.part::a.total] if a.total > 1 else roots:
        p.walk(f)
    with open(a.out, "w", encoding="utf-8") as fh:
        fh.write("\n".join(sorted(p.ids, key=int)) + "\n")
    sys.stderr.write(f"part {a.part}/{a.total}: {len(p.ids)} ids, {p.reqs} requests, "
                     f"{len(p.leaks)} leaks\n")
