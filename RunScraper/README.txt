SupremeMotors — AutoTrader Scraper controls
===========================================

Three files, double-click to use:

  StartScraper.bat   Arms and starts everything. Registers a task that
                     auto-resumes through net drops, crashes and reboots,
                     starts the deep scrape, and opens the live status page.
                     (Asks for admin — needed to register the task.)

  StopScraper.bat    Stops the scraper and removes the task. Progress is
                     saved; StartScraper.bat resumes exactly where it left
                     off. Only stops the scraper — never the CDN warm.

  WatchProgress.bat  Opens the live status dashboard (page %, products,
                     images, ETA). Refreshes itself every 30 seconds.

What "everything-proof" means
-----------------------------
  - Internet drops        -> waits it out, resumes, nothing lost
  - Laptop shuts down     -> resumes on next login, from the saved page
  - App/process crashes   -> relaunched within 5 minutes
  - Free proxies die       -> pool auto-refreshes, picked up live
  Nothing already scraped is ever re-fetched.

Image permanence (runs in PARALLEL, automatic)
----------------------------------------------
  As each car is scraped, its images are warmed into Bunny CDN at the
  same time: every photo is requested through the CDN so Bunny stores a
  PERMANENT copy. Your images then survive even if AutoTrader later
  deletes the originals. Because it runs alongside the scrape (not after),
  the whole job finishes in roughly the scrape time, not double.

Notes
-----
  - Prices are stored in USD (real price shown, not "Enquire").
  - Every product image is captured (full gallery, not just a few).
  - Uses free proxies, so the full run of ~93,000 cars can take
    8-15 hours. It finishes on its own and reports the whole way.
  - Files it writes live in: storage\app\cdn\
      autotrader-progress.json   machine-readable progress
      autotrader-status.html     the dashboard
      autotrader-heartbeat.txt   one line per page
      proxies.txt                the working proxy pool
