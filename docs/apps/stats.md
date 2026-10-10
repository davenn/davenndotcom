# Stats

First-party analytics: every page reports one anonymous page view, `api.php`
tallies its own requests, and `stats.html` shows both. There is no third-party
service in the loop. The page is deliberately unlisted on the home grid.

**Files:** [`stats.html`](../../stats.html), plus the beacon at the bottom of
every other page
**Tables:** `an_hits` `an_daily` `an_salt` `an_api`
**Auth:** `X-Admin-Secret` to read; the beacon is open

## The beacon

Each page ends with the same small inline script. On load it calls
`navigator.sendBeacon('/api.php?action=an_hit', …)` with the path, the
referrer, a device class (coarse pointer + short screen side → mobile, coarse
pointer → tablet, else desktop), whether it is running as an installed PWA,
its `data-theme` (or the OS preference if the page has none) and
`navigator.language`. `sendBeacon` sends `text/plain`, so there is no CORS
preflight and it survives the page being closed.

It sends nothing when the browser signals Do Not Track or Global Privacy
Control, when `navigator.webdriver` is set, or when `localStorage.an_optout`
is `'1'`. The "Don't count this device" box on the stats page sets that flag,
so the owner's own visits can be left out per browser.

**Adding a page:** paste the same script before `</body>`. `docs/api.html` gets
it from the template in `tools/gen-api-docs.mjs`, so it survives regeneration.
`stats.html` does not carry it, and `anPage()` refuses `stats` anyway.

## What the server keeps

`an_hit` always answers 204, so a caller cannot tell whether anything was kept.
It drops bot user agents, pages that do not exist as a file, and anything past
120 views an hour from one visitor or 5,000 an hour site-wide. It then adds
browser and OS families from the user agent, and city, region, country and
rounded coordinates from the IP.

What it never stores: the IP, the full user agent, the full referrer URL (only
its host, or `internal` for navigation within the site), or a query string.

**Unique visitors without cookies.** `vid` is the first 16 hex characters of
`sha256(salt | IP | user agent)`. `an_salt` holds exactly one salt, today's
(UTC). The first hit of a new day creates a fresh one and deletes the old, and
from then on yesterday's hashes cannot be recomputed from an IP. Two
consequences that show up on the dashboard:

- a visitor counts once per day, so "visitors" over a range is visitor-days,
  not people
- two people behind one NAT with the same browser build are one visitor

`ts` is UTC, truncated to the hour. The dashboard passes its timezone offset
to `an_stats`, which groups days and hours in local time. `an_api` and the
all-time history are in UTC days.

**Retention.** On roughly one hit in 200, `anRollup()` folds every whole UTC
day older than 90 days into `an_daily` (per page, plus `*` for the whole site)
and deletes those rows. So anything finer than daily views and visitors per
page, such as cities, referrers or devices, only exists for the last 90 days.

## API usage

A `register_shutdown_function` registered just after `$action` is read writes
one upsert per request into `an_api`, keyed by UTC day, method and action. It
records the call, an error if the status is 400+ or the request died with a
fatal error (an uncaught exception included), and the elapsed ms. Every branch
ends in `exit`, and shutdown functions still run after `exit`, so no endpoint
has to opt in. Anything that falls through to the final `Unknown action`
response is pooled as `(unknown)`, so junk URLs cannot grow the table.
`an_hit` and `an_stats` are left out.

## City lookup: GeoLite2

The location comes from MaxMind's free **GeoLite2 City** database, read by
`anGeoLookup()`, a small `.mmdb` reader inside `api.php` (no library; the site
has no package manager). It seeks through the file rather than loading it, at
roughly 0.2 ms per lookup. It was checked against MaxMind's own reader on their
published test databases.

The database is **not in the repo**. Its licence forbids redistribution and
the repo is public, so `*.mmdb` is gitignored. Install it by hand:

1. Create a free GeoLite account at maxmind.com, then download **GeoLite2
   City** in `.mmdb` format (a `.tar.gz`). Extract `GeoLite2-City.mmdb`.
2. Upload it by FTP to the hosting account's home directory, **one level
   above `public_html`**. That is the default path `anGeoDbPath()` looks at, and
   there it cannot be downloaded over the web. Never put it inside
   `public_html`: that would publish it.
3. If the host's `open_basedir` blocks reading outside `public_html`, put it
   anywhere readable and set `GEOIP_DB=/absolute/path/GeoLite2-City.mmdb` in
   the server `.env`. `?action=cp_diag` reports `open_basedir`.

Without the file, everything still works and visits are stored with no
location. The dashboard says so in a notice.

**Keep it fresh.** MaxMind updates GeoLite2 twice weekly, and the licence asks
that you move to a new release within 30 days. The dashboard shows a notice
once the file's modification date is more than 45 days old. Replacing the file
is the whole update.

The licence also requires the attribution line, which is in the
`stats.html` footer and on `privacy.html`.

## The dashboard

`stats.html` asks for the admin secret and keeps it in `sessionStorage`, or
`localStorage` if "Remember on this device" is ticked. A 401 clears it. It is
meant to be replaced by a PIN later.

It shows: tiles, views by day (by hour for Today), a city map, then ranked
tables for apps, cities, referrers, countries, devices, browsers, OS, languages
and theme, followed by the API table and an all-time daily series.

- **The map** draws Natural Earth 110m land, embedded in the file as one SVG
  path in quarter-degree units (`x = lon × 4`, `y = −lat × 4`). Embedding it
  means no CDN request and no library. US and World are just two viewBoxes.
  Dots are sized by the square root of views.
- **Distance from home.** "Set home to my location" uses the browser's
  geolocation once, rounds it to two decimals and keeps it in `localStorage`
  only. It adds a miles column to the cities table, a ring on the map, and
  four distance bands (≤25, 25–100, 100–500, 500+ mi) as a share of located
  visitors.

## Endpoints

`an_hit` `an_stats` — full detail in [`../api.md`](../api.md). `an_stats` is
admin-gated, so it is left off the public `docs/api.html`.
