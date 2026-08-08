#!/usr/bin/env python3
"""atvhunt.com crawler - one shard per runner, resumable, rate-governed.

Two modes, same shard arithmetic:
  --ids ids.txt.gz          crawl a committed id list (retry/residue passes)
  --range LO HI             sweep a contiguous id window (the tiling sweep)

  python atvhunt_crawl.py out --range 13056000 13823999 --acct 0 --nacct 4 \
                              --shard 7 --of 256 --rps 90 --conc 8 --maxreq 800
  python atvhunt_crawl.py --selftest

Writes  out/shard_<tag>.jsonl   one ATV/UTV product per line (body_style populated)
        out/cov_<tag>.txt       ids that got a DEFINITIVE HTTP answer
        out/amb_<tag>.txt       id<TAB>verdict for every NON-listing definitive answer
        out/other_<tag>.txt     id<TAB>token for live pages rejected by the ATV/UTV gate
        out/stats_<tag>.json    per-job counters (the fleet health signal)

missing = union(ids) - union(cov_*) is computed by the workflow's merge job. An id that
429s out or errors is ABSENT from cov_, so it is retried rather than silently written off.

cov_ means "we asked and the site gave a final answer", NOT "we captured a listing". That
distinction is why amb_ exists: a 200 that does not look like a listing, and every 30x, is
recorded with its verdict instead of vanishing into cov_ as "definitively not a listing".
The per-state accounting in atvhunt_close.py is the authority on completeness; this file
only guarantees that every id was asked and every non-answer is visible.
"""
import sys, os, json, gzip, time, threading, argparse, faulthandler
from concurrent.futures import ThreadPoolExecutor
faulthandler.dump_traceback_later(300, repeat=True)   # frozen? dump every stack to stderr
from curl_cffi import requests as rq
import atvhunt_parse as P

IMP = dict(impersonate="chrome")
BASE = "https://atvhunt.com/l/{}/x"
BLOCKED_EXIT = 3
_tls = threading.local()

# The sweep tiling. 18 windows x 4 accounts x 256 shards x 750 ids == 13,824,000 ids,
# which clears the highest id we have ever seen (13,738,032) by 85,968 and starts at 0 so
# there is no floor to justify. selftest() asserts the tiling is exact.
WIN, NACCT, NSHARD, SLICE = 768_000, 4, 256, 750
NWIN = 18


PROXY = os.environ.get("ATVHUNT_PROXY") or None


def sess_local():
    # curl_cffi Session is NOT thread-safe: one per worker thread. No header overrides --
    # they contradict the client hints the JA3/JA4 impersonation already sends.
    #
    # ATVHUNT_PROXY routes through a residential exit. Every datacenter egress we tested is
    # refused by Cloud Armor (AS8075 GitHub, AS396982 GCP, and all Bunny edge ASNs return
    # the same 134-byte deny page), and six different TLS fingerprints on one blocked IP
    # behaved identically -- so it is the network, and only a non-datacenter exit changes
    # it. Kept as an env var so a metered credential never lands in the repo or in argv.
    if not hasattr(_tls, "s"):
        kw = {"impersonate": "chrome"}
        if PROXY:
            kw["proxies"] = {"http": PROXY, "https": PROXY}
        _tls.s = rq.Session(**kw)
    return _tls.s


class Governor:
    """Fixed safe rate; back off on 429, drift back. Shared min-interval token bucket so
    grants cannot race. ONE multiplicative decrease per 2 s burst window: N threads 429ing
    simultaneously is ONE breach, not N.

    `attempts` counts HTTP REQUESTS, not ids. fetch() retries up to 3x per id, so a counter
    kept per id (as this file used to do) let --maxreq 780 issue up to 2,340 requests
    against a per-IP quota of ~800 -- the job would burn its IP three times over and blame
    Azure IP recycling for the residue.
    """
    def __init__(self, rps):
        self.lock = threading.Lock()
        self.delay = self.min_delay = 1.0 / rps
        self.max_delay = 16.0 / rps
        self.next_slot = 0.0
        self.last_cut = 0.0
        self.ok = self.f429 = self.blocked = self.attempts = 0

    def gate(self):
        while True:
            with self.lock:
                now = time.time()
                if now >= self.next_slot:
                    self.next_slot = max(now, self.next_slot) + self.delay
                    self.attempts += 1
                    return
                wait = self.next_slot - now
            time.sleep(min(wait, 0.5))        # capped -> never a long global freeze

    def on_ok(self):
        with self.lock:
            self.ok += 1
            if self.ok % 40 == 0:
                self.delay = max(self.min_delay, self.delay * 0.8)

    def on_429(self):
        with self.lock:
            self.f429 += 1
            now = time.time()
            if now - self.last_cut > 2.0:
                self.delay = min(self.max_delay, self.delay * 2.0)
                self.last_cut = now

    def on_403(self):
        # A 403 is the GEO/WAF block, not the rate limit. Falling through to on_ok() let a
        # blocked runner race its whole shard, write zero rows and exit 0.
        with self.lock:
            self.blocked += 1
            if self.blocked < 5:
                return
        sys.stderr.write("FATAL: 5x HTTP 403 - this runner's egress IP is geo/WAF blocked, "
                         "not rate-limited. Aborting so the shard retries on a fresh IP.\n")
        sys.stderr.flush()
        os._exit(BLOCKED_EXIT)     # SystemExit is SWALLOWED inside a ThreadPoolExecutor worker


def fetch(sess, i, gov, maxreq):
    """-> (html_or_None, verdict).

    verdict None  = no definitive answer (429ed/errored out) -> id stays OUT of cov_.
    verdict "LIST"  = a listing page, html returned.
    anything else = a definitive non-listing answer, recorded in amb_ with the reason.
    """
    for _ in range(3):
        if maxreq and gov.attempts >= maxreq:
            return None, None                  # IP quota spent: leave the id for a retry
        gov.gate()
        try:
            r = sess.get(BASE.format(i), timeout=(8, 12), allow_redirects=False, **IMP)
        except Exception:
            time.sleep(1); continue
        if r.status_code == 429:
            gov.on_429(); continue
        if r.status_code in (401, 403):
            gov.on_403(); continue
        gov.on_ok()
        if r.status_code == 200:
            return (r.text, "LIST") if P.is_listing(r.text) else (None, "AMBIG200")
        if 300 <= r.status_code < 400:
            # NOT followed (that would cost a second request out of the IP quota), but the
            # Location is recorded so the merge can tell "sold -> /" from "moved -> /l/N".
            return None, "REDIR " + (r.headers.get("location") or "?")
        return None, "HTTP%d" % r.status_code
    return None, None


def read_ids(path):
    op = gzip.open if str(path).endswith(".gz") else open
    with op(path, "rt") as f:
        return [int(x) for x in f.read().split()]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("outdir")
    ap.add_argument("--ids", help="id list (.txt or .txt.gz) - retry/residue passes")
    ap.add_argument("--range", nargs=2, type=int, metavar=("LO", "HI"),
                    help="sweep an id range instead of a list (no enumeration needed)")
    ap.add_argument("--acct", type=int, default=0, help="this account's index in the fleet")
    ap.add_argument("--nacct", type=int, default=1, help="accounts; acct takes id %% nacct == acct")
    ap.add_argument("--shard", type=int, default=0)
    ap.add_argument("--of", type=int, default=1)
    ap.add_argument("--rps", type=float, default=90.0)
    ap.add_argument("--conc", type=int, default=8)
    ap.add_argument("--budget", type=float, default=0, help="seconds of crawling; 0 = no cap")
    ap.add_argument("--maxreq", type=int, default=0,
                    help="stop after N HTTP ATTEMPTS. MEASURED: a runner IP serves ~750-800 "
                         "requests then is blackholed. A short job on a fresh IP beats a "
                         "long job on a spent one.")
    a = ap.parse_args()
    os.makedirs(a.outdir, exist_ok=True)
    tag = f"{a.shard:03d}of{a.of}"
    if a.range:
        # Pure arithmetic, O(1) memory: slicing a range yields a range. Account k owns
        # id %% nacct == k, so the four accounts are disjoint by construction -- no id files
        # to ship and no coordination service. Deliberately NOT bucket-filtered: ~15% of
        # listings have zero images, so an image-derived candidate list cannot be complete.
        lo, hi = a.range
        ids = range(lo + ((a.acct - lo) % a.nacct), hi + 1, a.nacct)[a.shard::a.of]
    else:
        ids = read_ids(a.ids)[a.shard::a.of]         # STRIDE: every shard samples the whole list
    covp = os.path.join(a.outdir, f"cov_{tag}.txt")
    done = set(read_ids(covp)) if os.path.exists(covp) else set()
    todo = [i for i in ids if i not in done]         # resume = set difference, not a watermark
    print(f"shard {tag}: {len(todo)} of {len(ids)} ids, rps={a.rps} maxreq={a.maxreq}", flush=True)

    gov = Governor(a.rps)
    wlock = threading.Lock()
    op = lambda n: open(os.path.join(a.outdir, f"{n}_{tag}.txt"), "a", encoding="utf-8")
    fh = open(os.path.join(a.outdir, f"shard_{tag}.jsonl"), "a", encoding="utf-8")
    cov, amb, other = open(covp, "a"), op("amb"), op("other")
    c = {"list": 0, "amb": 0, "other": 0, "n": 0}
    deadline = time.time() + a.budget if a.budget else float("inf")
    t0 = time.time()

    def work(i):
        if time.time() > deadline or (a.maxreq and gov.attempts >= a.maxreq):
            return                                   # budget/quota spent: the rest drain
        htmltext, verdict = fetch(sess_local(), i, gov, a.maxreq)
        with wlock:
            c["n"] += 1
            if verdict:
                cov.write(f"{i}\n")
                if verdict != "LIST":
                    c["amb"] += 1
                    amb.write(f"{i}\t{verdict}\n")
            if c["n"] % 200 == 0:
                cov.flush(); fh.flush(); amb.flush()
                print(f"  {tag} n={c['n']} list={c['list']} req={gov.attempts} "
                      f"429={gov.f429} rate={c['n']/(time.time()-t0):.1f}/s", flush=True)
        if not htmltext:
            return
        try:
            row = P.parse(htmltext, listing_id=str(i))
            # THE ATV/UTV GATE. atvhunt shares its id space (and its platform) with sibling
            # marketplaces; the parser stamps category_id=1302 unconditionally, so without
            # this gate cars and motorcycles land in the live ATV table. body_style is
            # populated only when the listing header reads "(New|Used) ... (ATV|UTV) in
            # City, ST". Rejected-but-live ids go to other_ WITH their category token, so a
            # gate that turns out too tight is recoverable from the artifact, not a refetch.
            if row.get("body_style"):
                with wlock:
                    fh.write(json.dumps(row, ensure_ascii=False) + "\n")
                    c["list"] += 1
            elif row.get("title"):
                with wlock:
                    c["other"] += 1
                    other.write(f"{i}\t{P.category_token(htmltext) or '?'}\n")
        except Exception as e:
            sys.stderr.write(f"parse {i}: {e}\n")

    with ThreadPoolExecutor(max_workers=a.conc) as ex:
        list(ex.map(work, todo))
    for f in (cov, fh, amb, other):
        f.close()
    st = dict(c, tag=tag, ids=len(ids), attempts=gov.attempts, ok=gov.ok,
              f429=gov.f429, blocked=gov.blocked, secs=round(time.time() - t0, 1))
    json.dump(st, open(os.path.join(a.outdir, f"stats_{tag}.json"), "w"))
    print(f"shard {tag} DONE: {st}", flush=True)


def selftest():
    g = Governor(2.0)
    assert g.delay == 0.5, g.delay
    g.on_429(); g.on_429(); g.on_429()               # a burst is ONE decrease, not three
    assert g.delay == 1.0, g.delay
    g.last_cut = 0                                   # a NEW burst, >2 s later
    g.on_429()
    assert g.delay == 2.0, g.delay
    for _ in range(40):
        g.on_ok()
    assert abs(g.delay - 1.6) < 1e-9, g.delay        # drifts back toward the safe rate
    assert g.max_delay == 8.0, g.max_delay
    g2 = Governor(0.5)
    t = time.time(); g2.next_slot = 0; g2.gate(); g2.gate()
    assert g2.next_slot >= t + 4.0 - 1e-6            # token bucket paces two grants at 0.5 rps
    assert g2.attempts == 2, "gate() must count HTTP ATTEMPTS, not ids"

    # THE COVERAGE PROOF. 18 windows x 4 accounts x 256 shards must tile [0, 13,824,000)
    # with every shard exactly SLICE ids -- so --maxreq (which caps ATTEMPTS) can never be
    # the thing that truncates a shard's id list.
    assert NWIN * WIN == 13_824_000
    assert WIN == NACCT * NSHARD * SLICE, (WIN, NACCT * NSHARD * SLICE)
    for lo in (0, WIN, 13_056_000):                  # first, second and last window
        cover = []
        for acct in range(NACCT):
            acct_ids = range(lo + ((acct - lo) % NACCT), lo + WIN, NACCT)
            assert isinstance(acct_ids, range)
            for sh in range(NSHARD):
                sl = acct_ids[sh::NSHARD]
                assert isinstance(sl, range), "slice must stay O(1) memory"
                assert len(sl) == SLICE, (lo, acct, sh, len(sl))
                cover += list(sl)
        assert sorted(cover) == list(range(lo, lo + WIN)), (lo, len(cover))
        assert len(cover) == len(set(cover)), "shards must not overlap"
    print("CRAWL SELFTEST PASSED (tiling exact, attempts counted, governor sane)")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest()
    else:
        main()
