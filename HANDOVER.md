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

**Shared words — fixed 13 Sep 2026.** A match used to mean "shares any whole word", and
the strict pass returned the FIRST matching variation. Captains and double-sided
thickness terms are `5cm-band` / `8cm-band`, and the customer's "8cm band" shares "band"
with both — so the band choice was decided by list order. Reproduced through the real
add-to-cart handler on staging before fixing: on captains both choices landed as "Band
Thickness=5cm Band" at £10.00; on double-sided (8cm listed first) both landed as "8cm
Band" at £18.00, so 5cm orders were overcharged. Matches now score 2 (same label ignoring case and punctuation) or 1
(shared word), and both passes keep the best score. **Rule for product setup:** name
variation options so the customiser's label matches exactly one option EXACTLY. The
pennant's variations must be "With Frill" / "No Frill" — the toggle's own labels.
(76cc42f: a non-exact match now scores the number of shared words, exact = 1000, so
"6 Flags" picks "6 Corner Flags" over "4 Corner Flags" instead of tying.)

**Empty size buttons trap.** If a variable product has no customiser size buttons
(`_bespoke_customiser_sizes` empty), the size selector is hidden, `bespoke_size` is
sent empty, and the handler silently takes the FIRST variation — every order is that
size at that price. Shin pad sleeves and player cards were in this state on 13 Sep.

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

**13 Sep 2026, checked from the rendered pages (homepage, shop, cart, a product, a
collection page):** nothing from Simple Custom CSS and JS runs on staging's front end,
so nothing in Custom Code needs to travel at launch. Live runs exactly one entry on every
page: **4910 "FPD Inside/Outside Button Fix"** (copy in `custom-css-js/`), which makes
Fancy Product Designer's inside/outside buttons work on phones. FPD itself is still
active on live. Both are switched off on launch day — see §9, step 5 of the runbook.

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

**There is now a ready-made one:** `node tools/customiser-harness.mjs`, then open
`http://localhost:5178/armbands.html` (any product type as the page name). It rebuilds the
page from the current files on every load, fakes admin-ajax in the page, and captures the
add-to-basket POST in `window.__cartPayload` and each preview PNG in `window.__previews`,
so a whole order can be checked without touching staging. It has no band artwork or
registered designs, so add those globals by hand when a bug depends on them. Uploads are
simulated by building a `File` in the page and calling `handleBadge({target:{files:[f]}})`.

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

### 6.13 `sanitize_key()` lowercases — never compare its output to camelCase

The add-to-cart handler read the customer's removed elements ("badgeR,nameL") by running
each through `sanitize_key()` and keeping those `in_array( …, [ 'badgeL', 'badgeR', … ],
true )`. `sanitize_key()` lowercases, so "badgeR" became "badger" and nothing ever
matched. From Stage 2 (commit 88131e9) until 21 Sep 2026, **no order recorded a removed
element** on any product — the cart's "Removed" row and the admin's "Removed elements"
section could never appear, even when a customer deleted a shin pad name.

Found on 21 Sep when a real staging basket for a one-badge armband read "Club badge on
both sides". Fixed with `bespoke_parse_hidden_elements()` in customiser-ajax.php, which
matches on the lowercased key and returns the canonical camelCase one. Orders placed
before that still have an empty `hidden_elements`; `bespoke_badge_sides()` works out an
empty armband side from `badge2.mode` so they read correctly anyway.

### 6.14 A product with no price takes its own page down

`$product->get_price()` returns an **empty string** when no price is set — a
draft being built, or a variable product whose prices live on the variations.
PHP 8 refuses that: `number_format("")` throws
`TypeError: Argument #1 ($num) must be of type int|float, string given`, and
the product page dies with "There has been a critical error on this website".

Found 29 Sep 2026 on the new Boot Stickers draft, which had no price yet. The
page rendered as far as `price: '£` in `window.BespokeConfig` and stopped.
Fixed by casting at source in customiser-frontend.php, so an unpriced product
shows £0.00 instead of a white screen.

**Rule:** never hand `get_price()` straight to `number_format()`. Cast it.

---

### 6.15 The harness does not carry the frontend PHP's CSS overrides

`tools/customiser-harness.mjs` serves customiser.html on its own. The real page
also gets a block of CSS injected by customiser-frontend.php (~line 1743),
including:

```css
#bespoke-customiser-root .screen.active { display: flex !important; }
```

That is specificity (1,2,0) **with** !important, and it sits after everything in
customiser.html. Any rule that needs to hide or restyle a screen has to beat
it — `.bcp-choosing .screen` (0,2,0) loses, and even
`#bespoke-customiser-root .bcp-choosing .screen` only ties and then loses on
source order. Name `.screen.active` as well to get to (1,3,0).

Cost 29 Sep 2026: the boot sticker chooser worked perfectly in the harness and
was invisible on staging, twice.

**Rule:** anything touching `.screen` visibility gets tested on the real page,
not just the harness. Injecting the candidate CSS into the live page from the
browser console proves it in seconds without an upload.

---

### 6.16 Nobody could find the basket

Found 30 Sep 2026, the hard way: Nick asked someone to order from the site as
a test and they could not work out how to add to the basket. Add to cart only
existed on the review screen, and nothing told a customer the tabs were a
sequence with an end to reach.

Fixed 8 Oct 2026 for every product: the price and an **Add to basket** button
now sit in every step's nav row, and the tabs carry **Step 1 / Step 2** above
their names, numbering only the steps that product shows.

**Rule:** never let the only route to the basket be the last screen of a
multi-step flow. A customer who is happy after step one should be able to buy
from step one.

---

### 6.17 A media query carries no extra weight

bespoke-product-page.css sets `white-space: nowrap !important` on the sizing
chart cells. A phone override written **above** that rule, inside
`@media (max-width: 600px)`, loses: the two selectors are identical, a media
query adds nothing to specificity, and the later rule wins.

Cost 12 Oct 2026: the fix was written, committed, uploaded and still did
nothing. The file contained the rule and the browser ignored it.

**Rule:** a phone override goes **below** the rule it overrides, or into the
`@media (max-width: 600px)` block that already exists near the end of that
file. And check `getComputedStyle` on the real page rather than trusting that
the rule is in the file — the third time this project has lost to source order
(see 6.15).

---

### 6.18 The design renderer is private — watch for its work, don't wrap it

`applyRegisteredDesignSVG()` lives inside the script customiser-frontend.php
injects. It is **not** on `window`; the PHP calls it by its own local name,
on an animation frame, after `updateDesignLayers()` and `makeSVG()`. So a
wrapper around `window.applyRegisteredDesignSVG` never fires on the real
page — and `tools/customiser-harness.mjs` stubs that name as a global, which
made the wrapper look as if it worked.

Cost 13 Oct 2026: three commits that "reapplied the tags after a design
rebuild" reapplied nothing. The running-tag preview with a design chosen was
a black slab over the shoes the whole time.

**Rule:** to run something after the renderer has rebuilt an overlay, watch
the DOM for a `[data-bespoke-design-overlay]` node being added
(`bespokeTagWatchDesigns()` is the pattern — a MutationObserver on
`#bespoke-customiser-root`, once per batch). And prove any hook on the
staging page, in the console, before trusting the harness.

### 6.19 A tag design is a crop, and the first layer is the body

Every product's design goes through the same renderer: **layer 0 is the
shape that takes the body colour** (`S.bgColor`, the "Pad background" row),
**layers 1+ are patterns** tinted with their own colour, and every layer PNG
is drawn stretched over the whole 1200 canvas. That is the finished picture
for an armband. A running-tag design is exported as one tag, cropped —
**734 × 201**, the tag's extent in the Body stencil — so `applyTagPlates()`
draws each layer again into the two large plates, removes the stretched
original, and holds the layer to the stencil; `applyTagShoeOverlay()` carries
the plate copies down to the shoes with the badge and text.

What follows from the "first layer is the body" rule: a one-file design is
its own body, tinted the body colour — so the pattern vanishes into the tag.
Each tag design needs **two files**: the body crop (same file every time)
and the pattern, in that order. With the bulk importer that is
`Name--Body-#000000.png` + `Name--Pattern-#HEX.png` (+ `Name--thumb.png`);
"Body" sorts before "Pattern", which is the order the import reads them in.

The plates themselves (`BESPOKE_TAG_PLATES`) are the stencil's bounding
boxes, measured from the file's alpha: 734 × 201 at (383,117) and (383,359)
in the 1500px template. Earlier figures taken from an inner shape sat 9px
inside the edge and left a ring of body colour round every design.

Related: the bulk importer now files a `--thumb` as a Media Library
attachment and stores `_bespoke_thumb_id`, because that is the only key the
admin list, the edit screen and the picker read. Its first version stored a
URL under `_bespoke_thumb_url`, which nothing reads — 16 of the 17 imported
tag designs had no thumbnail.
### 6.20 Running tags: the two badge keys are two spots on ONE tag

On a running tag `badgeL` / `badgeR` are the **left end and right end of
the upper tag** — what the Left / Right badge cards describe — and the lower
tag repeats them through `BAND_MIRRORS.running_tags` (scale 1, one plate
pitch down), the same mechanism as the armbands' 3D mirror. So a pair of
tags always matches, and `+ Add another badge` / `Swap sides` / an empty
side all mean what they say on each tag. Text is per tag: `nameR` on the
upper plate, `nameL` on the lower.

Nick's spec, 14 Oct 2026: one badge, the same badge at both ends, or two
different badges — "full flexibility". A tag starts with one badge
(`bespokeBadgeSpotsStartMode()` answers `none` for tags, `same` for
armbands; `bespokeResetBadgeSpots()` takes a mode so "Put the club badge on
both sides" still asks for `same`). `bespokeUseBadgeSpots()` is the one
switch for which products run their badges through the cards and lose the
× on the preview; badges on those products lock to the strip
(`bespokeLockBadgeY`) and `bespokeClampArmbandBadgeX` clamps a tag badge to
its plate.

The first version of this (13 Oct) had the two keys meaning "a badge on the
upper tag" and "a badge on the lower tag", so a second badge could only
replace one of them.

**A pair that differs** (16 Oct 2026, "they can match but they can be
completely different too"): the RUNNING TAGS — THE PAIR section in
customiser.html. `S.tagsMatch` (default on) is the "Same badges on both
tags" switch above the cards; off, Right tag / Left tag tabs say which tag
the cards edit. The tag being edited lives in `S.*` as always, so the cards
and every handler are untouched; the other tag's arrangement (badge,
badge2, mode, swap, the two spots' hidden/pos/rot) is parked in
`S.tagStore`, its File objects in `S.tagFiles`, and `bespokeBandMirror()`
answers null so the lower plate is drawn from that snapshot instead
(`bespokeTagDrawOther()`, not draggable until picked). When the LEFT tag is
live its draggables sit in a `g.bcp-tag-live` wrapper translated one plate
pitch down — drags work in deltas, so positions stay in upper-plate terms,
exactly as the band-mirror clones do. At add-to-cart the right tag is made
live first; the left goes as `bespoke_tag_left` (its own uploads through
`bespoke_upload_badge`), parsed by `bespoke_parse_tag_pair()` into
`$d['tags']`, and shown by the running-tag renderers as "Right tag badges /
Left tag badges" with a second "Badge sides — left tag" section on the order
screen.
### 6.21 A saved placement outlives a change to what a key means

The admin "Save placement" editor stores a product's positions
(`BespokeConfig.geometry`, merged over `DEF` at load, `geometryIsOwn`
true). Change what a key MEANS in the code — as 6.20 did, `badgeL` from
"a badge on the lower tag" to "the left spot on the upper tag" — and the
saved placement quietly keeps the old meaning: on staging, Running Tags had
`badgeL` saved at (420,367), so the club badge landed on one tag, the
second badge on the other, and the lower tag went off the canvas when it
was being edited. The harness never shows this (its config has no
geometry).

**Rule:** when a key's meaning changes, go and look at what each product
has saved (`window.BespokeConfig.geometry` in the console on the real
page) before trusting the defaults — and guard the merge, as the running
tags do, when old saved values can be told apart.
### 6.22 Awareness tags: one product per theme, no product type per theme

Designs are registered against a product TYPE, so every product of a type
shows the type's whole list — fine for a picker, wrong for "Autism
Awareness Tags" as its own page for the search engines. Rather than a
self-serve type per theme (which would also need the inheritance chain
walked everywhere), a product can tick which of its type's designs it
shows: `_bespoke_design_allow` (design post IDs), the tick-list in the
product's BEspoke customiser box, read as `post__in` where
customiser-frontend.php builds `BESPOKE_REGISTERED_DESIGNS`.

So an awareness tag is: a WooCommerce product of type **Running Tags —
Themed** (steps: text + design; inherits the tags' assets and placement),
with ONE design ticked. `bespokeActiveSteps()` drops the design step for a
tag product with a single design, and the PHP calls `applyStepConfig()`
again once the design list is known, since the steps were first worked out
before it existed. A product with nothing ticked shows every themed design
(an "Awareness Tags" picker product); the Emergency ID tag is type
**Running Tags — Emergency ID**, with its own single design, and keeps its
own list (`bespoke_type_has_exclusive_designs`).

Artwork rule for themed designs (the renderer's, not ours): a layer PNG is
TINTED flat to one colour, so a full-colour flag or multi-colour logo must
be a **.jpg** (`isPhotoUrl`) — no transparency needed, the stencil cuts the
tag — while flat-colour artwork is PNG layers with "Customer editable"
unticked so the colours stay the charity's.

Country flags are ONE product ("Country Flag Running Tags", every flag
ticked), not one per country — Nick's call, 17 Oct 2026, for a single strong
page. That product has **Show the designs as a dropdown** ticked in its
BEspoke customiser box (`_bespoke_design_dropdown`, carried by Copy to
Live), so its picker is a dropdown (`.player-select`, as the boot stickers'
country list) instead of the thumbnail strip, in the admin's design order —
so the home nations go first by dragging them to the top of Customiser
Designs. It used to switch on by itself past twelve designs, which took the
pictures away from the plain running tags when they reached twenty; every
other product keeps the pictures.

The themed and ID tag types should carry **no artwork of their own** on
Product Setup: they then use the Running Tags background and Body stencil.
`bespoke_get_product_assets()` treats an entry with no files left (every
asset deleted) as none and falls back to the parent; before 17 Oct 2026 an
emptied entry left the type with no artwork at all. The first themed
product had the tag OUTLINE uploaded as its mask, so the design showed only
round the edges and the holes.

First paint: the renderer's hook paints at once, while `S.design` is still
the customiser's built-in 'vanguard' — not a registered design — so it
paints none; `patchDesigns()` then switches to the first registered design
and now calls `updateDesignLayers()` to paint it. Unseen where the customer
picks a design; fatal on a single-design product with no design step.
### 6.23 Launch moves files, never the database — so the copy tool carries the rest

Live takes orders all day; its database is never replaced (§ the 28 Sept
warning). Everything else crosses with **Copy to Live**
(`includes/customiser-copy.php`, export file format 2; format 1 still read):

- **Products already on live** — wording, BEspoke Page fields, customiser
  type, Rank Math, and *Designs shown*. Never prices, stock, options,
  variations, images or categories.
- **Products not on live** — made as **drafts** from `bespoke_copy_product_shape()`:
  simple/variable, prices, attributes + variations (a shared `pa_` attribute
  missing on live becomes the product's own; the customiser matches options
  by their words, so nothing breaks), postage class and categories by slug
  (made if missing — a new class has no rates, and the notice says so),
  tags, pictures sideloaded once from the source URL (tagged
  `_bespoke_copy_source`, so a re-run reuses them). Matching includes
  drafts, so running the same file twice never makes a product twice.
- **Designs shown** (`_bespoke_design_allow`) travels by design slug/title —
  design IDs differ after the WP design import. A list with any name
  missing is never written: short would offer fewer designs, empty would
  offer all of them.
- **Settings** — Product Setup artwork by *file name*, rebuilt at live's
  `BESPOKE_PRODUCT_ASSETS_URL` and only for files present there (never left
  pointing at staging); saved placements (cleaned to the Save placement
  ranges); self-serve product types. Artwork only adds/replaces the files it
  names for a type.
- **Undo** restores wording and settings and trashes the drafts it made.

Proved end to end in a throwaway WordPress + WooCommerce 11.2 with
WordPress Playground (`npx @wp-playground/cli server --blueprint …`, plugin
mounted, test script mounted beside it — 41 checks: export, renumbered
designs, missing class/categories, import, re-run, undo). On Windows Git
Bash, run it with `MSYS_NO_PATHCONV=1` or the `/wordpress/...` mount paths
get rewritten to `C:/Program Files/Git/...`, and mount a folder, not a
single file.
### 6.24 Boot stickers: 10mm tall, and the flag is a draggable of its own

The flag used to be drawn at the badge's LIVE position (`S.pos.badgeL`,
150 lower when a badge sticker is picked) on a layer that ignored the
pointer — so it couldn't be dragged, and dragging the badge carried it
along. It now has its own key, `S.pos.flag`, and `applyBootStickerOverlay()`
draws it as a `.draggable` with `data-drag="flag"`, so `setupDrag()` moves
it like the badge and the text with no drag code of its own, and it turns
with the same handle (`S.rot.flag`).

Until the customer moves it, it follows `bespokeBootFlagHome()`: a saved
spot if Save placement has one, otherwise under where the badge STARTS
(`DEF.badgeL`), or in that spot when there's no badge sticker.
`S._bootFlagHome` remembers the last home it was put at; once `S.pos.flag`
differs, it stays where the customer put it. Save placement only stores
`flag` once it has been moved by hand — saving the followed spot would pin
it under an empty badge spot on a flag-only set. `flag` is in the merge
list in customiser.html and the key lists in `customiser-products.php`
(save) and `customiser-copy.php` (Copy to Live).

**Every sticker — text, flag and badge — is made 10mm tall** (Nick, 17 Oct
2026), so nothing on this product is sized by the customer.
`bespokeBootSizes()` turns 10mm into canvas units in the view each starts
in — the badge 44 on the rear view (4.37 units/mm), the text a font size of
48 on the side (3.46, capitals 0.72 of the font size) — and
`bespokeBootStickerUI()` holds `S` to those, whatever a saved placement or
basket line says. The size sliders carry `data-boot-hide`;
`bespokeFixedSizes()` drops the resize corners and makes pinch and the
wheel do nothing (the page scrolls/zooms as normal). A badge keeps its own
shape at 10mm tall (`bespokeImageAspect()`, so a wide logo shows wider);
a rectangle flag is 1.6:1, measured off `assets/flags` (all 208 x 130).
The order records the size itself — `height_mm` from
`BESPOKE_BOOT_STICKER_MM` in `bespoke_parse_boot()`, not from the page —
and the order screen shows "Height: 10mm — every sticker"; orders from
before carry `text_height_mm` / `badge_width_mm` and still show those.

The text sticker is as long as the name needs. `bespokeBootTextSize()`
(every redraw) says under the text box how long it comes out — measured in
the customer's font with the capitals 10mm tall (`bespokeBootTextLengthMm()`,
canvas `measureText`, ink width over the font's own "H" height) — and,
past `BESPOKE_BOOT_ROOM_MM` (48, the side space on Nick's old size
buttons), that it may be too long for a smaller boot.

The "Change what goes on your stickers" link is now the full-width
**Main menu** button (`.bcp-main-menu`, inside `#boot-rechoose` in the
step bar).
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
- **Order totals on a phone** (commit 4022c4d, customiser-woocommerce.php, end of file):
  WordPress hides every column after the first below 782px, so the orders screen showed
  order/customer/date/status but no value. One rule inside the mobile media query, printed
  only on the orders screen, un-hides `td.column-order_total`. Verified by rebuilding the
  screen at 375px from the real wp-admin + WooCommerce stylesheets.

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

### The copy tool — built 25 Sep 2026 (commit ee5aa26)

`includes/customiser-copy.php`, admin page **Designs → Copy to Live** (capability
`manage_options`). Staging exports ticked products as JSON; live uploads that file, sees a
per-field plan, and only writes what's ticked. Each written product keeps its previous values
in `_bespoke_copy_backup`, and `bespoke_copy_last_import` drives a one-click undo.

It carries the whitelist in `bespoke_copy_fields()` and nothing else: `post_content`,
`post_excerpt`, `_bespoke_product_type`, `_bespoke_eyebrow`, `_bespoke_subtitle`,
`_bespoke_price_suffix`, `_bespoke_at_a_glance`, `_bespoke_customiser_sizes`,
`_bespoke_sizing_headers`, `_bespoke_sizing_chart`, and Rank Math's title, description and
focus keyword. Price, stock, attributes, variations, images and categories are never touched
— that's the whole point, so don't "helpfully" add them.

Products match on slug first, then on id **only when the title also matches**; anything else
is skipped rather than guessed. Adding a field to the BEspoke Page tab means adding it to
`bespoke_copy_fields()` too, or it won't travel.

Tested in php-wasm against a stand-in WordPress (`scratchpad/copy-tool-test.mjs` pattern):
identical fields don't appear in the plan, an unmatched product is skipped, applying writes
only the changed fields, price and stock survive, a second run is a no-op, and undo restores.
**Not yet run against a real WordPress** — do the dry run first: export from staging, upload
that same file back into staging, and every product should read "already the same".

### Translation (TranslatePress) — started 25 Sep 2026

Nick is adding French, then German and Spanish (maybe Italian), for European customers.
TranslatePress + Personal licence is installed **on staging only**, French configured but
not active; the SEO Pack is on, automatic translation is on, and 50,000 AI words are
unused. Live has no TranslatePress at all.

**The order of work: launch first, translate after.** Two reasons. Translations are keyed
to the exact English string, and launch replaces live's English wholesale, so anything
translated beforehand orphans itself. And TranslatePress keeps its translations in its own
`wp_trp_*` tables, which the launch never copies — his project notes said "push DB with
files", which would destroy live's orders and must not happen. Redoing the (small) French
setup on live is quicker and safer than moving tables.

**Done (commit 856f614):** `includes/customiser-i18n.php` holds every phrase the customiser's
script writes at runtime as literal `__()` calls — TranslatePress reads gettext, but never
sees text that JavaScript creates. It prints `window.BESPOKE_I18N` before customiser.html,
and `bespokeT()` looks phrases up by their English text, falling back to the English in the
script. Order-critical values (sizes, band thickness, print options, font names) are
deliberately NOT translated — WooCommerce matches variations on those strings.
`tools/customiser-harness.mjs?i18n` marks every known string «like this» so you can see at a
glance what still writes hard-coded English.

**Second pass done (commit 6eaffa7):** the team mug (step tabs, squad count, player-count
options, both tab hints), design layer names (`Layer N`), and the heading above the band
thickness / print / frill buttons. Player positions never needed it — they're `<option>`s in
the page's HTML. **Player-card stats too (commit 7df70f4):** Nick asked for the six labels to
follow the language the card is bought in, so a French card prints VIT / TIR / … Each stat now
reaches the order as BOTH the printed label and its English key (`bespoke_player_stat_N_key`),
and the admin card reads "VIT (PAC) 92" when they differ — production never has to translate
back. Orders placed before the key existed are unaffected. Admin-only Save-placement messages
don't need translating. Design names, size
button labels and product step labels come from admin data, so they'd be translated as data,
not here.

**Fonts:** Athletic and Jersey have no accented characters at all; Borg lacks ç ñ Ç Ñ ß;
Villain, Bespoke Libero and BEspoke Mezzala lack Æ Œ (and ß where they have lowercase). Nick
drew the fonts in Illustrator and will add the missing glyphs. Full per-font list on the NAS:
`BEspoke Customiser/Font accent gaps - 25 Sep 2026.md`. Re-check after any re-upload by
reading the font's cmap, not by eye.

### Needs a decision from Nick
1. ~~Hero `veil` value~~ — **DECIDED 5 Sep 2026: keep it at `1`.** Nick: "lets keep, I
   think it looks stylish." Already the default in both the shortcode and the CSS, so
   nothing to change.
2. ~~The old Elementor hero ("BUILT FOR YOUR BADGE")~~ — **DONE 6 Sep 2026.** Replaced by
   the three-step `[bespoke_how]` "how it works" section (pick → design → we make it).
   Nothing on the homepage had explained the process, and its old copy duplicated both
   the new hero's eyebrow and the "Why Choose BEspoke" bullets further down.
3. **The mugs.** Grassroots Football and the club-inspired mugs (Aston Villa and the
   rest). Grassroots Football was moved onto our customiser on 6 Sep 2026 and Nick
   confirmed "the mugs are good", so they ARE going on the new site — an earlier note
   here saying otherwise was out of date. One thing still open: the Aston Villa mug
   names a real club, while the homepage small print says designs using
   copyright-protected badges such as Premier League clubs cannot be used.
   "Football Legends Mug – Choose Your Team" is NOT a mug to sell: it is the umbrella
   tile for the club mugs (see 4e). The £0 / "Read more" it shows is by design.
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

4e. ~~Two products are unfinished and unbuyable~~ — **WRONG, corrected 13 Sep 2026.**
    `football-legends-mug-choose-your-team` (product 5494) and
    `pre-designed-custom-captains-armbands` (product 6060) are deliberate umbrella
    tiles, confirmed by Nick. Each is a £0 product whose permalink opens a collection
    page: "Custom Shirt Mugs" (page 5495, `/custom-shirt-mugs/`, 45 club mugs) and
    "Pre-Designed Custom Captains Armbands" (page 6061, 29 armbands). That is how the
    two ranges get a tile in the shop grid and on the homepage. Set up identically on
    live. **Leave them published** — Draft would remove the only tiles leading into
    those two ranges. Detection tip: their Store API `permalink` is not under
    `/product/`.

4f. ~~Basket test passed~~ — **TOO SHALLOW, corrected 13 Sep 2026.** The 9 Sep test
    checked that each add-to-cart SUCCEEDED, not WHICH variation and price it landed on.
    Re-tested through the real handler, reading the Store API cart line back: captains
    8cm went in as 5cm at £10, double-sided 5cm as 8cm at £18 (both fixed, commit
    5341713, verified on staging); corner flags "6 Flags" as 4 flags at £60 (fixed in
    76cc42f, needs uploading and re-testing); shin pad sleeves and player cards ALWAYS
    go in as Small at £15, because neither has customiser size buttons, so no size is
    sent and the handler falls back to the first variation (needs size buttons whose
    labels equal the variation names). Grip socks can never select "Inside & Outside
    Facing (extra £2)" — the customiser has no control for it. Correct: referee,
    remembrance, grip socks sizes, glass trophy sizes, 1 and 4 flags, shin pads.
    **Any future basket test must read the cart line's variation and price back.**
    Original note: all 14 customiser products add to basket via the real server path,
    recording design, size, colours, names and numbers. Detection
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

4h. **Armband ages — DECIDED 13 Sep 2026: the captains scale everywhere** (16cm U6–U8,
    20cm U8–U12, 24cm U11–U15, 28cm Young Adult/Smaller Build, 32cm "Average" build,
    36cm Bigger Build). Two more places carry ages and must follow: (1) the CUSTOMISER
    size buttons (`_bespoke_customiser_sizes`, Line 1) on all four customiser armbands
    show a third scale — 16cm U6/U10, 20cm U10/U13, 24cm U12/U16 — and that is what
    customers actually pick from; (2) referee armbands offer 16cm and 20cm buttons
    although the product only has 24–36cm lengths (such an order resolves to 24cm).
    The three custom Length attributes use "Any" length on every variation (verified in
    the Store API), so renaming their values is safe without re-picking variations.
    Original finding: The 29 armbands on the global
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
   4. The homepage Elementor layout. (There is no separate How It Works page — checked
      13 Sep 2026. How It Works is the `[bespoke_how]` section on the homepage, rendered
      by the plugin, so it travels as a file.)

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
   `assets/hero-world-m.zip` 14.9MB) were still on the staging server and publicly
   downloadable (Nick deleted both the same day; both now 404). And **Fancy Product Designer is still active on live** (its assets
   load on shin pads and the armbands), along with Custom Code entry 4910 that exists
   only to fix its phone buttons. The runbook's step 5 now deactivates both on live,
   immediately before the product content is brought across.

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

   **But not the same versions (read from asset `?ver=` strings, 13 Sep 2026).** Live
   auto-updates; staging doesn't. Live: WooCommerce 11.1.0, Elementor 4.2.3, Elementor
   Pro 4.2.2, Astra 4.13.11, Astra Pro 4.13.8. Staging: WooCommerce 10.7.0, Elementor
   3.35.8, Elementor Pro 3.35.1, Astra 4.13.1, Astra Pro 4.12.4. Elementor 4 is a major
   version. Bring staging up to date and retest the customiser, basket, checkout and
   homepage BEFORE launch. PHP is not exposed (no `X-Powered-By`) and doesn't need
   checking: the plugin needs 7.4+ (arrow functions, no PHP 8-only syntax), and live
   already runs WooCommerce 11.1.

   **SEO (13 Sep 2026).** Both sites run Rank Math PRO. On the homepage and the 14
   customiser products, SEO titles and descriptions are identical on staging and live
   (14 of 15; the pennant differs only because it has no custom description and falls
   back to its short description). So nothing was ever edited on staging, and the
   problems are live in Google now: player cards carries the captains armbands
   description; "BESpoke" is misspelt in five descriptions; sleeves (296 chars),
   captains and player cards (256) are far too long, six more slightly over; titles mix
   "BEspoke Sport"/"BEspoke Sports"; the homepage title names only shin pads and
   captains armbands; the Man of the Match title says "GameChanger Award". Rank Math
   fields are post meta (`rank_math_title`, `rank_math_description`, …), so SEO edits
   made on staging must travel in the copy tool. Staging pages are `noindex, nofollow`
   (correct for staging, must never reach live); live pages are `index, follow` with
   canonicals. Product schema (ProductGroup, or Product with offers) comes from Rank
   Math on both and is unaffected by our template.

   **Theme settings (compared from the page code, 13 Sep 2026).** Astra global colours,
   fonts, container widths, the Elementor kit, the header menu items and the footer are
   identical. Two things are not: (1) staging's header is an **Elementor Theme Builder
   header, template 6607**, while live renders Astra's own `#masthead` — so the header
   template must be exported and imported at launch (runbook step 6), or live keeps the
   old header; (2) on BOTH sites eight footer menu links point at `#` (menus "Usefull
   Links" and the collection menu: Home, Shop, Design Studio, Contact Us, three
   products, and Personalised Water Bottles, a dropped product). Menus and the Site Title
   ("BEspoke Sports", which feeds the footer copyright and Rank Math's `%sitename%`;
   Nick chose "BEspoke Sport") live in each database, so they're fixed on both sites.
   Nick confirmed nothing else was changed on staging.

   **Decisions from Nick, 13 Sep 2026:** pennant keeps both options — With Frill £12,
   No Frill £10 (names must equal the customiser toggle labels, see §6.3); glass trophy
   keeps live's £13–23; launch on a Wednesday or Thursday; Aston Villa mug stays;
   umbrella tiles stay as they are; build the copy tool the week before launch.

   **More decisions, 21 Sep 2026:** security headers added (commit b5a4579, in
   `customiser-master.php`); pennant options named exactly "With Frill" / "No Frill";
   referee armbands come in all six lengths (16–36cm) in 5cm and 8cm, so 16cm and 20cm
   are added to its Length options; the footer's "Design Studio" link goes to /shop/;
   staging's Astra Pro stays unlicensed (the licence is on live); grip socks' "Inside &
   Outside" printing is still sold, so the customiser now offers it (commit 6433d97).
   **Facebook:** both sites run "Meta pixel for WordPress" AND "Facebook for
   WooCommerce" on one pixel (643897…), double-counting, and staging sends its test
   traffic to that real pixel. Plan: live keeps Facebook for WooCommerce only
   (reconnect under Marketing → Facebook with Nick's Meta Business Suite login);
   staging deactivates both. Nick asked for three customiser features, armbands
   first: a clearer badge section (upload, "+ Add another badge", "Swap sides"), a
   Photoshop-style font dropdown, and "Add text box" with no fixed limit.

   **Verified on staging 21 Sep 2026, after Nick's uploads** (real add-to-cart, cart
   line read back): corner flags 1 / 4 / 6 → £20 / £60 / £90; grip socks XL outside
   £12, XL inside and outside £14, S inside and outside £12; X-Frame-Options and
   Strict-Transport-Security sent on every page. **Tracking:** live now runs Meta for
   WooCommerce only (the plugin Meta renamed from Facebook for WooCommerce; folder still
   `facebook-for-woocommerce`), one pixel per page. On staging, Meta pixel for
   WordPress, Meta for WooCommerce, Google for WooCommerce, Site Kit and "Google
   Analytics for WooCommerce" (`woocommerce-google-analytics-integration`) are all off;
   checked 21 Sep, staging pages send nothing to Facebook or GA4.

   **Armband badge section rebuilt, 21 Sep 2026** (from the mockup Nick approved). After
   the club badge is uploaded, the upload box gives way to a Left and a Right card. The
   club badge starts on both sides; "+ Add another badge" is a `<label for="badge2-file">`,
   so it opens the file picker straight away and the new badge goes on the right; "Swap
   sides" appears once the sides differ; the × on the second spot leaves that side empty;
   "Put the club badge on both sides" undoes that; "Change club badge" / "Remove badges"
   sit under the cards. The old "Badge on the left" and "Same badge left & right" toggles
   are gone, and the double-sided share toggle now reads "Same club badge inside and
   outside" (it lives inside `#badge2-wrap` now). Data: `S.badge2Mode` ('same' /
   'different' / 'none') as before, plus `S.badgeSwap`, which puts the second spot on the
   LEFT (per face, in `BESPOKE_FACE_FIELDS`; sent as `bespoke_badge2_swap` and
   `badge2_swap` in the inside-face JSON; stored as `badge2.swap`). An empty side still
   rides in `hidden_elements`. `bespoke_badge_sides()` in customiser-woocommerce.php turns
   that into Left / Right: the basket and email show one "Badges" row (e.g. "Club badge
   left, second badge right"), the admin card a "Badge sides" section with the second
   badge's file linked. **Bug fixed on the way:** the preview capture in
   customiser-frontend.php swapped EVERY badge image to the club badge's server URL, so an
   order with a second badge had the club badge on both sides of its preview picture; it
   now swaps only the club badge's own images.

   **Verified on staging 21 Sep 2026** (Nick uploaded all four files; real add-to-cart,
   basket read back through the Store API, then emptied): captains with a second badge →
   "Club badge left, second badge right"; swapped → "Second badge left, club badge
   right"; double-sided with the inside swapped → outside "Club badge on both sides",
   "Inside badges: Second badge left, club badge right"; referee → "Club badge left,
   second badge right". The one-badge basket read "Club badge on both sides", which
   exposed the removed-elements bug in §6.13. Fixed the same day, uploaded and
   retested: one badge only → "Club badge left, right side empty" (swapped: "Club
   badge right, left side empty"), and a shin pad basket with the right name removed
   → "Removed: Right name". That exposed one more display gap: the typed text of a
   removed name still showed as "Right pad: …". Commit 89ccf16 leaves removed shin pad /
   grip sock text out of the basket line and shows "Removed by customer" on the admin
   card (not yet uploaded).

   **Font dropdown, 21 Sep 2026** (commit 1453170, not yet uploaded): on armbands
   (base type, so captains, double-sided, referee and remembrance) the Text step's
   card strip becomes one field that opens into a list, each font's name set in that
   font. Every other product keeps the cards. It reads "Choose a font" until one is
   picked, because armband text starts in Arial Black, which isn't in Nick's
   uploaded list (12 fonts, "Villain" first). The uploaded-fonts script in
   customiser-frontend.php used to wait for `.font-card` elements, which armbands no
   longer have. It now waits for `FONTS` / `buildFontGrid()` and redraws both pickers
   through it. Previewed on the staging page (code loaded into Claude's browser only):
   all 12 fonts load in the list, none clipped. **Uploaded and verified 21 Sep:** real
   baskets carry the picked font on captains (Sporten), double-sided (Villain outside,
   Borg inside), referee (RaceSport) and remembrance (Archive); the shin pad basket no
   longer lists a removed name (89ccf16 live).

   **Extra text boxes, 21 Sep 2026** (commit bb7a9cb, not yet uploaded): armbands'
   Text step has "+ Add text box" (max 10). `S.textBoxes` = [{id, text, font, color,
   size}], positions/rotation in `S.pos` / `S.rot` under the id ('tb1'…), so the drag
   code moves them; `textBoxes` is in `BESPOKE_FACE_FIELDS`. On the band-mirror view
   they borrow nameL's mirror mapping. Resizing (handles, pinch, wheel) has a 'tb'
   branch; `fastPinchSize` matches a box's id exactly. The font dropdown became
   `bespokeFontSelect(host, current, onPick)` so each box has one. Sent as
   `bespoke_text_boxes` JSON (and `text_boxes` in the inside-face blob), sanitised by
   `bespoke_parse_text_boxes()`; basket/email "Extra text", admin "Extra text" section
   with font, size, position, rotation and a colour swatch. **Uploaded and verified 21
   Sep** with real baskets on captains, double-sided (inside boxes → "Inside extra
   text"), referee and remembrance.

   **Armbands start in Villain** (Nick, 21 Sep; commit c1c0b81, not yet uploaded):
   `ARMBAND_START_FONT` in customiser.html, applied by `bespokeArmbandStartFont()`
   whenever the armband font field is drawn. It only moves text still in a font the
   customer can't pick (the built-in Arial Black), matched by label because uploaded
   fonts' family ids are just `bespoke-font-N`.

   **Fixes 3–6 checked on staging 21 Sep: none done yet.** Armband Line 1 labels are
   still the old ages, referee's Length still lacks 16/20cm, the pennant is still a
   simple £12 product, the footer links still point at "#" with "© 2026 BEspoke
   Sports", and sleeves / player cards still have no size buttons.

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
