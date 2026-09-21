# Pomodoro

A pomodoro timer: work, short break, long break, cycling automatically. Plus
a stripped-back page that can be cast to a second screen.

**Files:** [`pomodoro.html`](../../pomodoro.html), [`pomodoro-cast.html`](../../pomodoro-cast.html)
**Tables:** none
**Auth:** none — entirely client-side

## Phases

Three durations, each adjustable in the app and kept in `localStorage`:

| Key | Phase |
|---|---|
| `pom_work` | work interval |
| `pom_short` | short break |
| `pom_long` | long break |

`advance` moves to the next phase and `loadPhase` sets the clock for it. The
cycle runs on its own; `skip` jumps ahead.

## Staying awake and being heard

Two things a timer app has to get right on a phone:

- **Wake lock.** `acquireWakeLock` / `releaseWakeLock` hold the screen on
  while running, because a pomodoro you cannot see is not doing its job.
  Released when paused, so it does not hold the screen on indefinitely.
- **Notification and sound.** `notify` and `beep` both fire at a phase
  change. Either can be unavailable — notification permission may be denied,
  and audio may be blocked until the user has interacted with the page — so
  there are two channels rather than one.

## Casting

`toggleCast` sends state to `pomodoro-cast.html`, a minimal page meant for a
TV or second monitor. The cast page only displays; it does not control the
timer. `sendCastState` pushes updates and `onCastEnd` cleans up.

The cast page is deliberately not in the app grid on the home page — it is a
destination for the cast, not something to open directly.

## Front-end notes

- Has an install button (`triggerInstall`) wired to the PWA install prompt.
- Full-screen toggle (`toggleFs`).
- Theme under `localStorage['pom_theme']` — note the short key, not
  `pomodoro_theme`.
- Nothing is ever sent to the server, so there is no history and no record of
  completed pomodoros.
