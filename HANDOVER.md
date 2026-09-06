# BEspoke Customiser — Project Handover

**Written:** 28 July 2026 · **Repo state at handover:** `main` @ `dfe867e`, clean, pushed.

This document is written for **a fresh Claude session on a different machine**. Read it
end-to-end before touching anything. It contains the context, the workflow, and — most
importantly — the **hard-won gotchas** that cost real hours to discover.

---

## 1. Who you're working with

**Nick — a designer, not a web developer.** This matters more than anything else here.

- Explain in **plain language**. No unexplained jargon. When something technical must be
  named, say what it does in the same breath.
- He makes **excellent design judgements** — trust his eye. When he says something "looks
  wrong", he's usually right even if he can't articulate why. Your job is to find the
  mechanism.
- He renders his own 3D product artwork in **Cinema 4D**, and it's genuinely good. Don't
  propose solutions that waste it or ask for re-renders you can avoid — check first
  whether the problem can be solved in code. (This has already saved ~9 hours once.)
- **He cannot run PHP, Node, or a terminal comfortably.** Don't hand him command-line
  tasks. Give him files to upload and buttons to click.
- He is direct and will tell you plainly when something doesn't work. Don't be defensive —
  diagnose. Several times the real cause was NOT what either of us first assumed.

---

## 2. What the project is

A **custom WordPress/WooCommerce plugin** (`bespoke-customiser`) that lets grassroots
football clubs design personalised kit and equipment on-screen — shin pads, captain's
armbands, grip socks, trophies, player cards, flags and more — then order them, with the
design spec flowing through to the cart, the confirmation email, and a printable admin
order screen.

**BEspoke Sport** is the business. Brand: black `#0D0D0D`, white, mint `#5DCAA5`;
fonts Anton (headings) + Inter/Roboto (body).

### The customiser in one paragraph
Each product has a **product type** (`shinpads`, `armbands`, `player_cards`…). That type
drives which steps show, where the badge/text sit, which artwork loads, and how the order
is rendered. The customer moves through steps (Badge → Text → Number → Design → Colours →
Review), dragging elements on an SVG preview, and the finished spec is POSTed to
`admin-ajax.php`, matched to a WooCommerce variation, and stored on the cart item.

---

## 3. Where everything lives

| Thing | Location |
|---|---|
| **Code (git)** | `C:\Users\nickl\Documents\GitHub\bespoke-customiser` |
| **GitHub** | `https://github.com/BespokeSport/bespoke-customiser` (branch `main`) |
| **Staging site** | `https://staging2.bespokesport.uk` (WP admin at `/wp-admin`) |
| **Hosting** | SiteGround — files uploaded via **Site Tools → File Manager** |
| **Plugin path on server** | `/wp-content/plugins/bespoke-customiser/` |
| **Non-git working files** | NAS: `\\DS718\nld\BEspoke\BEspokesports Web\BEspoke Customiser` |
| **Hero animation source** | `C:\Users\nickl\Documents\GitHub\World Animation` (C4D frames + prototypes) |

> **Save rule:** anything that isn't code — exports, downloads, screenshots, renders —
> goes to the **NAS path**, not the repo.

---

## 4. THE WORKFLOW (critical — do not deviate)

There is **no automated deployment**. The cycle is:

1. **You** edit files locally in the git repo.
2. **You** commit and push to GitHub (this is the backup).
3. **Nick** manually uploads the changed files to SiteGround via File Manager.
4. **Nick** clicks **Purge SG Cache**.
5. **You** verify on staging.

**Therefore:** after every change, tell him **exactly which files to upload** — full paths,
nothing extra. He can't diff; he relies on you being precise. If you changed four files,
list four files.

### Uploading a folder of files (the zip trap)
When a change ships a whole FOLDER (the hero frame sequences), zip it and have Nick upload
the zip and use File Manager's Extract. **SiteGround extracts into a new folder named after
the zip**, so a zip that already contains `hero-world-m/` lands as
`assets/hero-world-m/hero-world-m/` — one level too deep. This happened on 4 Sep 2026 and
the symptom was subtle: the site looked fine, just wrong, because the hero's
`file_exists()` check quietly fell back to the widescreen frames.

**So:** after any folder upload, curl a file at the path the code expects before saying it
is done. Zip the folder's CONTENTS rather than the folder itself, or tell him to move the
inner folder up a level afterwards.

### Verification discipline
- **Always verify on staging after he uploads.** Don't assume.
- Use a **cache-busting query string** (`?v=123`) when re-checking, or you'll read a stale
  page and draw the wrong conclusion. *This has misled us before.*
- **Never blame cache without evidence.** Early on I repeatedly blamed cache when the real
  cause was a CSS specificity/source-order problem. He proved me wrong with an incognito
  test. Investigate first.

---

## 5. Architecture map

### Bootstrap
`bespoke-customiser.php` → loads modules from `includes/` in order (each wrapped in
`file_exists()` so a partial upload degrades instead of white-screening).

### Modules (`includes/`)
| File | Responsibility |
|---|---|
| `customiser-designs.php` | Design CPT, **the product-type registry** (`bespoke_get_product_types()`) |
| `customiser-products.php` | Product Setup admin page (artwork uploads), placement geometry, **type inheritance** |
| `customiser-frontend.php` | Emits `window.BespokeConfig`, renders the customiser, layer compositing |
| `customiser-ajax.php` | Add-to-cart handler, **variation matching**, file uploads |
| `customiser-woocommerce.php` | Cart/email/admin **order display renderers** |
| `customiser-hero-world.php` | `[bespoke_hero_world]` scroll-driven homepage hero |
| `customiser-{shop,cart,blog,contact,homepage,master}.php` | Page styling |
| `customiser-shortcodes.php` | `[bespoke_ticker]`, `[bespoke_promise]`, `[bespoke_clubs_say]` |
| `customiser-fonts.php` / `-global-fonts.php` | Custom font upload; force-load Anton |

### Front-end
`assets/customiser.html` — **~4.2MB single file**, the entire customiser UI (HTML + CSS +
JS). Yes it's huge. Use `Grep` to navigate it; never read it whole.

### Key concepts you must understand

**Product type vs BASE type.** Some types *inherit* another's behaviour:
- `double_sided_armbands` → `armbands`
- `referee_armbands` → `armbands`
- any admin-added type → its chosen base

`bespokeProductType()` = the raw type. `bespokeBaseType()` = collapsed to the base.
**Getting this wrong is the single most common bug in this codebase** — see §6.

**Self-serve product types.** Product Setup has an "Add a product type" tool. Nick can add
simple products (name + "behaves like") without code. Stored in option
`bespoke_customiser_custom_product_types`.

---

## 6. ⚠️ Hard-won gotchas (read this section twice)

### 6.1 Raw type vs base type — the recurring bug class
Three separate production bugs came from looking up something by the **raw** product type
when it should have been the **base** type. Symptoms varied wildly:

- **Undercharging.** `BG_VARIANT_LABELS[bespokeProductType()]` was undefined for referee /
  double-sided armbands, so the band-thickness choice never reached the variation matcher.
  8cm bands were sold at the 5cm price (**£8 instead of £10**, and **£15 instead of £18**)
  *and the cart said the wrong size*.
- **Blank orders.** Display renderers are found by building a function name
  (`bespoke_render_cart_{type}`). No function existed for `referee_armbands`, so the cart
  line, confirmation email **and admin order screen showed nothing** — an unprintable
  order. Fixed with `bespoke_resolve_display_renderer()` (type → base → standard).
- **Wrong heading.** Borrowed renderers announced "CAPTAIN ARMBAND SPECIFICATION" on
  referee orders.

**Rule:** when adding *anything* keyed by product type, ask "should a child type match
this?" Almost always yes → use `bespokeBaseType()` / `bespoke_inherit_product_type()`.

### 6.2 The drag "phantom" bug (customiser.html)
Badges appearing to delete themselves, jump to the cursor, or vanish — **all one bug**:

`selectDraggable()` calls `syncAll()`, which rebuilds the badge DOM node. On touch, that
happens *before the finger lifts*, so `touchend` fires on an orphaned node and the
`dragging` flag stays stuck. Then any synthesized **button-less `mousemove`** (which fires
when you tap anything) is treated as a drag and teleports the element.

Fixed by ignoring mousemoves without a held button:
`if ((dragging||resizing||rotating) && !(e.buttons & 1)) { clear; return; }`

**Also:** the armband **mask layer paints ON TOP of badges**. An element dragged off the
band isn't deleted — it's hidden under the mask. Armband badges are now locked to
horizontal movement and clamped to the band cut-out.

### 6.3 WooCommerce variation matching
`bespoke_match_variation()` runs strict then loose. In the loose pass, an **"Any"
attribute will happily swallow a real choice** — a customer's "8cm band" was satisfied by
an unrelated Any-Length attribute, returning the wrong variation. Loose matches are now
**scored** by how many customer values hit a *specific* (non-Any) attribute; best wins.

### 6.4 SiteGround
- SiteGround **minifies/combines CSS**. Plugin stylesheets are excluded (see
  `customiser-master.php`) because stale `.min.css` caused hours of confusion.
- Always append `?ver=filemtime()` when enqueuing.

### 6.5 There are TWO code-injector plugins
- **Code Snippets** — held only inactive PHP.
- **Simple Custom CSS and JS** (post type `custom-css-js`, admin menu "Custom Code") —
  **this is where the site's inline CSS/JS actually lives.**

When hunting a stray `<script>`, check both. Simple Custom CSS and JS wraps its output in
`<!-- start/end Simple Custom CSS and JS -->` comments — that's how to trace the source.
On 2026-07-20 I deactivated **"BEspoke Customise Button Fix"** (post 6622), a broken
"Read more"→"View Options" script with 4 extra `}` that threw a SyntaxError on every page.

### 6.6 Load-order trap
`customiser-frontend.php` echoes `customiser.html` at ~line 182 but only defines
`window.BESPOKE_PRODUCT_ASSETS` at ~line 352. **Anything in customiser.html that runs at
parse time cannot rely on that global.** This silently hid the pennant Frill toggle for
weeks (its `requireAlt` check read an undefined value).


### 6.7 Fancy Product Designer is still installed AND ACTIVE

Easy to assume otherwise, because Nick's own customiser handles everything he talks
about. It does not handle everything on the site. As of 5 Sep 2026 FPD still loads its
full designer — `FancyProductDesigner-all.min.js` and friends, 400+ `fpd-` elements — on
at least three products:

- **Remembrance Day Armband** (id 4852) — the one described elsewhere as "pre-designed,
  no customiser". It has a customiser; it is just a different plugin's.
- **Grassroots Football Mug** (id 5861)
- **Aston Villa Inspired Mug** (id 5540)

`customiser-woocommerce.php` (~line 185) carries a small script whose whole job is to
rewrite FPD's American "Customize" label to "Customise" on those buttons. If FPD ever
goes, that script goes with it.

**Before go-live, decide what happens to FPD.** Three products depending on a second,
heavyweight customiser plugin is a real cost per page load, and it is the kind of thing
that gets forgotten until a product page is mysteriously slow.


### 6.8 There are TWO design-lookup paths, and only one is live

- **`customiser-frontend.php` (~line 300)** builds `BESPOKE_REGISTERED_DESIGNS` server-side
  and patches it into `ALL_DESIGNS`. **This is what actually fills the picker.** It
  deliberately ORs in the PARENT type's designs, so a design registered once against
  Captain Armbands also appears for double-sided and referee bands.
- **`bespoke_ajax_get_designs()` in `customiser-designs.php`** answers
  `admin-ajax.php?action=bespoke_get_designs`. It does an EXACT type match with no
  inheritance — and **nothing on the front end calls it.** Grep confirms zero references
  outside its own file.

The two therefore disagree for any child type, and on 5 Sep 2026 that cost real time: a
Remembrance band was showing all 34 captain armband designs, I queried the AJAX endpoint,
got the correct single design back, and told Nick the filter was fine and his ticking was
at fault. It wasn't. I had tested a code path the page never runs.

**If you are debugging the design picker, read `customiser-frontend.php`.** Verify against
the rendered page (`window.BESPOKE_REGISTERED_DESIGNS`), never against admin-ajax.

Types that must keep their own curated list — the pre-designed bands — are named in
`bespoke_type_has_exclusive_designs()` in `customiser-products.php`, filterable via
`bespoke_exclusive_design_types`. They still inherit armband geometry, just not its
designs.


### 6.9 NEVER inline an image into customiser.html

On 6 Sep 2026 this file was **4.10MB**, of which **3.8MB (92.5%) was three base64
JPEGs** — `APEX_SRC`, `FALSE_NINE_SRC`, `FORTRESS_SRC`. Placeholder design photographs
from before designs were managed in WP admin. Each name appeared exactly ONCE in the
whole file, its own assignment: no other reference, no `window[...]` lookups, no `eval`.
Completely dead, and the registered designs replace `ALL_DESIGNS` at runtime anyway.

The file is **inlined into every product page**, so those three dead images were blocking
the first paint of the customiser on every product on the site. Removing them took it to
**0.31MB** and product pages from 4.5MB+ to about 740KB.

**If placeholder artwork is ever wanted again, reference it by URL.** Anything inlined
here is downloaded and parsed before a customer sees anything, on every product, forever.

Worth re-measuring occasionally:

```
node -e "const h=require('fs').readFileSync('assets/customiser.html','utf8');
console.log((h.length/1048576).toFixed(2)+'MB');
console.log('base64:', (h.match(/data:image\/[a-z]+;base64,/g)||[]).length)"
```

### 6.10 Test the customiser LOCALLY before asking Nick to upload

The Team Mug took far more rounds than it should have because I kept changing code and
asking Nick to check it. Almost every bug lived in state that only exists once real
designs are loaded, which no amount of reading the source will reveal.

The fix is a local harness: take `assets/customiser.html`, inject the globals
`customiser-frontend.php` would emit (`BespokeConfig`, `BESPOKE_REGISTERED_DESIGNS`,
`BESPOKE_PRODUCT_ASSETS` — copy the real values straight out of a live product page), and
serve it. The whole flow can then be driven with `goTo()` / the product's own functions
and the state inspected directly. That found in one pass what several upload-and-ask
rounds had not.

Two traps while doing this, both already noted above: the Browser pane is usually
`document.hidden`, which suspends `requestAnimationFrame` — shim it. And check the file
actually on the server before debugging, since a stale upload wastes the whole exercise.

---

## 7. Tooling notes (for you, on the new machine)

- **There is no PHP locally.** `tools/` in the repo has what you need — run `npm install`
  in that folder once:
  - `node tools/phprun.mjs lint <file.php>` — a real PHP 8.3 parse check (php-wasm).
    `php-parser` is installed too for a second opinion. **Always lint PHP before telling
    Nick to upload — a syntax error white-screens his site.**
  - `node tools/phprun.mjs render includes/customiser-hero-world.php out.html` — renders
    the hero shortcode with the WordPress functions stubbed, so its markup and script can
    be tested in a local page without a WordPress install.
  - `node tools/serve.js <folder> <port>` — zero-dependency static server. The Browser
    pane shows `file://` pages outside the project as static snapshots (no CSS, no
    images), so anything with relative assets has to be served. `.claude/launch.json`
    has a `hero-test` entry serving `C:\Users\nickl\Documents\GitHub` on port 8765.
- **`sharp`** (Node) converts frames to AVIF; **`ffmpeg-static`** pulls frames out of an
  MP4 losslessly (the portrait hero render arrived as a video).
- **Browser tooling:** the in-app browser tools work well against staging. Two traps:
  - `javascript_tool` **does not await Promises** — use a "kick and poll" pattern (start
    async work storing to `window.__x`, then read it in a second call).
  - The MCP content filter **blocks returning raw page HTML/URLs** — return booleans,
    measurements and short strings only, never page content.
- **Testing mobile:** the Browser pane's `resize_window` (preset `mobile`, or an explicit
  width/height) genuinely changes the viewport, so `@media` and `(orientation: portrait)`
  rules apply. Trap: preset `desktop` only restores the pane's own size, which is itself
  taller than wide — use an explicit 1280×720 for a landscape check. On a page you can't
  resize, an `<iframe style="width:390px">` at the same URL still works (its
  `contentWindow` is scriptable); that's how the mobile cart and armband bugs were found.

---

## 8. Current state — what's DONE

**Products live and verified end-to-end** (customise → cart → checkout → admin order):
Shin Pads · Grip Socks · Shin Pad Sleeves · Captain Armbands · **Double-Sided Armbands** ·
**Referee Armbands** · Player Cards · Football Pennant · Corner Flags · Man of the Match
(GameChanger) · Plate Trophy · Player of the Match · Water Bottles.

**Recent completed work:**
- Full QA sweep of all 20 shop products; full order-flow test of all 12 customisable ones.
- Five real bugs found and fixed (see §6) — two were **losing money**.
- Self-serve "Add a product type" admin tool.
- Referee Armbands built (inherits armbands; defaults to two-line "U15 / REF", black,
  size 110, on the yellow `pro-ref` design).
- Pennant Frill/No-Frill toggle now shows and records on the order.
- **Scroll-driven "World" hero** — see §9.

---

## 9. The homepage hero (most recent work)

`[bespoke_hero_world]` — a pinned hero where a rendered world of products on a floodlit
pitch rotates as you scroll, holding at each of four products with an info card.

**Journey:** Armbands (frame 0) → Shin Pads (30) → Grip Socks (60) → GameChanger (90 —
89 in the portrait set).

**Delivery decision — important.** Video was tried and **rejected**: browsers seek video by
keyframe so scrubbing arrived in ~6-frame lumps, and H.264 visibly softened the night sky
and grass. It uses **pre-rendered AVIF frames** (the same frames as WebP were ~21MB).
Frames load by priority — the first stop first, then the other three stops, then outward
from each stop.

**Two renders, picked by orientation** (`matchMedia('(orientation: portrait)')`, and it
swaps live if the phone is turned):
- `assets/hero-world/` — widescreen 1920×1080, **91 frames** (0–90), 4.8MB.
- `assets/hero-world-m/` — portrait 1080×1920, **90 frames** (0–89), 4.9MB. Nick's C4D
  export came out one frame short, so the trophies stop on 89 (3° off square — invisible).
  Stop frames are full size (q68); the in-between frames are 810×1440 (q45) — above what
  a phone canvas draws at, and only ever seen in motion.
- The portrait set is only offered if `hero-world-m/f0000.avif` exists on the server, so
  phones fall back to the widescreen set (cover-cropped) rather than a blank canvas when
  the folder hasn't been uploaded.
- Portrait layout: copy sits at the **bottom** over the grass, wash runs upward and clears
  at 44%. Bottom, not top, because the products' tops are ~27% down the portrait frame and
  a copy block is ~35% of a phone's height — at the top it would land across the socks and
  trophies. The dots sit up in the sky at 32% (mid-height crossed the right floodlight).
- The copy block is a CSS grid (eyebrow on row 1, all four cards stacked on row 2). It
  used to be absolutely positioned cards, which gave the block a 34px height — the eyebrow
  sat across the headline and the button hung off the bottom of short windows.
- Full-bleed width subtracts the scrollbar (`--bs-world-sbw`, set by the script) — plain
  `100vw` includes it and pushed the hero 8px off the left edge on desktop.

**Re-rendering the artwork.** Nick renders in C4D and saves an image sequence; the site
needs AVIF, so he cannot drop his files straight in. The cycle is: he saves the sequence
and tells you the folder → you convert and number them `f0000.avif`… (`sharp`, see
`tools/`) and zip them → he uploads and extracts. Keep 30 frames per 90° turn; if a frame
count changes, update `BESPOKE_HERO_FRAMES` / `BESPOKE_HERO_FRAMES_M` and the `frame` /
`mframe` stops. **Zip the files FLAT, with no containing folder** — SiteGround creates a
folder named after the zip, so `hero-world-m.zip` full of bare `.avif` files lands exactly
right (see the zip trap in §4).

**⚠ Never accept the animation as a video file.** The first portrait sequence arrived as
`World Mobile V1.mp4` — H.264, 90 frames, 2.14MB, about **6 Mbps / 24KB per frame**, while
the desktop sequence was rendered as **652KB JPG stills**. Extracting the frames from that
video is lossless, but the damage is already baked in: at 3x zoom on comparable grass the
video frames show smeared, waxy blades where the stills resolve individual ones. The AVIF
stop frames encoded from it are *larger* than the video frames they came from, so the
encoder is faithfully preserving H.264 artefacts. If a sequence turns up as a video, ask
for stills before spending any time on it.

**Render spec to give Nick** (he can output any pixel size from C4D):

| | Size | Frames | Notes |
|---|---|---|---|
| Portrait (phones) | **1440 × 2560** | 91 (0–90) | 9:16. Covers every phone at 3x density. |
| Widescreen (desktop) | **2560 × 1440** | 91 (0–90) | 16:9. Only helps retina/4K; lower priority. |

Image sequence, **JPEG at maximum quality or PNG — never a video**. DPI/PPI in the render
settings is irrelevant to the web and can be ignored; only pixel dimensions matter. Keep
30 frames per 90° turn and the same four product stops. Asking for 91 portrait frames
retires the odd `mframe => 89` special case.

Bigger sources barely cost file size, because only the **four stop frames** are encoded at
full resolution — the ~87 in-between frames are downscaled regardless, and downscaling
from a larger original is cleaner than encoding a small one. Projected totals stay near
7MB per set.

**Encoding recipe** (`sharp`): stop frames q80 at full size with `4:4:4` chroma; the
in-between frames q45 at 85% size with `4:2:0`. Weighting it this way matters — the eye
only ever rests on the four stops. Measured against Nick's originals, the stop frames are
close to indistinguishable; the mid-turn frames are visibly softer, but are only seen
while the page is actually moving. Each set lands around 6-7MB.

**Resolution ceiling.** At a 3x pixel-density cap a phone canvas reaches roughly 1300px
wide, so 1080×1920 is marginally under and 1440×2560 clears it. The widescreen render at
1920×1080 is well short on high-density laptops and 4K monitors. See the render spec table
above.

**Pixel density.** The canvas renders at the screen's real `devicePixelRatio` (capped at
3), not a flat 2x — that flat cap was the single biggest cause of a soft-looking hero on
phones, worth far more than any compression setting. It is also capped to the frames' own
resolution so a big monitor can't allocate a canvas of pure upscale.

**Frame rate and smoothness.** At 30 frames per 90° the world turns 3° per frame, and the
artwork travels about **64px across a 1080px-wide frame between adjacent frames** (measured
by correlating frames 10 and 11). One turn spans roughly 1300px of scroll, so a frame
changes about every 45px of finger movement — the picture moves faster than the finger,
which is why a slow scroll shows a visible step. Doubling to 60fps (181 frames, 1.5° each)
halves that jump and is the only lever that genuinely helps; changing the `scroll` length
alters how often the jumps arrive, not how big they are.

**Cross-fading between frames was tried and rejected.** Blending frame N with N+1 at
partial alpha sounded like free smoothness, but at 64px of travel it produces an obvious
double image, not motion blur — the armband's "C" simply appears twice. `BLEND-TEST.html`
in the World Animation folder reproduces it, and `BLEND-COMPARISON.png` shows it. Don't
re-propose this without re-checking that comparison.

If a 60fps sequence is delivered, ship every frame only if the set stays near 7MB;
otherwise encode every second frame and keep the rest in reserve — that needs no re-render,
just a different encode pass and the matching `BESPOKE_HERO_FRAMES` / stop values.

**Local test page:** `World Animation\MOBILE-TEST.html` is generated from the real
shortcode (`tools/phprun.mjs render`) with the frame paths pointed at `web/` and `web-m/`;
serve the GitHub folder (`hero-test` in launch.json) and open it at phone and 1280×720
sizes. Regenerate it after editing the PHP — it's a copy, not a link.

**Current settings (dialled in on the real site, don't change casually):**
```
[bespoke_hero_world]          → height 92, focus 27, veil 1, scroll 1000
```
- `height` — vh of the pinned window. Frames are **cropped, never squashed**, so this
  needs **no re-render**.
- `focus` — 0–100, which slice survives the crop. 27 protects the floodlights.
- `veil` — 0–1, strength of the dark wash behind the copy. It clears by 46% of the width
  so it never dulls the products.
- `scroll` — vh of scroll journey. Higher = gentler.

**Tooling left behind** in `C:\Users\nickl\Documents\GitHub\World Animation`:
- `FRAMING-TOOL.html` — live sliders for height/focus; outputs the shortcode. **Use this
  instead of guessing** whenever framing changes.
- `PROTOTYPE-V2.html` — the standalone frames prototype.

---

## 10. Outstanding work

*Reviewed against the live site on 5 Sep 2026. Everything in the plugin is uploaded and
live — nothing is sitting in git waiting.*

### Needs a decision from Nick
1. ~~Hero `veil` value~~ — **DECIDED 5 Sep 2026: keep it at `1`.** Nick: "lets keep, I
   think it looks stylish." Already the default in both the shortcode and the CSS, so
   nothing to change.
2. **The old Elementor hero** ("BUILT FOR YOUR BADGE") still sits below the new one, so two
   heroes stack. Nick wants to keep the slot and change its content. The suggestion on the
   table is a three-step "how it works" (pick → design → we make it), because nothing on
   the homepage explains the process. Its eyebrow currently duplicates the new hero's
   word for word, and its three bullets duplicate "Why Choose BEspoke" further down.
3. **The three mugs.** Football Legends, Grassroots Football and Aston Villa Inspired are
   all publicly listed and shoppable. Nick says none are going on the new site. Two
   wrinkles while they are up: Football Legends has **no price**, so it cannot be bought
   and shows "Read more" where every other product says "CUSTOMISE"; and the Aston Villa
   mug names a real club while the homepage small print says designs with
   copyright-protected badges such as Premier League clubs cannot be used.
4. **Player Cards** — the on-field position dropdown is optional. Skip it and the position
   box on the card is blank AND the six stats keep the outfield labels, so a goalkeeper
   who skips it gets PAC/SHO/PAS instead of DIV/HAN/KIC. Making it required is small.

### Ready to action
5. **Fancy Product Designer — one product away from removal.** Remembrance moved off it,
   and the **Grassroots Football Mug is now on our Team Mug customiser** (6 Sep 2026:
   Squad / Keeper / Outfield, two independently styled shirts, squad list captured as
   text). Only the **Aston Villa Inspired Mug** still loads FPD. Move or retire that one
   and the whole plugin can go, along with the "Customize"→"Customise" patch script in
   `customiser-woocommerce.php` (~line 185) that exists only to paper over it.
6. **Delete `assets/hero-world-m.zip` from the server** — 5.1MB of dead weight left over
   from the portrait hero upload.
7. **"BESPOKE: Read plugin files (AJAX debug)"** is still ACTIVE in Code Snippets. It
   serves plugin file contents over a web request. Fine on staging, must not reach live.
8. **Remembrance band attributes** — it uses one-off `width` / `length` attributes while
   the other 27 themed bands use the global `pa_band_thickness` / `pa_band-width`. It
   works (the matcher compares values, not names) but it is the only band set up that way.
   Its size guidance also disagrees: 24cm is "U11-U15" on standard bands, "U14-U16" here.

### Bigger pieces
9. **Go-live plan.** Everything is on staging. Moving to production is **not just plugin
   files** — designs, product artwork, saved placements and per-product config all live in
   the **WordPress database**. This needs writing down before the day.
10. **Meta tracking is failing.** Every page POSTs to Facebook's conversions endpoint and
    gets a 500 back. Unrelated to any of our work and invisible to visitors, but if
    conversion data is expected at launch it currently will not arrive.

### Known non-issues (don't re-investigate)
- Boys/Girls Creator Packs — **being removed** from the site.
- Nick has verified sizes come through correctly on armband orders.
- The Remembrance band DOES have a customiser now (ours). Earlier notes saying it has none
  are out of date.

---

## 11. How to pick up

1. `git clone https://github.com/BespokeSport/bespoke-customiser.git`
2. Read this file, then skim `includes/customiser-frontend.php` and
   `includes/customiser-woocommerce.php` for the two main flows.
3. Ask Nick what he wants to tackle — don't assume from the backlog order.
4. Before any PHP change ships: **lint it**. Before saying "it's fixed": **verify on
   staging with a cache-bust**.

**One last piece of advice:** the biggest wins on this project came from *not* accepting
the first explanation. "The badge is deleting" was actually a mask layer. "It's the sale
price" was actually the wrong variation. "It needs a re-render" was actually a crop
setting. Reproduce before you fix.
