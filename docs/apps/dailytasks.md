# Daily Tasks

A daily task list where each task has its own timer, plus a weekly
leaderboard. Works signed out; signing in adds sync across devices.

**File:** [`dailytasks.html`](../../dailytasks.html)
**Tables:** `dt_scores` (leaderboard) `dt_tasks` (synced lists)
**Auth:** none for the leaderboard; Toolshare accounts for sync

## Two halves with different rules

This is the one app where part of it authenticates and part does not, so the
distinction matters:

| Half | Endpoints | Auth | Storage |
|---|---|---|---|
| Leaderboard | `dt_week` `dt_save` | none | `dt_scores` |
| Task sync | `dt_get_tasks` `dt_save_tasks` | `requireAuth()` | `dt_tasks` |

**Sync reuses Toolshare accounts.** There is no separate registration: the app
calls `tb_login` and `tb_register`, and the token it gets back is a Toolshare
session token. Changing Toolshare's auth changes this app too.

Signing in is optional throughout. The task list lives in `localStorage` and
works offline; an account only adds carrying it between devices.

## The daily reset

Tasks are keyed by day (`todayKey`). `checkDailyReset` rolls the list over
when the date changes, which has to be checked on load and while the page is
open — a phone left on the counter overnight is the normal case, not the edge
case.

## Leaderboard ranking

`dt_week` ranks by `task_count` descending, then `total_seconds` ascending:
doing more wins, and doing the same amount faster settles the tie. Posting to
it is a deliberate act, not automatic — the app has a Save to Leaderboard
button.

## Front-end notes

- Each task has a start/pause timer; elapsed time accumulates across pauses
  rather than being wall-clock from first start (`computeElapsed`).
- Signed in, the app polls the server (`pollServer`) so a list edited on a
  phone shows up on a laptop without a manual refresh.
- Theme under `localStorage['dailytasks_theme']`.
