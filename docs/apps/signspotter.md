# Sign Spotter

Point your camera at an object and it tells you the American Sign Language
sign for it. Object recognition as a way into a dictionary.

**File:** [`signspotter.html`](../../signspotter.html)
**Tables:** none
**Auth:** none — entirely client-side

> Not road signs. The name is about ASL.

## How it works

1. `start` opens the camera stream.
2. `detectLoop` runs [coco-ssd](https://github.com/tensorflow/tfjs-models/tree/master/coco-ssd)
   (via TensorFlow.js) over the video on an interval, rather than per frame —
   detection is far slower than the frame rate and a queue of stale frames
   helps nobody.
3. `drawBoxes` overlays what it found; `handleTopPick` takes the strongest
   detection and looks it up.
4. `signFor` resolves that label to an entry in `SIGN_LIBRARY` and
   `showSignPanel` shows the sign.

Everything runs in the browser. No frame is ever uploaded, and there is no
backend for this app at all.

## The sign library

`SIGN_LIBRARY` maps coco-ssd's labels to entries on
[Lifeprint](https://www.lifeprint.com), the ASL reference it links out to.
Around 65 entries, keyed by the exact label the model emits — `'cell phone'`,
not `'phone'`.

Entries carry a display word, a page link, usually an animated GIF or still,
and sometimes a note where a single sign does not exist:

```js
'tv': { word: 'TV', page: '…/tv.htm', media: null,
        note: 'Fingerspelled T-V — no single sign' }
```

That note field matters. Some things are fingerspelled and some signs vary by
the shape of the object (`bottle`), and saying so is more honest than showing
one GIF as though it were the answer.

**Unmapped labels degrade gracefully.** `signFor` falls back to title-casing
the raw label and linking to the Lifeprint homepage, marking the result
`covered: false`. coco-ssd knows 80 classes and the library covers a subset,
so this path is normal, not exceptional.

## Adding to the library

Add a key matching the coco-ssd label exactly, with `word`, `page`, and either
`media` or a `note` explaining its absence. No other change is needed.

## Front-end notes

- Needs camera permission and HTTPS; `showError` covers refusal and absence.
- `sizeOverlay` keeps the detection canvas aligned with the video element
  across orientation changes.
- Models load from a CDN, so first use needs a connection even though the app
  is installable.
