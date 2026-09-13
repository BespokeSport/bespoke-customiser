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


### 6.7 Fancy Product Designer — REMOVED 6 Sep 2026

*(Kept for context. Deactivated on 6 Sep 2026 after every product was moved off it; see
6.7b. Nothing below is current — it describes the situation that led to the removal.)*

### Historic: Fancy Product Designer was installed AND ACTIVE

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


### 6.7b Fancy Product Designer can now be switched off

Superseding §6.7. Swept every product on 6 Sep 2026 and **nothing loads the FPD designer
any more** — not one of the 15 Premier League mugs, not the Grassroots mug (moved to our
Team Mug type), not the Remembrance armband (moved to Remembrance Armbands).

The Premier League mugs never needed it. Their artwork is a fixed illustrated shirt, and
the surnames are collected by a **themecomplete Extra Product Options textarea**
(`tmcp_textarea_0`), which is a different plugin entirely and unaffected by FPD. The only
FPD file still enqueued anywhere is `frontend-woo-variations.js`, its WooCommerce
variations shim, which has nothing to shim once the designer is gone.

So FPD can be **deactivated without building anything**. After it is off and the mugs are
verified, the `Customize`→`Customise` patch script in `customiser-woocommerce.php`
(~line 178) can probably go too — its `.fpd-catalog-customize` selector dies with FPD,
and the related/up-sell selectors alongside it only ever mattered because FPD renamed
those buttons. Check the buttons read correctly before deleting it.

⚠ One caution: FPD stores its design data on historic ORDERS. Deactivating may make old
FPD orders unreadable in the admin. Irrelevant on staging pre-launch, but worth knowing
before doing the same on production.

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

### 6.11 The site-wide link colour swallows custom buttons

`bespoke-master.css` paints every link mint:

```
body.bespoke-themed:not(.bs-light) a:not([class*="elementor-"]):not(.button):not(.add_to_cart_button):not(.checkout-button)
```

Anything that is an `<a>` styled as a button but does not carry one of those classes
gets mint text, and pure white on hover. That selector is far more specific than a plain
`.my-btn { color: ... }`, so the button loses its own colour silently.

It caught `.bs-world-btn`. The hero CTA was rendering pale mint on mint at **1.40:1** —
effectively invisible — while the hero stylesheet plainly said `#04342C`. Nick spotted it
by eye on 12 Sep 2026. Reading the stylesheet would never have found it; what found it was
measuring `getComputedStyle` on the live button and then walking `document.styleSheets`
for every rule matching that element.

**Adding a custom button? Add its class to that exclusion list — both the base rule and
the `:hover`.** Otherwise this recurs.

Hero button text is now `#0D0D0D`: 9.7:1 on mint, and 5.1:1 under the scroll-cue scrim,
which dims it. White was considered and rejected: on mint it is 4.0:1, under the AA
threshold of 4.5:1 for normal text.

### 6.12 A full-bleed rule has to be undone ALL AT ONCE

`bespoke-product-page.css` section 6 makes the description tabs full-bleed with four
properties that only work as a set: `position: relative`, `left: 50%` (push right by half
the parent), `width: 100vw`, and a `-50vw` margin (pull back).

When the tabs were moved into `.bs-tabs-grid` beside the At a glance sidebar, the override
reset only the margins. That removed the pull-back while keeping the push and the width, so
on **every product page with that grid** the tabs were a whole viewport wide and shoved
right by half their column. Desktop: the `2fr` column blew out to the full screen width and
the sidebar sat off-screen. Phone: the right half of every line of the description was cut
off. Found 12 Sep 2026 when Nick looked at the mug page after editing its table — it was
never caused by the edit and affected shin pads identically.

Fixed by resetting `width`, `max-width`, `left` and `right` in the grid override, and by
making the grid `minmax(0, 2fr) minmax(0, 1fr)` rather than bare `fr` — a bare `fr` column
will not shrink below its content, so one over-wide child blows out the whole grid.

Verified against a local reproduction (`World Animation/PP-TEST.html`, same element chain
as the live page) loading the old and new stylesheet in turn, at 1600px and 375px.

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
  - `javascript_tool` **does await Promises** now — verified 10 Sep 2026: an async IIFE with
    awaited timeouts returns its result. (An older note said it did not; if a call ever comes
    back empty, fall back to kick-and-poll through `window.__x`.)
  - The MCP content filter **blocks returning raw page HTML/URLs** — return booleans,
    measurements and short strings only, never page content.
- **A collapsed Browser pane renders the page at 0×0.** `document.hidden` is true, `innerWidth`
  and `innerHeight` are 0, `(orientation: portrait)` MATCHES (0 is not less than 0), screenshots
  come back blank and rAF is suspended — so a desktop test run like that silently exercises the
  portrait code. `resize_window` gives a real viewport even while collapsed; shim rAF with
  `setTimeout`; and verify a canvas by pixel-matching rather than by eye: draw each candidate
  frame with the same cover-fit into an offscreen canvas, shrink both to 48×48 and compare.
  That proved every hero stop pixel-exact at phone and desktop sizes on 10 Sep 2026.
- **Write scripts with the Write tool, not shell heredocs.** On 10 Sep 2026 a heredoc dropped
  one of a pair of backslashes on the way to node (a regex meant to match an optional
  backslash matched nothing, twice), and a later heredoc was never terminated at all, so the
  git commit chained after it silently never ran. Where a backslash must be built in code,
  use `String.fromCharCode(92)`. Check a chained command printed every step it should.
- **Watch for MIXED line endings in the hero files.** On 12 Sep 2026
  `customiser-hero-world.php` held 554 CRLF lines and 32 LF ones, because successive
  patch scripts each detected the ending differently and wrote their own. Multi-line
  string anchors then matched NOTHING while single-line ones worked, which reads exactly
  like a missing anchor even though the text is there in front of you. Both hero files
  are normalised to CRLF now. If a multi-line anchor misses on text you can see, check
  the line endings before you doubt the text, or join anchor lines with a `?
` regex.
- **Programmatic scrolling looks broken in the pane, and is not.** The site sets
  `html { scroll-behavior: smooth }`, and the hero passes `behavior: smooth` explicitly
  when a dot or Skip is clicked. A smooth scroll is an ANIMATION, and a hidden pane
  produces no frames, so `scrollY` simply stays where it was — for direct calls and for
  clicks alike. Three attempts were burned on this before it was spotted, each looking
  exactly like a dead click handler. To observe a jump in the pane you need BOTH:
  `document.documentElement.style.scrollBehavior = auto` and a `window.scrollTo` patched
  to drop the options object. Real browsers are unaffected — verified on staging that all
  four dots land pixel-exact on their stop frames.
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

**Journey:** Armbands (frame 0) → Shin Pads (60) → Grip Socks (120) → GameChanger (180), in
both sets — 60 frames per 90° turn since 10 Sep 2026.

**Delivery decision — important.** Video was tried and **rejected**: browsers seek video by
keyframe so scrubbing arrived in ~6-frame lumps, and H.264 visibly softened the night sky
and grass. It uses **pre-rendered AVIF frames** (the same frames as WebP were ~21MB).
Frames load by priority — the first stop first, then the other three stops, then outward
from each stop.

**Two renders, picked by orientation** (`matchMedia('(orientation: portrait)')`, and it
swaps live if the phone is turned):
- `assets/hero-world/` — widescreen 1920×1080, **181 frames** (0–180), 10.5MB.
- `assets/hero-world-m/` — portrait 1080×1920, **181 frames** (0–180), 13.6MB.
  Both went from 30 to **60 frames per 90° turn** on 10 Sep 2026 (they were 4.8MB / 4.9MB).
  Stop frames are full size, q80 4:4:4; the in-betweens are 80% size (1536×864 / 864×1536),
  q40 4:2:0 — see the encoding recipe below.
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

**The scroll cue (12 Sep 2026).** Nick handed his phone to his 14-year-old, whose first
instinct was to TAP THE DOTS — it never occurred to him to scroll. Two quiet versions
failed before this one: grey `#5a5a5a` on `#0D0D0D` is about 2.6:1 contrast at 11px, and
even a bright corner label was still missed. What works is dimming the whole hero — a
scrim at `rgba(13,13,13,.55)` over the sticky window with the animated mouse Nick supplied
centred in it (`assets/bespoke-scroll-cue.gif`, 198×500, 75 frames, 3s, transparent
background). **It carries the words SCROLL DOWN itself, so do not add a label** — the
first attempt did and showed it twice. A GIF cannot be paused, so
`assets/bespoke-scroll-cue.png`, a still of frame one, is swapped in under
`prefers-reduced-motion`. Opacity is driven per frame from scroll progress: full at rest,
gone by 3.5%, so it lifts AS you scroll rather than snapping off at a threshold — which is
why there is no CSS transition on it, it would fight the per-frame updates.
`pointer-events: none` keeps the CTA underneath clickable.

**The opening card needs a lead-in (12 Sep 2026).** `FIRST_HOLD` was 0.18 — about 2% of
the journey — so the headline left almost the instant you started scrolling, while the cue
was still fading: you never got a clean look at it. That was a sound call when the hero sat
there undimmed and you read it before touching the wheel; the scrim overturned it. At 0.85
the opening card holds for about 9%. Measured on the real output: cue gone by 3.5%, card 0
still up at 9%, world turning by 11%. Raising it changes the share of the scroll the turns
get (4.8 of 9.4, not 4.8 of 8.73), which alone would speed the rotation up 7% per wheel
click, so `scroll` went 1600 → 1720 to keep the pacing Nick signed off on:
(1720−92) × 0.5106 = 831px of turning against (1600−92) × 0.5498 = 829px before.

**The dots jump to their product (12 Sep 2026).** That tap was the real signal, so rather
than make them look less like controls they became controls. They are real `<button>`s now
instead of `<span>`s, each with an aria-label ("Show shin pads", derived from the CTA text),
so they are keyboard reachable too. A click scrolls to the MIDDLE of that product hold, so
you arrive with the card up and the world still.

`scrollToCard()` is the exact inverse of the mapping in `onScroll`, run-up allowance
included — not an approximation. Verified by clicking all four out of order and
pixel-matching the canvas against the expected stop frames: every one landed exactly, diff
0.0. A `::before` widens the touch target to roughly 37x21px without changing anything
drawn; the dots sit 23px apart centre to centre, so targets stop just short of touching.
They are hidden in the reduced-motion layout, where every card is stacked as ordinary
content and there is no journey to jump to.

- Full-bleed width subtracts the scrollbar (`--bs-world-sbw`, set by the script) — plain
  `100vw` includes it and pushed the hero 8px off the left edge on desktop.

**Re-rendering the artwork.** Nick renders in C4D and saves an image sequence; the site
needs AVIF, so he cannot drop his files straight in. The cycle is: he saves the sequence
and tells you the folder → you convert and number them `f0000.avif`… (`sharp`, see
`tools/`) and zip them → he uploads and extracts. Keep 60 frames per 90° turn; if a frame
count changes, update `BESPOKE_HERO_FRAMES` / `BESPOKE_HERO_FRAMES_M` and the `frame` /
`mframe` stops. **Zip the files FLAT, with no containing folder** — SiteGround creates a
folder named after the zip, so `hero-world-m.zip` full of bare `.avif` files lands exactly
right (see the zip trap in §4).

**Frame URLs are versioned automatically.** Every render reuses the filenames `f0000…`, so
without a version a returning visitor's browser mixes cached frames from the old render with
new ones — two animations with different timing, spliced together. The shortcode appends
`?v=` plus the upload time of `f0000.avif` to every frame and poster URL, so replacing the
sequence busts the cache by itself. Nothing to bump by hand.

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
| Portrait (phones) | **1440 × 2560** | 181 (0–180) | 9:16. Covers every phone at 3x density. |
| Widescreen (desktop) | **2560 × 1440** | 181 (0–180) | 16:9. Only helps retina/4K; lower priority. |

Image sequence, **JPEG at maximum quality or PNG — never a video**. DPI/PPI in the render
settings is irrelevant to the web and can be ignored; only pixel dimensions matter. Keep
60 frames per 90° turn and the same four product stops (0, 60, 120, 180).

**The 10 Sep 2026 renders — which files are which.** The NAS folder
`Hero Animation/World Animation/Mobile` holds THREE sequences, mixed together:
- `Grass_World_Mobile_V2_…` — 91 frames, 30fps stills, 1080×1920 (3–4 Sep).
- `World 1_####` — 181 frames, 60fps, 1080×1920 (9 Sep). **This is the one that is live.**
- `World 1_i1_####` — **do not use.** 1440×2560, but only 124 of 181 frames, framed by a
  different camera, and the shin pad artwork and sock crests rendered solid black (missing
  textures). It cannot be topped up by rendering the last 57 frames: the scene has changed.

So the portrait set is still 1080×1920. For the sharper phone hero, re-render all 181 frames
at 1440×2560 from the current scene, then change `w: 1080, h: 1920` in the shortcode's
`SETS` to match — the pixel-density cap works from those numbers, and leaving them at 1080
would throw the extra resolution away. `Desktop/desktop World 1_####` (181, 1920×1080) is
complete. **Check a delivered folder with a contact sheet before converting it:** the
texture fault above is invisible in a frame count and obvious at a glance.

Bigger sources barely cost file size, because only the **four stop frames** are encoded at
full resolution — the 177 in-between frames are downscaled regardless, and downscaling
from a larger original is cleaner than encoding a small one. Measured totals are under
the encoding recipe.

**Encoding recipe** (`sharp`, effort 6): stop frames q80 at full size with `4:4:4` chroma;
the in-between frames **q40 at 80% size** with `4:2:0`. Weighting it this way matters — the
eye only ever rests on the four stops. At 60fps each in-between is on screen half as long as
at 30, which is what pays for the lighter setting. Measured on 10 Sep 2026 against the source
JPGs: 85%/q45 gave 14.3MB desktop + 18.3MB portrait; 80%/q40 gives **10.5MB + 13.6MB** with
no visible difference mid-turn; 75%/q36 starts smearing the sock crest and losing stars.
Raising effort to 8 saved nothing. Stop frames are indistinguishable from the source at 1:1.
(An older note here said 85%/q45 and 6-7MB per set; the frames actually live until 10 Sep
were lighter than that, with 75% in-betweens.)

**Pre-sharpening the stop frames (12 Sep 2026).** Nick reported the desktop hero looking
soft, and crucially said his source JPGs look fine on the same screen. That is the tell.
The files are faithful — source JPG and served AVIF are indistinguishable at 1:1, checked
on a crop of the crest lettering — but the page stretches a 1920-wide frame across a wider
screen, and stretching is where the detail goes. A photo viewer does not stretch it, which
is why the JPGs look better. Without a re-render the fix is `sharpen({ sigma: 0.8 })`
before encoding, pre-compensating for that stretch; measured against the source stretched
to 2560 it gets most of the way back, with no halos at 1:1.

Applied to the **four stop frames only**. Sharpening the in-betweens as well adds
high-frequency detail the encoder then has to pay for: it took desktop from 10.5MB to
14.4MB and portrait from 13.6MB to 19.0MB, all spent on frames that are only ever seen
while moving. Stops-only costs about 4%.

A canvas `imageSmoothingQuality` theory was tested first and is a DEAD END — Chrome
returns byte-identical pixels for `low`, `high` and the default at these scales. Do not
spend time on it again.

**Resolution ceiling.** At a 3x pixel-density cap a phone canvas reaches roughly 1300px
wide, so 1080×1920 is marginally under and 1440×2560 clears it. The widescreen render at
1920×1080 is well short on high-density laptops and 4K monitors. See the render spec table
above.

**Pixel density.** The canvas renders at the screen's real `devicePixelRatio` (capped at
3), not a flat 2x — that flat cap was the single biggest cause of a soft-looking hero on
phones, worth far more than any compression setting. It is also capped to the frames' own
resolution so a big monitor can't allocate a canvas of pure upscale.

**Frame rate and smoothness.** (Both sets moved to 60fps on 10 Sep 2026, 1.5° per frame;
what follows is why.) At 30 frames per 90° the world turns 3° per frame, and the
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
2. ~~The old Elementor hero ("BUILT FOR YOUR BADGE")~~ — **DONE 6 Sep 2026.** Replaced by
   the three-step `[bespoke_how]` "how it works" section (pick → design → we make it).
   Nothing on the homepage had explained the process, and its old copy duplicated both
   the new hero's eyebrow and the "Why Choose BEspoke" bullets further down.
3. **The three mugs.** Football Legends, Grassroots Football and Aston Villa Inspired.
   Grassroots Football was moved onto our customiser on 6 Sep 2026 and Nick confirmed
   "the mugs are good", so they ARE going on the new site — an earlier note here saying
   otherwise was out of date. Two things still open: Football Legends has **no price**,
   so it cannot be bought and shows "Read more" where every other product says
   "CUSTOMISE"; and the Aston Villa mug names a real club, while the homepage small
   print says designs using copyright-protected badges such as Premier League clubs
   cannot be used.
4. ~~Player Cards position dropdown~~ — **DONE 5 Sep 2026: now required.** Leaving it
   blank used to produce a card with an empty position box AND the outfield stat labels,
   so a goalkeeper got PAC/SHO/PAS instead of DIV/HAN/KIC.

### Ready to action

**PRODUCT COPY AUDIT — 9 Sep 2026, re-checked and updated 13 Sep 2026.** Findings and
paste-ready copy: <https://claude.ai/code/artifact/6680476b-51b8-4db5-ab7e-15826158bf72>

4a. ~~The "At a glance" sidebar is copy-pasted and factually WRONG on 12 products~~ —
    **DONE 13 Sep 2026.** Nick rewrote all 12; nothing claims FA-safety approval and
    only shin pads, sleeves and grip socks say "sold as a pair". Almost certainly not
    a copy-paste: the product editor pre-filled an EMPTY box with exactly that armband
    set as real input values, and they saved with the product on its first Update. The
    starter rows are now placeholders only (`bespoke_pdp_render_glance_row()` takes a
    `$hint` pair), so an untouched box saves nothing. Returns rows were removed on
    purpose — everything is personalised, so returns are not accepted.

4b. ~~The lead time contradicts itself~~ — **DONE 13 Sep 2026.** All 14 boxes say 3-5
    working days, the capitals "allow up to 7 days" notice is gone from all three
    pages, and the shin pads subtitle says "3-5 day turnaround".

4c. ~~Typos~~ — **DONE 13 Sep 2026.** The re-check read every customiser product's full
    copy (the 9 Sep pass had not) and found 23 fixes across 13 products, all made and
    verified on staging. The worst was corner flags saying "full sets of four" when
    they sell 1, 4 or 6. The "36 descriptions with a space before punctuation" figure
    did not reproduce: a check that keeps paragraph breaks found 2, both fixed. The
    other 74 products have not been proofread word by word.

4d. ~~Only 2 of 16 products have the full page treatment~~ — **DONE 13 Sep 2026** for
    eyebrow, subtitle and price suffix on all 14 customiser products. The eyebrow is
    now **"THE CUSTOMISER"** everywhere — Nick's call; "SERIES 03 ·" was dropped, and
    the admin placeholder follows. The hero price now reads "FROM £10.00", not
    "FROM £10.00 – £12.00": the template collapses WooCommerce's range to the lowest
    price whenever the FROM caption shows, via a `woocommerce_format_price_range`
    filter added and removed around that one call. **Still open:** sizing charts
    exist only on shin pads and grip socks. Shin pad sleeves and the glass trophy
    already list measurements in their descriptions, so those two are ready.

4e. **Two products are unfinished and unbuyable but publicly reachable** (still true
    13 Sep): `pre-designed-custom-captains-armbands` and
    `football-legends-mug-choose-your-team` have no description, no price and no
    customiser.

4f. **Basket test passed.** All 14 customiser products add to basket correctly via the
    real server path, recording design, size, colours, names and numbers. Detection
    tip: a product has the customiser if its page contains `BespokeConfig`.
    `bespoke-customiser-root` is NOT a valid test — it appears in the CSS on every
    product page, and `productType` matches WooCommerce's own "variable" on many.

4g. **Security scan clean.** No exposed config/backups/logs/.git, no directory
    listings, XML-RPC blocked, user enumeration blocked, and the badge folder's PHP
    deny rule verified LIVE (a non-existent .php returns 403, not 404). Missing:
    X-Frame-Options, Strict-Transport-Security, Referrer-Policy, CSP — none urgent,
    the first two worth adding in SiteGround before launch. (All four still missing
    13 Sep 2026. The removed `bespoke_get_designs` endpoint confirmed gone: 400 "0".)

    NB: heavy automated testing trips SiteGround's bot protection (sgcaptcha) and
    then returns a 249-byte challenge page instead of the file. **Always assert the
    response size before trusting a grep count** — it silently produced a wrong
    "0 references" result during this audit.

4h. **Armband ages disagree — Nick to decide.** The 29 armbands on the global
    `pa_band-width` attribute say 20cm = U8–U12 and 24cm = U11–U15. Remembrance,
    referee and Black History Month use a CUSTOM "Length" attribute saying 20cm =
    U9–U13/14 and 24cm = U14–U16, so an U13 player is sent to different bands. The
    option names clash too: "Band Width" is the size round the arm on the 29, but
    "Width" is 5cm/8cm on the other three. If the remembrance ages are right, rename
    the six `pa_band-width` term NAMES (never the slugs — variations store slugs) and
    all 29 update safely. If the captains ages are right, the custom values must
    change AND every variation of those three products be re-picked, because
    variations of a custom attribute store the literal text. A 13 Sep check found all
    261 variation attribute values still matching their options.

5. ~~Fancy Product Designer~~ — **REMOVED 6 Sep 2026.** Every product is off it, it is
   deactivated, and the `Customize`→`Customise` patch script it required has been deleted
   from `customiser-woocommerce.php`. The plugin folder is still on the server and can be
   deleted whenever. NB: it stored design data on historic orders, so expect old FPD
   orders to read oddly in the admin.
6a. **Uploaded 6 Sep 2026:** the Review step's "Design" row read `DESIGN_CATALOGUE`, an
    array declared empty and never filled, so it showed an em dash on EVERY product and
    every order. It now reads `window.BESPOKE_REGISTERED_DESIGNS`, which is what
    `fillMugSummary()` in the same file already used. Two lookup paths existed; only one
    worked (see §6.8).

6b. **Uploaded 6 Sep 2026:** the two anonymous upload endpoints are now rate-limited
    (`bespoke_upload_rate_limited()` in `customiser-ajax.php`). Each already capped one
    file at 5MB but nothing stopped repeated posts, and a full disk on shared hosting
    takes the shop down. 60 per 10 minutes — deliberately generous, since a real order
    uses two and a club can share one address. **It disables itself when it cannot tell
    visitors apart**, so an nginx proxy hiding the visitor address can never put every
    customer in one bucket and block a sale.

6. **Delete `assets/hero-world-m.zip` from the server** — 5.1MB of dead weight left over
   from the portrait hero upload.
7. ~~"BESPOKE: Read plugin files (AJAX debug)"~~ — **DELETED 6 Sep 2026 by Nick.** It
   served plugin file contents over a web request and must never have reached live.
8. **Remembrance band attributes** — it uses one-off `width` / `length` attributes while
   the other 27 themed bands use the global `pa_band_thickness` / `pa_band-width`. It
   works (the matcher compares values, not names) but it is the only band set up that way.
   Its size guidance also disagrees: 24cm is "U11-U15" on standard bands, "U14-U16" here.

### Bigger pieces
9. ~~Go-live plan~~ — **WRITTEN 6 Sep 2026, then rewritten twice the same day; updated
   13 Sep 2026.** Full runbook: <https://claude.ai/code/artifact/756410b7-3776-41ce-9c00-0a6516718707>

   **Do NOT use SiteGround Full Deploy.** It copies the staging database over the live
   one, destroying every order taken since the staging copy. It is also unnecessary.

   **The route (Nick's idea, and the right one): upload files, import four things,
   never touch the orders.** The live orders and customer accounts are never moved,
   copied, exported or replaced — they just stay where they are. Everything else is
   either a file upload or an ADDITIVE import.

   Travels as files: the plugin folder, all design artwork, product assets, fonts, hero
   frames, and the site-wide styling (which is why `customiser-brand-base.php` was
   worth migrating out of the Custom Code plugin — it is now a file, not a DB record).

   Must be moved as data — four things only:
   1. The design library, ~70 `bespoke_design` posts (Tools → Export/Import)
   2. The font list — a single option, `bespoke_customiser_fonts`
   3. Customiser setup on **14 products** (not 88 — verified 6 Sep 2026)
   4. The homepage + How It Works Elementor layouts

   The 14: shin pads, shin pad sleeves, captains armbands, double-sided armbands,
   referee armbands, remembrance armbands, grip socks, corner flags, pennant, player
   cards, grassroots mug, and the three trophies (traditional, player-of-the-match,
   plate). Detect them with the presence of `BespokeConfig` in the page source —
   `bespoke-customiser-root` is NOT a reliable test, it appears in the CSS on every
   product.

   **⚠ Two traps, both verified 6 Sep 2026:**
   - `_bespoke_layers` stores artwork as an **absolute URL** containing
     `staging2.bespokesport.uk`, not an attachment ID. After importing designs to live,
     run a find-and-replace to `www.bespokesport.uk` **before** the products go live,
     or every design quietly loads its images from staging and breaks the day staging
     is refreshed.
   - `_bespoke_thumb_id` IS an attachment ID. Product IDs match across the two sites
     (checked five, all matched — staging inherited them from live), but images
     uploaded to staging since the copy have IDs that mean something else on live, so
     expect a few wrong design thumbnails. Visible immediately in the design picker.

   **⚠ Three more, found 13 Sep 2026:**
   - **WooCommerce's CSV export cannot carry the BEspoke Page content.**
     `WC_Product_CSV_Exporter::prepare_meta_for_export()` skips any meta value that is
     not scalar (unless `woocommerce_product_export_meta_value` converts it), and
     `_bespoke_at_a_glance`, `_bespoke_sizing_chart`, `_bespoke_sizing_headers` and
     `_bespoke_customiser_sizes` are all arrays. The importer then writes every column
     the file has (`set_meta_data()` → `update_meta_data()`), so a full export would
     also reset live prices and stock to staging's. Plan: a small copy tool in the
     plugin that exports only `_bespoke_*` meta plus description and short description
     for chosen product IDs, and imports them on live after a preview. NOT BUILT YET.
   - **Live and staging differ structurally on three products** (Store API on both
     shops): shin pads are variable on live (Size S/M/L, all £20) but simple on staging;
     the pennant is variable on live (Frilled / Non Frilled, £10–12) but simple £12 on
     staging; the glass Player of the Match trophy is £13–23 on live, £15–25 on staging.
     Shin pads must be made simple on live before its customiser appears; the other two
     are Nick's decisions. The other 13 of the 16 compared match on type, options and
     price range; player cards, the grassroots mug and the Man of the Match trophy are
     identical.
   - **The copy fixes exist on staging only.** Descriptions changed on 13 products,
     including the Manchester City and Nottingham Forest mugs, which are outside the
     14, so the copy tool has to cover 16. Copy edited on live before launch would be
     overwritten.

   Also found 13 Sep: both 60fps hero zips (`assets/hero-world.zip` 11.4MB,
   `assets/hero-world-m.zip` 14.9MB) are still on the staging server and publicly
   downloadable, and the two unfinished products (4e) are published on LIVE too.

   Sequence matters: everything invisible happens first. Uploading and activating the
   plugin changes nothing a customer sees, because the customiser only appears once a
   product has its setup. Only the product import and the Elementor pages are visible,
   so those two wait for a quiet hour.

   Also verified 6 Sep 2026: staging carries 88 products, live 92. The four extras are
   Boys/Girls Creator Packs, Thermal Cups and Water Bottles, all deliberately dropped.
   **No product exists on staging that is missing from live**, so every product change
   is an edit. On this route the four survive the move and must be removed by hand.

   Both sites run the same Astra theme and the same 18 plugins. The only differences:
   staging has our plugin + `woocommerce-services`, live has `official-facebook-pixel`.

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
