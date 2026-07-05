@echo off
REM Opens the live scrape status dashboard (auto-refreshes every 30s).
set STATUS=C:\xampp\htdocs\SupremeMotors\storage\app\cdn\autotrader-status.html
if exist "%STATUS%" (
    start "" "%STATUS%"
) else (
    echo Status page not created yet - it appears after the first page is scraped.
    echo Start the scraper with StartScraper.bat first.
    pause
)
