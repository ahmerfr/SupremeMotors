@echo off
REM ============================================================
REM  SupremeMotors - Start the AutoTrader scraper
REM  Double-click this file to arm everything. It:
REM    - registers a reboot-proof scheduled task (runs every 5 min)
REM    - starts the deep scrape right now
REM    - opens the live status page in your browser
REM  Safe to close this window afterwards - it keeps running.
REM ============================================================

REM --- self-elevate to admin (needed to register the task) ---
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Requesting administrator rights...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

set PROJECT=C:\xampp\htdocs\SupremeMotors
set KEEPALIVE=%PROJECT%\scripts\scrape-keepalive.ps1
set STATUS=%PROJECT%\storage\app\cdn\autotrader-status.html

echo.
echo === Ensuring the database schema is current (adds spec columns/indexes) ===
"C:\xampp\php\php.exe" "%PROJECT%\artisan" migrate --force

echo.
echo === Registering the auto-resume task (every 5 min, survives reboots) ===
schtasks /create /tn "SupremeMotors-Scrape" /tr "powershell -NoProfile -ExecutionPolicy Bypass -File %KEEPALIVE%" /sc minute /mo 5 /rl HIGHEST /f

echo.
echo === Starting the scraper now (harvests proxies, then deep-scrapes) ===
powershell -NoProfile -ExecutionPolicy Bypass -File "%KEEPALIVE%"

echo.
echo === Opening the live status page (auto-refreshes every 30s) ===
timeout /t 3 >nul
if exist "%STATUS%" ( start "" "%STATUS%" ) else ( echo    status page appears after the first page is scraped )

echo.
echo ============================================================
echo  Scraper is armed and running.
echo  It auto-resumes through net drops, crashes and reboots.
echo  You can close this window - it keeps going in the background.
echo  To stop it later, run StopScraper.bat in this folder.
echo ============================================================
echo.
pause
