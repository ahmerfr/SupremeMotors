#!/usr/bin/env python3
"""Parse one atvhunt.com /l/{id}/ detail page -> a clean product dict matching the
Supreme Motors `products` schema (website='atvhunt', category_id=1302 ATVs & UTVs).

Detail markup is stable:
  - price:  <span class="h1">$14,999</span>
  - facts:  <div class="row lp-specs-row"><div..><b>Label:</b></div><div..>VALUE</div></div>
  - subtitle: "New Utility UTV in Crossville, TN"  (condition, body_style, city, state)
  - images: storage.googleapis.com/mhimg/p/{last4}/{id}/{hash}_l.jpg (single public origin)

Run standalone to self-test against a saved sample:  python atvhunt_parse.py <sample.html>
"""
import re, html as ihtml, json

# make name -> live make_id (from db-export/atvhunt-category-makes.sql readback). Longest
# names first so "Arctic Cat" wins over a bare "Arctic", "Can-Am" over "Can", etc.
MAKE_ID = {
    "Can-Am": 462, "CFMOTO": 268, "Honda": 22, "Kawasaki": 752, "Kayo": 1303,
    "Kymco": 773, "Polaris": 954, "SSR": 1304, "Suzuki": 24, "Yamaha": 1279,
    "Arctic Cat": 1305, "Argo": 1306, "Apollo": 1307, "Bennche": 1308, "DRR": 1309,
    "Hisun Motors": 1310, "Odes": 1311, "Segway": 1075, "Tao Motor": 1312,
    "Tracker Off Road": 1313, "Trailmaster": 1314, "Vitacci": 1315,
}
MAKES_BY_LEN = sorted(MAKE_ID, key=len, reverse=True)
CATEGORY_ID = 1302
IMG_ZONE = "https://sm-atvhunt.b-cdn.net"          # pull zone -> storage.googleapis.com
IMG_ORIGIN_PATH = re.compile(r'/mhimg/p/\d+/\d+/[0-9a-f]+_[a-z]\.jpg')


def _txt(s):
    return re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', ihtml.unescape(s or ''))).strip()


def _specs(htmltext):
    """label -> value from every lp-specs-row. Linear/bounded regex (no re.S, no
    unbounded .*?) so a malformed page can't trigger O(n^2) backtracking (ReDoS)."""
    out = {}
    for m in re.finditer(r'<b>\s*([^<:]{1,40})\s*:?\s*</b>\s*</div>\s*<div[^>]*>([^<]{0,150})', htmltext):
        k = _txt(m.group(1)); v = _txt(m.group(2))
        if k and v:
            out[k] = v
    return out


def parse(htmltext, listing_id=None):
    # Hard-cap parse input: listing data is all near the top; a giant page (huge dealer
    # blurb / embedded blob) must never let any regex run long and freeze the GIL.
    htmltext = htmltext[:120000]
    # title / year / make / model. Bounded lazy {0,200} (no re.S) -> title stays on one
    # line, `.` absorbs the ® and any nested tag chars, and the bound blocks backtracking.
    h1 = re.search(r'<h1[^>]*>(.{0,200}?)</h1>', htmltext)
    title = _txt(h1.group(1)) if h1 else ''
    title = title.replace('®', '').replace('™', '').strip()   # drop (R)/(TM)
    ym = re.match(r'(19|20)\d\d', title)
    year = int(title[:4]) if ym else None
    rest = title[4:].strip() if ym else title
    make = next((m for m in MAKES_BY_LEN if rest.lower().startswith(m.lower())), None)
    if not make:                                   # unknown make -> first token, no id
        make = rest.split(' ')[0] if rest else None
    model = rest[len(make):].strip() if make and rest.lower().startswith(make.lower()) else rest

    # subtitle: "New Utility UTV in City, ST" — search only the top (few New/Used tokens)
    sub = re.search(r'\b(New|Used)\s+([A-Za-z][A-Za-z /]{1,22}?(?:ATV|UTV))\s+in\s+([^,<]{1,40}),\s*([A-Z]{2})\b', htmltext)
    condition = sub.group(1) if sub else (S.get('Condition') if (S := _specs(htmltext)) else None)
    body_style = _txt(sub.group(2)) if sub else None
    city = _txt(sub.group(3)) if sub else None
    state = sub.group(4) if sub else None

    S = _specs(htmltext)
    price_m = re.search(r'<span class="h1">\s*\$?([\d,]+)', htmltext)
    price = int(price_m.group(1).replace(',', '')) if price_m else 0

    def num(key, pat=r'([\d,]+)'):
        v = S.get(key, ''); m = re.search(pat, v); return int(m.group(1).replace(',', '')) if m else None

    engine_cc = num('Engine')                       # "999 cc, 2-cylinder, 4-stroke"
    cyl = re.search(r'(\d+)-cylinder', S.get('Engine', ''))
    power_hp = num('Power', r'(\d+)\s*hp')
    miles = num('Mileage')
    mileage_km = round(miles * 1.60934) if miles else None
    transmission = S.get('Transmission')
    color = S.get('Color')
    vin = S.get('VIN'); stock = S.get('Stock #') or S.get('Stock')
    dealer = S.get('Dealer')

    # images -> pull zone, large variant, deduped in order
    seen, imgs = set(), []
    for p in IMG_ORIGIN_PATH.findall(htmltext):
        if p not in seen:
            seen.add(p); imgs.append(IMG_ZONE + p)

    canon = re.search(r'<link[^>]+rel="canonical"[^>]+href="([^"]+)"', htmltext)
    if not listing_id:
        lid = re.search(r'/l/(\d+)/', canon.group(1) if canon else '')
        listing_id = lid.group(1) if lid else None
    product_link = canon.group(1) if canon else (f"https://atvhunt.com/l/{listing_id}/x" if listing_id else None)

    specifications = {k: v for k, v in {
        **S, 'MSRP': S.get('Base MSRP'), 'ALP': S.get('ALP'),
        'cylinders': cyl.group(1) if cyl else None, 'dealer': dealer,
        'city': city, 'state': state, 'source_id': listing_id,
    }.items() if v}

    details = ['<ul>'] + [f'<li><b>{ihtml.escape(k)}:</b> {ihtml.escape(v)}</li>'
                          for k, v in S.items()] + ['</ul>']
    product_details = '\n'.join(details)

    return {
        'source_id': listing_id, 'title': title, 'year': year,
        'make': make, 'make_id': MAKE_ID.get(make), 'model': model or None,
        'category_id': CATEGORY_ID, 'condition': condition or 'Used',
        'body_style': body_style, 'color': color, 'mileage_km': mileage_km,
        'engine_cc': engine_cc, 'power_hp': power_hp, 'transmission': transmission,
        'price_usd': price, 'country': 'USA', 'product_link': product_link,
        'front_image': imgs[0] if imgs else None, 'images': imgs,
        'vin': vin, 'stock': stock, 'city': city, 'state': state,
        'product_details': product_details, 'specifications': specifications,
    }


def is_listing(htmltext):
    """True only for a real listing page (not a 429/soft-404/redirect landing)."""
    return '<h1' in htmltext and 'lp-specs-row' in htmltext and 'VIN' in htmltext


if __name__ == '__main__':
    import sys
    html_in = open(sys.argv[1], encoding='utf-8', errors='replace').read()
    r = parse(html_in, listing_id='13704289')
    print(json.dumps(r, indent=1, ensure_ascii=False)[:1600])
    # self-test against known values in the sample page
    assert r['year'] == 2026, r['year']
    assert r['make'] == 'Polaris' and r['make_id'] == 954, (r['make'], r['make_id'])
    assert 'Ranger' in r['model'], r['model']
    assert r['engine_cc'] == 999, r['engine_cc']
    assert r['power_hp'] == 61, r['power_hp']
    assert r['color'] == 'Granite Gray', r['color']
    assert r['vin'] == '3NSTAE999TH258969', r['vin']
    assert r['price_usd'] == 14999, r['price_usd']
    assert r['condition'] == 'New', r['condition']
    assert r['body_style'] == 'Utility UTV', r['body_style']
    assert r['state'] == 'TN' and r['city'] == 'Crossville', (r['city'], r['state'])
    assert len(r['images']) >= 4 and r['images'][0].startswith(IMG_ZONE), r['images'][:1]
    assert r['category_id'] == 1302
    print('\nSELF-TEST PASSED')
