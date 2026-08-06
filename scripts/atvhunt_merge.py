#!/usr/bin/env python3
"""Merge downloaded shard artifacts into one JSONL and PROVE how complete it is.

  python atvhunt_merge.py dl ids.txt.gz atvhunt-all.jsonl.gz missing.txt.gz
  python atvhunt_merge.py --selftest

covered = union of every cov_*.txt. An id lands there only on a DEFINITIVE answer (a
          listing, a 302, a 404 or a soft-404). 429-exhausted, errored, budget-expired and
          403-aborted ids are simply absent.
missing = requested ids - covered   -> re-dispatch the same workflow to sweep these up.

Also counts DISTINCT egress IPs across the preflight files, because GitHub documents NO
guarantee that concurrent runners get distinct addresses. If this prints 1, the entire
fan-out premise was wrong and no amount of sharding was buying anything.
"""
import sys, os, re, json, glob, gzip


def read_ids(path):
    op = gzip.open if path.endswith(".gz") else open
    with op(path, "rt") as f:
        return {int(x) for x in f.read().split()}


def run(dl, idsp, outp, missp):
    seen, n, bad = set(), 0, 0
    with gzip.open(outp, "wt", encoding="utf-8") as out:
        for f in sorted(glob.glob(os.path.join(dl, "**", "shard_*.jsonl"), recursive=True)):
            for line in open(f, encoding="utf-8", errors="replace"):
                if not line.strip():
                    continue
                try:
                    sid = json.loads(line)["source_id"]
                except Exception:
                    bad += 1; continue
                if sid in seen:                 # a resumed shard appends; dedupe by listing id
                    continue
                seen.add(sid)
                out.write(line if line.endswith("\n") else line + "\n")
                n += 1

    want = read_ids(idsp)
    cov = set()
    for f in glob.glob(os.path.join(dl, "**", "cov_*.txt"), recursive=True):
        cov |= read_ids(f)
    cov &= want
    miss = sorted(want - cov)
    with gzip.open(missp, "wt") as m:
        m.write("".join(f"{i}\n" for i in miss))

    ips = set()
    for f in glob.glob(os.path.join(dl, "**", "pre*.json"), recursive=True):
        try:
            ips.add(json.load(open(f)).get("egress", {}).get("ip"))
        except Exception:
            pass
    ips.discard(None)

    pct = len(cov) * 100.0 / max(1, len(want))
    print(f"listings={n} unique (bad lines {bad})")
    print(f"covered={len(cov)}/{len(want)} ({pct:.1f}%)  hit-rate={n*100.0/max(1,len(cov)):.1f}%")
    print(f"missing={len(miss)} -> {missp}")
    print(f"distinct_egress_ips={len(ips)}")
    if len(ips) == 1:
        print("WARNING: every shard egressed from ONE IP - the fan-out bought nothing")
    if pct < 98:
        print("COVERAGE < 98% - re-dispatch the workflow; shards resume from cov_ by set difference")
        return 1
    return 0


def selftest():
    import tempfile, shutil
    d = tempfile.mkdtemp()
    try:
        os.makedirs(os.path.join(d, "dl"))
        open(os.path.join(d, "dl", "shard_000of2.jsonl"), "w").write(
            '{"source_id":"1","title":"a"}\n{"source_id":"1","title":"a"}\n')
        open(os.path.join(d, "dl", "cov_000of2.txt"), "w").write("1\n3\n")
        gzip.open(os.path.join(d, "ids.txt.gz"), "wt").write("1\n2\n3\n")
        rc = run(os.path.join(d, "dl"), os.path.join(d, "ids.txt.gz"),
                 os.path.join(d, "o.gz"), os.path.join(d, "m.gz"))
        assert rc == 1                                    # 2/3 covered -> under 98%
        assert gzip.open(os.path.join(d, "m.gz"), "rt").read().split() == ["2"]
        assert len(gzip.open(os.path.join(d, "o.gz"), "rt").read().splitlines()) == 1  # deduped
        print("MERGE SELFTEST PASSED")
    finally:
        shutil.rmtree(d, ignore_errors=True)


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest()
    else:
        sys.exit(run(*sys.argv[1:5]))
