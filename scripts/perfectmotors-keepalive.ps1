# Perfect Motors scrape coordinator/keepalive - run by the
# SupremeMotors-PerfectMotors task every 5 minutes, so the run survives net
# drops, crashes and reboots: each tick resumes from the id cursor. Nothing
# banked is ever re-fetched. This source has no WAF/rate-limit, so it fetches
# directly from the home IP concurrently (no proxies) - it runs happily
# alongside the AutoTrader scrape, which uses proxies + a different origin.
#
# Phases (sequential; the catalogue is small ~5,900 cars):
#   1. sweep   -> scrape:perfectmotors (id-range sweep) until perfectmotors-scrape.done
#   2. fill    -> scrape:perfectmotors --fill-incomplete until perfectmotors-fill.done
#   3. warm    -> products:warm-cdn --website=perfectmotors (Perma-Cache images) until warm-pmwarm.done
#   4. done    -> write perfectmotors.done, self-delete the task

$project = 'C:\xampp\htdocs\SupremeMotors'
$php     = 'C:\xampp\php\php.exe'
$state   = Join-Path $project 'storage\app\cdn'
$logDir  = Join-Path $project 'storage\logs'
if (-not (Test-Path $state))  { New-Item -ItemType Directory -Force $state  | Out-Null }
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force $logDir | Out-Null }

$sweepDone   = Join-Path $state 'perfectmotors-scrape.done'
$sweepStable = Join-Path $state 'perfectmotors-sweep-stable.done'
$cursorFile  = Join-Path $state 'perfectmotors.cursor'
$countFile   = Join-Path $state 'perfectmotors-lastcount.txt'
$fillDone    = Join-Path $state 'perfectmotors-fill.done'
$warmDone    = Join-Path $state 'warm-pmwarm.done'
$allDone     = Join-Path $state 'perfectmotors.done'

function Log($msg) {
    Add-Content -Path (Join-Path $logDir 'perfectmotors-keepalive.log') -Value ((Get-Date -Format o) + "  " + $msg)
}

function BankedCount() {
    try { return [int](& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root supreme_motors -N -e "SELECT COUNT(*) FROM products WHERE website='perfectmotors'" 2>$null) } catch { return 0 }
}

# MySQL up + big buffer pool (shared with the AutoTrader run)
$mysqld = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysqld) {
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' -WindowStyle Hidden
    foreach ($i in 1..45) {
        Start-Sleep -Seconds 2
        try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1',3307); $c.Close(); break } catch {}
    }
    Log 'started mysqld'
}
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -B -e 'SET GLOBAL innodb_buffer_pool_size=4294967296' 2>$null

$phps = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'"

# 4. everything done -> finish + self-destruct
if ((Test-Path $sweepStable) -and (Test-Path $fillDone) -and (Test-Path $warmDone)) {
    Set-Content -Path $allDone -Value (Get-Date -Format o)
    schtasks /delete /tn 'SupremeMotors-PerfectMotors' /f 2>$null
    Log 'sweep + fill + image warm complete - done, task removed'
    exit 0
}

# 1. sweep phase — LOOP UNTIL STABLE. A single pass drops cars that
#    transiently failed to fetch under load; each extra pass re-fetches only
#    the non-banked ids and recovers them. We stop when a full pass adds < 10
#    new cars.
if (-not (Test-Path $sweepStable)) {
    $running = $phps | Where-Object { $_.CommandLine -like '*scrape:perfectmotors*' -and $_.CommandLine -notlike '*--fill-incomplete*' }
    if ($running) {
        # a sweep pass is in progress — let it run
    } elseif (-not (Test-Path $sweepDone)) {
        Start-Process -FilePath $php -ArgumentList (@('artisan','scrape:perfectmotors','--min-id=64000','--max-id=71000','--pool=12') -join ' ') -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'perfectmotors-sweep.log') -RedirectStandardError (Join-Path $logDir 'perfectmotors-sweep.err.log')
        Log 'sweep pass launched'
    } else {
        # a pass finished: did it recover new cars?
        $now = BankedCount
        $last = if (Test-Path $countFile) { [int](Get-Content $countFile -Raw) } else { 0 }
        if (($now - $last) -ge 10) {
            Set-Content -Path $countFile -Value $now
            Remove-Item $cursorFile -Force -ErrorAction SilentlyContinue
            Remove-Item $sweepDone -Force -ErrorAction SilentlyContinue
            Log "sweep pass added $($now - $last) cars (now $now) - re-sweeping to recover stragglers"
        } else {
            Set-Content -Path $sweepStable -Value (Get-Date -Format o)
            Log "sweep stable at $now cars"
        }
    }
}
# 2. fill phase (sweep stable, fill not)
elseif (-not (Test-Path $fillDone)) {
    $filling = $phps | Where-Object { $_.CommandLine -like '*scrape:perfectmotors*--fill-incomplete*' }
    if (-not $filling) {
        Start-Process -FilePath $php -ArgumentList (@('artisan','scrape:perfectmotors','--fill-incomplete','--pool=20') -join ' ') -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'perfectmotors-fill.log') -RedirectStandardError (Join-Path $logDir 'perfectmotors-fill.err.log')
        Log 'fill-incomplete worker launched'
    }
}
# 3. warm phase (sweep + fill done, warm not) - Perma-Cache every image into Bunny
elseif (-not (Test-Path $warmDone)) {
    $warming = $phps | Where-Object { $_.CommandLine -like '*products:warm-cdn*--website=perfectmotors*' }
    if (-not $warming) {
        Start-Process -FilePath $php -ArgumentList (@('artisan','products:warm-cdn','--website=perfectmotors','--shard=pmwarm','--pool=40','--timeout=30') -join ' ') -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'perfectmotors-imagewarm.log') -RedirectStandardError (Join-Path $logDir 'perfectmotors-imagewarm.err.log')
        Log 'image warm launched (Perma-Cache into Bunny)'
    }
}

# status page from the progress json
$pf = Join-Path $state 'perfectmotors-progress.json'
$phase = if (-not (Test-Path $sweepStable)) { 'sweeping' } elseif (-not (Test-Path $fillDone)) { 'filling' } elseif (-not (Test-Path $warmDone)) { 'warming images' } else { 'done' }
if (Test-Path $pf) {
    try {
        $p = Get-Content $pf -Raw | ConvertFrom-Json
        $lines = @()
        $lines += '<!doctype html><meta charset="utf-8"><title>Perfect Motors scrape</title><meta http-equiv="refresh" content="30">'
        $lines += '<style>body{font-family:Segoe UI,system-ui,sans-serif;background:#3a0708;color:#fff;margin:0;display:grid;place-items:center;min-height:100vh}.card{background:#5a1112;border-radius:18px;padding:34px 40px;width:min(560px,92vw)}h1{font-size:18px;color:#ffd0d0}.big{font-size:42px;font-weight:800}.bar{height:14px;background:#3a0708;border-radius:10px;overflow:hidden;margin:10px 0 22px}.fill{height:100%;background:linear-gradient(90deg,#ff5a60,#fff)}.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}.k{font-size:24px;font-weight:800}.l{font-size:12px;color:#ffd0d0}</style>'
        $lines += '<div class=card><h1>PERFECT MOTORS - ' + $phase.ToUpper() + '</h1>'
        $lines += '<div class=big>' + $p.percent + '%</div><div class=bar><div class=fill style="width:' + $p.percent + '%"></div></div><div class=grid>'
        $lines += '<div><div class=k>' + $p.products_scraped + '</div><div class=l>cars banked</div></div>'
        $lines += '<div><div class=k>' + $p.images_scraped + '</div><div class=l>images</div></div>'
        $lines += '<div><div class=k>' + $p.ids_skipped + '</div><div class=l>ids skipped</div></div>'
        $lines += '</div><div class=l style="margin-top:16px">id ' + $p.current_id + '/' + $p.max_id + ' - updated ' + (Get-Date -Format o) + '</div></div>'
        Set-Content -Path (Join-Path $state 'perfectmotors-status.html') -Value ($lines -join "`n") -Encoding utf8
    } catch {}
}

Log ("tick - phase=" + $phase)
