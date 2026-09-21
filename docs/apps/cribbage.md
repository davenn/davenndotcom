# Cribbage

A cribbage scoreboard. Two players, buttons for the common point values, and
an undo that actually works.

**File:** [`cribbage.html`](../../cribbage.html)
**Tables:** none
**Auth:** none — entirely client-side

## Scoring

Buttons for 1 through 8 cover almost every hand; `Add` handles the rest.
Points accumulate as a pending amount (`updatePending`) before being committed
to the active player, so a miscount is corrected before it lands rather than
after.

`setActive` picks whose score the points go to.

## Undo

The reason the app is pleasant to use. `snapshot` captures state and
`pushHistory` stacks it on every scoring action, so `undo` steps back through
real previous states rather than trying to subtract points.

This matters because the common cribbage error is not "wrong number" but
"right number, wrong player", and subtraction cannot fix that.

## Persistence

`loadState` and `saveState` keep the game in `localStorage`, so closing the
tab mid-game does not lose it. `newGame` clears it. Players can be renamed
(`openRenameModal`), and a win triggers `openWinModal` at 121.

## Front-end notes

- Theme under `localStorage['cribbage_theme']`.
- Has an install button (`triggerInstall`).
- All rendered names go through `escapeHtml`.
- No network calls at all — this app works offline permanently, not just when
  the service worker has cached it.
