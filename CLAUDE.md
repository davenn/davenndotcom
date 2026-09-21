# davenn.com

A personal site hosting a dozen small self-contained web apps, backed by one PHP
file and one MySQL database, deployed by FTP to shared GoDaddy hosting.

There is **no build step, no framework, no package manager, and no `node_modules`**.
What is in the repo is byte-for-byte what is served. Do not introduce a bundler,
a transpiler, or a dependency manifest without being asked — the whole design
depends on a file being editable and deployable on its own.

## Architecture in one breath

Each app is a single `.html` file at the site root containing its own markup,
CSS and JS. Apps that need to persist anything call `api.php` — one 3,000-line
PHP script that serves **every** app — which talks to a single MySQL database.
Pushing to `main` FTPs the changed files to `/public_html/`.

```
Browser ──► /appname.html  (self-contained: HTML + CSS + JS)
                │
                └─ fetch ──► /api.php?action=<verb> ──► MySQL (one DB, prefixed tables)
                                      │
                                      └─► Anthropic API · Twilio · ESPN
```

## Layout

| Path | What it is |
|---|---|
| `index.html` | Home page — the app grid, plus the update-notification signup |
| `<app>.html` | One file per app; see the inventory below |
| `api.php` | The entire backend. Every app, every endpoint |
| `manifest-<app>.json` | PWA manifest, one per installable app |
| `sw.js` | Single service worker shared by all apps |
| `icons/` | Per-app SVG icons, plus 192/512 PNGs for older installers |
| `brand/` | RCS/SMS sender branding assets |
| `docs/` | `api.md` (generated reference) and public styled HTML doc pages |
| `tools/` | Repo tooling. Not deployed |
| `CHANGELOG.md` | **Load-bearing** — edits here text and email subscribers. See Deploy |
| `.github/workflows/deploy.yml` | Lint, FTP deploy, changelog notification |
| `.env` | Credentials. Never deployed (excluded in the workflow) |

## App inventory

| App | File(s) | Tables | Auth |
|---|---|---|---|
| Home / notify | `index.html`, `notify.html` | `subscribers` | — |
| Meeting Cost Timer | `meetingtimer.html` | `meetings` | — |
| Track Timer | `tracktimer.html` | `track_sessions` | — |
| Toolshare | `toolshare.html` | `tb_users` `tb_tools` `tb_requests` `tb_sessions` `tb_friendships` | `X-Auth-Token` |
| Flight Tracker | `flighttracker.html` | `ft_users` `ft_flights` | `X-Auth-Token` |
| Glucose | `glucose.html`, `bgcast.html` | `bg_readings` `bg_events` | `X-BG-Token` (read), `X-Admin-Secret` (ingest) |
| Confidence Pool | `nflpool.html` | `cp_weeks` `cp_games` `cp_entries` `cp_picks` `cp_players` `cp_pending` | `X-Admin-Secret` (admin only) |
| Daily Tasks | `dailytasks.html` | `dt_scores` `dt_tasks` | none for the leaderboard; `X-Auth-Token` (Toolshare accounts) for cross-device sync |
| Face Breaker | `facebreaker.html` | `fb_scores` | — |
| Reaction Test | `reactiontest.html` | `reaction_scores` | — |
| Pomodoro | `pomodoro.html`, `pomodoro-cast.html` | — | local only |
| Sign Spotter | `signspotter.html` | — | local only |
| Cribbage | `cribbage.html` | — | local only |
| Legal | `privacy.html`, `terms.html` | — | static |

## api.php

**A syntax error here takes down all twelve apps at once.** CI runs `php -l`
before deploying for exactly this reason. Never push PHP you have not linted.

- **Dispatch** is a flat sequence of guard clauses, not a router or a switch:
  ```php
  if ($method === 'GET' && $action === 'week') { ... exit; }
  ```
  `$action` comes from `?action=`. Each branch ends by echoing JSON and exiting.
  Add new endpoints in the same shape, grouped with their app's other branches.
- **Table prefixes** namespace each app: `tb_` Toolshare, `ft_` Flight Tracker,
  `bg_` glucose, `dt_` Daily Tasks, `cp_` Confidence Pool. Unprefixed tables
  (`meetings`, `track_sessions`, `subscribers`, `fb_scores`, `reaction_scores`)
  predate the convention — leave their names alone.
- **Schema is self-migrating.** Every request runs the `CREATE TABLE IF NOT
  EXISTS` block at the top of the file. To add a table, add it there. There are
  no migration files, so column *changes* to an existing table need a manual
  `ALTER` against the live DB — `CREATE TABLE IF NOT EXISTS` will not apply them.
- **Auth** is four unrelated schemes, by design:
  - `X-Auth-Token` → two separate implementations that share the header name:
    `authUser()` / `requireAuth()` resolves it against `tb_sessions` (Toolshare
    supports multiple sessions per user), while `ftAuthUser()` /
    `ftRequireAuth()` matches a single `token` column on `ft_users`. A Toolshare
    token is meaningless to Flight Tracker and vice versa. Daily Tasks'
    cross-device sync (`dt_get_tasks`, `dt_save_tasks`) reuses Toolshare
    accounts via `requireAuth()` — its leaderboard endpoints stay open
  - `X-BG-Token` → `bgRequireRead()`, a single shared read token for glucose
  - `X-Admin-Secret` → operator-only endpoints (ingest, notify, roster, purge)
  - none → the leaderboard and timer apps write unauthenticated
- **Endpoint comments already exist** above the non-obvious branches and explain
  intent, not mechanics. Read them before changing a branch, and keep the habit.

**Diagnostic endpoints get the admin guard too.** `cp_diag` returns
`PHP_VERSION`, `curl_version`, `open_basedir` and `SERVER_ADDR`. It holds no
credentials and no user data, which is why it was originally left open — but
that combination is reconnaissance, so it is admin-gated like the other
operator endpoints. Apply the same reasoning to anything new that reports on
the host rather than on the apps.

## Documentation

Docs come in two tiers, split by audience:

- **In the repo, for building.** `CLAUDE.md`, `docs/api.md` and
  [`docs/apps/`](docs/apps/) — one page per app, covering what a single
  endpoint cannot: how an app's parts fit together and why it behaves as it
  does. Read on GitHub or in an editor, never deployed. On GitHub the
  per-endpoint source links resolve to real lines in `api.php`, which is why
  it is the better surface for `api.md`.
- **On the site, for using.** `docs/*.html`, deployed, reachable from the
  `Docs` nav link → [`docs/index.html`](docs/index.html), which is the hub.

Both API references are **generated** from `api.php` by one script — do not
edit either by hand. Regenerate after touching `api.php`:

```
node tools/gen-api-docs.mjs          # rewrite docs/api.md and docs/api.html
node tools/gen-api-docs.mjs --check  # exit 1 if either is stale (what CI runs)
```

`docs/api.md` and `docs/api.html` come from a single parse, so the two cannot
drift from each other. Output must stay byte-identical between runs — never
put a timestamp or anything environment-dependent in it, or `--check` becomes
meaningless.

`docs/index.html`, `docs/architecture.html` and `docs/glucose-api.html` are
hand-written. When adding a hand-written doc page, link it from the hub and add
it to `sw.js`.

Nothing checks `docs/apps/` against the code, so when you change how an app
works, that is the file to update. Endpoint-level detail does not belong
there — it goes in a comment above the branch in `api.php`, which is what the
generated reference is built from.

### What the public pages deliberately leave out

`docs/architecture.html` is the public counterpart of this file, and it is
hand-written rather than derived from it **on purpose**: a redaction script
cannot judge new content, so anything added here would leak the moment it was
generated. Keeping the two separate means every public sentence was chosen.
When the architecture here changes, update that page by hand.

It omits, and should keep omitting: environment variable names, the
operator-secret header, which endpoints accept unauthenticated writes, where
credentials live and how they are loaded, deploy mechanics and server paths,
and the fact that one bad parse takes the whole API down.

`docs/api.html` omits every endpoint where `isOperator()` in the generator
returns true — anything admin-gated, plus anything named in the
`OPERATOR_ACTIONS` escape hatch. `docs/api.md` stays complete.

`OPERATOR_ACTIONS` is empty, and that is the healthy state. Needing an entry
means an endpoint is exposed in a way the code does not express — fix the
endpoint rather than hiding it from the page.

The generator parses the dispatch branches, the `CREATE TABLE` block, and **the
comment block directly above each branch**, which is where endpoint
descriptions come from. So document an endpoint by writing a comment next to
the code, not by editing Markdown. Roughly two thirds of the endpoints have one
today; the rest are listed under "Not yet described" at the bottom of the
generated file, which is the to-do list.

Because the parser relies on the file's shape, keep new branches in the exact
existing form — `if (...) {` starting at column 0, closing `}` at column 0.

The staleness check is its own CI job and does **not** gate the deploy: a stale
doc marks the commit red but never blocks a production fix.

## Front-end conventions

Every app file follows the same shape. Match it when adding or editing one.

- **Theme stamp before first paint** — an inline IIFE in `<head>` reads
  `localStorage['<app>_theme']`, falls back to `prefers-color-scheme`, and sets
  `data-theme` on `<html>`. This runs before any CSS so there is no flash of the
  wrong theme. Each app uses its own storage key.
- **Shared design tokens** — `--page`, `--surface`, `--surface-2`, `--border`,
  `--text-primary`, `--text-secondary`, `--text-muted`, defined under
  `:root, [data-theme="light"]` and redefined under `[data-theme="dark"]`.
  Status colors are deliberately *not* themed and are always paired with a word,
  so red/green distinctions survive colorblind vision.
- **DM Mono** from Google Fonts, preconnected, is the house typeface.
- **API base** is inconsistent across apps — some use `/api.php`, some the
  absolute `https://davenn.com/api.php`. Both work. Follow whatever the file you
  are editing already does rather than normalizing it as a drive-by.

## PWA

An installable app needs three things in sync: `manifest-<app>.json`, an entry
in `icons/`, and **its path added to the `SHELL` array in `sw.js`**. Forgetting
the third is the usual bug — the app installs but will not open offline.

When changing anything cached, bump the `CACHE` constant in `sw.js`
(`davenn-v16` → `v17`) or clients keep serving the old assets. HTML is
network-first (always fresh, cache as offline fallback), everything else is
cache-first, and `/api.php` is never intercepted.

`SHELL` entries must be **real file paths**. `addAll()` rejects as a whole if
any one URL fails, so a directory URL like `/docs/` that depends on a server
index rule can silently leave every app uncached. Link to `/docs/` in markup;
precache `/docs/index.html`.

## Deploy

Push to `main` and that is the deploy. The workflow:

1. `php -l` every tracked `.php` file — a failure blocks the deploy
2. FTP **only the changed files** to `/public_html/`, excluding `.env`,
   `uploads/`, and git metadata
3. If the push changed `CHANGELOG.md`, POST the topmost `## ` entry to
   `?action=notify_subscribers`, which **texts and emails every subscriber**

**Consequence:** editing `CHANGELOG.md` sends real SMS to real people. Only add
an entry when an update is genuinely worth announcing, keep it SMS-short, and
add it at the top. Everything under the newest `## YYYY-MM-DD` heading goes out
verbatim.

The trigger is `git diff --name-only … | grep -qx 'CHANGELOG.md'`, so **any**
change to that file fires a send — including editing a comment or fixing a
typo in an old entry. It does not compare entries. A touch-up to the file
therefore re-sends whatever is currently at the top. Do not edit it for
anything other than announcing something.

Keep entries plain ASCII. An em dash, curly quote or emoji forces the text
into UCS-2, cutting a segment from 160 characters to 70 and roughly doubling
what the send costs. The body of an update text is the changelog entry
verbatim.

Per the owner's standing rule, **do not `git commit` unless asked for that
specific commit** — on this repo a commit to `main` is a production deploy.

## Configuration

`.env` sits beside `api.php` on the server and is parsed by hand at the top of
the file (not a library). It is excluded from deploys, so **server-side changes
must be made on the host** — pushing will not update it.

`DB_HOST` `DB_NAME` `DB_USER` `DB_PASS` · `UPLOAD_DIR` `UPLOAD_URL` ·
`MAIL_FROM` `MAIL_FROM_NAME` `MAIL_REPLY_TO` `APP_URL` ·
`TWILIO_ACCOUNT_SID` `TWILIO_AUTH_TOKEN` `TWILIO_FROM_NUMBER` ·
`ADMIN_SECRET` `BG_READ_TOKEN` `BG_TIMEZONE`

`ANTHROPIC_API_KEY` is read by `api.php` but is **not** in the local `.env` —
it exists only in the server copy. Vision features will fail when testing
against a local env file.

## External services

- **Anthropic API** — Toolshare's `tb_identify_tool` (photo → tool details) and
  Confidence Pool's `cp_scan` (photo of a filled pick sheet → picks). Uses
  `claude-opus-5` and `claude-haiku-4-5-20251001`.
- **Twilio** — outbound SMS for subscriber notifications; inbound webhook at
  `?action=cp_sms` for Confidence Pool pick submission by text.
- **ESPN** (`site.api.espn.com`, `cdn.espn.com`) — unofficial, unauthenticated
  scores and schedule feed for Confidence Pool. It breaks without notice;
  `?action=cp_diag` probes the outbound path from the web host itself.

## Rules of thumb

- Keep each app file self-contained. Resist extracting shared CSS or JS into a
  common file — independent deployability is the point of this architecture.
- Add backend behavior to `api.php` as a new guard clause; do not split the file.
- Lint PHP locally before pushing.
- Bump the `sw.js` cache version whenever cached assets change.
- Treat `CHANGELOG.md` as a send button.
