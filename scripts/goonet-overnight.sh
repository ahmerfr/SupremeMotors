#!/usr/bin/env bash
# Unattended goo-net full crawl -> strip -> import -> chunk -> shutdown.
# Guards: if the crawl or strip comes back short (< MIN rows, i.e. rate-limited /
# incomplete), it does NOT import and does NOT shut down — the machine stays on
# with logs so nothing is lost. Shutdown has a 180s abort window: `shutdown //a`.
set -uo pipefail
cd /c/xampp/htdocs/SupremeMotors

WORK="C:/Users/ahmer/AppData/Local/Temp/claude/C--xampp-htdocs-SupremeMotors/7734410e-9232-4106-b9f0-f32cd001765c/scratchpad/goonet-work"
mkdir -p "$WORK"
LOG="$WORK/overnight.log"
MIN=150000
: > "$LOG"
say() { echo "[$(date '+%m-%d %H:%M:%S')] $*" | tee -a "$LOG"; }

# keep the machine awake for the run (sleep previously crashed MySQL)
powercfg //change standby-timeout-ac 0 2>/dev/null || true
powercfg //change hibernate-timeout-ac 0 2>/dev/null || true

say "CRAWL start — 4 shards x pool 12 (curl_cffi Chrome-impersonation)"
for s in 1 2 3 4; do
  python scripts/goonet_crawl.py run "$WORK/shard-$s.jsonl" 4 $s 12 > "$WORK/shard-$s.log" 2>&1 &
done
wait
cat "$WORK"/shard-*.jsonl > "$WORK/all.jsonl" 2>/dev/null || true
ROWS=$(grep -c . "$WORK/all.jsonl" 2>/dev/null || echo 0)
say "CRAWL done: $ROWS rows"
if [ "$ROWS" -lt "$MIN" ]; then
  say "ABORT: only $ROWS rows (< $MIN) — crawl incomplete/rate-limited. NOT importing, NOT shutting down. Review $WORK/shard-*.log"
  exit 1
fi

say "STRIP start — hashing first-15 gallery on the image CDN (pool 120)"
python scripts/goonet_crawl.py strip "$WORK/all.jsonl" "$WORK/clean.jsonl" 120 >> "$LOG" 2>&1
CLEAN=$(grep -c . "$WORK/clean.jsonl" 2>/dev/null || echo 0)
say "STRIP done: $CLEAN clean rows"
if [ "$CLEAN" -lt "$MIN" ]; then
  say "ABORT: strip produced $CLEAN rows (< $MIN). NOT importing, NOT shutting down."
  exit 1
fi

# ensure MySQL is up for the import
tasklist 2>/dev/null | grep -qi mysqld || { say "starting MySQL"; ( "C:/xampp/mysql/bin/mysqld.exe" --defaults-file="C:/xampp/mysql/bin/my.ini" >/dev/null 2>&1 & ) ; sleep 10; }

say "IMPORT to local DB"
php artisan scrape:goonet --import-jsonl="$WORK/clean.jsonl" >> "$LOG" 2>&1
GN=$("C:/xampp/mysql/bin/mysql.exe" -h 127.0.0.1 -P 3307 -u root -N -e "SELECT COUNT(*) FROM supreme_motors.products WHERE website='goonet';" 2>/dev/null || echo "?")
say "DB goonet rows now: $GN"

say "EXPORT live chunk"
php artisan scrape:goonet --export-chunk >> "$LOG" 2>&1
ls -la db-export/goonet-products.zip 2>/dev/null | awk '{print "chunk:",$5,"bytes"}' | tee -a "$LOG"

say "PIPELINE COMPLETE. clean.jsonl + db-export/goonet-products.zip ready. Shutting down in 180s (abort with: shutdown //a)"
shutdown //s //t 180 //c "goonet crawl complete — Supreme Motors"
