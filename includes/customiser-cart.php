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
