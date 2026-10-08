# WildcatsXC

Cross country meet results, read off a results sheet by Claude, checked by
hand, and filed per meet so each athlete builds up a season record. The
scanner and the record come first; trend dashboards are meant to be built on
top of the same tables.

**File:** [`wildcatsxc.html`](../../wildcatsxc.html)
**Tables:** `xc_meets` `xc_athletes` `xc_results`
**Auth:** shared team PIN (`xcRequirePin()`, header `X-XC-PIN`) on every endpoint

## One team record behind a PIN

There are no accounts. Every coach with the PIN sees and edits the same meets
and athletes, so the tables carry no owner column.

Results name minors, so nothing here is open:

- **The PIN is `XC_PIN` in the server `.env`**, never in the repo — the repo is
  public, and a PIN in the source is a PIN anyone can read. Unset, every
  endpoint answers 503, so a missing config fails locked rather than open.
- **Guessing is rate-limited.** Four digits is 10,000 guesses, so each wrong
  PIN is logged in `xc_pin_attempts` by IP; ten in an hour locks that address
  out until the hour passes. The lockout is checked before the PIN is
  compared, so further tries from a locked-out address reveal nothing.
- **The app remembers a PIN that worked** under
  `localStorage['wildcatsxc_pin']`, and drops it on any 401 — which is how
  changing `XC_PIN` signs every device out. The Lock button forgets it on
  purpose, for a shared or borrowed phone.

There is no separate PIN-check endpoint: the app calls `xc_meets`, which
loads the list on the right PIN and returns 401 on a wrong one.

## From sheet to saved meet

1. **Scan** (`xc_scan`) — Claude (`claude-opus-5-5`) returns the meet name,
   date, course and every race's finishers. Nothing is saved, and the files are
   never written to disk. **The app sends one request per photo and one per
   PDF page** (see below), three at a time, and stitches the parts back
   together in document order.
2. **Check** — the app shows every row grouped by race, editable. A time that
   was unreadable comes back blank and the row is marked red: the prompt asks
   for a blank rather than a guess, because one misread digit becomes a fake
   PR or a fake bad day on the trend line.
3. **Save** (`xc_save_meet`) — validated again server-side, written in one
   transaction.

**Why a page at a time.** A whole meet PDF in one request is minutes of
output, and the host does not let a PHP request run that long: a 7-page,
318-runner PDF died at 97 seconds as a bare 500 with an empty body. So
`scanFiles()` sends page 1 first — every reply carries `page_count` — then
queues the remaining pages, three in flight (`inPool()`). Each request reads
one page (`xc_scan` with `page`) in roughly half a minute. The PDF goes up with
every page request but is marked for prompt caching, so after the first it is
a cheap cache read. A page that fails, or holds only team scores, becomes a
note in the review rather than failing the whole upload. Two safety nets stay
in `xc_scan` regardless: it reconnects to MySQL if the read outlasted the
connection, and a shutdown handler turns any PHP fatal into a JSON error the
app can show.

**The school filter** ("Only these schools", remembered per device) is passed
into the prompt so the model skips everyone else — for relevance, and it also
shortens each page's read.

**Add a page** appends a second scan to the meet open for review — for a sheet
that runs across pages photographed separately.

## Importing a MileSplit link

**MileSplit link** (and **Add from MileSplit** in the review) takes a results
link copied from the browser's address bar. `xc_milesplit` makes two requests
to the one meet the link names:

1. the results page, for the meet date (`startDate`), the course from the meta
   description, and `meetResultFiles` — which results files exist and which
   are PRO-only;
2. `/api/v1/meets/{id}/performances` for each non-PRO file — the same JSON
   MileSplit's own page loads, unauthenticated.

The link's `event`, `gender` and `division` parameters pick the race, so a link
to "Boys 5000m Varsity" imports just those finishers; a link without them
imports every race (each named division + gender, e.g. "JV Flight 1 Girls").
Grade comes from `gradYear`: a fall meet belongs to the school year ending the
next June, so a 2029 graduate racing in September 2026 is grade 10. Runners
with a status code (DNF, DNS, DQ) or no usable time are left out and counted
in the note. The rows go through `xcMatchRows()` and come back in `xc_scan`'s
shape, so the app reuses the scan path (`takeRows()`), and the "Only these
schools" filter applies in the browser.

**Rules this feature keeps — do not loosen them:**

- **User-initiated only.** One pasted link, one import. No saved links, no
  refresh, no schedule, no following links to other meets. MileSplit's terms
  discourage scraping; the owner accepted the risk on exactly these terms.
- **No workarounds.** Requests identify as this site (`xcMilesplitGet()`), not
  as a browser, and send no MileSplit app or user token. If MileSplit blocks
  that, adds a captcha, or moves results behind PRO, the import fails with a
  message — it never works around it. PRO-only files are skipped, not fetched.
- **No open fetcher.** The pasted URL is only parsed for host and meet id; the
  addresses actually fetched are rebuilt from those, the host must be
  `milesplit.com` or a subdomain, and redirects may not leave it.

This rides on an unofficial API, so expect it to break without notice, the way
ESPN does for the Confidence Pool. Pasting and the official results file are
the fallbacks.

## Pasting results

The other way in: copy one race off a results site (MileSplit's formatted
results, one event and division per page) and paste it. **Paste a race** adds
another race to the meet open for review, so a meet's races go in one after
another.

Parsing happens in the browser (`parseLine()`), not through Claude — pasted
text is regular enough that a model read would only add cost and minutes.
`xc_match` then runs the rows through the same `xcMatchRows()` a scan uses, so
pasted runners get the same known / new / "Same as …?" tags.

`parseLine()` takes two paths, because sites copy differently and order their
columns differently:

```
MileSplit, spaces:      318 Jameson Rothwell SO Whitewater 21:53.20 318
                        place name ........ grade school   time    points
Athletic.net, tabs:     1.<tab>12<tab>Noah Gailey<tab><tab>15:58.2<tab><tab>Hartford Union
                        place  grade  name                 time             school
```

**Tab-separated (`parseTabbed()`)** — copied out of an HTML table, so every
cell is whole, and cells are classified by what they look like rather than
where they sit: the time is the last time-shaped cell, the place a number in
the first cell, the grade a grade-shaped cell (FR/SO/JR/SR or 9–12) before the
time; points and marks like PR/SR are skipped; what is left is text, and the
first text is the name. That one rule covers MileSplit's order and
Athletic.net's (grade before the name, school after the time). Three text
cells read as First | Last | School.

**Space-separated (`parseSpaced()`)** — here the grade is the anchor. Names and
schools are both often several words ("Mary Kate O'Neil", "Sun Prairie East"),
so neither can be found by position: the name is everything between the place
and the first grade token (at least two words in), the school everything
between the grade and the time. The time is the last time-shaped token, so
trailing points or a PR mark don't matter. If no grade follows the name but one
leads it, the line is read as Athletic.net's order with the tabs lost — grade,
name, time, school. Space-separated lines without a grade are ambiguous and
skipped — reported in the review with an example, never guessed.

Either way, "Last, First" is reordered.

The race name and distance come from the paste box (default 5000m), and the
"Only these schools" filter applies, matched on the normalised school name
containing a filter term.

## Athlete identity

The season record depends on the same runner on two sheets being one
`xc_athletes` row. The key is `match_key` = normalised name + `|` + normalised
school (`xcKey()`): lowercased, punctuation folded, and school suffixes like
"HS" / "High School" dropped, so "Madison West HS" and "Madison West" match.

Matching is **exact on that key, never fuzzy**. A scan does two softer things,
both visible and both only suggestions:

- a school whose normalised form matches one already stored takes the stored
  spelling;
- a new name within two edits of a stored teammate at the same school (same
  first letter, at least five characters) comes back with `similar` set, shown
  as a "Same as …?" button. The coach decides. Auto-merging would silently
  fuse teammates who are a letter apart, and with one result per athlete per
  meet, one of their times would overwrite the other.

On save the latest spelling of a name wins, so fixing a name once fixes it on
every meet. Athletes left with no results after a save or delete are removed
(`xcPruneAthletes()`).

## Saving semantics

| Call | Behaviour |
|---|---|
| `xc_save_meet` with `meet_id` | Edit: the rows **replace** that meet's results, so a renamed runner does not leave an old row behind |
| without `meet_id`, same name + date exists | Added to that meet; a runner already in it is updated (`UNIQUE (meet_id, athlete_id)`) |
| without `meet_id`, new | Creates the meet |

The same runner twice in one save is rejected rather than collapsed.

## Times

Stored as `time_ms`. `xcParseTime()` accepts `17:23`, `17:23.4`, `17:23.45`
and `1:02:03.5`, bounded to 1 minute – 2 hours, which also catches a place
number that landed in the time column. `xcFormatTime()` writes them back the
way sheets print them, tenths or hundredths only when they were there. The
front end has a copy of the parser so a bad time is flagged while typing; keep
the two in step.

## The reader

`xcReadResults()` differs from the pick-sheet reader on purpose:

- **Streamed** — a long read on a silent non-streamed connection is what
  proxies drop. Text is collected per content block and only the last text
  block is parsed. Streaming does not lift the host's own request limit,
  which is why PDFs are also split by page.
- **`page` reads one page of a PDF** and adds instructions to carry a race
  name across a page break. The document block carries `cache_control`, with
  the page-specific prompt after it, so the PDF is the cached prefix.
- **`fallbacks: "default"`** (beta `server-side-fallback-2026-07-01`) — a
  refused request is retried server-side on another model. When that happens
  mid-stream the refused model's partial text stays in the stream ahead of the
  fallback's answer, which is why only the last text block counts.
- **Images at 2576px**, the high-resolution models' limit, through
  `cpPrepareImage($bytes, $type, 2576)` — results pages are far denser than a
  pick sheet.
- **Effort `medium`** set explicitly. Raise it if reads of rough photos come
  back with gaps; lower it if speed matters more.

## Front-end notes

- Rows are flat in state and each carries its race; the overlay groups them.
  Renaming a race applies to every row in it, and renaming it to match another
  race merges the two.
- **Delete race** (shown only when a meet has more than one race) drops every
  row of that race from the review after a confirm. It is a local edit like
  any other: nothing reaches the server until Save, Cancel discards it, and
  because saving an existing meet replaces its results, the race's stored
  results go when the meet is saved. Deleting the last race is Delete meet's
  job, so the button is not offered there. Both share `askConfirm()`.
- **Races fold.** The chevron in a race's header hides its runners and shows
  a one-line summary instead — runner count, plus "N to fix" in red for rows
  that cannot be saved (`rowProblem()`: no name, no school, or no valid time),
  so folding never hides a problem. Folded races live in `review.collapsed`,
  keyed by race name, so the state survives re-renders and a rename carries
  it across. Anything that needs a row on screen opens the race first: "+ Add
  runner", and Save when validation fails on a row inside a folded race.
- Field edits update state without re-rendering so typing keeps focus;
  structural changes (remove, add, accept a suggestion) re-render.
- Theme under `localStorage['wildcatsxc_theme']`, school filter under
  `wildcatsxc_schools`.
