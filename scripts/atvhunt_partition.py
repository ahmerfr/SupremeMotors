#!/usr/bin/env python3
"""Enumerate atvhunt's full catalogue by ADAPTIVE FILTER PARTITIONING -- concurrent BFS.

atvhunt never paginates. There is no page/start/n/offset/limit param, no pager markup and
no XHR -- the browse form exposes filters only, and any filtered URL shows at most 24
listings. But it also reports its own result count:

    <h1> <b>177,161</b> ATVs and UTVs for sale</h1>

So the catalogue is enumerable by recursive subdivision: ask a slice how big it is; if it
is <= 24 take its ids, else split it on a filter dimension and ask again.

WHAT CHANGED vs the DFS version (and why every change is forced by a measurement):

 1. CONCURRENT BFS WORK QUEUE, not recursive DFS. The old walker held one socket per
    runner, so 18 runners x 10 rps delivered 0.33-0.88 req/s each -- 3.3-8.8% of the
    authorised 180 req/s. Recursion cannot live inside a bounded pool (a parent blocking
    on its children pins a worker and deadlocks), so the tree is an explicit queue of
    nodes and the parent/child join is done by a fan-in counter. Same tree, same
    conservation rule, same leak definition -- only the scheduling changed.
 2. ADAPTIVE DIMENSION CHOICE. Attempt 6 fanned 122 models onto cells the state filter
    had already cut to ~7 listings: 25,064 probes at 1.09 ids/request. A k-way fan-out is
    only worth k probes while the children stay big, so a node picks the WIDEST option
    that still satisfies n >= 24*k and otherwise takes the cheapest (a 2-way bisect).
 3. UPPER conservation bound as well as lower. The old check (got >= n*0.9) could not
    catch a non-partitioning dimension -- hasvin returned 490 for BOTH values and
    490+490 clears any parent. got > n*1.05 now means "this dimension is not a
    partition"; three such verdicts blacklist it fleet-wide. This is what makes it safe
    to TRY price_from/price_to without having verified them first.
 4. NO SILENT DROPS. `if n is None: return 0` abandoned whole subtrees with no log line,
    no counter and a clean exit code -- 60 of 63 roots per shard vanished that way in
    attempt 6. A countless node is retried, then logged as DROP and reported as 0 to its
    parent so the conservation check re-splits the slice on another dimension.
 5. ROOTS = state x typeid, LPT-balanced by a measured census. state sums to 177,184
    (100.0% exact) and typeid 6+7 sums to 177,184; make sums to only 175,350 (99.0%), so
    make-rooted runs can never reach 1,811 listings. The census costs 104 probes/runner
    and doubles as level-0 harvesting.
 6. LEAK HARVEST. 666 fully-pinned cells held 41,055 listings in attempt 5 -- 47% of
    everything it reached. sort= (robots-disallowed, owner-authorised) is the only known
    way to see past a cell's top-24; it is CALIBRATED at the first leak (keep only orders
    that return the same count and new ids) so a wrong guess costs 12 requests once, not
    12 per leak. Then q= over tokens mined from the cell's own /l/{id}/{slug} links.

  python atvhunt_partition.py out.txt [--part 0 --total 18] [--rps 10] [--conc 32]
                                      [--budget 1020] [--root make=Polaris,_state=Texas]
  python atvhunt_partition.py --selftest

Writes ids to out.txt (one per line) and <out>-stats.json, ALWAYS -- including on a
budget overrun -- so a shard that runs long still ships what it found.
"""
import sys, os, re, json, time, queue, argparse, threading

A = "https://atvhunt.com"
BROWSE = A + "/atv-utv-for-sale"
PAGE_CAP = 24                                  # server renders at most this many
COUNT_RE = re.compile(r"<h1>\s*<b>([\d,]+)</b>", re.I)
ID_RE = re.compile(r"/l/(\d+)/")
SLUG_RE = re.compile(r"/l/\d+/([a-z0-9][a-z0-9\-]{3,80})")

TOL_LO, TOL_HI = 0.90, 1.05                    # children must account for their parent
DEAD_AFTER = 3                                 # overcounts before a dimension is dropped
HARVEST_Q = 12                                 # q= tokens tried per leak cell

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

# Numeric axes, (from_param, to_param, min, max). MEASURED on Honda/Texas/typeid=6/2026
# (614 listings): displacement 0-500 -> 449, 501-2048 -> 165, sum 614 EXACTLY. mileage is
# NOT here: ~90% of listings have a NULL mileage and any mileage filter drops them (a
# 1,564 cell filtered 0-99999 gives 163). seatsid/hasvin/rad are excluded for the same
# reason -- a dimension that does not partition swallows everything beneath it.
NUM_MID = [("displacement_from", "displacement_to", 0, 2048),
           ("year_from", "year_to", 1980, 2030)]
# price is robots-disallowed and OWNER-AUTHORISED. It is the only dimension that splits
# the identical-dealer-stock cells the other six cannot (projected: unreachable listings
# 52,221 -> 9,891). It goes LAST: a bisect axis placed high multiplies every subtree
# beneath it (measured 156,165 requests vs 81,362 for the same coverage). Rule 3 above
# retires it automatically if the site turns out to ignore it.
NUM_LAST = [("price_from", "price_to", 0, 262144)]

# sort= is robots-disallowed, owner-authorised and UNVERIFIED. Candidates only; the real
# set is decided at runtime by calibrate_sorts().
SORTS = ["price_asc", "price_desc", "year_asc", "year_desc", "newest", "oldest",
         "mileage_asc", "distance", "1", "2", "3", "4"]
STOPWORDS = set("for sale new used atv utv side and the with only".split())


def qs(f):
    return "&".join(f"{k}={str(v).replace(' ', '+')}"
                    for k, v in sorted(f.items()) if v not in (None, ""))


def url_of(f):
    state = f.get("_state")
    base = f"{BROWSE}/{state}" if state else BROWSE
    q = qs({k: v for k, v in f.items() if not k.startswith("_")})
    return base + ("?" + q if q else "")


def lpt(items, bins):
    """Longest-processing-time bin packing. items = [(size, obj)] -> `bins` lists.

    Deterministic, so all 18 runners compute the same packing from the same census with
    no coordination. Static roots[N::18] left one shard running 6,974 s alone while the
    other 17 had exited -- 2.32x makespan tax on a fleet whose rate budget is global.
    """
    out, load = [[] for _ in range(bins)], [0] * bins
    for sz, obj in sorted(items, key=lambda x: -x[0]):
        i = load.index(min(load))
        out[i].append(obj)
        load[i] += sz
    return out


def tokens(body, f):
    """Vocabulary mined from the cell's own listing links. /l/{id}/{slug} carries the
    title, so a leak cell hands us the exact words that distinguish the units inside it --
    no vocabulary file to ship and no tokens that are irrelevant to this slice."""
    mk = str(f.get("make", "")).lower().replace(" ", "-")
    seen = []
    for slug in SLUG_RE.findall(body):
        for w in slug.split("-"):
            if len(w) < 3 or w.isdigit() or w in STOPWORDS or (mk and w in mk):
                continue
            if w not in seen:
                seen.append(w)
    return seen


class Node:
    """One slice of the catalogue. `opts` is the ordered list of ways it can be split;
    `di` is which one is currently in flight; `pending`/`got` are the fan-in join; `oi` is
    which of the parent's options created this node, so a child can tell that its parent
    has since abandoned that split; `parked` holds children waiting on this node's
    verdict."""
    __slots__ = ("f", "parent", "n", "opts", "di", "got", "pending", "tries", "leaf",
                 "oi", "parked", "body")

    def __init__(self, f, parent=None, leaf=False):
        self.f, self.parent, self.leaf = f, parent, leaf
        self.n = self.di = self.got = self.pending = self.tries = 0
        self.oi = parent.di if parent is not None else 0
        self.opts, self.parked, self.body = None, [], None


class Partitioner:
    def __init__(self, get, conc=32, cap=PAGE_CAP, deadline=float("inf")):
        self.get, self.cap, self.conc, self.deadline = get, cap, conc, deadline
        self.lock = threading.Lock()      # ids + counters
        self.jlock = threading.Lock()     # fan-in bookkeeping
        self.mlock = threading.Lock()     # model cache, HELD ACROSS THE FETCH so 32
        self.slock = threading.Lock()     # threads do not all fetch the same selector
        self.q = queue.Queue()
        self.ids, self.reqs, self.out, self.errs = set(), 0, 0, 0
        self.leaks, self.drops, self.dead, self.bad = [], [], set(), {}
        self.sorts = None                 # None = sort= not calibrated yet
        self._models = {}

    # ---- network ---------------------------------------------------------------
    def probe(self, f):
        body = self.get(url_of(f))
        found = set(ID_RE.findall(body))
        with self.lock:
            self.reqs += 1
            self.ids |= found
        m = COUNT_RE.search(body)
        return (int(m.group(1).replace(",", "")) if m else None), body

    def models_for(self, make):
        """Model ids from the site's own model-selector modal. Values look like
        data-id="vm:42587" and the browse param that consumes them is modelid -- with the
        COLON kept (modelid=42587 and modelid=vm42587 both return nothing; modelid=vm:42587
        returns 940 for Polaris Outlaw, matching the selector's own printed count)."""
        with self.mlock:
            if make not in self._models:
                body = self.get(f"{A}/model-selector?make={make.replace(' ', '+')}")
                with self.lock:
                    self.reqs += 1
                self._models[make] = sorted(set(re.findall(r'data-id="(vm:\d+)"', body)))
                sys.stderr.write(f"models({make}) = {len(self._models[make])}\n")
            return self._models[make]

    # ---- the tree --------------------------------------------------------------
    def _num(self, out, f, spec):
        lo_k, hi_k, lo_d, hi_d = spec
        lo, hi = int(f.get(lo_k, lo_d)), int(f.get(hi_k, hi_d))
        if lo < hi:
            mid = lo + (hi - lo) // 2
            out.append((lo_k, [{**f, lo_k: lo, hi_k: mid},
                               {**f, lo_k: mid + 1, hi_k: hi}]))

    def dims(self, f):
        """Every split still available for this slice, as (name, [child filters])."""
        out = []
        if "make" not in f:
            out.append(("make", [{**f, "make": m} for m in MAKES]))
        if "make" in f and "modelid" not in f:
            ms = self.models_for(f["make"])
            if ms:
                out.append(("modelid", [{**f, "modelid": m} for m in ms]))
        for spec in NUM_MID:
            self._num(out, f, spec)
        if "typeid" not in f:
            out.append(("typeid", [{**f, "typeid": t} for t in TYPEIDS]))
        if "_state" not in f:
            out.append(("_state", [{**f, "_state": s} for s in STATES]))
        for spec in NUM_LAST:
            self._num(out, f, spec)
        return [o for o in out if o[0] not in self.dead]

    def expand(self, nd):
        if nd.opts is None:
            o = self.dims(nd.f)
            # WIDEST option that still leaves its children above the 24 cap, else the
            # NARROWEST (a 2-way bisect). A 22-way fan-out onto a 77-listing cell buys
            # 3.5 ids/request; two bisects buy 11. Stable sort keeps dims() order on ties.
            o.sort(key=lambda x: (0, -len(x[1])) if nd.n >= self.cap * len(x[1])
                   else (1, len(x[1])))
            nd.opts = o
        while nd.di < len(nd.opts):
            name, kids = nd.opts[nd.di]
            if name in self.dead or not kids:
                nd.di += 1
                continue
            with self.jlock:                     # set BEFORE any child can complete
                nd.got, nd.pending = 0, len(kids)
            for cf in kids:
                self.push(Node(cf, nd))
            return
        self.leak(nd)

    def done(self, nd, n):
        """Credit nd's count to its parent and, when the last sibling lands, run the
        conservation check. Called the moment nd is PROBED, not when its subtree finishes:
        the check has only ever compared reported counts, and knowing them early is what
        stops an ignored axis from exploding before the verdict. A bisect on a param the
        site ignores returns the parent count in BOTH halves, so the old finish-first join
        built 2^18 nodes before anyone noticed -- it is also why walk() could hand a parent
        full credit for a subtree that had been blackholed."""
        p = nd.parent
        if p is None:
            return
        with self.jlock:
            p.got += n
            p.pending -= 1
            if p.pending:
                return
            got, want, name = p.got, p.n, p.opts[p.di][0]
        if want * TOL_LO <= got <= want * TOL_HI:
            p.body = None                        # p is finished; drop its page
            for c in self.unpark(p):             # verdict is in: the full child may go on
                self.expand(c)
            return
        if got > want * TOL_HI:
            # NOT A PARTITION. hasvin returned 490 for both values on a 490 parent, so the
            # lower bound alone waved it through and the slice was crawled twice instead
            # of split. Three verdicts and the dimension is retired for this runner.
            sys.stderr.write(f"OVERCOUNT {name} on {url_of(p.f)}: {got} > {want}\n")
            with self.lock:
                self.bad[name] = self.bad.get(name, 0) + 1
                if self.bad[name] >= DEAD_AFTER and name not in self.dead:
                    self.dead.add(name)
                    sys.stderr.write(f"DEAD-DIM {name} does not partition - dropped\n")
        else:
            sys.stderr.write(f"SPLIT-LOSS {name} on {url_of(p.f)}: {got} < {want}\n")
        p.di += 1                                # this split is abandoned; its parked
        self.unpark(p)                           # children are dropped, and any already
        self.expand(p)                           # in flight stop at the nd.oi check

    def unpark(self, p):
        with self.jlock:
            out, p.parked = p.parked, []
        return out

    def leak(self, nd):
        with self.lock:
            self.leaks.append((url_of(nd.f), nd.n))
        sys.stderr.write(f"LEAK {url_of(nd.f)} still {nd.n} > {self.cap}\n")
        for vf in self.harvest(nd):
            self.push(Node(vf, None, leaf=True))   # leaf: bank ids, never expand
        nd.body = None

    def harvest(self, nd):
        """Extra VIEWS of an unsplittable cell. Both levers overlap rather than partition,
        which is fine here: accounting is already abandoned, we only want ids."""
        body = nd.body or ""
        out = [{**nd.f, "sort": s} for s in self.calibrate_sorts(nd, body)]
        out += [{**nd.f, "q": t} for t in tokens(body, nd.f)[:HARVEST_Q]]
        return out

    def calibrate_sorts(self, nd, body):
        """sort= is documented nowhere and disallowed by robots (owner-authorised). Try
        the candidates ONCE, on the first leak, and keep only those that leave the count
        alone (so it really is a sort, not a filter) while showing ids the default order
        did not. If the site ignores sort= entirely this returns [] and costs 12 requests
        for the whole run."""
        if self.sorts is not None:
            return self.sorts
        with self.slock:
            if self.sorts is not None:
                return self.sorts
            base, keep = set(ID_RE.findall(body)), []
            for s in SORTS:
                n2, b2 = self.probe({**nd.f, "sort": s})
                if n2 == nd.n and (set(ID_RE.findall(b2)) - base):
                    keep.append(s)
            self.sorts = keep
            sys.stderr.write(f"sort= calibration: {len(keep)} usable {keep}\n")
            return keep

    # ---- the pool --------------------------------------------------------------
    def push(self, nd):
        with self.jlock:
            self.out += 1
        self.q.put(nd)

    def visit(self, nd):
        if time.time() > self.deadline:
            return                          # budget spent: drain, do not re-fan
        n, body = self.probe(nd.f)
        if n is None:
            nd.tries += 1
            if nd.tries < 3:
                return self.push(nd)
            # NEVER a silent 0. The old `if n is None: return 0` abandoned the subtree
            # with no log line and no counter; 13 of 14 shards lost 60 of 63 roots to it
            # and still printed a clean summary.
            with self.lock:
                self.drops.append(url_of(nd.f))
            sys.stderr.write(f"DROP {url_of(nd.f)} no count after 3 tries\n")
            return self.done(nd, 0)
        if nd.leaf:
            return                          # ids already banked by probe()
        self.done(nd, n)                    # report BEFORE expanding -- see done()
        if n <= self.cap:
            return
        nd.n, nd.body = n, body             # kept until this node leaks or is accounted
        p = nd.parent                       # for; the leak harvest needs the page
        if p is not None:
            if nd.oi != p.di:
                return          # parent abandoned this split and re-fanned the WHOLE
                                # slice on another dimension; carrying on here is pure
                                # duplicate work (the old SPLIT-LOSS path did exactly
                                # that and doubled the request count for the subtree)
            if n == p.n:
                # As big as its parent: either the sibling is empty (a legitimate
                # narrowing) or the axis is ignored and BOTH halves are full. Wait for the
                # verdict. Without this the tree doubles once per level while the verdict
                # stays one level behind -- 59 wasted probes in the fixture, 2^18 live.
                with self.jlock:
                    if p.pending:
                        p.parked.append(nd)
                        return
        self.expand(nd)

    def worker(self):
        while True:
            nd = self.q.get()
            if nd is None:
                return
            try:
                self.visit(nd)
            except Exception as e:
                with self.lock:                 # a bug, not a network fault (probe()
                    self.errs += 1              # swallows those) -- must reach stats.json
                sys.stderr.write(f"ERR {url_of(nd.f)}: {e!r}\n")
            finally:
                with self.jlock:
                    self.out -= 1
                    fin = self.out == 0
                if fin:                     # nothing queued, nothing in flight
                    for _ in range(self.conc):
                        self.q.put(None)

    def run(self, roots):
        if not roots:
            return                          # empty bin: workers would block on get() forever
        for f in roots:
            self.push(Node(f))
        ts = [threading.Thread(target=self.worker, daemon=True) for _ in range(self.conc)]
        for t in ts:
            t.start()
        for t in ts:
            t.join()

    def census(self, roots):
        """Size every root concurrently. Costs 104 probes and is not waste: the probes
        bank ids, and roots already <= 24 need no traversal at all."""
        from concurrent.futures import ThreadPoolExecutor
        with ThreadPoolExecutor(max_workers=self.conc) as ex:
            return list(ex.map(lambda f: ((self.probe(f)[0] or 0), f), roots))


def selftest():
    """Synthetic catalogue that filters like the real site. Covers the fan-in join, the
    conservation check in both directions, dead-dimension retirement and termination."""
    mks, sts, yrs = MAKES[:4], STATES[:5], [2019, 2020, 2021]
    # 12 per (make, state, type, year) so a (make, state, year) cell is exactly 24: the
    # broken-dimension regression below needs typeid to be redundant, not load-bearing.
    cat = [(str(i), mk, st, t, yr) for i, (mk, st, t, yr) in enumerate(
        [(mk, st, t, yr) for mk in mks for st in sts for t in TYPEIDS for yr in yrs
         for _ in range(12)], start=1)]
    want = {r[0] for r in cat}

    def match(u):
        mk = re.search(r"make=([^&]+)", u)
        st = re.search(r"/atv-utv-for-sale/([A-Za-z\-]+)", u)
        t = re.search(r"typeid=(\d)", u)
        yf, yt = re.search(r"year_from=(\d+)", u), re.search(r"year_to=(\d+)", u)
        out = []
        for _i, rmk, rst, rt, ryr in cat:
            if mk and rmk.replace(" ", "+") != mk.group(1) and rmk != mk.group(1):
                continue
            if st and rst != st.group(1):
                continue
            if t and rt != t.group(1):
                continue
            if yf and ryr < int(yf.group(1)):
                continue
            if yt and ryr > int(yt.group(1)):
                continue
            out.append(_i)
        return out

    def fake_get(u):
        if "/model-selector" in u:
            return ""                                  # no models in the fixture
        s = match(u)
        return (f"<h1> <b>{len(s):,}</b> ATVs and UTVs for sale</h1>"
                + "".join(f'<a href="/l/{i}/2026-test-ranger-xp">' for i in
                          sorted(s, key=int)[:PAGE_CAP]))

    p = Partitioner(fake_get, conc=8)
    p.run([{}])
    assert not (want - p.ids), f"missed {len(want - p.ids)} of {len(cat)} ids"
    assert not p.leaks, f"{len(p.leaks)} unsplittable slices"
    assert not p.drops, f"{len(p.drops)} dropped slices"
    # displacement and price are IGNORED by the fixture, so both halves return the parent
    # -> got == 2n. The upper conservation bound must catch that and retire them; the old
    # lower-bound-only check would have waved it through and bisected 18 levels deep.
    assert "displacement_from" in p.dead and "price_from" in p.dead, p.dead
    worst = len(MAKES) * len(STATES) * len(TYPEIDS) * 6
    assert p.reqs < worst / 4, f"{p.reqs} requests vs worst-case {worst} - not pruning"

    # REGRESSION: a dimension that reports nothing (seatsid did exactly this live) must
    # not swallow the listings beneath it -- the walker has to fall through to one that
    # works. Here typeid is the broken one.
    def broken_get(u):
        return ("<h1> <b>0</b> ATVs and UTVs for sale</h1>" if "typeid=" in u
                else fake_get(u))

    q = Partitioner(broken_get, conc=8)
    q.run([{}])
    assert not (want - q.ids), f"broken dimension lost {len(want - q.ids)} of {len(cat)}"

    # LEAK PATH: cap of 4 with only 3 usable dimensions forces unsplittable cells; they
    # must be logged, harvested (q= tokens mined from the page) and still terminate.
    r = Partitioner(fake_get, conc=8, cap=4)
    r.run([{"make": "Polaris", "_state": STATES[0], "typeid": "6", "year_from": 2019,
            "year_to": 2019}])
    assert r.leaks, "cap=4 on a fully pinned cell must leak"
    assert r.sorts == [], "fixture ignores sort=, so calibration must keep none"

    assert lpt([(10, "a"), (7, "b"), (5, "c"), (4, "d")], 2) == [["a", "d"], ["b", "c"]]
    print(f"PARTITION SELFTEST PASSED ({len(p.ids)}/{len(cat)} ids, 0 leaks, {p.reqs} "
          f"requests vs {worst} worst-case; dead-dim, broken-dim and leak paths clean)")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest(); sys.exit(0)
    ap = argparse.ArgumentParser()
    ap.add_argument("out")
    ap.add_argument("--part", type=int, default=0)
    ap.add_argument("--total", type=int, default=1)
    ap.add_argument("--rps", type=float, default=10.0, help="per runner; x18 = aggregate")
    ap.add_argument("--conc", type=int, default=32, help="in-flight requests per runner")
    ap.add_argument("--budget", type=float, default=1020, help="seconds; 0 = no cap")
    ap.add_argument("--root", default=None, help="single root, e.g. make=Polaris,_state=Texas")
    a = ap.parse_args()

    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from atvhunt_crawl import Governor, sess_local    # thread-safe bucket, 429 backoff,
    gov = Governor(a.rps)                             # os._exit on 5x403 (sys.exit is
    deadline = time.time() + a.budget if a.budget else float("inf")

    def get(u):                                       # swallowed inside a worker thread)
        for _ in range(4):
            if time.time() > deadline:
                return ""
            gov.gate()
            try:
                r = sess_local().get(u, timeout=(8, 25), impersonate="chrome")
            except Exception:
                time.sleep(1); continue
            if r.status_code == 429:
                gov.on_429(); continue
            if r.status_code in (401, 403):
                gov.on_403(); continue
            gov.on_ok()
            return r.text if r.status_code == 200 else ""
        return ""

    p = Partitioner(get, conc=a.conc, deadline=deadline)
    t0 = time.time()
    try:
        if a.root:
            mine = [dict(kv.split("=", 1) for kv in a.root.split(","))]
        else:
            # ROOTS = state x typeid. Both dimensions sum to 177,184 EXACTLY, so every
            # listing has one; make sums to 175,350 (99.0%) and can never reach the other
            # 1,811. 104 roots is fine-grained enough that LPT packs 18 near-equal bins.
            roots = [{"_state": s, "typeid": t} for s in STATES for t in TYPEIDS]
            sized = p.census(roots)
            big = [(n, f) for n, f in sized if n > PAGE_CAP]
            mine = lpt(big, a.total)[a.part] if a.total > 1 else [f for _, f in big]
            sys.stderr.write(f"census {sum(n for n, _ in sized)} listings over {len(roots)}"
                             f" roots, {len(big)} need splitting, mine={len(mine)}\n")
        p.run(mine)
    finally:
        with open(a.out, "w", encoding="utf-8") as fh:
            fh.write("\n".join(sorted(p.ids, key=int)) + "\n")
        stats = {"part": a.part, "total": a.total, "ids": len(p.ids), "requests": p.reqs,
                 "leaks": len(p.leaks), "leaked_listings": sum(n for _, n in p.leaks),
                 "drops": len(p.drops), "errs": p.errs,
                 "dead_dims": sorted(p.dead), "sorts": p.sorts,
                 "seconds": round(time.time() - t0, 1),
                 "rate": round(p.reqs / max(1e-9, time.time() - t0), 2)}
        with open(os.path.splitext(a.out)[0] + "-stats.json", "w") as fh:
            json.dump(stats, fh, indent=1)
        sys.stderr.write(f"part {a.part}/{a.total}: {json.dumps(stats)}\n")
