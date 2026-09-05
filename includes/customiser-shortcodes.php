<?php
/**
 * BEspoke Customiser — Reusable design shortcodes.
 *
 * Three drop-anywhere shortcodes for use inside blog posts, pages,
 * Elementor "Shortcode" widgets, or anywhere WordPress runs
 * do_shortcode():
 *
 *   [bespoke_ticker]       — the mint scrolling marquee
 *   [bespoke_promise]      — the dark "BEspoke Promise" 3-column block
 *   [bespoke_clubs_say]    — the "What clubs say" 3-card testimonial
 *
 * All three use the full-bleed CSS trick (`width: 100vw; margin-left:
 * -50vw; left: 50%`) so they break out of narrow content columns
 * (like the 720px article reading column) and run edge-to-edge —
 * unless their parent has `overflow: hidden`, in which case the
 * shortcode is clipped to that parent's width.
 *
 * Why shortcodes rather than Elementor sections:
 *   - inserting via [bespoke_ticker] in a blog post is one keystroke
 *   - same component looks identical on every page (single source of
 *     truth)
 *   - no risk of one post's Elementor section getting out of sync
 *     with another's
 *   - keyboard-paste-friendly in classic editor + block editor
 *
 * How to override the copy without editing this file
 * -----------------------------------------------------
 * Drop any of these filters into Code Snippets:
 *
 *   add_filter( 'bespoke_ticker_items', function() {
 *       return [ 'NO MINIMUM ORDER', 'UK MADE', '5 DAY DISPATCH' ];
 *   } );
 *
 *   add_filter( 'bespoke_promise_cards', function( $cards ) {
 *       $cards[0]['body'] = 'My custom body copy';
 *       return $cards;
 *   } );
 *
 *   add_filter( 'bespoke_clubs_say_quotes', function() {
 *       return [
 *           [ 'quote' => '…', 'author' => 'Name', 'club' => 'Club' ],
 *           // up to as many as you like
 *       ];
 *   } );
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-shortcodes.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. REGISTER the three shortcodes on init (early enough for any
 *    template that uses them).
 * --------------------------------------------------------------------- */
add_action( 'init', 'bespoke_register_shortcodes' );
function bespoke_register_shortcodes() {
	add_shortcode( 'bespoke_ticker',    'bespoke_shortcode_ticker' );
	add_shortcode( 'bespoke_promise',   'bespoke_shortcode_promise' );
	add_shortcode( 'bespoke_clubs_say', 'bespoke_shortcode_clubs_say' );
	add_shortcode( 'bespoke_how',       'bespoke_shortcode_how' );
}

/* -------------------------------------------------------------------------
 * 2. [bespoke_ticker]  — the mint scrolling marquee.
 *
 *    Usage:
 *      [bespoke_ticker]
 *      [bespoke_ticker items="ONE THING|ANOTHER THING|A THIRD"]
 *      [bespoke_ticker speed="40"]  (seconds for one full loop, default 30)
 * --------------------------------------------------------------------- */
function bespoke_shortcode_ticker( $atts ) {
	$atts = shortcode_atts( [
		'items' => '',
		'speed' => 30,
	], $atts, 'bespoke_ticker' );

	// Priority 1: items passed via the shortcode attribute (pipe-separated).
	// Priority 2: items injected via the `bespoke_ticker_items` filter.
	// Priority 3: the design's default items.
	if ( $atts['items'] !== '' ) {
		$items = array_filter( array_map( 'trim', explode( '|', $atts['items'] ) ) );
	} else {
		$items = apply_filters( 'bespoke_ticker_items', [
			'NO MINIMUM ORDER',
			'UK MADE',
			'5 DAY DISPATCH',
			'YOUR CREST YOUR COLOURS',
			'GRASSROOTS TO ELITE',
			'EXPRESS PRINT',
		] );
	}

	if ( empty( $items ) ) {
		return '';
	}

	bespoke_shortcodes_emit_css();

	// One "group" = item • item • item • … . We render TWO groups so
	// the animation can translate(-50%) for a seamless loop — when the
	// first group has scrolled off the left, the second group is in
	// exactly the same horizontal position as the first was when the
	// animation started, so the loop appears continuous.
	$build_group = function( $items ) {
		$html = '<div class="bs-ticker__group">';
		$first = true;
		foreach ( $items as $item ) {
			if ( ! $first ) {
				$html .= '<span class="bs-ticker__dot" aria-hidden="true">·</span>';
			}
			$html .= '<span class="bs-ticker__item">' . esc_html( $item ) . '</span>';
			$first = false;
		}
		$html .= '</div>';
		return $html;
	};

	$speed = max( 5, absint( $atts['speed'] ) );

	return sprintf(
		'<div class="bs-ticker" role="presentation">'
		. '<div class="bs-ticker__track" style="animation-duration:%ds">%s%s</div>'
		. '</div>',
		$speed,
		$build_group( $items ),
		$build_group( $items )
	);
}

/* -------------------------------------------------------------------------
 * 3. [bespoke_promise]  — the dark "BEspoke Promise" 3-column block.
 *
 *    Usage:
 *      [bespoke_promise]
 *      [bespoke_promise eyebrow="Our guarantees"]
 *    To swap the card copy, use the `bespoke_promise_cards` filter
 *    (see top of file).
 * --------------------------------------------------------------------- */
function bespoke_shortcode_promise( $atts ) {
	$atts = shortcode_atts( [
		'eyebrow' => 'The BEspoke Promise',
	], $atts, 'bespoke_promise' );

	$default_cards = [
		[
			'num'   => '01',
			'big1'  => 'NO',
			'big2'  => 'MIN.',
			'label' => 'Minimum order quantity',
			'body'  => 'Order one shin pad. One armband. One of anything. Every item made just for you.',
		],
		[
			'num'   => '02',
			'big1'  => '5',
			'big2'  => 'DAYS',
			'label' => 'From design to your door',
			'body'  => 'Sign off your proof on Monday, kit on the pitch by the weekend. Express print available.',
		],
		[
			'num'   => '03',
			'big1'  => 'UK',
			'big2'  => 'MADE',
			'label' => 'Printed in Hampshire',
			'body'  => 'Designed, printed and packed by us in Waterlooville. Your kit, our press, no middlemen.',
		],
	];

	$cards = apply_filters( 'bespoke_promise_cards', $default_cards );
	if ( ! is_array( $cards ) || empty( $cards ) ) {
		return '';
	}

	bespoke_shortcodes_emit_css();

	$cards_html = '';
	foreach ( $cards as $card ) {
		$cards_html .= '<article class="bs-promise__card">'
			. '<div class="bs-promise__num">' . esc_html( $card['num']  ?? '' ) . '</div>'
			. '<div class="bs-promise__display">'
				. '<span class="bs-promise__big">' . esc_html( $card['big1'] ?? '' ) . '</span>'
				. '<span class="bs-promise__big bs-promise__big--mint">' . esc_html( $card['big2'] ?? '' ) . '</span>'
			. '</div>'
			. '<p class="bs-promise__label">' . esc_html( $card['label'] ?? '' ) . '</p>'
			. '<p class="bs-promise__body">'  . esc_html( $card['body']  ?? '' ) . '</p>'
			. '</article>';
	}

	return sprintf(
		'<section class="bs-promise"><div class="bs-promise__inner">'
		. '<p class="bs-promise__eyebrow">%s</p>'
		. '<div class="bs-promise__grid">%s</div>'
		. '</div></section>',
		esc_html( $atts['eyebrow'] ),
		$cards_html
	);
}

/* -------------------------------------------------------------------------
 * 4. [bespoke_clubs_say]  — the "What clubs say" 3-card testimonial.
 *
 *    Usage:
 *      [bespoke_clubs_say]
 *      [bespoke_clubs_say eyebrow="From the side line"
 *                         title="Real words from real coaches."]
 *    To swap the quotes, use the `bespoke_clubs_say_quotes` filter
 *    (see top of file).
 * --------------------------------------------------------------------- */
function bespoke_shortcode_clubs_say( $atts ) {
	$atts = shortcode_atts( [
		'eyebrow' => 'What clubs say',
		'title'   => 'Words from the side line.',
	], $atts, 'bespoke_clubs_say' );

	$default_quotes = [
		[
			'quote'  => 'BEspoke nailed our crest first time and turned the order round in five days. Our captains\' armbands look smarter than the seniors\'.',
			'author' => 'Dan Mitchell',
			'club'   => 'U14 Manager · Brampton Rovers',
		],
		[
			'quote'  => 'We needed bespoke shin pads for our cup final in three weeks. BEspoke delivered in five days. The kids haven\'t taken them off since.',
			'author' => 'Sarah Patel',
			'club'   => 'Chair · Chiswick Celtic',
		],
		[
			'quote'  => 'Every season we battled minimum orders elsewhere. Order what you need, when you need it — that\'s how it should work.',
			'author' => 'Mark Henderson',
			'club'   => 'Head Coach · Burnfields FC',
		],
	];

	$quotes = apply_filters( 'bespoke_clubs_say_quotes', $default_quotes );
	if ( ! is_array( $quotes ) || empty( $quotes ) ) {
		return '';
	}

	bespoke_shortcodes_emit_css();

	$cards_html = '';
	foreach ( $quotes as $q ) {
		$cards_html .= '<blockquote class="bs-clubs-say__card">'
			. '<p class="bs-clubs-say__quote">' . esc_html( $q['quote'] ?? '' ) . '</p>'
			. '<footer class="bs-clubs-say__footer">'
				. '<span class="bs-clubs-say__author">' . esc_html( $q['author'] ?? '' ) . '</span>'
				. '<span class="bs-clubs-say__club">'   . esc_html( $q['club']   ?? '' ) . '</span>'
			. '</footer>'
			. '</blockquote>';
	}

	return sprintf(
		'<section class="bs-clubs-say"><div class="bs-clubs-say__inner">'
		. '<p class="bs-clubs-say__eyebrow">%s</p>'
		. '<h2 class="bs-clubs-say__title">%s</h2>'
		. '<div class="bs-clubs-say__grid">%s</div>'
		. '</div></section>',
		esc_html( $atts['eyebrow'] ),
		esc_html( $atts['title']   ),
		$cards_html
	);
}

/* -------------------------------------------------------------------------
 * 4b. [bespoke_how] — the three steps of ordering.
 *
 *    Written for the homepage slot under the rotating hero, where nothing
 *    currently explains the process. The hero shows four products and four
 *    buttons; the obvious next question is "so how do I actually get one".
 *
 *    Every string is a shortcode attribute so the copy can be changed from
 *    the page without touching code:
 *
 *      [bespoke_how
 *         eyebrow="How it works"
 *         title="Your badge on it, in three steps."
 *         step1title="Pick your product"      step1body="…"
 *         step2title="Make it yours"          step2body="…"
 *         step3title="We make it and send it" step3body="…"
 *         cta="Start designing" ctaurl="/shop/"]
 *
 *    The defaults deliberately make NO claim about turnaround time. The
 *    other shortcode in this file ships a "5 DAYS from design to your door"
 *    default that nobody has confirmed, and a delivery promise is not
 *    something to guess at. Add the real figure to step 3 when it is known.
 * --------------------------------------------------------------------- */
function bespoke_shortcode_how( $atts ) {
	$atts = shortcode_atts( [
		'eyebrow'    => 'How it works',
		'title'      => 'Your badge on it, in three steps.',
		'step1title' => 'Pick your product',
		'step1body'  => 'Armbands, shin pads, grip socks, trophies and more. Every one made to order.',
		'step2title' => 'Make it yours',
		'step2body'  => 'Upload your badge, add the names and numbers, choose your colours. You see the finished thing as you build it.',
		'step3title' => 'We make it and send it',
		'step3body'  => 'Designed, printed and packed by us. No minimum order, so one of something is absolutely fine.',
		'cta'        => 'Start designing',
		'ctaurl'     => '/shop/',
	], $atts, 'bespoke_how' );

	$steps = [
		[ 'num' => '01', 'title' => $atts['step1title'], 'body' => $atts['step1body'] ],
		[ 'num' => '02', 'title' => $atts['step2title'], 'body' => $atts['step2body'] ],
		[ 'num' => '03', 'title' => $atts['step3title'], 'body' => $atts['step3body'] ],
	];

	bespoke_shortcodes_emit_css();

	$cards = '';
	foreach ( $steps as $step ) {
		$cards .= '<article class="bs-how__card">'
			. '<div class="bs-how__num">'   . esc_html( $step['num']   ) . '</div>'
			. '<h3 class="bs-how__title">'  . esc_html( $step['title'] ) . '</h3>'
			. '<p class="bs-how__body">'    . esc_html( $step['body']  ) . '</p>'
			. '</article>';
	}

	$cta = '';
	if ( $atts['cta'] !== '' && $atts['ctaurl'] !== '' ) {
		$cta = sprintf(
			'<a class="bs-how__cta" href="%s">%s</a>',
			esc_url( $atts['ctaurl'] ),
			esc_html( $atts['cta'] )
		);
	}

	return sprintf(
		'<section class="bs-how"><div class="bs-how__inner">'
		. '<p class="bs-how__eyebrow">%s</p>'
		. '<h2 class="bs-how__heading">%s</h2>'
		. '<div class="bs-how__grid">%s</div>'
		. '%s'
		. '</div></section>',
		esc_html( $atts['eyebrow'] ),
		esc_html( $atts['title'] ),
		$cards,
		$cta
	);
}

/* -------------------------------------------------------------------------
 * 5. CSS — printed inline the first time ANY of the three shortcodes
 *    renders on the page, never again. Static flag guards against
 *    duplicate emission if the same shortcode is used twice on one
 *    page (or two different shortcodes appear).
 *
 *    Inlined rather than enqueued because shortcodes can render
 *    anywhere (in widgets, inside other shortcodes, in Elementor's
 *    Shortcode widget) and there's no reliable way to detect "this
 *    page WILL use a shortcode" before output buffering kicks in.
 *    Inlining is one HTTP request fewer + guarantees the CSS
 *    arrives before the markup it styles.
 * --------------------------------------------------------------------- */
function bespoke_shortcodes_emit_css() {
	static $emitted = false;
	if ( $emitted ) {
		return;
	}
	$emitted = true;
	echo '<style id="bespoke-shortcodes-css">' . bespoke_shortcodes_get_css() . '</style>';
}

function bespoke_shortcodes_get_css() {
	return <<<'CSS'
/* Design tokens — scoped to our shortcode roots so they don't leak. */
.bs-ticker, .bs-promise, .bs-clubs-say, .bs-how {
	--bs-ink:        #0E0E10;
	--bs-deep:       #050505;
	--bs-panel:      #141417;
	--bs-mint:       #7FECB8;
	--bs-text:       rgba(255,255,255,0.85);
	--bs-muted:      rgba(255,255,255,0.55);
	--bs-faint:      rgba(255,255,255,0.40);
	--bs-line:       rgba(255,255,255,0.10);
	--bs-font-ui:    'Inter', system-ui, -apple-system, sans-serif;
	--bs-font-mono:  'JetBrains Mono', SFMono-Regular, ui-monospace, monospace;
	--bs-font-display: 'Anton', Impact, 'Arial Black', sans-serif;
}

/* ─────────────────────────────────────────────────────────────────
   FULL-BLEED HELPER  applied to all three sections so they escape
   narrow article columns and run edge-to-edge of the viewport. The
   technique: width:100vw + left:50% + margin-left:-50vw. Only
   defeated by a parent with overflow:hidden + a width smaller than
   the viewport.
   ───────────────────────────────────────────────────────────────── */
.bs-ticker, .bs-promise, .bs-clubs-say {
	position: relative;
	width: 100vw;
	max-width: 100vw;
	left: 50%;
	right: 50%;
	margin-left: -50vw;
	margin-right: -50vw;
	box-sizing: border-box;
}

/* ═══════════════════════════════════════════════════════════════════
   [bespoke_ticker] — mint scrolling marquee
   ═══════════════════════════════════════════════════════════════════ */
.bs-ticker {
	background: var(--bs-mint);
	color: var(--bs-ink);
	overflow: hidden;
	padding: 22px 0;
	display: block;
}
.bs-ticker__track {
	display: flex;
	align-items: center;
	white-space: nowrap;
	width: max-content;
	animation: bs-ticker-scroll 30s linear infinite;
	will-change: transform;
}
.bs-ticker__group {
	display: flex;
	align-items: center;
	gap: 36px;
	padding-right: 36px;
	flex-shrink: 0;
}
.bs-ticker__item {
	font-family: var(--bs-font-display);
	font-size: clamp(28px, 4vw, 44px);
	font-weight: 400;
	letter-spacing: 0.015em;
	text-transform: uppercase;
	line-height: 1;
}
.bs-ticker__dot {
	font-size: 18px;
	opacity: 0.6;
	line-height: 1;
}
.bs-ticker:hover .bs-ticker__track { animation-play-state: paused; }
@keyframes bs-ticker-scroll {
	from { transform: translate3d(0, 0, 0); }
	to   { transform: translate3d(-50%, 0, 0); }
}
@media (prefers-reduced-motion: reduce) {
	.bs-ticker__track { animation: none; }
}

/* ═══════════════════════════════════════════════════════════════════
   [bespoke_promise] — dark 3-column "BEspoke Promise"
   ═══════════════════════════════════════════════════════════════════ */
.bs-promise {
	background: var(--bs-ink);
	color: var(--bs-text);
	padding: 88px 0;
}
.bs-promise__inner {
	max-width: 1200px;
	margin: 0 auto;
	padding: 0 48px;
}
.bs-promise__eyebrow {
	font-family: var(--bs-font-mono);
	font-size: 11px;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--bs-muted);
	margin: 0 0 56px;
	font-weight: 500;
}
.bs-promise__grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 32px;
}
.bs-promise__card {
	border-top: 1px solid var(--bs-line);
	padding-top: 28px;
}
.bs-promise__num {
	font-family: var(--bs-font-mono);
	font-size: 13px;
	color: var(--bs-mint);
	letter-spacing: 0.16em;
	margin-bottom: 18px;
	font-weight: 500;
}
.bs-promise__display {
	display: flex;
	flex-direction: column;
	margin-bottom: 28px;
	line-height: 0.9;
}
.bs-promise__big {
	font-family: var(--bs-font-display);
	font-size: clamp(80px, 11vw, 140px);
	font-weight: 400;
	line-height: 0.9;
	color: #fff;
	letter-spacing: -0.005em;
}
.bs-promise__big--mint {
	color: var(--bs-mint);
}
.bs-promise__label {
	font-family: var(--bs-font-mono);
	font-size: 11px;
	letter-spacing: 0.16em;
	text-transform: uppercase;
	color: var(--bs-muted);
	margin: 0 0 18px;
	font-weight: 500;
}
.bs-promise__body {
	font-family: var(--bs-font-ui);
	font-size: 17px;
	line-height: 1.55;
	color: #fff;
	margin: 0;
}
@media (max-width: 980px) {
	.bs-promise__grid { grid-template-columns: 1fr; gap: 48px; }
}
@media (max-width: 768px) {
	.bs-promise { padding: 56px 0; }
	.bs-promise__inner { padding: 0 24px; }
}

/* ═══════════════════════════════════════════════════════════════════
   [bespoke_clubs_say] — "What clubs say" 3-card testimonials
   ═══════════════════════════════════════════════════════════════════ */
.bs-clubs-say {
	background: var(--bs-ink);
	color: var(--bs-text);
	padding: 96px 0;
}
.bs-clubs-say__inner {
	max-width: 1200px;
	margin: 0 auto;
	padding: 0 48px;
}
.bs-clubs-say__eyebrow {
	font-family: var(--bs-font-mono);
	font-size: 11px;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--bs-mint);
	margin: 0 0 18px;
	font-weight: 500;
}
.bs-clubs-say__title {
	font-family: var(--bs-font-ui);
	font-weight: 800;
	font-size: clamp(36px, 4.5vw, 56px);
	letter-spacing: -0.025em;
	line-height: 1;
	color: #fff;
	margin: 0 0 56px;
}
.bs-clubs-say__grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 20px;
}
.bs-clubs-say__card {
	background: var(--bs-panel);
	border: 1px solid var(--bs-line);
	border-radius: 14px;
	padding: 36px 32px 28px;
	margin: 0;
	position: relative;
	display: flex;
	flex-direction: column;
}
.bs-clubs-say__card::before {
	content: '\201C';
	position: absolute;
	top: 8px;
	left: 24px;
	font-family: var(--bs-font-display);
	font-size: 64px;
	line-height: 1;
	color: var(--bs-mint);
	opacity: 0.7;
}
.bs-clubs-say__quote {
	font-family: var(--bs-font-ui);
	font-size: 17px;
	line-height: 1.55;
	color: #fff;
	margin: 24px 0 28px;
	font-weight: 500;
	flex-grow: 1;
}
.bs-clubs-say__footer {
	display: flex;
	flex-direction: column;
	gap: 4px;
	border-top: 1px solid var(--bs-line);
	padding-top: 18px;
}
.bs-clubs-say__author {
	font-family: var(--bs-font-ui);
	font-weight: 700;
	color: #fff;
	font-size: 14px;
}
.bs-clubs-say__club {
	font-family: var(--bs-font-mono);
	font-size: 11px;
	letter-spacing: 0.1em;
	text-transform: uppercase;
	color: var(--bs-muted);
}
@media (max-width: 980px) {
	.bs-clubs-say__grid { grid-template-columns: 1fr; gap: 14px; }
}
@media (max-width: 768px) {
	.bs-clubs-say { padding: 56px 0; }
	.bs-clubs-say__inner { padding: 0 24px; }
}

/* ==========================================================================
   [bespoke_how] — three steps
   Same numbered-card language as the Promise block above, but the numbers
   lead rather than a big display figure: the point here is sequence.
   ========================================================================== */
.bs-how {
	background: var(--bs-ink);
	padding: 96px 24px 104px;
}
.bs-how__inner {
	max-width: 1180px;
	margin: 0 auto;
}
.bs-how__eyebrow {
	font-family: 'Anton', sans-serif;
	font-size: 12px;
	letter-spacing: .28em;
	text-transform: uppercase;
	color: #5DCAA5;
	margin: 0 0 14px;
}
.bs-how__heading {
	font-family: 'Anton', sans-serif;
	font-weight: 400;
	font-size: clamp(32px, 4.4vw, 60px);
	line-height: 1;
	text-transform: uppercase;
	color: #fff;
	margin: 0 0 56px;
}
.bs-how__grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 40px;
}
.bs-how__card {
	border-top: 2px solid #5DCAA5;
	padding-top: 22px;
}
.bs-how__num {
	font-family: 'Anton', sans-serif;
	font-size: 13px;
	letter-spacing: .2em;
	color: #5DCAA5;
	margin-bottom: 14px;
}
.bs-how__title {
	font-family: 'Anton', sans-serif;
	font-weight: 400;
	font-size: 24px;
	line-height: 1.1;
	text-transform: uppercase;
	color: #fff;
	margin: 0 0 12px;
}
.bs-how__body {
	font-size: 15px;
	line-height: 1.65;
	color: #c9c9c9;
	margin: 0;
}
.bs-how__cta {
	display: inline-block;
	margin-top: 52px;
	background: #5DCAA5;
	color: #04342C;
	font-family: 'Anton', sans-serif;
	letter-spacing: .14em;
	font-size: 14px;
	text-transform: uppercase;
	text-decoration: none;
	padding: 16px 34px;
	border-radius: 2px;
	transition: transform .2s ease, background .2s ease;
}
.bs-how__cta:hover {
	transform: translateY(-2px);
	background: #6fd8b4;
	color: #04342C;
}
@media (max-width: 900px) {
	.bs-how { padding: 64px 22px 72px; }
	.bs-how__grid { grid-template-columns: 1fr; gap: 34px; }
	.bs-how__heading { margin-bottom: 40px; }
	.bs-how__cta { margin-top: 38px; }
}
CSS;
}
