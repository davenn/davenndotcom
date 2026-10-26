# Cali's Movie List

A watch list of tearjerkers and feel-good favorites. Each film has a tissue
rating, a one-line vibe and its Rotten Tomatoes scores. Check one off once
watched, then record how many tissues it actually took. New movies are added
from the page, with Claude drafting the details from a title.

**File:** [`movielist.html`](../../movielist.html)
**Tables:** `ml_movies`, `ml_watched`, `ml_draft_log`
**Auth:** none. Anyone can add or remove a movie, by the owner's choice

## Two sources, one list

`MOVIES` in the script is the starting list of 21: title, year, a TMDB
`poster` path, a fallback `emoji`, `tissues` (1–5), `vibe`, `tags`, and two
Rotten Tomatoes numbers, in the order Cali gave them. Changing it means editing
the file.

Movies added from the page live in `ml_movies` and come back from
`ml_movies` in the order they were added. `all()` appends them after the
built-ins, so list order is still "the order they joined the list". Their ids
are `'ml' + row id`, so they cannot collide with a built-in id, and watched
state keys off that id.

The last fetched copy of the added movies is kept in `localStorage` under
`movielist_added`, so they still show offline and appear before the fetch
returns. The fetch replaces it on every load.

## Adding a movie

`Add Movie` shows a one-line form just above the list, a title box and
`Submit`, and turns into `Cancel` while the line is open.

`Submit` on a title sends it to `ml_draft`, which asks Claude for the year, an
emoji, a tissue rating, a vibe and tags, in the voice of the existing entries
and with no spoilers, then passes that draft straight to `ml_add`. There is no
review step: the form is deliberately one line. When Claude does not recognize
the title, `found` is false, the note is shown under the line, and nothing is
added.

So added movies have no Rotten Tomatoes scores and no poster. Neither is
drafted, on purpose: a remembered score is often a few points off, and a
remembered image URL is usually invented. `ml_add` still accepts `critics`,
`audience`, `rt` and `poster`; the page just does not send them. A card with
no scores leaves the scores row out, and one with no poster shows its emoji.

Added movies (not the built-ins) carry `Remove from list`, which asks to
confirm and calls `ml_delete`.

## No PIN, and what bounds the cost

Adding was PIN-gated at first; the owner chose to open it. The page is public,
so anyone who finds it can add or remove movies and check them off. The only
server-side limit is on `ml_draft`, because each draft is a paid Claude call:
`mlDraftLimit()` allows 30 per address per hour, logged in `ml_draft_log` and
pruned after a day. `ml_add`, `ml_delete` and `ml_watch` are cheap and
unlimited.

## Poster art

For built-ins, `poster` is the file part of a TMDB image path, hotlinked from
`image.tmdb.org` with a size segment in between (`w185` on phones, `w342`
otherwise, through `srcset`). The path comes from the film's page on
themoviedb.org (its `og:image`); match the title and year there, since TMDB ids
are easy to get wrong. Added movies may instead carry any https image link,
used as is.

The emoji sits under the image. If the image fails, which is normal offline
because the service worker does not cache cross-origin images, `onerror`
removes the `<img>` and the emoji shows instead. The footer credits TMDB.

## Rotten Tomatoes scores

`critics` is the Tomatometer and `audience` is the Popcornmeter. For the
built-ins both were copied by hand from each film's RT page and the footer
dates them. They are not fetched live: RT has no public API, and these numbers
move by a point or two a year at most.

`rt` is the page slug, and it matters more than it looks. Several titles here
share a name with another film (`my_sisters_keeper` is a 2002 TV movie and
`remember_me` is a different film), so the slug pins down which one the scores
came from. `ml_add` accepts a whole RT link and keeps only the slug.

Fresh/Rotten follows RT's line at 60%. The colors are fixed, not themed, and
always come with the word, because RT's own convention (red is good, green is
bad) is the reverse of what anyone expects. Sorting by a score puts movies
with no score last.

## Tissue ratings

`tissues` is an opinion, not data from anywhere. The words for each level live
in `TISSUE_WORDS`. The shown rating is ours (Claude's, for added movies);
the "Your tissues" row under a watched film is Cali's.

## Watched state

Which movies are checked off, and Cali's tissue count for each, live in
`ml_watched`: one row per watched movie, keyed by the page's movie id, so a
built-in (`dps`) and an added movie (`ml12`) are stored the same way. There is
one list and no accounts, so every device and every visitor sees the same
checkmarks. `ml_movies` returns this as `watched` alongside the added movies,
so one request on load brings in everything the server holds.

Checking, un-checking and tapping a tissue count go through `saveWatch()`: the
card changes at once, then `ml_watch` saves it. If the save fails the change
is undone and the reason shows above the list. The last fetched copy is kept
in `localStorage` as `movielist_watched` so the page shows it offline, but it
is a cache; the server is the record. `ml_delete` removes an added movie's
watched row along with it.

Filter and sort are each device's own view, so they stay in `localStorage`
as `movielist_view`.

## Front-end notes

- The checkbox in each card's top-right corner is a real `<input
  type="checkbox">`, styled with `appearance: none`, and handled on `change`.
  Unchecking deletes the entry, including Cali's tissue count.
- "Pick one for me" draws from unwatched films (all of them once everything is
  watched), switches the filter if the pick would be hidden, then scrolls to and
  highlights the card. A newly added movie gets the same treatment.
- The poster column is 120px (92px below 480px) and stretches to the card's
  full height, cropped with `object-fit: cover`.
