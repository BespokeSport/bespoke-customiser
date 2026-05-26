<?php
/**
 * BEspoke Sport – WooCommerce Integration
 *
 * Handles everything that happens AFTER the customer clicks "Add to cart":
 *
 *   1. Cart & checkout display  – shows a readable design summary under the product name
 *   2. Order persistence        – saves the customisation JSON to the order when checkout completes
 *   3. Admin order view         – renders a full, production-ready spec inside the WP admin order page
 *
 * Adding a new product type (armbands, bottles, grip socks…) only requires:
 *   - A new 'bespoke_render_cart_{type}()' function  (for cart display)
 *   - A new 'bespoke_render_admin_{type}()' function (for admin display)
 *   The hooks themselves never need to change.
 *
 * File location: /wp-content/plugins/bespoke-sport/includes/customiser-woocommerce.php
 * This file is included by the main plugin bootstrap (bespoke-sport.php).
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
   1. PERSIST CUSTOMISATION DATA TO THE ORDER
   Fires when the customer completes checkout. Copies the customisation
   from the WooCommerce session into permanent order item meta.
   ========================================================================= */

add_action(
    'woocommerce_checkout_create_order_line_item',
    'bespoke_save_order_item_meta',
    10,
    4
);

/**
 * @param WC_Order_Item_Product $item         The order line item being created.
 * @param string                $cart_item_key The cart item key.
 * @param array                 $values        The cart item data (includes our customisation).
 * @param WC_Order              $order         The order being created.
 */
function bespoke_save_order_item_meta( $item, $cart_item_key, $values, $order ) {

    if ( empty( $values['bespoke_customisation'] ) ) {
        return;
    }

    // Store as JSON under a single private meta key (prefixed with _ so WC
    // doesn't auto-display it on the front-end order confirmation page).
    $item->update_meta_data(
        '_bespoke_customisation',
        wp_json_encode( $values['bespoke_customisation'] )
    );
}


/* =========================================================================
   2. CART THUMBNAIL
   Replaces the default product image in the cart with the customer's
   actual design so they can see exactly what they've ordered.
   ========================================================================= */

add_filter( 'woocommerce_cart_item_thumbnail', 'bespoke_cart_item_thumbnail', 10, 3 );

/**
 * @param  string $thumbnail   The default product thumbnail HTML.
 * @param  array  $cart_item   The full cart item array.
 * @param  string $cart_item_key
 * @return string
 */
function bespoke_cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {

    $preview_url = $cart_item['bespoke_customisation']['data']['preview_url'] ?? '';

    if ( ! $preview_url ) {
        return $thumbnail;
    }

    return sprintf(
        '<img src="%s" alt="Your custom design" style="width:100px;height:100px;object-fit:contain;display:block;" />',
        esc_url( $preview_url )
    );
}


/* =========================================================================
   3. CART & CHECKOUT DISPLAY
   Shows a concise design summary below the product name in the cart,
   checkout, and order confirmation emails.
   ========================================================================= */

add_filter( 'woocommerce_get_item_data', 'bespoke_display_cart_item_data', 10, 2 );

/**
 * @param  array $item_data  Existing display rows for this cart item.
 * @param  array $cart_item  The full cart item array.
 * @return array             Updated display rows.
 */
function bespoke_display_cart_item_data( $item_data, $cart_item ) {

    if ( empty( $cart_item['bespoke_customisation'] ) ) {
        return $item_data;
    }

    $customisation = $cart_item['bespoke_customisation'];
    $type          = $customisation['type'] ?? '';

    // Route to the correct renderer for this product type
    $renderer = 'bespoke_render_cart_' . $type;

    if ( is_callable( $renderer ) ) {
        $item_data = call_user_func( $renderer, $item_data, $customisation['data'] );
    }

    return $item_data;
}

/**
 * Cart display renderer for shin pads.
 * Returns a compact summary: size, design, pad text, badge status.
 *
 * @param  array $item_data  Rows to append to.
 * @param  array $d          The 'data' portion of the customisation blob.
 * @return array
 */
function bespoke_render_cart_shinpads( $item_data, $d ) {

    // Size
    if ( ! empty( $d['size'] ) ) {
        $item_data[] = [
            'name'  => 'Size',
            'value' => esc_html( $d['size'] ),
        ];
    }

    // Design
    if ( ! empty( $d['design'] ) ) {
        $item_data[] = [
            'name'  => 'Design',
            'value' => esc_html( $d['design'] ),
        ];
    }

    // Left pad – only show if name or number was entered
    $left_parts = array_filter( [
        $d['left']['name']   ?? '',
        $d['left']['number'] ?? '',
    ] );
    if ( $left_parts ) {
        $item_data[] = [
            'name'  => 'Left pad',
            'value' => esc_html( implode( ' / ', $left_parts ) ),
        ];
    }

    // Right pad
    $right_parts = array_filter( [
        $d['right']['name']   ?? '',
        $d['right']['number'] ?? '',
    ] );
    if ( $right_parts ) {
        $item_data[] = [
            'name'  => 'Right pad',
            'value' => esc_html( implode( ' / ', $right_parts ) ),
        ];
    }

    // Badge
    $item_data[] = [
        'name'  => 'Club badge',
        'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added',
    ];

    return $item_data;
}


/* =========================================================================
   3. ADMIN ORDER VIEW
   Renders a full production spec inside the WP admin order page.
   Formatted so the production team can read every detail they need
   without opening any other system.
   ========================================================================= */

add_action( 'woocommerce_after_order_itemmeta', 'bespoke_display_admin_order_meta', 10, 3 );

/**
 * @param int                   $item_id  The order item ID.
 * @param WC_Order_Item_Product $item     The order line item object.
 * @param WC_Product|null       $product  The product object.
 */
function bespoke_display_admin_order_meta( $item_id, $item, $product ) {

    $raw = $item->get_meta( '_bespoke_customisation' );

    if ( ! $raw ) {
        return;
    }

    $customisation = json_decode( $raw, true );

    if ( ! is_array( $customisation ) ) {
        return;
    }

    $type     = $customisation['type'] ?? '';
    $renderer = 'bespoke_render_admin_' . $type;

    if ( is_callable( $renderer ) ) {
        call_user_func( $renderer, $customisation['data'], $item );
    }
}

/* =========================================================================
   HIDE INTERNAL META KEYS
   _bespoke_customisation is a private JSON blob — hide it from the
   cart, checkout, emails, and admin order item meta panels.
   ========================================================================= */
add_filter( 'woocommerce_hidden_order_itemmeta', function( $hidden ) {
    $hidden[] = '_bespoke_customisation';
    return $hidden;
} );

/**
 * Admin order display renderer for shin pads.
 * Outputs a complete production specification card.
 *
 * @param array                 $d     The 'data' portion of the customisation blob.
 * @param WC_Order_Item_Product $item  The order line item (needed for unique IDs).
 */
function bespoke_render_admin_shinpads( $d, $item ) {

    // Helper: render a colour swatch + hex value
    $colour_swatch = function( $hex ) {
        if ( ! $hex ) return '—';
        $safe = esc_attr( $hex );
        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:6px;">'
            . '<span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:%1$s;border:1px solid rgba(0,0,0,0.15);flex-shrink:0;"></span>'
            . '<code style="font-size:12px;">%1$s</code>'
            . '</span>',
            $safe
        );
    };

    // Helper: row in the spec table
    $row = function( $label, $value ) {
        if ( $value === '' || $value === null ) $value = '—';
        return '<tr>'
            . '<td style="padding:5px 10px 5px 0;color:#666;white-space:nowrap;font-size:12px;vertical-align:top;">' . esc_html( $label ) . '</td>'
            . '<td style="padding:5px 0;font-size:12px;font-weight:600;color:#1a1a1a;">' . $value . '</td>'
            . '</tr>';
    };

    // Helper: section heading
    $heading = function( $label ) {
        return '<tr><td colspan="2" style="padding:10px 0 4px;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.6px;border-top:1px solid #eee;">'
            . esc_html( $label ) . '</td></tr>';
    };

    // Helper: render an entire section as a sub-table (label + value rows)
    $section = function( $heading_label, $rows_html ) {
        return '<div style="margin-bottom:14px;">'
            . '<div style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.6px;padding-bottom:4px;margin-bottom:6px;border-bottom:1px solid #eee;">'
            . esc_html( $heading_label )
            . '</div>'
            . '<table style="width:100%;border-collapse:collapse;">'
            . $rows_html
            . '</table>'
            . '</div>';
    };

    ob_start();
    ?>
    <div style="
        margin-top: 12px;
        padding: 14px 16px;
        background: #f9f8f6;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    ">
        <div style="font-size:11px;font-weight:700;color:#2E7D32;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;">
            ✦ BEspoke Sport — Shin Pad Specification
        </div>

        <!-- Two-column grid: left = product/pads, right = fonts/colours/badge -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

            <!-- ── LEFT COLUMN ───────────────────────────────────────────── -->
            <div>
                <?php
                echo $section( 'Product',
                    $row( 'Size',   $d['size']   ?? '' ) .
                    $row( 'Design', $d['design'] ?? '' )
                );
                echo $section( 'Left pad',
                    $row( 'Name',   $d['left']['name']   ?? '' ) .
                    $row( 'Number', $d['left']['number'] ?? '' )
                );
                echo $section( 'Right pad',
                    $row( 'Name',   $d['right']['name']   ?? '' ) .
                    $row( 'Number', $d['right']['number'] ?? '' )
                );
                ?>
            </div>

            <!-- ── RIGHT COLUMN ──────────────────────────────────────────── -->
            <div>
                <?php
                echo $section( 'Fonts',
                    $row( 'Name font',   $d['fonts']['name']   ?? '' ) .
                    $row( 'Number font', $d['fonts']['number'] ?? '' )
                );
                echo $section( 'Colours',
                    $row( 'Pad background', $colour_swatch( $d['colours']['background']  ?? '' ) ) .
                    $row( 'Pattern',        $colour_swatch( $d['colours']['pattern']     ?? '' ) ) .
                    $row( 'Name text',      $colour_swatch( $d['colours']['name_text']   ?? '' ) ) .
                    $row( 'Number text',    $colour_swatch( $d['colours']['number_text'] ?? '' ) )
                );

                // Club badge
                if ( ! empty( $d['badge']['url'] ) ) {
                    $badge_html = sprintf(
                        '<a href="%s" target="_blank"><img src="%s" style="max-height:48px;max-width:80px;object-fit:contain;border:1px solid #eee;border-radius:4px;padding:2px;background:#fff;" /></a>',
                        esc_url( $d['badge']['url'] ),
                        esc_url( $d['badge']['url'] )
                    );
                    echo $section( 'Club badge',
                        $row( 'Badge image', $badge_html ) .
                        $row( 'Filename',    esc_html( $d['badge']['filename'] ?? '' ) )
                    );
                } else {
                    echo $section( 'Club badge', $row( 'Badge image', 'Not added' ) );
                }
                ?>
            </div>

        </div>

        <!-- ── FULL-WIDTH ROW: Preview + Notes ───────────────────────────── -->
        <?php if ( ! empty( $d['preview_url'] ) || ! empty( $d['notes'] ) ) : ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:4px;">
                <?php if ( ! empty( $d['preview_url'] ) ) : ?>
                    <div>
                        <?php
                        $preview_html = sprintf(
                            '<a href="%1$s" target="_blank"><img src="%1$s" style="max-height:160px;max-width:100%%;object-fit:contain;border:1px solid #eee;border-radius:4px;padding:4px;background:#fff;display:block;" /></a>',
                            esc_url( $d['preview_url'] )
                        );
                        echo $section( 'Design preview', $row( 'Preview', $preview_html ) );
                        ?>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $d['notes'] ) ) : ?>
                    <div>
                        <?php
                        echo $section( 'Order notes',
                            $row( 'Notes', '<span style="white-space:pre-wrap;">' . esc_html( $d['notes'] ) . '</span>' )
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
    <?php

    echo ob_get_clean();
}


/* =========================================================================
   ADDING NEW PRODUCT TYPES IN FUTURE
   =========================================================================

   To add support for a new product (e.g. armbands), you only need to add
   two functions below. The hooks above will automatically pick them up
   based on the 'type' value in the JSON blob.

   1. Cart display function:
   ─────────────────────────
   function bespoke_render_cart_armbands( $item_data, $d ) {
       // Append whatever summary rows make sense for this product.
       // See bespoke_render_cart_shinpads() above for the pattern.
       $item_data[] = [ 'name' => 'Colour', 'value' => esc_html( $d['colour'] ?? '' ) ];
       return $item_data;
   }

   2. Admin display function:
   ──────────────────────────
   function bespoke_render_admin_armbands( $d ) {
       // Output the HTML spec card for this product type.
       // See bespoke_render_admin_shinpads() above for the pattern.
   }

   The AJAX handler (customiser-ajax.php) requires no changes —
   it stores whatever 'type' the frontend sends.

   ========================================================================= */
