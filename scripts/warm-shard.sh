#!/usr/bin/env bash
# Warm one manifest shard into Bunny's perma-cache storage.
# Runs on a GitHub Actions runner (its own egress IP), so 20 shards = 20 IPs
# downloading from m.atcdn in parallel — beating the single-IP origin cap.
# For each `<size>/<hash>` line: download the image from m.atcdn, then PUT it
# straight into the pull-zone's perma-cache storage path (which makes Bunny
# serve it CDN-Cache: HIT with no origin pull).
#
# Env: BUNNY_STORAGE_KEY (secret), STORAGE_ZONE, PZDIR.  Arg 1: manifest file.
set -uo pipefail

MANIFEST="${1:?usage: warm-shard.sh <manifest-file>}"
: "${BUNNY_STORAGE_KEY:?BUNNY_STORAGE_KEY secret not set}"
STORAGE_ZONE="${STORAGE_ZONE:-suprememotors-media}"
: "${PZDIR:?PZDIR (perma-cache pullzone dir) not set}"
STORAGE="https://storage.bunnycdn.com"
ORIGIN="https://m.atcdn.co.uk"
POOL="${POOL:-40}"

# Per image: download from m.atcdn, PUT into the Bunny perma-cache path. Each
# image runs in an inline ( ) & subshell (creds are in-scope from the exports
# above); we cap concurrency by waiting every $POOL launches. Verified working
# on both git-bash and Linux runners.
# read plain or gzipped manifest
reader() { case "$MANIFEST" in *.gz) zcat "$MANIFEST" 2>/dev/null;; *) cat "$MANIFEST";; esac; }

total=$(reader | grep -cvE '^[[:space:]]*$')
echo "warming $total images from $MANIFEST at pool $POOL"
n=0
while IFS= read -r sh; do
  [ -z "$sh" ] && continue
  (
    size="${sh%%/*}"; hash="${sh##*/}"
    tmp="$(mktemp)"
    curl -s --retry 2 --retry-delay 1 --max-time 30 -A "Mozilla/5.0" -o "$tmp" "$ORIGIN/a/media/$size/$hash.jpg" 2>/dev/null
    if [ "$(wc -c < "$tmp" 2>/dev/null || echo 0)" -gt 1000 ]; then
      curl -s -o /dev/null -X PUT --max-time 30 \
        -H "AccessKey: $BUNNY_STORAGE_KEY" -H "Content-Type: image/jpeg" --data-binary "@$tmp" \
        "$STORAGE/$STORAGE_ZONE/__bcdn_perma_cache__/$PZDIR/a/media/$size/$hash.jpg" 2>/dev/null
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
