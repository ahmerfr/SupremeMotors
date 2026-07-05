# Phase-1 driver: run every price-band shard BACK TO BACK in one long-lived
# process (launched + kept alive by the keepalive). Without this the keepalive
# would fire one band per 3-min tick = ~17h of idle for 528 bands; here bands
# run one-after-another with no wait, so Phase-1 finishes in ~1h and Phase-2
# enrich (already running in parallel) drains the backlog behind it.
#
# Idempotent + resumable: skips bands that already have a .done marker (each
# band's command writes it on completion), so a crash/relaunch continues where
# it left off. Exits when every band is done.

$project = 'C:\xampp\htdocs\SupremeMotors'
$php     = 'C:\xampp\php\php.exe'
$state   = Join-Path $project 'storage\app\cdn'
$logDir  = Join-Path $project 'storage\logs'
$shardsFile = Join-Path $state 'autotraderuk-shards.json'
if (-not (Test-Path $shardsFile)) { exit 0 }

# single-instance lock: if another loop is already alive, exit (prevents the
# keepalive + a manual start from racing two loops over the same bands)
$lock = Join-Path $state 'autotraderuk-phase1.lock'
if (Test-Path $lock) {
    $otherPid = (Get-Content $lock -ErrorAction SilentlyContinue | Select-Object -First 1)
    if ($otherPid -and (Get-Process -Id ([int]$otherPid) -ErrorAction SilentlyContinue)) { exit 0 }
}
Set-Content -Path $lock -Value $PID
$cleanup = { Remove-Item $lock -Force -ErrorAction SilentlyContinue }
Register-EngineEvent PowerShell.Exiting -Action $cleanup | Out-Null

# run from the project root so `artisan` resolves (the & call operator does not
# take -WorkingDirectory, so use the absolute artisan path)
Set-Location $project
$artisan = Join-Path $project 'artisan'

$plan  = Get-Content $shardsFile -Raw | ConvertFrom-Json
foreach ($b in $plan.bands) {
    $doneMk = Join-Path $state ("autotraderuk-scrape-" + $b.shard + ".done")
    if (Test-Path $doneMk) { continue }

    $sargs = @($artisan,'scrape:autotraderuk',('--shard=' + $b.shard),'--pool=3','--delay-ms=200')
    if ($b.min -ne $null) { $sargs += ('--min-price=' + [int]$b.min); $sargs += ('--max-price=' + [int]$b.max) }

    # blocking run — one band, then straight on to the next
    & $php @sargs *> (Join-Path $logDir ('autotraderuk-' + $b.shard + '.log'))
}

Remove-Item $lock -Force -ErrorAction SilentlyContinue
