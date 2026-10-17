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

	// A line added since the design started travelling with it can be
	// reopened: Amend replaces the line, Duplicate adds another. Older lines
	// keep the plain link to the product.
	if ( ! empty( $cart_item['bespoke_state'] ) && is_array( $cart_item['bespoke_state'] ) ) {
		$amend = add_query_arg( 'bespoke_edit', $cart_item_key, $url );
		$copy  = add_query_arg( 'bespoke_copy', $cart_item_key, $url );
		echo '<span class="bs-customise-links">'
			. '<a class="bs-customise-link" href="' . esc_url( $amend ) . '">' . esc_html__( 'Amend →', 'bespoke-customiser' ) . '</a>'
			. '<a class="bs-customise-link" href="' . esc_url( $copy ) . '">' . esc_html__( 'Duplicate →', 'bespoke-customiser' ) . '</a>'
			. '</span>';
		return;
	}

	$label = apply_filters( 'bespoke_cart_customise_link_label', __( 'Re-customise →', 'bespoke-customiser' ) );

	echo '<a class="bs-customise-link" href="' . esc_url( $url ) . '">'
		. esc_html( $label )
		. '</a>';
}

/* -------------------------------------------------------------------------
 * 3b. The design picture opens large.
 *
 *    The basket's thumbnails are small. Clicking one opens the design
 *    preview at full size in a box over the basket — closed with the ×,
 *    a click outside it, or Esc — rather than following the thumbnail's
 *    link away to the product page.
 * --------------------------------------------------------------------- */
add_action( 'wp_footer', 'bespoke_cart_print_lightbox' );
function bespoke_cart_print_lightbox() {
	if ( ! bespoke_cart_is_cart_page() ) {
		return;
	}
	?>
	<style id="bespoke-cart-lightbox">
	.bcp-zoom{position:relative;display:inline-block;cursor:zoom-in;}
	.bcp-zoom::after{content:"";position:absolute;right:4px;bottom:4px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.65) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235DCAA5' stroke-width='2.4'%3E%3Ccircle cx='10.5' cy='10.5' r='6'/%3E%3Cline x1='15' y1='15' x2='20' y2='20'/%3E%3Cline x1='10.5' y1='8' x2='10.5' y2='13'/%3E%3Cline x1='8' y1='10.5' x2='13' y2='10.5'/%3E%3C/svg%3E") center/14px no-repeat;pointer-events:none;}
	.bs-customise-links{display:inline-flex;flex-wrap:wrap;gap:6px 14px;}
	.bcp-lightbox{position:fixed;inset:0;z-index:2147483646;background:rgba(0,0,0,.82);display:flex;align-items:center;justify-content:center;gap:16px;padding:24px;box-sizing:border-box;cursor:zoom-out;}
	.bcp-lightbox figure{margin:0;text-align:center;}
	.bcp-lightbox img{display:block;max-width:min(88vw,760px);max-height:82vh;width:auto;height:auto;background:#fff;border-radius:12px;box-shadow:0 18px 60px rgba(0,0,0,.6);cursor:default;}
	.bcp-lightbox figcaption{margin-top:8px;color:#ddd;font-size:13px;letter-spacing:.06em;text-transform:uppercase;}
	.bcp-lightbox-x{position:fixed;top:14px;right:14px;width:44px;height:44px;border-radius:50%;border:none;background:#5DCAA5;color:#08312A;font-size:26px;line-height:44px;font-weight:700;cursor:pointer;}
	@media (max-width:640px){.bcp-lightbox{flex-direction:column;}.bcp-lightbox img{max-width:92vw;max-height:40vh;}}
	</style>
	<script id="bespoke-cart-lightbox-js">
	(function(){
		var open = null;
		function close(){
			if (!open) return;
			open.remove();
			open = null;
			document.documentElement.style.overflow = '';
		}
		document.addEventListener('click', function(e){
			var z = e.target.closest ? e.target.closest('.bcp-zoom') : null;
			if (!z) return;
			// The thumbnail sits inside a link to the product; open the
			// picture here instead of leaving the basket.
			e.preventDefault();
			e.stopPropagation();
			close();
			// A double-sided armband shows both faces side by side.
			var cell  = z.parentNode && z.parentNode.querySelectorAll ? z.parentNode : null;
			var shots = cell ? cell.querySelectorAll('.bcp-zoom') : [z];
			if (!shots.length) shots = [z];
			var box = document.createElement('div');
			box.className = 'bcp-lightbox';
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-label', 'Your design');
			Array.prototype.forEach.call(shots, function(s){
				var fig = document.createElement('figure');
				var img = document.createElement('img');
				img.src = s.getAttribute('data-full');
				img.alt = 'Your design';
				fig.appendChild(img);
				var label = s.getAttribute('data-label');
				if (label) {
					var cap = document.createElement('figcaption');
					cap.textContent = label;
					fig.appendChild(cap);
				}
				fig.addEventListener('click', function(ev){ ev.stopPropagation(); });
				box.appendChild(fig);
			});
			var x = document.createElement('button');
			x.type = 'button';
			x.className = 'bcp-lightbox-x';
			x.setAttribute('aria-label', 'Close');
			x.textContent = '×';
			x.addEventListener('click', function(ev){ ev.stopPropagation(); close(); });
			box.appendChild(x);
			box.addEventListener('click', close);
			document.body.appendChild(box);
			document.documentElement.style.overflow = 'hidden';
			open = box;
			x.focus();
		}, true);
		document.addEventListener('keydown', function(e){
			if (e.key === 'Escape') close();
		});
	})();
	</script>
	<?php
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
