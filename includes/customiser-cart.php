<?php
/**
 * BEspoke Customiser — Cart page styling.
 *
 * Wires Claude Design's "bespoke-cart-page.css" drop-in into the
 * WooCommerce cart page so the cart picks up the dark, mint-
 * accented look that matches the shop, PDP and blog.
 *
 * What this file does (high-level for the non-dev designer):
 *   1) Enqueues assets/bespoke-cart-page.css ONLY on the /cart/
 *      page. It can't leak into the shop, the checkout, or
 *      anywhere else.
 *   2) Adds a `bespoke-cart-styled` body class for CSS
 *      specificity.
 *   3) Adds a mint "Re-customise →" link next to each customiser
 *      cart item, so the customer can jump back to the PDP to
 *      tweak their design before checkout. The link uses the
 *      design's `.bs-customise-link` class so it picks up the
 *      mint mono-caps treatment automatically.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-cart.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Enqueue the drop-in stylesheet on the cart page only.
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'bespoke_cart_enqueue_styles' );
function bespoke_cart_enqueue_styles() {
	if ( ! bespoke_cart_is_cart_page() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}
	$css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-cart-page.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style(
		'bespoke-cart-page',
		BESPOKE_PLUGIN_URL . 'assets/bespoke-cart-page.css',
		[],
		$version
	);
}

/* -------------------------------------------------------------------------
 * 2. Body class — stable specificity hook.
 * --------------------------------------------------------------------- */
add_filter( 'body_class', 'bespoke_cart_body_class' );
function bespoke_cart_body_class( $classes ) {
	if ( bespoke_cart_is_cart_page() ) {
		$classes[] = 'bespoke-cart-styled';
	}
	return $classes;
}

/* -------------------------------------------------------------------------
 * 3. Append a mint "Re-customise →" link under the product name on
 *    every customiser cart item, so the customer can hop straight
 *    back to the PDP to tweak the design. The link is rendered with
 *    the design's `.bs-customise-link` class so the CSS handles the
 *    visual treatment.
 *
 *    Hooked to `woocommerce_after_cart_item_name` which fires inside
 *    the .product-name cell, right after WC outputs the variation
 *    attribute list. Runs at priority 100 so it lands after any
 *    other plugins that hook the same action.
 * --------------------------------------------------------------------- */
add_action( 'woocommerce_after_cart_item_name', 'bespoke_cart_render_customise_link', 100, 2 );
function bespoke_cart_render_customise_link( $cart_item, $cart_item_key ) {
	if ( ! is_array( $cart_item ) ) {
		return;
	}
	// Only show on customiser cart items — the line item must carry
	// our `_bespoke_customisation` meta. Skip stock products.
	if ( empty( $cart_item['bespoke_customisation'] ) && empty( $cart_item['_bespoke_customisation'] ) ) {
		// Some setups stash the blob under different keys depending
		// on cart-item-data version. If neither is present, also try
		// the product meta as a last resort before giving up.
		$product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
		if ( $product_id <= 0 || ! get_post_meta( $product_id, '_bespoke_product_type', true ) ) {
			return;
		}
	}

	$product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
	if ( $product_id <= 0 ) {
		return;
	}

	$url = get_permalink( $product_id );
	if ( ! $url ) {
		return;
	}

	$label = apply_filters( 'bespoke_cart_customise_link_label', __( 'Re-customise →', 'bespoke-customiser' ) );

	echo '<a class="bs-customise-link" href="' . esc_url( $url ) . '">'
		. esc_html( $label )
		. '</a>';
}

/* -------------------------------------------------------------------------
 * Elementor WC Cart widget compatibility layer.
 *
 * The /cart/ page renders the cart through Elementor Pro's
 * `elementor-widget-woocommerce-cart` widget, which wraps WC's
 * standard cart markup in its own .elementor-widget-container.
 * The Claude Design CSS targets WC's classes directly (table.cart,
 * .cart_totals etc) which still works on descendant selectors, but
 * Elementor's own widget styles (padding, backgrounds) sometimes
 * win on specificity.
 *
 * This inline layer (a) flattens Elementor's widget wrapper so it
 * doesn't double-pad the cart, and (b) styles the empty-cart
 * message specifically (since most testing happens with an empty
 * cart, this is what the designer sees first).
 * --------------------------------------------------------------- */
add_action( 'wp_head', 'bespoke_cart_print_elementor_compat_css', 99 );
function bespoke_cart_print_elementor_compat_css() {
	if ( ! bespoke_cart_is_cart_page() ) {
		return;
	}
	?>
	<style id="bespoke-cart-elementor-compat">
	/* Flatten Elementor's cart-widget wrapping so we don't get
	   double containers / double padding. */
	body.bespoke-cart-styled .elementor-widget-woocommerce-cart {
		background: transparent !important;
	}
	body.bespoke-cart-styled .elementor-widget-woocommerce-cart > .elementor-widget-container {
		background: transparent !important;
		padding: 0 !important;
	}

	/* Empty-cart state — what shows when the basket is empty. The
	   Claude Design CSS does style .cart-empty, but the standalone
	   wrapper around it (.wc-empty-cart-message) needs centring
	   and breathing room. */
	body.bespoke-cart-styled .wc-empty-cart-message {
		max-width: 720px !important;
		margin: 48px auto !important;
		text-align: center !important;
	}
	body.bespoke-cart-styled .cart-empty.woocommerce-info {
		background: #141417 !important;
		color: #fff !important;
		border: 1px solid rgba(255,255,255,0.10) !important;
		border-left: 4px solid #7FECB8 !important;
		border-radius: 12px !important;
		padding: 28px 32px !important;
		font-family: 'Inter', system-ui, sans-serif !important;
		font-size: 16px !important;
		text-align: left !important;
	}
	body.bespoke-cart-styled .return-to-shop {
		text-align: center !important;
		margin-top: 24px !important;
	}
	body.bespoke-cart-styled .return-to-shop .button {
		background: #7FECB8 !important;
		color: #0E0E10 !important;
		border-radius: 999px !important;
		padding: 14px 28px !important;
		font-family: 'Inter', system-ui, sans-serif !important;
		font-size: 13px !important;
		font-weight: 700 !important;
		letter-spacing: 0.1em !important;
		text-transform: uppercase !important;
		text-decoration: none !important;
		display: inline-block !important;
	}
	body.bespoke-cart-styled .return-to-shop .button:hover {
		filter: brightness(1.08) !important;
	}
	</style>
	<?php
}

/* -------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/**
 * Is this the WooCommerce cart page?
 * Returns false everywhere else.
 */
function bespoke_cart_is_cart_page() {
	if ( is_admin() ) {
		return false;
	}
	if ( ! function_exists( 'is_cart' ) ) {
		return false;
	}
	return is_cart();
}
