# Finance (unlisted)

The owner's private money check-up: take-home pay, recurring costs and 401(k)
saving, rolled up into monthly figures and a few rule-of-thumb health checks.
One person, one vault. Like Stats, it is deliberately not on the home grid.

**File:** [`finance.html`](../../finance.html)
**Tables:** `fn_vault`
**Auth:** `X-Admin-Secret`, the same secret as Stats, checked inline in each
branch like every other admin endpoint

## One secret, two jobs

The admin secret unlocks the two endpoints, exactly as it unlocks Stats. The
page also stretches it with PBKDF2-SHA256 (600,000 iterations, fixed salt
`davenn.com/finance/v1`) into the AES-GCM key that encrypts the vault in the
browser. `fn_vault` holds one row whose `ciphertext` is
`v1.<iv>.<ciphertext>` in base64.

What that buys, and what it does not:

- **A database dump or a hosting backup of the DB reveals nothing.** The
  secret is not in the database.
- **Someone with both the server `.env` and the database could decrypt it.**
  The server knows `ADMIN_SECRET`, so it can derive the key. Owning both
  already means owning the site, so this was accepted in exchange for one
  secret instead of a separate passphrase. (The first version used its own
  passphrase, whose key the server never saw; that was swapped for the shared
  secret at the owner's request.)
- **Changing `ADMIN_SECRET` orphans the vault.** The new secret derives a
  different key. Back up first, change the secret, unlock with the new one and
  restore. Unlocking over data the key cannot read shows a notice saying so;
  the first save, or a restore, replaces it.

The salt is fixed because there is one user and nothing to look a per-user
salt up by before authenticating.

## Remembering a device

"Remember on this device" keeps the secret and the derived key in
`localStorage['finance_keys']`; otherwise they live in `sessionStorage` for the
tab. This is separate from Stats' own `stats_secret`, so `Lock` here does not
sign Stats out, and signing in to Stats does not unlock this. `Lock` clears
both stores. The last ciphertext seen is cached in
`localStorage['finance_cache']`, so the app opens offline; edits made offline
retry every 15 seconds.

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
way back in after the admin secret changes and is readable without this page. The
file is sensitive. `Restore` accepts the same shape and runs it through
`normalize()`, which is also what every server read goes through.
