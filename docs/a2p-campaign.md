# A2P 10DLC campaign registration

The site sends SMS from **two different Twilio numbers**, so there are **two
separate campaigns**. Each number's traffic must be registered against the
campaign that describes it — describing messaging a number does not send, or
omitting messaging it does, is a compliance problem even when it is approved.

| Campaign | Number | Direction | Opt-in |
|---|---|---|---|
| [A. Update Notifications](#campaign-a--update-notifications) | updates number | outbound broadcast | web form with consent checkbox |
| [B. Confidence Pool](#campaign-b--confidence-pool) | pool number | two-way conversational | the person texts the number first |

Kept in the repo because a reviewer compares the campaign description, the
sample messages, the opt-in flow on the website and the privacy policy
against each other — inconsistency between them is the most common rejection
cause. When you change [`notify.html`](../notify.html),
[`terms.html`](../terms.html), [`privacy.html`](../privacy.html) or the
message strings in `api.php`, change this too.

> **Fill in `[LEGAL NAME]`** — the legal entity registered with Twilio. If it
> is not literally "davenn.com", the description must state the relationship
> or a reviewer reads the website and the brand as two separate businesses.
> That is a named rejection trap.

## Why the last submission was rejected

> The Campaign field does not clearly explain the messaging program. Describe
> who is sending the messages, who receives them, and why the messages are
> being sent. Make sure the description matches your selected campaign use
> case, sample messages, and registered brand details.

The submitted description said what recipients get but never said **who is
sending**, which is the first thing asked for. It also opened with
"ecipients" — a missing leading R. Two required fields, the opt-in keywords
and the opt-in message, were left blank, and neither the help nor the opt-out
message named the brand.

---

# Campaign A — Update Notifications

The number that `sendSms()` sends from (`TWILIO_FROM_NUMBER`). Outbound only.

## Campaign description

```
Messages are sent by [LEGAL NAME], operating as davenn.com, a personal website that hosts a collection of free web apps. Recipients are people who entered their own mobile number into the sign-up form at https://davenn.com/notify.html and ticked a consent box; no numbers are purchased, rented, or obtained from third parties. They receive a text when a new app or feature ships on davenn.com, so they know something new is available to use. Content is limited to product update announcements for davenn.com itself - no marketing for other companies, no third-party or affiliate content. Message frequency varies, typically no more than a few per month, sent only when something new is released. Recipients can reply STOP at any time to stop all messages, or HELP for help. Terms: https://davenn.com/terms.html Privacy: https://davenn.com/privacy.html
```

## Message flow: How do end-users consent to receive messages?

```
End users opt in on a public web form at https://davenn.com/notify.html. The page requires no login and no interaction to display - the form and its disclosures are visible on arrival. The user enters their mobile number and must tick an unchecked consent box reading: "I agree to receive recurring automated text messages and/or emails from davenn.com Update Notifications at the number or address I provide. Consent is not a condition of using anything on this site. Message frequency varies - typically no more than a few messages per month. Message and data rates may apply. Reply STOP to cancel or HELP for help at any time." The consent text links the Privacy Policy and the SMS program terms. The form will not submit without the box ticked. The number then receives a confirmation text. Consent is collected only for the person's own number and is never bought, shared or inferred.
```

## Opt-in method proof

```
Web form, publicly accessible with no login required:
https://davenn.com/notify.html

The page shows the sign-up field, the unticked consent checkbox with its full
wording, the program name, message frequency, cost, opt-out and help
instructions, and links to the privacy policy and SMS program terms.
```

## Privacy policy URL

```
https://davenn.com/privacy.html
```

## Terms and conditions URL

```
https://davenn.com/terms.html
```

## Checkboxes

| Field | Answer |
|---|---|
| Messages will include embedded links | **Yes** — all point to https://davenn.com, no shorteners |
| Messages will include phone numbers | No |
| Content related to direct lending | No |
| Age-gated content | No |

## Sample messages

Each is a changelog entry plus the fixed suffix the code appends.

```
Added Daily Tasks - a daily task tracker with per-task timers and a weekly leaderboard. https://davenn.com/dailytasks.html

davenn.com - Reply STOP to unsubscribe.
```

```
Reaction Test now has SPEED MODE, a faster five-round variant. https://davenn.com/reactiontest.html

davenn.com - Reply STOP to unsubscribe.
```

```
New app: Confidence Pool - photograph your filled-in NFL pick sheet and it reads the picks off the paper. https://davenn.com/nflpool.html

davenn.com - Reply STOP to unsubscribe.
```

```
Toolshare can now identify a tool from a photo when you add it to your library. https://davenn.com/toolshare.html

davenn.com - Reply STOP to unsubscribe.
```

```
Flight Tracker now imports a whole itinerary at once instead of one leg at a time. https://davenn.com/flighttracker.html

davenn.com - Reply STOP to unsubscribe.
```

## Opt-in keywords

```
START,UNSTOP,YES
```

## Opt-in message

Matches `?action=subscribe` word for word.

```
davenn.com Update Notifications: you're signed up. Expect a text when a new app or feature ships, typically no more than a few messages per month. Message and data rates may apply. Reply HELP for help, STOP to cancel.
```

## Opt-out keywords

```
OPTOUT,CANCEL,END,QUIT,UNSUBSCRIBE,REVOKE,STOP,STOPALL
```

## Opt-out message

```
davenn.com Update Notifications: you have successfully been unsubscribed and will not receive any more messages from this number. Reply START to resubscribe. Help: support@davenn.com
```

## Help keywords

```
HELP,INFO
```

## Help message

```
davenn.com Update Notifications: we text you when a new app or feature ships on davenn.com. Message and data rates may apply. Reply STOP to unsubscribe. Help: support@davenn.com or https://davenn.com/terms.html
```

> **This number has no inbound webhook.** Nothing in `api.php` answers STOP,
> START or HELP sent to it, so Twilio's platform defaults reply. Set the
> three messages above in this Messaging Service's **Advanced Opt-Out**
> settings, or what Twilio actually sends will not match what was filed.

---

# Campaign B — Confidence Pool

The pool's own number, with `?action=cp_sms` as its inbound webhook. Every
outbound message is a reply to one the recipient sent first.

## Campaign description

```
Messages are sent by [LEGAL NAME], operating as davenn.com. davenn.com runs a private NFL confidence pool for one small group of friends and family. A member texts a photo of their completed paper pick sheet to this number. We read the picks off the photo and text that same person back a link so they can check what was read before anything is saved. Every message this number sends is a reply to a message that person sent first; it never initiates a conversation. Recipients are only the handful of people in that one private pool, who are given the number directly. There is no sign-up form, the number is not published or advertised, and no money changes hands - no entry fees, no wagers, no prizes. Recipients can reply STOP at any time to stop all messages, or HELP for help. Terms: https://davenn.com/terms.html Privacy: https://davenn.com/privacy.html
```

## Message flow: How do end-users consent to receive messages?

```
A pool member consents by texting this number first. The number is given directly to the handful of people in one private pool; it is not published, advertised, or offered as a sign-up anywhere. The number never sends the first message to anyone. When an unrecognised number texts in, the only reply is a prompt asking for the sender's name so their sheet can be filed against the right person; if they do not reply, nothing further is ever sent. Program terms for this number, including frequency, cost, opt-out and help, are published at https://davenn.com/terms.html under "Confidence Pool messaging".
```

## Opt-in method proof

```
Consent is given by the recipient texting this number first. The number is
shared directly with members of one private pool and is not published.

Published program terms, publicly accessible with no login:
https://davenn.com/terms.html (section "Confidence Pool messaging") - states
the program name, what it does, who it is for, frequency, cost, opt-out and
help instructions.

Exact messages exchanged when someone texts in for the first time:
  Member: [photo of a completed pick sheet]
  davenn.com: "I do not recognise this number yet. Reply with your name
  first, then text a photo of your sheet."
  Member: "[Name]"
  davenn.com: "davenn.com Confidence Pool: thanks [Name], this number is now
  linked to your picks. Text a photo of your sheet whenever you are ready.
  Reply STOP to opt out."
```

## Privacy policy URL

```
https://davenn.com/privacy.html
```

## Terms and conditions URL

```
https://davenn.com/terms.html
```

## Checkboxes

| Field | Answer |
|---|---|
| Messages will include embedded links | **Yes** — all point to https://davenn.com, no shorteners |
| Messages will include phone numbers | No |
| Content related to direct lending | No |
| Age-gated content | No — see below |

Not age-gated: the pool is a free scorekeeping tool for a private game. No
entry fees, no funds held or transferred, no bets, no odds published, no
prizes. Stated under "No wagering" at https://davenn.com/terms.html, which is
the page to point at if a reviewer queries this.

## Sample messages

```
davenn.com Confidence Pool: read [16] of [16] picks for Week [3]. Nothing is saved yet - open this to confirm: https://davenn.com/nflpool.html?review=[token]
```

```
davenn.com Confidence Pool: thanks [Name], this number is now linked to your picks. Text a photo of your sheet whenever you are ready. Reply STOP to opt out.
```

```
davenn.com Confidence Pool: read [14] of [16] picks for Week [7]. [2] thing(s) to check. Nothing is saved yet - open this to confirm: https://davenn.com/nflpool.html?review=[token]
```

```
davenn.com Confidence Pool: I could not read that sheet. Try again with the whole page in frame, flat, in even light. Reply HELP for help.
```

## Opt-in keywords

```
START,UNSTOP,YES
```

## Opt-in message

Matches the START branch in `?action=cp_sms`.

```
davenn.com Confidence Pool: you are set up again. Text a photo of your pick sheet any time and I will reply with a link to check it. Message and data rates may apply. Reply HELP for help, STOP to opt out.
```

## Opt-out keywords

```
OPTOUT,CANCEL,END,QUIT,UNSUBSCRIBE,REVOKE,STOP,STOPALL
```

All eight are honoured in `?action=cp_sms`.

## Opt-out message

```
davenn.com Confidence Pool: you have successfully been unsubscribed and will not receive any more messages from this number. Reply START to resubscribe. Help: support@davenn.com
```

> The code answers STOP with **silence** (`cpTwimlSilent()`) so the carrier's
> own confirmation is not doubled. Set the text above in this Messaging
> Service's **Advanced Opt-Out** settings so Twilio sends exactly what was
> filed, and leave the code silent.

## Help keywords

```
HELP,INFO
```

## Help message

Matches the HELP branch in `?action=cp_sms` word for word.

```
davenn.com Confidence Pool: text a photo of your filled-in pick sheet and I will read it and send back a link to check it. Message and data rates may apply. Reply STOP to opt out. Help: support@davenn.com
```

---

## Changes already made to match these filings

- **Opt-in confirmation** spells out `Message and data rates may apply` and
  the frequency (`?action=subscribe`), matching Campaign A word for word.
- **Pool HELP reply** names the program, the rates and a support route, and
  describes the pool only — this webhook is on the pool's number.
- **Pool START reply** rewritten as a compliant opt-in confirmation.
- **Pool sheet reply and number-linked reply** lead with the brand, as every
  filed sample must identify the sender.
- **`OPTOUT` and `REVOKE`** added to the opt-out keywords honoured in
  `cp_sms`, so the filed list and the handled list are the same.
- **All outbound SMS is ASCII.** Em dashes forced UCS-2, cutting a segment
  from 160 characters to 70.
- **STOP and HELP are bold** in [`terms.html`](../terms.html).
- **Privacy policy** carries the CTIA sentence verbatim.

## Still outstanding

- **Advanced Opt-Out on both Messaging Services.** Console settings; nothing
  in the repo can do it. Campaign A's number has no inbound webhook at all,
  so all three of its keyword replies come from Twilio.
- **A STOP to the updates number never reaches the database.** Twilio blocks
  delivery so compliance holds, but the row stays in `subscribers` and every
  broadcast retries and fails against it. Fixing it means giving that number
  an inbound webhook, or reconciling against Twilio's opt-out list.

## Before resubmitting

- [ ] `[LEGAL NAME]` replaced in both descriptions.
- [ ] Campaign A's description no longer begins "ecipients".
- [ ] Opt-in keywords and opt-in message are filled in — both were blank.
- [ ] Each campaign filed against the right number.
- [ ] Advanced Opt-Out set on both Messaging Services.
- [ ] `support@davenn.com` receives mail — several filed answers point at it.

## Where the code lives

| Piece | Location |
|---|---|
| Sign-up and opt-in confirmation | `?action=subscribe` in `api.php` |
| Broadcast | `?action=notify_subscribers`, fired by the deploy workflow on a `CHANGELOG.md` change |
| Pool inbound, keywords, replies | `?action=cp_sms` in `api.php` |
| Sending | `sendSms()` in `api.php` |
| Program terms | [`notify.html`](../notify.html), [`terms.html`](../terms.html) |
| Privacy policy | [`privacy.html`](../privacy.html) |
