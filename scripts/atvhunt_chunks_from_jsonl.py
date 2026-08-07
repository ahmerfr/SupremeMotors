#!/usr/bin/env python3
"""Build upload-friendly gzipped SQL chunks for atvhunt directly from JSONL — DB-free,
same shape as the goo-net / autowini / jaftim chunkers. website='atvhunt', so its DELETE
and its rows never touch another source's inventory.

  python atvhunt_chunks_from_jsonl.py atvhunt-all.jsonl out/ [chunk_rows] [now]
  python atvhunt_chunks_from_jsonl.py --selftest

Input may be .jsonl or .jsonl.gz. Column list is taken from Products::$fillable, not
guessed: price_usd -> `price`, images -> `other_images`(+_source), and vin / city / state
/ source_id are NOT columns — they already ride inside the `specifications` JSON that
atvhunt_parse.py builds, so nothing is lost by omitting them.
"""
import sys, os, json, gzip, glob

PERSTMT = 50
CATEGORY_FALLBACK = 1302              # ATVs & UTVs

COLS = ["title", "model", "year", "engine_cc", "mileage_km", "transmission", "`condition`",
        "color", "power_hp", "category_id", "make_id", "price", "website", "country",
        "body_style", "product_link", "front_image", "front_image_source",
        "other_images", "other_images_source", "product_details", "specifications",
        "created_at", "updated_at"]
COLLIST = ", ".join(COLS)


def q(v):
    """Single-quoted SQL literal. Backslash AND quote escaped — the autowini chunker's
    .replace("'", "\\'") relies on NO_BACKSLASH_ESCAPES being off; doubling the quote is
    portable either way."""
    if v is None or v == "":
        return "NULL"
    s = str(v).replace("\\", "\\\\").replace("'", "''")
    return "'" + s.replace("\n", " ").replace("\r", " ") + "'"


def qn(v):
    if v in (None, ""):
        return "NULL"
    try:
        return str(int(v))
    except (TypeError, ValueError):
        return "NULL"


def row_vals(r, now):
    imgs = json.dumps(r.get("images") or [], ensure_ascii=False, separators=(",", ":"))
    specs = json.dumps(r.get("specifications") or {}, ensure_ascii=False, separators=(",", ":"))
    fi = r.get("front_image")
    return "(" + ", ".join([
        q((r.get("title") or "")[:255]), q(r.get("model")), qn(r.get("year")),
        qn(r.get("engine_cc")), qn(r.get("mileage_km")), q(r.get("transmission")),
        q(r.get("condition") or "Used"), q(r.get("color")), qn(r.get("power_hp")),
        str(int(r.get("category_id") or CATEGORY_FALLBACK)), qn(r.get("make_id")),
        str(float(r.get("price_usd") or 0)), q("atvhunt"), q(r.get("country") or "USA"),
        q(r.get("body_style")), q(r.get("product_link")), q(fi), q(fi),
        q(imgs), q(imgs), q(r.get("product_details") or ""), q(specs), q(now), q(now),
    ]) + ")"


def run(inp, outdir, chunk, now):
    op = gzip.open if inp.endswith(".gz") else open
    rows = [json.loads(l) for l in op(inp, "rt", encoding="utf-8") if l.strip()]
    # last row wins per source_id: a resumed shard can append the same listing twice
    dedup = {}
    for r in rows:
        dedup[r.get("source_id") or r.get("product_link")] = r
    rows = list(dedup.values())
    n = len(rows)
    files = max(1, (n + chunk - 1) // chunk)
    if os.path.isdir(outdir):
        for f in glob.glob(outdir + "/*"):
            os.unlink(f)
    os.makedirs(outdir, exist_ok=True)
    print(f"{n} rows -> {files} chunks of {chunk}")

    fh = {"f": None, "i": 0, "in": 0}

    def openf():
        fh["i"] += 1
        fh["f"] = gzip.open(f"{outdir}/atvhunt-{fh['i']:02d}of{files:02d}.sql.gz",
                            "wt", encoding="utf-8")
        fh["f"].write(f"-- Supreme Motors atvhunt inventory — chunk {fh['i']}/{files}. Import in order.\n")
        fh["f"].write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n")
        if fh["i"] == 1:
            fh["f"].write("DELETE FROM `products` WHERE `website`='atvhunt';\n")
        fh["in"] = 0

    def closestmt():
        if fh["in"] > 0:
            fh["f"].write(";\n"); fh["in"] = 0

    def closef():
        if fh["f"]:
            closestmt(); fh["f"].write("COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n")
            fh["f"].close(); fh["f"] = None

    openf()
    for i, r in enumerate(rows):
        if i > 0 and i % chunk == 0:
            closef(); openf()
        if fh["in"] == 0:
            fh["f"].write(f"INSERT INTO `products` ({COLLIST}) VALUES\n")
        else:
            fh["f"].write(",\n")
        fh["f"].write(row_vals(r, now)); fh["in"] += 1
        if fh["in"] >= PERSTMT:
            closestmt()
    closef()
    with open(f"{outdir}/atvhunt-{files + 1:02d}of{files:02d}-finalize.sql", "w", encoding="utf-8") as f:
        f.write("-- Run LAST: rebuild stock_code from new ids.\n"
                "UPDATE `products` SET `stock_code`=CONCAT('AH', id) "
                "WHERE `website`='atvhunt' AND (`stock_code` IS NULL OR `stock_code`='');\n")
    return n, files


def selftest():
    import tempfile, shutil, re
    d = tempfile.mkdtemp()
    try:
        p = os.path.join(d, "in.jsonl")
        with open(p, "w", encoding="utf-8") as f:
            for r in ({"source_id": "1", "title": "2026 Polaris RANGER", "model": "RANGER",
                       "year": 2026, "make_id": 954, "price_usd": 14999, "category_id": 1302,
                       "condition": "New", "images": ["https://x/a.jpg"], "front_image": "https://x/a.jpg",
                       "specifications": {"VIN": "X1"}, "product_details": "<ul></ul>"},
                      # same source_id, re-fetched later at a lower price: LAST must win,
                      # because a resumed shard appends the fresher copy after the stale one
                      {"source_id": "1", "title": "2026 Polaris RANGER", "model": "RANGER",
                       "year": 2026, "make_id": 954, "price_usd": 13499, "category_id": 1302,
                       "condition": "New", "images": ["https://x/a.jpg"], "front_image": "https://x/a.jpg",
                       "specifications": {"VIN": "X1"}, "product_details": "<ul></ul>"},
                      {"source_id": "2", "title": "O'Brien's ATV \\ test", "price_usd": None}):
                f.write(json.dumps(r) + "\n")
        n, files = run(p, os.path.join(d, "o"), 3000, "2026-08-07 00:00:00")
        assert n == 2, n                                    # deduped on source_id
        sql = gzip.open(os.path.join(d, "o", "atvhunt-01of01.sql.gz"), "rt", encoding="utf-8").read()
        assert "DELETE FROM `products` WHERE `website`='atvhunt'" in sql
        assert "'O''Brien''s ATV \\\\ test'" in sql, "quote/backslash escaping"
        assert "13499.0" in sql and "14999.0" not in sql, "last write must win on source_id"
        assert "0.0" in sql                                # null price -> 0.0, never NULL
        assert sql.count("INSERT INTO") == 1 and sql.rstrip().endswith("SET FOREIGN_KEY_CHECKS=1;")
        assert "'{\"VIN\":\"X1\"}'" in sql, "specifications JSON must be embedded"
        fin = open(os.path.join(d, "o", "atvhunt-02of01-finalize.sql"), encoding="utf-8").read()
        assert "CONCAT('AH', id)" in fin
        print("CHUNKER SELFTEST PASSED")
    finally:
        shutil.rmtree(d, ignore_errors=True)


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest()
    else:
        run(sys.argv[1], sys.argv[2],
            int(sys.argv[3]) if len(sys.argv) > 3 else 3000,
            sys.argv[4] if len(sys.argv) > 4 else "2026-08-07 00:00:00")
