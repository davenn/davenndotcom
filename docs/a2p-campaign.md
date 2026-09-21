# A2P 10DLC campaign registration

Answers for the Twilio campaign form, in the order the fields appear, so they
can be pasted down the page. Kept in the repo because a reviewer compares the
campaign description, the sample messages, the opt-in flow on the website and
the privacy policy against each other — inconsistency between them is the
most common rejection cause. When you change [`notify.html`](../notify.html),
[`terms.html`](../terms.html), [`privacy.html`](../privacy.html) or the
message strings in `api.php`, change this too.

> **Two placeholders to fill before submitting.** `[LEGAL NAME]` is the legal
> entity name registered with Twilio. If it is not literally "davenn.com",
> the description must state the relationship or the reviewer reads the
> website and the brand as two different businesses — a named rejection trap.

## Why the last submission was rejected

> The Campaign field does not clearly explain the messaging program. Describe
> who is sending the messages, who receives them, and why the messages are
> being sent. Make sure the description matches your selected campaign use
> case, sample messages, and registered brand details.

Two likely causes, both fixed below:

1. **This number carries two different messaging programs.** A description
   covering only one will not match the sample messages or the website, which
   describe both.

| Program | Direction | Opt-in |
|---|---|---|
| Update Notifications | outbound broadcast | web form with consent checkbox |
| Confidence Pool | two-way conversational | the person texts the number first |

2. **A possible legal-name / website-name mismatch.** If the brand is
   registered under a personal or company legal name while the site, the
   sender name and the messages all say "davenn.com", a reviewer treats that
   as two businesses unless the description spells out the relationship.

---

## Use cases

```
MIXED
```

Two genuinely different message types share this number, so MIXED is the
honest answer. It costs more and gets lower throughput than a single-purpose
use case — acceptable here, since volume is a few messages a month.

If the console offers **Low Volume Mixed** and the brand qualifies, take it
instead: same description, lower cost, and throughput is irrelevant at this
volume.

## Campaign description

```
Messages are sent by [LEGAL NAME], operating as davenn.com, a personal
website hosting a collection of free web apps. This campaign covers the only
two ways the site sends text messages. Both are opt-in. No numbers are
purchased, rented, or obtained from third parties, and we never message
anyone who has not asked us to.

1. Update notifications. A visitor to davenn.com who wants to know when a new
app or feature is released enters their own mobile number into the sign-up
form at https://davenn.com/notify.html and ticks an unticked consent
checkbox. davenn.com then texts that person when something new ships on the
site, typically no more than a few messages per month. The only people who
receive these messages are people who submitted their own number through that
form. Consent is not a condition of using anything on the site, and the
messages advertise nothing other than davenn.com itself.

2. Confidence Pool replies. davenn.com runs a private NFL confidence pool for
one small group of friends and family. A member texts a photo of their
completed paper pick sheet to this number. davenn.com reads the picks off the
photo and texts that same person back a link so they can check what was read
before anything is saved. Every message in this program is a reply to a
message that person sent first. Nobody is added to the pool by us, there is
no sign-up, and no money changes hands.

Recipients of either program can reply STOP at any time to stop all messages,
or HELP for help. Program terms are at https://davenn.com/terms.html and the
privacy policy is at https://davenn.com/privacy.html.
```

## Message flow: How do end-users consent to receive messages?

```
Update notifications: the visitor goes to https://davenn.com/notify.html,
linked from the davenn.com home page. They enter their own mobile number and
tick a consent box that is unticked by default; the form will not submit
without it. The box reads: "I agree to receive recurring automated text
messages and/or emails from davenn.com Update Notifications at the number or
address I provide. Consent is not a condition of using anything on this site.
Message frequency varies - typically no more than a few messages per month.
Message and data rates may apply. Reply STOP to cancel or HELP for help at
any time." The page also states the program name, what is sent, frequency and
cost, and links the Privacy Policy and SMS terms. The number then receives a
confirmation text. Consent is collected only for the person's own number.

Confidence Pool: a member consents by texting the number first. It is given
directly to one small private pool, is not published, and never sends first.
```

## Opt-in method proof

```
Web form, publicly accessible with no login required:
https://davenn.com/notify.html

The page shows the sign-up field, the unticked consent checkbox with its full
wording, the program name, message frequency, cost, opt-out and help
instructions, and links to the privacy policy and SMS program terms.

The consent checkbox reads: "I agree to receive recurring automated text
messages and/or emails from davenn.com Update Notifications at the number or
address I provide. Consent is not a condition of using anything on this site.
Message frequency varies - typically no more than a few messages per month.
Message and data rates may apply. Reply STOP to cancel or HELP for help at
any time."
```

## Privacy policy URL

```
https://davenn.com/privacy.html
```

## Terms and conditions URL

```
https://davenn.com/terms.html
```

## Messages will include embedded links

```
Yes
```

All links point to https://davenn.com. No link shorteners are used.

## Messages will include phone numbers

```
No
```

## Messages include content related to direct lending

```
No
```

## Messages include age-gated content

```
No
```

The Confidence Pool is a free scorekeeping tool for a private game. No entry
fees, no funds held or transferred, no bets, no odds published, no prizes —
stated explicitly under "No wagering" at https://davenn.com/terms.html, which
is the page to point at if a reviewer queries this.

## Sample message #1

Opt-in confirmation, sent once on sign-up.

```
davenn.com Update Notifications: you're signed up. Expect a text when a new app or feature ships, typically no more than a few messages per month. Message and data rates may apply. Reply HELP for help, STOP to cancel.
```

## Sample message #2

Update notification. The body is the newest changelog entry.

```
New on davenn.com: [Feature] - [one-line description of what it does]. https://davenn.com

davenn.com - Reply STOP to unsubscribe.
```

## Sample message #3

Confidence Pool reply to a texted-in pick sheet.

```
davenn.com Confidence Pool: read [16] of [16] picks for Week [3]. Nothing is saved yet - open this to confirm: https://davenn.com/nflpool.html?review=[token]
```

## Sample message #4

Help reply.

```
davenn.com Confidence Pool: text a photo of your filled-in pick sheet and I will read it and send back a link to check it. Reply STOP to opt out. Help: support@davenn.com
```

## Sample message #5

Confirmation when a pool member's number is first linked.

```
davenn.com Confidence Pool: thanks [Name], this number is now linked to your picks. Text a photo of your sheet whenever you are ready. Reply STOP to opt out.
```

## Opt-in keywords

```
START, UNSTOP, YES
```

## Opt-in message

```
davenn.com Update Notifications: you're signed up. Expect a text when a new app or feature ships, typically no more than a few messages per month. Message and data rates may apply. Reply HELP for help, STOP to cancel.
```

## Opt-out keywords

```
STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT
```

## Opt-out message

```
davenn.com: you are unsubscribed and will receive no further messages from davenn.com Update Notifications or the davenn.com Confidence Pool. Reply START to resubscribe. Help: support@davenn.com
```

## Help keywords

```
HELP, INFO
```

## Help message

```
davenn.com: this number sends davenn.com update notifications and replies to Confidence Pool pick sheets. Message and data rates may apply. Reply STOP to opt out. Help: support@davenn.com or https://davenn.com/terms.html
```

---

## Code and site changes made to match these answers

Filing an answer the code does not actually send is the inconsistency that
gets campaigns rejected. These were brought into line:

- **Opt-in message** now spells out `Message and data rates may apply` and the
  frequency, matching the filing word for word (`?action=subscribe`).
- **Help reply** now answers for both programs instead of only the Confidence
  Pool, so an update subscriber who texts HELP gets a relevant answer
  (`?action=cp_sms`).
- **Pool sheet reply and number-linked reply** now lead with the brand, as
  every filed sample must identify the sender.
- **All outbound SMS is ASCII.** Em dashes are gone from every message the
  code sends; see the encoding note above.
- **STOP and HELP are bold** in [`terms.html`](../terms.html), as required.
- **Privacy policy** now carries the CTIA sentence verbatim, ahead of the
  existing wording.

### Still outstanding

- **Opt-out message.** The code deliberately stays silent on STOP
  (`cpTwimlSilent()`) so the carrier's own confirmation is not doubled. That
  is the right behaviour, but the form still needs text. Set the opt-out
  message above in the Messaging Service's **Advanced Opt-Out** settings so
  Twilio sends exactly what was filed, and leave the code silent. Nothing in
  the repo can do this — it is a console setting.
- **A STOP from an update subscriber never reaches the database.** Twilio
  blocks delivery, so compliance holds, but the row stays in `subscribers`
  and every broadcast retries and fails against it. Fixing it means handling
  STOP for non-pool numbers in the inbound webhook; not done, because it
  changes behaviour rather than wording.

## Before resubmitting

- [ ] `[LEGAL NAME]` replaced, and the DBA relationship reads correctly.
- [ ] Brand name registered with Twilio matches the legal entity exactly.
- [ ] https://davenn.com/notify.html opens for someone not logged in.
- [ ] Privacy policy and terms URLs both load over https.
- [ ] Sample messages match what the code sends, after the three changes above.
- [ ] `support@davenn.com` receives mail — three filed answers point at it.

## Where the code lives

| Piece | Location |
|---|---|
| Sign-up and opt-in confirmation | `?action=subscribe` in `api.php` |
| Broadcast | `?action=notify_subscribers`, fired by the deploy workflow on a `CHANGELOG.md` change |
| Pool inbound, STOP/HELP, replies | `?action=cp_sms` in `api.php` |
| Sending | `sendSms()` in `api.php` |
| Program terms | [`notify.html`](../notify.html), [`terms.html`](../terms.html) |
| Privacy policy | [`privacy.html`](../privacy.html) |
