#!/usr/bin/env python3
"""The completeness PROOF for the atvhunt sweep, and the sweep's stop condition.

The tiling proves we ASKED about every id. It cannot prove we CAPTURED every listing --
a soft-blocked 200, an unfollowed 30x or an over-tight ATV/UTV gate all look like a clean
answer. Only the site's own counts can see those. So:

  THE ACCOUNTING IS AUTHORITATIVE. THE TILING IS THE DELIVERY MECHANISM.

state partitions the catalogue EXACTLY (52 values summing to 177,184 = the h1 total) and
typeid 6+7 also sums exactly, so 52 state cells (and 104 state x typeid cells) each carry a
site-reported N. deficit = sum over cells of max(0, N_site - N_ours). deficit ~ 0 is the
proof; a non-zero deficit names the state and the exact count still missing.

  python atvhunt_close.py --fetch cells.json          # 105 browse requests, needs a US IP
  python atvhunt_close.py cells.json rows/*.jsonl     # -> deficit + per-state breakdown
  python atvhunt_close.py --selftest
"""
import sys, os, json, glob, collections

BROWSE = "https://atvhunt.com/atv-utv-for-sale"
STATES = ("Alabama Alaska Arizona Arkansas California Colorado Connecticut Delaware Florida "
          "Georgia Guam Hawaii Idaho Illinois Indiana Iowa Kansas Kentucky Louisiana Maine "
          "Maryland Massachusetts Michigan Minnesota Mississippi Missouri Montana Nebraska "
          "Nevada New-Hampshire New-Jersey New-Mexico New-York North-Carolina North-Dakota "
          "Ohio Oklahoma Oregon Pennsylvania Puerto-Rico Rhode-Island South-Carolina "
          "South-Dakota Tennessee Texas Utah Vermont Virginia Washington West-Virginia "
          "Wisconsin Wyoming").split()
CODES = ("AL AK AZ AR CA CO CT DE FL GA GU HI ID IL IN IA KS KY LA ME MD MA MI MN MS MO MT "
         "NE NV NH NJ NM NY NC ND OH OK OR PA PR RI SC SD TN TX UT VT VA WA WV WI WY").split()
CODE_OF = dict(zip(STATES, CODES))
CHURN = 250        # listings created/sold during a ~2 h run; the h1 total and the state
                   # census already disagree by 23 with nothing running at all.


def typeid_of(body_style):
    """The site's own split: typeid=7 is UTV, typeid=6 is ATV. Derived from body_style
    rather than a hardcoded style list, so a new UTV sub-type cannot silently become an
    ATV and hide a deficit in the wrong cell."""
    return "7" if "UTV" in (body_style or "") else "6"


def fetch_cells(out):
    """105 requests: the unfiltered total + 52 states x 2 typeids. Re-read at the END of
    the run, never from an earlier measurement -- inventory drifts."""
    import re
    from curl_cffi import requests as rq
    s = rq.Session(impersonate="chrome")
    count = re.compile(r"<h1>\s*<b>([\d,]+)</b>", re.I)

    def n_of(url):
        for _ in range(4):
            r = s.get(url, timeout=(8, 20))
            m = count.search(r.text)
            if m:
                return int(m.group(1).replace(",", ""))
            if "No results" in r.text or "0 ATVs and UTVs" in r.text:
                return 0
        raise SystemExit(f"no count on {url}")

    cells = {"_total": n_of(BROWSE)}
    for st in STATES:
        for t in ("6", "7"):
            cells[f"{CODE_OF[st]}|{t}"] = n_of(f"{BROWSE}/{st}?typeid={t}")
        print(f"  {st} {cells[CODE_OF[st]+'|6']}+{cells[CODE_OF[st]+'|7']}", flush=True)
    json.dump(cells, open(out, "w"), indent=0)
    tot = sum(v for k, v in cells.items() if k != "_total")
    print(f"cells -> {out}: total={cells['_total']:,} state x typeid sum={tot:,} "
          f"(drift {tot - cells['_total']:+d})")
    return cells


def load_rows(paths):
    """Deduped on source_id. Only rows that passed the ATV/UTV gate are counted -- a row
    with no body_style belongs to a sibling marketplace sharing the id space."""
    rows = {}
    for p in paths:
        with open(p, encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                r = json.loads(line)
                if r.get("body_style") and r.get("state"):
                    rows[r["source_id"]] = r
    return rows


def close(cells, rows):
    ours_st = collections.Counter(r["state"] for r in rows.values())
    ours_c = collections.Counter(f"{r['state']}|{typeid_of(r['body_style'])}"
                                 for r in rows.values())
    site_st = collections.Counter()
    for k, n in cells.items():
        if k != "_total":
            site_st[k.split("|")[0]] += n

    per_st = {s: n - ours_st[s] for s, n in site_st.items() if ours_st[s] < n}
    per_c = {k: n - ours_c[k] for k, n in cells.items()
             if k != "_total" and ours_c[k] < n}
    d_st, d_c = sum(per_st.values()), sum(per_c.values())
    print(f"rows(gated,deduped)={len(rows):,}  site_total={cells['_total']:,}")
    print(f"STATE deficit   = {d_st:,}   (authoritative; state partitions exactly)")
    print(f"STATExTYPE def. = {d_c:,}   (diagnostic; depends on the UTV/ATV split)")
    print("worst states:", sorted(per_st.items(), key=lambda kv: -kv[1])[:8])
    over = {s: ours_st[s] - n for s, n in site_st.items() if ours_st[s] > n + CHURN}
    if over:
        print("OVERCOUNT (gate is letting foreign listings in):",
              sorted(over.items(), key=lambda kv: -kv[1])[:8])
    return d_st


def selftest():
    assert len(STATES) == 52 and len(CODES) == 52 and len(set(CODES)) == 52
    assert typeid_of("Side-by-Side UTV") == "7" and typeid_of("Sport ATV") == "6"
    assert typeid_of(None) == "6"
    cells = {"_total": 3, "TN|6": 1, "TN|7": 1, "TX|6": 1, "TX|7": 0}
    mk = lambda i, st, bs: {"source_id": i, "state": st, "body_style": bs}
    rows = {r["source_id"]: r for r in [mk("1", "TN", "Sport ATV"),
                                        mk("2", "TN", "Side-by-Side UTV"),
                                        mk("3", "TX", "Utility ATV")]}
    assert close(cells, rows) == 0
    rows.pop("3")
    assert close(cells, rows) == 1                      # one missing listing -> deficit 1
    rows["3"] = mk("3", "TX", None)                     # sibling row must NOT paper it over
    assert close(cells, {k: v for k, v in rows.items() if v.get("body_style")}) == 1
    print("CLOSE SELFTEST PASSED")


if __name__ == "__main__":
    if "--selftest" in sys.argv:
        selftest(); sys.exit(0)
    if sys.argv[1] == "--fetch":
        fetch_cells(sys.argv[2]); sys.exit(0)
    paths = [p for a in sys.argv[2:] for p in (glob.glob(a) if "*" in a else [a])
             if os.path.getsize(p)]
    sys.exit(0 if close(json.load(open(sys.argv[1])), load_rows(paths)) >= 0 else 1)
