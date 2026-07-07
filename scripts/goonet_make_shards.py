import json, sys, os, gzip
W=sys.argv[1]; NSHARD=20; CAP=10
sized=set()
for l in open(W+"/clean.jsonl.sizes", encoding="utf-8"):
    u=l.split("\t",1)[0]
    if u: sized.add(u)
print(f"checkpoint sized: {len(sized)}")
rem=[]
seen=set()
for l in open(W+"/all.jsonl", encoding="utf-8"):
    if not l.strip(): continue
    r=json.loads(l)
    for u in r.get("images",[])[1:CAP+1]:
        if u not in sized and u not in seen:
            seen.add(u); rem.append(u)
print(f"remaining to size: {len(rem)}")
outdir="scripts/size-shards"; os.makedirs(outdir, exist_ok=True)
for i in range(NSHARD):
    part=rem[i::NSHARD]
    with gzip.open(f"{outdir}/shard-{i+1:02d}.txt.gz","wt",encoding="utf-8") as f:
        f.write("\n".join(part))
    if i<2 or i==NSHARD-1: print(f"  shard {i+1:02d}: {len(part)} urls")
print("done")
