# Finance (unlisted)

The owner's private money check-up: take-home pay, recurring costs and 401(k)
saving, rolled up into monthly figures and a few rule-of-thumb health checks.
One person, one vault. Like Stats, it is deliberately not on the home grid.

**File:** [`finance.html`](../../finance.html)
**Tables:** `fn_vault`, `fn_attempts`
**Auth:** `X-FN-Key`, an access key derived from the owner's passphrase,
checked by `fnRequireKey()` against `FN_KEY_HASH` in the server `.env`

## The server never sees a number

Everything is encrypted in the browser before it is sent. The passphrase is
stretched with PBKDF2-SHA256 (600,000 iterations, fixed salt
`davenn.com/finance/v1`) into 512 bits, split in two:

- **bits 0–255** → the AES-GCM key. Never leaves the browser.
- **bits 256–511** → the access key, sent as `X-FN-Key` on every request. The
  server stores only its SHA-256 (`FN_KEY_HASH`) and compares against that.

The two halves are independent, so the server can tell the right passphrase
from a wrong one without being able to decrypt anything. `fn_vault` holds one
row whose `ciphertext` is `v1.<iv>.<ciphertext>` in base64; a database dump
or a GoDaddy backup reveals nothing.

The salt is fixed because there is one user and nothing to look a per-user
salt up by before authenticating. PBKDF2's cost and the per-IP limit below are
what slow down guessing.

## Setup and the passphrase

With `FN_KEY_HASH` unset, both endpoints answer 503 and the app is locked, the
same way WildcatsXC behaves without `XC_PIN`. On that 503 the unlock form asks
for the passphrase again (at least 12 characters), then shows the exact
`FN_KEY_HASH=…` line to add to `.env` on the host. Nothing is committed.

**There is no reset.** Lose the passphrase and the data is unrecoverable —
that is the point of the design, not a gap in it.

To **change** the passphrase: `Back up` (exports plain JSON), put the new
passphrase's line in `.env` (run the setup flow by temporarily removing the old
line, or derive it the same way), unlock with the new passphrase, and
`Restore` the backup. Unlocking with the new passphrase over data encrypted
with the old one shows a notice that the saved data cannot be read; the first
save, or a restore, replaces it.

Ten wrong keys from one address in an hour lock that address out for the rest
of the hour (`fn_attempts`, pruned after a day).

## Remembering a device

"Remember on this device" keeps the derived keys (not the passphrase) in
`localStorage['finance_keys']`; otherwise they live in `sessionStorage` for the
tab. `Lock` clears both. The last ciphertext seen is cached in
`localStorage['finance_cache']`, so the app opens read-only offline; edits made
offline retry every 15 seconds.

## Syncing two devices

`fn_save` takes the `version` the page loaded. If another device has saved
since, the server writes nothing and answers 409; the page asks whether to
keep what is on screen (it then saves over the newer copy) or load the other
version. Returning to the tab re-fetches when nothing is unsaved, so a phone
picks up laptop edits without a reload.

## The numbers

Pay is **take-home only**: what lands in the bank, after tax and the 401(k)
deduction. Every line is normalised to a month (`PER_MONTH`: weekly × 52/12,
every two weeks × 26/12, twice a month × 2, quarterly ÷ 3, yearly ÷ 12).

- The **Paycheck** income line always exists (id `pay`) and cannot be removed:
  401(k) contributions are entered per paycheck and follow its schedule.
- Recurring costs in the **Savings** category (Roth, HSA, brokerage) count as
  saving, not spending.
- **Left over** = take-home − costs − savings transfers. The 401(k) is not
  subtracted, because it never reached take-home.
- **Retirement saving %** = (yours + match) ÷ (take-home + yours). Gross pay is
  not entered, so this denominator is smaller than gross and the figure reads
  high; the page says so.
- **Emergency fund** = cash savings ÷ monthly recurring costs (excluding the
  Savings category).
- 401(k) **projections** use monthly compounding at the entered yearly return,
  in nominal dollars. Logged balances (one per day) draw the history chart.

Health thresholds: costs ≤ 50% of take-home Good, ≤ 65% Watch; cushion ≥ 10%
of take-home Good, ≥ 0 Tight; retirement ≥ 15% On track, ≥ 10% OK; emergency
fund ≥ 6 months Good, ≥ 3 OK. Every status is a word as well as a colour.

## Backups

`Back up` downloads the decrypted data as plain JSON, deliberately: it is the
way back in after a passphrase change and is readable without this page. The
file is sensitive. `Restore` accepts the same shape and runs it through
`normalize()`, which is also what every server read goes through.
