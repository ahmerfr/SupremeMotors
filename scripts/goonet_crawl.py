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
import sys, re, json, os, hashlib, time, html as ihtml, threading, math, glob as _glob
from concurrent.futures import ThreadPoolExecutor, as_completed
from curl_cffi import requests

_tls = threading.local()


def sess_local():
    """One reused Chrome-impersonated session per worker thread (avoids a TLS
    handshake per request — critical for throughput)."""
    if not hasattr(_tls, "s"):
        _tls.s = new_session()
    return _tls.s

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
    u = (t or "").strip().upper()
    if not u or u in ("-", "--", "?"): return None
    if u.startswith("AT") or "AUTO" in u or u in ("CVT", "DCT", "AMT", "DSG", "EAT"): return "Automatic"
    if u.startswith("MT") or "MANUAL" in u: return "Manual"
    return _title_case(u)


def _norm_drive(d):
    u = (d or "").strip().upper()
    if not u or u in ("-", "--", "?"): return None
    if u == "4WD": return "Four Wheel Drive"
    if u == "AWD": return "AWD"
    if u in ("FF", "FWD"): return "FWD"
    if u in ("FR", "RWD", "MR", "RR"): return "RWD"
    if u == "2WD": return "2WD"
    return _title_case(u)


# goo-net puts a body descriptor in the "Doors" field for some cars (OPEN, COUPE,
# 4DHT, etc). Split it into a numeric door count (when present) and a body hint.
_DOORS_BODY = {"OPEN": ("Convertible", 2), "COUPE": ("Coupe", 2), "CONVERTIBLE": ("Convertible", 2),
               "CABRIOLET": ("Convertible", 2), "HARDTOP": ("Hardtop", None), "WAGON": ("Wagon", None),
               "HATCHBACK": ("Hatchback", None), "SEDAN": ("Sedan", None), "SUV": ("SUV", None),
               "MINIVAN": ("Minivan", None), "TRUCK": ("Pickup", None)}

def _doors_body(raw):
    """Return (doors:int|None, body_hint:str|None) from the raw Doors field."""
    if not raw:
        return None, None
    u = raw.strip().upper()
    # e.g. "4DHT" = 4-door hardtop, "2D" = 2-door
    dm = re.match(r"(\d)\s*D", u)
    doors = int(dm.group(1)) if dm else None
    if "HT" in u and "D" in u:
        return doors, "Hardtop"
    for k, (b, d) in _DOORS_BODY.items():
        if k in u:
            return (doors if doors else d), b
    return doors, None


def _norm_repaired(r):
    """goo-net repair/accident history. -> 'No' | 'Yes' | 'Repaired' | None."""
    u = (r or "").strip().upper()
    if not u or u in ("-", "?"): return None
    if "NO REPAIR" in u or u == "NO": return "No"
    if "MAJOR" in u: return "Repaired (Major)"
    if "YES" in u or "REPAIR" in u: return "Repaired"
    return None


# region prefixes that leak into the maker token on some goo-net URLs
# (e.g. AMERICA_MITSUBISHI, CANADA_HONDA, THAILAND_OTHER). Strip when a real
# make follows; keep the token intact if stripping would leave nothing.
_REGION_PREFIX = {"America", "Canada", "Thailand", "Europe", "Australia", "England",
                  "Germany", "France", "Italy", "China", "Korea", "India"}

def _clean_make(make):
    parts = (make or "").split()
    if len(parts) >= 2 and parts[0] in _REGION_PREFIX:
        return " ".join(parts[1:])
    return make


def _make_title(make, model, year):
    """goo-net's model token often repeats the make (make='Mazda',
    model='Mazda Speed Axela'; or make='Chrysler Jeep', model='Jeep Patriot').
    Collapse adjacent duplicate words so the title reads clean and the stored
    model drops the redundant make prefix. Returns (title, clean_model)."""
    mw = (make or "").split()
    ded = []
    for w in (mw + (model or "").split()):
        if not ded or ded[-1].lower() != w.lower():
            ded.append(w)
    clean_model = " ".join(ded[len(mw):]) or (model or None)
    title = " ".join(ded + ([str(year)] if year else []))
    return title, clean_model


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
    make = _clean_make(_title_case(m.group(1).replace("_", " ")))
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
    doors, body_hint = _doors_body(_dd(html, "Doors"))
    body_style = _body(model) or body_hint
    repaired = _norm_repaired(_dd(html, "Repaired"))
    trans = _norm_trans(_dd(html, "Transmission"))
    drive = _norm_drive(_dd(html, "Drive System"))
    steer = _norm_steer(_dd(html, "Steering"))
    color = _clean(_dd(html, "Color"))
    title, model = _make_title(make, model, year)
    if not title:
        title = "Goo-net " + stock
    return {
        "stock_id": stock, "title": title[:255], "make": make, "model": model,
        "grade": grade, "year": year, "mileage_km": _int(_dd(html, "Mileage")) or None,
        "fuel": fuel, "transmission": trans,
        "condition": "Used", "color": color,
        "body_style": body_style,
        "engine_cc": (_int(_dd(html, "Displacement")) or None),
        "drive_type": drive,
        "doors": doors,
        "steering": steer,
        "repaired": repaired,
        "price_jpy": price_jpy, "price_usd": int(round(price_jpy * JPY_USD)) if price_jpy else 0,
        "category_id": _category(model, fuel), "country": "Japan",
        "product_link": url, "front_image": images[0] if images else None,
        "images": images,
        "product_details": _details(grade, reg, _dd(html, "Displacement"), fuel,
                                    trans, drive, doors, steer, color, year, repaired, body_style),
    }


def _details(grade, reg, cc, fuel, trans, drive, doors, steer, color, year, repaired=None, body=None):
    rows = [("Grade", grade), ("Reg. (M/Y)", reg), ("Year", year), ("Engine", cc),
            ("Fuel", fuel), ("Transmission", trans), ("Drive", drive), ("Body", body),
            ("Doors", doors), ("Steering", steer), ("Colour", color), ("Repair History", repaired)]
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


def do_run(out, shards, shard, pool=12):
    sess = new_session()
    brands = list(parse_brands(fetch(sess, SITE + "/")).keys())
    if shards > 1:
        brands = [b for i, b in enumerate(brands) if i % shards == shard - 1]
    print(f"shard {shard}/{shards}: {len(brands)} brands, pool {pool}", flush=True)

    # Phase 1 — enumerate every listing page (concurrent), collect detail URLs.
    pages = []
    for cd in brands:
        total = parse_total(fetch(sess, f"{SUMMARY}?brand_cd={cd}&offset=0")) or 0
        pages += [(cd, p * 20) for p in range(math.ceil(total / 20) + 1)]
    print(f"  {len(pages)} listing pages to enumerate...", flush=True)

    def enum_page(bo):
        return listing_urls(fetch(sess_local(), f"{SUMMARY}?brand_cd={bo[0]}&offset={bo[1]}"))

    all_urls = []
    with ThreadPoolExecutor(max_workers=pool) as ex:
        for i, urls in enumerate(ex.map(enum_page, pages)):
            all_urls += urls
            if i % 300 == 0:
                print(f"  enum {i}/{len(pages)} pages, {len(all_urls)} urls", flush=True)
    all_urls = list(dict.fromkeys(all_urls))
    print(f"  {len(all_urls)} unique detail URLs; fetching details...", flush=True)

    # Phase 2 — fetch + parse detail pages (concurrent), stream to JSONL.
    def fetch_parse(u):
        return parse_detail(fetch(sess_local(), u), u)

    n = 0
    with open(out, "w", encoding="utf-8") as fh, ThreadPoolExecutor(max_workers=pool) as ex:
        for i, row in enumerate(ex.map(fetch_parse, all_urls)):
            if row:
                fh.write(json.dumps(row, ensure_ascii=False) + "\n")
                n += 1
            if i % 2000 == 0:
                print(f"  detail {i}/{len(all_urls)}, wrote {n}", flush=True)
    print(f"RUN DONE: {n} rows -> {out}", flush=True)


def do_strip(inp, out, cap=10, minc=6, pool=64):
    """Remove promo/ad images. HEAD each first-`cap` gallery image for its byte SIZE
    (promos are byte-identical -> identical size; HEAD sends no body so it dodges the
    CDN's bandwidth throttle that made GET-every-image infeasible). Sizes recurring
    across >= minc distinct cars are candidate promos; each candidate is CONFIRMED by
    GET-hashing 2 images of that size from different cars (identical md5 = genuine
    shared promo), so a coincidental size collision can never strip a real photo.
    Cover always kept; tail-promo block excluded by the cap. Resumable via checkpoint."""
    rows = [json.loads(l) for l in open(inp, encoding="utf-8") if l.strip()]
    print(f"strip(HEAD+size): {len(rows)} cars, cap {cap}, pool {pool}", flush=True)

    todo = {}
    for r in rows:
        for u in r.get("images", [])[1:cap + 1]:
            todo[u] = None
    urls = list(todo.keys())

    def hd(u):
        for _ in range(3):
            try:
                rr = sess_local().head(u, timeout=15)
                if rr.status_code == 200:
                    cl = rr.headers.get("Content-Length")
                    return (u, int(cl) if cl else 0)
                time.sleep(0.3)
            except Exception:
                time.sleep(0.3)
        return (u, 0)

    ckpt = out + ".sizes"
    usize = {}
    if os.path.isfile(ckpt):
        for l in open(ckpt, encoding="utf-8"):
            try:
                u, s = l.rstrip("\n").split("\t")
                usize[u] = int(s)
            except Exception:
                pass
    remaining = [u for u in urls if u not in usize]
    print(f"  {len(usize)} from checkpoint, HEADing {len(remaining)} of {len(urls)}...", flush=True)
    cf = open(ckpt, "a", encoding="utf-8")
    done = len(usize)
    # process in chunks so we don't pre-submit millions of futures (which stalls startup)
    with ThreadPoolExecutor(max_workers=pool) as ex:
        for i in range(0, len(remaining), 5000):
            for u, s in ex.map(hd, remaining[i:i + 5000]):
                usize[u] = s
                cf.write(f"{u}\t{s}\n")
                done += 1
            cf.flush()
            print(f"  headed {done}/{len(urls)}", flush=True)
    cf.close()

    freq = {}
    for r in rows:
        seen = set()
        for u in r.get("images", [])[1:cap + 1]:
            s = usize.get(u, 0)
            if s and s not in seen:
                seen.add(s)
                freq[s] = freq.get(s, 0) + 1
    cand = [s for s, c in freq.items() if c >= minc]
    print(f"  {len(cand)} candidate promo sizes (>= {minc} cars); confirming content...", flush=True)

    samples = {}
    for r in rows:
        for u in r.get("images", [])[1:cap + 1]:
            s = usize.get(u, 0)
            if s in freq and freq[s] >= minc:
                lst = samples.setdefault(s, [])
                if len(lst) < 2 and u not in lst:
                    lst.append(u)

    def confirm(s):
        hs = set()
        for u in samples.get(s, []):
            try:
                rr = sess_local().get(u, timeout=25)
                if rr.status_code == 200 and len(rr.content) > 500:
                    hs.add(hashlib.md5(rr.content).hexdigest())
            except Exception:
                pass
        return (s, len(samples.get(s, [])) >= 2 and len(hs) == 1)

    block_sizes = set()
    with ThreadPoolExecutor(max_workers=16) as ex:
        for s, ok in ex.map(confirm, cand):
            if ok:
                block_sizes.add(s)
    print(f"  confirmed {len(block_sizes)} promo sizes (of {len(cand)} candidates)", flush=True)

    stripped = 0
    with open(out, "w", encoding="utf-8") as fh:
        for r in rows:
            imgs = r.get("images", [])
            clean = [u for u in imgs[1:cap + 1] if usize.get(u, 0) not in block_sizes]
            stripped += (min(cap, max(0, len(imgs) - 1)) - len(clean))
            r["images"] = imgs[0:1] + clean
            r["front_image"] = r["images"][0] if r["images"] else r.get("front_image")
            fh.write(json.dumps(r, ensure_ascii=False) + "\n")
    print(f"STRIP DONE: stripped {stripped} promo images across {len(rows)} cars -> {out}", flush=True)


if __name__ == "__main__":
    mode = sys.argv[1] if len(sys.argv) > 1 else "preview"
    if mode == "preview":
        do_preview(int(sys.argv[2]) if len(sys.argv) > 2 else 20, sys.argv[3] if len(sys.argv) > 3 else "1010")
    elif mode == "run":
        do_run(sys.argv[2], int(sys.argv[3]) if len(sys.argv) > 3 else 1,
               int(sys.argv[4]) if len(sys.argv) > 4 else 1,
               int(sys.argv[5]) if len(sys.argv) > 5 else 12)
    elif mode == "strip":
        do_strip(sys.argv[2], sys.argv[3],
                 cap=int(sys.argv[5]) if len(sys.argv) > 5 else 10,
                 minc=int(sys.argv[6]) if len(sys.argv) > 6 else 6,
                 pool=int(sys.argv[4]) if len(sys.argv) > 4 else 100)
