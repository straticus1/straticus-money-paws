# PAWS starter art — generation prompts

Mode: built-in image generation. The visual reference for every initial image was `concepts/paws-world-v1.png`. Source PNGs are retained in `source/`; WebP exports use cwebp quality 86 (84 for development-only character proofs). No CLI image-generation API was used.

## clover-room-v1

Use case: stylized-concept
Asset type: production 2D browser game room background, landscape 1536x1024
Reference: the supplied PAWS sheet is a style reference only.
Create ONLY a warm hand-painted pet cottage interior background in that exact textured, charming illustration style. No pets, people, text, logos, panels or UI. View directly into the room at a slightly elevated frontal angle, not a cutaway building. Entire rectangular canvas filled with the interior. Arched sunlit garden window on upper left, cream plaster walls, sage built-in cupboard and books tucked into upper right. Wooden plank floor fills the lower 62% of image, perspective converging toward back wall. Leave the central and lower floor EMPTY and unobstructed for separately rendered pets and movable furniture; no rugs, bowls, toys, beds, chairs or foreground plants on the floor. Decorative greenery only on high shelves and windowsill. Warm ambient daylight, honey wood, sage trim, soft gouache texture. No baked-in animal shadows. Background usable behind interactive sprites at both desktop and mobile sizes.

## dog-v1

Use case: stylized-concept
Asset type: transparent standalone 2D pet game character sprite, square.
Use the supplied PAWS concept as STYLE reference only. Generate ONE honey-gold puppy with floppy ears, soulful dark eyes, soft textured fur, gently happy closed mouth. Full body seated facing almost straight forward, front paws together, tail visible curling to the side, animal anatomy. Match the premium hand-painted cozy gouache game illustration, warm daylight and carefully readable silhouette. No accessories, bandana, collar, objects, floor, scenery, text, border or watermark. True transparent alpha background, not a painted checkerboard or white rectangle. Character fully contained within canvas with 8% empty margin, centered, ears near 10% height and paws near 90%; head occupies upper half, neck at about 55% height. One pose, one animal. This will be layered on the room background in a game.

## cat-v1

Use case: stylized-concept
Asset type: transparent standalone 2D pet game character sprite, square.
Use the supplied PAWS concept as STYLE reference only. Generate ONE silver tabby cat with upright triangular ears, soulful dark eyes, soft textured fur, gently happy closed mouth. Full body seated facing almost straight forward, front paws together, long fluffy tail curling to the side, animal anatomy. Match the premium hand-painted cozy gouache game illustration, warm daylight and carefully readable silhouette. No accessories, bandana, collar, objects, floor, scenery, text, border or watermark. True transparent alpha background, not a painted checkerboard or white rectangle. Character fully contained within canvas with 8% empty margin, centered, ears near 10% height and paws near 90%; head occupies upper half, neck at about 55% height. One pose, one animal. This will be layered on the room background in a game.

## fox-v1

Use case: stylized-concept. Asset type: square full-body 2D game pet illustration. Use the supplied PAWS board for art style. ONE adorable rusty orange fox, cream chest and tail tip, dark paws, oversized fluffy tail curved alongside, upright triangular ears, lively kind eyes. Seated, facing nearly straight forward, centered with generous 10% margins. Closed mouth. No bandana or accessories. Premium hand-painted gouache illustration, soft tactile fur, warm daylight, same character design as the reference fox. Plain solid white background, no texture or pattern, no scenery, floor, shadow, text, border or panels. Single animal fully inside canvas.

## rabbit-v1

Use case: stylized-concept. Asset type: square full-body 2D game pet illustration. Use the supplied PAWS board for art style. ONE adorable soft cocoa brown rabbit, cream muzzle and chest, long gently drooping ears, lively kind dark eyes and small pinkish nose. Sitting upright with small front paws together, centered with generous 10% margins. Closed mouth. No bandana or accessories. Premium hand-painted gouache illustration, soft tactile fur, warm daylight, same character design as the reference rabbit. Plain solid white background, no texture or pattern, no scenery, floor, shadow, text, border or panels. Single animal fully inside canvas.

## squeaky-moon-v1

Use case: stylized-concept. Asset type: square game inventory icon.
Use the supplied PAWS concept sheet as style reference. Create ONLY the golden crescent moon plush toy from that sheet, made of warm mustard yellow felt with neat sewn edges and a tiny sleepy embroidered face. Rounded soft stuffing, premium hand-painted gouache illustration, warm subtle lighting. Three-quarter front view, centered, fills 75% of square canvas. Plain pure white background with no texture, no floor, no cast shadow. No text, logo, border, other items or panels. Clear readable silhouette suitable for a small game icon.

## rolling-acorn-v1

Use case: stylized-concept. Asset type: square game inventory icon.
Use the supplied PAWS concept sheet as style reference. Create ONLY one wooden acorn wobble toy: a rounded honey-brown polished wood body, darker carved acorn cap, small carved paw print. Premium hand-painted gouache illustration, warm subtle lighting. Three-quarter front view, centered, fills 75% of square canvas. Plain pure white background with no texture, no floor, no cast shadow. No text, logo, border, other items or panels. Clear readable silhouette suitable for a small game icon.

## clover-crunch-v1

Use case: stylized-concept. Asset type: square game inventory icon.
Use the supplied PAWS concept sheet as style reference. Create ONLY one low rounded cream ceramic pet bowl filled with brown kibble pieces, a sage green paw print on the front, subtle handmade glaze. Premium hand-painted gouache illustration, warm subtle lighting. Three-quarter front view, centered, fills 75% of square canvas. Plain pure white background with no texture, no floor, no cast shadow. No text, logo, border, other items or panels. Clear readable silhouette suitable for a small game icon.

## Rejected transparency retry

The first dog and cat outputs contained an opaque painted checkerboard. One dog extraction retry also remained opaque and was not selected. Do not interpret any checkerboard in the source art as real alpha.

Use case: background-extraction. Edit the supplied puppy image: remove the entire gray and white checkerboard background and replace it with actual transparent alpha pixels. Keep only the puppy and its fur edges; preserve the puppy illustration, colors, identity and pose exactly. Deliver a genuinely transparent PNG cutout, RGBA with alpha=0 outside the animal. Do not draw a checkerboard, solid background, texture or shadow; those must be transparent pixels. No new objects or text.
