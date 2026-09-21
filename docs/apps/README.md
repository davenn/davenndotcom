# Per-app notes

One page per app: what it does, the behaviour that is not obvious from reading
the code, its tables and its endpoints.

These are hand-written and in-repo only — they are not deployed. For the
generated endpoint reference see [`../api.md`](../api.md); for how the site
fits together see [`../../CLAUDE.md`](../../CLAUDE.md).

| App | Page | Backend |
|---|---|---|
| Toolshare | [toolshare.md](toolshare.md) | accounts, tools, borrowing, friends |
| Confidence Pool | [nflpool.md](nflpool.md) | weeks, games, entries, picks, SMS |
| Glucose | [glucose.md](glucose.md) | CGM readings and annotations |
| Flight Tracker | [flighttracker.md](flighttracker.md) | accounts, flights |
| Daily Tasks | [dailytasks.md](dailytasks.md) | leaderboard, optional sync |
| Meeting Cost Timer | [meetingtimer.md](meetingtimer.md) | weekly board |
| Track Timer | [tracktimer.md](tracktimer.md) | saved sessions |
| Reaction Test | [reactiontest.md](reactiontest.md) | weekly top ten |
| Face Breaker | [facebreaker.md](facebreaker.md) | weekly top ten |
| Pomodoro | [pomodoro.md](pomodoro.md) | none |
| Cribbage | [cribbage.md](cribbage.md) | none |
| Sign Spotter | [signspotter.md](signspotter.md) | none |

## Keeping these true

Unlike `api.md`, nothing checks these against the code. They describe intent
and behaviour, which changes far more slowly than the code does, but when you
change how an app works this is the file to update.

Endpoint-level detail belongs in a comment above the branch in `api.php`, not
here — that is what the generated reference is built from. These pages are for
what a single endpoint cannot tell you: how the parts of an app fit together
and why it behaves the way it does.
