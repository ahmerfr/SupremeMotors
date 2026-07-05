# AutoTrader UK crawl coordinator/keepalive - run by the SupremeMotors-AutotraderUK
# task every few minutes so the crawl survives net drops, crashes and reboots.
# Each tick resumes every shard from its own cursor; nothing banked is re-fetched.
#
# SPEED MODEL (all one home IP + Schannel curl.exe past Cloudflare, no proxies):
#   0. PLAN once  -> price bands under the 100-page cap (scrape:autotraderuk-plan)
#   1. Phase-1    -> one search shard per price band (small pool), banks the
#                    search-tier row (specifications NULL) for every car.
#   2. Phase-2    -> enrich worker runs IN PARALLEL with Phase-1 (pipelined),
#                    draining specifications-NULL rows to full detail+gallery.
#      Combined concurrency stays under the Cloudflare ceiling (~15). Once all
#      bands are done the enrich pool is bumped up (Phase-1 no longer competes).
#   3. Warm       -> Perma-Cache images into Bunny (a DIFFERENT origin, so free
#                    to run alongside). DECOUPLED from data-completion.
#   Data is "done" when bands done AND no specifications-NULL rows remain; the
#   task self-deletes only after the background image warm also finishes.

$project = 'C:\xampp\htdocs\SupremeMotors'
$php     = 'C:\xampp\php\php.exe'
$mysql   = 'C:\xampp\mysql\bin\mysql.exe'
$state   = Join-Path $project 'storage\app\cdn'
$logDir  = Join-Path $project 'storage\logs'
if (-not (Test-Path $state))  { New-Item -ItemType Directory -Force $state  | Out-Null }
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force $logDir | Out-Null }

$shardsFile = Join-Path $state 'autotraderuk-shards.json'
$dataDone   = Join-Path $state 'autotraderuk-data.done'
$warmDone   = Join-Path $state 'warm-ukwarm.done'
$allDone    = Join-Path $state 'autotraderuk.done'

$phase1Pool = 3     # search shard worker (Phase-1)
# Detail enrich is REQUEST-RATE limited by Cloudflare per home IP: high
# concurrency just gets throttled (most GETs fail), so NET throughput is worse.
# A modest, sustainable pool gets more through than pool 16 did.
$enrichPool = 6
$enrichPoolSolo = 6

function Log($msg) {
    Add-Content -Path (Join-Path $logDir 'autotraderuk-keepalive.log') -Value ((Get-Date -Format o) + "  " + $msg)
}
function NullCount() {
    # cars still needing Phase-2 detail (search-tier only). enriched=0 is the marker.
    try { return [int](& $mysql -h 127.0.0.1 -P 3307 -u root supreme_motors -N -e "SELECT COUNT(*) FROM products WHERE website='autotraderuk' AND enriched=0" 2>$null) } catch { return -1 }
}
function BankedCount() {
    try { return [int](& $mysql -h 127.0.0.1 -P 3307 -u root supreme_motors -N -e "SELECT COUNT(*) FROM products WHERE website='autotraderuk'" 2>$null) } catch { return 0 }
}

# --- MySQL up + big buffer pool ---
$mysqld = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysqld) {
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' -WindowStyle Hidden
    foreach ($i in 1..45) { Start-Sleep -Seconds 2; try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1',3307); $c.Close(); break } catch {} }
    Log 'started mysqld'
}
& $mysql -h 127.0.0.1 -P 3307 -u root -B -e 'SET GLOBAL innodb_buffer_pool_size=536870912' 2>$null

$phps = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'"

# --- everything done -> finish + self-destruct ---
if ((Test-Path $dataDone) -and (Test-Path $warmDone)) {
    Set-Content -Path $allDone -Value (Get-Date -Format o)
    schtasks /delete /tn 'SupremeMotors-AutotraderUK' /f 2>$null
    Log 'scrape + enrich + image warm complete - done, task removed'
    exit 0
}

# --- 0. PLAN the price-band shards once ---
if (-not (Test-Path $shardsFile)) {
    $planning = $phps | Where-Object { $_.CommandLine -like '*scrape:autotraderuk-plan*' }
    if (-not $planning) {
        Start-Process -FilePath $php -ArgumentList (@('artisan','scrape:autotraderuk-plan','--threshold=1800') -join ' ') -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'autotraderuk-plan.log') -RedirectStandardError (Join-Path $logDir 'autotraderuk-plan.err.log')
        Log 'planning price-band shards'
    }
    exit 0   # wait for the plan before scraping
}

$plan  = Get-Content $shardsFile -Raw | ConvertFrom-Json
$bands = $plan.bands

# --- 1. Phase-1: one band shard at a time (leaves IP headroom for enrich) ---
$allBandsDone = $true
$nextBand = $null
foreach ($b in $bands) {
    $doneMk = Join-Path $state ("autotraderuk-scrape-" + $b.shard + ".done")
    if (-not (Test-Path $doneMk)) { $allBandsDone = $false; if (-not $nextBand) { $nextBand = $b } }
}

# Phase-1 runs as ONE persistent loop process (bands back-to-back, no per-tick
# idle). Keep it alive until every band is done; it resumes from .done markers.
$p1loop = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" | Where-Object { $_.CommandLine -like '*autotraderuk-phase1-loop*' }
if (-not $allBandsDone -and -not $p1loop) {
    Start-Process -FilePath 'powershell.exe' -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $project 'scripts\autotraderuk-phase1-loop.ps1') -WindowStyle Hidden
    Log 'Phase-1 band loop launched (bands back-to-back)'
}

# --- 2. Phase-2 enrich: ALWAYS running, in parallel with Phase-1 (pipelined) ---
$wantPool = if ($allBandsDone) { $enrichPoolSolo } else { $enrichPool }
$enrich = $phps | Where-Object { $_.CommandLine -like '*scrape:autotraderuk*--enrich*' }
if ($enrich) {
    # bump the pool once Phase-1 frees the IP: kill the low-pool worker so the
    # next tick relaunches it at the solo pool
    if ($allBandsDone -and ($enrich.CommandLine -notlike ('*--pool=' + $enrichPoolSolo + '*'))) {
        $enrich | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
        Log 'bands done - restarting enrich at solo pool'
    }
} else {
    Start-Process -FilePath $php -ArgumentList (@('artisan','scrape:autotraderuk','--enrich',('--pool=' + $wantPool),'--delay-ms=250') -join ' ') -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'autotraderuk-enrich.log') -RedirectStandardError (Join-Path $logDir 'autotraderuk-enrich.err.log')
    Log ('Phase-2 enrich launched (pool ' + $wantPool + ')')
}

# --- data complete: bands done AND no specifications-NULL rows remain ---
$nullLeft = NullCount
if ($allBandsDone -and $nullLeft -eq 0 -and (BankedCount) -gt 0 -and -not (Test-Path $dataDone)) {
    Set-Content -Path $dataDone -Value (Get-Date -Format o)
    Log 'DATA COMPLETE - all bands scraped and every car enriched'
}

# --- 3. Warm images into Bunny (background, different origin, decoupled) ---
# run once data is flowing; front_image first so listings show a photo fast.
# a stale caught-up marker while cars still arrive -> clear + relaunch.
$warming = $phps | Where-Object { $_.CommandLine -like '*products:warm-cdn*--website=autotraderuk*' }
if (-not (Test-Path $dataDone) -and (Test-Path $warmDone)) { Remove-Item $warmDone -Force -ErrorAction SilentlyContinue }
if ((BankedCount) -gt 0 -and -not $warming -and -not (Test-Path $warmDone)) {
    Start-Process -FilePath $php -ArgumentList (@('artisan','products:warm-cdn','--website=autotraderuk','--shard=ukwarm','--pool=40','--timeout=30') -join ' ') -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'autotraderuk-warm.log') -RedirectStandardError (Join-Path $logDir 'autotraderuk-warm.err.log')
    Log 'image warm running (parallel Perma-Cache into Bunny)'
}

# --- status page ---
$banked = BankedCount
$enriched = if ($banked -gt 0) { $banked - [math]::Max(0,$nullLeft) } else { 0 }
$total = [int]$plan.total
$pct = if ($total -gt 0) { [math]::Round($banked / $total * 100, 1) } else { 0 }
$bandsDoneN = ($bands | Where-Object { Test-Path (Join-Path $state ("autotraderuk-scrape-" + $_.shard + ".done")) }).Count
$phase = if (Test-Path $dataDone) { if (Test-Path $warmDone) { 'DONE' } else { 'WARMING IMAGES' } } elseif ($allBandsDone) { 'ENRICHING' } else { 'SCRAPING + ENRICHING' }
$lines = @()
$lines += '<!doctype html><meta charset="utf-8"><title>AutoTrader UK crawl</title><meta http-equiv="refresh" content="30">'
$lines += '<style>body{font-family:Segoe UI,system-ui,sans-serif;background:#0b1e3b;color:#fff;margin:0;display:grid;place-items:center;min-height:100vh}.card{background:#122c53;border-radius:18px;padding:34px 40px;width:min(620px,92vw)}h1{font-size:17px;color:#9db2d4}.big{font-size:44px;font-weight:800}.bar{height:14px;background:#0b1e3b;border-radius:10px;overflow:hidden;margin:10px 0 22px}.fill{height:100%;background:linear-gradient(90deg,#e01f26,#ff5a60)}.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}.k{font-size:24px;font-weight:800}.l{font-size:12px;color:#9db2d4}</style>'
$lines += '<div class=card><h1>AUTOTRADER UK - ' + $phase + '</h1>'
$lines += '<div class=big>' + $pct + '%</div><div class=bar><div class=fill style="width:' + $pct + '%"></div></div><div class=grid>'
$lines += '<div><div class=k>' + $banked + '</div><div class=l>cars banked / ' + $total + '</div></div>'
$lines += '<div><div class=k>' + $enriched + '</div><div class=l>fully enriched</div></div>'
$lines += '<div><div class=k>' + $bandsDoneN + '/' + $bands.Count + '</div><div class=l>bands scraped</div></div>'
$lines += '</div><div class=l style="margin-top:16px">updated ' + (Get-Date -Format o) + '</div></div>'
Set-Content -Path (Join-Path $state 'autotraderuk-status.html') -Value ($lines -join "`n") -Encoding utf8

Log ("tick - phase=" + $phase + " banked=" + $banked + " enriched=" + $enriched + " bandsDone=" + $bandsDoneN + "/" + $bands.Count + " nullLeft=" + $nullLeft)
