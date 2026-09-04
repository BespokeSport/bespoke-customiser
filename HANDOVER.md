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

When hunting a stray `<script>`, check both. Simple-CCJ wraps its output in
`<!-- start/end Simple Custom CSS and JS -->` comments — that's how to trace the source.
On 2026-07-20 I deactivated **"BEspoke Customise Button Fix"** (post 6622), a broken
"Read more"→"View Options" script with 4 extra `}` that threw a SyntaxError on every page.

### 6.6 Load-order trap
`customiser-frontend.php` echoes `customiser.html` at ~line 182 but only defines
`window.BESPOKE_PRODUCT_ASSETS` at ~line 352. **Anything in customiser.html that runs at
parse time cannot rely on that global.** This silently hid the pennant Frill toggle for
weeks (its `requireAlt` check read an undefined value).

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

**Encoding recipe** (`sharp`): stop frames q80 at full size with `4:4:4` chroma; the
in-between frames q45 at 85% size with `4:2:0`. Weighting it this way matters — the eye
only ever rests on the four stops. Measured against Nick's originals, the stop frames are
close to indistinguishable; the mid-turn frames are visibly softer, but are only seen
while the page is actually moving. Each set lands around 6-7MB.

**Resolution ceiling.** The portrait render at 1080×1920 already matches a phone screen
1:1, so rendering it larger gains nothing. The widescreen render at 1920×1080 IS a limit
on high-density laptops and 4K monitors; 2560×1440 would help there, and is the only place
a bigger render is worth the C4D hours.

**Pixel density.** The canvas renders at the screen's real `devicePixelRatio` (capped at
3), not a flat 2x — that flat cap was the single biggest cause of a soft-looking hero on
phones, worth far more than any compression setting. It is also capped to the frames' own
resolution so a big monitor can't allocate a canvas of pure upscale.

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

### Immediate / decided
1. **Hero `veil` value** — Nick was testing `veil="0"` vs `1`. Whatever he lands on should
   become the default in `customiser-hero-world.php` **and** the CSS fallback.
2. **The old Elementor hero** ("BUILT FOR YOUR BADGE") still sits on the homepage. Two
   heroes stacked. Needs a decision: replace it, or keep both.
3. **Mobile hero** — DONE and live on staging (4 Sep 2026), verified on Nick's own phone.
   Tuning knobs if he wants changes are all in the `@media (orientation: portrait)`
   block of the CSS (wash gradient, copy position, dot height).

### Backlog
4. **Migrate "BEspoke Global Styles"** — the last remaining Simple-CCJ entry — into the
   plugin, removing the dependency on that plugin entirely.
5. **Go-live plan.** Everything is on staging. Moving to production is **not just plugin
   files** — designs, product artwork, saved placements and product config all live in the
   **WordPress database**. This needs a written plan before the day.
6. **Unfinished products** — Thermal Cups and Football Flags show *shin pad* artwork
   (no product type set); Grassroots Football Mug is **£0.00**. Nick says these are hidden
   and not in round one — **verify before launch**.
7. **Player Cards** — position dropdown could be made required (currently optional, and
   the placeholder-as-answer bug is fixed).

### Known non-issues (don't re-investigate)
- Remembrance Armband has no customiser — **intentional**, it's pre-designed.
- Boys/Girls Creator Packs — **being removed** from the site.
- Nick has verified sizes come through correctly on armband orders.

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
