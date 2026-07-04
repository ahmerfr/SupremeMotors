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

# shard table: name / min id / max id / pool (A = warmed+tcv sweep, B = madeinchina,
# C+D = the linemedia block split in half)
$shards = @(
    @{ name = 'a'; min = 0;      max = 176298; pool = 60 },
    @{ name = 'b'; min = 176298; max = 232420; pool = 60 },
    @{ name = 'c'; min = 232420; max = 345000; pool = 50 },
    @{ name = 'd'; min = 345000; max = 453376; pool = 50 }
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
            'artisan', 'products:warm-cdn', '--scope=fronts',
            ('--shard=' + $s.name), ('--min-id=' + $s.min), ('--max-id=' + $s.max), ('--pool=' + $s.pool)
        ) -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput $log
    }
}

if ($allDone) {
    'all shards complete' | Set-Content (Join-Path $state 'warm.done')
}
exit 0
