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

1. **Read** — results come in three ways and **never as a photo** (the owner
   removed photo upload): a **PDF** (`xc_scan`), a **MileSplit link**
   (`xc_milesplit`), or **pasted text** (`xc_match`). For a PDF, Claude
   (`claude-opus-5-5`) returns the meet name, date, course and every race's
   finishers. Nothing is saved, and the file is never written to disk. **The
   app sends one request per PDF page** (see below), three at a time, and
   stitches the parts back together in document order. `xc_scan` accepts
   only PDFs — by extension *and* by the file's `%PDF-` header, so a photo
   renamed `.pdf` is turned away — and the app checks before uploading,
   since some phones ignore the picker's PDF-only filter.
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

**Add a PDF** appends another PDF to the meet open for review — for a meet
whose races were posted as separate files.

## The Meets list

Opens on **only the meets our school ran** — a checkbox, "Only Verona Area
meets", ticked by default; unticking shows every meet entered. The choice is
remembered per device (`localStorage['wildcatsxc_meets_all']` is set only
when it is off). With it on, each meet's count reads "our runners of all
runners" (40 of 397), and the heading says how many meets are hidden
("6 meets of 7").

**"Our school"** is `ourSchool()`: the Team tab's pick, else `HOME_SCHOOL`
(Verona Area). The same helper decides the highlighted rows and orange line on
Conference and Section, so all three always agree; picking another team on the
Team tab changes the Meets filter too, and the label says which school it is.

`xc_meets` returns each meet's runners by school (`schools`: name → count),
from a second grouped query rather than `GROUP_CONCAT`, whose 1 KB default
would silently truncate a big invitational's school list. Schools are matched
loosely (`schoolKey()`), as on every other tab.

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

**PT Timing (`parsePtTiming()`)** copies as five lines per runner, so it is
tried first, on the whole paste, before the line-by-line parsers:

```
1<tab>Benjamin Motelet          place, name
Verona Area [SR] - 7760         school, [grade], bib
16:44.7                         time
-                               gap to the leader ("+6.6")
1                               team-score place
```

A runner starts at a "place name" line followed by a "School [GR]" line —
that pair is how the format is recognised, and MileSplit or Athletic.net
pastes never contain it. The time is the next time-shaped line (one stray line
allowed, never reaching into the next runner); bib, gap and score are ignored.
A runner with no time (DNF, DNS) is left out and counted in the review note.

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

**Races under 5K are saved as 5K.** Every trend, PR and team table compares
times at one distance, so a 3K or 2-mile race would otherwise sit on its own
line. `xc_save_meet` converts each time in a race of 1000–4999 m with Riegel's
formula, `T × (5000 / d)^1.06` (`xcTo5k()`), rounds to tenths, and stores the
race as 5000 m. The original distance and times are not kept, and because the
saved race is then 5000 m, re-saving the meet does not convert it twice. The
review shows a note on any race that will be converted. A distance under
1000 m is refused as a typo (3 meant as 3000) instead of being converted;
0 means unknown and is left alone.

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
- **Effort `medium`** set explicitly. Raise it if reads of scanned PDFs come
  back with gaps; lower it if speed matters more.

## Team tab

The first of the metrics views, and deliberately a plain one: one school's
season laid out as a grid — a runner per row, a meet per column, a section per
squad — so every time is visible before anything is summarised.

- `xc_schools` fills the team picker (most results first); `xc_results`
  returns that school's results flat, and the page arranges them. The last
  team picked is remembered in `localStorage['wildcatsxc_team']`.
- **Squad is decided per runner** (`squadsByRunner()`), not per race. Runners
  carry no gender, so it is read off race names (`squadOf()`: "Varsity Girls"
  / "Women" → Girls, "Boys" / "Men" → Boys; Girls tested first because
  "women" contains "men"). But many sheets name a race just "Varsity" or
  "JV1", and splitting race by race cut those runners' seasons in two — in
  the first real data, 82 of Verona's 203 results sat in a separate "Other"
  section. Two facts settle almost everyone: a runner only races one gender's
  races, so any named race of theirs places all of them, across every season
  and distance; and everyone in one race is the same gender, so an **unnamed
  race takes the gender of the runners in it already known** from elsewhere,
  which then places the rest of that field. The second step repeats until
  nothing changes. It matters most across schools: on the Conference tab, all
  of Janesville Craig's and Parker's runners came from one unnamed Midwest
  Invitational race and would otherwise be unplaceable (with it, all 490
  runners in the first real data were placed). Only runners whose races stay
  unknown land in "Other races" — naming one of their races (edit the meet)
  moves them.
- **Season and distance filter the whole page.** A time at 4K and one at 5K
  are not comparable, so only one distance is shown — the one the team ran
  most — with a picker that appears only when there is a choice (the season
  picker likewise).
- Columns: runner (with their latest recorded grade), races run, season best,
  then each meet in date order. Sorted fastest season best first; the season
  best is bold in its meet column too. A dash means the runner did not race
  that meet at this distance.
- The grid scrolls sideways inside its card with the runner column pinned
  (`position: sticky`). That needs `min-width: 0` on the layout's grid items —
  without it a grid item grows to fit the table and pushes the card off the
  page.
- **Names are shortened to "First L."** (`shortNames()`) — the pinned name
  column is what squeezes the times on a phone. The initial comes from the
  last word, skipping Jr./III, so "Mary Kate O'Neil" is "Mary O.". Within a
  section no two runners may look alike: colliding names take more letters
  ("Ryan Ho."), compared ignoring case and the trailing period, and names
  still identical after the whole last name show in full. The full name is in
  the cell's title (hover) and aria-label. Only this grid shortens names; the
  edit screen always shows them in full, since that is where they are fixed.
- **Progression chart.** Each open section has a chart above the grid,
  faster plotted higher. It opens on the **team view** (`drawTeamChart()`):
  two lines per meet, the **top-5 average** (the scoring five, whichever race
  they ran; blank at a meet with fewer than five) and the **whole-team
  average** (every squad runner there, varsity and JV). The owner chose that
  pair over a top-7 or median line. The whole-team line moves with how many JV
  were entered as much as with how anyone ran — the caption says so and the
  tooltip gives the runner count. On one axis the two sit minutes apart, so
  each line's meet-to-meet movement reads flatter than it would alone.
- **Tapping a row** (or Enter on it) switches to that runner
  (`drawRunnerChart()`): their time at each meet, season best labelled, axis
  fitted to their times alone. Team figures for the day — top-5 and team
  average — ride in the tooltip, not as lines: drawn on the runner's axis an
  earlier team median line sat minutes away and squashed the runner's line
  flat. **Back to the team**: the "← Team averages" button, or tapping the
  charted runner's row again. Switching never re-renders the grid, so one
  scrolled sideways stays put.
- **Chart mechanics.** Hand-drawn SVG (`lineChart()`), no library. The axis
  runs tick to tick (`tickStep()`), so every line has a labelled gridline above
  and below it. **Orange (`--series-1`) is Verona's school colour** — a
  runner, the top five, the coach's school — and blue (`--series-2`) the whole
  team or a school picked for comparison (the owner swapped them from the
  original blue-first order). The pair passed the dataviz palette validator
  against `--surface` in both themes, with light orange just under 3:1, so
  orange lines always carry a direct label (end labels, "SB", "Top 5") as well
  as the legend, and keyboard focus rings use the text colour rather than
  `--series-1`, since a focus indicator needs 3:1.
  Raw times still carry course differences, and neither view adjusts for them.
- **Reading a meet off the chart** works three ways, and they differ because
  the devices do. *Mouse*: a crosshair follows the pointer, snapping to the
  nearest meet, and leaving hides it. *Touch or pen*: there is no hover —
  a tap sends pointerdown/up and then an immediate pointerleave, no
  pointermove — so a tap shows the nearest meet and the tooltip stays until
  another tap moves it or a tap anywhere outside the chart closes it (some
  phones never focus an SVG, so blur cannot be the close signal); dragging
  still scrolls the page. *Keyboard*: focusing the chart opens the latest
  meet, arrows step, Escape closes. A tap also focuses the chart, so a flag
  set on pointerdown and cleared on pointerup keeps that focus from jumping
  the tooltip to the latest meet. The first version handled only mouse
  movement, so taps on a phone showed the wrong meet or nothing.
- **Squads fold.** Each section's title row is its toggle (chevron, label,
  runner count stay visible when folded). Folded squads are remembered on the
  device in `localStorage['wildcatsxc_team_folded']`, keyed by squad name — a
  coach of one squad folds the other once, and it stays folded across visits,
  schools and seasons. A convenience only: nothing about it reaches the server.

## Conference and Section tabs

Two tabs, one view: each combines a fixed list of schools into one ranked list
per squad — **each school's 10 fastest runners by season best, ranked
together**. Columns: rank and runner (pinned), season best, **average** (the
mean of every race that runner ran in the chosen season at the chosen
distance), school, races. Ranking stays by season best; a runner with one race
shows the same time in both. Averages always show the tenth (`tenths()`:
"16:49.0", not "16:49") so the column lines up; times as printed go through
`fmtTime()`, which keeps their own precision.

- **The school lists live in `GROUPS`** in `wildcatsxc.html` — the Big Eight
  for Conference, the WIAA sectional for Section. Edit them there when the
  conference or the sectional assignment changes (sectionals change yearly).
  Names are matched to the stored ones loosely (`schoolKey()`: case, spaces and
  "High School" ignored), so "Madison La Follette" also finds
  "Madison LaFollette". The section list as first given had typos ("Sun Prarie
  East", "Sun Praie West"); the list holds the correct spellings.
- **One request per tab**: `xc_results` takes `school[]=…` repeated and returns
  every listed school's results, each row carrying its school. Cached per tab
  until a meet is saved or deleted.
- **Boys and girls** use the same per-runner rule as the Team tab
  (`squadsByRunner()`), which is where the unnamed-race step earns its keep.
- **The coach's own school** — whatever is picked on the Team tab — is tinted
  with a bar on the runner cell. The school column names it too, so colour is
  never the only cue.
- **Coverage is stated, not hidden.** A school with fewer than 10 runners
  simply contributes fewer (Beloit Memorial had 5 at first), and a school with
  no results this season and distance is named under the heading — a ranking
  should never look complete when it isn't.
- Best and average sit right after the runner so they stay on screen on a phone; school and
  races scroll sideways. Season and distance filters behave as on Team, and
  squad folding is shared with the Team tab.

### Weekly top-5 chart

Each squad section on Conference and Section opens with **every school's
top-5 average by week** (`weeklyTop5()`, `drawGroupChart()`).

- **The numbers.** Weeks run Monday–Sunday and are labelled by their Monday.
  A runner counts once a week, at their best time that week across any race at
  the distance; the school's five fastest of those are averaged. Under five
  runners that week, the school has no point. A school with points in only one
  week shows as a dot, not a line.
- **Every school is drawn, but not every school gets a colour.** A dozen hues
  cannot be told apart, least of all under colour-blindness, so the coach's
  school (the Team tab's pick) is orange, Verona's colour, with dots, and every other school is a
  thin gray line, with small dots, to compare against. Gray lines first had no
  dots, and they started and stopped in mid-air beside other schools' lone
  dots — it read as lines failing to reach their points. The chips above the chart — one per
  school, in the list's order — pick one school out in blue; tapping it again
  puts it back. Emphasised lines carry end labels (capped at 30% of the width,
  shortened with "…" if they do not fit). The tooltip lists every school that
  week, fastest first.
- **A picked school is picked out in the ranked table too** (`syncPicked()`):
  its runners take the same blue tint and bar as its line, and the table's
  subtitle names it. The pick is shared by Conference and Section, so it
  carries across the two. Picking redraws the chart and toggles a class on the
  table rows in place — the table is never re-rendered, so a scrolled table
  stays put. The coach's own school can't be picked (its chip is not a
  button), so orange and blue never land on the same row.
- **Outliers do not set the scale** (`fence: true` on `lineChart()`,
  `upperFence()`). A point beyond Q3 + 1.5 × IQR of all the chart's points is
  drawn as a small marker on the bottom edge, its line breaks there rather than
  diving to the edge, and the tooltip keeps its real value marked "off the
  chart". In the first real data Beloit Memorial's only week averaged 26:31
  against a field within three minutes of each other; on one axis it squashed
  every other school into a sliver.
- **Courses differ within a week.** Schools racing different meets the same
  week ran different courses; the caption says to read the trend rather than
  any single week. The chart does not adjust for it.

## Screen sizes

The phone layout is the base; wider screens use the room rather than
centring a phone-width column. The page grows to 1280px, and:

| From | What changes |
|---|---|
| 900px | Check/edit screen widens to 1100px; meet name, date and course share a line; **each runner is one line** (place, name, grade, school, time, tag, remove) instead of two — the markup is unchanged and CSS grid areas reorder it, with the column header naming each one |
| 1000px | Meets tab: the add-a-meet panel becomes a 340px column that stays in view (sticky), the meets list beside it |
| 1200px | Conference / Section: the weekly chart and the ranked table sit side by side, the chart sticky while the table scrolls |

Charts grow taller with width (`drawLine()`: 210px under 560px wide, up to
320px), so a full-width desktop chart is not a flat ribbon. The Team tab's
season grid needs nothing extra — the width alone shows more meet columns
before it scrolls. The locked (PIN) card stays at 560px.

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
