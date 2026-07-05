# AutoTrader scrape coordinator/keepalive — run by the SupremeMotors-Scrape
# task every 5 minutes AND at logon, so the crawl survives net drops, crashes,
# and full reboots: on the next login Windows fires this and it resumes from
# the page cursor. Nothing banked is ever re-fetched.
#   1. autotrader-scrape.done -> remove the scheduled task and exit
#   2. ensure mysqld up + 512M buffer pool
#   3. refresh the free-proxy pool when it is stale/thin (background)
#   4. relaunch the scraper if its process died (resumes from cursor)
#   5. render a human status page from the progress JSON

$project = 'C:\xampp\htdocs\SupremeMotors'
$php     = 'C:\xampp\php\php.exe'
$state   = Join-Path $project 'storage\app\cdn'
$proxies = Join-Path $state 'proxies.txt'
$done    = Join-Path $state 'autotrader-scrape.done'
$logDir  = Join-Path $project 'storage\logs'
if (-not (Test-Path $state))  { New-Item -ItemType Directory -Force $state  | Out-Null }
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force $logDir | Out-Null }

# tuning — safe to edit; the running scraper picks up a new proxy file live
$scrapeArgs = @('artisan','scrape:autotrader','--deep','--pool=30',
                "--proxy-file=$proxies",'--usd-rate=0.055','--delay-ms=400')
$proxyMaxAgeMin = 15   # refresh the pool when older than this
$proxyMinLive   = 8    # or when fewer than this many remain

function Log($msg) {
    $line = (Get-Date -Format o) + "  $msg"
    Add-Content -Path (Join-Path $logDir 'scrape-keepalive.log') -Value $line
}

# 1. done -> self-destruct
if (Test-Path $done) {
    schtasks /delete /tn 'SupremeMotors-Scrape' /f 2>$null
    Log 'scrape complete — task removed'
    exit 0
}

# 2. MySQL up + big buffer pool (scraper writes products continuously)
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

# 3. refresh the free-proxy pool when stale or thin (runs detached; the scraper
#    hot-reloads proxies.txt between pages so it never has to stop)
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
        -ArgumentList (@('artisan','scrape:refresh-proxies','--validate','--pool=60','--limit=600') -join ' ') `
        -WorkingDirectory $project -WindowStyle Hidden
    Log 'refreshing proxy pool'
}

# 4. relaunch the scraper if it died (resumes from the page cursor)
$running = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
    Where-Object { $_.CommandLine -like '*scrape:autotrader*' }
if (-not $running) {
    Start-Process -FilePath $php -ArgumentList ($scrapeArgs -join ' ') `
        -WorkingDirectory $project -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDir 'scrape-run.log') `
        -RedirectStandardError  (Join-Path $logDir 'scrape-run.err.log')
    Log 'relaunched scraper (resume from cursor)'
}

# 5. render a status page from the progress JSON
$progressFile = Join-Path $state 'autotrader-progress.json'
if (Test-Path $progressFile) {
    try {
        $p = Get-Content $progressFile -Raw | ConvertFrom-Json
        $pct = if ($p.percent) { $p.percent } else { 0 }
        $eta = if ($p.eta_minutes) { [math]::Round($p.eta_minutes / 60, 1).ToString() + ' h' } else { '—' }
        $html = @"
<!doctype html><meta charset="utf-8"><title>AutoTrader scrape — live status</title>
<meta http-equiv="refresh" content="30">
<style>body{font-family:Segoe UI,system-ui,sans-serif;background:#0b1e3b;color:#fff;margin:0;display:grid;place-items:center;min-height:100vh}
.card{background:#122c53;border-radius:18px;padding:34px 40px;box-shadow:0 10px 40px rgba(0,0,0,.4);width:min(560px,92vw)}
h1{font-size:20px;margin:0 0 18px;color:#9db2d4;font-weight:700;letter-spacing:.04em}
.bar{height:14px;background:#0b1e3b;border-radius:10px;overflow:hidden;margin:10px 0 22px}
.fill{height:100%;background:linear-gradient(90deg,#e01f26,#ff5a60);width:$pct%}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.k{font-size:28px;font-weight:800}.l{font-size:12px;color:#9db2d4}.big{font-size:40px;color:#fff}
.upd{margin-top:20px;font-size:11px;color:#6f86a8}</style>
<div class=card><h1>AUTOTRADER SCRAPE — LIVE</h1>
<div class=big>$pct%</div><div class=bar><div class=fill></div></div>
<div class=grid>
<div><div class=k>$($p.products_scraped)</div><div class=l>products scraped / ~$($p.products_total_estimate)</div></div>
<div><div class=k>$($p.images_scraped)</div><div class=l>images captured</div></div>
<div><div class=k>page $($p.page)/$($p.last_page)</div><div class=l>$($p.mode) mode</div></div>
<div><div class=k>$eta</div><div class=l>est. remaining · $($p.rate_per_min)/min</div></div>
<div><div class=k>$($p.proxies_live)</div><div class=l>live proxies</div></div>
<div><div class=k>$($p.failures)</div><div class=l>failures logged</div></div>
</div><div class=upd>updated $($p.updated_at) · auto-refreshes every 30s</div></div>
"@
        Set-Content -Path (Join-Path $state 'autotrader-status.html') -Value $html -Encoding utf8
    } catch { Log "status render failed: $_" }
}

Log "tick — running=$([bool]$running) needProxies=$needProxies"
