#!/usr/bin/env python3
"""autowini crawl runner: read a gz shard of car detail URLs, parse each via the
shared parser (API JSON + detail-page spec block + gallery), write JSONL. Distinct
runner IP + gentle pool keeps it under autowini's per-IP rate limit."""
import gzip, sys, json
from concurrent.futures import ThreadPoolExecutor
sys.path.insert(0, "scripts")
import autowini_crawl as A

SHARD = sys.argv[1]; OUT = sys.argv[2]
POOL = int(sys.argv[3]) if len(sys.argv) > 3 else 8
urls = [u for u in gzip.open(SHARD, "rt", encoding="utf-8").read().split("\n") if u]
print(f"shard {SHARD}: {len(urls)} urls, pool {POOL}", flush=True)
done = kept = 0
with open(OUT, "w", encoding="utf-8") as f, ThreadPoolExecutor(max_workers=POOL) as ex:
    for i in range(0, len(urls), 1000):
        for r in ex.map(A.parse_car, urls[i:i + 1000]):
            done += 1
            if r and r.get("images"):
                f.write(json.dumps(r, ensure_ascii=False) + "\n"); kept += 1
        f.flush()
        print(f"  {done}/{len(urls)} (kept {kept})", flush=True)
print(f"DONE {done} kept {kept}", flush=True)
