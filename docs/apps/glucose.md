# Glucose

A dashboard over continuous glucose monitor readings, plus a way to annotate
why a stretch of the graph looked the way it did.

**Files:** [`glucose.html`](../../glucose.html) (dashboard), [`bgcast.html`](../../bgcast.html) (display view)
**Tables:** `bg_readings` `bg_events`
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

`bg_latest` returns `minutes_ago` rather than leaving the caller to work it
out, because the decision every display has to make is whether the number has
gone stale. An old reading presented as current is worse than no reading.

`bg_embed` exists for microcontrollers. Trend is an integer following Dexcom's
own ordering, so a device can index an arrow glyph straight off it:

```
0 unknown · 1 up-up · 2 up · 3 up-45 · 4 flat · 5 down-45 · 6 down · 7 down-down
```

`mgdl` of 0 means nothing is stored yet — distinct from a real low.

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
- Panel open/closed state persists under `bg_panel_*` keys.
