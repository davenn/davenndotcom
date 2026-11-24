# Glucose

A dashboard over continuous glucose monitor readings, plus a way to annotate
why a stretch of the graph looked the way it did.

**Files:** [`glucose.html`](../../glucose.html) (dashboard), [`bgcast.html`](../../bgcast.html) (display view), [`glucose-report.html`](../../glucose-report.html) (clinic report snapshot)
**Tables:** `bg_readings` `bg_events` `bg_pump` `bg_pump_cursor`
**Auth:** a shared read token — `X-BG-Token` or `?token=`; ingest needs the admin secret
**Public API page:** [`../glucose-api.html`](../glucose-api.html)

## Why readings are stored at all

Dexcom's Share API only ever serves a rolling 24-hour window. Anything older
is simply gone. So a poller pushes readings into `bg_readings` through
`bg_ingest`, and everything the app shows is served from storage — no request
from a reader ever reaches the CGM vendor.

`bg_ingest` is idempotent on purpose. The poller deliberately re-sends an
overlapping window, and replaying it refreshes rows that already exist rather
than duplicating them. That makes a missed run self-healing instead of
something to reconcile by hand.

## Reading the data

| Endpoint | For |
|---|---|
| `bg_latest` | the newest reading, with `minutes_ago` |
| `bg_history` | a window of readings for the curve |
| `bg_daily` | per-day rollup for the calendar view |
| `bg_embed` | plain text, for devices that cannot parse JSON |
| `bg_export` | every reading for up to 180 days, for clinic reports and time-of-day profiles |
| `bg_pump` | insulin pump records overlapping a window, up to 90 days |

`bg_latest` returns `minutes_ago` rather than leaving the caller to work it
out, because the decision every display has to make is whether the number has
gone stale. An old reading presented as current is worse than no reading.

`bg_embed` exists for microcontrollers. Trend is an integer following Dexcom's
own ordering, so a device can index an arrow glyph straight off it:

```
0 unknown · 1 up-up · 2 up · 3 up-45 · 4 flat · 5 down-45 · 6 down · 7 down-down
```

`mgdl` of 0 means nothing is stored yet — distinct from a real low.

## Insulin pump data

Levi's Omnipod 5 uploads to Glooko by itself, roughly hourly. The davenn-mcp
poller (the same service that reads Dexcom Share) signs in to that Glooko
account every 30 minutes and pushes what it finds to `bg_pump_ingest`:
boluses with insulin delivered and carbs entered, basal segments, suspends,
pod events, alarms, and Omnipod 5 mode intervals (automated, limited, manual,
activity) with automated-delivery bars.

- Glooko has no public API for individuals. The poller uses the unofficial one
  Glooko's own apps use, following the contracts the Nightscout community
  tested in nightscout-connect. It can break without notice, like Share.
- Pump times arrive as the pump's local clock marked as UTC. The poller turns
  them into real UTC with `GLOOKO_TIMEZONE` before they are stored.
- `bg_pump.raw` keeps each record exactly as Glooko sent it, so a wrong
  mapping can be fixed from what is stored rather than fetched again.
- Each feed's cursor lives in `bg_pump_cursor`, saved in the same transaction
  as its records. The poller's host sleeps and restarts; a restart resumes.
- Mode intervals are redrawn on every sync, so for those the poller replaces a
  two-day window instead of upserting.
- A rejected Glooko password, or a request for two-factor sign-in, stops the
  pump sync until someone fixes it. It is never retried, so the account cannot
  be locked out by the poller.

## Days and time zones

`bg_daily` groups into local days from a timezone name (`BG_TIMEZONE`) so it
follows daylight saving, using today's offset across the whole window. A day
either side of a DST change can land in the neighbouring bucket. That is a
deliberate trade: it is fine for a dashboard and avoids depending on MySQL's
timezone tables being loaded, which on shared hosting they often are not.

## Events

`bg_events` holds human annotations — a tagged span of time with an optional
note, answering "why did it look like that". Events can be open-ended (a start
with no end). Tags come from a fixed vocabulary in `bgEventTags()` rather than
free text, so they stay groupable.

## Front-end notes

- The token is entered once and kept in `localStorage['bg_token']`, shared with
  the [API console page](../glucose-api.html) so it carries over.
- The client passes it as `?token=`, not the header — both are accepted.
- Range switcher is 3H / 6H / 12H / 24H; the curve is hand-drawn SVG.
- Shows time in range, excursions, and current-run / longest-run figures.
- `bgcast.html` is a stripped-back view for a wall display or cast target.
- A **Reports** card, shown once unlocked, links to `glucose-report.html`.

## Clinic report

`glucose-report.html` is a fixed snapshot for an endocrinology visit
(Sep 17 - Nov 24, 2026): consensus CGM metrics, an ambulatory glucose profile,
lows and highs, weekly trend and a day calendar. **Its numbers are built into the
page**, by the owner's choice, so unlike the dashboard it needs no token: anyone
with the URL can read it, and the data is in this public repo's history. It is
`noindex`, not on the home grid and not in the service worker's `SHELL`.

The numbers were worked out from every reading via `bg_export`, and the page's
drawings use d3 from jsDelivr. To make a new report, regenerate the page from a
fresh export rather than editing the numbers by hand.
- Panel open/closed state persists under `bg_panel_*` keys.
