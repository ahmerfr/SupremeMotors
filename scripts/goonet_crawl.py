#!/usr/bin/env python3
"""
Fast goo-net-exchange crawler using curl_cffi (Chrome TLS impersonation).

goo-net's CloudFront WAF 429s plain curl/PHP-libcurl by TLS fingerprint, but a
Chrome-impersonated client passes at full speed from the same IP (no proxies, no
Playwright). ~272k requests (13k listing + 259k detail) across a thread pool
finishes the full 259k-car crawl in ~3-4h, under 6h.

Modes:
  preview <N> <brand_cd>        -> fetch N cars, apply promo blocklist, write
                                   public/goonet-preview.html (NO DB, clean galleries)
  run <out.jsonl> [shards shard]-> crawl all brands (or shard) -> JSONL rows
                                   (raw galleries; promo-strip is a separate pass)
JSONL rows feed:  php artisan scrape:goonet --import-jsonl=<dir>
"""
import sys, re, json, os, hashlib, time, html as ihtml
from concurrent.futures import ThreadPoolExecutor, as_completed
from curl_cffi import requests

SITE = "https://www.goo-net-exchange.com"
SUMMARY = SITE + "/php/search/summary.php"
IMG_CDN = "https://picture1.goo-net.com"
JPY_USD = 0.00622
REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

SEED_PROMOS = {
    "46dab262477cf5e0645ef25f82bcb3bc", "a50345434af25ae6cb9a980c199e53ad",
    "a86940df53ddab3406398c45e88024d3", "995cd288ad5ba5fa187e4cb65de99504",
    "9fcfdc18c89084ac7dbe8359e7cccb01", "4043f09c01b146906c41f0167f0f9197",
    "2aa832e9865e175e621b595a1deb3208", "231936efd0da793c72bf3e832ff22fe6",
    "fefb358f1ff2f81c942dd0d478687cf5", "9969e06218d109322a1e40142aa458fb",
    "4a38cc3359f5a7d95b0cace071ae6c28", "2ba1f6d2655849e01eafea44ce7f1bb0",
    "ebfc6f5fcda67f9a05b309b1be73c877",
}


def load_blocklist():
    s = set(SEED_PROMOS)
    f = os.path.join(REPO, "storage/app/cdn/goonet-image-blocklist.json")
    if os.path.isfile(f):
        try:
            d = json.load(open(f, encoding="utf-8"))
            s.update((d.get("blocked") or {}).keys())
        except Exception:
            pass
    return s


def new_session():
    s = requests.Session(impersonate="chrome")
    s.headers.update({"Accept-Language": "en", "Referer": SITE + "/"})
    return s


def fetch(sess, url, tries=4):
    for i in range(tries):
        try:
            r = sess.get(url, timeout=40)
            if r.status_code == 200 and r.text:
                return r.text
        except Exception:
            pass
        time.sleep(1.2 * (i + 1))
    return ""


def listing_urls(html):
    seen, out = set(), []
    for h in re.findall(r'href="(/usedcars/[A-Z0-9_]+/[A-Za-z0-9_%-]+/\d{15,25}/)"', html):
        if h not in seen:
            seen.add(h)
            out.append(SITE + h)
    return out


def parse_total(html):
    m = re.search(r'name="total"\s+value="(\d+)"', html)
    return int(m.group(1)) if m else None


def parse_brands(home):
    out = {}
    for cd, name, cnt in re.findall(r'<option[^>]*value="(\d{3,6})"[^>]*>\s*([A-Za-z0-9 ._/&\'.-]+?)\s*\(([\d,]+)\)\s*</option>', home):
        n = int(cnt.replace(",", ""))
        if cd not in out or n > out[cd][1]:
            out[cd] = (name.strip(), n)
    return out


def _dd(html, label):
    m = re.search(r"<dt>\s*" + re.escape(label) + r"\s*</dt>\s*<dd>(.*?)</dd>", html, re.S | re.I)
    return _clean(m.group(1)) if m else None


def _clean(s):
    if s is None:
        return None
    s = re.sub(r"<[^>]+>", "", s)
    s = ihtml.unescape(s)
    s = re.sub(r"\s+", " ", s).strip()
    return s or None


def _int(s):
    return int(re.sub(r"[^0-9]", "", s or "") or 0)


def _title_case(s):
    return re.sub(r"[A-Za-z0-9]+", lambda m: m.group(0).upper() if len(m.group(0)) <= 3 else m.group(0).capitalize(), (s or "").strip())


def _norm_fuel(f):
    u = (f or "").upper()
    if not u: return None
    if "HYBRID" in u: return "Hybrid"
    if "GASOLINE" in u or "PETROL" in u: return "Petrol"
    if "DIESEL" in u: return "Diesel"
    if "ELECTRIC" in u or u == "EV": return "Electric"
    return _title_case(u)


def _norm_trans(t):
    u = (t or "").upper()
    if not u: return None
    if u.startswith("AT") or "AUTO" in u or u == "CVT": return "Automatic"
    if u.startswith("MT") or "MANUAL" in u: return "Manual"
    return _title_case(u)


def _norm_drive(d):
    u = (d or "").upper()
    if u in ("4WD", "AWD"): return "Four Wheel Drive"
    if u in ("2WD", "FF", "FR"): return "2WD"
    return None


def _norm_steer(s):
    u = (s or "").lower()
    if "right" in u: return "Right"
    if "left" in u: return "Left"
    return None


def _body(model):
    m = (model or "").lower()
    if "truck" in m or "dump" in m: return "Pickup"
    if "van" in m or "hiace" in m or "caravan" in m: return "Van"
    if "bus" in m or "coaster" in m: return "Bus"
    return None


def _category(model, fuel):
    m = (model or "").lower()
    if fuel and "electric" in fuel.lower(): return 63
    if "truck" in m or "dump" in m: return 4
    if "bus" in m or "coaster" in m: return 13
    return 20


def gallery_images(html):
    seen, out = set(), []
    for u in re.findall(r"https?://picture\d?\.goo-net\.com/[a-z0-9]+/[a-z0-9]+/J/\d+A\d+[a-z]\d+\.jpg", html, re.I):
        if u not in seen:
            seen.add(u)
            out.append(u)
    out.sort()
    return out


def cover(stock):
    return f"{IMG_CDN}/{stock[0:10]}/{stock[10:18]}/J/{stock}00.jpg"


def parse_detail(html, url):
    m = re.search(r"/usedcars/([A-Z0-9_]+)/([A-Za-z0-9_%-]+)/(\d{15,25})/", url)
    if not m:
        return None
    make = _title_case(m.group(1).replace("_", " "))
    model = _title_case(m.group(2).replace("_", " ").replace("%20", " "))
    stock = m.group(3)
    pm = re.search(r'"price"\s*:\s*"?(\d+)"?', html)
    price_jpy = int(pm.group(1)) if pm else None
    reg = _dd(html, "Month/Year")
    year = int(re.search(r"(\d{4})", reg).group(1)) if reg and re.search(r"\d{4}", reg) else None
    nm = re.search(r'"name"\s*:\s*"([^"]+)"', html)
    grade = None
    if nm:
        g = re.sub(r"^" + re.escape(make) + r"\s+" + re.escape(m.group(2).replace("_", " ")) + r"\s*", "", nm.group(1), flags=re.I).strip()
        grade = g or None
    gal = gallery_images(html)
    cov = cover(stock)
    images = [cov] + [g for g in gal if g != cov]
    fuel = _norm_fuel(_dd(html, "Fuel"))
    title = " ".join(str(x) for x in [make, model, year] if x) or ("Goo-net " + stock)
    return {
        "stock_id": stock, "title": title[:255], "make": make, "model": model,
        "grade": grade, "year": year, "mileage_km": _int(_dd(html, "Mileage")) or None,
        "fuel": fuel, "transmission": _norm_trans(_dd(html, "Transmission")),
        "condition": "Used", "color": _clean(_dd(html, "Color")),
        "body_style": _body(model),
        "engine_cc": (_int(_dd(html, "Displacement")) or None),
        "drive_type": _norm_drive(_dd(html, "Drive System")),
        "doors": (_int(_dd(html, "Doors")) or None),
        "steering": _norm_steer(_dd(html, "Steering")),
        "price_jpy": price_jpy, "price_usd": int(round(price_jpy * JPY_USD)) if price_jpy else 0,
        "category_id": _category(model, fuel), "country": "Japan",
        "product_link": url, "front_image": images[0] if images else None,
        "images": images,
        "product_details": _details(grade, reg, _dd(html, "Displacement"), fuel,
                                    _norm_trans(_dd(html, "Transmission")),
                                    _norm_drive(_dd(html, "Drive System")),
                                    _dd(html, "Doors"), _norm_steer(_dd(html, "Steering")),
                                    _clean(_dd(html, "Color")), year),
    }


def _details(grade, reg, cc, fuel, trans, drive, doors, steer, color, year):
    rows = [("Grade", grade), ("Reg. (M/Y)", reg), ("Year", year), ("Engine", cc),
            ("Fuel", fuel), ("Transmission", trans), ("Drive", drive),
            ("Doors", doors), ("Steering", steer), ("Colour", color)]
    li = ""
    for k, v in rows:
        v = _clean(str(v)) if v not in (None, "") else None
        if v:
            li += f"<li><strong>{ihtml.escape(k)}:</strong> {ihtml.escape(v)}</li>"
    return "<ul>" + li + "</ul>" if li else ""


def strip_promos(sess, images, blockset):
    """Keep cover always; drop gallery images whose md5 is blocklisted."""
    if len(images) < 2:
        return images, 0
    cov, gallery = images[0], images[1:]
    keep, stripped = [], 0
    def h(u):
        try:
            r = sess.get(u, timeout=25)
            return (u, hashlib.md5(r.content).hexdigest()) if r.status_code == 200 and len(r.content) > 1024 else (u, None)
        except Exception:
            return (u, None)
    with ThreadPoolExecutor(max_workers=8) as ex:
        for u, md5 in ex.map(h, gallery):
            if md5 and md5 in blockset:
                stripped += 1
            else:
                keep.append(u)
    return [cov] + keep, stripped


def render_preview(rows):
    cards = ""
    for r in rows:
        specs = " · ".join(str(x) for x in [r.get("year"),
                 (f"{r['mileage_km']:,} km" if r.get("mileage_km") else None),
                 r.get("fuel"), r.get("transmission"), r.get("drive_type"),
                 (f"{r['engine_cc']}cc" if r.get("engine_cc") else None), r.get("color"),
                 (f"{r['steering']}-hand" if r.get("steering") else None)] if x)
        cards += (f'<div class="c"><img loading="lazy" src="{ihtml.escape(r.get("front_image") or "")}">'
                  f'<div class="b"><h3>{ihtml.escape(r["title"])}</h3>'
                  f'<p class="pr">${r["price_usd"]:,} <span>(¥{(r.get("price_jpy") or 0):,})</span></p>'
                  f'<p class="sp">{ihtml.escape(specs)}</p>'
                  f'<p class="im">{len(r["images"])} photos · stripped {r.get("stripped",0)} promos · '
                  f'<a href="{ihtml.escape(r["product_link"])}" target="_blank">source</a></p></div></div>')
    doc = ('<!doctype html><meta charset="utf-8"><title>goo-net preview</title><style>'
           'body{font:14px system-ui;margin:0;background:#0d0d0f;color:#eee;padding:24px}h1{font-weight:800}'
           '.g{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}'
           '.c{background:#17171b;border:1px solid #262630;border-radius:14px;overflow:hidden}'
           '.c img{width:100%;height:180px;object-fit:cover;background:#222}.b{padding:12px}'
           'h3{margin:0 0 6px;font-size:15px}.pr{color:#8e2527;font-weight:700;margin:0 0 6px}.pr span{color:#888;font-weight:400;font-size:12px}'
           '.sp{color:#bbb;margin:0 0 6px;font-size:12.5px}.im{color:#777;margin:0;font-size:12px}.im a{color:#6b9}</style>'
           f'<h1>goo-net-exchange — {len(rows)} sample cars (curl_cffi, blocklist applied)</h1><div class="g">' + cards + '</div>')
    open(os.path.join(REPO, "public/goonet-preview.html"), "w", encoding="utf-8").write(doc)


def do_preview(n, brand):
    sess = new_session()
    blockset = load_blocklist()
    urls = []
    for page in range(6):
        if len(urls) >= n:
            break
        u = listing_urls(fetch(sess, f"{SUMMARY}?brand_cd={brand}&offset={page*20}"))
        if not u:
            break
        urls += u
        time.sleep(0.6)
    urls = list(dict.fromkeys(urls))[:n]
    print(f"{len(urls)} detail URLs; fetching + stripping promos...")
    rows = []
    with ThreadPoolExecutor(max_workers=6) as ex:
        futs = {ex.submit(fetch, new_session(), u): u for u in urls}
        for f in as_completed(futs):
            row = parse_detail(f.result(), futs[f])
            if row:
                rows.append(row)
    for r in rows:
        r["images"], r["stripped"] = strip_promos(sess, r["images"], blockset)
        r["front_image"] = r["images"][0] if r["images"] else r["front_image"]
    render_preview(rows)
    print(f"{len(rows)} cars -> public/goonet-preview.html")
    for r in rows:
        print(f"  {r['make']} {r['model']} {r.get('year')}  ${r['price_usd']:,}  "
              f"{r.get('fuel')}/{r.get('transmission')}/{r.get('drive_type')}  "
              f"{len(r['images'])} imgs (stripped {r['stripped']})")


def do_run(out, shards, shard):
    sess = new_session()
    brands = list(parse_brands(fetch(sess, SITE + "/")).keys())
    if shards > 1:
        brands = [b for i, b in enumerate(brands) if i % shards == shard - 1]
    print(f"shard {shard}/{shards}: {len(brands)} brands -> {out}")
    n = 0
    with open(out, "w", encoding="utf-8") as fh, ThreadPoolExecutor(max_workers=16) as ex:
        for cd in brands:
            empty = 0
            for page in range(3000):
                urls = listing_urls(fetch(sess, f"{SUMMARY}?brand_cd={cd}&offset={page*20}"))
                if not urls:
                    empty += 1
                    if empty >= 2:
                        break
                    continue
                empty = 0
                futs = {ex.submit(fetch, new_session(), u): u for u in urls}
                for f in as_completed(futs):
                    row = parse_detail(f.result(), futs[f])
                    if row:
                        fh.write(json.dumps(row, ensure_ascii=False) + "\n")
                        n += 1
                if page % 20 == 0:
                    print(f"  brand {cd} page {page}: total {n}")
    print(f"RUN DONE: {n} rows -> {out}")


if __name__ == "__main__":
    mode = sys.argv[1] if len(sys.argv) > 1 else "preview"
    if mode == "preview":
        do_preview(int(sys.argv[2]) if len(sys.argv) > 2 else 20, sys.argv[3] if len(sys.argv) > 3 else "1010")
    elif mode == "run":
        do_run(sys.argv[2], int(sys.argv[3]) if len(sys.argv) > 3 else 1, int(sys.argv[4]) if len(sys.argv) > 4 else 1)
