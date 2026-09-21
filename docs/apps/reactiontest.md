# Reaction Test

Tap the circle as soon as it appears, repeatedly. Your average reaction time
in milliseconds is the score, and the ten fastest each week make the board.

**File:** [`reactiontest.html`](../../reactiontest.html)
**Tables:** `reaction_scores`
**Auth:** none

## The one behaviour worth knowing

**A score that misses the top ten is not saved, and that is not an error.**

`save_reaction` counts how many times in the week beat the submitted average.
If ten or more already have, it answers `success: false` with HTTP 200 and a
message — not a 4xx.

That is deliberate. Missing the board is an ordinary outcome of playing, and
the app should be able to say so plainly without a failure banner. A client
that treats any `success: false` as an error will get this wrong.

The board itself is `LIMIT 10`, ordered by `avg_ms` ascending.

## Weeks

Keyed by Monday date (`getMondayKey`), same convention as the meeting timer
and daily tasks. The board has previous/next navigation and a clear action
scoped to one week.

## Endpoints

`reaction_week` `save_reaction` `clear_reaction_week` — full detail in
[`../api.md`](../api.md).

## Front-end notes

- Two tabs: Test and Leaderboard.
- Names are truncated to 60 characters and stripped of tags server-side, then
  escaped again on render.
- Circle position is randomised per round (`randomizePosition`) so the game
  measures reaction rather than a memorised cursor position.
- Theme under `localStorage['reaction_theme']`.
