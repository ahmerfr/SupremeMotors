#!/usr/bin/env python3
"""Fetch a handful of KNOWN listing ids and report how many survive the ATV/UTV gate.

The gate drops any row without a body_style. A header-only subtitle anchor made that
None for every real listing, so a 750-id shard reported list=0 while 53 of its ids were
listings already in the dataset. This is the cheapest possible check that the gate lets
real listings through -- run it before any fleet dispatch.
"""
import sys, json
from curl_cffi import requests as rq
import atvhunt_parse as P

ids = [int(x) for x in sys.argv[1].split(",")]
S = rq.Session(impersonate="chrome")
ok = gated = listing = 0
rows = []
for i in ids:
    try:
        r = S.get(f"https://atvhunt.com/l/{i}/x", timeout=(8, 20),
                  allow_redirects=False, impersonate="chrome")
    except Exception as e:
        rows.append({"id": i, "err": str(e)[:60]}); continue
    if r.status_code != 200:
        rows.append({"id": i, "code": r.status_code}); continue
    ok += 1
    if not P.is_listing(r.text):
        rows.append({"id": i, "code": 200, "is_listing": False}); continue
    listing += 1
    d = P.parse(r.text, listing_id=str(i))
    if d.get("body_style"):
        gated += 1
    rows.append({"id": i, "is_listing": True, "body_style": d.get("body_style"),
                 "state": d.get("state"), "title": (d.get("title") or "")[:40]})
out = {"fetched": len(ids), "http200": ok, "is_listing": listing,
       "passed_atv_gate": gated, "rows": rows[:12]}
print(json.dumps(out, indent=1))
json.dump(out, open("verify.json", "w"))
sys.exit(0 if gated >= len(ids) * 0.6 else 1)
