# CDN pipeline keepalive — run by the SupremeMotors-CdnPipeline scheduled task
# every 5 minutes. Survives reboots, battery death and MySQL crashes:
#   1. done marker present -> remove the scheduled task and exit
#   2. ensure mysqld is up (start it after a reboot) + restore the buffer pool
#   3. ensure `php artisan cdn:pipeline` is running; start it if not

$project = 'C:\xampp\htdocs\SupremeMotors'
$doneMarker = Join-Path $project 'storage\app\cdn\warm.done'
$logDir = Join-Path $project 'storage\logs'
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force $logDir | Out-Null }

# 1. finished? clean up after ourselves
if (Test-Path $doneMarker) {
    schtasks /delete /tn 'SupremeMotors-CdnPipeline' /f 2>$null
    exit 0
}

# 2. MySQL up?
$mysqld = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysqld) {
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' -WindowStyle Hidden
    # wait for the port (up to 90s)
    $up = $false
    foreach ($i in 1..45) {
        Start-Sleep -Seconds 2
        try {
            $c = New-Object Net.Sockets.TcpClient
            $c.Connect('127.0.0.1', 3307); $c.Close(); $up = $true; break
        } catch {}
    }
    if ($up) {
        # my.ini still carries the tiny 16M pool; restore the fast one
        & 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -B -e 'SET GLOBAL innodb_buffer_pool_size=536870912' 2>$null
    } else {
        exit 1  # try again on the next tick
    }
}

# 3. pipeline running?
$running = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
    Where-Object { $_.CommandLine -like '*cdn:pipeline*' }
if (-not $running) {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $log = Join-Path $logDir "cdn-pipeline-$stamp.log"
    Start-Process -FilePath 'php' -ArgumentList 'artisan','cdn:pipeline' `
        -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput $log
}
exit 0
