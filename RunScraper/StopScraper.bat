@echo off
REM ============================================================
REM  SupremeMotors - Stop the AutoTrader scraper
REM  Removes the scheduled task and stops ONLY the scraper's
REM  php processes. It does NOT touch the CDN warm workers or
REM  anything else. Progress is saved - you can restart anytime
REM  with StartScraper.bat and it resumes where it left off.
REM ============================================================

net session >nul 2>&1
if %errorlevel% neq 0 (
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo.
echo === Removing the auto-resume task ===
schtasks /delete /tn "SupremeMotors-Scrape" /f 2>nul

echo.
echo === Stopping only the scraper processes (CDN warm untouched) ===
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { $_.CommandLine -match 'scrape:autotrader|scrape:refresh-proxies' } | ForEach-Object { Write-Host ('  stopping PID ' + $_.ProcessId); Stop-Process -Id $_.ProcessId -Force }"

echo.
echo Scraper stopped. Progress is saved - StartScraper.bat resumes it.
echo.
pause
