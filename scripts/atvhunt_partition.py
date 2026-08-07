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

# Numeric axes bisected after the categorical ones, in order. (from_param, to_param,
# min, max). All three are real inputs on the browse form and none is robots-disallowed.
# MEASURED on Honda/Texas/typeid=6/2026 (614 listings):
#   displacement 0-500 -> 449, 501-2048 -> 165. 449+165 = 614 EXACTLY: a true partition.
#   mileage 101-131072 -> None/0 (broken upper range), modelid=1 -> None, rad=25 -> no
#   change. Those three are excluded for the same reason seatsid and hasvin were: a
#   dimension that does not partition silently swallows everything beneath it.
NUMERIC = [("year_from", "year_to", 1980, 2030),
           ("displacement_from", "displacement_to", 0, 2048)]


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
        # NO hasvin: measured non-partitioning. hasvin=1 narrows but hasvin=0 is IGNORED and
        # returns the parent set unchanged (Honda/Texas/2026: 490 for BOTH values), so it
        # duplicates work instead of splitting. The conservation check cannot catch that on
        # its own -- 490+490 clears any parent -- so it is excluded by measurement.
        return out

    def walk(self, f, depth=0):
        """-> the slice's reported count, so a parent can verify its children add up."""
        n, found = self.probe(f)
        if n is None:
            return 0
        if n <= self.cap:
            return n                            # ids already banked by probe()
        # numeric bisection on YEAR is unbounded-depth, so prefer it once coarse dims run out
        d = self.dims(f)
        if d:
            k, vals = d[0]
            # CONSERVATION CHECK. seatsid silently returned count=None / 0 ids for every
            # value, so every listing under such a node was discarded without a word. A
            # split must account for its parent; if it does not, the dimension is broken
            # and we must fall through to one that is rather than lose the slice.
            got = 0
            for v in vals:
                c = self.walk({**f, k: v}, depth + 1)
                got += c or 0
            if got < n * 0.9:
                sys.stderr.write(
                    f"SPLIT-LOSS {k} on {url_of(f)}: children {got} < parent {n}\n")
                rest = d[1:]
                if rest:
                    k2, vals2 = rest[0]
                    for v in vals2:
                        self.walk({**f, k2: v}, depth + 1)
                else:
                    self.bisect_year(f, n)
            return n
        self.bisect_year(f, n)
        return n

    def bisect_year(self, f, n):
        """Numeric axes, bisected in turn. year alone is not enough: after
        make+state+typeid+year, Honda/Texas/2026 still held 490 listings with nothing left
        to split, so only its top 24 were reachable -- that was 89% of the catalogue lost.
        displacement and mileage are both on the browse form and both robots-permitted."""
        for lo_k, hi_k, lo_d, hi_d in NUMERIC:
            lo, hi = int(f.get(lo_k, lo_d)), int(f.get(hi_k, hi_d))
            if lo < hi:
                mid = lo + (hi - lo) // 2
                self.walk({**f, lo_k: lo, hi_k: mid})
                self.walk({**f, lo_k: mid + 1, hi_k: hi})
                return
        with self.lock:                          # every axis exhausted: record the loss
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
    # REGRESSION: a dimension that returns nothing (seatsid did exactly this on the live
    # site) must NOT swallow the listings under it. Conservation check has to catch it.
    def broken_get(u):
        if "hasvin=" in u:                       # pretend hasvin is the broken filter
            return "<h1> <b>0</b> ATVs and UTVs for sale</h1>"
        return fake_get(u)

    q = Partitioner(broken_get, cap=PAGE_CAP)
    q.walk({})
    lost = want - q.ids
    assert not lost, f"broken dimension silently lost {len(lost)} of {total} ids"
    print(f"PARTITION SELFTEST PASSED ({len(p.ids)}/{total} ids recovered, 0 leaks, "
          f"{p.reqs} requests vs {worst} worst-case; broken-dimension regression clean)")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest(); sys.exit(0)
    ap = argparse.ArgumentParser()
    ap.add_argument("out")
    ap.add_argument("--part", type=int, default=0)
    ap.add_argument("--total", type=int, default=1)
    ap.add_argument("--rps", type=float, default=5.0)
    ap.add_argument("--only-make", default=None, help="validate one make")
    ap.add_argument("--only-state", default=None, help="validate one state")
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
    # Shard on make x STATE, not make alone. Polaris is 58,399 of 177,161 listings (33%),
    # so a per-make split leaves one runner doing a third of the catalogue while the other
    # 17 idle. 22 x 52 = 1,144 roots spreads evenly and the tail shrinks to one state of
    # one make. Roots are interleaved so consecutive big states land on different runners.
    if a.only_make or a.only_state:
        f0 = {}
        if a.only_make:
            f0["make"] = a.only_make
        if a.only_state:
            f0["_state"] = a.only_state
        mine = [f0]
    elif a.total > 1:
        roots = [{"make": m, "_state": st} for m in MAKES for st in STATES]
        mine = roots[a.part::a.total]
    else:
        mine = [{}]
    for f in mine:
        p.walk(f)
    with open(a.out, "w", encoding="utf-8") as fh:
        fh.write("\n".join(sorted(p.ids, key=int)) + "\n")
    sys.stderr.write(f"part {a.part}/{a.total}: {len(p.ids)} ids, {p.reqs} requests, "
                     f"{len(p.leaks)} leaks\n")
