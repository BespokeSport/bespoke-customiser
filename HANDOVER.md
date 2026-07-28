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

- **There is no PHP locally.** To syntax-check PHP, use Node:
  `npm install php-parser --no-save`, then parse and read `ast.errors`. **Always lint PHP
  before telling Nick to upload — a syntax error white-screens his site.**
- **`sharp`** (Node) is how the hero frames were converted to AVIF.
- **Browser tooling:** the in-app browser tools work well against staging. Two traps:
  - `javascript_tool` **does not await Promises** — use a "kick and poll" pattern (start
    async work storing to `window.__x`, then read it in a second call).
  - The MCP content filter **blocks returning raw page HTML/URLs** — return booleans,
    measurements and short strings only, never page content.
- **Testing mobile:** the browser reports a desktop viewport and won't resize. Create an
  `<iframe style="width:390px">` pointing at the same URL — its `@media` rules genuinely
  apply and `iframe.contentWindow` is scriptable. This is how the mobile cart and armband
  bugs were found.

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

**Journey:** Armbands (frame 0) → Shin Pads (30) → Grip Socks (60) → GameChanger (90).

**Delivery decision — important.** Video was tried and **rejected**: browsers seek video by
keyframe so scrubbing arrived in ~6-frame lumps, and H.264 visibly softened the night sky
and grass. It uses **91 pre-rendered AVIF frames** in `assets/hero-world/` (4.5MB total;
the same frames as WebP were ~21MB). Frames load by priority — frame 0 first (~191KB),
then the other three stops, then outward from each stop.

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
3. **Mobile hero render.** The desktop 16:9 sequence is cropped on phones. Nick will render
   a **dedicated portrait version**. Spec to give him: same 4 stops, same 30 frames per 90°
   turn, portrait (~1080×1920), linear keyframes, static camera, constant rotation speed.
   Then load it via a `matchMedia` switch.

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
