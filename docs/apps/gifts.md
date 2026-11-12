# Gifts

A private list of the people you buy for and the gift ideas you have for each
of them. Every idea moves from **idea** to **bought** to **given**, and given
gifts are kept with the date, so next year's list can avoid repeats.

**File:** [`gifts.html`](../../gifts.html)
**Tables:** `gf_people`, `gf_ideas`
**Auth:** Toolshare account (`X-Auth-Token` via `requireAuth()`) on every endpoint

## Unlisted

Like `stats.html`, it is deliberately not on the home grid, and it carries
`noindex, nofollow`. Unlisted is not the privacy boundary, though: the list is.
Every row carries the owning `user_id` and every query is scoped to it, so a
signed-in account only ever sees its own people and ideas. The page is still
counted by the analytics beacon, and its endpoints appear in the public
`docs/api.html` like any other signed-in endpoint.

## Accounts

There is no Gifts account. The page signs in with a Toolshare account and
stores the token under the same `localStorage` keys (`tb_token`, `tb_user`),
so being signed in to Toolshare or Daily Tasks on a device means being signed
in here, and **Sign out** here ends that device's shared session for all three.
New accounts can be created from the page through `tb_register`.

A 401 from any call means the shared session ended elsewhere; the page drops
back to the sign-in form rather than showing an error.

## Data

`gf_people` holds a name, a free-text note (sizes, interests) and an optional
birthday stored as `MM-DD` with no year, which is all the countdown needs.
`gf_ideas` holds a title, an http(s) link, an optional price, a note and the
status. `status_at` changes only when the status does, so editing a given
gift's note keeps the date it was given. Removing a person removes their ideas
through the foreign key, and deleting a Toolshare account removes the lot.

`gf_list` returns everything in one call and the page groups ideas under people
itself. The last copy is cached in `localStorage` (`gifts_cache`, tagged with
the user id) so the page opens instantly and can be read offline. Writes need
the network; each returns the saved row and the page swaps it in.

## On the page

- **Status pill** on each idea is a button that cycles idea → bought → given →
  idea. The edit dialog sets it directly, and can move an idea to someone else.
- **Quick add** under each person takes a line of text. A pasted link becomes
  the idea's link and the rest of the line its name; a bare link is named after
  its site until edited.
- **Needs a gift / Got one** filters on whether a person has an idea marked
  bought (and not yet given). Given gifts fold into "Given before" under each
  person, newest first.
- **Spent in YEAR** in the summary adds the price of everything marked bought or
  given during the current calendar year.
- Sort (name, next birthday, most open ideas) and filter are remembered per
  device under `gifts_sort` and `gifts_filter`.
