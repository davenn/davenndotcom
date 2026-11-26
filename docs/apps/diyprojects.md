# DIY Projects

A private tracker for home projects (yard work, building, construction) from
first idea to done, with progress photos. Each project is a card on a freeform
canvas, or on a kanban board with one column per status.

**File:** [`diyprojects.html`](../../diyprojects.html)
**Tables:** `diy_projects` `diy_photos`
**Auth:** Toolshare accounts (`requireAuth()`) on every endpoint

## Private by construction

There is no sharing of any kind. Every query is scoped by `user_id`, and
`diyOwnProject()` answers someone else's project with the same 404 as a
missing one, so ids cannot be walked to learn what exists. The photo
endpoints check the same ownership.

**Accounts are Toolshare's.** Like Daily Tasks and Gifts, the page calls
`tb_login` and `tb_register` and stores the token under the same `tb_token` /
`tb_user` keys, so being signed in to Toolshare or Daily Tasks means being
signed in here. There is no DIY-specific account or session table.

## Photos

Toolshare's photos are public URLs under `uploads/tools/`. These are not, and
that difference is the main design decision in this app.

- **Stored where the web server will not serve them.** Files live in
  `uploads/diy/u<user_id>/`. `diyPhotoDir()` writes a deny-all `.htaccess`
  into `uploads/diy/` the first time it runs, because the deploy skips
  `uploads/` and could never ship it. Filenames are 128 random bits as well,
  so a host that ignored `.htaccess` would still not hand out guessable URLs.
- **Served only through `diy_photo`.** It checks the token, then streams the
  file. An `<img>` cannot send `X-Auth-Token`, so the page fetches each photo
  and shows it from a blob URL (`photoUrl()`), six at a time, once per visit.
  Responses are `Cache-Control: private` for a year with `Vary: X-Auth-Token`:
  a stored photo never changes, so revisits come from the browser cache.
- **Resized in the browser before upload.** Each photo is redrawn on a canvas
  as two JPEGs, about 2048px on the long edge for viewing and 480px for card
  covers and thumbnails, and both are uploaded together. That keeps uploads
  well under PHP's upload limit on any hosting plan, and needs no GD on the
  server. Re-encoding also **strips EXIF, including GPS**, which would
  otherwise record where the house is. EXIF orientation is applied while
  drawing, so the stored pixels are already upright.
- **Server checks.** Both parts must be real JPEGs (`getimagesize`), with
  8 MB and 1 MB caps, and each account has a 1 GB total, because
  registration is open to anyone.
- **Cover.** `cover_photo_id` on the project has no foreign key, since
  `diy_photos` already points back at the project. The endpoints keep it
  valid instead: the first upload becomes the cover, and deleting the cover
  promotes the oldest remaining photo, or leaves none.
- **Deleting.** Rows go first and files second, so a failed unlink leaves an
  orphan file rather than a row that points at nothing.
- **Backups.** `uploads/` is not in git, so photos exist only on the host and
  are covered only by the host's own backups.

## Statuses and dates

Ideation → Design → Planning → In Progress → Done, stored as an ENUM. Any
status can move to any other in either direction; nothing enforces an order.

`start_date` is nullable on purpose: a project with no start date has not
started yet, which is normal for anything still in Ideation. Moving a project
into In Progress fills in today's date if it has none, unless the project
already has an end date earlier than today. That happens in the page
(`setStatus`), not the API, because only the browser knows the user's local
date. The API's only date rule is that an end date cannot come before the
start date.

## Saving

`diy_edit_project` is partial: it changes only the fields sent. The detail
view saves each field as it is edited (notes after a pause in typing), and a
drag on the board sends just the status. Saves run one at a time, in order,
through `saveChain`, so a later edit can never land before an earlier one. A
failed save puts the old value back and says why.

## Canvas and board

- **Canvas positions are scratch space.** They live in a `Map` for the visit,
  survive switching to Kanban and back, and are never saved. Each load lays
  the cards out in a grid ordered by status, then start date. **Tidy up**
  puts them back in that grid.
- **Canvas is the default view on every load.** The choice is not remembered.
- **Dragging.** A mouse or pen drags straight away, with a 4px threshold so a
  click still opens the card. Touch needs a press and hold (320ms) first, so
  an ordinary swipe over the cards still scrolls. Both use the same code
  (`beginDrag` / `follow` / `endDrag`), with edge auto-scroll while dragging.
- **Dropping on a Kanban column** changes the status through the same
  `setStatus` as the detail view's control, start-date rule included.
- **The card face** is the cover photo, the title, and the start and end dates
  only when they are set. Status is not on the face, but it is in each card's
  accessible label.

## Front-end notes

- The detail view adds a history entry, so a phone's back button closes it
  rather than leaving the app.
- On a computer, photos can be dropped onto the photo area.
- Theme is stored under `localStorage['diyprojects_theme']`.
