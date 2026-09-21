# Face Breaker

Breakout, except the paddle is your face. Take a photo, and the outline of
your face becomes the shape the ball bounces off.

**File:** [`facebreaker.html`](../../facebreaker.html)
**Tables:** `fb_scores`
**Auth:** none

## The paddle

`buildFacePaddle` runs [face-api.js](https://github.com/justadudewhohacks/face-api.js)
(0.22.2, weights loaded from a CDN) over the photo, takes the 68 facial
landmarks, scales them back to the original image and wraps them in a
**convex hull**. That hull is the collision polygon, so the ball bounces off
the real silhouette rather than a rectangle behind the picture.

Collision is polygon-against-circle (`polyCircle`, `segDist`) rather than the
usual box test, which is the whole reason the app is interesting.

If no face is found, or the models fail to load, `buildDefaultPaddle` draws a
smiley and the game plays normally. Losing the camera should not lose the
game.

## Scores

Same top-ten-or-nothing rule as [Reaction Test](reactiontest.md): if ten
scores already beat yours this week, `fb_save_score` answers `success: false`
with HTTP 200, not an error. On success it returns your `rank`.

**One difference worth noting:** the week is computed **server-side** here,
from the ISO week, rather than being sent by the client as a `week_key` like
every other leaderboard on the site. A client cannot post into a different
week, and there is no week navigation.

## Endpoints

`fb_leaderboard` `fb_save_score` — full detail in [`../api.md`](../api.md).

## Front-end notes

- Names are capped at 32 characters here, not 60 as elsewhere.
- Canvas game loop (`loop`, `update`, `render`) with pointer, touch and tap
  handlers, and a resize handler that rescales the field.
- No `localStorage`, no theme toggle — the game draws its own surface.
