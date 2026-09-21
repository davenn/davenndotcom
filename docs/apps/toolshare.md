# Toolshare

A private tool library shared between friends. You catalogue what you own,
your friends can see it, and borrowing goes through a request that the owner
approves or declines.

**File:** [`toolshare.html`](../../toolshare.html) (~1,400 lines, the largest app)
**Tables:** `tb_users` `tb_sessions` `tb_tools` `tb_requests` `tb_friendships`
**Auth:** account, `X-Auth-Token`

## The model

Three things drive everything else:

- **A tool belongs to exactly one owner.** Visibility comes from friendship,
  not from sharing settings on the tool itself.
- **Friendships are mutual and undirected.** One row in `tb_friendships` holds
  `user_a` and `user_b`, so every query that walks them has to check both
  columns and take whichever side is not the current user.
- **Borrowing is a request, not a transfer.** A tool that is out is still the
  owner's; the request row carries the state.

## Connecting

There is no user search and no way to browse strangers — deliberate, since the
whole point is a library among people who already know each other.

Instead each account gets an invite code, minted lazily on first request so an
account that never shares anything never carries one. A code becomes a link
with a QR variant for handing over in person. Opening it shows a preview of who
is inviting you (`tb_invite_preview`, the one unauthenticated endpoint in the
app) so you know what you are accepting before signing in.

If you follow an invite while signed out, the code is parked in
`localStorage['tb_pending_invite']` and applied after you sign in, so the link
survives the detour through registration.

## Adding a tool by photograph

`tb_identify_tool` sends a photo to Claude and gets back a name, category and
tags to prefill the add form. It fills the form rather than saving, because a
misidentified drill is easier to correct before it is in the library than
after.

Tag suggestions (`tb_tag_suggestions`) come from tags already in use, which
keeps the vocabulary from fragmenting into `drill`, `drills` and `power drill`.

## Sessions

Sessions live in their own table, so signing in on a second device does not
sign out the first, and signing out ends only the current device's session.
This is the opposite of Flight Tracker, which keeps one token on the user row —
see [flighttracker.md](flighttracker.md).

Daily Tasks reuses these same accounts for its cross-device sync.

## Endpoints

Auth: `tb_register` `tb_login` `tb_logout`
Tools: `tb_my_tools` `tb_tools` `tb_add_tool` `tb_edit_tool` `tb_delete_tool` `tb_identify_tool` `tb_tag_suggestions`
Borrowing: `tb_request` `tb_requests` `tb_request_count` `tb_respond_request` `tb_return_tool`
Friends: `tb_my_invite` `tb_invite_preview` `tb_accept_invite` `tb_friends` `tb_remove_friend`

Full detail in [`../api.md`](../api.md).

## Front-end notes

- Four tabs: My Library, Community, Requests, Friends.
- The Requests tab carries a badge, polled by `tb_request_count` — a cheap
  count endpoint rather than fetching the whole list to find out it is empty.
- Token and user are cached in `localStorage` under `tb_token` and `tb_user`.
- Email goes out on borrow requests and responses, so the other person does not
  have to be in the app to find out.
