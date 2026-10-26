# API reference

<!-- Generated from api.php by tools/gen-api-docs.mjs. Do not edit by hand.
     Endpoint descriptions come from the comment block above each branch in
     api.php — edit them there and regenerate. -->

Every app on davenn.com is served by a single `api.php`. Endpoints are selected
by an `action` query parameter and always return JSON.

```
GET|POST|DELETE  /api.php?action=<action>
```

**91 endpoints · 37 tables**

## Endpoints at a glance

| Action | Method | App | Auth |
|---|---|---|---|
| [`clear_week`](#delete-actionclear_week) | DELETE | Meeting Cost Timer | None |
| [`save`](#post-actionsave) | POST | Meeting Cost Timer | None |
| [`week`](#get-actionweek) | GET | Meeting Cost Timer | None |
| [`clear_track_sessions`](#delete-actionclear_track_sessions) | DELETE | Track Timer | None |
| [`delete_track_session`](#delete-actiondelete_track_session) | DELETE | Track Timer | None |
| [`save_track_session`](#post-actionsave_track_session) | POST | Track Timer | None |
| [`track_sessions`](#get-actiontrack_sessions) | GET | Track Timer | None |
| [`tb_accept_invite`](#post-actiontb_accept_invite) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_add_tool`](#post-actiontb_add_tool) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_delete_tool`](#delete-actiontb_delete_tool) | DELETE | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_edit_tool`](#post-actiontb_edit_tool) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_friends`](#get-actiontb_friends) | GET | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_identify_tool`](#post-actiontb_identify_tool) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_invite_preview`](#get-actiontb_invite_preview) | GET | Toolshare | None |
| [`tb_login`](#post-actiontb_login) | POST | Toolshare | None |
| [`tb_logout`](#post-actiontb_logout) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_my_invite`](#get-actiontb_my_invite) | GET | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_my_tools`](#get-actiontb_my_tools) | GET | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_register`](#post-actiontb_register) | POST | Toolshare | None |
| [`tb_remove_friend`](#delete-actiontb_remove_friend) | DELETE | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_request`](#post-actiontb_request) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_request_count`](#get-actiontb_request_count) | GET | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_requests`](#get-actiontb_requests) | GET | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_respond_request`](#post-actiontb_respond_request) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_return_tool`](#post-actiontb_return_tool) | POST | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_tag_suggestions`](#get-actiontb_tag_suggestions) | GET | Toolshare | Toolshare account — `X-Auth-Token` |
| [`tb_tools`](#get-actiontb_tools) | GET | Toolshare | Toolshare account — `X-Auth-Token` |
| [`ft_add_flight`](#post-actionft_add_flight) | POST | Flight Tracker | Flight Tracker account — `X-Auth-Token` |
| [`ft_add_flights`](#post-actionft_add_flights) | POST | Flight Tracker | Flight Tracker account — `X-Auth-Token` |
| [`ft_delete_flight`](#delete-actionft_delete_flight) | DELETE | Flight Tracker | Flight Tracker account — `X-Auth-Token` |
| [`ft_flights`](#get-actionft_flights) | GET | Flight Tracker | Flight Tracker account — `X-Auth-Token` |
| [`ft_login`](#post-actionft_login) | POST | Flight Tracker | None |
| [`ft_logout`](#post-actionft_logout) | POST | Flight Tracker | Flight Tracker account — `X-Auth-Token` |
| [`ft_register`](#post-actionft_register) | POST | Flight Tracker | None |
| [`bg_daily`](#get-actionbg_daily) | GET | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`bg_embed`](#get-actionbg_embed) | GET | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`bg_event_delete`](#delete-actionbg_event_delete) | DELETE | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`bg_event_save`](#post-actionbg_event_save) | POST | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`bg_events`](#get-actionbg_events) | GET | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`bg_history`](#get-actionbg_history) | GET | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`bg_ingest`](#post-actionbg_ingest) | POST | Glucose | Admin — `X-Admin-Secret` |
| [`bg_latest`](#get-actionbg_latest) | GET | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`bg_refresh`](#post-actionbg_refresh) | POST | Glucose | Glucose read token — `X-BG-Token` or `?token=` |
| [`cp_diag`](#get-actioncp_diag) | GET | Confidence Pool | Admin — `X-Admin-Secret` |
| [`cp_entry`](#delete-actioncp_entry) | DELETE | Confidence Pool | None |
| [`cp_pending`](#delete-actioncp_pending) | DELETE | Confidence Pool | Single-use link token — `?token=` |
| [`cp_pending`](#get-actioncp_pending) | GET | Confidence Pool | Single-use link token — `?token=` |
| [`cp_purge_photos`](#post-actioncp_purge_photos) | POST | Confidence Pool | Admin — `X-Admin-Secret` |
| [`cp_roster`](#delete-actioncp_roster) | DELETE | Confidence Pool | Admin — `X-Admin-Secret` |
| [`cp_roster`](#get-actioncp_roster) | GET | Confidence Pool | Admin — `X-Admin-Secret` |
| [`cp_save_entry`](#post-actioncp_save_entry) | POST | Confidence Pool | None |
| [`cp_save_week`](#post-actioncp_save_week) | POST | Confidence Pool | None |
| [`cp_scan`](#post-actioncp_scan) | POST | Confidence Pool | None |
| [`cp_sms`](#post-actioncp_sms) | POST | Confidence Pool | None |
| [`cp_week`](#delete-actioncp_week) | DELETE | Confidence Pool | None |
| [`cp_week`](#get-actioncp_week) | GET | Confidence Pool | None |
| [`cp_weeks`](#get-actioncp_weeks) | GET | Confidence Pool | None |
| [`xc_match`](#post-actionxc_match) | POST | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_meet`](#delete-actionxc_meet) | DELETE | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_meet`](#get-actionxc_meet) | GET | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_meets`](#get-actionxc_meets) | GET | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_milesplit`](#post-actionxc_milesplit) | POST | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_results`](#get-actionxc_results) | GET | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_save_meet`](#post-actionxc_save_meet) | POST | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_scan`](#post-actionxc_scan) | POST | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`xc_schools`](#get-actionxc_schools) | GET | WildcatsXC | WildcatsXC team PIN — `X-XC-PIN` |
| [`dt_get_tasks`](#get-actiondt_get_tasks) | GET | Daily Tasks | Toolshare account — `X-Auth-Token` |
| [`dt_save`](#post-actiondt_save) | POST | Daily Tasks | None |
| [`dt_save_tasks`](#post-actiondt_save_tasks) | POST | Daily Tasks | Toolshare account — `X-Auth-Token` |
| [`dt_week`](#get-actiondt_week) | GET | Daily Tasks | None |
| [`fb_leaderboard`](#get-actionfb_leaderboard) | GET | Face Breaker | None |
| [`fb_save_score`](#post-actionfb_save_score) | POST | Face Breaker | None |
| [`clear_reaction_week`](#delete-actionclear_reaction_week) | DELETE | Reaction Test | None |
| [`reaction_week`](#get-actionreaction_week) | GET | Reaction Test | None |
| [`save_reaction`](#post-actionsave_reaction) | POST | Reaction Test | None |
| [`ml_add`](#post-actionml_add) | POST | Movie List | None |
| [`ml_delete`](#post-actionml_delete) | POST | Movie List | None |
| [`ml_draft`](#post-actionml_draft) | POST | Movie List | None |
| [`ml_movies`](#get-actionml_movies) | GET | Movie List | None |
| [`ml_watch`](#post-actionml_watch) | POST | Movie List | None |
| [`delete_subscriber`](#post-actiondelete_subscriber) | POST | Update Notifications | Admin — `X-Admin-Secret` |
| [`notify_subscribers`](#post-actionnotify_subscribers) | POST | Update Notifications | Admin — `X-Admin-Secret` |
| [`resetTestPhone`](#get-actionresettestphone) | GET | Update Notifications | None |
| [`sms_diag`](#get-actionsms_diag) | GET | Update Notifications | Admin — `X-Admin-Secret` |
| [`sms_diag`](#post-actionsms_diag) | POST | Update Notifications | Admin — `X-Admin-Secret` |
| [`subscribe`](#post-actionsubscribe) | POST | Update Notifications | None |
| [`unsubscribe`](#get-actionunsubscribe) | GET | Update Notifications | Single-use link token — `?token=` |
| [`an_fixed_cost`](#delete-actionan_fixed_cost) | DELETE | Analytics | Admin — `X-Admin-Secret` |
| [`an_fixed_cost`](#post-actionan_fixed_cost) | POST | Analytics | Admin — `X-Admin-Secret` |
| [`an_hit`](#post-actionan_hit) | POST | Analytics | None |
| [`an_stats`](#get-actionan_stats) | GET | Analytics | Admin — `X-Admin-Secret` |

## Meeting Cost Timer

### DELETE `?action=clear_week`

Answers with the number of rows removed, so the caller can tell a cleared
week from a week_key that matched nothing.

- **Auth:** None
- **Takes:** &week_key=YYYY-MM-DD — drop one week's meetings.
- **Query parameters:** `week_key`
- **Source:** [`api.php:658`](../api.php#L658)

### POST `?action=save`

Title, cost and length must all be present and positive. A meeting with no
cost is a timer that was never really started, and keeping it only adds noise
to the week.

- **Auth:** None
- **Takes:** body: { title, cost, seconds, week_key }
- **Source:** [`api.php:641`](../api.php#L641)

### GET `?action=week`

Cost is the entire point of the app, so the ranking leads with it.

- **Auth:** None
- **Takes:** &week_key=YYYY-MM-DD — one week's meetings, dearest first.
- **Query parameters:** `week_key`
- **Source:** [`api.php:627`](../api.php#L627)

## Track Timer

### DELETE `?action=clear_track_sessions`

Not scoped to a week or a user: it empties the table.

- **Auth:** None
- **Takes:** remove every saved session.
- **Source:** [`api.php:708`](../api.php#L708)

### DELETE `?action=delete_track_session`

- **Auth:** None
- **Takes:** &id=X — remove a single session.
- **Query parameters:** `id`
- **Source:** [`api.php:698`](../api.php#L698)

### POST `?action=save_track_session`

Athletes are stored as given — the app owns their shape, not the database.

- **Auth:** None
- **Takes:** body: { name, duration, athletes[] }
- **Source:** [`api.php:684`](../api.php#L684)

### GET `?action=track_sessions`

Athletes ride along as a JSON blob and are decoded on the way out: the
roster changes every session, so there is nothing stable to make columns of.

- **Auth:** None
- **Takes:** every saved session, newest first.
- **Source:** [`api.php:675`](../api.php#L675)

## Toolshare

### POST `?action=tb_accept_invite`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** body: { invite_code }
- **Source:** [`api.php:1181`](../api.php#L1181)

### POST `?action=tb_add_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** (multipart for photo upload OR JSON)
- **Source:** [`api.php:817`](../api.php#L817)

### DELETE `?action=tb_delete_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:956`](../api.php#L956)

### POST `?action=tb_edit_tool`

Multipart, owner only. A new photo replaces the old one and the old file is
deleted; sending no photo keeps the current one.

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:843`](../api.php#L843)

### GET `?action=tb_friends`

each of them has. Friendships are stored undirected, so the query checks both
columns and takes whichever side is not you.

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** everyone this user shares with, and how many tools
- **Source:** [`api.php:1201`](../api.php#L1201)

### POST `?action=tb_identify_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** vision-based auto-fill via Claude
- **Source:** [`api.php:873`](../api.php#L873)

### GET `?action=tb_invite_preview`

- **Auth:** None
- **Takes:** &code=xxx  (no auth required)
- **Query parameters:** `code`
- **Source:** [`api.php:1170`](../api.php#L1170)

### POST `?action=tb_login`

- **Auth:** None
- **Takes:** body: { username, password }
- **Source:** [`api.php:754`](../api.php#L754)

### POST `?action=tb_logout`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** ends only this device's session, other devices stay signed in
- **Source:** [`api.php:772`](../api.php#L772)

### GET `?action=tb_my_invite`

Codes are created lazily, so an account that never shares anything never
carries one.

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** this user's invite code, minted on first request.
- **Source:** [`api.php:1163`](../api.php#L1163)

### GET `?action=tb_my_tools`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** current user's tools only
- **Source:** [`api.php:799`](../api.php#L799)

### POST `?action=tb_register`

Signs the new account straight in and returns its token — registering and
then being asked to log in is a step with no purpose. A taken username is the
only way the insert can fail, so that is what the conflict reports.

- **Auth:** None
- **Takes:** body: { username, display_name, email, password }
- **Source:** [`api.php:721`](../api.php#L721)

### DELETE `?action=tb_remove_friend`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:1216`](../api.php#L1216)

### POST `?action=tb_request`

Only friends of the owner may ask. A stranger's tool answers exactly like a
missing one, so sequential ids cannot be walked to email every owner.

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** create borrow request + email owner
- **Source:** [`api.php:979`](../api.php#L979)

### GET `?action=tb_request_count`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** badge count of pending incoming requests
- **Source:** [`api.php:1042`](../api.php#L1042)

### GET `?action=tb_requests`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** inbox (owner) + outbox (requester) for current user
- **Source:** [`api.php:1014`](../api.php#L1014)

### POST `?action=tb_respond_request`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X  body: { status: "approved"\|"declined" }
- **Query parameters:** `id`
- **Source:** [`api.php:1050`](../api.php#L1050)

### POST `?action=tb_return_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X  (request id) — mark tool as returned
- **Query parameters:** `id`
- **Source:** [`api.php:1092`](../api.php#L1092)

### GET `?action=tb_tag_suggestions`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** unique tags from visible tools
- **Source:** [`api.php:1140`](../api.php#L1140)

### GET `?action=tb_tools`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** friends-gated community view
- **Source:** [`api.php:1226`](../api.php#L1226)

## Flight Tracker

### POST `?action=ft_add_flight`

body: { from_code, to_code, from_city, to_city, flight_date,
        airline?, seat_class?, flight_number? }
Airport codes are upper-cased on the way in, and a flight that lands where it
started is refused rather than stored as a curiosity.

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Source:** [`api.php:1446`](../api.php#L1446)

### POST `?action=ft_add_flights`

Rows that fail validation are skipped instead of failing the batch, and the
reply counts what landed. An import of fifty flights is still worth keeping
when two lines are malformed, and the caller can see the shortfall.

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Takes:** body: { flights: [ … ] } — bulk import.
- **Source:** [`api.php:1472`](../api.php#L1472)

### DELETE `?action=ft_delete_flight`

Ownership is part of the WHERE clause, so a guessed id belonging to someone
else matches nothing and answers 404 rather than deleting their row.

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Takes:** &id=X — remove one of this user's flights.
- **Query parameters:** `id`
- **Source:** [`api.php:1501`](../api.php#L1501)

### GET `?action=ft_flights`

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Takes:** this user's flights, most recent first.
- **Source:** [`api.php:1434`](../api.php#L1434)

### POST `?action=ft_login`

Issues a fresh token over the top of the old one, so signing in here signs
out whichever device was signed in before.

- **Auth:** None
- **Takes:** body: { username, password }
- **Source:** [`api.php:1410`](../api.php#L1410)

### POST `?action=ft_logout`

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Takes:** clears the stored token.
- **Source:** [`api.php:1427`](../api.php#L1427)

### POST `?action=ft_register`

The token lives on the user row rather than in a sessions table, unlike
Toolshare — one signed-in device at a time is all this app has needed.

- **Auth:** None
- **Takes:** body: { username, display_name, password }
- **Source:** [`api.php:1383`](../api.php#L1383)

## Glucose

### GET `?action=bg_daily`

This is the shape the streak calendar and time-in-range bars want; the
aggregation happens in SQL so a year of history stays a small response.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…&days=30&low=70&high=180 → one row per local day.
- **Query parameters:** `days`, `low`, `high`
- **Source:** [`api.php:2095`](../api.php#L2095)

### GET `?action=bg_embed`

mgdl 0 means nothing is stored yet. Trend follows Dexcom's own ordering, so a
device can index an arrow glyph straight off it:
  0 unknown · 1 up-up · 2 up · 3 up-45 · 4 flat · 5 down-45 · 6 down · 7 down-down

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Query parameters:** `low`, `high`, `spark`
- **Source:** [`api.php:2005`](../api.php#L2005)

### DELETE `?action=bg_event_delete`

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &id=…&token=…
- **Query parameters:** `id`
- **Source:** [`api.php:2294`](../api.php#L2294)

### POST `?action=bg_event_save`

body: { id?, start_at, end_at?, tag, note? }
Creates, or updates when id is given. Returns the stored row.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…
- **Source:** [`api.php:2219`](../api.php#L2219)

### GET `?action=bg_events`

Read token: anything that may read the readings may read what explains them.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…&hours=24 → annotations overlapping the window.
- **Query parameters:** `hours`
- **Source:** [`api.php:2184`](../api.php#L2184)

### GET `?action=bg_history`

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…&hours=24&low=70&high=180 → raw readings + summary.
- **Query parameters:** `hours`, `low`, `high`
- **Source:** [`api.php:2048`](../api.php#L2048)

### POST `?action=bg_ingest`

body: { readings: [ { at: ISO8601, mgdl: int, trend: string|null } ] }
Idempotent — the poller deliberately re-sends an overlapping window, and
replaying it just refreshes rows already stored.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret
- **Source:** [`api.php:1873`](../api.php#L1873)

### GET `?action=bg_latest`

minutes_ago is what a display should use to decide it has gone stale: show
the age, and never present an old number as though it were current.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…  → the newest stored reading.
- **Source:** [`api.php:1980`](../api.php#L1980)

### POST `?action=bg_refresh`

Asks the poller to pull from Dexcom now, so a manual refresh reflects live
data rather than whatever was last stored. The admin secret stays here on the
server — the page only ever holds the read-only token, which is why this is a
proxy rather than the browser calling the poller directly.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…
- **Source:** [`api.php:1928`](../api.php#L1928)

## Confidence Pool

### GET `?action=cp_diag`

Admin-guarded despite holding no credentials or user data: the reply names
the PHP and curl versions, open_basedir and the server's own address, and
that combination is worth more to someone scanning for a way in than it is
to anyone else. Nothing in the site calls this — it is run by hand.

- **Auth:** Admin — `X-Admin-Secret`
- **Query parameters:** `season`, `week`
- **Source:** [`api.php:3557`](../api.php#L3557)

### DELETE `?action=cp_entry`

- **Auth:** None
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:3617`](../api.php#L3617)

### DELETE `?action=cp_pending`

- **Auth:** Single-use link token — `?token=`
- **Takes:** &token=… — drop a staged sheet once it is saved.
- **Query parameters:** `token`
- **Source:** [`api.php:3480`](../api.php#L3480)

### GET `?action=cp_pending`

- **Auth:** Single-use link token — `?token=`
- **Takes:** &token=… — collect a staged sheet for review.
- **Query parameters:** `token`
- **Source:** [`api.php:3458`](../api.php#L3458)

### POST `?action=cp_purge_photos`

One-shot cleanup for sheet photos written before the app stopped keeping
them. Nothing creates these files any more, so this should report 0 on every
run after the first.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret
- **Source:** [`api.php:3524`](../api.php#L3524)

### DELETE `?action=cp_roster`

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** &id=…  header: X-Admin-Secret — unlink a number.
- **Query parameters:** `id`
- **Source:** [`api.php:3509`](../api.php#L3509)

### GET `?action=cp_roster`

Admin-guarded because this is the one table that maps a person to a phone
number, which is the most sensitive thing the pool holds.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret — who is linked to what number.
- **Source:** [`api.php:3492`](../api.php#L3492)

### POST `?action=cp_save_entry`

- **Auth:** None
- **Takes:** {season, week, player_name, picks:[{game_id,pick,confidence}]}
- **Source:** [`api.php:3067`](../api.php#L3067)

### POST `?action=cp_save_week`

Creates the week, or updates the spreads on one that already has entries.

- **Auth:** None
- **Takes:** {season, week, push_rule, games:[{away,home,favorite,spread}]}
- **Source:** [`api.php:2976`](../api.php#L2976)

### POST `?action=cp_scan`

Reads a filled-in sheet. Saves no picks — the app shows the result for
correction first, because a misread confidence number costs more than a
misread team name and both happen.

- **Auth:** None
- **Takes:** (multipart: photo, optional season + week)
- **Query parameters:** `season`, `week`
- **Source:** [`api.php:2923`](../api.php#L2923)

### POST `?action=cp_sms`

- **Auth:** None
- **Takes:** Twilio inbound webhook.
- **Source:** [`api.php:3314`](../api.php#L3314)

### DELETE `?action=cp_week`

- **Auth:** None
- **Takes:** &season=&week=
- **Query parameters:** `season`, `week`
- **Source:** [`api.php:3626`](../api.php#L3626)

### GET `?action=cp_week`

- **Auth:** None
- **Takes:** &season=&week=
- **Query parameters:** `season`, `week`, `force`
- **Source:** [`api.php:3139`](../api.php#L3139)

### GET `?action=cp_weeks`

- **Auth:** None
- **Takes:** every week that has been set up, newest first.
- **Source:** [`api.php:3602`](../api.php#L3602)

## WildcatsXC

### POST `?action=xc_match`

Lines rows the app parsed itself — results pasted from a site such as
MileSplit — up against the stored athletes, exactly as xc_scan does for a
read sheet, so a paste gets the same known / new / "same as" tags. Saves
nothing. The parsing stays in the browser: pasted text is regular enough
that a model read would only add cost and minutes.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** {rows:[{race, distance_m, place, name, grade, school, time}]}
- **Source:** [`api.php:4081`](../api.php#L4081)

### DELETE `?action=xc_meet`

results anywhere go with it.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** &id=X — the meet and its results; runners left with no
- **Query parameters:** `id`
- **Source:** [`api.php:4447`](../api.php#L4447)

### GET `?action=xc_meet`

xc_scan returns, so the app edits a saved meet with the grid it checks a scan in.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** &id=X — one meet with every result, in the same row shape
- **Query parameters:** `id`
- **Source:** [`api.php:4417`](../api.php#L4417)

### GET `?action=xc_meets`

holds and how many came from each school (`schools`: name → count), so the
app can show just the meets the coach's own school ran. Also what the app
calls to check a PIN when it is first entered.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** every meet, newest first, with how many runners each
- **Source:** [`api.php:4336`](../api.php#L4336)

### POST `?action=xc_milesplit`

Imports one MileSplit meet's results from a link a coach pasted. Only ever
runs because a person pasted a link and pressed Import — never on a
schedule, never following links to other meets. Two requests: the results
page (meet name, date, which results files exist) and the performances data
that page itself loads. Files MileSplit marks as PRO-only are skipped, not
fetched. The link's own event / gender / division filters choose the race;
a link without them imports every race. Saves nothing — the rows come back
in xc_scan's shape for checking.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** {url}
- **Source:** [`api.php:4140`](../api.php#L4140)

### GET `?action=xc_results`

date order, for the Team tab to arrange. `school[]=A&school[]=B` (up to 40)
returns several at once, each row carrying its school — what the
Conference and Section tabs ask for. Flat rather than pre-arranged: how
rows are grouped (boys and girls, which distance, top ten per school) is a
view choice that belongs in the browser.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** &school=X — every result for one school, flat and in
- **Query parameters:** `school`
- **Source:** [`api.php:4384`](../api.php#L4384)

### POST `?action=xc_save_meet`

With meet_id, the rows replace that meet's results — the edit path, where a
renamed runner must not leave their old row behind. Without one, a meet with
the same name and date is added to rather than duplicated, so a sheet's
second page can be scanned later; a runner already in it is updated.
A race under 5000 m is saved as 5K: each time becomes its xcTo5k() equivalent
and the distance 5000, so a 3K or 2-mile meet lands on the same trend line.
The original distance and times are not kept. Under 1000 m is refused as a
typo (3 for 3000) rather than turned into an absurd 5K time.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** {meet_id?, name, date, location, rows:[{race, distance_m, place, name, grade, school, time}]}
- **Source:** [`api.php:4244`](../api.php#L4244)

### POST `?action=xc_scan`

Reads a results PDF. PDFs only, by the owner's choice: results come in as a
PDF, a results link (xc_milesplit) or pasted text (xc_match), never a
photo. Saves nothing — the app shows every row for checking
first. Rows come back flat, each carrying its race, and already lined up
against the stored athletes: an exact name + school match takes the stored
spelling, and a new name close to an existing teammate's carries that name
as `similar` for the coach to accept or ignore. The files are never written
to disk. With `page` (and a single PDF), reads just that page — how the app
reads a PDF, a page per request, so no request runs long enough for the
host to kill it. Every reply carries page_count.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** (multipart: files[] — PDFs; optional schools, page)
- **Source:** [`api.php:3970`](../api.php#L3970)

### GET `?action=xc_schools`

counts, most results first. Feeds the team picker on the Team tab.

- **Auth:** WildcatsXC team PIN — `X-XC-PIN`
- **Takes:** every school on record with its runner and result
- **Source:** [`api.php:4365`](../api.php#L4365)

## Daily Tasks

### GET `?action=dt_get_tasks`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &date=YYYY-MM-DD — load this user's task list for a given day
- **Query parameters:** `date`
- **Source:** [`api.php:1548`](../api.php#L1548)

### POST `?action=dt_save`

- **Auth:** None
- **Takes:** body: { name, task_count, total_seconds, week_key }
- **Source:** [`api.php:1529`](../api.php#L1529)

### POST `?action=dt_save_tasks`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** body: { date, tasks, next_id } — upsert this user's task list for a given day
- **Source:** [`api.php:1568`](../api.php#L1568)

### GET `?action=dt_week`

Ranked by tasks finished, then by time taken: doing more wins, and doing the
same amount faster settles the tie.

- **Auth:** None
- **Takes:** &week_key=YYYY-MM-DD — the week's leaderboard.
- **Query parameters:** `week_key`
- **Source:** [`api.php:1518`](../api.php#L1518)

## Face Breaker

### GET `?action=fb_leaderboard`

- **Auth:** None
- **Takes:** top 10 for the current ISO week (no auth)
- **Source:** [`api.php:1256`](../api.php#L1256)

### POST `?action=fb_save_score`

- **Auth:** None
- **Takes:** body: { name, score } — no auth required
- **Source:** [`api.php:1266`](../api.php#L1266)

## Reaction Test

### DELETE `?action=clear_reaction_week`

- **Auth:** None
- **Takes:** &week_key=YYYY-MM-DD — reset one week.
- **Query parameters:** `week_key`
- **Source:** [`api.php:1328`](../api.php#L1328)

### GET `?action=reaction_week`

- **Auth:** None
- **Takes:** &week_key=YYYY-MM-DD — the week's ten fastest.
- **Query parameters:** `week_key`
- **Source:** [`api.php:1293`](../api.php#L1293)

### POST `?action=save_reaction`

Only a top-ten time is kept. A slower one comes back as success:false with a
200, not an error — missing the board is an ordinary outcome of playing, and
the app should be able to say so without dressing it up as a failure.

- **Auth:** None
- **Takes:** body: { name, avg_ms, week_key }
- **Source:** [`api.php:1307`](../api.php#L1307)

## Movie List

### POST `?action=ml_add`

Saves a movie and returns it in the ml_movies shape. rt takes a full Rotten
Tomatoes link or a bare slug and keeps only the slug. poster is an https
image link, or a bare TMDB file name that the page expands. critics,
audience, rt and poster may all be left empty.

- **Auth:** None
- **Takes:** {title, year, emoji, tissues, vibe, tags, critics, audience, rt, poster}
- **Source:** [`api.php:4674`](../api.php#L4674)

### POST `?action=ml_delete`

Removes a movie that was added from the page, and its watched row. The
built-in list in movielist.html is not in this table; it changes by editing
the file.

- **Auth:** None
- **Takes:** {id}
- **Source:** [`api.php:4720`](../api.php#L4720)

### POST `?action=ml_draft`

Claude drafts the year, emoji, tissue rating, vibe and tags from a title.
Saves nothing itself: the page passes the draft straight on to ml_add.
found is false when Claude does not recognize the title, with note saying
why, and nothing is added. Rotten Tomatoes scores and the poster are never
drafted. Open to anyone, so it is rate-limited per address (mlDraftLimit).

- **Auth:** None
- **Takes:** {title}
- **Source:** [`api.php:4639`](../api.php#L4639)

### GET `?action=ml_movies`

Everything the page keeps on the server, in one request: every movie added
from the page, oldest first, so the page can append them after its built-in
list in the order they were added; and watched, keyed by the page's movie
id, as {at: epoch ms, tissues: 0-5}.

- **Auth:** None
- **Source:** [`api.php:4603`](../api.php#L4603)

### POST `?action=ml_watch`

Checks a movie off (watched true) or un-checks it (false, which also drops
its tissue count). tissues, 0-5, is how many it took; 0 means not rated.
Re-sending watched true for a checked movie only updates tissues and keeps
the original watched time. id is not checked against the built-in list,
which only the page knows, so it is held to the shape of a page id.

- **Auth:** None
- **Takes:** {id, watched, tissues}
- **Source:** [`api.php:4619`](../api.php#L4619)

## Update Notifications

### POST `?action=delete_subscriber`

Removes one contact from the update list, e.g. to test the sign-up flow from
a clean slate. Normalizes the value the same way subscribe does, so "555 010 0199"
finds the row stored as "+15550100199". Admin-gated because it removes
someone else's consent record and confirms whether a contact is on the list.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret  body: { contact_value }
- **Source:** [`api.php:1782`](../api.php#L1782)

### POST `?action=notify_subscribers`

Called by the GitHub Actions deploy workflow when CHANGELOG.md changes.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret  body: { message }
- **Source:** [`api.php:1736`](../api.php#L1736)

### GET `?action=resetTestPhone`

Removes the operator's own test phone from the list so sign-up can be tested
from a clean slate. Takes no input and needs no secret, so it
can be tapped from a phone browser. Safe to leave open only because it can
touch exactly one row: the number comes from server configuration, never
from the request, and the repo is public so it is not written here. With no
test phone configured it does nothing.

- **Auth:** None
- **Source:** [`api.php:1816`](../api.php#L1816)

### GET `?action=sms_diag`

Why did a text not arrive? Reports whether this server has Twilio configured
and whether the number is on the subscriber list, which between them explain
most silent failures. Sends nothing. Admin-gated because it reports on the
host's configuration.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** &to=…  header: X-Admin-Secret
- **Query parameters:** `to`
- **Source:** [`api.php:1691`](../api.php#L1691)

### POST `?action=sms_diag`

Sends the opt-in confirmation to one number and returns Twilio's verdict
verbatim — HTTP status, message SID and error code. The only way to see why
a send failed without reading the host's error log. Costs one message.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** &to=…  header: X-Admin-Secret
- **Query parameters:** `to`
- **Source:** [`api.php:1723`](../api.php#L1723)

### POST `?action=subscribe`

"website" is an invisible honeypot field — real users never fill it in.

- **Auth:** None
- **Takes:** body: { contact_type: 'email'\|'phone', contact_value, website? }
- **Source:** [`api.php:1591`](../api.php#L1591)

### GET `?action=unsubscribe`

- **Auth:** Single-use link token — `?token=`
- **Takes:** &token=xxx — clicked from an email/SMS, so it renders HTML, not JSON
- **Query parameters:** `token`
- **Source:** [`api.php:1672`](../api.php#L1672)

## Analytics

### DELETE `?action=an_fixed_cost`

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** &id=X  header: X-Admin-Secret
- **Query parameters:** `id`
- **Source:** [`api.php:5203`](../api.php#L5203)

### POST `?action=an_fixed_cost`

Add a recurring cost for stats.html, or update one when id is given. period
is 'month' or 'year'.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret  body: { id?, label, amount, period }
- **Source:** [`api.php:5180`](../api.php#L5180)

### POST `?action=an_hit`

The page-view beacon every page sends on load: path, referrer, device class,
installed-as-app, theme and language. Open by necessity, and it always
answers 204 so a caller cannot tell whether a hit was kept. The server adds
browser and OS families and the city, which is looked up from the IP; the IP
itself is never stored.

- **Auth:** None
- **Takes:** body: { p, r, d, i, t, l }
- **Source:** [`api.php:5033`](../api.php#L5033)

### GET `?action=an_stats`

Everything stats.html shows, for the last `days` days (1–90, today included)
in the caller's timezone, given as minutes east of UTC. "Visitors" counts
each visitor once per day, because the hash that identifies them changes
daily — so over a range it is visitor-days, not people. `history` is the
whole-site daily series for all time, in UTC days, including days already
rolled up out of an_hits.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** &days=30&tz=-300  header: X-Admin-Secret
- **Query parameters:** `days`, `tz`
- **Source:** [`api.php:5082`](../api.php#L5082)

## Schema

All tables live in one database and are created on demand — the
`CREATE TABLE IF NOT EXISTS` block at the top of `api.php` runs on every
request. New tables appear automatically; column changes to an existing
table need a manual `ALTER` against the live database.

### Meeting Cost Timer

#### `meetings`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
title      VARCHAR(120)  NOT NULL
cost       DECIMAL(12,2) NOT NULL
seconds    INT           NOT NULL
week_key   DATE          NOT NULL
created_at DATETIME      DEFAULT CURRENT_TIMESTAMP
```

### Track Timer

#### `track_sessions`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
name       VARCHAR(120)  NOT NULL
duration   BIGINT        NOT NULL
athletes   LONGTEXT      NOT NULL
created_at DATETIME      DEFAULT CURRENT_TIMESTAMP
```

### Toolshare

#### `tb_users`

```sql
id            INT AUTO_INCREMENT PRIMARY KEY
username      VARCHAR(60)  NOT NULL UNIQUE
password_hash VARCHAR(255) NOT NULL
display_name  VARCHAR(80)  NOT NULL
email         VARCHAR(120) NOT NULL
token         VARCHAR(64)  DEFAULT NULL
created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
```

#### `tb_tools`

```sql
id          INT AUTO_INCREMENT PRIMARY KEY
owner_id    INT           NOT NULL
name        VARCHAR(120)  NOT NULL
brand       VARCHAR(80)   DEFAULT NULL
category    VARCHAR(60)   DEFAULT NULL
notes       TEXT          DEFAULT NULL
photo_url   VARCHAR(255)  DEFAULT NULL
status      ENUM('available','borrowed') DEFAULT 'available'
created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
FOREIGN KEY (owner_id) REFERENCES tb_users(id) ON DELETE CASCADE
```

#### `tb_requests`

```sql
id           INT AUTO_INCREMENT PRIMARY KEY
tool_id      INT           NOT NULL
requester_id INT           NOT NULL
owner_id     INT           NOT NULL
status       ENUM('pending','approved','declined','returned') DEFAULT 'pending'
message      TEXT          DEFAULT NULL
created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP
updated_at   DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
FOREIGN KEY (tool_id)      REFERENCES tb_tools(id)   ON DELETE CASCADE
FOREIGN KEY (requester_id) REFERENCES tb_users(id)   ON DELETE CASCADE
FOREIGN KEY (owner_id)     REFERENCES tb_users(id)   ON DELETE CASCADE
```

#### `tb_sessions`

```sql
token      VARCHAR(64) PRIMARY KEY
user_id    INT         NOT NULL
created_at DATETIME    DEFAULT CURRENT_TIMESTAMP
INDEX idx_user (user_id)
FOREIGN KEY (user_id) REFERENCES tb_users(id) ON DELETE CASCADE
```

#### `tb_friendships`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
user_a     INT NOT NULL
user_b     INT NOT NULL
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
UNIQUE KEY unique_pair (user_a, user_b)
FOREIGN KEY (user_a) REFERENCES tb_users(id) ON DELETE CASCADE
FOREIGN KEY (user_b) REFERENCES tb_users(id) ON DELETE CASCADE
```

### Flight Tracker

#### `ft_users`

```sql
id            INT AUTO_INCREMENT PRIMARY KEY
username      VARCHAR(60)  NOT NULL UNIQUE
password_hash VARCHAR(255) NOT NULL
display_name  VARCHAR(80)  NOT NULL
token         VARCHAR(64)  DEFAULT NULL
created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
```

#### `ft_flights`

```sql
id             INT AUTO_INCREMENT PRIMARY KEY
user_id        INT          NOT NULL
from_code      VARCHAR(4)   NOT NULL
to_code        VARCHAR(4)   NOT NULL
from_city      VARCHAR(100) NOT NULL
to_city        VARCHAR(100) NOT NULL
flight_date    DATE         NOT NULL
airline        VARCHAR(80)  DEFAULT NULL
seat_class     VARCHAR(30)  DEFAULT NULL
flight_number  VARCHAR(20)  DEFAULT NULL
created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP
FOREIGN KEY (user_id) REFERENCES ft_users(id) ON DELETE CASCADE
```

### Glucose

#### `bg_readings`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
reading_at DATETIME     NOT NULL
mgdl       SMALLINT     NOT NULL
trend      VARCHAR(20)  DEFAULT NULL
created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
UNIQUE KEY uniq_reading_at (reading_at)
```

#### `bg_events`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
start_at   DATETIME     NOT NULL
end_at     DATETIME     DEFAULT NULL
tag        VARCHAR(32)  NOT NULL
note       VARCHAR(500) DEFAULT NULL
created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
INDEX idx_start (start_at)
```

### Confidence Pool

#### `cp_weeks`

```sql
id                INT AUTO_INCREMENT PRIMARY KEY
season            INT          NOT NULL
week              INT          NOT NULL
push_rule         ENUM('award','void') DEFAULT 'void'
scores_fetched_at DATETIME     DEFAULT NULL
created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP
UNIQUE KEY uk_season_week (season, week)
```

#### `cp_games`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
week_id    INT          NOT NULL
sort_order INT          NOT NULL
away_team  VARCHAR(8)   NOT NULL
home_team  VARCHAR(8)   NOT NULL
favorite   ENUM('home','away') NOT NULL
spread     DECIMAL(4,1) NOT NULL
espn_id    VARCHAR(24)  DEFAULT NULL
away_score INT          DEFAULT 0
home_score INT          DEFAULT 0
state      ENUM('pre','in','post') DEFAULT 'pre'
detail     VARCHAR(60)  DEFAULT NULL
kickoff    DATETIME     DEFAULT NULL
UNIQUE KEY uk_week_order (week_id, sort_order)
FOREIGN KEY (week_id) REFERENCES cp_weeks(id) ON DELETE CASCADE
```

#### `cp_entries`

```sql
id          INT AUTO_INCREMENT PRIMARY KEY
week_id     INT          NOT NULL
player_name VARCHAR(60)  NOT NULL
created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
UNIQUE KEY uk_week_player (week_id, player_name)
FOREIGN KEY (week_id) REFERENCES cp_weeks(id) ON DELETE CASCADE
```

#### `cp_picks`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
entry_id   INT NOT NULL
game_id    INT NOT NULL
pick       ENUM('home','away') NOT NULL
confidence INT NOT NULL
UNIQUE KEY uk_entry_game (entry_id, game_id)
UNIQUE KEY uk_entry_conf (entry_id, confidence)
FOREIGN KEY (entry_id) REFERENCES cp_entries(id) ON DELETE CASCADE
FOREIGN KEY (game_id)  REFERENCES cp_games(id)   ON DELETE CASCADE
```

#### `cp_players`

```sql
id          INT AUTO_INCREMENT PRIMARY KEY
phone       VARCHAR(20)  NOT NULL UNIQUE
player_name VARCHAR(60)  NOT NULL
opted_out   TINYINT(1)   DEFAULT 0
created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

#### `cp_pending`

```sql
id          INT AUTO_INCREMENT PRIMARY KEY
token       VARCHAR(40)  NOT NULL UNIQUE
week_id     INT          NOT NULL
player_name VARCHAR(60)  NOT NULL
phone       VARCHAR(20)  DEFAULT NULL
rows_json   LONGTEXT     NOT NULL
note        VARCHAR(255) DEFAULT NULL
claimed_at  DATETIME     DEFAULT NULL
created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
INDEX idx_week (week_id)
FOREIGN KEY (week_id) REFERENCES cp_weeks(id) ON DELETE CASCADE
```

### WildcatsXC

#### `xc_meets`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
name       VARCHAR(160) NOT NULL
meet_date  DATE         NOT NULL
location   VARCHAR(160) DEFAULT NULL
created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
INDEX idx_date (meet_date)
```

#### `xc_athletes`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
name       VARCHAR(80)  NOT NULL
school     VARCHAR(120) NOT NULL
match_key  VARCHAR(210) NOT NULL
created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
UNIQUE KEY uniq_athlete (match_key)
```

#### `xc_results`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
meet_id    INT          NOT NULL
athlete_id INT          NOT NULL
race       VARCHAR(80)  NOT NULL
distance_m SMALLINT     DEFAULT NULL
place      SMALLINT     DEFAULT NULL
grade      TINYINT      DEFAULT NULL
time_ms    INT          NOT NULL
UNIQUE KEY uniq_run (meet_id, athlete_id)
INDEX idx_athlete (athlete_id)
FOREIGN KEY (meet_id)    REFERENCES xc_meets(id)    ON DELETE CASCADE
FOREIGN KEY (athlete_id) REFERENCES xc_athletes(id) ON DELETE CASCADE
```

#### `xc_pin_attempts`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
ip         VARCHAR(45) NOT NULL
created_at DATETIME    DEFAULT CURRENT_TIMESTAMP
KEY idx_ip (ip, created_at)
```

### Daily Tasks

#### `dt_scores`

```sql
id            INT AUTO_INCREMENT PRIMARY KEY
name          VARCHAR(60)  NOT NULL
task_count    INT          NOT NULL
total_seconds INT          NOT NULL
week_key      DATE         NOT NULL
created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
INDEX idx_week (week_key, task_count, total_seconds)
```

#### `dt_tasks`

```sql
user_id     INT          NOT NULL
date_key    DATE         NOT NULL
tasks_json  LONGTEXT     NOT NULL
next_id     INT          NOT NULL DEFAULT 1
updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
PRIMARY KEY (user_id, date_key)
FOREIGN KEY (user_id) REFERENCES tb_users(id) ON DELETE CASCADE
```

### Face Breaker

#### `fb_scores`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
name       VARCHAR(32)  NOT NULL
score      INT          NOT NULL
week_start DATE         NOT NULL
created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
INDEX idx_week_score (week_start, score)
```

### Reaction Test

#### `reaction_scores`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
name       VARCHAR(60)  NOT NULL
avg_ms     INT          NOT NULL
week_key   DATE         NOT NULL
created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
INDEX idx_week (week_key, avg_ms)
```

### Movie List

#### `ml_movies`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
title      VARCHAR(200) NOT NULL
year       SMALLINT     NOT NULL DEFAULT 0
emoji      VARCHAR(16)  NOT NULL DEFAULT ''
tissues    TINYINT      NOT NULL DEFAULT 3
vibe       VARCHAR(400) NOT NULL DEFAULT ''
tags       VARCHAR(200) NOT NULL DEFAULT ''
critics    TINYINT      DEFAULT NULL
audience   TINYINT      DEFAULT NULL
rt         VARCHAR(120) DEFAULT NULL
poster     VARCHAR(300) DEFAULT NULL
created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
```

#### `ml_draft_log`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
ip         VARCHAR(45) NOT NULL
created_at DATETIME    DEFAULT CURRENT_TIMESTAMP
KEY idx_ip (ip, created_at)
```

#### `ml_watched`

```sql
movie_id   VARCHAR(20) NOT NULL PRIMARY KEY
tissues    TINYINT     NOT NULL DEFAULT 0
watched_at DATETIME    DEFAULT CURRENT_TIMESTAMP
```

### Update Notifications

#### `subscribers`

```sql
id            INT AUTO_INCREMENT PRIMARY KEY
contact_type  ENUM('email','phone') NOT NULL
contact_value VARCHAR(120) NOT NULL
unsub_token   VARCHAR(64)  NOT NULL
created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
UNIQUE KEY uniq_contact (contact_type, contact_value)
```

### Analytics

#### `an_hits`

```sql
id        BIGINT AUTO_INCREMENT PRIMARY KEY
ts        DATETIME     NOT NULL
page      VARCHAR(60)  NOT NULL
vid       CHAR(16)     NOT NULL
referrer  VARCHAR(100) DEFAULT NULL
device    VARCHAR(8)   DEFAULT NULL
browser   VARCHAR(16)  DEFAULT NULL
os        VARCHAR(16)  DEFAULT NULL
installed TINYINT(1)   NOT NULL DEFAULT 0
theme     VARCHAR(5)   DEFAULT NULL
lang      VARCHAR(15)  DEFAULT NULL
city      VARCHAR(80)  DEFAULT NULL
region    VARCHAR(80)  DEFAULT NULL
country   CHAR(2)      DEFAULT NULL
lat       DECIMAL(5,2) DEFAULT NULL
lon       DECIMAL(5,2) DEFAULT NULL
KEY idx_ts (ts)
KEY idx_vid (vid, ts)
```

#### `an_daily`

```sql
day      DATE        NOT NULL
page     VARCHAR(60) NOT NULL
views    INT         NOT NULL
visitors INT         NOT NULL
PRIMARY KEY (day, page)
```

#### `an_salt`

```sql
day  DATE     NOT NULL PRIMARY KEY
salt CHAR(64) NOT NULL
```

#### `an_api`

```sql
day      DATE        NOT NULL
method   VARCHAR(7)  NOT NULL
action   VARCHAR(40) NOT NULL
calls    INT         NOT NULL DEFAULT 0
errors   INT         NOT NULL DEFAULT 0
total_ms BIGINT      NOT NULL DEFAULT 0
max_ms   INT         NOT NULL DEFAULT 0
PRIMARY KEY (day, method, action)
```

#### `an_ai`

```sql
day         DATE        NOT NULL
action      VARCHAR(40) NOT NULL
model       VARCHAR(60) NOT NULL
calls       INT         NOT NULL DEFAULT 0
input       BIGINT      NOT NULL DEFAULT 0
output      BIGINT      NOT NULL DEFAULT 0
cache_write BIGINT      NOT NULL DEFAULT 0
cache_read  BIGINT      NOT NULL DEFAULT 0
PRIMARY KEY (day, action, model)
```

#### `an_fixed_costs`

```sql
id         INT AUTO_INCREMENT PRIMARY KEY
label      VARCHAR(80)            NOT NULL
amount     DECIMAL(10,2)          NOT NULL
period     ENUM('month','year')   NOT NULL
created_at DATETIME               DEFAULT CURRENT_TIMESTAMP
```

#### `an_cache`

```sql
k          VARCHAR(80) NOT NULL PRIMARY KEY
v          MEDIUMTEXT  NOT NULL
fetched_at DATETIME    NOT NULL
```

### Other

#### `subscribe_attempts`

```sql
id            INT AUTO_INCREMENT PRIMARY KEY
ip            VARCHAR(45)  NOT NULL
contact_value VARCHAR(120) NOT NULL
texted        TINYINT(1)   NOT NULL DEFAULT 0
created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
KEY idx_ip (ip, created_at)
KEY idx_contact (contact_value, created_at)
```
