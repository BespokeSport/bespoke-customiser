<?php
/**
 * BEspoke Customiser — Shop / category archive styling.
 *
 * Wires the Claude Design "bespoke-shop-page.css" drop-in into the
 * Astra theme so the WooCommerce shop page + every category / tag
 * archive renders in the dark, mint-accented look that matches the
 * customiser, PDP and homepage.
 *
 * What this file does (high level for the non-dev BEspoke designer):
 *   1) Enqueues assets/bespoke-shop-page.css ONLY on shop / category /
 *      product-tag archive pages (it can't leak into the cart, the
 *      blog, the PDP, etc.).
 *   2) Adds a `bespoke-shop-styled` body class so the CSS has a
 *      stable specificity hook — pages without the styling get NO
 *      visual changes.
 *   3) Ensures the WC Shop page title + intro paragraph actually
 *      render inside Astra's `woocommerce-products-header` block, so
 *      the page-title set in WP Admin ▸ Pages comes through
 *      ("Build your club's kit bag." etc.).
 *   4) Provides a fallback intro paragraph the FIRST TIME the page is
 *      viewed (in case the user hasn't typed one yet), so the design
 *      isn't visibly half-finished while they're editing.
 *
 * To change the headline + intro on the shop page:
 *   WP Admin ▸ Pages ▸ open the page set as Shop in
 *   WC ▸ Settings ▸ Products ▸ Shop page. Edit the Title +
 *   add an intro paragraph in the body. Save. Done.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-shop.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Enqueue the drop-in stylesheet on shop + category + tag archives.
 *    Hook fires once per page load; the conditional checks WC archive
 *    helpers and short-circuits everywhere else so the CSS never
 *    pollutes other pages.
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'bespoke_shop_enqueue_styles' );
function bespoke_shop_enqueue_styles() {
	if ( ! bespoke_shop_is_archive() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}
	$css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-shop-page.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style(
		'bespoke-shop-page',
		BESPOKE_PLUGIN_URL . 'assets/bespoke-shop-page.css',
		[],
		$version
	);
}

/* -------------------------------------------------------------------------
 * 2. Body class — gives the CSS a stable specificity hook AND lets the
 *    designer disable styling on a per-page basis later (drop the body
 *    class via a filter).
 * --------------------------------------------------------------------- */
add_filter( 'body_class', 'bespoke_shop_body_class' );
function bespoke_shop_body_class( $classes ) {
	if ( bespoke_shop_is_archive() ) {
		$classes[] = 'bespoke-shop-styled';
	}
	return $classes;
}

/* -------------------------------------------------------------------------
 * 3. Make sure the Shop page title + intro actually render.
 *    Astra strips the shop page's body content by default; we
 *    re-inject it inside .ast-archive-description so the designer can
 *    write the intro paragraph in WP Admin ▸ Pages and have it appear
 *    under the headline. Category archives keep their stock behaviour
 *    (term name + term description) untouched.
 * --------------------------------------------------------------------- */
add_action( 'init', 'bespoke_shop_take_over_intro_rendering' );
function bespoke_shop_take_over_intro_rendering() {
	// WC's stock callback outputs `<div class="page-description">…</div>`
	// — which isn't the markup the design CSS keys off
	// (`.ast-archive-description`). Replace it with our own callback
	// that uses the right wrapper AND falls back to a default tagline
	// when the Shop page has no content yet.
	if ( function_exists( 'woocommerce_product_archive_description' ) ) {
		remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
	}
	add_action( 'woocommerce_archive_description', 'bespoke_shop_render_intro', 10 );
}

function bespoke_shop_render_intro() {
	// Only on the main shop page, not category / tag archives.
	if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
		return;
	}
	// Avoid noise on paginated views — only render on page 1, same
	// rule WC's own callback used.
	$paged = absint( get_query_var( 'paged' ) );
	if ( $paged > 1 ) {
		return;
	}
	$shop_page_id = wc_get_page_id( 'shop' );
	if ( $shop_page_id <= 0 ) {
		return;
	}
	$shop_page = get_post( $shop_page_id );
	if ( ! $shop_page ) {
		return;
	}

	$content = trim( (string) $shop_page->post_content );

	if ( $content !== '' ) {
		// Designer has typed an intro into the Shop page body in WP
		// Admin — render it (blocks, shortcodes etc all expand).
		$rendered = wc_format_content( wp_kses_post( $content ) );
	} else {
		// No content yet — drop in the design's default tagline so
		// the page doesn't look half-finished while they're editing.
		$fallback = __(
			'Eight personalised products, sublimated edge to edge with your crest. No minimum order, made in Hampshire, shipped in five days.',
			'bespoke-customiser'
		);
		$fallback = apply_filters( 'bespoke_shop_intro_fallback', $fallback );
		$rendered = '<p>' . esc_html( $fallback ) . '</p>';
	}

	// Both wrappers in case the active theme doesn't wrap the action
	// output in .ast-archive-description (the CSS relies on that
	// outer class for the dark banner). A double-wrap if Astra also
	// wraps is harmless — the descendant selectors still match.
	echo '<div class="ast-archive-description bespoke-shop-intro-wrap">'
		. '<div class="ast-archive-description-text bespoke-shop-intro">'
		. $rendered
		. '</div>'
		. '</div>';
}

/* -------------------------------------------------------------------------
 * 4. Inject the mint eyebrow as a real element (optional belt-and-
 *    braces — the CSS already injects it via ::before, but giving it
 *    real markup means screen readers + SEO see the text too).
 *    Filtered through `bespoke_shop_eyebrow_text` so the designer can
 *    later override it from a Code Snippet without editing the
 *    plugin.
 * --------------------------------------------------------------------- */
add_action( 'woocommerce_before_main_content', 'bespoke_shop_render_eyebrow', 5 );
function bespoke_shop_render_eyebrow() {
	if ( ! bespoke_shop_is_archive() ) {
		return;
	}
	$eyebrow = apply_filters(
		'bespoke_shop_eyebrow_text',
		__( 'Shop · Everything personalised', 'bespoke-customiser' )
	);
	if ( $eyebrow === '' ) {
		return;
	}
	// The CSS keys the ::before pseudo-element off the H1; this real
	// <p> stays hidden visually (the CSS still draws the eyebrow) but
	// is read aloud by screen readers and indexed by Google.
	echo '<p class="bs-eyebrow screen-reader-text" aria-hidden="false">' . esc_html( $eyebrow ) . '</p>';
}

/* -------------------------------------------------------------------------
 * 5. Force "From £x.xx" prefix on variable products in the shop loop.
 *    WC defaults to "£x – £y" for variable products; the design uses
 *    "From £x" instead. This filter swaps the price HTML for variable
 *    products only, matching the markup contract:
 *      <span class="price">
 *        <span class="from">From </span>
 *        <span class="woocommerce-Price-amount amount">£20.00</span>
 *      </span>
 * --------------------------------------------------------------------- */
add_filter( 'woocommerce_variable_price_html', 'bespoke_shop_variable_from_price', 10, 2 );
function bespoke_shop_variable_from_price( $price_html, $product ) {
	if ( ! bespoke_shop_is_archive() ) {
		return $price_html;
	}
	$min_price = $product->get_variation_price( 'min', true );
	if ( $min_price === '' ) {
		return $price_html;
	}
	return '<span class="from">' . esc_html__( 'From ', 'bespoke-customiser' ) . '</span>'
		. wc_price( $min_price );
}

/* -------------------------------------------------------------------------
 * 6. Swap the WC add-to-cart button label on shop tiles to
 *    "Customise →" (matches the markup contract). Direct simple
 *    products + variable products both route through
 *    woocommerce_product_add_to_cart_text.
 * --------------------------------------------------------------------- */
add_filter( 'woocommerce_product_add_to_cart_text', 'bespoke_shop_cart_button_text', 10, 2 );
function bespoke_shop_cart_button_text( $text, $product ) {
	if ( ! bespoke_shop_is_archive() ) {
		return $text;
	}
	// Only swap on products our plugin knows about — leaves any
	// non-customiser products with their stock "Add to cart" label.
	$type = get_post_meta( $product->get_id(), '_bespoke_product_type', true );
	if ( ! $type ) {
		return $text;
	}
	return __( 'Customise →', 'bespoke-customiser' );
}

/* -------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/**
 * Single source of truth for "are we on a shop archive page?".
 * Returns true on:
 *   - the main shop page
 *   - any product category archive
 *   - any product tag archive
 * Returns false everywhere else (PDP, cart, checkout, blog, search,
 * etc.).
 */
function bespoke_shop_is_archive() {
	if ( is_admin() ) {
		return false;
	}
	if ( ! function_exists( 'is_shop' ) ) {
		// WooCommerce not loaded — bail safely.
		return false;
	}
	return is_shop() || is_product_category() || is_product_tag();
}
