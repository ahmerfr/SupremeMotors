#!/usr/bin/env bash
# Export supreme_motors into phpMyAdmin-friendly .zip chunks (each well under the
# 50 MiB upload limit). Import order on live: 00 first, then 01..20 (any order).
#   00-schema-and-small.zip  — all table structures + small-table data + products
#                              CREATE (no product rows).
#   01..20-products.zip      — products rows only, split into 20 equal row-count
#                              ranges by id (~20-30 MB each compressed).
# --single-transaction => consistent, non-locking snapshot of the live InnoDB DB.
# The temp _autotraderuk_price_bak table is intentionally excluded.
# phpMyAdmin accepts .zip (and .gz) and decompresses on import.
set -uo pipefail

DUMP="C:/xampp/mysql/bin/mysqldump.exe"
MYSQL="C:/xampp/mysql/bin/mysql.exe"
H=127.0.0.1; P=3307; U=root; DB=supreme_motors
OUT="C:/xampp/htdocs/SupremeMotors/db-export"
CHUNKS=20
rm -rf "$OUT"; mkdir -p "$OUT"
COMMON="-h $H -P $P -u $U --single-transaction --quick --no-tablespaces --skip-lock-tables"

q(){ "$MYSQL" -h $H -P $P -u $U $DB -N -e "$1" 2>/dev/null; }

TOTAL=$(q "SELECT COUNT(*) FROM products")
PER=$(( (TOTAL + CHUNKS - 1) / CHUNKS ))
echo "products=$TOTAL  chunks=$CHUNKS  ~${PER} rows/chunk"

# boundary ids: the id at each PER-th row (ordered by id). LO/HI arrays build
# contiguous, non-overlapping ranges covering every row exactly once.
LO=(1); HI=()
for k in $(seq 1 $((CHUNKS-1))); do
  off=$(( PER * k ))
  bid=$(q "SELECT id FROM products ORDER BY id LIMIT 1 OFFSET $off")
  HI+=( $(( bid - 1 )) )
  LO+=( "$bid" )
done
HI+=( 999999999 )

PHP="C:/xampp/php/php.exe"
mkzip(){ # $1=sql-path $2=zip-path  (store the .sql at zip root, entry name = basename)
  "$PHP" -r '$z=new ZipArchive();$z->open($argv[2],ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFile($argv[1],basename($argv[1]));$z->close();' "$1" "$2"
}
zipdump(){ # $1=outfile-basename  $2..=mysqldump args
  local base="$1"; shift
  local sql="$OUT/${base}.sql"
  "$DUMP" $COMMON "$@" > "$sql"
  mkzip "$sql" "$OUT/${base}.zip" && rm -f "$sql"
}

echo "[00] schema + small tables ..."
{
  "$DUMP" $COMMON --ignore-table=$DB.products --ignore-table=$DB._autotraderuk_price_bak $DB
  "$DUMP" $COMMON --no-data $DB products
} > "$OUT/00-schema-and-small.sql"
mkzip "$OUT/00-schema-and-small.sql" "$OUT/00-schema-and-small.zip" && rm -f "$OUT/00-schema-and-small.sql"
echo "[00] done: $(du -h "$OUT/00-schema-and-small.zip" | cut -f1)"

for i in $(seq 0 $((CHUNKS-1))); do
  n=$(printf '%02d' $((i+1)))
  a=${LO[$i]}; b=${HI[$i]}
  echo "[$n] products id ${a}..${b} ..."
  zipdump "${n}-products" --no-create-info --where="id BETWEEN $a AND $b" $DB products
  echo "[$n] done: $(du -h "$OUT/${n}-products.zip" | cut -f1)"
done

echo "ALL DONE:"
ls -1Sh "$OUT"
echo "largest chunk: $(ls -S "$OUT"/*.zip | head -1 | xargs du -h | cut -f1)"
