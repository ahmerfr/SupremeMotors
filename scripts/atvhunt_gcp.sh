#!/bin/bash
# atvhunt.com fan-out crawler — RUN IN GCP CLOUD SHELL.
# Google US IPs pass atvhunt's Cloud Armor; N micro-VMs (each a distinct external IP)
# crawl an id-range shard with an adaptive per-IP throttle and upload JSONL to a bucket.
#
# One-time: upload these 3 files to Cloud Shell (drag-drop): atvhunt_gcp.sh,
# atvhunt_crawl.py, atvhunt_parse.py.  Then:
#   bash atvhunt_gcp.sh bounds                     # 1) find live /l/{id} range
#   MIN=.. MAX=.. bash atvhunt_gcp.sh up           # 2) bucket + code + SA perms
#   MIN=.. MAX=.. bash atvhunt_gcp.sh smoke        # 3) 1 VM sample -> verify a shard lands
#   MIN=.. MAX=.. N=20 bash atvhunt_gcp.sh fanout  # 4) N VMs over the range
#   bash atvhunt_gcp.sh status                     #    watch progress
#   bash atvhunt_gcp.sh collect                    # 5) merge -> atvhunt.jsonl
#   bash atvhunt_gcp.sh clean                      #    delete VMs
set -uo pipefail
PROJECT=$(gcloud config get-value project 2>/dev/null)
PNUM=$(gcloud projects describe "$PROJECT" --format='value(projectNumber)' 2>/dev/null)
SA="${PNUM}-compute@developer.gserviceaccount.com"
ZONE=${ZONE:-us-central1-a}
BUCKET="gs://${PROJECT}-atvhunt"
N=${N:-20}
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'
CMD=${1:-help}

live() { local i=$1 b c
  b=$(curl -s -m 20 -A "$UA" -w '\n%{http_code}' "https://atvhunt.com/l/${i}/x")
  c=$(printf '%s' "$b" | tail -1)
  { [ "$c" = 200 ] && printf '%s' "$b" | grep -q 'lp-specs-row'; } && echo V || echo "$c"; }

build_startup() {  # $1=extra args to crawler (e.g. --sample)
cat > /tmp/atvhunt_startup.sh <<EOF
#!/bin/bash
apt-get update -y && apt-get install -y python3-pip >/tmp/apt.log 2>&1
pip3 install --quiet curl_cffi >/tmp/pip.log 2>&1
cd /root
gsutil cp ${BUCKET}/code/atvhunt_parse.py .
gsutil cp ${BUCKET}/code/atvhunt_crawl_range.py .
S=\$(curl -s -H "Metadata-Flavor: Google" http://metadata/computeMetadata/v1/instance/attributes/shard_start)
E=\$(curl -s -H "Metadata-Flavor: Google" http://metadata/computeMetadata/v1/instance/attributes/shard_end)
python3 atvhunt_crawl_range.py out \$S \$E --conc-max 24 $1 >/tmp/crawl.log 2>&1
gsutil cp out/shard_*.jsonl ${BUCKET}/shards/ 2>/dev/null
gsutil cp /tmp/crawl.log ${BUCKET}/logs/log_\${S}_\${E}.txt 2>/dev/null
shutdown -h now
EOF
}

mkvm() {  # $1=name $2=start $3=end
  gcloud compute instances create "$1" --zone="$ZONE" --machine-type=e2-small \
    --image-family=debian-12 --image-project=debian-cloud --scopes=storage-rw \
    --metadata=shard_start=$2,shard_end=$3 \
    --metadata-from-file=startup-script=/tmp/atvhunt_startup.sh --quiet; }

case "$CMD" in
bounds)
  echo "sweeping for live id bounds..."; anchor=13704289
  probe(){ local c=$1 v=0 k; for k in 0 3 6 9 12 15 18 21; do [ "$(live $((c+k)))" = V ] && v=$((v+1)); done; echo $v; }
  lo=$anchor; while [ $lo -gt 12500000 ]; do [ "$(probe $((lo-100000)))" -gt 0 ] && lo=$((lo-100000)) || break; done
  hi=$anchor; while [ $hi -lt 14300000 ]; do [ "$(probe $((hi+100000)))" -gt 0 ] && hi=$((hi+100000)) || break; done
  echo "----"; echo "MIN=$((lo-100000)) MAX=$((hi+100000))" ;;

up)
  gsutil ls "$BUCKET" >/dev/null 2>&1 || gsutil mb -l us-central1 "$BUCKET"
  gsutil cp atvhunt_parse.py atvhunt_crawl_range.py "${BUCKET}/code/"
  gsutil iam ch "serviceAccount:${SA}:roles/storage.objectAdmin" "$BUCKET" 2>/dev/null || true
  echo "bucket ready: $BUCKET  (SA=$SA)" ;;

smoke)
  : "${MIN:?}"; build_startup "--sample"
  mkvm atvhunt-smoke "$MIN" "$((MIN+4000))"
  echo "smoke VM up. In ~3-4 min check:  gsutil cat ${BUCKET}/shards/shard_${MIN}_*.jsonl | head" ;;

fanout)
  : "${MIN:?}"; : "${MAX:?}"; build_startup ""
  span=$(( (MAX-MIN)/N )); echo "range $MIN..$MAX N=$N span=$span"
  for i in $(seq 0 $((N-1))); do
    s=$((MIN+i*span)); e=$((MIN+(i+1)*span-1)); [ $i -eq $((N-1)) ] && e=$MAX
    mkvm "atvhunt-$i" "$s" "$e" & sleep 1
  done; wait; echo "launched $N VMs. watch: bash atvhunt_gcp.sh status" ;;

status)
  gcloud compute instances list --filter="name~atvhunt" --format="table(name,status)"
  echo "shards: $(gsutil ls ${BUCKET}/shards/ 2>/dev/null | wc -l)  rows: $(gsutil cat ${BUCKET}/shards/*.jsonl 2>/dev/null | wc -l)" ;;

collect)
  gsutil cat "${BUCKET}/shards/*.jsonl" > atvhunt.jsonl 2>/dev/null
  echo "merged $(wc -l < atvhunt.jsonl) rows -> atvhunt.jsonl" ;;

clean)
  gcloud compute instances list --filter="name~atvhunt" --format="value(name)" \
    | xargs -r -I{} gcloud compute instances delete {} --zone="$ZONE" --quiet ;;

*) sed -n '2,22p' "$0" ;;
esac
