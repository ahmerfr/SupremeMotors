import json, os, sys, collections
sys.path.insert(0, "scripts")
from goonet_crawl import sess_local, IMG_CDN
import hashlib
from concurrent.futures import ThreadPoolExecutor

W = sys.argv[1]
CAP = 10; MINC = 6
sizes = {}
with open(W+"/clean.jsonl.sizes", encoding="utf-8") as f:
    for l in f:
        try:
            u, s = l.rstrip("\n").split("\t"); sizes[u] = int(s)
        except: pass
rows = [json.loads(l) for l in open(W+"/all.jsonl", encoding="utf-8") if l.strip()]
print(f"sizes checkpoint: {len(sizes)}  cars: {len(rows)}")

# coverage: how many cars have ALL first-CAP gallery imgs sized?
full=part=none=0
for r in rows:
    g = r.get("images", [])[1:CAP+1]
    if not g: full+=1; continue
    sz = sum(1 for u in g if u in sizes)
    if sz==len(g): full+=1
    elif sz==0: none+=1
    else: part+=1
print(f"coverage -> fully sized cars: {full}  partial: {part}  none: {none}")

# freq of sizes across distinct cars (among sized imgs)
freq = collections.Counter()
for r in rows:
    seen=set()
    for u in r.get("images", [])[1:CAP+1]:
        s=sizes.get(u,0)
        if s and s not in seen: seen.add(s); freq[s]+=1
cand = [s for s,c in freq.items() if c>=MINC]
print(f"candidate promo sizes (>= {MINC} cars): {len(cand)}")
print("top 15 by car-count:", [(s,freq[s]) for s in sorted(cand,key=lambda s:-freq[s])[:15]])
