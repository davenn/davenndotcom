# API reference

<!-- Generated from api.php by tools/gen-api-docs.mjs. Do not edit by hand.
     Endpoint descriptions come from the comment block above each branch in
     api.php — edit them there and regenerate. -->

Every app on davenn.com is served by a single `api.php`. Endpoints are selected
by an `action` query parameter and always return JSON.

```
GET|POST|DELETE  /api.php?action=<action>
```

**69 endpoints · 22 tables**

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
| [`dt_get_tasks`](#get-actiondt_get_tasks) | GET | Daily Tasks | Toolshare account — `X-Auth-Token` |
| [`dt_save`](#post-actiondt_save) | POST | Daily Tasks | None |
| [`dt_save_tasks`](#post-actiondt_save_tasks) | POST | Daily Tasks | Toolshare account — `X-Auth-Token` |
| [`dt_week`](#get-actiondt_week) | GET | Daily Tasks | None |
| [`fb_leaderboard`](#get-actionfb_leaderboard) | GET | Face Breaker | None |
| [`fb_save_score`](#post-actionfb_save_score) | POST | Face Breaker | None |
| [`clear_reaction_week`](#delete-actionclear_reaction_week) | DELETE | Reaction Test | None |
| [`reaction_week`](#get-actionreaction_week) | GET | Reaction Test | None |
| [`save_reaction`](#post-actionsave_reaction) | POST | Reaction Test | None |
| [`notify_subscribers`](#post-actionnotify_subscribers) | POST | Update Notifications | Admin — `X-Admin-Secret` |
| [`subscribe`](#post-actionsubscribe) | POST | Update Notifications | None |
| [`unsubscribe`](#get-actionunsubscribe) | GET | Update Notifications | Single-use link token — `?token=` |

## Meeting Cost Timer

### DELETE `?action=clear_week`

- **Auth:** None
- **Query parameters:** `week_key`
- **Source:** [`api.php:348`](../api.php#L348)

### POST `?action=save`

- **Auth:** None
- **Source:** [`api.php:334`](../api.php#L334)

### GET `?action=week`

- **Auth:** None
- **Query parameters:** `week_key`
- **Source:** [`api.php:324`](../api.php#L324)

## Track Timer

### DELETE `?action=clear_track_sessions`

- **Auth:** None
- **Source:** [`api.php:390`](../api.php#L390)

### DELETE `?action=delete_track_session`

- **Auth:** None
- **Query parameters:** `id`
- **Source:** [`api.php:382`](../api.php#L382)

### POST `?action=save_track_session`

- **Auth:** None
- **Source:** [`api.php:369`](../api.php#L369)

### GET `?action=track_sessions`

- **Auth:** None
- **Source:** [`api.php:362`](../api.php#L362)

## Toolshare

### POST `?action=tb_accept_invite`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** body: { invite_code }
- **Source:** [`api.php:833`](../api.php#L833)

### POST `?action=tb_add_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** (multipart for photo upload OR JSON)
- **Source:** [`api.php:476`](../api.php#L476)

### DELETE `?action=tb_delete_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:623`](../api.php#L623)

### POST `?action=tb_edit_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:507`](../api.php#L507)

### GET `?action=tb_friends`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Source:** [`api.php:851`](../api.php#L851)

### POST `?action=tb_identify_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** vision-based auto-fill via Claude
- **Source:** [`api.php:541`](../api.php#L541)

### GET `?action=tb_invite_preview`

- **Auth:** None
- **Takes:** &code=xxx  (no auth required)
- **Query parameters:** `code`
- **Source:** [`api.php:822`](../api.php#L822)

### POST `?action=tb_login`

- **Auth:** None
- **Source:** [`api.php:427`](../api.php#L427)

### POST `?action=tb_logout`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** ends only this device's session, other devices stay signed in
- **Source:** [`api.php:445`](../api.php#L445)

### GET `?action=tb_my_invite`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Source:** [`api.php:815`](../api.php#L815)

### GET `?action=tb_my_tools`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** current user's tools only
- **Source:** [`api.php:458`](../api.php#L458)

### POST `?action=tb_register`

- **Auth:** None
- **Source:** [`api.php:400`](../api.php#L400)

### DELETE `?action=tb_remove_friend`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:866`](../api.php#L866)

### POST `?action=tb_request`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** create borrow request + email owner
- **Source:** [`api.php:644`](../api.php#L644)

### GET `?action=tb_request_count`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** badge count of pending incoming requests
- **Source:** [`api.php:705`](../api.php#L705)

### GET `?action=tb_requests`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** inbox (owner) + outbox (requester) for current user
- **Source:** [`api.php:677`](../api.php#L677)

### POST `?action=tb_respond_request`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X  body: { status: "approved"\|"declined" }
- **Query parameters:** `id`
- **Source:** [`api.php:713`](../api.php#L713)

### POST `?action=tb_return_tool`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &id=X  (request id) — mark tool as returned
- **Query parameters:** `id`
- **Source:** [`api.php:748`](../api.php#L748)

### GET `?action=tb_tag_suggestions`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** unique tags from visible tools
- **Source:** [`api.php:794`](../api.php#L794)

### GET `?action=tb_tools`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** friends-gated community view
- **Source:** [`api.php:876`](../api.php#L876)

## Flight Tracker

### POST `?action=ft_add_flight`

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Source:** [`api.php:1077`](../api.php#L1077)

### POST `?action=ft_add_flights`

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Source:** [`api.php:1099`](../api.php#L1099)

### DELETE `?action=ft_delete_flight`

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Query parameters:** `id`
- **Source:** [`api.php:1125`](../api.php#L1125)

### GET `?action=ft_flights`

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Source:** [`api.php:1070`](../api.php#L1070)

### POST `?action=ft_login`

- **Auth:** None
- **Source:** [`api.php:1048`](../api.php#L1048)

### POST `?action=ft_logout`

- **Auth:** Flight Tracker account — `X-Auth-Token`
- **Source:** [`api.php:1064`](../api.php#L1064)

### POST `?action=ft_register`

- **Auth:** None
- **Source:** [`api.php:1024`](../api.php#L1024)

## Glucose

### GET `?action=bg_daily`

This is the shape the streak calendar and time-in-range bars want; the
aggregation happens in SQL so a year of history stays a small response.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…&days=30&low=70&high=180 → one row per local day.
- **Query parameters:** `days`, `low`, `high`
- **Source:** [`api.php:1582`](../api.php#L1582)

### GET `?action=bg_embed`

mgdl 0 means nothing is stored yet. Trend follows Dexcom's own ordering, so a
device can index an arrow glyph straight off it:
  0 unknown · 1 up-up · 2 up · 3 up-45 · 4 flat · 5 down-45 · 6 down · 7 down-down

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Query parameters:** `low`, `high`, `spark`
- **Source:** [`api.php:1492`](../api.php#L1492)

### DELETE `?action=bg_event_delete`

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &id=…&token=…
- **Query parameters:** `id`
- **Source:** [`api.php:1781`](../api.php#L1781)

### POST `?action=bg_event_save`

body: { id?, start_at, end_at?, tag, note? }
Creates, or updates when id is given. Returns the stored row.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…
- **Source:** [`api.php:1706`](../api.php#L1706)

### GET `?action=bg_events`

Read token: anything that may read the readings may read what explains them.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…&hours=24 → annotations overlapping the window.
- **Query parameters:** `hours`
- **Source:** [`api.php:1671`](../api.php#L1671)

### GET `?action=bg_history`

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…&hours=24&low=70&high=180 → raw readings + summary.
- **Query parameters:** `hours`, `low`, `high`
- **Source:** [`api.php:1535`](../api.php#L1535)

### POST `?action=bg_ingest`

body: { readings: [ { at: ISO8601, mgdl: int, trend: string|null } ] }
Idempotent — the poller deliberately re-sends an overlapping window, and
replaying it just refreshes rows already stored.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret
- **Source:** [`api.php:1360`](../api.php#L1360)

### GET `?action=bg_latest`

minutes_ago is what a display should use to decide it has gone stale: show
the age, and never present an old number as though it were current.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…  → the newest stored reading.
- **Source:** [`api.php:1467`](../api.php#L1467)

### POST `?action=bg_refresh`

Asks the poller to pull from Dexcom now, so a manual refresh reflects live
data rather than whatever was last stored. The admin secret stays here on the
server — the page only ever holds the read-only token, which is why this is a
proxy rather than the browser calling the poller directly.

- **Auth:** Glucose read token — `X-BG-Token` or `?token=`
- **Takes:** &token=…
- **Source:** [`api.php:1415`](../api.php#L1415)

## Confidence Pool

### GET `?action=cp_diag`

Admin-guarded despite holding no credentials or user data: the reply names
the PHP and curl versions, open_basedir and the server's own address, and
that combination is worth more to someone scanning for a way in than it is
to anyone else. Nothing in the site calls this — it is run by hand.

- **Auth:** Admin — `X-Admin-Secret`
- **Query parameters:** `season`, `week`
- **Source:** [`api.php:2952`](../api.php#L2952)

### DELETE `?action=cp_entry`

- **Auth:** None
- **Takes:** &id=X
- **Query parameters:** `id`
- **Source:** [`api.php:3012`](../api.php#L3012)

### DELETE `?action=cp_pending`

- **Auth:** Single-use link token — `?token=`
- **Takes:** &token=… — drop a staged sheet once it is saved.
- **Query parameters:** `token`
- **Source:** [`api.php:2875`](../api.php#L2875)

### GET `?action=cp_pending`

- **Auth:** Single-use link token — `?token=`
- **Takes:** &token=… — collect a staged sheet for review.
- **Query parameters:** `token`
- **Source:** [`api.php:2853`](../api.php#L2853)

### POST `?action=cp_purge_photos`

One-shot cleanup for sheet photos written before the app stopped keeping
them. Nothing creates these files any more, so this should report 0 on every
run after the first.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret
- **Source:** [`api.php:2919`](../api.php#L2919)

### DELETE `?action=cp_roster`

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** &id=…  header: X-Admin-Secret — unlink a number.
- **Query parameters:** `id`
- **Source:** [`api.php:2904`](../api.php#L2904)

### GET `?action=cp_roster`

Admin-guarded because this is the one table that maps a person to a phone
number, which is the most sensitive thing the pool holds.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret — who is linked to what number.
- **Source:** [`api.php:2887`](../api.php#L2887)

### POST `?action=cp_save_entry`

- **Auth:** None
- **Takes:** {season, week, player_name, picks:[{game_id,pick,confidence}]}
- **Source:** [`api.php:2550`](../api.php#L2550)

### POST `?action=cp_save_week`

Creates the week, or updates the spreads on one that already has entries.

- **Auth:** None
- **Takes:** {season, week, push_rule, games:[{away,home,favorite,spread}]}
- **Source:** [`api.php:2459`](../api.php#L2459)

### POST `?action=cp_scan`

Reads a filled-in sheet. Saves no picks — the app shows the result for
correction first, because a misread confidence number costs more than a
misread team name and both happen.

- **Auth:** None
- **Takes:** (multipart: photo, optional season + week)
- **Query parameters:** `season`, `week`
- **Source:** [`api.php:2406`](../api.php#L2406)

### POST `?action=cp_sms`

- **Auth:** None
- **Takes:** Twilio inbound webhook.
- **Source:** [`api.php:2752`](../api.php#L2752)

### DELETE `?action=cp_week`

- **Auth:** None
- **Takes:** &season=&week=
- **Query parameters:** `season`, `week`
- **Source:** [`api.php:3021`](../api.php#L3021)

### GET `?action=cp_week`

- **Auth:** None
- **Takes:** &season=&week=
- **Query parameters:** `season`, `week`, `force`
- **Source:** [`api.php:2622`](../api.php#L2622)

### GET `?action=cp_weeks`

- **Auth:** None
- **Takes:** every week that has been set up, newest first.
- **Source:** [`api.php:2997`](../api.php#L2997)

## Daily Tasks

### GET `?action=dt_get_tasks`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** &date=YYYY-MM-DD — load this user's task list for a given day
- **Query parameters:** `date`
- **Source:** [`api.php:1168`](../api.php#L1168)

### POST `?action=dt_save`

- **Auth:** None
- **Source:** [`api.php:1149`](../api.php#L1149)

### POST `?action=dt_save_tasks`

- **Auth:** Toolshare account — `X-Auth-Token`
- **Takes:** body: { date, tasks, next_id } — upsert this user's task list for a given day
- **Source:** [`api.php:1188`](../api.php#L1188)

### GET `?action=dt_week`

- **Auth:** None
- **Query parameters:** `week_key`
- **Source:** [`api.php:1139`](../api.php#L1139)

## Face Breaker

### GET `?action=fb_leaderboard`

- **Auth:** None
- **Takes:** top 10 for the current ISO week (no auth)
- **Source:** [`api.php:906`](../api.php#L906)

### POST `?action=fb_save_score`

- **Auth:** None
- **Takes:** body: { name, score } — no auth required
- **Source:** [`api.php:916`](../api.php#L916)

## Reaction Test

### DELETE `?action=clear_reaction_week`

- **Auth:** None
- **Query parameters:** `week_key`
- **Source:** [`api.php:972`](../api.php#L972)

### GET `?action=reaction_week`

- **Auth:** None
- **Query parameters:** `week_key`
- **Source:** [`api.php:942`](../api.php#L942)

### POST `?action=save_reaction`

- **Auth:** None
- **Source:** [`api.php:952`](../api.php#L952)

## Update Notifications

### POST `?action=notify_subscribers`

Called by the GitHub Actions deploy workflow when CHANGELOG.md changes.

- **Auth:** Admin — `X-Admin-Secret`
- **Takes:** header: X-Admin-Secret  body: { message }
- **Source:** [`api.php:1279`](../api.php#L1279)

### POST `?action=subscribe`

"website" is an invisible honeypot field — real users never fill it in.

- **Auth:** None
- **Takes:** body: { contact_type: 'email'\|'phone', contact_value, website? }
- **Source:** [`api.php:1211`](../api.php#L1211)

### GET `?action=unsubscribe`

- **Auth:** Single-use link token — `?token=`
- **Takes:** &token=xxx — clicked from an email/SMS, so it renders HTML, not JSON
- **Query parameters:** `token`
- **Source:** [`api.php:1263`](../api.php#L1263)

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

## Not yet described

These endpoints have no comment block above them in `api.php`. To
document one, write the comment there and regenerate — do not edit
this file.

- `DELETE ?action=clear_week` — [`api.php:348`](../api.php#L348)
- `POST ?action=save` — [`api.php:334`](../api.php#L334)
- `GET ?action=week` — [`api.php:324`](../api.php#L324)
- `DELETE ?action=clear_track_sessions` — [`api.php:390`](../api.php#L390)
- `DELETE ?action=delete_track_session` — [`api.php:382`](../api.php#L382)
- `POST ?action=save_track_session` — [`api.php:369`](../api.php#L369)
- `GET ?action=track_sessions` — [`api.php:362`](../api.php#L362)
- `GET ?action=tb_friends` — [`api.php:851`](../api.php#L851)
- `POST ?action=tb_login` — [`api.php:427`](../api.php#L427)
- `GET ?action=tb_my_invite` — [`api.php:815`](../api.php#L815)
- `POST ?action=tb_register` — [`api.php:400`](../api.php#L400)
- `POST ?action=ft_add_flight` — [`api.php:1077`](../api.php#L1077)
- `POST ?action=ft_add_flights` — [`api.php:1099`](../api.php#L1099)
- `DELETE ?action=ft_delete_flight` — [`api.php:1125`](../api.php#L1125)
- `GET ?action=ft_flights` — [`api.php:1070`](../api.php#L1070)
- `POST ?action=ft_login` — [`api.php:1048`](../api.php#L1048)
- `POST ?action=ft_logout` — [`api.php:1064`](../api.php#L1064)
- `POST ?action=ft_register` — [`api.php:1024`](../api.php#L1024)
- `POST ?action=dt_save` — [`api.php:1149`](../api.php#L1149)
- `GET ?action=dt_week` — [`api.php:1139`](../api.php#L1139)
- `DELETE ?action=clear_reaction_week` — [`api.php:972`](../api.php#L972)
- `GET ?action=reaction_week` — [`api.php:942`](../api.php#L942)
- `POST ?action=save_reaction` — [`api.php:952`](../api.php#L952)
