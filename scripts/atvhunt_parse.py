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

# body style needs digits and hyphens: "Side-by-Side UTV" (the industry-standard term for
# a UTV), "4x4 Utility ATV", "2-Up ATV" were all silently rejected by [A-Za-z /] and thrown
# away as sibling-marketplace listings by every gate downstream.
SUB_RE = r'\b(New|Used)\s+([A-Za-z0-9][A-Za-z0-9 /-]{1,22}?(?:ATV|UTV))\s+in\s+([^,<]{1,40}),\s*([A-Z]{2})\b'
# same shape, any category - used to log WHY a live page failed the ATV/UTV gate
CAT_RE = re.compile(r'\b(?:New|Used)\s+([A-Za-z0-9][A-Za-z0-9 /-]{1,30}?)\s+in\s+[^,<]{1,40},\s*[A-Z]{2}\b')


def _head(htmltext):
    """The listing header region: everything before the first spec row.

    MEASURED THE HARD WAY: the subtitle does NOT always render above the specs. Anchoring
    to this region alone returned body_style=None for every real listing, and because the
    crawler's ATV/UTV gate drops any row without a body_style, an entire 750-id shard
    reported list=0 while 53 of its ids were listings we already hold. Callers must use
    _sub() below, which prefers the header and falls back to the whole page, rather than
    trusting this region to contain the subtitle."""
    i = htmltext.find('lp-specs-row')
    return htmltext[:i] if i > 0 else htmltext


# A cross-sell block is the thing that must not donate body_style/state -- NOT the spec
# rows. Cutting at the first lp-specs-row (the previous anchor) also cut off the subtitle
# on every real listing, so the gate rejected all of them.
XSELL_RE = re.compile(r'similar\s+listings|you\s+may\s+also\s+like|related\s+listings|'
                      r'recommended\s+for\s+you|similar-listings|lp-similar', re.I)


def _own(htmltext):
    """The part of the page that describes THIS listing: everything before the first
    cross-sell block, or the whole page when there is none."""
    m = XSELL_RE.search(htmltext)
    return htmltext[:m.start()] if m else htmltext


def _sub(htmltext, pattern):
    """Match within this listing's own region. Keeps the cross-sell protection that the
    header anchor was reaching for, without assuming the subtitle sits above the specs."""
    return re.search(pattern, _own(htmltext))


def category_token(htmltext):
    """The category word from a live page's header, whatever it is ('Sedan', 'Cruiser').
    Written to other_*.txt so a too-tight ATV/UTV gate is fixable without re-fetching."""
    m = CAT_RE.search(_own(htmltext))
    return m.group(1).strip() if m else None


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


def _make_of(rest):
    """(make, rest_without_trailing_make) for the title minus its leading year.

    Most titles lead with the make ("Polaris Sportsman 570"), but a real slice of the
    catalogue puts it LAST after a dash - "Brute Force 750 EPS LE Camo - Kawasaki",
    "Outlander MAX Pro XU HD7 - Can-Am". Taking the first token there yields make="Brute"
    / "Outlander", which is not in MAKE_ID, so those rows silently lose make_id."""
    m = next((k for k in MAKES_BY_LEN if rest.lower().startswith(k.lower())), None)
    if m:
        return m, rest
    tail = re.search(r'[-–]\s*([A-Za-z][A-Za-z .\-]{1,24})\s*$', rest)
    if tail:
        t = tail.group(1).strip().lower()
        m = next((k for k in MAKES_BY_LEN if t == k.lower()), None)
        if m:                                      # strip the trailing "- Make" from model
            return m, rest[:tail.start()].strip()
    return (rest.split(' ')[0] if rest else None), rest   # unknown make -> first token, no id


def parse(htmltext, listing_id=None):
    # Hard-cap parse input: listing data is all near the top; a giant page (huge dealer
    # blurb / embedded blob) must never let any regex run long and freeze the GIL.
    htmltext = htmltext[:120000]
    # title / year / make / model. Bounded lazy {0,200} (no re.S) -> title stays on one
    # line, `.` absorbs the (R) and any nested tag chars, and the bound blocks backtracking.
    h1 = re.search(r'<h1[^>]*>(.{0,200}?)</h1>', htmltext)
    title = _txt(h1.group(1)) if h1 else ''
    title = title.replace('®', '').replace('™', '').strip()   # drop (R)/(TM)
    ym = re.match(r'(19|20)\d\d', title)
    year = int(title[:4]) if ym else None
    rest = title[4:].strip() if ym else title
    make, rest = _make_of(rest)
    model = rest[len(make):].strip() if make and rest.lower().startswith(make.lower()) else rest

    # subtitle: "New Utility UTV in City, ST" - the LISTING HEADER only, never the whole
    # page: a "similar listings near you" cross-sell block on a sibling-marketplace page
    # (a car on motohunt) donates an ATV body_style AND a state, which both passes the
    # ATV/UTV gate and corrupts the per-state count that audits the gate.
    sub = _sub(htmltext, SUB_RE)
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
    """True for a real listing page (not a 429/soft-404/redirect landing).

    'VIN' is NOT required. It used to be, and that made every VIN-less listing invisible:
    fetch() classified it as "definitively not a listing", wrote the id to cov_ and the
    coverage proof closed over it. A soft-404 has no spec rows at all, so <h1> + one
    lp-specs-row is the real discriminator; the ATV/UTV gate in the crawler decides what
    is ours, and atvhunt_close.py's per-state count is what proves nothing was lost."""
    return '<h1' in htmltext and 'lp-specs-row' in htmltext


def _selftest_makes():
    """Runs without a sample page, so CI can catch a make-parsing regression on every push."""
    for t, want in (('Polaris Sportsman 570 Trail', 'Polaris'),
                    ('Brute Force 750 EPS LE Camo - Kawasaki', 'Kawasaki'),
                    ('Outlander MAX Pro XU HD7 - Can-Am', 'Can-Am'),
                    ('Arctic Cat Alterra 600', 'Arctic Cat'),
                    ('KingQuad 400FSi - Suzuki', 'Suzuki'),
                    ('Weirdbrand 900 XT', 'Weirdbrand')):
        got, _ = _make_of(t)
        assert got == want, f'{t!r} -> {got!r}, want {want!r}'
    assert _make_of('Brute Force 750 - Kawasaki')[1] == 'Brute Force 750'   # suffix stripped

    SPEC = '<div class="row lp-specs-row"><div><b>VIN:</b></div><div>3NSTAE9</div></div>'
    # 1. hyphenated / digit-bearing body styles must survive the gate
    for bs in ('Side-by-Side UTV', '4x4 Utility ATV', '2-Up ATV', 'Utility UTV', 'Sport ATV'):
        r = parse(f'<h1>2026 Polaris Ranger</h1><p>New {bs} in Crossville, TN</p>{SPEC}')
        assert r['body_style'] == bs, (bs, r['body_style'])
        assert r['state'] == 'TN' and r['city'] == 'Crossville', r['city']
    # 2. a cross-sell block BELOW the specs must not donate body_style or state. Without
    #    the header anchor this Toyota passes the ATV gate and inflates TN's audited count.
    car = (f'<h1>2021 Toyota Camry SE</h1><p>Used Sedan in Nashville, TN</p>{SPEC}'
           '<div>Similar listings near you</div><p>New Utility UTV in Crossville, TN</p>')
    r = parse(car)
    assert r['body_style'] is None, r['body_style']
    assert category_token(car) == 'Sedan', category_token(car)
    # 3. a VIN-less listing is still a listing (it used to be classified as "not a listing"
    #    and silently closed inside cov_)
    novin = ('<h1>2019 Honda Rancher</h1><p>Used Utility ATV in Ada, OK</p>'
             '<div class="row lp-specs-row"><div><b>Color:</b></div><div>Red</div></div>')
    assert is_listing(novin) and parse(novin)['body_style'] == 'Utility ATV'
    # 3. THE REGRESSION THAT COST A FLEET RUN: subtitle rendered BELOW the first spec row.
    #    Header-only anchoring returned None here, the ATV gate dropped the row, and a
    #    750-id shard reported list=0 with 53 known listings inside it.
    below = ('<h1>2026 Polaris Ranger</h1>'
             '<div class="row lp-specs-row"><div><b>VIN:</b></div><div>ABC</div></div>'
             '<p>New Utility UTV in Crossville, TN</p>')
    rb = parse(below)
    assert is_listing(below), 'must still be a listing'
    assert rb['body_style'] == 'Utility UTV', ('subtitle below specs', rb['body_style'])
    assert rb['state'] == 'TN' and rb['city'] == 'Crossville', (rb['city'], rb['state'])
    assert not is_listing('<h1>Page not found</h1>')
    print('PARSE SELFTEST PASSED (make, body-style class, header anchor, VIN-less listing)')


if __name__ == '__main__':
    import sys
    if '--selftest' in sys.argv:
        _selftest_makes(); sys.exit(0)
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
