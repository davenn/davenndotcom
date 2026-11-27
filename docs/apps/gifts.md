# Gifts

A private list of the people you buy for and the gift ideas you have for each
of them. Every idea moves from **idea** to **bought** to **given**, and given
gifts are kept, so next year's list can avoid repeats. It is meant to stay
simple: a person is just a name, and each card shows only that person's ideas.

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
in here. The page has no Sign out button, by the owner's choice: to sign a
device out, sign out of Toolshare or Daily Tasks, which ends the shared session
for all three. New accounts can be created from the page through `tb_register`.

A 401 from any call means the shared session ended elsewhere; the page drops
back to the sign-in form rather than showing an error.

## Installing

The nav has an install icon whenever the page is open in a browser, and hides
it once Gifts runs as the installed app. Where the browser offers its own
install prompt (Chrome, Edge, Android) the icon opens it; elsewhere (iPhone and
iPad, Safari, Firefox) it opens a short sheet with the steps to add it by hand,
such as Share, then Add to Home Screen on iOS.

## Data

`gf_people` holds a name. It also has a `note` and a `birthday` (`MM-DD`)
column, and `gf_person_save` still accepts both, but the page stopped
collecting them when it was simplified to name-only, so it sends neither and
they stay empty. Renaming a person from the page clears them if they were set.
`gf_ideas` holds a title, an http(s) link, an optional price, a note and the
status. `status_at` changes only when the status does, so editing a given
gift's note keeps the date it was given. Removing a person removes their ideas
through the foreign key, and deleting a Toolshare account removes the lot.

`gf_list` returns everything in one call and the page groups ideas under people
itself. The last copy is cached in `localStorage` (`gifts_cache`, tagged with
the user id) so the page opens instantly and can be read offline. Writes need
the network; each returns the saved row and the page swaps it in.

## On the page

**Layout.** On a phone: four stats in a row, then search, filter and
**+ Person**, then the people as small cards, two to a row (three from 640px).
From 1000px it follows the WildcatsXC meets view: stats and controls sit in a
280px column on the left that stays in view while scrolling, and the cards fill
the right. People are always in name order.

- **A card** is a drop-down with no arrow or buttons on it. Closed, it shows
  only the person's name, whether they still need a gift, and how many open
  ideas they have (💡 and a count); tap the name to open it to one line per
  idea and an add box at the bottom. **Hold** the card (500 ms, or right-click
  on a computer) to rename or remove the person; a hold on the card's own
  controls (status pills, idea names, links, the add box) does not count, and
  moving the finger, as in a scroll, cancels it. Cards start closed, and which are open is remembered
  per device in `localStorage` (`gifts_open`). A search opens every matching
  card without changing what is remembered, and adding a person, or moving an
  idea to someone, opens that person's card.
- **Status pill** on each idea is a button that cycles idea → bought → given →
  idea. An open idea shows only a 💡, to keep cards narrow; bought and given
  keep their word, because they are the coloured states and colour is never
  shown alone. Tapping the idea's name opens the edit window: the
  name as its top line, link and price on one line, and notes that start as
  one line and grow. Status and person are not in it: the slide and the pill
  set the status, an idea stays with the person it was added under (to move
  one, add it again under the other person and delete this one), and a save
  keeps both as they are. The 🗑 deletes after a confirm, Save saves, and
  a tap outside or a swipe down closes without saving (every dialog on the page
  closes that way). ↗ opens the link.
- **Slide** an idea to the right to mark it bought, or a bought one to the
  left to put it back to an idea. Green with "Bought" (or "Idea") shows behind
  the row as it moves; past the threshold (40% of the row, at most 90px) it
  commits, short of it it springs back. A drag that starts mostly up or down
  stays a scroll, and ideas under Given before do not slide. Marking a bought
  gift as given is still a tap on its pill.
- **Quick add** takes a line of text and adds it on Enter; there is no button. A pasted link becomes the idea's link and
  the rest of the line its name; a bare link is named after its site until
  edited.
- **Needs a gift / Got one** filters on whether a person has an idea marked
  bought (and not yet given). Given gifts fold into "Given before" under each
  person, newest first.
- **Spent in YEAR** adds the price of everything marked bought or given during
  the current calendar year, rounded to the dollar. The filter is remembered
  per device under `gifts_filter`.
