import json, sys, collections
sys.path.insert(0, "scripts")
from goonet_crawl import sess_local
import hashlib
from concurrent.futures import ThreadPoolExecutor

W = sys.argv[1]; CAP=10; MINC=6; FREQMIN=30
sizes={}
for l in open(W+"/clean.jsonl.sizes", encoding="utf-8"):
    try: u,s=l.rstrip("\n").split("\t"); sizes[u]=int(s)
    except: pass
rows=[json.loads(l) for l in open(W+"/all.jsonl", encoding="utf-8") if l.strip()]
freq=collections.Counter(); samples=collections.defaultdict(list)
for r in rows:
    seen=set()
    for u in r.get("images",[])[1:CAP+1]:
        s=sizes.get(u,0)
        if s and s not in seen: seen.add(s); freq[s]+=1
for r in rows:
    for u in r.get("images",[])[1:CAP+1]:
        s=sizes.get(u,0)
        if freq[s]>=FREQMIN and len(samples[s])<2 and u not in samples[s]:
            samples[s].append(u)
cand=[s for s in freq if freq[s]>=FREQMIN]
print(f"confirming {len(cand)} high-freq candidates (freq>={FREQMIN})...", flush=True)
def confirm(s):
    hs=set()
    for u in samples[s][:2]:
        try:
            rr=sess_local().get(u, timeout=25)
            if rr.status_code==200 and len(rr.content)>500: hs.add(hashlib.md5(rr.content).hexdigest())
        except: pass
    return (s, len(samples[s])>=2 and len(hs)==1)
block=set()
with ThreadPoolExecutor(max_workers=16) as ex:
    for s,ok in ex.map(confirm, cand):
        if ok: block.add(s)
print(f"confirmed promo sizes: {len(block)} of {len(cand)}", flush=True)
print("  block sizes+carfreq:", sorted([(s,freq[s]) for s in block], key=lambda x:-x[1])[:20])
# yield on fully-sized cars
sc=simg=cars_hit=0
for r in rows:
    g=r.get("images",[])[1:CAP+1]
    if not g or not all(u in sizes for u in g): continue
    sc+=1
    hit=[u for u in g if sizes.get(u,0) in block]
    if hit: cars_hit+=1; simg+=len(hit)
print(f"sized cars: {sc}  cars with >=1 embedded promo in first{CAP}: {cars_hit} ({100*cars_hit/max(sc,1):.1f}%)  promo imgs removed: {simg}")
