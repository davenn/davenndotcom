# Track Timer

A stopwatch for track training. One master timer with any number of athletes,
each getting their own splits, and finished sessions saved to a board.

**File:** [`tracktimer.html`](../../tracktimer.html)
**Tables:** `track_sessions`
**Auth:** none

## What it does

Start the master timer, add athletes, then record a split per athlete as they
finish (`recordSplit`) or `masterLap` to lap everyone at once. A session is
saved with a name, its total duration and the athlete list.

## Athletes are a JSON blob

`track_sessions.athletes` holds JSON rather than a related table, decoded on
the way out in `track_sessions`. The roster changes every session and splits
are a variable-length list per athlete, so there is nothing stable to make
columns of. Reporting across sessions would need a real schema; nothing asks
for that today.

## Endpoints

`track_sessions` `save_track_session` `delete_track_session`
`clear_track_sessions` — full detail in [`../api.md`](../api.md).

Note the scope difference: `delete_track_session` takes an id, while
`clear_track_sessions` **empties the table** — it is not scoped to a week, a
user or a session. There is only one board, shared by everyone who opens the
app.

## Front-end notes

- Two tabs: Timer and Session Board.
- Board rows expand to show per-athlete splits (`toggleSession`).
- Theme under `localStorage['track_theme']`.
- All rendered text goes through `escHtml` — athlete names are free text.
