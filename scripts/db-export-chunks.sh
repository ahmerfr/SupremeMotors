#!/usr/bin/env bash
# Export supreme_motors into host-friendly chunks:
#   00-schema-and-small.sql.gz  — all table structures + full data for the small
#                                 tables + products CREATE (no product rows).
#   01..10-products.sql.gz      — products rows only, split on id deciles.
# Import order on the host: 00 first, then 01..10 (any order after 00).
# --single-transaction => consistent, non-locking snapshot of the live InnoDB DB.
# The temp _autotraderuk_price_bak table is intentionally excluded.
set -uo pipefail

DUMP="C:/xampp/mysql/bin/mysqldump.exe"
H=127.0.0.1; P=3307; U=root; DB=supreme_motors
OUT="C:/xampp/htdocs/SupremeMotors/db-export"
mkdir -p "$OUT"
COMMON="-h $H -P $P -u $U --single-transaction --quick --no-tablespaces --skip-lock-tables"

# id decile boundaries (each chunk ~83.8k rows)
LO=(1 244896 328729 412562 496395 580228 664061 747894 831727 915560)
HI=(244895 328728 412561 496394 580227 664060 747893 831726 915559 999999999)

echo "[00] schema + small tables ..."
{
  "$DUMP" $COMMON --ignore-table=$DB.products --ignore-table=$DB._autotraderuk_price_bak $DB
  "$DUMP" $COMMON --no-data $DB products
} | gzip > "$OUT/00-schema-and-small.sql.gz"
echo "[00] done: $(du -h "$OUT/00-schema-and-small.sql.gz" | cut -f1)"

for i in $(seq 0 9); do
  n=$(printf '%02d' $((i+1)))
  a=${LO[$i]}; b=${HI[$i]}
  echo "[$n] products id ${a}..${b} ..."
  "$DUMP" $COMMON --no-create-info --where="id BETWEEN $a AND $b" $DB products \
    | gzip > "$OUT/${n}-products.sql.gz"
  echo "[$n] done: $(du -h "$OUT/${n}-products.sql.gz" | cut -f1)"
done

echo "ALL DONE. files in $OUT:"
ls -1sh "$OUT"
