#!/usr/bin/env python3
"""atvhunt.com ID-range crawler — one shard per worker/VM, resumable, self-throttling.

atvhunt sits behind Google Cloud Armor per-IP rate limiting (429 with no Retry-After)
and is US-geo-locked. There is no catalogue pagination/API, but /l/{id}/ numeric ids are
dense, so we scan an id range. Each VM owns a sub-range [start,end] (fan-out = distinct
IPs = aggregate throughput). Rate is AIMD-adaptive: additive-increase concurrency while
200s flow, multiplicative-decrease + sleep on 429 — so each IP self-tunes to just under
its ban threshold without a hand-picked constant.

Usage:
  python atvhunt_crawl.py <outdir> <start_id> <end_id> [--conc-max 24] [--sample]
Resume: re-run same args — it continues past the last id in <outdir>/progress_<s>_<e>.txt.
Output: <outdir>/shard_<start>_<end>.jsonl  (one product JSON per line; feeds the chunker)
"""
import sys, os, json, time, threading, argparse, faulthandler
from concurrent.futures import ThreadPoolExecutor, as_completed
# If anything freezes, dump every thread's stack to stderr (-> crawl.log) every 45s.
faulthandler.dump_traceback_later(300, repeat=True)
from curl_cffi import requests as rq
import atvhunt_parse as P

UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                    "(KHTML, like Gecko) Chrome/126.0 Safari/537.36", "Accept-Language": "en"}
IMP = dict(impersonate="chrome")
BASE = "https://atvhunt.com/l/{}/x"

# curl_cffi Session is NOT thread-safe (one shared handle -> hangs under concurrency).
# One session per worker thread, like the goonet crawler.
_tls = threading.local()


def sess_local():
    if not hasattr(_tls, "s"):
        _tls.s = rq.Session(impersonate="chrome")
        _tls.s.headers.update(UA)
    return _tls.s


class Governor:
    """Steady-rate AIMD throttle: one shared min-interval between request starts.
    On 429 it SLOWS DOWN (bigger interval); on sustained 200s it eases up. Crucially it
    NEVER does a long global cooldown/freeze — every thread keeps flowing at the current
    rate, so the crawl converges to just under atvhunt's per-IP limit instead of stalling.
    Proper token bucket (next_slot = max(now,next_slot)+delay) so grants can't race ahead."""
    def __init__(self, cmax):
        self.lock = threading.Lock()
        self.delay = 0.06
        self.min_delay = 0.03
        self.max_delay = 8.0
        self.next_slot = 0.0
        self.ok = self.f429 = 0

    def gate(self):
        while True:
            with self.lock:
                now = time.time()
                if now >= self.next_slot:
                    self.next_slot = max(now, self.next_slot) + self.delay
                    return
                wait = self.next_slot - now
            time.sleep(min(wait, 0.5))       # capped -> never a long freeze

    def on_ok(self):
        with self.lock:
            self.ok += 1
            if self.ok % 120 == 0:           # ease up gently after a sustained clean run
                self.delay = max(self.min_delay, self.delay * 0.92)

    def on_429(self):
        with self.lock:
            self.f429 += 1
            self.delay = min(self.max_delay, self.delay * 1.4)   # slow down, no freeze
            self.cooldown_until = time.time() + min(90, 8 * (1 + self.f429 % 8))


def fetch(sess, i, gov):
    # tight timeouts (connect, read) + few tries so a bad/hanging page frees the worker
    # in <~20s instead of blocking; the as_completed loop means it never stalls the shard.
    for _ in range(3):
        gov.gate()
        try:
            r = sess.get(BASE.format(i), timeout=(8, 12), allow_redirects=False, **IMP)
            if r.status_code == 429:
                gov.on_429(); continue
            gov.on_ok()
            if r.status_code == 200 and P.is_listing(r.text):
                return r.text
            return None                     # 302 / soft-404 / non-listing -> skip
        except Exception:
            time.sleep(1)
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("outdir"); ap.add_argument("start", type=int); ap.add_argument("end", type=int)
    ap.add_argument("--conc-max", type=int, default=24)
    ap.add_argument("--sample", action="store_true", help="stop after 25 hits (smoke test)")
    a = ap.parse_args()
    os.makedirs(a.outdir, exist_ok=True)
    tag = f"{a.start}_{a.end}"
    outp = os.path.join(a.outdir, f"shard_{tag}.jsonl")
    prog = os.path.join(a.outdir, f"progress_{tag}.txt")

    start = a.start
    if os.path.exists(prog):
        try: start = max(start, int(open(prog).read().strip()) + 1)
        except Exception: pass
    print(f"shard {tag}: scanning {start}..{a.end} ({a.end-start+1} ids)", flush=True)

    gov = Governor(a.conc_max)
    wlock = threading.Lock()
    fh = open(outp, "a", encoding="utf-8")
    hits = [0]

    def work(i):
        htmltext = fetch(sess_local(), i, gov)
        if htmltext:
            try:
                row = P.parse(htmltext, listing_id=str(i))
                if row.get("title"):
                    with wlock:
                        fh.write(json.dumps(row, ensure_ascii=False) + "\n"); fh.flush()
                        hits[0] += 1
            except Exception as e:
                sys.stderr.write(f"parse {i}: {e}\n")

    # as_completed (unordered): a hung/slow work() ties up ONE worker for <~20s (tight
    # fetch timeout) but never blocks the shard's progress the way ordered map did.
    ids = range(start, a.end + 1)
    t0 = time.time()
    with ThreadPoolExecutor(max_workers=a.conc_max) as ex:
        futures = [ex.submit(work, i) for i in ids]
        for n, _fut in enumerate(as_completed(futures), 1):
            if n % 200 == 0:
                dt = time.time() - t0
                print(f"  {tag} scanned={n} hits={hits[0]} ok={gov.ok} 429={gov.f429} "
                      f"delay={gov.delay:.3f} rate={n/dt:.1f}/s", flush=True)
                with wlock: open(prog, "w").write(str(start + n))
            if a.sample and hits[0] >= 25:
                break
    open(prog, "w").write(str(a.end))
    fh.close()
    print(f"shard {tag} DONE: hits={hits[0]} written -> {outp}", flush=True)


if __name__ == "__main__":
    main()
