# AutoTrader scrape coordinator/keepalive — run by the SupremeMotors-Scrape
# task every 5 minutes AND at logon, so the crawl survives net drops, crashes,
# and full reboots: on the next login Windows fires this and it resumes each
# shard from its own page cursor. Nothing banked is ever re-fetched.
#
# SPEED MODEL:
#   * ~3,700 search pages split into 4 parallel scrape shards, each with a big
#     concurrent proxy pool -> N-fold throughput.
#   * The image warm runs IN PARALLEL with scraping (not after it): it caches
#     each car's images into Bunny as soon as the car is scraped. Scrape hits
#     AutoTrader via proxies; warm hits the Bunny CDN — different networks, so
#     they barely compete and the two phases overlap instead of stacking.
# Each tick:
#   1. ensure mysqld up + 512M buffer pool
#   2. refresh the proxy pool when stale/thin (background)
#   3. keep the image warm chewing the scraped backlog (parallel)
#   4. relaunch any dead scrape shard (resumes from its cursor)
#   5. finish: all shards done AND warm caught up -> done + self-delete
#   6. aggregate progress into one live status page

$project = 'C:\xampp\htdocs\SupremeMotors'
$php     = 'C:\xampp\php\php.exe'
$state   = Join-Path $project 'storage\app\cdn'
$proxies = Join-Path $state 'proxies.txt'
$done    = Join-Path $state 'autotrader-scrape.done'
$warmMk  = Join-Path $state 'warm-atwarm.done'   # WarmCdn writes this when it catches up
$logDir  = Join-Path $project 'storage\logs'
if (-not (Test-Path $state))  { New-Item -ItemType Directory -Force $state  | Out-Null }
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force $logDir | Out-Null }

$shards = @(
    @{ name = 's1'; min = 1;    max = 928 },
    @{ name = 's2'; min = 929;  max = 1856 },
    @{ name = 's3'; min = 1857; max = 2784 },
    @{ name = 's4'; min = 2785; max = 0 }
)
$pool = 40
$proxyMaxAgeMin = 10
$proxyMinLive   = 25

function Log($msg) {
    Add-Content -Path (Join-Path $logDir 'scrape-keepalive.log') -Value ((Get-Date -Format o) + "  $msg")
}

$allShardsDone = $true
foreach ($s in $shards) {
    if (-not (Test-Path (Join-Path $state ("autotrader-scrape-" + $s.name + ".done")))) { $allShardsDone = $false }
}

# 1. MySQL up + big buffer pool (shards write products; warm reads them)
$mysqld = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysqld) {
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' `
        -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' -WindowStyle Hidden
    foreach ($i in 1..45) {
        Start-Sleep -Seconds 2
        try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1',3307); $c.Close(); break } catch {}
    }
    Log 'started mysqld'
}
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -B -e 'SET GLOBAL innodb_buffer_pool_size=536870912' 2>$null

# 2. refresh the free-proxy pool when stale or thin (detached; shards hot-reload it)
$needProxies = $true
if (Test-Path $proxies) {
    $ageMin = ((Get-Date) - (Get-Item $proxies).LastWriteTime).TotalMinutes
    $live   = (Get-Content $proxies | Where-Object { $_ -and ($_ -notmatch '^#') }).Count
    if ($ageMin -lt $proxyMaxAgeMin -and $live -ge $proxyMinLive) { $needProxies = $false }
}
$refreshing = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
    Where-Object { $_.CommandLine -like '*scrape:refresh-proxies*' }
if ($needProxies -and -not $refreshing) {
    Start-Process -FilePath $php `
        -ArgumentList (@('artisan','scrape:refresh-proxies','--validate','--limit=3200','--pool=250','--timeout=6') -join ' ') `
        -WorkingDirectory $project -WindowStyle Hidden
    Log 'refreshing proxy pool'
}

# 3. keep the image warm running IN PARALLEL with scraping. It resumes from its
#    cursor and warms newly-scraped cars. While scraping is still going, a
#    caught-up marker is stale (more cars coming) so we clear it and relaunch;
#    the warm only truly finishes once all shards are done.
$phps = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'"
$warming = $phps | Where-Object { $_.CommandLine -like '*products:warm-cdn*--website=autotraderza*' }
if (-not $allShardsDone -and (Test-Path $warmMk)) {
    Remove-Item $warmMk -Force -ErrorAction SilentlyContinue   # caught up, but more cars are coming
}
if (-not $warming -and -not (Test-Path $warmMk)) {
    Start-Process -FilePath $php `
        -ArgumentList (@('artisan','products:warm-cdn','--website=autotraderza','--shard=atwarm','--pool=60','--timeout=30') -join ' ') `
        -WorkingDirectory $project -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDir 'scrape-imagewarm.log') `
        -RedirectStandardError  (Join-Path $logDir 'scrape-imagewarm.err.log')
    Log 'image warm running (parallel Perma-Cache into Bunny)'
}

# 4. relaunch any dead scrape shard (resumes from its own cursor)
if (-not $allShardsDone) {
    foreach ($s in $shards) {
        if (Test-Path (Join-Path $state ("autotrader-scrape-" + $s.name + ".done"))) { continue }
        $running = $phps | Where-Object { $_.CommandLine -like ('*--shard=' + $s.name + ' *') }
        if (-not $running) {
            $args = @('artisan','scrape:autotrader','--deep',
                      ('--shard=' + $s.name), ('--min-page=' + $s.min), ('--max-page=' + $s.max),
                      ('--pool=' + $pool), ("--proxy-file=$proxies"), '--usd-rate=0.055', '--delay-ms=0')
            Start-Process -FilePath $php -ArgumentList ($args -join ' ') `
                -WorkingDirectory $project -WindowStyle Hidden `
                -RedirectStandardOutput (Join-Path $logDir ('scrape-' + $s.name + '.log')) `
                -RedirectStandardError  (Join-Path $logDir ('scrape-' + $s.name + '.err.log'))
            Log ('relaunched shard ' + $s.name)
        }
    }
}

# 5. finish: scraping done AND the warm has caught all of it up
if ($allShardsDone -and (Test-Path $warmMk)) {
    Set-Content -Path $done -Value (Get-Date -Format o)
    schtasks /delete /tn 'SupremeMotors-Scrape' /f 2>$null
    Log 'scrape + parallel image warm complete — done, task removed'
    exit 0
}

# 6. aggregate per-shard progress into one live status page
$tot = 0; $img = 0; $fail = 0; $pagesDone = 0; $pagesTotal = 0; $rate = 0; $liveProxies = 0; $doneCount = 0
foreach ($s in $shards) {
    $pf = Join-Path $state ("autotrader-progress-" + $s.name + ".json")
    if (Test-Path $pf) {
        try {
            $p = Get-Content $pf -Raw | ConvertFrom-Json
            $tot += [int]$p.products_scraped; $img += [int]$p.images_scraped; $fail += [int]$p.failures
            $rate += [double]$p.rate_per_min; $liveProxies = [int]$p.proxies_live
            if ($p.last_page) { $pagesTotal += [int]$p.last_page - [int]$s.min + 1; $pagesDone += [int]$p.page - [int]$s.min + 1 }
            if ($p.done) { $doneCount++ }
        } catch {}
    }
}
$pct = if ($pagesTotal -gt 0) { [math]::Round($pagesDone / $pagesTotal * 100, 1) } else { 0 }
$eta = if ($rate -gt 0) { [math]::Round((92807 - $tot) / $rate / 60, 1).ToString() + ' h' } else { '—' }
$warmState = if ($allShardsDone) { if (Test-Path $warmMk) { 'complete' } else { 'finishing' } } else { 'running in parallel' }
$html = @"
<!doctype html><meta charset="utf-8"><title>AutoTrader scrape — live</title>
<meta http-equiv="refresh" content="30">
<style>body{font-family:Segoe UI,system-ui,sans-serif;background:#0b1e3b;color:#fff;margin:0;display:grid;place-items:center;min-height:100vh}
.card{background:#122c53;border-radius:18px;padding:34px 40px;box-shadow:0 10px 40px rgba(0,0,0,.4);width:min(600px,92vw)}
h1{font-size:20px;margin:0 0 6px;color:#9db2d4;letter-spacing:.04em}.big{font-size:44px;font-weight:800}
.bar{height:14px;background:#0b1e3b;border-radius:10px;overflow:hidden;margin:10px 0 22px}
.fill{height:100%;background:linear-gradient(90deg,#e01f26,#ff5a60);width:$pct%}
.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.k{font-size:26px;font-weight:800}.l{font-size:12px;color:#9db2d4}.upd{margin-top:20px;font-size:11px;color:#6f86a8}</style>
<div class=card><h1>AUTOTRADER — $doneCount/$($shards.Count) SHARDS DONE · IMAGE WARM $($warmState.ToUpper())</h1>
<div class=big>$pct%</div><div class=bar><div class=fill></div></div>
<div class=grid>
<div><div class=k>$tot</div><div class=l>products / ~92,807</div></div>
<div><div class=k>$img</div><div class=l>images captured</div></div>
<div><div class=k>$eta</div><div class=l>est. remaining</div></div>
<div><div class=k>$([math]::Round($rate,0))</div><div class=l>products/min</div></div>
<div><div class=k>$liveProxies</div><div class=l>live proxies</div></div>
<div><div class=k>$fail</div><div class=l>failures</div></div>
</div><div class=upd>updated $(Get-Date -Format o) · auto-refreshes every 30s</div></div>
"@
Set-Content -Path (Join-Path $state 'autotrader-status.html') -Value $html -Encoding utf8

Log ("tick — shards done=$doneCount warm=$warmState products=$tot images=$img proxies=$liveProxies")
