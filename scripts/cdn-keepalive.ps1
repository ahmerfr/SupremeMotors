# CDN warm coordinator/keepalive — run by the SupremeMotors-CdnPipeline task
# every 5 minutes. Four fronts-scope shard workers cover distinct id ranges
# (distinct origins), so one throttling origin never gates the others.
#   1. warm.done -> remove the scheduled task and exit
#   2. ensure mysqld up + 512M buffer pool
#   3. relaunch any shard whose worker died and whose done-marker is missing
#   4. all shard markers present -> write warm.done (verify+shutdown watcher takes over)

$project = 'C:\xampp\htdocs\SupremeMotors'
$state = Join-Path $project 'storage\app\cdn'
$logDir = Join-Path $project 'storage\logs'
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force $logDir | Out-Null }

# Gallery-scope shard table: name / min id / max id / pool / scope.
# ga = tcv sweep (galleries mostly warmed already), gb = madeinchina at a
# crawl (their limiter is hour-scale), gc+gd = the linemedia block halves.
# linemedia now throttles Bunny's origin fetches: fetches complete in 60-120s
# under throttle, so wide pools just churn 45s timeouts. Small pools + long
# timeouts trickle steadily instead.
$shards = @(
    @{ name = 'ga'; min = 0;      max = 176298; pool = 40; timeout = 45;  scope = 'all' },
    @{ name = 'gb'; min = 176298; max = 232420; pool = 6;  timeout = 120; scope = 'all' },
    @{ name = 'gc';  min = 232420; max = 300000; pool = 90; timeout = 20; scope = 'all' },
    @{ name = 'gc2'; min = 300000; max = 345000; pool = 90; timeout = 20; scope = 'all' },
    @{ name = 'gd';  min = 345000; max = 385000; pool = 90; timeout = 20; scope = 'all' },
    @{ name = 'gd2'; min = 385000; max = 453376; pool = 90; timeout = 20; scope = 'all' }
)

if (Test-Path (Join-Path $state 'warm.done')) {
    schtasks /delete /tn 'SupremeMotors-CdnPipeline' /f 2>$null
    exit 0
}

# MySQL up + big pool
$mysqld = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysqld) {
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' -WindowStyle Hidden
    foreach ($i in 1..45) {
        Start-Sleep -Seconds 2
        try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', 3307); $c.Close(); break } catch {}
    }
}
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -B -e 'SET GLOBAL innodb_buffer_pool_size=536870912' 2>$null

# shard supervision
$phps = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'"
$allDone = $true
foreach ($s in $shards) {
    $doneMarker = Join-Path $state ("warm-" + $s.name + ".done")
    if (Test-Path $doneMarker) { continue }
    $allDone = $false
    $running = $phps | Where-Object { $_.CommandLine -like ('*--shard=' + $s.name + '*') }
    if (-not $running) {
        $stamp = Get-Date -Format 'HHmmss'
        $log = Join-Path $logDir ("warm-shard-" + $s.name + "-$stamp.log")
        Start-Process -FilePath 'php' -ArgumentList @(
            'artisan', 'products:warm-cdn', ('--scope=' + $s.scope),
            ('--shard=' + $s.name), ('--min-id=' + $s.min), ('--max-id=' + $s.max),
            ('--pool=' + $s.pool), ('--timeout=' + $s.timeout)
        ) -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput $log
    }
}

if ($allDone) {
    'all shards complete' | Set-Content (Join-Path $state 'warm.done')
}
exit 0
