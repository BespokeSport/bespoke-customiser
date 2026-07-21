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
   0. WC PRODUCT-LEVEL CUSTOMISER TYPE FIELD
   ============================================================================
   Adds a "Customiser type" dropdown to the General tab of every WooCommerce
   product editor. Selecting a type wires the product to one of the
   customisers registered in bespoke_get_product_types() (Shin Pads, Grip
   Socks, etc.). Once set:
     - The [bespoke_customiser] shortcode auto-detects product_id +
       product_type when used on the product's page, so no per-product
       shortcode parameters are needed.
     - The default WC "Add to cart" button is removed from the single-
       product page (the customiser provides its own).
   ========================================================================= */

add_action( 'woocommerce_product_options_general_product_data', 'bespoke_wc_add_customiser_type_field' );

function bespoke_wc_add_customiser_type_field() {
    $types = function_exists( 'bespoke_get_product_types' )
        ? bespoke_get_product_types()
        : [];

    $options = [ '' => '— None (not customisable) —' ];
    foreach ( $types as $key => $label ) {
        $options[ $key ] = $label;
    }

    echo '<div class="options_group">';
    woocommerce_wp_select( [
        'id'          => '_bespoke_product_type',
        'label'       => 'BEspoke customiser',
        'description' => 'Pick which customiser this product uses. The [bespoke_customiser] shortcode will pick this up automatically — no per-product arguments needed. Set to None to disable.',
        'desc_tip'    => false,
        'options'     => $options,
    ] );
    echo '</div>';
}

add_action( 'woocommerce_process_product_meta', 'bespoke_wc_save_customiser_type_field' );

function bespoke_wc_save_customiser_type_field( $post_id ) {
    // Defence in depth — WC's own save flow already cap-checks before
    // firing woocommerce_process_product_meta, but explicit checks make
    // the handler safe if it's ever invoked from a non-WC code path.
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( get_post_type( $post_id ) !== 'product' )    return;
    if ( ! isset( $_POST['_bespoke_product_type'] ) ) {
        return;
    }
    $value = sanitize_key( wp_unslash( $_POST['_bespoke_product_type'] ) );

    // Validate against the registered list.
    $valid = function_exists( 'bespoke_get_product_types' )
        ? array_keys( bespoke_get_product_types() )
        : [];
    if ( $value && ! in_array( $value, $valid, true ) ) {
        $value = '';
    }

    if ( $value ) {
        update_post_meta( $post_id, '_bespoke_product_type', $value );
    } else {
        delete_post_meta( $post_id, '_bespoke_product_type' );
    }
}

/**
 * Hide the default WC add-to-cart UI on any single-product page where the
 * product has a customiser type set. The customer adds to cart via the
 * customiser's own button at the end of the Review step, so the duplicate
 * default form would only be confusing (and would let people skip the
 * customiser entirely).
 *
 * Fires on `wp` (after the global $post is set) so we know what product
 * the visitor is looking at before WC starts rendering.
 */
add_action( 'wp', 'bespoke_wc_hide_default_cart_for_customisable_products' );

function bespoke_wc_hide_default_cart_for_customisable_products() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }
    global $post;
    if ( ! $post ) {
        return;
    }
    $type = get_post_meta( $post->ID, '_bespoke_product_type', true );
    if ( ! $type ) {
        return;
    }
    // Remove WooCommerce's default add-to-cart template from the
    // single-product summary. The price + meta blocks stay; only the
    // cart button + quantity + variations form goes.
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
}

/**
 * Loop add-to-cart text — any product with a customiser type uses the
 * verb "Customise" (British spelling) on its shop / related-products
 * card button. Falls back to the default WC text for non-customisable
 * products so we don't change unrelated buttons.
 *
 * High priority (9999) so we run AFTER the Fancy Product Designer
 * plugin's own filter — FPD sets the loop button text to "Customize"
 * (US spelling) and would otherwise win the cascade.
 *
 * The filter fires on every product card render in a loop (shop archive,
 * related products, up-sells, search results) — it returns the text the
 * card link displays.
 */
add_filter( 'woocommerce_product_add_to_cart_text', 'bespoke_wc_customise_loop_text', 9999, 2 );

function bespoke_wc_customise_loop_text( $text, $product ) {
    if ( ! $product instanceof WC_Product ) {
        return $text;
    }
    $type = get_post_meta( $product->get_id(), '_bespoke_product_type', true );
    if ( $type ) {
        return 'Customise';
    }
    return $text;
}

/**
 * Also filter the full loop add-to-cart LINK HTML at high priority,
 * because FPD outputs the entire link itself (with its own classes
 * and text) via woocommerce_loop_add_to_cart_link — replacing only
 * the inner text isn't enough.
 *
 * We do a defensive string-replace on the button's visible label so
 * we don't risk damaging any data-* attributes FPD relies on.
 */
add_filter( 'woocommerce_loop_add_to_cart_link', 'bespoke_wc_customise_loop_link', 9999, 2 );

function bespoke_wc_customise_loop_link( $html, $product ) {
    if ( ! $product instanceof WC_Product ) {
        return $html;
    }
    $type = get_post_meta( $product->get_id(), '_bespoke_product_type', true );
    if ( ! $type ) {
        return $html;
    }
    // Swap any "Customize" / "Add to cart" label inside the link for the
    // British "Customise". Preserves all surrounding tag attributes.
    $html = preg_replace( '/>\s*Customize\s*</i',  '>Customise<', $html );
    $html = preg_replace( '/>\s*Add to cart\s*</i', '>Customise<', $html );
    return $html;
}

/**
 * JS belt-and-braces — if any "Customize" text slipped through (e.g.
 * because FPD rendered the button via a different path, or the page
 * is server-cached pre-filter), rewrite the visible label client-side.
 * Only fires on the front-end.
 */
add_action( 'wp_footer', 'bespoke_wc_customise_text_js', 99 );

function bespoke_wc_customise_text_js() {
    if ( is_admin() ) return;
    ?>
    <script>
    (function(){
      function fix(){
        var sels = '.fpd-catalog-customize, .related .product a.button, .up-sells .product a.button';
        document.querySelectorAll(sels).forEach(function(btn){
          var t = (btn.textContent || '').trim();
          if (/^customize$/i.test(t)) btn.textContent = 'Customise';
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fix);
      else fix();
    })();
    </script>
    <?php
}

/**
 * Enqueue the drop-in product page stylesheet on EVERY single-product
 * page. Used to only fire on products with a `_bespoke_product_type`
 * meta key set — which meant pre-designed products (Wales captain
 * armband, Brampton, etc.) got no styling and reverted to Astra's
 * stock light look on a dark site. They're standard WooCommerce
 * variable products (size + thickness only, no customiser workflow)
 * but still need the BEspoke visual treatment.
 *
 * The CSS targets body.single-product so it only applies on WC product
 * pages — won't bleed into the shop archive, cart, or anywhere else.
 * Customiser-specific rules inside the file (.bespoke-customiser-root
 * etc.) target elements that only exist on customiser products, so
 * applying the full stylesheet to a pre-designed product is harmless
 * for those rules — they simply don't match anything.
 *
 * Per-product opt-out: filter `bespoke_apply_product_page_styles`
 * returning false for any product that should keep the theme default
 * (a third-party product, a legacy listing, etc).
 */
add_action( 'wp_enqueue_scripts', 'bespoke_wc_enqueue_product_page_styles' );

/**
 * Add a `bespoke-customiser-product` body class ONLY on single-
 * product pages where the product has a `_bespoke_product_type` meta
 * set (i.e. it goes through the customiser workflow).
 *
 * Used by bespoke-product-page.css to scope a few rules — most
 * importantly the gallery-hide rule — that should only fire on
 * customiser products. Without this class, the standard WC product
 * gallery on pre-designed products (Wales / Brampton / etc.) would
 * be hidden because the PDP CSS replaces the gallery with the
 * configurator on customiser products.
 */
add_filter( 'body_class', 'bespoke_wc_customiser_product_body_class' );
function bespoke_wc_customiser_product_body_class( $classes ) {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return $classes;
    }
    global $post;
    if ( ! $post ) {
        return $classes;
    }
    $type = get_post_meta( $post->ID, '_bespoke_product_type', true );
    if ( $type ) {
        $classes[] = 'bespoke-customiser-product';
    }
    return $classes;
}

function bespoke_wc_enqueue_product_page_styles() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }
    global $post;
    if ( ! $post ) {
        return;
    }
    // Per-product opt-out hook. Default is true (apply styling) so
    // every product page picks up the dark theme automatically; pass
    // false from the filter to revert a specific product.
    $apply = apply_filters( 'bespoke_apply_product_page_styles', true, $post );
    if ( ! $apply ) {
        return;
    }
    // BESPOKE_PLUGIN_URL is defined by the main plugin bootstrap.
    if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
        return;
    }
    $css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-product-page.css';
    $version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
    wp_enqueue_style(
        'bespoke-product-page',
        BESPOKE_PLUGIN_URL . 'assets/bespoke-product-page.css',
        [],
        $version
    );
}

/**
 * Inline cart / checkout styling. The custom cart-item-data rows we
 * append via woocommerce_get_item_data render through WC's standard
 * <dl class="variation"><dt>…</dt><dd>…</dd></dl> markup, but the
 * Astra theme + most stock WC themes target the <ul class="variation">
 * <li> markup that WC uses to display variation attributes. Without
 * matching CSS, the dl/dt/dd rows stack vertically with labels on
 * their own line and values on the next — which is what's showing as
 * "Band Thickness:" then "8CM BAND" on a new line.
 *
 * This rule normalises both forms so each cart row reads as
 *   Label: Value
 * on a single line (or wraps as one continuous block), matching the
 * variation attribute display directly above it.
 */
add_action( 'wp_head', function () {
    if ( ! function_exists( 'is_cart' ) ) return;
    if ( ! is_cart() && ! is_checkout() ) return;
    ?>
    <style id="bespoke-cart-meta-styles">
    /* WooCommerce wraps each cart-item-data row in its OWN
       <dl class="variation"><dt>…</dt><dd>…</dd></dl>. Default browser
       dl styling pushes <dd> onto its own line below the <dt>, which
       is what's making Design / Text / Club badge values appear under
       their labels instead of beside them.

       We can't always rely on theme selector hierarchy beating ours,
       so this rule uses high specificity (body + woocommerce class +
       cart_item) and !important to guarantee a one-line label + value
       layout on every cart row that goes through the dl markup. */

    body.woocommerce-cart .cart_item dl.variation,
    body.woocommerce-checkout .cart_item dl.variation,
    .woocommerce-cart-form__cart-item dl.variation,
    table.shop_table .cart_item dl.variation {
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
        overflow: hidden !important;        /* clearfix for floated dt */
    }
    body.woocommerce-cart .cart_item dl.variation dt,
    body.woocommerce-checkout .cart_item dl.variation dt,
    .woocommerce-cart-form__cart-item dl.variation dt,
    table.shop_table .cart_item dl.variation dt {
        float: left !important;
        clear: left !important;
        margin: 0 .35em .25em 0 !important;
        padding: 0 !important;
        font-weight: 500 !important;
        display: block !important;
    }
    body.woocommerce-cart .cart_item dl.variation dd,
    body.woocommerce-checkout .cart_item dl.variation dd,
    .woocommerce-cart-form__cart-item dl.variation dd,
    table.shop_table .cart_item dl.variation dd {
        margin: 0 0 .25em 0 !important;
        padding: 0 !important;
        font-weight: 500 !important;
        display: block !important;
        overflow: hidden !important;       /* allows the dd to flow next
                                              to the floated dt without
                                              wrapping under it */
    }
    /* WC wraps the value in <p> via wpautop — un-block it. */
    body.woocommerce-cart .cart_item dl.variation dd p,
    body.woocommerce-checkout .cart_item dl.variation dd p,
    .woocommerce-cart-form__cart-item dl.variation dd p,
    table.shop_table .cart_item dl.variation dd p {
        margin: 0 !important;
        padding: 0 !important;
        display: inline !important;
    }
    </style>
    <?php
} );


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

    // Double-sided armbands carry a second preview for the inside face —
    // stack both, labelled, so the cart shows everything being printed.
    $inside_url = $cart_item['bespoke_customisation']['data']['face_inside']['preview_url'] ?? '';

    if ( $inside_url ) {
        $thumb = function( $url, $label ) {
            return sprintf(
                '<span style="display:block;">'
                . '<img src="%s" alt="%s design" style="width:90px;height:90px;object-fit:contain;display:block;background:#fff;border-radius:6px;" />'
                . '<span style="display:block;font-size:10px;line-height:1.4;text-align:center;opacity:.7;">%s</span>'
                . '</span>',
                esc_url( $url ), esc_attr( $label ), esc_html( $label )
            );
        };
        return '<span style="display:inline-block;">'
            . $thumb( $preview_url, 'Outside' )
            . $thumb( $inside_url,  'Inside' )
            . '</span>';
    }

    return sprintf(
        '<img src="%s" alt="Your custom design" style="width:100px;height:100px;object-fit:contain;display:block;" />',
        esc_url( $preview_url )
    );
}


/* =========================================================================
   2b. CUSTOMER-FACING ORDER SUMMARY
   The order-received (thank-you) page and the customer emails only show
   WooCommerce's own variation attributes (Pattern / Band Thickness / …) —
   the customisation itself lives in the hidden _bespoke_customisation
   blob. Append the same summary rows the customer saw in the cart, plus
   the design preview image(s), so the confirmation actually shows what
   they ordered.
   ========================================================================= */

add_action( 'woocommerce_order_item_meta_end', 'bespoke_order_item_customer_summary', 10, 4 );

/**
 * @param int                   $item_id
 * @param WC_Order_Item_Product $item
 * @param WC_Order              $order
 * @param bool                  $plain_text  True in plain-text emails.
 */
function bespoke_order_item_customer_summary( $item_id, $item, $order, $plain_text = false ) {

    if ( ! $item instanceof WC_Order_Item_Product ) {
        return;
    }
    $raw = $item->get_meta( '_bespoke_customisation' );
    if ( ! $raw ) {
        return;
    }
    $customisation = json_decode( $raw, true );
    if ( ! is_array( $customisation ) ) {
        return;
    }
    $type = $customisation['type'] ?? '';
    $d    = $customisation['data'] ?? [];

    // Reuse the cart renderer so the confirmation matches what the
    // customer saw in the cart (Design / Text / Club badge / Inside face…).
    $renderer = bespoke_resolve_display_renderer( 'bespoke_render_cart_', $type );
    $rows     = $renderer ? call_user_func( $renderer, [], $d ) : [];

    if ( $plain_text ) {
        foreach ( $rows as $r ) {
            echo "\n" . wp_strip_all_tags( $r['name'] ) . ': ' . wp_strip_all_tags( $r['value'] );
        }
        return;
    }

    echo '<div class="bespoke-order-summary" style="margin-top:10px;font-size:13px;line-height:1.7;">';
    foreach ( $rows as $r ) {
        echo '<div><span style="color:#888;">' . wp_kses_post( $r['name'] ) . ':</span> '
            . '<strong>' . wp_kses_post( $r['value'] ) . '</strong></div>';
    }

    // Design preview(s). Double-sided armbands show Outside + Inside side
    // by side; every other product shows its single preview, unlabelled.
    $previews = [];
    if ( ! empty( $d['preview_url'] ) ) {
        $previews[] = [ 'url' => $d['preview_url'], 'label' => '' ];
    }
    if ( ! empty( $d['face_inside']['preview_url'] ) ) {
        if ( $previews ) {
            $previews[0]['label'] = 'Outside';
        }
        $previews[] = [ 'url' => $d['face_inside']['preview_url'], 'label' => 'Inside' ];
    }
    if ( $previews ) {
        echo '<div style="margin-top:8px;">';
        foreach ( $previews as $p ) {
            echo '<span style="display:inline-block;vertical-align:top;margin:0 10px 6px 0;text-align:center;">'
                . '<img src="' . esc_url( $p['url'] ) . '" alt="Design preview" '
                . 'style="width:120px;height:120px;object-fit:contain;background:#fff;border-radius:8px;display:block;" />';
            if ( $p['label'] !== '' ) {
                echo '<span style="display:block;font-size:11px;margin-top:3px;opacity:.7;">' . esc_html( $p['label'] ) . '</span>';
            }
            echo '</span>';
        }
        echo '</div>';
    }
    echo '</div>';
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

    // Route to the correct renderer for this product type (falling back to the
    // inherited base type, then the standard renderer — see the resolver).
    $renderer = bespoke_resolve_display_renderer( 'bespoke_render_cart_', $type );

    if ( $renderer ) {
        $item_data = call_user_func( $renderer, $item_data, $customisation['data'] );
    }

    return $item_data;
}

/**
 * Pick the display renderer for a product type.
 *
 * Renderers are named bespoke_render_cart_{type} / bespoke_render_admin_{type}.
 * Child types (referee_armbands) and anything added via Product Setup →
 * "Add a product type" have no renderer of their own, and the dispatchers used
 * to simply skip when the exact function was missing — so the cart line, the
 * confirmation email AND the admin order screen showed NOTHING for them: no
 * design, colours, text or badge, i.e. an order you couldn't print.
 *
 * Resolve in the same order the front end resolves behaviour:
 *   1. the type's own renderer
 *   2. the base type it inherits (referee_armbands → armbands)
 *   3. the standard shin-pad renderer — which is what a type with no declared
 *      base already renders as in the customiser, so the two stay consistent.
 *
 * @param  string $prefix 'bespoke_render_cart_' or 'bespoke_render_admin_'.
 * @param  string $type   The stored customisation type.
 * @return callable|null
 */
function bespoke_resolve_display_renderer( $prefix, $type ) {
    $type = (string) $type;
    // Cleared unless we actually fall back — read by
    // bespoke_render_admin_generic_card() to head the card with the REAL
    // product name. Types with their own renderer keep their own heading.
    $GLOBALS['bespoke_render_fallback_type'] = '';
    if ( $type === '' ) {
        return null;
    }
    if ( is_callable( $prefix . $type ) ) {
        return $prefix . $type;
    }
    // Borrowing another type's renderer from here on — remember whose order
    // this really is, so the spec card isn't headed with the lender's name.
    $GLOBALS['bespoke_render_fallback_type'] = $type;
    if ( function_exists( 'bespoke_inherit_product_type' ) ) {
        $base = bespoke_inherit_product_type( $type );
        if ( $base && $base !== $type && is_callable( $prefix . $base ) ) {
            return $prefix . $base;
        }
    }
    return is_callable( $prefix . 'shinpads' ) ? $prefix . 'shinpads' : null;
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

    // New fields (Stage 2 / Stage 3) — only render when non-empty so a
    // bog-standard order without removals or rotation looks the same as
    // it always has.
    $removed = bespoke_format_hidden( $d['hidden_elements'] ?? [] );
    if ( $removed !== '' ) {
        $item_data[] = [ 'name' => 'Removed', 'value' => esc_html( $removed ) ];
    }
    $rot_rows = bespoke_format_rotation( $d['rotation'] ?? [] );
    if ( $rot_rows ) {
        $item_data[] = [ 'name' => 'Rotation', 'value' => esc_html( implode( ', ', $rot_rows ) ) ];
    }

    return $item_data;
}


/**
 * Cart display renderer for grip socks.
 * Same shape as shin pads but without Design (grip socks have no
 * design step). Relabels "pad" → "sock" so the customer sees the
 * right wording at checkout.
 *
 * @param  array $item_data  Rows to append to.
 * @param  array $d          The 'data' portion of the customisation blob.
 * @return array
 */
function bespoke_render_cart_gripsocks( $item_data, $d ) {

    if ( ! empty( $d['size'] ) ) {
        $item_data[] = [ 'name' => 'Size', 'value' => esc_html( $d['size'] ) ];
    }
    $left_parts = array_filter( [
        $d['left']['name']   ?? '',
        $d['left']['number'] ?? '',
    ] );
    if ( $left_parts ) {
        $item_data[] = [
            'name'  => 'Left sock',
            'value' => esc_html( implode( ' / ', $left_parts ) ),
        ];
    }
    $right_parts = array_filter( [
        $d['right']['name']   ?? '',
        $d['right']['number'] ?? '',
    ] );
    if ( $right_parts ) {
        $item_data[] = [
            'name'  => 'Right sock',
            'value' => esc_html( implode( ' / ', $right_parts ) ),
        ];
    }
    $item_data[] = [
        'name'  => 'Club badge',
        'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added',
    ];
    $removed = bespoke_format_hidden( $d['hidden_elements'] ?? [] );
    if ( $removed !== '' ) {
        $item_data[] = [ 'name' => 'Removed', 'value' => esc_html( $removed ) ];
    }
    $rot_rows = bespoke_format_rotation( $d['rotation'] ?? [] );
    if ( $rot_rows ) {
        $item_data[] = [ 'name' => 'Rotation', 'value' => esc_html( implode( ', ', $rot_rows ) ) ];
    }
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
    $renderer = bespoke_resolve_display_renderer( 'bespoke_render_admin_', $type );

    if ( $renderer ) {
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

    // Helper: render a colour swatch + hex value + one-click Copy button.
    // Delegates to the shared bespoke_admin_colour_swatch() so every spec
    // card looks identical and the copy-to-clipboard JS only emits once.
    $colour_swatch = function( $hex ) {
        return bespoke_admin_colour_swatch( $hex );
    };

    // Helper: render a gradient swatch (from → to) with TWO Copy
    // buttons so production can grab either stop straight into
    // Photoshop. The gradient bar already shows the colours, so we
    // skip the per-stop solid squares and just show "#hex [Copy]"
    // for each end of the gradient.
    $gradient_swatch = function( $grad ) {
        if ( empty( $grad['from'] ) || empty( $grad['to'] ) ) return '—';
        $from = esc_attr( $grad['from'] );
        $to   = esc_attr( $grad['to']   );
        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;">'
            . '<span style="display:inline-block;width:36px;height:14px;border-radius:3px;background:linear-gradient(180deg,%1$s,%2$s);border:1px solid rgba(0,0,0,0.15);flex-shrink:0;" title="Gradient %1$s → %2$s"></span>'
            . '<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;"><code>%1$s</code>%3$s</span>'
            . '<span style="font-size:11px;color:#666;">→</span>'
            . '<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;"><code>%2$s</code>%4$s</span>'
            . '<span style="font-size:10px;font-weight:700;color:#2E7D32;text-transform:uppercase;letter-spacing:.5px;background:#E8F5E9;padding:2px 6px;border-radius:3px;">Gradient</span>'
            . '</span>',
            $from,
            $to,
            bespoke_admin_copy_button( $grad['from'] ),
            bespoke_admin_copy_button( $grad['to']   )
        );
    };

    // Helper: render either a solid swatch or gradient swatch depending
    // on whether the zone is in gradient mode.
    $colour_or_gradient = function( $solid_hex, $grad ) use ( $colour_swatch, $gradient_swatch ) {
        if ( is_array( $grad ) && ! empty( $grad['from'] ) && ! empty( $grad['to'] ) ) {
            return $gradient_swatch( $grad );
        }
        return $colour_swatch( $solid_hex );
    };

    // Helper: pretty-print a font name. The customiser sends either a
    // friendly label ('Villain') or a raw CSS font-family list ('"Arial
    // Black", Arial, sans-serif'). For the CSS list case, take the first
    // family and strip quotes so production doesn't see backslashes.
    $clean_font = function( $raw ) {
        $raw = trim( (string) $raw );
        if ( $raw === '' ) return '—';
        // Take first entry from a comma list (CSS font-family stacks)
        $first = trim( explode( ',', $raw )[0] );
        // Strip surrounding single/double quotes (and any escaped slashes)
        $first = trim( $first, "\"' \t\n\r\0\x0B\\" );
        return $first !== '' ? $first : $raw;
    };

    // Helper: look up the human-readable design name from its slug.
    // Falls back to the slug ucfirst'd if no matching post is found.
    $design_label = function( $slug ) {
        $slug = trim( (string) $slug );
        if ( $slug === '' ) return '—';
        $posts = get_posts( [
            'post_type'        => 'bespoke_design',
            'name'             => $slug,
            'posts_per_page'   => 1,
            'post_status'      => 'any',
            'suppress_filters' => true,
        ] );
        if ( ! empty( $posts ) ) {
            return $posts[0]->post_title;
        }
        return ucfirst( $slug );
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
                    $row( 'Design', esc_html( $design_label( $d['design'] ?? '' ) ) )
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
                    $row( 'Name font',   esc_html( $clean_font( $d['fonts']['name']   ?? '' ) ) ) .
                    $row( 'Number font', esc_html( $clean_font( $d['fonts']['number'] ?? '' ) ) )
                );
                // Pattern colour rows. Multi-pattern designs (e.g. Tramline)
                // send a 'patterns' array — show each layer as Pattern 1,
                // Pattern 2, etc. Single-pattern designs (or older orders
                // placed before the multi-pattern update) fall back to the
                // legacy 'pattern' field. If a per-pattern gradient is set
                // for that layer, the gradient overrides the solid swatch.
                $patterns_arr = $d['colours']['patterns'] ?? [];
                if ( ! is_array( $patterns_arr ) ) {
                    $patterns_arr = [];
                }
                if ( empty( $patterns_arr ) ) {
                    $legacy = $d['colours']['pattern'] ?? '';
                    if ( $legacy ) $patterns_arr = [ $legacy ];
                }
                $pat_grads_arr = $d['colours']['pattern_gradients'] ?? [];
                if ( ! is_array( $pat_grads_arr ) ) {
                    $pat_grads_arr = [];
                }

                $pattern_rows = '';
                if ( count( $patterns_arr ) <= 1 ) {
                    $first = $patterns_arr[0] ?? '';
                    $pattern_rows = $row(
                        'Pattern',
                        $colour_or_gradient( $first, $pat_grads_arr[0] ?? null )
                    );
                } else {
                    foreach ( $patterns_arr as $i => $hex ) {
                        $pattern_rows .= $row(
                            'Pattern ' . ( $i + 1 ),
                            $colour_or_gradient( $hex, $pat_grads_arr[ $i ] ?? null )
                        );
                    }
                }

                // Pad background: show gradient if customer enabled one,
                // else the solid swatch.
                $bg_value = $colour_or_gradient(
                    $d['colours']['background']  ?? '',
                    $d['colours']['bg_gradient'] ?? null
                );

                echo $section( 'Colours',
                    $row( 'Pad background', $bg_value ) .
                    $pattern_rows .
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


/**
 * Admin order display renderer for grip socks.
 * Slimmer than shin pads — no Design row, no Pad-background / Pattern
 * colour swatches (grip socks don't have those steps). Re-uses the
 * helper closures by re-defining a minimal set inline so this file
 * remains self-contained.
 *
 * @param array                 $d     The 'data' portion of the customisation blob.
 * @param WC_Order_Item_Product $item  The order line item.
 */
function bespoke_render_admin_gripsocks( $d, $item ) {

    // Delegates to the shared bespoke_admin_colour_swatch() so every
    // hex value in every spec card gets the same one-click Copy button.
    $colour_swatch = function( $hex ) {
        return bespoke_admin_colour_swatch( $hex );
    };
    $row = function( $label, $value ) {
        if ( $value === '' || $value === null ) $value = '—';
        return '<tr>'
            . '<td style="padding:5px 10px 5px 0;color:#666;white-space:nowrap;font-size:12px;vertical-align:top;">' . esc_html( $label ) . '</td>'
            . '<td style="padding:5px 0;font-size:12px;font-weight:600;color:#1a1a1a;">' . $value . '</td>'
            . '</tr>';
    };
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
    $clean_font = function( $raw ) {
        $raw = trim( (string) $raw );
        if ( $raw === '' ) return '—';
        $first = trim( explode( ',', $raw )[0] );
        $first = trim( $first, "\"' \t\n\r\0\x0B\\" );
        return $first !== '' ? $first : $raw;
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
            ✦ BEspoke Sport — Grip Sock Specification
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

            <!-- ── LEFT COLUMN ───────────────────────────────────────────── -->
            <div>
                <?php
                echo $section( 'Product',
                    $row( 'Size', $d['size'] ?? '' )
                );
                echo $section( 'Left sock',
                    $row( 'Name',   $d['left']['name']   ?? '' ) .
                    $row( 'Number', $d['left']['number'] ?? '' )
                );
                echo $section( 'Right sock',
                    $row( 'Name',   $d['right']['name']   ?? '' ) .
                    $row( 'Number', $d['right']['number'] ?? '' )
                );
                ?>
            </div>

            <!-- ── RIGHT COLUMN ──────────────────────────────────────────── -->
            <div>
                <?php
                echo $section( 'Fonts',
                    $row( 'Name font',   esc_html( $clean_font( $d['fonts']['name']   ?? '' ) ) ) .
                    $row( 'Number font', esc_html( $clean_font( $d['fonts']['number'] ?? '' ) ) )
                );
                // Grip socks only customise text colour (no pad bg or
                // pattern, since there's no design or colour step).
                echo $section( 'Colours',
                    $row( 'Name text',   $colour_swatch( $d['colours']['name_text']   ?? '' ) ) .
                    $row( 'Number text', $colour_swatch( $d['colours']['number_text'] ?? '' ) )
                );
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


/* =========================================================================
   SHARED FORMATTING HELPERS
   Reused by every product-specific cart / admin renderer below — converts
   the raw flag values stored in 'data' (hidden_elements as ['badgeR'],
   rotation as { badgeL: 12.5 }) into customer- and production-readable
   text. Wrapped as plain functions so any future product renderer can
   call them without re-implementing the friendly-label logic.
   ========================================================================= */

/**
 * Human-readable name for one of the data-drag keys. Used by the cart and
 * admin renderers to turn 'badgeR' / 'nameL' / etc. into "Right badge" /
 * "Left name" etc. that the customer + production team can read at a glance.
 */
function bespoke_friendly_key( $key ) {
    $side  = ( substr( $key, -1 ) === 'L' ) ? 'Left'  : 'Right';
    $thing = '';
    if ( strpos( $key, 'badge' ) === 0 ) $thing = 'badge';
    elseif ( strpos( $key, 'name' )  === 0 ) $thing = 'name';
    elseif ( strpos( $key, 'num' )   === 0 ) $thing = 'number';
    if ( ! $thing ) return $key;
    return $side . ' ' . $thing;
}

/**
 * Build the "Removed" line for the cart / admin display. Returns an empty
 * string when nothing was removed.
 */
function bespoke_format_hidden( $hidden ) {
    if ( empty( $hidden ) || ! is_array( $hidden ) ) return '';
    $labels = array_map( 'bespoke_friendly_key', $hidden );
    return implode( ', ', $labels );
}

/**
 * Build the rotation summary list. Each rotated element becomes a row
 * "Left badge: 12.5°". Empty array when nothing's rotated.
 */
function bespoke_format_rotation( $rotation ) {
    if ( empty( $rotation ) || ! is_array( $rotation ) ) return [];
    $rows = [];
    foreach ( $rotation as $k => $deg ) {
        if ( abs( (float) $deg ) < 0.1 ) continue;
        $rows[] = bespoke_friendly_key( $k ) . ': ' . round( (float) $deg, 1 ) . '°';
    }
    return $rows;
}

/**
 * Background variant friendly label. Prefers the JS-sent label
 * ("5cm band" / "With Frill"), falls back to 'default' / 'alt' as a
 * minimum so production never sees an empty cell.
 */
function bespoke_format_bg_variant( $d ) {
    $label = trim( (string) ( $d['bg_variant_label'] ?? '' ) );
    if ( $label !== '' ) return $label;
    return ( ( $d['bg_variant'] ?? '' ) === 'alt' ) ? 'Alt' : 'Default';
}


/**
 * Render just the "Copy" button for a hex value — used inline by
 * gradient swatches where the solid swatch square would duplicate
 * the gradient bar visually. Also emits the inline JS once per
 * request via bespoke_admin_emit_copy_script().
 */
function bespoke_admin_copy_button( $hex ) {
    if ( ! $hex ) return '';
    bespoke_admin_emit_copy_script();
    $safe = esc_attr( $hex );
    return sprintf(
        '<button type="button" class="bespoke-copy-hex" data-bespoke-copy="%1$s" '
        . 'title="Copy %1$s to clipboard" '
        . 'style="all:unset;cursor:pointer;font-size:10px;font-weight:700;color:#2E7D32;text-transform:uppercase;letter-spacing:.5px;background:#E8F5E9;padding:2px 7px;border-radius:3px;line-height:1.4;border:1px solid #C8E6C9;">'
        . 'Copy'
        . '</button>',
        $safe
    );
}

/**
 * Render a colour swatch + hex value + one-click "Copy" button.
 *
 * Used by every product spec card in the WC admin order screen so
 * production can paste hex codes straight into Photoshop without
 * having to manually select + Ctrl+C. The button writes the hex to
 * the clipboard via the modern Clipboard API (with a
 * document.execCommand fallback for older browsers) and briefly
 * flashes "✓ Copied" feedback so the user knows it worked.
 *
 * Returns the swatch markup (or '—' if no hex).
 */
function bespoke_admin_colour_swatch( $hex ) {
    if ( ! $hex ) return '—';
    $safe = esc_attr( $hex );
    return sprintf(
        '<span style="display:inline-flex;align-items:center;gap:6px;">'
        . '<span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:%1$s;border:1px solid rgba(0,0,0,0.15);flex-shrink:0;"></span>'
        . '<code style="font-size:12px;">%1$s</code>'
        . '%2$s'
        . '</span>',
        $safe,
        bespoke_admin_copy_button( $hex )
    );
}

/**
 * Emit the inline JS that powers the "Copy" buttons in colour
 * swatches. Static flag guarantees the script tag is printed at most
 * once per request — first colour swatch on the page wins, every
 * later swatch just relies on the already-wired delegated listener.
 */
function bespoke_admin_emit_copy_script() {
    static $emitted = false;
    if ( $emitted ) return;
    $emitted = true;
    ?>
    <script>
    (function(){
        if (window.__bespokeCopyHexWired) return;
        window.__bespokeCopyHexWired = true;
        document.addEventListener('click', function(e){
            var btn = e.target && e.target.closest && e.target.closest('.bespoke-copy-hex');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var hex = btn.getAttribute('data-bespoke-copy') || '';
            if (!hex) return;
            var done = function(ok){
                var orig = btn.dataset.bespokeOrig || btn.textContent;
                btn.dataset.bespokeOrig = orig;
                btn.textContent = ok ? '✓ Copied' : 'Copy failed';
                btn.style.background = ok ? '#C8E6C9' : '#FFCDD2';
                btn.style.color      = ok ? '#1B5E20' : '#B71C1C';
                clearTimeout(btn.__bespokeResetTimer);
                btn.__bespokeResetTimer = setTimeout(function(){
                    btn.textContent      = orig;
                    btn.style.background = '#E8F5E9';
                    btn.style.color      = '#2E7D32';
                }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(hex)
                    .then(function(){ done(true); })
                    .catch(function(){ done(false); });
            } else {
                // Fallback for older browsers / non-HTTPS admin contexts
                var ta = document.createElement('textarea');
                ta.value = hex;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.left     = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch(err) { ok = false; }
                document.body.removeChild(ta);
                done(ok);
            }
        });
    })();
    </script>
    <?php
}


/* =========================================================================
   ARMBAND — cart + admin renderers
   ========================================================================= */

function bespoke_render_cart_armbands( $item_data, $d ) {
    // Size + band thickness aren't added here — WC shows them
    // automatically via the variation attributes for variable products
    // (Band Thickness / Band Width / etc.). Adding our own Diameter and
    // Band thickness rows on top of that just duplicates the info.
    if ( ! empty( $d['design'] ) ) {
        $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    }
    if ( ! empty( $d['left']['name'] ) ) {
        $item_data[] = [ 'name' => 'Text', 'value' => esc_html( $d['left']['name'] ) ];
    }
    $item_data[] = [
        'name'  => 'Club badge',
        'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added',
    ];
    // Second badge spot — only rowed when it deviates from the default
    // "same badge twice", so ordinary orders stay uncluttered.
    $b2mode = $d['badge2']['mode'] ?? 'same';
    if ( $b2mode === 'different' ) {
        $item_data[] = [
            'name'  => 'Second badge',
            'value' => ! empty( $d['badge2']['url'] ) ? 'Uploaded' : 'Not added',
        ];
    } elseif ( $b2mode === 'none' ) {
        $item_data[] = [ 'name' => 'Second badge', 'value' => 'None' ];
    }
    $removed = bespoke_format_hidden( $d['hidden_elements'] ?? [] );
    if ( $removed !== '' ) {
        $item_data[] = [ 'name' => 'Removed', 'value' => esc_html( $removed ) ];
    }
    return $item_data;
}

function bespoke_render_admin_armbands( $d, $item ) {
    echo bespoke_render_admin_generic_card( 'Captain Armband', $d, [
        'size_label'    => 'Diameter',
        'show_variant'  => 'Band thickness',
        'show_text'     => [ 'left_name' => 'Slogan / name' ],
    ] );
}


/* =========================================================================
   DOUBLE-SIDED CAPTAIN ARMBANDS — cart + admin renderers
   Two independent armband designs in one product: the primary $d is the
   OUTSIDE face; $d['face_inside'] is the fully separate INSIDE face (same
   shape as $d, produced by bespoke_parse_inside_face()).
   ========================================================================= */

function bespoke_render_cart_double_sided_armbands( $item_data, $d ) {
    // Reuse the single-armband cart rows for the OUTSIDE face…
    $item_data = bespoke_render_cart_armbands( $item_data, $d );
    // …and flag that a separate inside design rides along.
    if ( ! empty( $d['face_inside'] ) && is_array( $d['face_inside'] ) ) {
        $inside = $d['face_inside'];
        $item_data[] = [
            'name'  => 'Inside face',
            'value' => ! empty( $inside['left']['name'] )
                        ? esc_html( $inside['left']['name'] ) . ' — separate design'
                        : 'Separate design',
        ];
    }
    return $item_data;
}

function bespoke_render_admin_double_sided_armbands( $d, $item ) {
    $band = '<div style="font-size:11px;font-weight:700;color:#2E7D32;text-transform:uppercase;letter-spacing:.5px;margin:10px 0 -6px;">%s <span style="color:#999;font-weight:600;text-transform:none;letter-spacing:0;">%s</span></div>';

    // OUTSIDE face — the primary design, rendered as a normal armband card.
    printf( $band, 'Outside face', '(faces out)' );
    bespoke_render_admin_armbands( $d, $item );

    // INSIDE face — a fully separate design. It shares the outside's structure,
    // so the generic card renders it directly (including its own preview image).
    $inside = $d['face_inside'] ?? null;
    if ( is_array( $inside ) ) {
        printf( $band, 'Inside face', '(against the arm)' );
        echo bespoke_render_admin_generic_card( 'Captain Armband — Inside', $inside, [
            'size_label' => 'Diameter',
            'show_text'  => [ 'left_name' => 'Slogan / name' ],
        ] );
    } else {
        printf( $band, 'Inside face', '(against the arm)' );
        echo '<p style="font-size:12px;color:#aaa;margin:8px 0;">No separate inside design was submitted.</p>';
    }
}


/* =========================================================================
   PENNANT — cart + admin renderers
   ========================================================================= */

function bespoke_render_cart_pennant( $item_data, $d ) {
    // Style is shown by WC's own variation attribute display for
    // variable-product pennants. We just add the design + text + badge.
    if ( ! empty( $d['design'] ) ) {
        $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    }
    if ( ! empty( $d['left']['name'] ) ) {
        $item_data[] = [ 'name' => 'Text', 'value' => esc_html( $d['left']['name'] ) ];
    }
    $item_data[] = [
        'name'  => 'Club badge',
        'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added',
    ];
    return $item_data;
}

function bespoke_render_admin_pennant( $d, $item ) {
    echo bespoke_render_admin_generic_card( 'Pennant', $d, [
        'show_variant'  => 'Style',
        'show_text'     => [ 'left_name' => 'Pennant text' ],
    ] );
}


/* =========================================================================
   AWARDS — Plate Trophy, Gamechanger, Glassblock
   Plate has TWO text fields (Top text / Bottom text wired through left.name +
   left.number). The other two have one text field each.
   ========================================================================= */

function bespoke_render_cart_award_plate( $item_data, $d ) {
    if ( ! empty( $d['design'] ) ) $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    if ( ! empty( $d['left']['name'] ) )   $item_data[] = [ 'name' => 'Top text',    'value' => esc_html( $d['left']['name'] ) ];
    if ( ! empty( $d['left']['number'] ) ) $item_data[] = [ 'name' => 'Bottom text', 'value' => esc_html( $d['left']['number'] ) ];
    $item_data[] = [ 'name' => 'Club badge', 'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added' ];
    return $item_data;
}
function bespoke_render_admin_award_plate( $d, $item ) {
    echo bespoke_render_admin_generic_card( 'Plate Trophy', $d, [
        'show_text' => [
            'left_name'   => 'Top text',
            'left_number' => 'Bottom text',
        ],
    ] );
}

function bespoke_render_cart_award_gamechanger( $item_data, $d ) {
    if ( ! empty( $d['design'] ) ) $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    if ( ! empty( $d['left']['name'] ) ) $item_data[] = [ 'name' => 'Text', 'value' => esc_html( $d['left']['name'] ) ];
    $item_data[] = [ 'name' => 'Club badge', 'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added' ];
    return $item_data;
}
function bespoke_render_admin_award_gamechanger( $d, $item ) {
    echo bespoke_render_admin_generic_card( 'Gamechanger Trophy', $d, [
        'show_text' => [ 'left_name' => 'Text' ],
    ] );
}

function bespoke_render_cart_award_glassblock( $item_data, $d ) {
    if ( ! empty( $d['design'] ) ) $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    if ( ! empty( $d['left']['name'] ) ) $item_data[] = [ 'name' => 'Text', 'value' => esc_html( $d['left']['name'] ) ];
    $item_data[] = [ 'name' => 'Club badge', 'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added' ];
    return $item_data;
}
function bespoke_render_admin_award_glassblock( $d, $item ) {
    echo bespoke_render_admin_generic_card( 'Glassblock Trophy', $d, [
        'show_text' => [ 'left_name' => 'Text' ],
    ] );
}


/* =========================================================================
   CORNER FLAGS — single item, one badge + one text
   ========================================================================= */

function bespoke_render_cart_corner_flags( $item_data, $d ) {
    if ( ! empty( $d['design'] ) ) $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    if ( ! empty( $d['left']['name'] ) ) $item_data[] = [ 'name' => 'Text', 'value' => esc_html( $d['left']['name'] ) ];
    $item_data[] = [ 'name' => 'Club badge', 'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added' ];
    return $item_data;
}
function bespoke_render_admin_corner_flags( $d, $item ) {
    echo bespoke_render_admin_generic_card( 'Corner Flag', $d, [
        'show_text' => [ 'left_name' => 'Text' ],
    ] );
}


/* =========================================================================
   BOTTLES — cart + admin renderers
   Single sublimated bottle: one badge, one name, one number, design +
   colours. Mirrors corner_flags but adds the squad number row.
   ========================================================================= */
function bespoke_render_cart_bottles( $item_data, $d ) {
    if ( ! empty( $d['design'] ) ) {
        $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    }
    if ( ! empty( $d['left']['name'] ) ) {
        $item_data[] = [ 'name' => 'Name', 'value' => esc_html( $d['left']['name'] ) ];
    }
    if ( ! empty( $d['left']['number'] ) ) {
        $item_data[] = [ 'name' => 'Number', 'value' => esc_html( $d['left']['number'] ) ];
    }
    $item_data[] = [
        'name'  => 'Club badge',
        'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added',
    ];
    return $item_data;
}
function bespoke_render_admin_bottles( $d, $item ) {
    echo bespoke_render_admin_generic_card( 'Bottle', $d, [
        'show_text' => [
            'left_name'   => 'Name',
            'left_number' => 'Number',
        ],
    ] );
}


/* =========================================================================
   PLAYER CARDS — cart + admin renderers
   FIFA-style keepsake card with a stack of extra data beyond the standard
   customiser fields: player photo (uploaded), country flag (from bundled
   PNG set), on-field position (RB / GK etc.), 6 stats (PAC/SHO/… or
   DIV/HAN/… for GK) with an auto-calculated OVR rating.

   The customiser stashes all of this under $d['player_card'] alongside
   the standard name / badge / preview fields. Both renderers below reach
   into that sub-array.
   ========================================================================= */
function bespoke_render_cart_player_cards( $item_data, $d ) {
    $pc = ( isset( $d['player_card'] ) && is_array( $d['player_card'] ) ) ? $d['player_card'] : [];
    if ( ! empty( $d['design'] ) ) {
        $item_data[] = [ 'name' => 'Design', 'value' => esc_html( $d['design'] ) ];
    }
    if ( ! empty( $d['left']['name'] ) ) {
        $item_data[] = [ 'name' => 'Player name', 'value' => esc_html( $d['left']['name'] ) ];
    }
    $pos = ! empty( $pc['position_label'] ) ? $pc['position_label'] : ( $pc['position'] ?? '' );
    if ( $pos !== '' ) {
        $item_data[] = [ 'name' => 'Position', 'value' => esc_html( $pos ) ];
    }
    if ( ! empty( $pc['flag'] ) ) {
        $item_data[] = [ 'name' => 'Country', 'value' => esc_html( $pc['flag'] ) ];
    }
    if ( ! empty( $pc['ovr'] ) ) {
        $item_data[] = [ 'name' => 'Overall rating', 'value' => esc_html( (int) $pc['ovr'] ) ];
    }
    $item_data[] = [
        'name'  => 'Player photo',
        'value' => ! empty( $pc['photo']['url'] ) ? 'Uploaded' : 'Not added',
    ];
    $item_data[] = [
        'name'  => 'Club badge',
        'value' => ! empty( $d['badge']['url'] ) ? 'Uploaded' : 'Not added',
    ];
    return $item_data;
}

function bespoke_render_admin_player_cards( $d, $item ) {
    $pc = ( isset( $d['player_card'] ) && is_array( $d['player_card'] ) ) ? $d['player_card'] : [];

    // Flag PNG lives in the plugin at /assets/flags/{Country}.png.
    // rawurlencode handles country names with spaces ("Czech Republic").
    $flag_url = '';
    if ( ! empty( $pc['flag'] ) && defined( 'BESPOKE_PLUGIN_URL' ) ) {
        $flag_url = BESPOKE_PLUGIN_URL . 'assets/flags/' . rawurlencode( $pc['flag'] ) . '.png';
    }

    // Convenience — one line = one label/value pair in the spec table.
    $row = function( $label, $value ) {
        if ( $value === '' || $value === null ) $value = '—';
        return '<tr>'
             . '<td style="padding:5px 10px 5px 0;color:#666;white-space:nowrap;font-size:12px;vertical-align:top;">' . esc_html( $label ) . '</td>'
             . '<td style="padding:5px 0;font-size:12px;font-weight:600;color:#1a1a1a;">' . $value . '</td>'
             . '</tr>';
    };
    $section = function( $heading, $rows_html ) {
        return '<div style="margin-bottom:14px;">'
             . '<div style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.6px;padding-bottom:4px;margin-bottom:6px;border-bottom:1px solid #eee;">'
             . esc_html( $heading ) . '</div>'
             . '<table style="width:100%;border-collapse:collapse;">' . $rows_html . '</table>'
             . '</div>';
    };

    ob_start();
    ?>
    <div style="margin-top:12px;padding:14px 16px;background:#f9f8f6;border:1px solid #e5e5e5;border-radius:6px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
        <div style="font-size:11px;font-weight:700;color:#2E7D32;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;">
            ✦ BEspoke Sport — Player Card Specification
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

            <!-- LEFT COLUMN: Player + Stats + Product -->
            <div>
                <?php
                // --- Player identity ---
                $rows = '';
                if ( ! empty( $d['left']['name'] ) ) {
                    $rows .= $row( 'Name', esc_html( $d['left']['name'] ) );
                }
                $pos_label = ! empty( $pc['position_label'] ) ? $pc['position_label'] : ( $pc['position'] ?? '' );
                if ( $pos_label !== '' ) {
                    $rows .= $row( 'Position', esc_html( $pos_label ) );
                }
                if ( ! empty( $pc['flag'] ) ) {
                    $flag_cell = '';
                    if ( $flag_url ) {
                        $flag_cell .= '<img src="' . esc_url( $flag_url ) . '" alt="" style="width:26px;height:auto;vertical-align:middle;margin-right:8px;border:1px solid #ddd;"/>';
                    }
                    $flag_cell .= esc_html( $pc['flag'] );
                    $rows .= $row( 'Country', $flag_cell );
                }
                $ovr = isset( $pc['ovr'] ) ? (int) $pc['ovr'] : 0;
                if ( $ovr > 0 ) {
                    $rows .= $row( 'Overall',
                        '<span style="font-size:20px;font-weight:800;color:#2E7D32;line-height:1;">' . $ovr . '</span>'
                    );
                }
                if ( $rows ) echo $section( 'Player', $rows );

                // --- Stats ---
                $stats = ( isset( $pc['stats'] ) && is_array( $pc['stats'] ) ) ? $pc['stats'] : [];
                $has_stat_value = false;
                foreach ( $stats as $st ) {
                    if ( ! empty( $st['value'] ) ) { $has_stat_value = true; break; }
                }
                if ( ! empty( $stats ) ) {
                    $stat_rows = '';
                    foreach ( $stats as $st ) {
                        $lbl = isset( $st['label'] ) ? $st['label'] : '';
                        $val = isset( $st['value'] ) ? (int) $st['value'] : 0;
                        if ( $lbl === '' ) continue;
                        $stat_rows .= $row( $lbl, '<span style="font-size:14px;font-weight:700;">' . $val . '</span>' );
                    }
                    if ( $stat_rows ) echo $section( 'Stats', $stat_rows );
                }

                // --- Product / Design ---
                $product_rows = '';
                if ( ! empty( $d['size'] ) ) {
                    $product_rows .= $row( 'Size',   esc_html( $d['size'] ) );
                }
                if ( ! empty( $d['design'] ) ) {
                    $product_rows .= $row( 'Design', esc_html( $d['design'] ) );
                }
                // Font (only if the customer changed it from the default)
                $font_raw = $d['fonts']['name'] ?? '';
                if ( $font_raw !== '' && $font_raw !== 'Arial Black' ) {
                    $first = trim( explode( ',', $font_raw )[0] );
                    $first = trim( $first, "\"' \t\n\r\0\x0B\\" );
                    if ( $first !== '' ) {
                        $product_rows .= $row( 'Text font', esc_html( $first ) );
                    }
                }
                if ( $product_rows ) echo $section( 'Product', $product_rows );
                ?>
            </div>

            <!-- RIGHT COLUMN: Photo + Badge + Notes -->
            <div>
                <?php
                // --- Player photo ---
                $photo_rows = '';
                if ( ! empty( $pc['photo']['url'] ) ) {
                    $photo_rows .= $row( 'Status', '<span style="color:#2E7D32;font-weight:700;">Uploaded</span>' );
                    if ( ! empty( $pc['photo']['filename'] ) ) {
                        $photo_rows .= $row( 'Filename',
                            '<a href="' . esc_url( $pc['photo']['url'] ) . '" target="_blank" style="color:#0073aa;text-decoration:none;"><code>' . esc_html( $pc['photo']['filename'] ) . '</code></a>'
                        );
                    }
                    $photo_rows .= $row( 'Preview',
                        '<a href="' . esc_url( $pc['photo']['url'] ) . '" target="_blank">'
                        . '<img src="' . esc_url( $pc['photo']['url'] ) . '" style="max-width:140px;max-height:200px;object-fit:contain;border:1px solid #ddd;border-radius:4px;background:#fff;display:block;margin-top:4px;"/>'
                        . '</a>'
                    );
                } else {
                    $photo_rows .= $row( 'Status', '<span style="color:#aaa;">Not added</span>' );
                }
                echo $section( 'Player photo', $photo_rows );

                // --- Club badge ---
                $badge_rows = '';
                if ( ! empty( $d['badge']['url'] ) ) {
                    $badge_rows .= $row( 'Status', '<span style="color:#2E7D32;font-weight:700;">Uploaded</span>' );
                    if ( ! empty( $d['badge']['filename'] ) ) {
                        $badge_rows .= $row( 'Filename',
                            '<a href="' . esc_url( $d['badge']['url'] ) . '" target="_blank" style="color:#0073aa;text-decoration:none;"><code>' . esc_html( $d['badge']['filename'] ) . '</code></a>'
                        );
                    }
                    $badge_rows .= $row( 'Preview',
                        '<a href="' . esc_url( $d['badge']['url'] ) . '" target="_blank">'
                        . '<img src="' . esc_url( $d['badge']['url'] ) . '" style="max-width:120px;max-height:120px;object-fit:contain;border:1px solid #ddd;border-radius:4px;background:#fff;padding:4px;display:block;margin-top:4px;"/>'
                        . '</a>'
                    );
                } else {
                    $badge_rows .= $row( 'Status', '<span style="color:#aaa;">Not added</span>' );
                }
                echo $section( 'Club badge', $badge_rows );

                // --- Order notes ---
                if ( ! empty( $d['notes'] ) ) {
                    echo $section( 'Order notes',
                        '<tr><td colspan="2" style="padding:6px 0;font-size:12px;color:#1a1a1a;background:#fff;border:1px solid #eee;border-radius:4px;padding:8px 10px;">'
                        . nl2br( esc_html( $d['notes'] ) )
                        . '</td></tr>'
                    );
                }
                ?>
            </div>
        </div>

        <!-- Full-width card preview snapshot the customiser uploaded
             just before add-to-cart. Same treatment as every other
             product's admin card so production sees what the customer
             saw. -->
        <?php if ( ! empty( $d['preview_url'] ) ) : ?>
            <div style="margin-top:6px;">
                <div style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.6px;padding-bottom:4px;margin-bottom:6px;border-bottom:1px solid #eee;">
                    Card preview
                </div>
                <a href="<?php echo esc_url( $d['preview_url'] ); ?>" target="_blank">
                    <img src="<?php echo esc_url( $d['preview_url'] ); ?>"
                         style="max-height:320px;max-width:100%;object-fit:contain;border:1px solid #eee;border-radius:4px;padding:4px;background:#fff;display:block;"/>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
}


/* =========================================================================
   GENERIC ADMIN ORDER CARD
   Reused by every product type added above so we don't replicate the
   ~200-line shin-pad layout for each. Returns HTML.

   $product_label : friendly header ("Pennant", "Captain Armband").
   $opts:
     size_label   — overrides the "Size" row label (e.g. "Diameter").
     show_variant — when set, renders the bg_variant friendly label as a
                    row with the given label ("Style", "Band thickness").
     show_text    — map of [ field => label ]. Keys:
                      left_name, left_number, right_name, right_number.
                    Only rows whose value is non-empty render.
   ========================================================================= */
function bespoke_render_admin_generic_card( $product_label, $d, $opts = [] ) {

    // When this type is BORROWING another type's renderer (referee armbands
    // using the captain-armband card, or anything added via "Add a product
    // type"), head the card with the real product name — otherwise a referee
    // order reads "CAPTAIN ARMBAND SPECIFICATION" and production can pull the
    // wrong item. Types with their own renderer are untouched.
    $fallback_type = $GLOBALS['bespoke_render_fallback_type'] ?? '';
    if ( $fallback_type && function_exists( 'bespoke_get_product_types' ) ) {
        $all_types = bespoke_get_product_types();
        if ( ! empty( $all_types[ $fallback_type ] ) ) {
            $product_label = $all_types[ $fallback_type ];
        }
    }

    $row = function( $label, $value ) {
        if ( $value === '' || $value === null ) $value = '—';
        return '<tr><td style="padding:5px 10px 5px 0;color:#666;white-space:nowrap;font-size:12px;vertical-align:top;">'
            . esc_html( $label ) . '</td>'
            . '<td style="padding:5px 0;font-size:12px;font-weight:600;color:#1a1a1a;">' . $value . '</td></tr>';
    };
    $section = function( $heading, $rows_html ) {
        return '<div style="margin-bottom:14px;">'
            . '<div style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.6px;padding-bottom:4px;margin-bottom:6px;border-bottom:1px solid #eee;">'
            . esc_html( $heading ) . '</div>'
            . '<table style="width:100%;border-collapse:collapse;">' . $rows_html . '</table>'
            . '</div>';
    };
    // Delegates to the shared bespoke_admin_colour_swatch() so every
    // hex value in every spec card gets the same one-click Copy button.
    $swatch = function( $hex ) {
        return bespoke_admin_colour_swatch( $hex );
    };

    $size_label   = $opts['size_label']   ?? 'Size';
    $show_variant = $opts['show_variant'] ?? '';
    $show_text    = $opts['show_text']    ?? [];

    ob_start();
    ?>
    <div style="margin-top:12px;padding:14px 16px;background:#f9f8f6;border:1px solid #e5e5e5;border-radius:6px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
        <div style="font-size:11px;font-weight:700;color:#2E7D32;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;">
            ✦ BEspoke Sport — <?php echo esc_html( $product_label ); ?> Specification
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

            <!-- LEFT COLUMN: product details + text -->
            <div>
                <?php
                $product_rows = '';
                if ( ! empty( $d['size']   ) ) $product_rows .= $row( $size_label, esc_html( $d['size'] ) );
                if ( ! empty( $d['design'] ) ) $product_rows .= $row( 'Design',   esc_html( $d['design'] ) );
                if ( $show_variant )           $product_rows .= $row( $show_variant, esc_html( bespoke_format_bg_variant( $d ) ) );
                if ( $product_rows ) echo $section( 'Product', $product_rows );

                // Helper: clean a font reference. The customiser sends
                // either a friendly label ("Villain") or a CSS family
                // stack ('"Arial Black", Arial, sans-serif'). Take the
                // first family from a stack and strip the quotes.
                $clean_font = function( $raw ) {
                    $raw = trim( (string) $raw );
                    if ( $raw === '' ) return '';
                    $first = trim( explode( ',', $raw )[0] );
                    return trim( $first, "\"' \t\n\r\0\x0B\\" );
                };

                $text_rows = '';
                foreach ( $show_text as $field => $label ) {
                    $parts = explode( '_', $field, 2 );
                    if ( count( $parts ) !== 2 ) continue;
                    list( $side, $kind ) = $parts;
                    $val = $d[ $side ][ $kind ] ?? '';
                    if ( $val === '' ) continue;
                    $text_rows .= $row( $label, esc_html( $val ) );
                    // Show the matching font next to its text row so
                    // production knows what to set. 'name' kind reads
                    // d.fonts.name, 'number' reads d.fonts.number.
                    $font_raw   = $d['fonts'][ $kind ] ?? '';
                    $font_clean = $clean_font( $font_raw );
                    if ( $font_clean !== '' ) {
                        $text_rows .= $row( $label . ' font', esc_html( $font_clean ) );
                    }
                }
                if ( $text_rows ) echo $section( 'Text', $text_rows );

                $hidden_str = bespoke_format_hidden( $d['hidden_elements'] ?? [] );
                if ( $hidden_str !== '' ) {
                    echo $section( 'Removed elements', $row( 'Customer removed', esc_html( $hidden_str ) ) );
                }
                $rot_rows = bespoke_format_rotation( $d['rotation'] ?? [] );
                if ( $rot_rows ) {
                    $rot_html = '';
                    foreach ( $rot_rows as $r ) {
                        $rot_html .= $row( 'Rotation', esc_html( $r ) );
                    }
                    echo $section( 'Rotation', $rot_html );
                }
                ?>
            </div>

            <!-- RIGHT COLUMN: badge + colours + notes -->
            <div>
                <?php
                $badge_url = $d['badge']['url'] ?? '';
                $badge_fn  = $d['badge']['filename'] ?? '';
                $badge_html = '';
                if ( $badge_url ) {
                    $badge_html .= $row( 'Status', '<span style="color:#2E7D32;font-weight:700;">Uploaded</span>' );
                    if ( $badge_fn ) {
                        $badge_html .= $row( 'Filename', '<a href="' . esc_url( $badge_url ) . '" target="_blank"><code>' . esc_html( $badge_fn ) . '</code></a>' );
                    }
                    $badge_html .= $row( 'Size', floatval( $d['badge']['size'] ?? 0 ) . ' SVG units' );
                    $badge_html .= $row( 'Position', '(' . floatval( $d['badge']['x'] ?? 0 ) . ', ' . floatval( $d['badge']['y'] ?? 0 ) . ')' );
                } else {
                    $badge_html .= $row( 'Status', '<span style="color:#aaa;">Not added</span>' );
                }
                echo $section( 'Club badge', $badge_html );

                // Second badge spot (armbands club + charity/sponsor
                // orders). Only shown when it deviates from the default
                // "same badge in both positions".
                $b2 = $d['badge2'] ?? null;
                if ( is_array( $b2 ) && ( $b2['mode'] ?? 'same' ) !== 'same' ) {
                    $b2_html = '';
                    if ( ( $b2['mode'] ?? '' ) === 'none' ) {
                        $b2_html .= $row( 'Status', '<span style="color:#aaa;">Empty — customer removed the second badge</span>' );
                    } elseif ( ! empty( $b2['url'] ) ) {
                        $b2_html .= $row( 'Status', '<span style="color:#2E7D32;font-weight:700;">Different badge uploaded</span>' );
                        if ( ! empty( $b2['filename'] ) ) {
                            $b2_html .= $row( 'Filename', '<a href="' . esc_url( $b2['url'] ) . '" target="_blank"><code>' . esc_html( $b2['filename'] ) . '</code></a>' );
                        }
                    } else {
                        $b2_html .= $row( 'Status', 'Different badge chosen but no file received' );
                    }
                    echo $section( 'Second badge', $b2_html );
                }

                $colour_rows = '';
                if ( ! empty( $d['colours']['background']   ) ) $colour_rows .= $row( 'Background',  $swatch( $d['colours']['background'] ) );
                if ( ! empty( $d['colours']['pattern']      ) ) $colour_rows .= $row( 'Pattern',     $swatch( $d['colours']['pattern'] ) );
                if ( ! empty( $d['colours']['name_text']    ) ) $colour_rows .= $row( 'Name text',   $swatch( $d['colours']['name_text'] ) );
                if ( ! empty( $d['colours']['number_text'] ) && in_array( 'left_number', array_keys( $show_text ), true ) ) {
                    $colour_rows .= $row( 'Bottom text', $swatch( $d['colours']['number_text'] ) );
                }
                if ( $colour_rows ) echo $section( 'Colours', $colour_rows );

                if ( ! empty( $d['notes'] ) ) {
                    echo $section( 'Order notes',
                        '<tr><td colspan="2" style="padding:6px 0;font-size:12px;color:#1a1a1a;background:#fff;border:1px solid #eee;border-radius:4px;padding:8px 10px;">'
                        . nl2br( esc_html( $d['notes'] ) )
                        . '</td></tr>'
                    );
                }
                ?>
            </div>
        </div>

        <!-- Full-width design preview row — matches the shin-pad layout
             so production sees the same kind of snapshot for every
             product type. preview_url is the PNG snapshot the customiser
             uploaded just before add-to-cart. -->
        <?php if ( ! empty( $d['preview_url'] ) ) : ?>
            <div style="margin-top:6px;">
                <div style="font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.6px;padding-bottom:4px;margin-bottom:6px;border-bottom:1px solid #eee;">
                    Design preview
                </div>
                <a href="<?php echo esc_url( $d['preview_url'] ); ?>" target="_blank">
                    <img src="<?php echo esc_url( $d['preview_url'] ); ?>"
                         style="max-height:200px;max-width:100%;object-fit:contain;border:1px solid #eee;border-radius:4px;padding:4px;background:#fff;display:block;" />
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
