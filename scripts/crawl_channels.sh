#!/bin/bash
# Crawl every AutoTrader UK non-car channel: plan price bands, then scrape each
# band under the 100-page cap. Sequential (one home IP past Cloudflare). Resumable
# — each band has its own --shard cursor, so a re-run skips finished bands.
set -u
cd /c/xampp/htdocs/SupremeMotors
CHANNELS="${1:-caravans farm trucks motorhomes bikes vans}"
for ch in $CHANNELS; do
  echo "=== [$ch] planning price bands ==="
  php artisan scrape:autotraderuk-plan --channel="$ch" --threshold=1800 \
      --out="db-export/atuk-$ch-shards.json" || { echo "[$ch] plan failed"; continue; }
  python - "$ch" <<'PY'
import sys, json, subprocess
ch = sys.argv[1]
bands = json.load(open(f"db-export/atuk-{ch}-shards.json"))["bands"]
for b in bands:
    if b.get("count", 0) <= 0:
        continue
    a = ["php", "artisan", "scrape:autotraderuk", f"--channel={ch}",
         f"--shard={ch}-{b['shard']}", "--pool=6"]
    if b.get("min") is not None:
        a += [f"--min-price={int(b['min'])}", f"--max-price={int(b['max'])}"]
    print(f"  [{ch}] band {b['shard']} (~{b['count']})", flush=True)
    subprocess.run(a)
PY
  echo "=== [$ch] done ==="
done
echo "ALL CHANNELS DONE"
