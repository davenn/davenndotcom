# Confidence Pool

An NFL confidence pool. Each player picks every game of the week against the
spread and assigns each pick a confidence value; a correct pick earns its
confidence, a wrong one earns nothing. Highest total wins the week.

**File:** [`nflpool.html`](../../nflpool.html)
**Tables:** `cp_weeks` `cp_games` `cp_entries` `cp_picks` `cp_players` `cp_pending`
**Auth:** open for reading and entering picks; operator endpoints need the admin secret

## Getting picks in

This is the part the app exists for, and there are three routes:

1. **Fill the sheet in the app** — pick and rank every game by hand.
2. **Photograph a filled paper sheet** and upload it (`cp_scan`).
3. **Text a photo of the sheet in**, handled by `cp_sms` as a Twilio webhook.

Routes 2 and 3 both read the sheet with Claude, and neither saves anything
directly. The read result is staged in `cp_pending` behind a single-use
32-character token and the sender gets a link back to confirm or correct it
first. The reasoning is in the endpoint comment: a misread confidence number
costs more than a misread team name, and both happen.

Texting a sheet in maps a phone number to a player through `cp_players`. That
table is the one place a person is tied to a phone number, which is why the
roster endpoints are admin-guarded while nothing else in the pool is.

### Saving over an existing sheet

`cp_save_entry` upserts on `(week_id, player_name)`, so entering a name that
already has a sheet replaces its picks rather than creating a second entry.
That is intended — re-uploading after fixing a misread row is the normal way
to correct an entry.

The review screen warns before it happens: it names the existing entry, says
how many picks would be lost, and relabels the button `Replace picks`. It does
not block, because replacing is usually what the person means to do.

The match is **case-insensitive and trimmed**, deliberately. MySQL's default
collation is case-insensitive, so `dave` and `Dave` are the same row to the
database; matching exactly here would let a lower-cased name overwrite a sheet
with no warning at all. The message shows the spelling already stored rather
than the one just typed, so the collision is obvious.

## Weeks and scoring

A week is a row in `cp_weeks` holding the games and the rule for pushes:
`push_rule` is `void` (a push earns nothing) or `award` (a push earns its
confidence). It defaults to `void` and is set per week when the week is
created, because the answer depends on which pool you are running.

Scores come from ESPN's public, undocumented feed. It is not a supported API
and it breaks without warning, so the code tries more than one endpoint and
more than one user agent before giving up. When scores stop updating,
`cp_diag` probes the outbound path from the web host itself — the one thing
that cannot be checked from a laptop. It is admin-guarded because its reply
describes the server.

## Endpoints

Public: `cp_week` `cp_weeks` `cp_scan` `cp_save_week` `cp_save_entry`
`cp_sms` `cp_pending` (GET and DELETE) `cp_entry` (DELETE) `cp_week` (DELETE)
Operator: `cp_roster` (GET and DELETE) `cp_purge_photos` `cp_diag`

Operator endpoints are excluded from the public reference. Full detail in
[`../api.md`](../api.md).

## Front-end notes

- Polls for score updates while a week is in progress (`schedulePoll`).
- Remembers who you are in `localStorage['nflpool_player']`, so returning to
  the board does not mean re-identifying yourself.
- Team logos are keyed off abbreviations normalised in `cpTeamAbbr()`, which
  exists because the feed is not consistent about them.
- Deleting an entry is behind a long press plus a confirm — on a phone, next
  to a scrollable board, a single tap was too easy to hit by accident.
- Uploaded sheet photos accumulate; `cp_purge_photos` clears them.
