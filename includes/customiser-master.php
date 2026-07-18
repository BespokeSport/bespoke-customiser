<?php
/**
 * BEspoke Customiser — Master / global page theme.
 *
 * Wires the master stylesheet (assets/bespoke-master.css) into every
 * front-end page so any NEW Page the designer creates picks up the
 * dark canvas, brand fonts, mint links and on-brand selection /
 * scrollbar / submit-button styling automatically — no per-page setup
 * needed.
 *
 * What this file does:
 *   1) Enqueues assets/bespoke-master.css on every front-end page.
 *   2) Adds a `bespoke-themed` body class so the CSS has a stable
 *      hook (everything in the stylesheet is scoped to that class).
 *   3) Respects two opt-outs:
 *        a) per-page: add `bs-light` to the page's body classes via
 *           Astra's "Body Class" page setting, or the Yoast / SEO
 *           plugin body class field. The CSS uses `:not(.bs-light)`
 *           so the dark theme reverts to whatever the theme would
 *           render normally.
 *        b) site-wide / programmatic: filter `bespoke_apply_master_theme`
 *           returning false. Useful in Code Snippets if you want to
 *           toggle the master theme off temporarily.
 *
 * Why this file is separate from customiser-global-fonts.php:
 *   The fonts loader runs at wp_enqueue_scripts priority 1 (race
 *   to top of <head> for fastest font swap). The master theme
 *   should load AFTER any theme defaults so its rules win on the
 *   normal source-order tiebreak — priority 50 keeps it out of the
 *   fonts loader's lane.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-master.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Enqueue the master stylesheet on every front-end page.
 *    Priority 50 — late enough to win over the theme's own canvas
 *    styles on a source-order tiebreak, early enough to lose to
 *    page-specific CSS (shop / blog / cart / contact / PDP) which
 *    layers on top.
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'bespoke_master_enqueue', 50 );
function bespoke_master_enqueue() {
	if ( is_admin() ) {
		return;
	}
	if ( ! bespoke_master_should_apply() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}
	$css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-master.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style(
		'bespoke-master',
		BESPOKE_PLUGIN_URL . 'assets/bespoke-master.css',
		[],
		$version
	);
}

/* -------------------------------------------------------------------------
 * 1b. Keep SiteGround Optimizer's hands OFF the plugin stylesheets.
 *
 * SG's "Minify CSS" writes a `.min.css` SIBLING next to each stylesheet
 * and silently rewrites the page to load THAT instead of the file we
 * enqueued. It has been caught serving a STALE .min.css long after the
 * source file was re-uploaded (18 Jul 2026: the cart de-squash CSS was
 * live on the server for hours while every visitor kept getting the old
 * minified copy — "I uploaded it but nothing changed"). Purge SG Cache
 * does NOT regenerate these siblings.
 *
 * Our stylesheets are a few KB each and versioned by filemtime — the
 * bytes saved by minifying are nothing next to the cost of invisible
 * deploys. Exclude every plugin style handle from minify AND combine so
 * the browser always loads the exact file the designer uploaded.
 * --------------------------------------------------------------------- */
function bespoke_sgo_exclude_plugin_styles( $exclude ) {
	$handles = [
		'bespoke-master',
		'bespoke-cart-page',
		'bespoke-shop-page',
		'bespoke-blog-page',
		'bespoke-contact-page',
		'bespoke-homepage',
		'bespoke-product-page',
		'bespoke-customiser',
		'bespoke-global',
		'bespoke-brand-fonts',
	];
	foreach ( $handles as $h ) {
		$exclude[] = $h;
	}
	return $exclude;
}
add_filter( 'sgo_css_minify_exclude',  'bespoke_sgo_exclude_plugin_styles' );
add_filter( 'sgo_css_combine_exclude', 'bespoke_sgo_exclude_plugin_styles' );

/* -------------------------------------------------------------------------
 * 2. Body class — every selector in the master stylesheet is scoped to
 *    `body.bespoke-themed`, so without this class NOTHING in the
 *    master stylesheet fires. Anchoring the whole stylesheet on a
 *    PHP-controlled class gives us a single switch to flip if we
 *    ever need to disable the master theme everywhere.
 * --------------------------------------------------------------------- */
add_filter( 'body_class', 'bespoke_master_body_class' );
function bespoke_master_body_class( $classes ) {
	if ( is_admin() ) {
		return $classes;
	}
	if ( ! bespoke_master_should_apply() ) {
		return $classes;
	}
	$classes[] = 'bespoke-themed';
	return $classes;
}

/* -------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/**
 * Should the master theme apply on the current request?
 *
 * Returns:
 *   true   on every front-end page by default
 *   false  if the `bespoke_apply_master_theme` filter says no
 *
 * Note: per-page opt-out via the `bs-light` body class is handled
 * directly in the CSS via `:not(.bs-light)` — no PHP needed, since
 * Astra's per-page body-class field already injects whatever the
 * designer types into the body tag.
 */
function bespoke_master_should_apply() {
	return (bool) apply_filters( 'bespoke_apply_master_theme', true );
}


/* =========================================================================
   HEADER CART BUTTON
   The Elementor header template has no cart. Inject a cart icon + live
   count into the header (top-right, beside the search icon) on every
   page, desktop + mobile. We inject via a wp_footer script rather than
   editing the Elementor template so it stays in version control and the
   count is server-rendered fresh on every page load.
   ========================================================================= */

/**
 * Current cart item count (0 when WooCommerce / the cart isn't ready).
 */
function bespoke_header_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	return (int) WC()->cart->get_cart_contents_count();
}

/**
 * The cart button markup. Kept in one place so the wp_footer injector and
 * the WooCommerce fragment refresh below emit an identical element.
 */
function bespoke_header_cart_button_html() {
	$count = bespoke_header_cart_count();
	$url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

	$svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
		. '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>'
		. '<path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';

	// Count bubble — hidden (via CSS) when empty.
	$badge = '<span class="bcp-cart-count" data-count="' . esc_attr( $count ) . '"'
		. ( $count > 0 ? '' : ' hidden' ) . '>' . esc_html( $count ) . '</span>';

	return '<a class="bcp-header-cart" href="' . esc_url( $url ) . '" aria-label="View your basket">'
		. $svg . $badge . '</a>';
}

/**
 * Inject the button into the Elementor header on the front end.
 * Placed in the column that holds the search icon (top-right, shown on
 * desktop AND mobile) so it lives in the same spot on both.
 */
add_action( 'wp_footer', 'bespoke_header_cart_inject' );
function bespoke_header_cart_inject() {
	if ( is_admin() || ! bespoke_master_should_apply() ) {
		return;
	}
	$html = wp_json_encode( bespoke_header_cart_button_html() );
	?>
	<script>
	(function () {
		function inject() {
			var header = document.querySelector('header.elementor-location-header');
			if (!header || header.querySelector('.bcp-header-cart')) return;
			var wrap = document.createElement('span');
			wrap.innerHTML = <?php echo $html; ?>;
			var cart = wrap.firstElementChild;
			// Prefer the column that holds the search icon (top-right on both
			// desktop + mobile); fall back to the header's flex container.
			var search = header.querySelector('[class*="elementor-widget-search"], [class*="search-form"], [class*="ast-search"], [class*="icon-search"]');
			var col = search ? (search.closest('.elementor-column') || search.parentElement) : null;
			if (col) { col.insertBefore(cart, col.firstChild); }
			else {
				var container = header.querySelector('.elementor-container') || header;
				container.appendChild(cart);
			}
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', inject);
		} else { inject(); }
	})();
	</script>
	<?php
}

/**
 * Keep the header count in sync when items are added through WooCommerce's
 * own AJAX (shop loop "Add to cart" etc.). Our customiser add-to-cart
 * redirects to /cart/, so that path refreshes the count via a normal page
 * load; this covers the AJAX path too.
 */
add_filter( 'woocommerce_add_to_cart_fragments', 'bespoke_header_cart_fragment' );
function bespoke_header_cart_fragment( $fragments ) {
	$count = bespoke_header_cart_count();
	$fragments['.bcp-header-cart .bcp-cart-count'] =
		'<span class="bcp-cart-count" data-count="' . esc_attr( $count ) . '"'
		. ( $count > 0 ? '' : ' hidden' ) . '>' . esc_html( $count ) . '</span>';
	return $fragments;
}
