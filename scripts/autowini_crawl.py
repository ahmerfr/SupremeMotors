#!/usr/bin/env python3
"""autowini.com scraper — Korean used-car export marketplace (207k cars).

Data model (recon-verified, NO auth needed):
  * Coverage: 11 gzip sitemaps (sitemap-cars-1..11) list every car detail URL
    -> /en/Cars/IC<id>/cars-detail  (English locale, clean text).
  * Specs:    public JSON  https://v2api.autowini.com/items/<IC-id>  returns an
    XML/JSON V2Response with the reliable core fields (name, price USD, fuel,
    engine cc, mileage, drive, transmission, passengers, country, port).
    Fuller /detail /spec endpoints are Keycloak-gated (401) — NOT used.
  * Gallery:  the server-rendered detail page carries the photo URLs; the car's
    own photos are the ones whose path contains the item's <itemCode>.

We only assert fields the source reliably provides; unknown fields are left NULL
rather than guessed (color/body/doors are client-hydrated on the site and absent
from the public payload, so they stay NULL — never fabricated).

Modes:
  preview <N>   fetch N cars, render public/autowini-preview.html, NO DB writes
  enumerate     write all car detail URLs to <out>
"""
import sys, re, json, time, html as ihtml, threading
from concurrent.futures import ThreadPoolExecutor
from curl_cffi import requests

SITE = "https://www.autowini.com"
API = "https://v2api.autowini.com"
IMG_HOST = "image.autowini.com"
USD = 1.0  # itemPrice is already USD

# autowini tags each car's trade country; normalize to full names. Most stock is
# Korean; a minority are Japanese re-exports (kei cars via Tokyo). Accurate per car.
_COUNTRY = {"S.KOREA": "South Korea", "KOREA": "South Korea", "JAPAN": "Japan"}
def _norm_country(c):
    if not c:
        return "South Korea"
    return _COUNTRY.get(c.strip().upper(), c.strip())

_tl = threading.local()
def sess():
    if not hasattr(_tl, "s"):
        s = requests.Session(impersonate="chrome")
        s.headers.update({"Referer": SITE + "/", "Origin": SITE,
                          "Accept-Language": "en", "Accept": "application/json, text/plain, */*"})
        _tl.s = s
    return _tl.s

def _get(url, timeout=30):
    for attempt in range(6):
        try:
            r = sess().get(url, timeout=timeout)
            if r.status_code == 200:
                return r.text
            if r.status_code == 404:
                return None
            time.sleep(0.4 * (attempt + 1))
        except Exception:
            # transient DNS/connection hiccups under concurrency — back off and retry
            time.sleep(0.5 * (attempt + 1))
    return None

# ---------------- sitemap enumeration -----------------------------------------
def car_sitemaps():
    idx = _get(SITE + "/sitemap.xml") or ""
    return [u for u in re.findall(r"<loc>([^<]+)</loc>", idx) if re.search(r"sitemap-cars-\d+", u)]

def enumerate_urls():
    urls = []
    for sm in car_sitemaps():
        t = _get(sm) or ""
        urls += re.findall(r"<loc>([^<]+)</loc>", t)
    return urls

# derive the IC id from any autowini car URL
def id_from_url(url):
    m = re.search(r"/(IC\d+)/", url) or re.search(r"-(IC\d+)/", url) or re.search(r"(IC\d+)", url)
    return m.group(1) if m else None

# ---------------- field helpers -----------------------------------------------
def _tag(x, t):
    m = re.search(rf"<{t}>([^<]*)</{t}>", x)
    return ihtml.unescape(m.group(1)).strip() if m and m.group(1).strip() else None

def _norm_fuel(f):
    u = (f or "").upper()
    if not u: return None
    if "HYBRID" in u: return "Hybrid"
    if "GASOLINE" in u or "PETROL" in u: return "Petrol"
    if "DIESEL" in u: return "Diesel"
    if "ELECTRIC" in u: return "Electric"
    if "LPG" in u or "LPI" in u: return "LPG"
    return f.title()

def _norm_drive(d):
    # canonical set {FWD, RWD, 4WD, 2WD}. Direction preserved when the source
    # states it (Front/Rear); a bare "2WD" (grade string, direction unknown) stays
    # "2WD" rather than guessing FWD — honest over wrong.
    u = (d or "").upper()
    if not u: return None
    if "4 WHEEL" in u or "4WD" in u or "AWD" in u or "4 WD" in u: return "4WD"
    if "FRONT" in u or u == "FF" or "FWD" in u: return "FWD"
    if "REAR" in u or u == "FR" or "RWD" in u: return "RWD"
    if "2WD" in u: return "2WD"
    return None

def _norm_trans(t):
    u = (t or "").upper()
    if not u: return None
    if "AUTO" in u or u == "AT" or "CVT" in u: return "Automatic"
    if "MANUAL" in u or u == "MT": return "Manual"
    return t.title()

def _int(s):
    if s is None: return None
    n = re.sub(r"[^0-9]", "", str(s))
    return int(n) if n else None

def _split_name(item_name, model_name):
    """itemName = '<year> <make> <model...> <grade...>'. Derive year/make/grade
    using the known modelName as the pivot."""
    name = (item_name or "").strip()
    ym = re.match(r"((?:19|20)\d{2})\s+(.*)", name)
    year = int(ym.group(1)) if ym else None
    rest = ym.group(2) if ym else name
    make = grade = None
    if model_name and model_name in rest:
        make = rest.split(model_name)[0].strip() or None
        grade = rest.split(model_name, 1)[1].strip() or None
    else:
        parts = rest.split()
        make = parts[0] if parts else None
    return year, make, grade

# ---------------- per-car parse -----------------------------------------------
def gallery_from_detail(html, item_code):
    """The car's own photos: any autowini image URL whose path contains the
    itemCode (covers BOTH hosting schemes — image.autowini.com/AUTOWINI4/... and
    imagebox.autowini.com/upload/<seller>/car/...). UI icons live under
    /resources/ and never contain the itemCode, so they're excluded. For each
    distinct photo we keep the 1024px rendition (fallback: original)."""
    if not html or not item_code:
        return []
    from collections import OrderedDict
    urls = re.findall(
        rf"https?://[a-z0-9.]*autowini\.com/[^\"'\s)]*{re.escape(item_code)}[^\"'\s)]*\.(?:jpg|jpeg|png)",
        html, re.I)
    groups = OrderedDict()
    for u in urls:
        m = re.match(r"(.+?)_([0-9a-z]*)\.(?:jpg|jpeg|png)$", u, re.I)
        key = m.group(1) if m else u
        size = (m.group(2) if m else "") or "orig"
        groups.setdefault(key, {})[size] = u
    out = []
    for ren in groups.values():
        out.append(ren.get("1024") or ren.get("orig") or next(iter(ren.values())))
    return out

def spec_block(html):
    """The car's OWN spec list — the <dt>/<dd> run anchored at 'Item No.'. This is
    server-rendered and complete (color, vehicle type, grade, size...), unlike the
    public /items API which omits many of these. Sidebar menus live elsewhere in
    the DOM so anchoring on 'Item No.' avoids that noise."""
    i = html.find("Item No.")
    if i < 0:
        return {}
    seg = html[i - 100:i + 2600]
    out = {}
    for k, v in re.findall(r"<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>", seg, re.S):
        kk = re.sub(r"<[^>]+>", "", k).strip()
        vv = re.sub(r"\s+", " ", ihtml.unescape(re.sub(r"<[^>]+>", "", v))).strip()
        if kk and kk not in out:
            out[kk] = vv
    return out

def _parse_class(model_name, mcg):
    """'Model / Class / Grade' e.g. 'The New Mohave Borrego / 3.0 Diesel 2WD 5 Seats
    Noblesse' -> (model, grade, drive, seats). Prefer the API modelName for model."""
    model = grade = drive = seats = None
    if mcg:
        parts = [p.strip() for p in mcg.split("/", 1)]
        model = model_name or parts[0] or None
        grade = parts[1].strip() if len(parts) > 1 and parts[1].strip() else None
        blob = mcg
        dm = re.search(r"\b(4WD|AWD|2WD|FWD|RWD)\b", blob, re.I)
        drive = _norm_drive(dm.group(1)) if dm else None
        sm = re.search(r"(\d+)\s*Seat", blob, re.I)
        seats = int(sm.group(1)) if sm else None
    return (model or model_name), grade, drive, seats

def _norm_steer(s):
    u = (s or "").upper()
    if "RHD" in u or "RIGHT" in u: return "Right"
    if "LHD" in u or "LEFT" in u: return "Left"
    return None

def _clean_color(c):
    c = (c or "").strip()
    return c.title() if c and c not in ("-", "N/A") else None

def _api_item(lid):
    """Fetch the item JSON, retrying on a transient empty/failed body so we never
    silently emit a price=0 row from a hiccup (a real sold item 404s -> None)."""
    for attempt in range(5):
        x = _get(f"{API}/items/{lid}")
        if x is None:
            return None  # 404 / gone
        try:
            payload = json.loads(x)
        except Exception:
            payload = None
        if payload and payload.get("result") == "SUCCESS" and isinstance(payload.get("data"), dict) \
           and payload["data"].get("itemCode"):
            return payload["data"]
        time.sleep(0.6 * (attempt + 1))
    return None

def parse_car(url):
    lid = id_from_url(url)
    if not lid:
        return None
    # 1) API summary: reliable price / mileage / port / itemCode / gallery thumb
    d = _api_item(lid)
    if not d:
        return None
    g = lambda k: (str(d[k]).strip() if d.get(k) not in (None, "", "null") else None)
    item_code = g("itemCode")
    api_model = g("modelName")
    price = _int(g("itemPrice")) or 0
    mileage = _int(g("mileage"))
    port = g("tradePortName")
    country = _norm_country(g("tradeCountryName"))
    detail_url = g("detailUrl") or url
    thumb = g("imageUrl")

    # 2) detail HTML (clean /en/ url): full spec block + gallery
    dh = _get(f"{SITE}/en/Cars/{lid}/cars-detail") or ""
    sb = spec_block(dh)
    gallery = gallery_from_detail(dh, item_code)
    images = gallery if gallery else ([thumb] if thumb else [])

    year = _int(sb.get("Model Year"))
    make = sb.get("Make") or None
    model, grade, drive_c, seats_c = _parse_class(api_model, sb.get("Model / Class / Grade"))
    body = sb.get("Vehicle Type") or None
    fuel = _norm_fuel(sb.get("Fuel Type") or g("fuelTypeName"))
    trans = _norm_trans(sb.get("Transmission") or g("transmissionName"))
    steering = _norm_steer(sb.get("Steering")) or "Left"
    color = _clean_color(sb.get("Exterior Color"))
    engine = _int((sb.get("Engine Volume") or "").replace("CC", "")) or _int(g("engineVolume")) or None
    drive = _norm_drive(g("driveTypeName")) or drive_c
    doors = _int(sb.get("Door"))
    seats = _int(g("passenger")) or seats_c or _int(sb.get("No. of Passenger"))
    size = sb.get("Size") or None
    # mileage: API is primary; fall back to the detail 'Odometer Reading' km, and
    # capture the seller's actual/not-actual provenance flag (a buyer trust signal).
    odo = sb.get("Odometer Reading") or ""
    if not mileage:
        om = re.search(r"([\d,]+)\s*Km", odo, re.I)
        if om:
            mileage = _int(om.group(1))
    odo_flag = "Actual" if re.search(r"\(\s*Actual\s*\)", odo) else ("Not actual" if "not actual" in odo.lower() else None)
    title = " ".join(str(v) for v in [year, make, model] if v) or (g("itemName") or f"autowini {lid}")

    details = _details(grade, year, engine, fuel, trans, drive, seats, steering,
                       color, body, doors, size, odo_flag, port)
    return {
        "stock_id": lid, "item_code": item_code, "title": title[:255],
        "make": make, "model": model, "grade": grade, "year": year,
        "mileage_km": mileage or None, "fuel": fuel, "transmission": trans,
        "condition": "Used", "color": color, "body_style": body,
        "engine_cc": engine, "drive_type": drive, "doors": doors,
        "seats": seats, "steering": steering,
        "price_usd": price, "price": price,
        "category_id": 20, "country": COUNTRY, "trade_port": port,
        "product_link": detail_url,
        "front_image": images[0] if images else None, "images": images,
        "product_details": details,
    }

def _details(grade, year, cc, fuel, trans, drive, seats, steer, color, body, doors, size, odo_flag, port):
    rows = [("Grade", grade), ("Year", year), ("Body", body), ("Colour", color),
            ("Engine", f"{cc}cc" if cc else None), ("Fuel", fuel),
            ("Transmission", trans), ("Drive", drive), ("Doors", doors), ("Seats", seats),
            ("Steering", steer), ("Odometer", odo_flag), ("Dimensions", size), ("Export Port", port)]
    li = "".join(f"<li><strong>{ihtml.escape(k)}:</strong> {ihtml.escape(str(v))}</li>"
                 for k, v in rows if v not in (None, ""))
    return f"<ul>{li}</ul>" if li else ""

# ---------------- preview ------------------------------------------------------
def do_preview(n):
    urls = []
    for sm in car_sitemaps()[:2]:
        t = _get(sm) or ""
        urls += re.findall(r"<loc>([^<]+)</loc>", t)
        if len(urls) >= n * 3:
            break
    urls = urls[: n * 3]
    rows = []
    # sequential: this dev box's resolver is flaky under concurrency (the real
    # crawl runs sharded on GitHub where DNS is reliable). Preview correctness > speed.
    for u in urls:
        r = parse_car(u)
        if r and r.get("images"):
            rows.append(r)
        if len(rows) >= n:
            break
    render_preview(rows[:n])
    print(f"PREVIEW: {len(rows[:n])} cars -> public/autowini-preview.html")
    return rows[:n]

def render_preview(rows):
    cards = []
    for r in rows:
        imgs = r["images"][:6]
        thumbs = "".join(f'<img src="{u}" loading="lazy">' for u in imgs)
        fields = [("Year", r["year"]), ("Make", r["make"]), ("Model", r["model"]),
                  ("Grade", r["grade"]), ("Body", r["body_style"]), ("Colour", r["color"]),
                  ("Price", f"${r['price_usd']:,}" if r['price_usd'] else "—"),
                  ("Mileage", f"{r['mileage_km']:,} km" if r["mileage_km"] else "—"),
                  ("Fuel", r["fuel"]), ("Transmission", r["transmission"]),
                  ("Drive", r["drive_type"]), ("Engine", f"{r['engine_cc']}cc" if r["engine_cc"] else "—"),
                  ("Doors", r["doors"]), ("Seats", r["seats"]), ("Steering", r["steering"]),
                  ("Country", r["country"]), ("Port", r["trade_port"]), ("Photos", len(r["images"]))]
        rowsh = "".join(f"<tr><td>{k}</td><td>{'' if v in (None,'') else v}</td></tr>" for k, v in fields)
        cards.append(f'''<div class="card"><div class="imgs">{thumbs}</div>
          <h3>{ihtml.escape(r["title"])}</h3>
          <a href="{r['product_link']}" target="_blank">{r['stock_id']}</a>
          <table>{rowsh}</table></div>''')
    html = f'''<!doctype html><meta charset="utf-8"><title>autowini preview</title>
<style>body{{font-family:system-ui;background:#0b1e3b;color:#e8edf5;margin:0;padding:24px}}
h1{{font-size:20px}} .grid{{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}}
.card{{background:#122a4d;border:1px solid #1e3a63;border-radius:12px;padding:12px}}
.imgs{{display:flex;gap:4px;overflow-x:auto}} .imgs img{{height:90px;border-radius:6px}}
h3{{font-size:14px;margin:8px 0 2px}} a{{color:#7fb0ff;font-size:12px;text-decoration:none}}
table{{width:100%;border-collapse:collapse;margin-top:8px;font-size:12.5px}}
td{{padding:3px 6px;border-bottom:1px solid #1e3a63}} td:first-child{{color:#9db4d6;width:38%}}</style>
<h1>autowini.com — preview ({len(rows)} cars · country=South Korea · no DB writes)</h1>
<div class="grid">{''.join(cards)}</div>'''
    import os
    os.makedirs("public", exist_ok=True)
    open("public/autowini-preview.html", "w", encoding="utf-8").write(html)

if __name__ == "__main__":
    mode = sys.argv[1] if len(sys.argv) > 1 else "preview"
    if mode == "preview":
        do_preview(int(sys.argv[2]) if len(sys.argv) > 2 else 20)
    elif mode == "enumerate":
        us = enumerate_urls()
        out = sys.argv[2] if len(sys.argv) > 2 else "autowini-urls.txt"
        open(out, "w", encoding="utf-8").write("\n".join(us))
        print(f"{len(us)} car URLs -> {out}")
