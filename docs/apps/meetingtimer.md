# Meeting Cost Timer

Runs a timer during a meeting and converts elapsed time into money, given the
number of people in the room and what they cost per hour. Finished meetings
can be posted to a weekly board.

**File:** [`meetingtimer.html`](../../meetingtimer.html)
**Tables:** `meetings`
**Auth:** none

## What it does

Cost per second is derived from headcount and rate (`getCostPerSecond`) and
ticked up live while the timer runs. Time can be adjusted by hand
(`manualTimeAdjust`) for the usual reason — you started the timer ten minutes
late.

Saving posts the title, cost, seconds and week to `meetings`. The board lists
one week at a time, **ordered by cost descending**, because the expensive
meeting is the whole point of the app.

## Weeks

Weeks are keyed by the Monday date in `YYYY-MM-DD` form (`getMondayKey`), and
the API validates that shape on every call. The board has previous/next
navigation and a Clear this week action.

## Endpoints

`week` `save` `clear_week` — full detail in [`../api.md`](../api.md).

Validation rejects a meeting without a title, or with a cost or duration of
zero: a meeting with no cost is a timer that was never really started, and
keeping it only adds noise to the week.

## Front-end notes

- Two tabs: Timer and Leaderboard.
- No `localStorage` at all — this is the only app with no client-side
  persistence, so a reload during a meeting loses the running timer.
- Still contains commented-out `yourdomain.com` example lines above the live
  `API_URL`. They are examples, not a misconfiguration.
