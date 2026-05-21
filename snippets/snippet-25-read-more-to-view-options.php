<?php
/**
 * Snippet ID:    25
 * Name:          BEspoke - Change Read More to View Options
 * Status:        INACTIVE
 * Last modified: 2026-05-15 06:31:06
 * On WooCommerce product loops, replaces "Read more" link text with
 * "View Options".
 *
 * NOTE: original snippet body ends with a stray "})" that looks like a
 * syntax error. Preserved here as-is for fidelity to live state.
 */

add_filter( 'woocommerce_loop_add_to_cart_link', function( $link, $product ) {
	    return preg_replace( '/(?i)>\s*Read more\s*</', '>View Options<', $link );
}, 10, 2 );
})
