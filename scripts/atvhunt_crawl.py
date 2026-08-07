#!/usr/bin/env python3
"""atvhunt.com crawler - one shard per runner, id-list driven, resumable, rate-governed.

Feed it the canonical id list (scripts/atvhunt_ids.py -> ids.txt.gz) and a shard index; it
takes ids[shard::of], so re-running a failed job on a fresh VM recomputes byte-identical
work with zero state to reconcile.

  python atvhunt_crawl.py out --ids ids.txt.gz --shard 3 --of 36 --rps 1.5 --budget 3000
  python atvhunt_crawl.py --selftest

Writes  out/shard_<tag>.jsonl   one product per line (feeds the chunker)
        out/cov_<tag>.txt       ids with a DEFINITIVE answer (listing / 302 / 404 / soft-404)

missing = union(ids) - union(cov_*) is computed by atvhunt_merge.py. An id that 429s out or
errors is simply ABSENT from cov_, so it gets retried rather than silently written off - the
old single-integer progress watermark lost every id in flight during a 429 burst, forever.
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


def sess_local():
    # ponytail: no header overrides. The old UA dict claimed Windows + Chrome/126 and a bare
    # `Accept-Language: en` on top of curl_cffi's own macOS Chromium/146 client hints - a
    # three-way self-contradiction that defeats the JA3/JA4 impersonation it rides on.
    # curl_cffi Session is NOT thread-safe: one per worker thread, like the goonet crawler.
    if not hasattr(_tls, "s"):
        _tls.s = rq.Session(impersonate="chrome")
    return _tls.s


class Governor:
    """Fixed safe rate measured by the probe; back off on 429, drift back. Shared
    min-interval token bucket (next_slot = max(now,next_slot)+delay) so grants cannot race.

    ONE multiplicative decrease per 2 s burst window: N threads 429ing simultaneously is ONE
    breach, not N. The old governor did delay *= 1.4 per 429 and delay *= 0.92 per 120 OKs,
    so 15 429s (which 24 threads deliver in a single breach) pinned it at the 8 s ceiling and
    recovery cost ln(8/0.06)/ln(1/0.92) = 59 steps = 11,907 s = 3.31 HOURS.
    """
    def __init__(self, rps):
        self.lock = threading.Lock()
        self.delay = self.min_delay = 1.0 / rps
        self.max_delay = 16.0 / rps
        self.next_slot = 0.0
        self.last_cut = 0.0
        self.ok = self.f429 = self.blocked = 0

    def gate(self):
        while True:
            with self.lock:
                now = time.time()
                if now >= self.next_slot:
                    self.next_slot = max(now, self.next_slot) + self.delay
                    return
                wait = self.next_slot - now
            time.sleep(min(wait, 0.5))        # capped -> never a long global freeze

    def on_ok(self):
        with self.lock:
            self.ok += 1
            if self.ok % 40 == 0:             # back to the safe rate in ~9 steps, not 3.3 h
                self.delay = max(self.min_delay, self.delay * 0.8)

    def on_429(self):
        with self.lock:
            self.f429 += 1
            now = time.time()
            if now - self.last_cut > 2.0:
                self.delay = min(self.max_delay, self.delay * 2.0)
                self.last_cut = now

    def on_403(self):
        # A 403 is the GEO/WAF block, not the rate limit. The old code fell through to
        # on_ok(), so a blocked runner raced its whole shard at min_delay, wrote zero rows
        # and exited 0 - the single most likely way to silently burn a window.
        with self.lock:
            self.blocked += 1
            if self.blocked < 5:
                return
        sys.stderr.write("FATAL: 5x HTTP 403 - this runner's egress IP is geo/WAF blocked, "
                         "not rate-limited. Aborting so the shard retries on a fresh IP.\n")
        sys.stderr.flush()
        os._exit(BLOCKED_EXIT)     # SystemExit is SWALLOWED inside a ThreadPoolExecutor worker


def fetch(sess, i, gov):
    """-> (html_or_None, covered). covered=False means no definitive answer, so the id stays
    out of cov_ and atvhunt_merge.py schedules it for retry."""
    for _ in range(3):
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
        if r.status_code == 200 and P.is_listing(r.text):
            return r.text, True
        return None, True                      # 302 / 404 / soft-404 -> definitively not a listing
    return None, False


def read_ids(path):
    op = gzip.open if str(path).endswith(".gz") else open
    with op(path, "rt") as f:
        return [int(x) for x in f.read().split()]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("outdir")
    ap.add_argument("--ids", help="canonical id list (.txt or .txt.gz)")
    ap.add_argument("--range", nargs=2, type=int, metavar=("LO", "HI"),
                    help="sweep an id range instead of a list (no enumeration needed)")
    ap.add_argument("--acct", type=int, default=0, help="this account's index in the fleet")
    ap.add_argument("--nacct", type=int, default=1, help="accounts in the fleet; acct takes id %% nacct == acct")
    ap.add_argument("--shard", type=int, default=0)
    ap.add_argument("--of", type=int, default=1)
    ap.add_argument("--rps", type=float, default=1.5, help="one third of the probe's ceiling")
    ap.add_argument("--conc", type=int, default=8)   # 24 threads at <=3 req/s was theatre
    ap.add_argument("--budget", type=float, default=0, help="seconds of crawling; 0 = no cap")
    ap.add_argument("--maxreq", type=int, default=0,
                    help="stop after N HTTP attempts. MEASURED: a runner IP serves ~800 "
                         "successful requests then is blackholed (first 429 at #785). A "
                         "short job on a fresh IP beats a long job on a spent one.")
    a = ap.parse_args()
    os.makedirs(a.outdir, exist_ok=True)
    tag = f"{a.shard:03d}of{a.of}"
    if a.range:
        # Pure arithmetic, O(1) memory: slicing a range yields a range, so a 930k-id
        # account slice never materialises as a list. Account k owns id %% nacct == k, so
        # the four accounts are disjoint by construction -- no id files to ship around and
        # no coordination service. Deliberately NOT bucket-filtered: 11% of listings have
        # zero images, so a candidate list built from the image bucket would miss them.
        lo, hi = a.range
        ids = range(lo + ((a.acct - lo) % a.nacct), hi + 1, a.nacct)[a.shard::a.of]
    else:
        ids = read_ids(a.ids)[a.shard::a.of]         # STRIDE: every shard samples the whole list
    covp = os.path.join(a.outdir, f"cov_{tag}.txt")
    done = set(read_ids(covp)) if os.path.exists(covp) else set()
    todo = [i for i in ids if i not in done]         # resume = set difference, not a watermark
    print(f"shard {tag}: {len(todo)} of {len(ids)} ids to go, rps={a.rps} "
          f"budget={a.budget}s", flush=True)

    gov = Governor(a.rps)
    wlock = threading.Lock()
    fh = open(os.path.join(a.outdir, f"shard_{tag}.jsonl"), "a", encoding="utf-8")
    cov = open(covp, "a")
    hits, n = [0], [0]
    deadline = time.time() + a.budget if a.budget else float("inf")
    t0 = time.time()

    http = [0]
    hlock = threading.Lock()

    def work(i):
        if time.time() > deadline:
            return                                   # budget spent: the rest drain instantly
        with hlock:
            if a.maxreq and http[0] >= a.maxreq:
                return                               # IP quota spent: stop cleanly
            http[0] += 1
        htmltext, covered = fetch(sess_local(), i, gov)
        with wlock:
            n[0] += 1
            if covered:
                cov.write(f"{i}\n")
            if n[0] % 200 == 0:
                cov.flush(); fh.flush()
                dt = time.time() - t0
                print(f"  {tag} scanned={n[0]} hits={hits[0]} ok={gov.ok} 429={gov.f429} "
                      f"delay={gov.delay:.2f} rate={n[0]/dt:.2f}/s", flush=True)
        if not htmltext:
            return
        try:
            row = P.parse(htmltext, listing_id=str(i))
            if row.get("title"):
                with wlock:
                    fh.write(json.dumps(row, ensure_ascii=False) + "\n")
                    hits[0] += 1
        except Exception as e:
            sys.stderr.write(f"parse {i}: {e}\n")

    with ThreadPoolExecutor(max_workers=a.conc) as ex:
        list(ex.map(work, todo))
    cov.close(); fh.close()
    print(f"shard {tag} DONE: hits={hits[0]} scanned={n[0]} 429={gov.f429} "
          f"blocked={gov.blocked}", flush=True)


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
    ids = list(range(100))
    parts = [ids[s::4] for s in range(4)]
    assert sorted(sum(parts, [])) == ids and len(set(map(len, parts))) == 1
    # 4-account x 36-shard range split must be DISJOINT and COMPLETE, and stay a range
    lo, hi, NA, OF = 10_000_000, 10_000_999, 4, 36
    cover = []
    for acct in range(NA):
        acct_ids = range(lo + ((acct - lo) % NA), hi + 1, NA)
        assert isinstance(acct_ids, range)
        for sh in range(OF):
            sl = acct_ids[sh::OF]
            assert isinstance(sl, range), "slice must stay O(1) memory"
            cover += list(sl)
    assert sorted(cover) == list(range(lo, hi + 1)), (len(cover), hi - lo + 1)
    assert len(cover) == len(set(cover)), "shards must not overlap"
    print("GOVERNOR SELFTEST PASSED")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest()
    else:
        main()
