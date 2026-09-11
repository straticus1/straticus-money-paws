# PAWS starter art

Eight original illustrations were created with the built-in image generation tool, using [the approved concept](concepts/paws-world-v1.png). Exact generation prompts remain in [PROMPTS.md](PROMPTS.md). The user authorized local background removal on September 11, 2026.

## Connected assets

| Asset | Finished PNG | Runtime WebP |
| --- | --- | --- |
| Clover Room | [source/clover-room-v1.png](source/clover-room-v1.png) | clover-room-v1.webp |
| Dog | [cutouts/dog-v1.png](cutouts/dog-v1.png) | dog-cutout-v1.webp |
| Cat | [cutouts/cat-v1.png](cutouts/cat-v1.png) | cat-cutout-v1.webp |
| Fox | [cutouts/fox-v1.png](cutouts/fox-v1.png) | fox-cutout-v1.webp |
| Rabbit | [cutouts/rabbit-v1.png](cutouts/rabbit-v1.png) | rabbit-cutout-v1.webp |
| Clover Crunch | [cutouts/clover-crunch-v1.png](cutouts/clover-crunch-v1.png) | clover-crunch-cutout-v1.webp |
| Squeaky Moon | [cutouts/squeaky-moon-v1.png](cutouts/squeaky-moon-v1.png) | squeaky-moon-cutout-v1.webp |
| Rolling Acorn | [cutouts/rolling-acorn-v1.png](cutouts/rolling-acorn-v1.png) | rolling-acorn-cutout-v1.webp |

Runtime files live in `../packages/web/src/assets/home/`. The room is 1536×1024, pets are 512×512, and items are 384×384. All seven cutouts retain true RGBA transparency. The eight production images total 550,582 bytes.

Painted dog, cat, fox and rabbit are enabled by default in the home. Bandana colors follow the server's equipped cosmetic. Care reactions use CSS animation and respect reduced-motion preferences. These are single-pose illustrations, not frame-by-frame animation.

The Pet artwork selector offers Classic, which retains individual coats, markings and smile details. Painted illustrations currently show a fixed appearance for each species; the UI explains this. Other species and image-load failures retain their previous artwork. Item cutouts appear in the store, furniture bag and room wherever the matching item is present.

Build-time imports receive Vite content hashes. No arbitrary image URLs are accepted from item data. The CSS room remains underneath as a background-load fallback. Care, cooldowns, purchases, placement ownership and backend validation are unchanged.

## Accessibility changes

The home and studio use at least 1rem for ordinary text, 44px minimum control heights, stronger focus indicators, and a single-column layout at narrower widths. The companion journal no longer requires scrolling inside a small panel. High-contrast and forced-colors media styles are included.

The real home has a Room description and placed items disclosure with written scenery, companion, bandana and occupied-position descriptions. Furniture placement uses native buttons and selects, with no dragging required.

The studio announces the selected pet through a polite status region, gives full written descriptions of the painted pets, and displays an open room description. Image inspection is not required to learn what changed.

Calculated text contrast: home text 10.39:1, disabled-control text 5.80:1, primary-action text 7.17:1. These targeted improvements are not full-game accessibility certification. Live reflow, keyboard traversal and screen-reader announcements still need runtime verification when a browser becomes available.

## Local cleanup workflow

Original opaque outputs remain unchanged in `source/`. The dog and cat checkerboards were actual RGB pixels. After explicit user authorization, [Rembg](https://github.com/danielgatis/rembg) and its local `isnet-general-use` model removed the backgrounds. No images were uploaded to a removal service, and no alternate image-generation API was used.

Tool dependencies are in [requirements.txt](requirements.txt). This run used Python 3.12 in `/private/tmp/paws-art-tools`, model cache `/private/tmp/paws-art-models`, and Numba cache `/private/tmp/paws-numba-cache`. They are temporary authoring tools, not dependencies of the game.

[prepare-cutouts.py](prepare-cutouts.py) preserves sources, refuses to overwrite existing finished PNGs, checks foreground coverage, and produces RGBA images. The model downloads on first use; processing is local. Alpha below 8 is cleared and alpha above 250 is made opaque.

Exports use cwebp with alpha quality 100, color quality 90 for pets and 88 for items. Earlier opaque exports and proofs remain historical files; the production build does not import them.

## Validation

- [verify-cutouts.py](verify-cutouts.py) passed for all seven PNG/WebP pairs: actual alpha, full 0–255 range, transparent corners, plausible foreground coverage and correct export dimensions.
- Per-asset results: [cutouts/validation.json](cutouts/validation.json).
- [cutouts/edge-review.png](cutouts/edge-review.png) was visually inspected on cream, dark green and wood-colored backgrounds.
- [cutouts/room-review.png](cutouts/room-review.png) was visually inspected for illustration and bandana placement. This is an image composite, **not a browser screenshot**.
- Web TypeScript checking and production build passed.
- Local preview HTML, transformed preview module and finished dog WebP returned HTTP 200.
- The configured browser runtime reports no available browsers. Browser interaction and assistive-technology testing were not performed.

## Local studio

From `platform/`, run:

```sh
pnpm --filter @paws/web dev --host 127.0.0.1
```

The studio is at `http://127.0.0.1:5173/art-preview.html` (or the port Vite reports). It imports the real artwork components and home styles, with no API calls, account requirement, rewards or saved progress changes. It is a development-only HTML entry and is not a production page.

## Remaining art work

Painted coat/marking variants, frame animation, nine additional species, remaining catalog and keepsake art, and minigame artwork remain outstanding. Deployment and infrastructure provisioning remain deferred; committing this artwork does not deploy the game.
