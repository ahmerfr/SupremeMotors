#!/usr/bin/env python3
"""Build upload-friendly gzipped SQL chunks DIRECTLY from clean JSONL — no local
DB needed (the local MariaDB crashes on the 252k bulk insert due to the FULLTEXT
index). Produces the exact same chunk format ScrapeGoonet::exportChunk emits, so
the live import is identical: N files of chunk_rows, 300-row extended inserts,
each file its own transaction, DELETE only in chunk 1, a finalize file rebuilds
stock_code from the new live ids. id/stock_code omitted -> no live id collision."""
import sys, os, json, gzip, glob

INP = sys.argv[1]; OUTDIR = sys.argv[2]
CHUNK = int(sys.argv[3]) if len(sys.argv) > 3 else 6000
PERSTMT = 50
NOW = sys.argv[4] if len(sys.argv) > 4 else "2026-07-08 00:00:00"

COLS = ["title","model","year","mileage_km","fuel","transmission","`condition`","color",
        "body_style","engine_cc","drive_type","doors","steering","category_id","price",
        "website","country","product_link","front_image","front_image_source",
        "other_images","other_images_source","product_details","created_at","updated_at"]
COLLIST = ", ".join(COLS)

def q(v):
    if v is None or v == "":
        return "NULL"
    s = str(v).replace("\\", "\\\\").replace("'", "\'").replace("\n", " ").replace("\r", " ")
    return "'" + s + "'"

def qn(v):  # numeric-or-null
    return "NULL" if v in (None, "") else str(int(v))

def row_vals(r):
    imgs = json.dumps(r.get("images", []), ensure_ascii=False, separators=(",", ":"))
    fi = r.get("front_image")
    return "(" + ", ".join([
        q((r.get("title") or "")[:255]), q(r.get("model")), qn(r.get("year")),
        qn(r.get("mileage_km")), q(r.get("fuel")), q(r.get("transmission")),
        q(r.get("condition") or "Used"), q(r.get("color")), q(r.get("body_style")),
        qn(r.get("engine_cc")), q(r.get("drive_type")), qn(r.get("doors")), q(r.get("steering")),
        str(int(r.get("category_id") or 20)), str(float(r.get("price_usd") or 0)),
        q("goonet"), q(r.get("country") or "Japan"), q(r.get("product_link")),
        q(fi), q(fi), q(imgs), q(imgs), q(r.get("product_details") or ""), q(NOW), q(NOW),
    ]) + ")"

rows = [json.loads(l) for l in open(INP, encoding="utf-8") if l.strip()]
n = len(rows); files = (n + CHUNK - 1) // CHUNK
if os.path.isdir(OUTDIR):
    for f in glob.glob(OUTDIR + "/*"): os.unlink(f)
os.makedirs(OUTDIR, exist_ok=True)
print(f"{n} rows -> {files} chunks of {CHUNK}")

fh = None; fidx = 0; instmt = 0
def openf():
    global fh, fidx, instmt
    fidx += 1
    fh = gzip.open(f"{OUTDIR}/goonet-{fidx:02d}of{files:02d}.sql.gz", "wt", encoding="utf-8")
    fh.write(f"-- Supreme Motors goo-net inventory — chunk {fidx}/{files}. Import in order.\n")
    fh.write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n")
    if fidx == 1:
        fh.write("DELETE FROM `products` WHERE `website`='goonet';\n")
    instmt = 0
def closestmt():
    global instmt
    if instmt > 0: fh.write(";\n"); instmt = 0
def closef():
    global fh
    if fh: closestmt(); fh.write("COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n"); fh.close(); fh = None

openf()
for i, r in enumerate(rows):
    if i > 0 and i % CHUNK == 0:
        closef(); openf()
    if instmt == 0:
        fh.write(f"INSERT INTO `products` ({COLLIST}) VALUES\n")
    else:
        fh.write(",\n")
    fh.write(row_vals(r)); instmt += 1
    if instmt >= PERSTMT: closestmt()
closef()

with open(f"{OUTDIR}/goonet-{files+1:02d}of{files:02d}-finalize.sql", "w", encoding="utf-8") as f:
    f.write("-- Run LAST: rebuild stock_code from new ids.\n")
    f.write("UPDATE `products` SET `stock_code`=CONCAT('GN', id) "
            "WHERE `website`='goonet' AND (`stock_code` IS NULL OR `stock_code`='');\n")
with open(f"{OUTDIR}/README.txt", "w", encoding="utf-8") as f:
    f.write(f"Supreme Motors goo-net inventory ({n:,} cars)\n" + "="*48 + "\n\n"
            "Gzipped SQL (.sql.gz). phpMyAdmin Import reads .gz directly; or CLI:\n"
            f"    zcat goonet-01of{files}.sql.gz | mysql -u USER -p DBNAME\n\n"
            "Import IN ORDER. Each file = its own transaction. Chunk 1 clears old\n"
            "goonet rows; the finalize file (run LAST) rebuilds stock codes.\n"
            "Only touches website='goonet' rows — other sources untouched.\n\n"
            "After import on live:  php artisan cache:clear\n")
print("chunks written")
