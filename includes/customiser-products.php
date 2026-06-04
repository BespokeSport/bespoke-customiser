<?php
/**
 * BEspoke Sport – Product-level Asset Setup
 *
 * Adds a "Product Setup" admin sub-page (under Customiser Designs) where the
 * site owner can upload the per-product Background image and Pad Base shape
 * for every product type registered in bespoke_get_product_types().
 *
 * Storage:
 *   - Files: /wp-content/uploads/bespoke-product-assets/{product}-{asset}.{ext}
 *   - Metadata: WP option `bespoke_customiser_product_assets`
 *     [
 *       'shinpads' => [
 *         'background_url' => '…', 'background_filename' => '…',
 *         'pad_base_url'   => '…', 'pad_base_filename'   => '…',
 *       ],
 *       …
 *     ]
 *
 * Files are uploaded via our own AJAX endpoint (bespoke_upload_product_asset)
 * so security plugins (Really Simple Security etc.) can't strip embedded
 * raster data from SVGs the way they do with the WP media library.
 *
 * Public API:
 *   bespoke_get_product_assets( $product_type )
 *     → array with 'background_url' and 'pad_base_url' (if set)
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-products.php
 * Included by:   bespoke-customiser.php (main plugin bootstrap)
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
   1. CONSTANTS & DIRECTORY SETUP
   ========================================================================= */

if ( ! defined( 'BESPOKE_PRODUCT_ASSETS_DIR' ) ) {
    define( 'BESPOKE_PRODUCT_ASSETS_DIR', wp_upload_dir()['basedir'] . '/bespoke-product-assets/' );
    define( 'BESPOKE_PRODUCT_ASSETS_URL', wp_upload_dir()['baseurl'] . '/bespoke-product-assets/' );
}

add_action( 'init', function() {
    if ( ! file_exists( BESPOKE_PRODUCT_ASSETS_DIR ) ) {
        wp_mkdir_p( BESPOKE_PRODUCT_ASSETS_DIR );
        @file_put_contents( BESPOKE_PRODUCT_ASSETS_DIR . '.htaccess', "Options -Indexes\n" );
    }
} );


/* =========================================================================
   2. ADMIN SUB-MENU (under Customiser Designs)
   ========================================================================= */

add_action( 'admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=bespoke_design',
        'Customiser Product Setup',
        'Product Setup',
        'manage_options',
        'bespoke-product-setup',
        'bespoke_render_product_setup_page'
    );
} );


/* =========================================================================
   3. AJAX UPLOAD ENDPOINT
   ========================================================================= */

add_action( 'wp_ajax_bespoke_upload_product_asset', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Not authorised', 403 );
    }
    if ( ! check_ajax_referer( 'bespoke_product_asset_upload', '_nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }

    $product_type = isset( $_POST['product_type'] ) ? sanitize_key( $_POST['product_type'] ) : '';
    $asset_type   = isset( $_POST['asset_type'] )   ? sanitize_key( $_POST['asset_type'] )   : '';

    if ( ! in_array( $asset_type, [ 'background', 'background_alt', 'pad_base', 'highlights', 'shadow', 'mask', 'mask_alt' ], true ) ) {
        wp_send_json_error( 'Invalid asset type' );
    }
    if ( ! function_exists( 'bespoke_get_product_types' )
         || ! array_key_exists( $product_type, bespoke_get_product_types() ) ) {
        wp_send_json_error( 'Invalid product type' );
    }
    if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'No file or upload error' );
    }

    $file = $_FILES['file'];
    $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'svg', 'png', 'jpg', 'jpeg' ], true ) ) {
        wp_send_json_error( 'Only .svg, .png, .jpg files are accepted' );
    }

    if ( ! file_exists( BESPOKE_PRODUCT_ASSETS_DIR ) ) {
        wp_mkdir_p( BESPOKE_PRODUCT_ASSETS_DIR );
    }

    // Remove ANY existing file for this product+asset combination
    // (handles ext change e.g. svg → png).
    $existing = glob( BESPOKE_PRODUCT_ASSETS_DIR . $product_type . '-' . $asset_type . '.*' );
    if ( is_array( $existing ) ) {
        foreach ( $existing as $f ) {
            @unlink( $f );
        }
    }

    $safe_name = $product_type . '-' . $asset_type . '.' . $ext;
    $target    = BESPOKE_PRODUCT_ASSETS_DIR . $safe_name;

    if ( ! @move_uploaded_file( $file['tmp_name'], $target ) ) {
        wp_send_json_error( 'Could not save file (check folder permissions on /wp-content/uploads/bespoke-product-assets/)' );
    }

    // Cache-buster query string. The filename pattern is fixed
    // ({product}-{asset}.{ext}) so a replacement always overwrites at
    // the same URL — and the customer's browser happily keeps serving
    // the old image from its HTTP cache. Appending ?v=<mtime> gives the
    // URL a fresh fingerprint per upload so the browser refetches.
    $mtime  = @filemtime( $target );
    $cb     = $mtime ? '?v=' . $mtime : '';
    $url    = BESPOKE_PRODUCT_ASSETS_URL . $safe_name . $cb;

    $assets = get_option( 'bespoke_customiser_product_assets', [] );
    if ( ! isset( $assets[ $product_type ] ) || ! is_array( $assets[ $product_type ] ) ) {
        $assets[ $product_type ] = [];
    }
    $assets[ $product_type ][ $asset_type . '_url' ]      = $url;
    $assets[ $product_type ][ $asset_type . '_filename' ] = $safe_name;
    update_option( 'bespoke_customiser_product_assets', $assets );

    wp_send_json_success( [
        'url'      => $url,
        'filename' => $safe_name,
        'size'     => filesize( $target ),
    ] );
} );


/* =========================================================================
   3b. PLACEMENT GEOMETRY  (admin "Save placement" editor)
   -------------------------------------------------------------------------
   Per-product-type default positions + sizes for the badge, name and number,
   set by an admin dragging them on the live customiser and clicking "Save
   placement". The front-end customiser reads these as each product's starting
   layout. Stored in option 'bespoke_customiser_product_geometry':
       [ 'gripsocks' => [ 'badgeL' => ['x'=>.., 'y'=>..], ..., 'badgeSize'=>.. ] ]
   ========================================================================= */

function bespoke_get_product_geometry( $product_type ) {
    $all = get_option( 'bespoke_customiser_product_geometry', [] );
    return ( is_array( $all ) && isset( $all[ $product_type ] ) && is_array( $all[ $product_type ] ) )
        ? $all[ $product_type ]
        : [];
}

add_action( 'wp_ajax_bespoke_save_product_geometry', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Not authorised', 403 );
    }
    if ( ! check_ajax_referer( 'bespoke_save_geometry', '_nonce', false ) ) {
        wp_send_json_error( 'Security check failed', 400 );
    }
    $product_type = isset( $_POST['product_type'] ) ? sanitize_key( $_POST['product_type'] ) : '';
    if ( ! function_exists( 'bespoke_get_product_types' )
         || ! array_key_exists( $product_type, bespoke_get_product_types() ) ) {
        wp_send_json_error( 'Unknown product type' );
    }

    $data = json_decode( isset( $_POST['geometry'] ) ? wp_unslash( $_POST['geometry'] ) : '', true );
    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'Invalid placement data' );
    }

    // Sanitise: clamp positions to a generous band around the 1200x1200
    // canvas; clamp sizes to the same ranges the customiser sliders allow.
    $clean = [];
    foreach ( [ 'badgeL', 'badgeR', 'nameL', 'nameR', 'numL', 'numR' ] as $key ) {
        if ( isset( $data[ $key ]['x'], $data[ $key ]['y'] ) ) {
            $clean[ $key ] = [
                'x' => max( -300, min( 1500, (float) $data[ $key ]['x'] ) ),
                'y' => max( -300, min( 1500, (float) $data[ $key ]['y'] ) ),
            ];
        }
    }
    foreach ( [ 'badgeSize' => [ 80, 320 ], 'nameSize' => [ 40, 140 ], 'numSize' => [ 60, 220 ] ] as $key => $r ) {
        if ( isset( $data[ $key ] ) ) {
            $clean[ $key ] = max( $r[0], min( $r[1], (int) $data[ $key ] ) );
        }
    }
    if ( empty( $clean ) ) {
        wp_send_json_error( 'Nothing to save' );
    }

    $all = get_option( 'bespoke_customiser_product_geometry', [] );
    if ( ! is_array( $all ) ) {
        $all = [];
    }
    $all[ $product_type ] = $clean;
    update_option( 'bespoke_customiser_product_geometry', $all );

    wp_send_json_success( [ 'saved' => $clean ] );
} );


/* =========================================================================
   4. ADMIN PAGE RENDERER
   ========================================================================= */

function bespoke_render_product_setup_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have permission to access this page.' );
    }

    // Handle delete (regular POST submission)
    if ( isset( $_POST['bespoke_delete_asset'] ) && check_admin_referer( 'bespoke_delete_asset_action' ) ) {
        $pt = isset( $_POST['product_type'] ) ? sanitize_key( $_POST['product_type'] ) : '';
        $at = isset( $_POST['asset_type'] )   ? sanitize_key( $_POST['asset_type'] )   : '';
        if ( $pt && in_array( $at, [ 'background', 'background_alt', 'pad_base', 'highlights', 'shadow', 'mask', 'mask_alt' ], true ) ) {
            $assets = get_option( 'bespoke_customiser_product_assets', [] );
            if ( isset( $assets[ $pt ][ $at . '_filename' ] ) ) {
                $file_path = BESPOKE_PRODUCT_ASSETS_DIR . $assets[ $pt ][ $at . '_filename' ];
                if ( file_exists( $file_path ) ) {
                    @unlink( $file_path );
                }
                unset( $assets[ $pt ][ $at . '_url' ], $assets[ $pt ][ $at . '_filename' ] );
                update_option( 'bespoke_customiser_product_assets', $assets );
                add_settings_error( 'bespoke_product_assets', 'deleted', 'Asset deleted.', 'updated' );
            }
        }
    }

    $product_types = function_exists( 'bespoke_get_product_types' ) ? bespoke_get_product_types() : [];
    $assets        = get_option( 'bespoke_customiser_product_assets', [] );
    $upload_nonce  = wp_create_nonce( 'bespoke_product_asset_upload' );

    settings_errors( 'bespoke_product_assets' );
    ?>
    <div class="wrap">
        <h1>Customiser Product Setup</h1>
        <p>For each product type, upload the <strong>Background</strong> image (static wallpaper behind the design — non-editable by the customer) and the <strong>Pad Base</strong> shape (the product silhouette — fills with the customer's chosen base colour).</p>
        <p><strong>Highlights</strong> and <strong>Shadow</strong> are optional transparent PNGs that sit on top of the background &amp; design for depth (e.g. a curved shin pad or cylindrical trophy). They're purely cosmetic — never editable or clickable — and sit beneath the badge / name / number so those stay crisp.</p>
        <p>These apply to <em>all designs</em> for that product. <code>.svg</code> is preferred for the pad base (so it can be cleanly recoloured); <code>.svg</code>, <code>.png</code> or <code>.jpg</code> work for the background. Highlights &amp; Shadow should be <code>.png</code> with transparency.</p>

        <p style="color:#666;"><strong>Background (Alt)</strong> is optional — used for products that ship in two variants (e.g. the Pennant comes With Frill or No Frill). When this is set on the Pennant product specifically, the customiser shows a Frill / No Frill toggle to the customer and renders whichever background they pick. Leave blank for products that only have one background.</p>
        <p style="color:#666;"><strong>Mask (on top, optional)</strong> sits ABOVE the badge and text layers. Use a PNG of "the background minus the product silhouette" — i.e. opaque everywhere except where the band / pad shape is. Anything the customer drags outside the band gets covered by the mask, creating the illusion that the badge wraps around the back. Provide a matching <strong>Mask (Alt)</strong> if you've set a Background (Alt) — otherwise the same mask is used for both variants and the cut-out won't match the alt band size.</p>

        <table class="widefat striped" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th style="width: 10%;">Product</th>
                    <th style="width: 13%;">Background (static)</th>
                    <th style="width: 13%;">Background (Alt)</th>
                    <th style="width: 13%;">Pad Base (tinted)</th>
                    <th style="width: 13%;">Highlights (on top)</th>
                    <th style="width: 13%;">Shadow (on top)</th>
                    <th style="width: 13%;">Mask (on top)</th>
                    <th style="width: 12%;">Mask (Alt)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $product_types as $key => $label ) :
                    $bg_url      = isset( $assets[ $key ]['background_url'] )          ? $assets[ $key ]['background_url']          : '';
                    $bg_filename = isset( $assets[ $key ]['background_filename'] )     ? $assets[ $key ]['background_filename']     : '';
                    $ba_url      = isset( $assets[ $key ]['background_alt_url'] )      ? $assets[ $key ]['background_alt_url']      : '';
                    $ba_filename = isset( $assets[ $key ]['background_alt_filename'] ) ? $assets[ $key ]['background_alt_filename'] : '';
                    $pb_url      = isset( $assets[ $key ]['pad_base_url'] )            ? $assets[ $key ]['pad_base_url']            : '';
                    $pb_filename = isset( $assets[ $key ]['pad_base_filename'] )       ? $assets[ $key ]['pad_base_filename']       : '';
                    $hl_url      = isset( $assets[ $key ]['highlights_url'] )          ? $assets[ $key ]['highlights_url']          : '';
                    $hl_filename = isset( $assets[ $key ]['highlights_filename'] )     ? $assets[ $key ]['highlights_filename']     : '';
                    $sd_url      = isset( $assets[ $key ]['shadow_url'] )              ? $assets[ $key ]['shadow_url']              : '';
                    $sd_filename = isset( $assets[ $key ]['shadow_filename'] )         ? $assets[ $key ]['shadow_filename']         : '';
                    $mk_url      = isset( $assets[ $key ]['mask_url'] )                ? $assets[ $key ]['mask_url']                : '';
                    $mk_filename = isset( $assets[ $key ]['mask_filename'] )           ? $assets[ $key ]['mask_filename']           : '';
                    $ma_url      = isset( $assets[ $key ]['mask_alt_url'] )            ? $assets[ $key ]['mask_alt_url']            : '';
                    $ma_filename = isset( $assets[ $key ]['mask_alt_filename'] )       ? $assets[ $key ]['mask_alt_filename']       : '';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $label ); ?></strong><br/>
                            <code style="font-size: 11px; color: #666;"><?php echo esc_html( $key ); ?></code>
                        </td>
                        <?php foreach ( [ 'background' => [ $bg_url, $bg_filename ], 'background_alt' => [ $ba_url, $ba_filename ], 'pad_base' => [ $pb_url, $pb_filename ], 'highlights' => [ $hl_url, $hl_filename ], 'shadow' => [ $sd_url, $sd_filename ], 'mask' => [ $mk_url, $mk_filename ], 'mask_alt' => [ $ma_url, $ma_filename ] ] as $atype => $info ) :
                            list( $url, $fn ) = $info;
                            ?>
                            <td>
                                <div class="bespoke-asset-cell" data-product="<?php echo esc_attr( $key ); ?>" data-asset="<?php echo esc_attr( $atype ); ?>">
                                    <?php if ( $url ) : ?>
                                        <div style="margin-bottom: 6px;">
                                            <a href="<?php echo esc_url( $url ); ?>" target="_blank">View ↗</a>
                                            &nbsp;<code style="font-size: 11px;"><?php echo esc_html( $fn ); ?></code>
                                        </div>
                                    <?php else : ?>
                                        <div style="margin-bottom: 6px; color: #999;"><em>Not set</em></div>
                                    <?php endif; ?>
                                    <button type="button" class="button bespoke-upload-btn">
                                        <?php echo $url ? 'Replace' : 'Upload'; ?>
                                    </button>
                                    <?php if ( $url ) : ?>
                                        <form method="post" style="display: inline; margin-left: 4px;" onsubmit="return confirm( 'Delete this asset?' );">
                                            <?php wp_nonce_field( 'bespoke_delete_asset_action' ); ?>
                                            <input type="hidden" name="product_type" value="<?php echo esc_attr( $key ); ?>" />
                                            <input type="hidden" name="asset_type"   value="<?php echo esc_attr( $atype ); ?>" />
                                            <input type="submit" name="bespoke_delete_asset" class="button button-link-delete" value="Delete" />
                                        </form>
                                    <?php endif; ?>
                                    <span class="bespoke-upload-msg" style="margin-left: 8px;"></span>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        jQuery( function( $ ) {
            var nonce = '<?php echo esc_js( $upload_nonce ); ?>';

            $( '.bespoke-upload-btn' ).on( 'click', function() {
                var $btn   = $( this );
                var $cell  = $btn.closest( '.bespoke-asset-cell' );
                var $msg   = $cell.find( '.bespoke-upload-msg' );
                var product = $cell.data( 'product' );
                var asset   = $cell.data( 'asset' );

                var input = document.createElement( 'input' );
                input.type   = 'file';
                input.accept = '.svg,.png,.jpg,.jpeg,image/svg+xml,image/png,image/jpeg';
                input.onchange = function() {
                    var file = input.files[0];
                    if ( ! file ) return;

                    var originalText = $btn.text();
                    $btn.text( 'Uploading…' ).prop( 'disabled', true );
                    $msg.text( '' );

                    var fd = new FormData();
                    fd.append( 'action',       'bespoke_upload_product_asset' );
                    fd.append( '_nonce',       nonce );
                    fd.append( 'product_type', product );
                    fd.append( 'asset_type',   asset );
                    fd.append( 'file',         file );

                    fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'include' } )
                        .then( function( r ) { return r.json(); } )
                        .then( function( res ) {
                            if ( res && res.success ) {
                                var sizeKb = Math.round( res.data.size / 1024 );
                                $msg.css( 'color', '#1d8348' ).text(
                                    '✓ Uploaded ' + res.data.filename + ' (' + sizeKb + ' KB) — reloading…'
                                );
                                setTimeout( function() { location.reload(); }, 800 );
                            } else {
                                var errMsg = ( res && res.data ) || 'unknown error';
                                $msg.css( 'color', '#a00' ).text( 'Upload failed: ' + errMsg );
                                $btn.text( originalText ).prop( 'disabled', false );
                            }
                        } )
                        .catch( function( err ) {
                            $msg.css( 'color', '#a00' ).text( 'Error: ' + err );
                            $btn.text( originalText ).prop( 'disabled', false );
                        } );
                };
                input.click();
            } );
        } );
        </script>
    </div>
    <?php
}


/* =========================================================================
   5. PUBLIC API (for renderer)
   ========================================================================= */

/**
 * Returns the uploaded asset URLs for a product type.
 *
 * @param string $product_type One of the keys from bespoke_get_product_types().
 * @return array Empty array if none set; otherwise keys 'background_url' and/or 'pad_base_url'.
 */
function bespoke_get_product_assets( $product_type ) {
    $assets = get_option( 'bespoke_customiser_product_assets', [] );
    return isset( $assets[ $product_type ] ) && is_array( $assets[ $product_type ] )
        ? $assets[ $product_type ]
        : [];
}
