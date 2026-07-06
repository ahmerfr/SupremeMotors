#!/usr/bin/env bash
# Warm one jaftim manifest shard into Bunny's perma-cache storage.
# Runs on a GitHub Actions runner (its own egress IP), so 20 shards = 20 IPs
# in parallel. For each `storage/app/public/stock/<id>/<file.ext>` line:
#   1. DOWNLOAD the image via the Bunny PULL-ZONE (sm-jaftim.b-cdn.net), NOT the
#      erp.jaftim.com origin directly — the origin throttles our-style IPs, and
#      letting the Bunny edge pull-and-cache is the whole point. The pull-zone
#      is not throttled.
#   2. PUT it straight into the pull-zone's perma-cache storage path, so Bunny
#      then serves it CDN-Cache: HIT with no origin pull.
# Content-Type is derived from the file extension (jpg/jpeg/png/webp/avif).
#
# Env: BUNNY_STORAGE_KEY (secret), STORAGE_ZONE, PZDIR.  Arg 1: manifest file.
set -uo pipefail

MANIFEST="${1:?usage: warm-shard-jaftim.sh <manifest-file>}"
: "${BUNNY_STORAGE_KEY:?BUNNY_STORAGE_KEY secret not set}"
STORAGE_ZONE="${STORAGE_ZONE:-suprememotors-media}"
: "${PZDIR:?PZDIR (perma-cache pullzone dir) not set}"
STORAGE="https://storage.bunnycdn.com"
PULLZONE="https://sm-jaftim.b-cdn.net"
POOL="${POOL:-25}"

# Map file extension -> Content-Type.
ctype_for() {
  case "${1,,}" in
    jpg|jpeg) echo "image/jpeg" ;;
    png)      echo "image/png" ;;
    webp)     echo "image/webp" ;;
    avif)     echo "image/avif" ;;
    gif)      echo "image/gif" ;;
    *)        echo "application/octet-stream" ;;
  esac
}

# read plain or gzipped manifest
reader() { case "$MANIFEST" in *.gz) zcat "$MANIFEST" 2>/dev/null;; *) cat "$MANIFEST";; esac; }

total=$(reader | grep -cvE '^[[:space:]]*$')
echo "warming $total jaftim images from $MANIFEST at pool $POOL"
n=0
while IFS= read -r path; do
  [ -z "$path" ] && continue
  (
    ext="${path##*.}"
    ct="$(ctype_for "$ext")"
    tmp="$(mktemp)"
    # download via the pull-zone (Bunny edge pulls from erp origin & caches)
    curl -s --retry 2 --retry-delay 1 --max-time 30 -A "Mozilla/5.0" -o "$tmp" "$PULLZONE/$path" 2>/dev/null
    if [ "$(wc -c < "$tmp" 2>/dev/null || echo 0)" -gt 1000 ]; then
      curl -s -o /dev/null -X PUT --max-time 30 \
        -H "AccessKey: $BUNNY_STORAGE_KEY" -H "Content-Type: $ct" --data-binary "@$tmp" \
        "$STORAGE/$STORAGE_ZONE/__bcdn_perma_cache__/$PZDIR/$path" 2>/dev/null
    fi
    rm -f "$tmp"
  ) &
  n=$((n + 1))
  if [ "$((n % POOL))" -eq 0 ]; then
    wait
    [ "$((n % 5000))" -lt "$POOL" ] && echo "  ...$n/$total"
  fi
done < <(reader)
wait
echo "shard complete: $MANIFEST ($n images)"
