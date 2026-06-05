<?php
/**
 * BEspoke Sport – Customiser AJAX Handlers
 *
 * Handles two requests fired by the customiser frontend:
 *   1. bespoke_upload_badge  – saves the club badge image to the WP media library
 *   2. bespoke_add_to_cart   – validates the design spec and adds the product to the WooCommerce cart
 *
 * Both actions work for logged-in AND guest (nopriv) users so that
 * customers don't need an account to place a bespoke order.
 *
 * File location: /wp-content/plugins/bespoke-sport/includes/customiser-ajax.php
 * This file is included by the main plugin bootstrap (bespoke-sport.php).
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
   HELPERS
   ========================================================================= */

/**
 * Read the per-layer pattern colours sent by the frontend.
 *
 * The customiser posts:
 *   bespoke_colour_pat_count = N  (how many pattern layers)
 *   bespoke_colour_pat_0     = #rrggbb  (layer 1)
 *   bespoke_colour_pat_1     = #rrggbb  (layer 2)
 *   ...
 *
 * Returns a numerically-indexed array of sanitised hex colours.
 * Falls back to [ bespoke_colour_pat ] (the legacy single value) if no
 * count was sent — so older browsers / cached pages still work.
 *
 * @return array<int,string>
 */
function bespoke_collect_pattern_colours() {

    $count = isset( $_POST['bespoke_colour_pat_count'] )
        ? intval( $_POST['bespoke_colour_pat_count'] )
        : 0;

    // Legacy fallback — no count field means an older customiser build.
    if ( $count < 1 ) {
        $legacy = sanitize_hex_color( $_POST['bespoke_colour_pat'] ?? '' );
        return $legacy ? [ $legacy ] : [];
    }

    // Cap at a sane upper bound to defend against malicious clients.
    $count = min( $count, 12 );

    $out = [];
    for ( $i = 0; $i < $count; $i++ ) {
        $val = sanitize_hex_color( $_POST[ 'bespoke_colour_pat_' . $i ] ?? '' );
        if ( $val ) {
            $out[ $i ] = $val;
        }
    }

    return $out;
}

/**
 * Read a single gradient from POST.
 *
 * Expects two fields:
 *   bespoke_gradient_{prefix}_from = '#rrggbb'
 *   bespoke_gradient_{prefix}_to   = '#rrggbb'
 *
 * Returns [ 'from' => '#xxx', 'to' => '#xxx' ] when both are valid hex,
 * or null when either is missing / invalid (= solid mode for that zone).
 *
 * @param string $prefix Field-name suffix, e.g. 'bg' or 'pat_0'.
 * @return array{from:string,to:string}|null
 */
function bespoke_read_gradient( $prefix ) {
    $from_raw = $_POST[ 'bespoke_gradient_' . $prefix . '_from' ] ?? '';
    $to_raw   = $_POST[ 'bespoke_gradient_' . $prefix . '_to'   ] ?? '';
    $from = sanitize_hex_color( wp_unslash( $from_raw ) );
    $to   = sanitize_hex_color( wp_unslash( $to_raw ) );
    if ( $from && $to ) {
        return [ 'from' => $from, 'to' => $to ];
    }
    return null;
}

/**
 * Read per-pattern-layer gradients, indexed numerically. Uses the same
 * pattern count as bespoke_collect_pattern_colours() so indices line up.
 * Trims trailing nulls so storage stays compact.
 *
 * @return array<int, array{from:string,to:string}|null>
 */
function bespoke_collect_pattern_gradients() {
    $count = isset( $_POST['bespoke_colour_pat_count'] )
        ? intval( $_POST['bespoke_colour_pat_count'] )
        : 0;
    $count = min( max( 0, $count ), 12 );

    $out = [];
    for ( $i = 0; $i < $count; $i++ ) {
        $out[ $i ] = bespoke_read_gradient( 'pat_' . $i );
    }
    // Drop trailing nulls so [null, null, {...}] stays full but
    // [{...}, null, null] becomes [{...}].
    while ( count( $out ) > 0 && $out[ count( $out ) - 1 ] === null ) {
        array_pop( $out );
    }
    return $out;
}


/* =========================================================================
   1. BADGE UPLOAD
   ========================================================================= */

add_action( 'wp_ajax_bespoke_upload_badge',        'bespoke_handle_badge_upload' );
add_action( 'wp_ajax_nopriv_bespoke_upload_badge', 'bespoke_handle_badge_upload' );

/**
 * Receives the club badge image POSTed from the customiser,
 * validates it, and saves it to the WordPress uploads folder.
 *
 * Expected POST fields:
 *   nonce  – WordPress nonce (action: bespoke_add_to_cart)
 *   badge  – the image file
 *
 * Returns JSON:
 *   success + { url, filename }   on success
 *   error   + message string      on failure
 */
function bespoke_handle_badge_upload() {

    // ── Security ────────────────────────────────────────────────────────────
    check_ajax_referer( 'bespoke_add_to_cart', 'nonce' );

    // ── File presence ────────────────────────────────────────────────────────
    if ( empty( $_FILES['badge'] ) || $_FILES['badge']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'No file received or upload error.' );
    }

    $file = $_FILES['badge'];

    // ── File size (max 5 MB) ─────────────────────────────────────────────────
    $max_bytes = 5 * 1024 * 1024;
    if ( $file['size'] > $max_bytes ) {
        wp_send_json_error( 'File too large. Maximum badge size is 5 MB.' );
    }

    // ── MIME type validation (check actual file content, not just extension) ─
    $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    $finfo     = finfo_open( FILEINFO_MIME_TYPE );
    $real_mime = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );

    if ( ! in_array( $real_mime, $allowed_mimes, true ) ) {
        wp_send_json_error( 'Invalid file type. Please upload a PNG, JPG, GIF, WebP, or SVG image.' );
    }

    // ── Save to the dedicated bespoke-badges upload folder ───────────────────
    // This folder is created on plugin activation (see bespoke-customiser.php).
    // Keeping badges separate makes them easy to find and back up.
    $safe_name = 'badge-' . time() . '-' . sanitize_file_name( $file['name'] );
    $dest_path = BESPOKE_UPLOAD_DIR . $safe_name;
    $dest_url  = BESPOKE_UPLOAD_URL . $safe_name;

    if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
        wp_send_json_error( 'Could not save the badge image. Please try again.' );
    }

    // ── Return the public URL and filename to the frontend ───────────────────
    wp_send_json_success( [
        'url'      => $dest_url,
        'filename' => $safe_name,
    ] );
}


/* =========================================================================
   1b. PREVIEW SVG UPLOAD
   ========================================================================= */

add_action( 'wp_ajax_bespoke_upload_preview',        'bespoke_handle_preview_upload' );
add_action( 'wp_ajax_nopriv_bespoke_upload_preview', 'bespoke_handle_preview_upload' );

/**
 * Receives the SVG preview snapshot uploaded during add-to-cart,
 * saves it, and returns the URL for use as the cart thumbnail.
 */
function bespoke_handle_preview_upload() {

    check_ajax_referer( 'bespoke_add_to_cart', 'nonce' );

    if ( empty( $_FILES['preview'] ) || $_FILES['preview']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'No preview file received.' );
    }

    $file = $_FILES['preview'];

    // Read the raw content
    $content = file_get_contents( $file['tmp_name'] );

    // ── PNG branch ──────────────────────────────────────────────────────────
    // Detect PNG by magic bytes (first 4 bytes = \x89PNG). If it's a PNG, we
    // just save it directly — no XML parsing or namespace work needed. This
    // is the preferred path: the frontend renders the SVG to canvas and
    // uploads the PNG, sidestepping every SVG quirk.
    if ( substr( $content, 0, 4 ) === "\x89PNG" ) {
        $safe_name = 'preview-' . time() . '-' . wp_generate_uuid4() . '.png';
        $dest_path = BESPOKE_UPLOAD_DIR . $safe_name;
        $dest_url  = BESPOKE_UPLOAD_URL . $safe_name;
        if ( file_put_contents( $dest_path, $content ) === false ) {
            wp_send_json_error( 'Could not save preview PNG.' );
        }
        wp_send_json_success( [ 'url' => $dest_url ] );
        return;
    }

    // ── SVG fallback ────────────────────────────────────────────────────────
    // If canvas conversion failed client-side, we still get an SVG blob.
    // Continue with the original SVG cleanup pipeline.
    if ( strpos( $content, '<svg' ) === false ) {
        wp_send_json_error( 'Invalid preview file.' );
    }

    // ── Normalise SVG so it renders standalone ───────────────────────────────
    // The customiser uses xlink:href on <image> elements. When the browser's
    // XMLSerializer captures the live SVG and we save it as a standalone file,
    // the xmlns:xlink declaration is sometimes lost. Also, the customiser sets
    // BOTH xlink:href AND href on the same element (legacy browser support),
    // so naive "convert xlink:href → href" produces duplicate attributes that
    // fail with "Attribute href redefined".
    //
    // Use DOMDocument for safe, attribute-aware parsing:
    //   1. Pre-declare the xlink namespace so the parser accepts the file
    //   2. For every element with xlink:href, drop xlink:href and keep
    //      plain href (set one if missing)
    //   3. Save back — no duplicates, no missing namespace

    // Pre-declare xmlns / xmlns:xlink so DOMDocument can parse legally
    if ( ! preg_match( '/<svg\b[^>]*\bxmlns:xlink\s*=/i', $content ) ) {
        $content = preg_replace(
            '/<svg\b/',
            '<svg xmlns:xlink="http://www.w3.org/1999/xlink"',
            $content,
            1
        );
    }
    if ( ! preg_match( '/<svg\b[^>]*\bxmlns\s*=\s*["\']http:\/\/www\.w3\.org\/2000\/svg["\']/i', $content ) ) {
        $content = preg_replace(
            '/<svg\b/',
            '<svg xmlns="http://www.w3.org/2000/svg"',
            $content,
            1
        );
    }

    // Primary fix: dedup duplicate href attributes. For every opening tag
    // containing BOTH xlink:href and a plain href, drop the xlink:href.
    // Match whole opening tags (up to first '>'). This works on real SVG —
    // the previous regex broke on URLs containing slashes inside attribute
    // values because [^>\/]+ stopped at the first "/" in "http://".
    $content = preg_replace_callback(
        '/<[^>]+>/',
        function( $m ) {
            $tag = $m[0];
            // Skip closing tags and comments
            if ( strpos( $tag, '</' ) === 0 || strpos( $tag, '<!' ) === 0 ) {
                return $tag;
            }
            $has_xlink_href = preg_match( '/\bxlink:href\s*=/', $tag );
            // (?<![\w:]) — "href" NOT preceded by a word char or colon
            // (so xlink:href won't match)
            $has_plain_href = preg_match( '/(?<![\w:])href\s*=/', $tag );

            if ( $has_xlink_href && $has_plain_href ) {
                $tag = preg_replace( '/\s*xlink:href\s*=\s*(?:"[^"]*"|\'[^\']*\')/', '', $tag );
            }
            return $tag;
        },
        $content
    );

    // Secondary: try DOMDocument cleanup for any remaining xlink:href elements
    // (those that had only xlink:href, no plain href — convert them).
    libxml_use_internal_errors( true );
    $doc    = new DOMDocument();
    $loaded = $doc->loadXML( $content );
    libxml_clear_errors();
    libxml_use_internal_errors( false );

    if ( $loaded ) {
        $xpath = new DOMXPath( $doc );
        $xpath->registerNamespace( 'x', 'http://www.w3.org/1999/xlink' );
        foreach ( $xpath->query( '//*[@x:href]' ) as $node ) {
            $xlink_value = $node->getAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
            $node->removeAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
            if ( ! $node->hasAttribute( 'href' ) ) {
                $node->setAttribute( 'href', $xlink_value );
            }
        }
        $content = $doc->saveXML();
    }

    $safe_name = 'preview-' . time() . '-' . wp_generate_uuid4() . '.svg';
    $dest_path = BESPOKE_UPLOAD_DIR . $safe_name;
    $dest_url  = BESPOKE_UPLOAD_URL . $safe_name;

    // Write the (possibly patched) content directly rather than move_uploaded_file
    if ( file_put_contents( $dest_path, $content ) === false ) {
        wp_send_json_error( 'Could not save preview image.' );
    }

    wp_send_json_success( [ 'url' => $dest_url ] );
}


/* =========================================================================
   2. ADD TO CART
   ========================================================================= */

add_action( 'wp_ajax_bespoke_add_to_cart',        'bespoke_handle_add_to_cart' );
add_action( 'wp_ajax_nopriv_bespoke_add_to_cart', 'bespoke_handle_add_to_cart' );

/**
 * Validates the design specification sent from the customiser,
 * packages it into a single JSON meta blob, and adds the product
 * to the WooCommerce cart.
 *
 * Using a single JSON blob (rather than many individual meta keys) means
 * adding new product types in future (armbands, bottles, grip socks…)
 * requires no changes to this handler or the database schema —
 * only the 'type' value and 'data' structure change.
 *
 * Expected POST fields: see inline comments below.
 *
 * Returns JSON:
 *   success + { cart_url }   on success
 *   error   + message string on failure
 */
function bespoke_handle_add_to_cart() {

    // ── Security ─────────────────────────────────────────────────────────────
    check_ajax_referer( 'bespoke_add_to_cart', 'nonce' );

    // ── Ensure WooCommerce session and cart are ready ─────────────────────────
    //
    // On some page loads the WC session hasn't started by the time this AJAX
    // request arrives (particularly on pages that don't normally load cart JS).
    // Forcing the session here prevents an intermittent "could not add to cart"
    // error on first use.
    //
    if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
    }

    if ( null === WC()->cart ) {
        wc_load_cart();
    }

    // ── Product validation ────────────────────────────────────────────────────
    $product_id = intval( $_POST['product_id'] ?? 0 );

    if ( ! $product_id ) {
        wp_send_json_error( 'No product specified.' );
    }

    $product = wc_get_product( $product_id );

    if ( ! $product || ! $product->is_purchasable() ) {
        wp_send_json_error( 'This product is not available.' );
    }

    // ── Build the customisation data structure ────────────────────────────────
    //
    // Everything lives under a single 'bespoke_customisation' key.
    // The 'type' field tells the display and print handlers which product
    // this is, so they can render it correctly.
    //
    // Product type drives which admin / cart renderer handles the
    // saved data. Comes from the customiser frontend (mirrors the
    // shortcode's product_type attribute). Sanitised to a key so an
    // attacker can't slip arbitrary strings into our renderer lookup.
    $product_type = sanitize_key( $_POST['bespoke_type'] ?? 'shinpads' );
    // Allow-list against the registered product types so a typo or
    // tampered POST doesn't end up creating an unknown 'type' value
    // that no renderer handles.
    $valid_types = function_exists( 'bespoke_get_product_types' )
        ? array_keys( bespoke_get_product_types() )
        : [ 'shinpads' ];
    if ( ! in_array( $product_type, $valid_types, true ) ) {
        $product_type = 'shinpads';
    }

    $customisation = [

        'type' => $product_type,

        'data' => [

            // ── Size ──────────────────────────────────────────────────────────
            'size'   => sanitize_text_field( $_POST['bespoke_size']   ?? '' ),

            // ── Design pattern ────────────────────────────────────────────────
            'design' => sanitize_text_field( $_POST['bespoke_design'] ?? '' ),

            // ── Per-pad personalisation ───────────────────────────────────────
            'left'  => [
                'name'   => sanitize_text_field( $_POST['bespoke_name_left']   ?? '' ),
                'number' => sanitize_text_field( $_POST['bespoke_number_left'] ?? '' ),
            ],
            'right' => [
                'name'   => sanitize_text_field( $_POST['bespoke_name_right']   ?? '' ),
                'number' => sanitize_text_field( $_POST['bespoke_number_right'] ?? '' ),
            ],

            // ── Colours ───────────────────────────────────────────────────────
            //
            // 'pattern'           — legacy single pattern colour (= layer 1).
            //                       Kept for backward compatibility with old
            //                       code that only knows about a single
            //                       pattern colour.
            // 'patterns'          — full per-layer solid array. Index 0 =
            //                       pattern layer 1, index 1 = layer 2, ...
            //                       For multi-pattern designs (e.g. Tramline)
            //                       every layer the customer tinted is stored
            //                       here so production can reproduce the
            //                       exact colour scheme.
            // 'bg_gradient'       — null when the pad background is solid;
            //                       { from, to } when the customer enabled
            //                       gradient mode on Pad Background.
            // 'pattern_gradients' — per-layer array (same indexing as
            //                       patterns). null at each index = solid;
            //                       { from, to } = gradient.
            'colours' => [
                'background'        => sanitize_hex_color( $_POST['bespoke_colour_bg']   ?? '' ),
                'pattern'           => sanitize_hex_color( $_POST['bespoke_colour_pat']  ?? '' ),
                'patterns'          => bespoke_collect_pattern_colours(),
                'name_text'         => sanitize_hex_color( $_POST['bespoke_colour_name'] ?? '' ),
                'number_text'       => sanitize_hex_color( $_POST['bespoke_colour_num']  ?? '' ),
                'bg_gradient'       => bespoke_read_gradient( 'bg' ),
                'pattern_gradients' => bespoke_collect_pattern_gradients(),
            ],

            // ── Fonts ─────────────────────────────────────────────────────────
            // wp_unslash strips the backslashes WordPress adds to $_POST data,
            // so font strings containing quotes (e.g. '"Arial Black", Arial,
            // sans-serif') don't end up displayed as '\"Arial Black\"' in
            // the admin spec.
            'fonts' => [
                'name'   => sanitize_text_field( wp_unslash( $_POST['bespoke_font_name']   ?? '' ) ),
                'number' => sanitize_text_field( wp_unslash( $_POST['bespoke_font_number'] ?? '' ) ),
            ],

            // ── Club badge ────────────────────────────────────────────────────
            // The URL was returned by the bespoke_upload_badge call earlier.
            // If no badge was uploaded these will be empty strings.
            'badge' => [
                'url'      => esc_url_raw( $_POST['bespoke_badge_url']      ?? '' ),
                'filename' => sanitize_file_name( $_POST['bespoke_badge_filename'] ?? '' ),
                'x'        => floatval( $_POST['bespoke_badge_x']    ?? 0 ),
                'y'        => floatval( $_POST['bespoke_badge_y']    ?? 0 ),
                'size'     => floatval( $_POST['bespoke_badge_size'] ?? 0 ),
            ],

            // ── Element positions on the pad (SVG coordinate space) ───────────
            // Stored so a production SVG can be regenerated server-side later.
            'positions' => [
                'name' => [
                    'x'    => floatval( $_POST['bespoke_name_x']    ?? 0 ),
                    'y'    => floatval( $_POST['bespoke_name_y']    ?? 0 ),
                    'size' => floatval( $_POST['bespoke_name_size'] ?? 0 ),
                ],
                'number' => [
                    'x'    => floatval( $_POST['bespoke_number_x']    ?? 0 ),
                    'y'    => floatval( $_POST['bespoke_number_y']    ?? 0 ),
                    'size' => floatval( $_POST['bespoke_number_size'] ?? 0 ),
                ],
            ],

            // ── Order notes ───────────────────────────────────────────────────
            'notes' => sanitize_textarea_field( $_POST['bespoke_notes'] ?? '' ),

            // ── Background variant ────────────────────────────────────────────
            // Customer's pick from a per-product variant toggle:
            //   Pennant  → "With Frill" / "No Frill"
            //   Armband  → "5cm band"   / "8cm band"
            // 'default' = primary Background, 'alt' = Background (Alt). The
            // _label is the human-readable string shown in the order email
            // and admin order screen so production sees real units, not flags.
            'bg_variant'       => ( ( $_POST['bespoke_bg_variant'] ?? '' ) === 'alt' ) ? 'alt' : 'default',
            'bg_variant_label' => sanitize_text_field( $_POST['bespoke_bg_variant_label'] ?? '' ),

            // ── Hidden elements ──────────────────────────────────────────────
            // CSV of badge / name / number positions the customer chose to
            // remove via the in-preview X (e.g. "badgeR,nameR"). Production
            // reads this so e.g. a single-badge armband design knows which
            // side to leave blank.
            'hidden_elements' => array_values( array_filter( array_map(
                'sanitize_key',
                explode( ',', wp_unslash( $_POST['bespoke_hidden_elements'] ?? '' ) )
            ), function( $k ) {
                return in_array( $k, [ 'badgeL', 'badgeR', 'nameL', 'nameR', 'numL', 'numR' ], true );
            } ) ),

            // ── Per-element rotation (Stage 3) ────────────────────────────────
            // Degrees, 1dp. JS only sends a field when the rotation is
            // non-zero (≥0.1), so missing keys stay at 0 for production.
            'rotation' => array_filter( [
                'badgeL' => isset( $_POST['bespoke_rot_badgeL'] ) ? (float) $_POST['bespoke_rot_badgeL'] : 0,
                'badgeR' => isset( $_POST['bespoke_rot_badgeR'] ) ? (float) $_POST['bespoke_rot_badgeR'] : 0,
                'nameL'  => isset( $_POST['bespoke_rot_nameL']  ) ? (float) $_POST['bespoke_rot_nameL']  : 0,
                'nameR'  => isset( $_POST['bespoke_rot_nameR']  ) ? (float) $_POST['bespoke_rot_nameR']  : 0,
                'numL'   => isset( $_POST['bespoke_rot_numL']   ) ? (float) $_POST['bespoke_rot_numL']   : 0,
                'numR'   => isset( $_POST['bespoke_rot_numR']   ) ? (float) $_POST['bespoke_rot_numR']   : 0,
            ], function( $v ) { return abs( $v ) >= 0.1; } ),

            // ── Cart thumbnail ────────────────────────────────────────────────
            // SVG preview uploaded just before add-to-cart. Shown in cart
            // instead of the default product image so the customer can see
            // exactly what they designed.
            'preview_url' => esc_url_raw( $_POST['bespoke_preview_url'] ?? '' ),

        ],
    ];

    // ── Resolve variation (if the product is variable) ──────────────────────
    // Match the customer's chosen size + (when set) bg_variant_label
    // against the product's variation attributes. Simple products skip
    // this entirely and fall through with variation_id 0.
    $variation_id    = 0;
    $variation_attrs = [];
    if ( $product->is_type( 'variable' ) ) {
        $customer_values = array_filter( [
            $customisation['data']['size']             ?? '',
            $customisation['data']['bg_variant_label'] ?? '',
        ] );
        $match = bespoke_match_variation( $product, $customer_values );
        if ( ! $match ) {
            wp_send_json_error(
                'No matching product variation for "' .
                esc_html( implode( ' / ', $customer_values ) ) .
                '". Set up a variation in WooCommerce that matches the customer\'s chosen options.'
            );
        }
        $variation_id    = $match['id'];
        $variation_attrs = $match['attributes'];
    }

    // ── Add to WooCommerce cart ────────────────────────────────────────────────
    //
    // The customisation array is passed as cart item data.
    // WooCommerce stores this in the session and carries it through to
    // the order when the customer checks out.
    //
    $cart_item_key = WC()->cart->add_to_cart(
        $product_id,
        1,                // Quantity – always 1 pair
        $variation_id,    // Variation ID – 0 for simple products
        $variation_attrs, // Variation attribute map
        [
            'bespoke_customisation' => $customisation,
        ]
    );

    if ( ! $cart_item_key ) {
        $notices = wc_get_notices( 'error' );
        $message = ! empty( $notices ) ? implode( ' | ', array_column( $notices, 'notice' ) ) : 'add_to_cart returned false — no WC error captured.';
        wp_send_json_error( wp_strip_all_tags( $message ) );
    }

    wp_send_json_success( [
        'cart_url' => wc_get_cart_url(),
    ] );
}


/* =========================================================================
   VARIATION MATCHING — used by bespoke_handle_add_to_cart for variable
   WC products. Finds the variation whose attribute values match the
   customer's choices in the customiser (size + bg_variant_label).
   ========================================================================= */

/**
 * Returns a flexible-match check between two attribute values. WC
 * variation values often look slightly different from what the
 * customiser sends ("5cm" vs "5cm band", "small" vs "Small"), so we
 * normalise case + whitespace and accept a substring match in either
 * direction. Exact and case-insensitive matches still win first.
 *
 * @param string $customer_value  e.g. "24cm" or "5cm band"
 * @param string $variation_value e.g. "24cm" or "5cm"
 * @return bool
 */
function bespoke_attr_values_match( $customer_value, $variation_value ) {
    // Strip ALL non-alphanumeric characters and lowercase for a
    // forgiving comparison. Handles:
    //   "5cm Band"   ↔ "5cm band"   (label case mismatch)
    //   "5cm-band"   ↔ "5cm Band"   (slug vs label — WC stores global
    //                                taxonomy attributes as the term
    //                                slug, per-product attributes as
    //                                the typed value)
    //   "24cm"       ↔ "24 cm"      (whitespace inside the value)
    $normalize = function( $v ) {
        return strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $v ) );
    };
    $c = $normalize( $customer_value );
    $v = $normalize( $variation_value );
    if ( $c === '' || $v === '' ) return false;
    if ( $c === $v ) return true;
    // Substring either way — covers "5cm band" customer / "5cm" variation
    // OR "5cm" customer / "5cm band" variation.
    return ( strpos( $c, $v ) !== false || strpos( $v, $c ) !== false );
}

/**
 * Find the variation whose attribute values all match one of the
 * customer's chosen values (size, bg_variant_label, etc.).
 *
 * WC variation attribute arrays look like:
 *   [ 'attribute_pa_size' => '24cm', 'attribute_pa_thickness' => '5cm' ]
 * An empty value means "Any …" — matches anything.
 *
 * @param WC_Product_Variable $product
 * @param string[]            $customer_values  e.g. [ '24cm', '5cm band' ]
 * @return array|null  [ 'id' => int, 'attributes' => [] ]
 */
function bespoke_match_variation( $product, $customer_values ) {
    if ( ! $product instanceof WC_Product_Variable ) return null;
    foreach ( $product->get_available_variations() as $variation ) {
        $ok = true;
        foreach ( $variation['attributes'] as $attr_key => $attr_value ) {
            if ( $attr_value === '' ) continue; // "Any" — matches anything
            $found = false;
            foreach ( $customer_values as $cust ) {
                if ( bespoke_attr_values_match( $cust, $attr_value ) ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) { $ok = false; break; }
        }
        if ( $ok ) {
            return [
                'id'         => (int) $variation['variation_id'],
                'attributes' => $variation['attributes'],
            ];
        }
    }
    return null;
}
