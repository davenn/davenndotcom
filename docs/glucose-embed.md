# Glucose Embed Endpoint

How to ingest CGM readings on a microcontroller.

As of 2026-09-18

## The contract

`bg_embed` returns one line of plain text holding the newest stored reading.

```
GET https://davenn.com/api.php?action=bg_embed&token=<BG_READ_TOKEN>

141,3,1,1
```

That is the whole response: 10 bytes, `text/plain`, no JSON. A 24-point sparkline brings it to about 100 bytes. The equivalent `bg_history` call is 17.7 KB, which is why this endpoint exists.

**It reads MySQL only.** A device never reaches Dexcom, so adding displays costs the upstream API nothing — Dexcom load stays fixed by the poller no matter how many panels are on the wall. It also means a display shows whatever was last stored: if the poller stalls, the reading goes stale rather than wrong.

**Status: written, not deployed.** As of 2026-09-18 the endpoint returns 404 on davenn.com. The PHP is in `api.php` uncommitted, and has not been run through an interpreter — there is no PHP on the dev machine, so the code is unverified beyond a simulation of its output against live data.

## Authentication

Every read needs `BG_READ_TOKEN`, passed either way:

| Method | Form |
| --- | --- |
| Query string | `?action=bg_embed&token=<TOKEN>` |
| Header | `X-BG-Token: <TOKEN>` |

The query string is simpler on a microcontroller and is what the examples below use. The token lives in the `.env` on the GoDaddy server, not in the repo — the deploy workflow excludes `.env`, so it has to be set there directly.

Auth fails closed. An empty or missing `BG_READ_TOKEN` on the server rejects every request, so a 401 means either a wrong token or an unconfigured server, not an open endpoint.

**The token is not purely read-only.** `bg_refresh` accepts the same token, so anything holding it can trigger a poll against Dexcom. A 45-second server-side cooldown caps the rate, and for a wall display that is arguably useful — it can force a sync when someone walks up. But a device that goes missing carries that capability with it. Splitting refresh onto its own token is a small change if that matters.

## Fields

Line 1 is four comma-separated integers, newline-terminated.

| Position | Field | Meaning |
| --- | --- | --- |
| 1 | `mgdl` | Glucose in mg/dL. **0 means nothing is stored.** |
| 2 | `trend` | Direction, 0–7 (table below) |
| 3 | `minutes_ago` | Age of the reading. `-1` when there is no data |
| 4 | `in_range` | 1 inside 70–180, else 0 |

The `in_range` thresholds follow `&low=` and `&high=` if given, defaulting to 70 and 180, so a device agrees with whatever the dashboard shows.

### Trend codes

The codes follow Dexcom's own ordering, so a device can index an arrow glyph array directly rather than comparing strings:

| Code | Direction | Glyph |
| --- | --- | --- |
| 0 | Unknown | — |
| 1 | Rising fast | ↑↑ |
| 2 | Rising | ↑ |
| 3 | Drifting up | ↗ |
| 4 | Steady | → |
| 5 | Drifting down | ↘ |
| 6 | Falling | ↓ |
| 7 | Falling fast | ↓↓ |

### Why mgdl is the no-data flag

A single check covers an empty database, a brand-new install, and a sensor that has never reported. Testing `mgdl <= 0` before drawing avoids a separate error path and any status-code handling on the device.

### Freshness

`minutes_ago` is computed from the reading's true timestamp, not the 5-minute grid key used for deduplication. That distinction matters: the grid key floors the time, which made fresh readings look up to five minutes old and would push a display into a false stale state.

## Requesting history

Adding `&spark=N` appends a second line: the last N readings as comma-separated mg/dL values, oldest first.

```
GET ...&action=bg_embed&token=<TOKEN>&spark=24

141,3,1,1
108,108,109,117,121,117,116,115,113,109,106,103,...
```

At the sensor's 5-minute cadence, count determines span:

| `spark` | Approx. span | Response size |
| --- | --- | --- |
| 12 | 1 hour | ~48 bytes |
| 24 | 2 hours | ~100 bytes |
| 60 (max) | 5 hours | ~240 bytes |

### Two limitations

**Count and span are welded together.** A 64-pixel-wide display wants exactly 64 points; whether those cover 2 hours or 12 is a separate decision you cannot currently make. Asking for 64 points forces a 5.3-hour window.

**Time is ignored.** `LIMIT 60` takes the last 60 rows whatever their timestamps. If the poller was down overnight, those rows might span 14 hours, and a device drawing them evenly spaced produces a smooth, confident, wrong line. The dashboard chart breaks its line at gaps; this endpoint has no such protection.

### Planned replacement

A `span` parameter decoupling the two, bucketing the window into N slots and averaging within each:

```
&points=64&span=6     → 64 points evenly covering the last 6 hours
```

Empty buckets emit `0`, so a device knows where not to draw and an outage renders as a gap rather than a fabricated line. The cap rises from 60 to 128, since common display widths are 64 and 128 and 128 points is still only ~512 bytes.

**Open: `spark` or `points`.** `spark` is short for sparkline — jargon that does not say it returns data points, and it pairs awkwardly with `span`. Renaming is free while nothing is deployed; once a device is flashed with a URL, changing it means reflashing.

## Parsing on device

The whole parse is one line:

```c
int mgdl, trend, mins, inRange;
if (sscanf(buf, "%d,%d,%d,%d", &mgdl, &trend, &mins, &inRange) != 4) return;
if (mgdl <= 0) { showNoData(); return; }
```

No JSON library, no dynamic allocation, no heap fragmentation over months of uptime. A fixed stack buffer is enough:

| Request | Buffer |
| --- | --- |
| Line 1 only | 32 bytes |
| With `spark=24` | 160 bytes |
| With `spark=60` | 320 bytes |

The sparkline sits on line 2, so split on the newline first and walk it with `strtok`:

```c
char *line2 = strchr(buf, '\n');
if (line2) {
  int i = 0;
  for (char *t = strtok(line2 + 1, ","); t && i < MAX_PTS; t = strtok(NULL, ",")) {
    spark[i++] = atoi(t);   // 0 = no data for that slot, skip drawing
  }
}
```

Request only what you will draw. Asking for 60 points to render 24 wastes bandwidth, parse time and buffer on a device that has little of any.

## TLS

The endpoint is HTTPS only, and TLS is the bulk of the work on an ESP32. The chain served by davenn.com is:

```
leaf    CN=davenn.com                                  expires 2026-11-21
chain   Go Daddy Secure Certificate Authority - G2    (intermediate)
root    Go Daddy Root Certificate Authority - G2      (expected in trust store)
```

**Embed the root, not the leaf.** The leaf expires 21 Nov 2026 and rotates on every renewal — pin it and the device dies roughly twice a year, at whatever hour the renewal lands. Embed the Go Daddy Root CA G2 (from `certs.godaddy.com/repository`) and it survives renewals untouched.

```cpp
WiFiClientSecure client;
client.setCACert(GODADDY_ROOT_G2);   // not the leaf
```

### Memory

The handshake needs roughly 30–40 KB of RAM. Comfortable on an ESP32, tight on an ESP8266 — on the latter, expect to free heap elsewhere or accept intermittent handshake failures.

### On setInsecure

`client.setInsecure()` skips verification and is what most tutorials reach for. It works, and it sends `BG_READ_TOKEN` over a connection nobody has authenticated. On a home LAN the practical risk is modest, but it is the kind of shortcut that quietly becomes permanent — and this token can trigger a poll, not just read. Worth the extra hour to pin the root.

## Polling and staleness

Poll every 60 seconds. Readings only arrive every 5 minutes, so faster buys nothing but battery and bandwidth.

On battery, deep-sleep between polls. `minutes_ago` tells the device where it sits in the cycle, so it can sleep until just after the next reading is due rather than waking on a blind timer — the same trick the server-side poller uses.

### The staleness rule

**A display must never present an old number as current.** This is the one rule worth enforcing in firmware, because a wall panel showing a confident wrong number is worse than one showing nothing.

| `minutes_ago` | Display |
| --- | --- |
| 0–15 | Normal |
| 15–60 | Show the age alongside the number, dimmed |
| Over 60 | Stop showing the number; show the age alone |

Fifteen minutes is the threshold the dashboard uses. It sits comfortably above healthy worst case: a reading publishes about a minute after it is taken, and the poller picks it up within its own cycle.

### Failure modes

| Symptom | Meaning |
| --- | --- |
| `mgdl` is 0 | Nothing stored yet — new install, or the poller has never run |
| `minutes_ago` climbing past 15 | Poller stalled; stored data is fine, just not fresh |
| HTTP 401 | Wrong token, or `BG_READ_TOKEN` unset on the server |
| HTTP 404 | Endpoint not deployed |
| Connection fails | Usually TLS — check the root CA before suspecting the network |

A stalled poller and a dead sensor look identical from the device. Neither is worth distinguishing in firmware: both mean the number on the wall is old, and the staleness rule already covers it.
