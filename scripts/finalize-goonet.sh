#!/usr/bin/env bash
set -uo pipefail
cd /c/xampp/htdocs/SupremeMotors
WORK="C:/Users/ahmer/AppData/Local/Temp/claude/C--xampp-htdocs-SupremeMotors/7734410e-9232-4106-b9f0-f32cd001765c/scratchpad/goonet-work"
LOG="$WORK/finalize.log"
say(){ echo "[$(date '+%H:%M:%S')] $*" | tee -a "$LOG"; }
say "STRIP start (HEAD+size, resumable)"
python scripts/goonet_crawl.py strip "$WORK/all.jsonl" "$WORK/clean.jsonl" 80 >> "$LOG" 2>&1
CLEAN=$(wc -l < "$WORK/clean.jsonl" 2>/dev/null | tr -d ' ' || echo 0)
say "STRIP done: $CLEAN clean rows"
if [ "${CLEAN:-0}" -lt 150000 ]; then say "ABORT: strip short ($CLEAN)"; exit 1; fi
say "DELETE existing raw goonet rows"
"C:/xampp/mysql/bin/mysql.exe" -h 127.0.0.1 -P 3307 -u root -e "DELETE FROM supreme_motors.products WHERE website='goonet';" 2>>"$LOG"
say "IMPORT clean (batch)"
php artisan scrape:goonet --import-jsonl="$WORK/clean.jsonl" >> "$LOG" 2>&1
GN=$("C:/xampp/mysql/bin/mysql.exe" -h 127.0.0.1 -P 3307 -u root -N -e "SELECT COUNT(*) FROM supreme_motors.products WHERE website='goonet';" 2>/dev/null)
say "DB goonet rows: $GN"
say "EXPORT chunk"
php artisan scrape:goonet --export-chunk >> "$LOG" 2>&1
say "FINALIZE COMPLETE — db-export/goonet-products.zip ready."
