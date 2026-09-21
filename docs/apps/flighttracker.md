# Flight Tracker

A personal log of flights taken, shown as a map, a set of statistics and a
timeline.

**File:** [`flighttracker.html`](../../flighttracker.html)
**Tables:** `ft_users` `ft_flights`
**Auth:** account, `X-Auth-Token`

## Sessions differ from Toolshare

Worth knowing before changing anything here: the token lives in a `token`
column on `ft_users`, not in a sessions table.

One consequence follows from that and it is not a bug: **signing in issues a
fresh token over the top of the old one, so signing in anywhere signs out
wherever you were before.** One device at a time is all this app has needed.
Signing out simply nulls the column.

Toolshare does the opposite with `tb_sessions` — see
[toolshare.md](toolshare.md). If this app ever needs multiple devices, that is
the pattern to copy rather than improvise.

## Flights

A flight is two airport codes, a date, and optional airline, seat class and
flight number. Codes are upper-cased on the way in. A flight that lands where
it started is rejected rather than stored as a curiosity.

Deletion is scoped by owner inside the `WHERE` clause rather than checked
first, so a guessed id belonging to someone else matches nothing and answers
404 — the row is never at risk and the response does not confirm it exists.

## Bulk import

`ft_add_flights` takes a list, parsed client-side from CSV. Rows that fail
validation are **skipped rather than failing the batch**, and the reply counts
what landed. An import of fifty flights is still worth keeping when two lines
are malformed, and the count lets the app show the shortfall.

So a successful response does not mean everything was imported. Compare
`inserted` against what was sent.

## Endpoints

`ft_register` `ft_login` `ft_logout` `ft_flights` `ft_add_flight`
`ft_add_flights` `ft_delete_flight`

Full detail in [`../api.md`](../api.md).

## Front-end notes

- Three views: Map, Stats, Timeline.
- Distances use the haversine formula client-side; nothing is stored.
- Airport lookup is a client-side autocomplete over a bundled list, so adding
  a flight does not depend on a third-party geocoder.
- Token and user cached in `localStorage` as `ft_token` and `ft_user`.
- The page title is just "Flights", unlike the other apps' "Name — davenn.com".
