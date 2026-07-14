#!/usr/bin/env python3
"""Export the AutoTrader UK non-car channels from the local DB into import chunks.

Live-safe design:
  * category_id + make_id are REMAPPED at import via (cat_title,type) subqueries —
    local ids differ from live. `autotrader-makes.sql` + `autotrader-categories.sql`
    seed any missing category/make rows first (idempotent).
  * stock_code is exported NULL and rebuilt to SM<id> in a finalize file (it is
    id-derived; live ids differ).
  * DELETE is scoped to the channel's category for the 6 NEW categories (empty on
    live -> safe + re-import-proof). Trucks gets NO delete (shared with
    cars-routed-to-Trucks — a category delete would wipe those).
  * chunks roll at ~280 MB uncompressed (~30-40 MB gzipped).

Run (AFTER enrich finishes):  python scripts/autotrader_chunks_from_db.py
"""
import pymysql, gzip, os, glob

OUT = "db-export/autotrader-chunks"
ROLL_BYTES = 280 * 1024 * 1024      # ~35 MB gz
PERSTMT = 50
# (category name, do_delete) — Trucks reuses the shared existing category
CHANNELS = [("Vans", True), ("Bikes", True), ("Motorhomes", True),
            ("Caravans", True), ("Tractors", True), ("Heavy Machinery", True), ("Trucks", False)]

# insert columns: all product cols EXCEPT id, mongo_id, stock_code.
# category_id + make_id are emitted as expressions, not literals.
LITERAL_COLS = ["title", "model", "model_code", "year", "engine_cc", "mileage_km", "fuel",
    "transmission", "`condition`", "color", "steering", "seats", "doors", "drive_type",
    "axles", "load_capacity_kg", "power_hp", "emission_standard", "running_hours",
    "price", "country", "website", "body_style", "product_link", "front_image",
    "other_images", "product_details", "specifications", "created_at", "updated_at",
    "front_image_dead_at", "front_image_source", "other_images_source", "enriched",
    "enquire_sort", "shuffle_key"]
SELECT_COLS = [c.strip("`") for c in LITERAL_COLS]          # for the SELECT
INSERT_COLS = LITERAL_COLS + ["category_id", "make_id"]     # order of VALUES

conn = pymysql.connect(host="127.0.0.1", port=3307, user="root", db="supreme_motors", charset="utf8mb4")

def q(v):
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    s = str(v).replace("\\", "\\\\").replace("'", "\\'").replace("\n", "\\n").replace("\r", "")
    return "'" + s + "'"

def row_values(r, make_name):
    vals = [q(r[c]) for c in SELECT_COLS]
    vals.append("@cat")
    vals.append("(SELECT id FROM `categories` WHERE `cat_title`=" + q(make_name) + " AND `type`='make' LIMIT 1)"
                if make_name else "NULL")
    return "(" + ",".join(vals) + ")"

if os.path.isdir(OUT):
    for f in glob.glob(OUT + "/*"):
        os.unlink(f)
os.makedirs(OUT, exist_ok=True)

collist = ", ".join(INSERT_COLS)
makes_seen = set()

for cat_name, do_delete in CHANNELS:
    cur = conn.cursor(pymysql.cursors.SSDictCursor)          # server-side, streaming
    cur.execute(
        "SELECT p.*, m.cat_title AS _make FROM products p "
        "JOIN categories c ON c.id=p.category_id AND c.type='category' "
        "LEFT JOIN categories m ON m.id=p.make_id AND m.type='make' "
        "WHERE p.website='autotraderuk' AND c.cat_title=%s", (cat_name,))
    fidx = 0; fh = None; instmt = 0; nrows = 0; bytes_in_file = 0
    slug = cat_name.lower().replace(" ", "-")

    def close_stmt():
        global instmt
        if instmt:
            fh.write(";\n"); instmt = 0

    def close_file():
        global fh
        if fh:
            close_stmt(); fh.write("COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n"); fh.close(); fh = None

    def open_file():
        global fh, fidx, instmt, bytes_in_file
        fidx += 1
        fh = gzip.open(f"{OUT}/{slug}-{fidx:02d}.sql.gz", "wt", encoding="utf-8")
        fh.write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n")
        fh.write("SET @cat=(SELECT id FROM `categories` WHERE `cat_title`=" + q(cat_name)
                 + " AND `type`='category' LIMIT 1);\n")
        if fidx == 1 and do_delete:
            fh.write("DELETE FROM `products` WHERE `website`='autotraderuk' AND `category_id`=@cat;\n")
        instmt = 0; bytes_in_file = 0

    open_file()
    for r in cur:
        mk = r.get("_make")
        if mk:
            makes_seen.add(mk)
        if bytes_in_file >= ROLL_BYTES and instmt == 0:
            close_file(); open_file()
        if instmt == 0:
            fh.write(f"INSERT INTO `products` ({collist}) VALUES\n")
        else:
            fh.write(",\n")
        line = row_values(r, mk)
        fh.write(line); instmt += 1; nrows += 1; bytes_in_file += len(line)
        if instmt >= PERSTMT:
            close_stmt()
        if bytes_in_file >= ROLL_BYTES and instmt == 0:
            close_file(); open_file()
    close_file(); cur.close()
    # finalize: rebuild stock_code from the new live ids
    with open(f"{OUT}/{slug}-zz-finalize.sql", "w", encoding="utf-8") as f:
        f.write("SET @cat=(SELECT id FROM `categories` WHERE `cat_title`=" + q(cat_name)
                + " AND `type`='category' LIMIT 1);\n")
        f.write("UPDATE `products` SET `stock_code`=CONCAT('SM',id) "
                "WHERE `website`='autotraderuk' AND `category_id`=@cat AND (`stock_code` IS NULL OR `stock_code`='');\n")
    print(f"{cat_name}: {nrows} rows -> {fidx} chunk(s)")

# idempotent makes seed (type='make')
with open("db-export/autotrader-makes.sql", "w", encoding="utf-8") as f:
    f.write("-- Idempotent make rows (type='make') for the new-channel products. Run before chunks.\n")
    for mk in sorted(makes_seen):
        f.write("INSERT INTO `categories` (`cat_title`,`type`,`created_at`,`updated_at`)\n"
                "SELECT c,t,a,u FROM (SELECT " + q(mk) + " c,'make' t,NOW() a,NOW() u) x\n"
                "WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `cat_title`=" + q(mk) + " AND `type`='make');\n")
print(f"makes seed: {len(makes_seen)} make rows -> db-export/autotrader-makes.sql")
conn.close()
