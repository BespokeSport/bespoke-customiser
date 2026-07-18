<?php
/**
 * BEspoke Sport – Design Management System
 *
 * Registers a 'bespoke_design' custom post type so you can add, edit
 * and remove designs through the WordPress admin without touching code.
 *
 * Each design stores:
 *   - A display name (the post title)
 *   - Which products it applies to (checkboxes)
 *   - A PNG thumbnail (shown in the customiser picker)
 *   - An SVG file URL (the actual design artwork, uses --col-1/--col-2 etc.)
 *   - Repeatable colour layers (label, CSS variable, default colour)
 *   - Display order (lower = appears first)
 *   - Active/inactive toggle
 *
 * The AJAX endpoint bespoke_get_designs is called by customiser.html
 * on page load to fetch the live design catalogue for the current product.
 *
 * File location: /wp-content/plugins/bespoke-sport/includes/customiser-designs.php
 * Included by:   bespoke-customiser.php (main plugin bootstrap)
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
   1. REGISTER CUSTOM POST TYPE
   ========================================================================= */

add_action( 'init', 'bespoke_register_design_post_type' );

function bespoke_register_design_post_type() {
    register_post_type( 'bespoke_design', [
        'labels' => [
            'name'               => 'Customiser Designs',
            'singular_name'      => 'Design',
            'add_new'            => 'Add New Design',
            'add_new_item'       => 'Add New Design',
            'edit_item'          => 'Edit Design',
            'new_item'           => 'New Design',
            'view_item'          => 'View Design',
            'search_items'       => 'Search Designs',
            'not_found'          => 'No designs found',
            'not_found_in_trash' => 'No designs in trash',
            'menu_name'          => 'Customiser Designs',
        ],
        'public'          => false,  // Not accessible on the front end as a post
        'show_ui'         => true,   // Show in WP admin
        'show_in_menu'    => true,   // Show as its own top-level menu item
        'show_in_rest'    => false,  // Not needed via REST API
        'supports'        => [ 'title' ],
        'menu_icon'       => 'dashicons-art',
        'menu_position'   => 56,
        'capability_type' => 'post',
    ] );
}


/* =========================================================================
   2. PRODUCT LIST
   Keys must match the product_type values used in [bespoke_customiser] shortcodes.
   Add new products here as you build their customisers.
   ========================================================================= */

function bespoke_get_product_types() {
    return [
        'shinpads'           => 'Shin Pads',
        'gripsocks'          => 'Grip Socks',
        'armbands'           => 'Captain Armbands',
        'double_sided_armbands' => 'Double Sided Captain Armbands',
        'armbands_predesign' => 'Pre-designed Armbands',
        'shinpad_sleeves'    => 'Shin Pad Sleeves',
        'bottles'            => 'Bottles',
        'pennant'            => 'Pennant',
        'corner_flags'       => 'Corner Flags',
        'award_gamechanger'  => 'Game Changer Award',
        'award_plate'        => 'Plate Trophy Award',
        'award_glassblock'   => 'Glassblock Trophy',
        'player_cards'       => 'Player Cards',
    ];
}


/* =========================================================================
   2b. ADMIN ASSETS
   Enqueue the WordPress media uploader on the design edit screen so the
   thumbnail / SVG upload buttons can open the Media Library.
   Also allow SVG uploads (restricted to manage_options users).
   ========================================================================= */

add_action( 'admin_enqueue_scripts', function( $hook ) {
    global $post;
    if ( ( $hook === 'post.php' || $hook === 'post-new.php' )
         && isset( $post->post_type ) && $post->post_type === 'bespoke_design' ) {
        wp_enqueue_media();
        // Needed by the drag-to-reorder handle on the Colour Layers table.
        wp_enqueue_script( 'jquery-ui-sortable' );
    }
} );


/* =========================================================================
   2c. CUSTOM SVG UPLOAD ENDPOINT
   Bypasses the WP media library entirely so security plugins (Really Simple
   Security, etc.) can't strip the embedded base64 raster data from the SVG
   on upload. Saves the file straight to /wp-content/uploads/bespoke-designs/.
   Only admins (manage_options) can hit this endpoint.
   ========================================================================= */

if ( ! defined( 'BESPOKE_DESIGNS_DIR' ) ) {
    define( 'BESPOKE_DESIGNS_DIR', wp_upload_dir()['basedir'] . '/bespoke-designs/' );
    define( 'BESPOKE_DESIGNS_URL', wp_upload_dir()['baseurl'] . '/bespoke-designs/' );
}

add_action( 'init', function() {
    if ( ! file_exists( BESPOKE_DESIGNS_DIR ) ) {
        wp_mkdir_p( BESPOKE_DESIGNS_DIR );
        @file_put_contents( BESPOKE_DESIGNS_DIR . '.htaccess',
            "Options -Indexes\n" .
            "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)\$\">\n" .
            "    Require all denied\n" .
            "</FilesMatch>\n"
        );
    }
} );

add_action( 'wp_ajax_bespoke_upload_layer_file', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Not authorised', 403 );
    }
    if ( ! check_ajax_referer( 'bespoke_design_svg_upload', '_nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'No file or upload error' );
    }
    $file = $_FILES['file'];
    $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'svg', 'png', 'jpg', 'jpeg' ], true ) ) {
        wp_send_json_error( 'Only .svg, .png, .jpg files are accepted' );
    }
    if ( ! file_exists( BESPOKE_DESIGNS_DIR ) ) {
        wp_mkdir_p( BESPOKE_DESIGNS_DIR );
    }
    $safe   = sanitize_file_name( $file['name'] );
    $base   = pathinfo( $safe, PATHINFO_FILENAME );
    $target = BESPOKE_DESIGNS_DIR . $safe;
    $counter = 1;
    while ( file_exists( $target ) ) {
        $target = BESPOKE_DESIGNS_DIR . $base . '-' . $counter . '.' . $ext;
        $counter++;
    }
    // Sanitise SVG before writing — admin upload, but the resulting
    // file is publicly accessible at BESPOKE_DESIGNS_URL, so a stored
    // <script>/onload payload would execute in the site origin when an
    // admin (or any visitor opening the design's pattern URL) loads it.
    if ( $ext === 'svg' && function_exists( 'bespoke_sanitise_svg' ) ) {
        $raw   = file_get_contents( $file['tmp_name'] );
        $clean = bespoke_sanitise_svg( $raw );
        if ( $clean === '' ) {
            wp_send_json_error( 'SVG could not be processed safely.' );
        }
        if ( file_put_contents( $target, $clean ) === false ) {
            wp_send_json_error( 'Could not save file (check folder permissions on /wp-content/uploads/bespoke-designs/)' );
        }
    } else {
        if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
            wp_send_json_error( 'Could not save file (check folder permissions on /wp-content/uploads/bespoke-designs/)' );
        }
    }
    wp_send_json_success( [
        'url'      => BESPOKE_DESIGNS_URL . basename( $target ),
        'filename' => basename( $target ),
        'size'     => filesize( $target ),
    ] );
} );


add_action( 'wp_ajax_bespoke_upload_design_svg', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Not authorised', 403 );
    }
    if ( ! check_ajax_referer( 'bespoke_design_svg_upload', '_nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    if ( empty( $_FILES['svg'] ) || $_FILES['svg']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'No file or upload error' );
    }
    $file = $_FILES['svg'];
    $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    if ( $ext !== 'svg' ) {
        wp_send_json_error( 'Only .svg files allowed' );
    }
    if ( ! file_exists( BESPOKE_DESIGNS_DIR ) ) {
        wp_mkdir_p( BESPOKE_DESIGNS_DIR );
    }

    // Sanitised, dash-style filename (no spaces or special characters).
    $safe   = sanitize_file_name( $file['name'] );
    $base   = pathinfo( $safe, PATHINFO_FILENAME );
    $target = BESPOKE_DESIGNS_DIR . $safe;
    $counter = 1;
    while ( file_exists( $target ) ) {
        $target = BESPOKE_DESIGNS_DIR . $base . '-' . $counter . '.svg';
        $counter++;
    }

    // Sanitise SVG before writing (admin upload, but the result is
    // publicly served, so stored <script>/onload would execute).
    if ( function_exists( 'bespoke_sanitise_svg' ) ) {
        $raw   = file_get_contents( $file['tmp_name'] );
        $clean = bespoke_sanitise_svg( $raw );
        if ( $clean === '' ) {
            wp_send_json_error( 'SVG could not be processed safely.' );
        }
        if ( file_put_contents( $target, $clean ) === false ) {
            wp_send_json_error( 'Could not save file (check folder permissions on /wp-content/uploads/bespoke-designs/)' );
        }
    } else {
        if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
            wp_send_json_error( 'Could not save file (check folder permissions on /wp-content/uploads/bespoke-designs/)' );
        }
    }

    wp_send_json_success( [
        'url'      => BESPOKE_DESIGNS_URL . basename( $target ),
        'filename' => basename( $target ),
        'size'     => filesize( $target ),
    ] );
} );

// Allow SVG uploads for admins (needed so the design SVG can be picked from
// the Media Library). Hardened: only users who can manage_options.
add_filter( 'upload_mimes', function( $mimes ) {
    if ( current_user_can( 'manage_options' ) ) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
} );

// WordPress 5.0.1+ re-checks MIME via fileinfo and rejects SVGs because
// they're plain text. This filter accepts an SVG whose extension is .svg.
add_filter( 'wp_check_filetype_and_ext', function( $data, $file, $filename, $mimes ) {
    if ( ! current_user_can( 'manage_options' ) ) return $data;
    $ext = pathinfo( $filename, PATHINFO_EXTENSION );
    if ( strtolower( $ext ) === 'svg' ) {
        $data['type'] = 'image/svg+xml';
        $data['ext']  = 'svg';
    }
    return $data;
}, 10, 4 );


/* =========================================================================
   3. META BOXES
   ========================================================================= */

add_action( 'add_meta_boxes', 'bespoke_design_add_meta_boxes' );

function bespoke_design_add_meta_boxes() {
    add_meta_box(
        'bespoke_design_details',
        'Design Details',
        'bespoke_design_details_cb',
        'bespoke_design',
        'normal',
        'high'
    );
    add_meta_box(
        'bespoke_design_files',
        'Design Files',
        'bespoke_design_files_cb',
        'bespoke_design',
        'normal',
        'high'
    );
    add_meta_box(
        'bespoke_design_layers',
        'Colour Layers',
        'bespoke_design_layers_cb',
        'bespoke_design',
        'normal',
        'high'
    );
}


/* ── 3a. DETAILS META BOX ── */

function bespoke_design_details_cb( $post ) {
    wp_nonce_field( 'bespoke_design_save', 'bespoke_design_nonce' );

    $active       = get_post_meta( $post->ID, '_bespoke_active',   true );
    $order        = get_post_meta( $post->ID, '_bespoke_order',    true );
    $products     = get_post_meta( $post->ID, '_bespoke_products', true );
    $active       = $active   === '' ? '1'  : $active;   // Default: active
    $order        = $order    === '' ? '10' : $order;    // Default: order 10
    $products     = is_array( $products ) ? $products : [];
    $product_list = bespoke_get_product_types();
    ?>
    <table class="form-table" style="margin-top:0;">
        <tr>
            <th style="width:160px;"><label for="bespoke_active">Active</label></th>
            <td>
                <label>
                    <input type="checkbox" name="bespoke_active" id="bespoke_active"
                           value="1" <?php checked( $active, '1' ); ?> />
                    Show this design in the customiser
                </label>
                <p class="description">Uncheck to temporarily hide this design without deleting it.</p>
            </td>
        </tr>
        <tr>
            <th><label for="bespoke_order">Display order</label></th>
            <td>
                <input type="number" name="bespoke_order" id="bespoke_order"
                       value="<?php echo esc_attr( $order ); ?>"
                       min="1" max="999" style="width:80px;" />
                <p class="description">Lower numbers appear first in the picker. Use 10, 20, 30… to leave room for reordering.</p>
            </td>
        </tr>
        <tr>
            <th>Products</th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">Products this design applies to</legend>
                    <?php foreach ( $product_list as $key => $label ) : ?>
                        <label style="display:inline-block;margin-right:20px;margin-bottom:8px;">
                            <input type="checkbox"
                                   name="bespoke_products[]"
                                   value="<?php echo esc_attr( $key ); ?>"
                                   <?php checked( in_array( $key, $products, true ) ); ?> />
                            <?php echo esc_html( $label ); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="description">Tick every product type this design can be used with.</p>
            </td>
        </tr>
    </table>
    <?php
}


/* ── 3b. FILES META BOX ── */

function bespoke_design_files_cb( $post ) {
    $thumb_id  = get_post_meta( $post->ID, '_bespoke_thumb_id', true );
    $svg_url   = get_post_meta( $post->ID, '_bespoke_svg_url',  true );
    $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
    ?>
    <table class="form-table" style="margin-top:0;">

        <!-- PNG Thumbnail -->
        <tr>
            <th style="width:160px;"><label>PNG thumbnail</label></th>
            <td>
                <div id="bespoke-thumb-preview" style="margin-bottom:10px;">
                    <?php if ( $thumb_url ) : ?>
                        <img src="<?php echo esc_url( $thumb_url ); ?>"
                             style="max-width:150px;max-height:150px;object-fit:contain;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9;" />
                    <?php endif; ?>
                </div>
                <input type="hidden" name="bespoke_thumb_id" id="bespoke_thumb_id"
                       value="<?php echo esc_attr( $thumb_id ); ?>" />
                <button type="button" class="button" id="bespoke-thumb-btn">
                    <?php echo $thumb_id ? 'Change thumbnail' : 'Upload thumbnail'; ?>
                </button>
                <?php if ( $thumb_id ) : ?>
                    <button type="button" class="button" id="bespoke-thumb-remove"
                            style="margin-left:6px;">Remove</button>
                <?php endif; ?>
                <p class="description">
                    Upload a PNG preview image — shown as the design card in the customiser picker.<br />
                    Ideally square, 400×400 px minimum.
                </p>
            </td>
        </tr>

        <!--
            Legacy single-SVG-per-design field removed 2026-05-22.
            New flow: upload pattern files per row in the "Colour Layers" box below.
            The bespoke_svg_url post meta is no longer read by the renderer.
        -->
        <tr style="display:none;">
            <td colspan="2">
                <input type="hidden" name="bespoke_svg_url" id="bespoke_svg_url" value="<?php echo esc_attr( $svg_url ); ?>" />
            </td>
        </tr>

    </table>

    <script>
    jQuery( function( $ ) {

        // ── Media library uploader for PNG thumbnail ──────────────────────────
        var mediaUploader;

        $( '#bespoke-thumb-btn' ).on( 'click', function( e ) {
            e.preventDefault();
            if ( mediaUploader ) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media( {
                title:    'Select Design Thumbnail',
                button:   { text: 'Use this image' },
                multiple: false,
                library:  { type: 'image' }
            } );
            mediaUploader.on( 'select', function() {
                var attachment = mediaUploader.state().get( 'selection' ).first().toJSON();
                $( '#bespoke_thumb_id' ).val( attachment.id );
                $( '#bespoke-thumb-preview' ).html(
                    '<img src="' + attachment.url + '" style="max-width:150px;max-height:150px;' +
                    'object-fit:contain;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9;" />'
                );
                $( '#bespoke-thumb-btn' ).text( 'Change thumbnail' );
            } );
            mediaUploader.open();
        } );

        // ── Remove thumbnail ──────────────────────────────────────────────────
        $( document ).on( 'click', '#bespoke-thumb-remove', function( e ) {
            e.preventDefault();
            $( '#bespoke_thumb_id' ).val( '' );
            $( '#bespoke-thumb-preview' ).html( '' );
            $( '#bespoke-thumb-btn' ).text( 'Upload thumbnail' );
            $( this ).remove();
        } );

        // ── Custom SVG uploader (bypasses WP media library + security plugins) ─
        // We use our own AJAX endpoint so the embedded base64 raster data in
        // designs isn't stripped by Really Simple Security / SG Security.
        var svgUploadNonce = '<?php echo esc_js( wp_create_nonce( "bespoke_design_svg_upload" ) ); ?>';

        $( '#bespoke-svg-btn' ).on( 'click', function( e ) {
            e.preventDefault();
            var fileInput = document.createElement( 'input' );
            fileInput.type    = 'file';
            fileInput.accept  = '.svg,image/svg+xml';
            fileInput.onchange = function() {
                var file = fileInput.files[0];
                if ( ! file ) return;

                var $btn = $( '#bespoke-svg-btn' );
                var originalText = $btn.text();
                $btn.text( 'Uploading…' ).prop( 'disabled', true );

                var formData = new FormData();
                formData.append( 'action',  'bespoke_upload_design_svg' );
                formData.append( '_nonce',  svgUploadNonce );
                formData.append( 'svg',     file );

                fetch( ajaxurl, {
                    method: 'POST',
                    body:   formData,
                    credentials: 'include'
                } )
                .then( function( r ) { return r.json(); } )
                .then( function( res ) {
                    $btn.prop( 'disabled', false );
                    if ( res && res.success ) {
                        $( '#bespoke_svg_url' ).val( res.data.url );
                        $btn.text( 'Change SVG' );
                        // Show success badge
                        var sizeKb = Math.round( res.data.size / 1024 );
                        $btn.after(
                            '<span class="bespoke-svg-msg" style="margin-left:10px;color:#1d8348;">' +
                            '✓ Uploaded: ' + res.data.filename + ' (' + sizeKb + ' KB)</span>'
                        );
                        setTimeout(function(){ $( '.bespoke-svg-msg' ).fadeOut(); }, 6000);
                    } else {
                        var msg = ( res && res.data ) || 'unknown error';
                        $btn.text( originalText );
                        alert( 'SVG upload failed: ' + msg );
                    }
                } )
                .catch( function( err ) {
                    $btn.prop( 'disabled', false ).text( originalText );
                    alert( 'Upload error: ' + err );
                } );
            };
            fileInput.click();
        } );

        // ── Remove SVG ────────────────────────────────────────────────────────
        $( document ).on( 'click', '#bespoke-svg-remove', function( e ) {
            e.preventDefault();
            $( '#bespoke_svg_url' ).val( '' );
            $( '#bespoke-svg-btn' ).text( 'Upload SVG' );
            $( this ).remove();
        } );

    } );
    </script>
    <?php
}


/* ── 3c. COLOUR LAYERS META BOX ── */

function bespoke_design_layers_cb( $post ) {
    $layers = get_post_meta( $post->ID, '_bespoke_layers', true );
    if ( ! is_array( $layers ) || empty( $layers ) ) {
        // Default two layers to get people started
        $layers = [
            [ 'label' => 'Pad background', 'default' => '#feef00' ],
            [ 'label' => 'Pattern',        'default' => '#211d33' ],
        ];
    }
    ?>
    <div style="margin-bottom:14px;padding:12px;background:#f0f6fc;border-left:4px solid #2271b1;">
        <p style="margin:0 0 6px 0;"><strong>Each layer = one colour zone the customer can recolour.</strong></p>
        <ul style="margin:0 0 0 18px;list-style:disc;">
            <li><strong>Layer 1</strong> = the pad colour picker shown to the customer. <strong>Leave the file blank</strong> — it uses the shared <em>Pad Base</em> from <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bespoke_design&page=bespoke-product-setup' ) ); ?>">Product Setup</a>. Only upload a file here if this specific design needs a custom pad shape.</li>
            <li><strong>Layer 2+</strong> = the actual pattern overlays for THIS design (e.g. distressed texture, stripes). <strong>A file is required</strong> for each. Each is tinted with the customer's colour picker for that zone.</li>
        </ul>
        <p style="margin:6px 0 0 0;color:#555;font-size:12px;">The shared <strong>Background</strong> (static wallpaper) is also set in Product Setup — no per-design file needed.</p>
    </div>

    <table class="widefat" id="bespoke-layers-table" style="margin-bottom:12px;">
        <thead>
            <tr>
                <th style="width:32px;text-align:center;padding:8px 4px;"><span class="dashicons dashicons-menu" title="Drag to reorder" style="opacity:.6;"></span></th>
                <th style="width:32px;">#</th>
                <th style="width:22%;">Layer label <span style="font-weight:400;color:#888;">(shown to customer)</span></th>
                <th style="width:140px;">Default colour</th>
                <th style="width:120px;">Customer editable</th>
                <th>Pattern file</th>
                <th style="width:80px;">Remove</th>
            </tr>
        </thead>
        <tbody id="bespoke-layers-body">
            <?php foreach ( $layers as $i => $layer ) :
                $n         = $i + 1;
                $file_url  = $layer['file_url']      ?? '';
                $file_name = $layer['file_filename'] ?? '';
                // Default to editable (=== '1') when key is missing — existing
                // designs created before this feature should stay editable.
                $editable  = ( ! isset( $layer['editable'] ) || $layer['editable'] === '1' ) ? '1' : '0';
            ?>
            <tr class="bespoke-layer-row" style="background:#fff;" data-layer-idx="<?php echo $i; ?>">
                <td style="vertical-align:middle;text-align:center;padding:8px 4px;">
                    <span class="bespoke-layer-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
                </td>
                <td style="vertical-align:middle;font-weight:700;color:#888;"><?php echo $n; ?></td>
                <td>
                    <input type="text"
                           name="bespoke_layers[<?php echo $i; ?>][label]"
                           value="<?php echo esc_attr( $layer['label'] ?? '' ); ?>"
                           placeholder="e.g. Pad background"
                           style="width:100%;" />
                    <small class="bespoke-css-var-hint" style="color:#aaa;">CSS variable: <code>--col-<?php echo $n; ?></code></small>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="color"
                               name="bespoke_layers[<?php echo $i; ?>][default]"
                               value="<?php echo esc_attr( $layer['default'] ?? '#000000' ); ?>"
                               style="width:48px;height:32px;border:1px solid #ddd;border-radius:4px;cursor:pointer;" />
                        <input type="text"
                               class="bespoke-hex-display"
                               value="<?php echo esc_attr( $layer['default'] ?? '#000000' ); ?>"
                               maxlength="7"
                               style="width:80px;font-family:monospace;" />
                    </div>
                    <input type="text"
                           name="bespoke_layers[<?php echo $i; ?>][colours]"
                           value="<?php echo esc_attr( is_array( $layer['colours'] ?? null ) ? implode( ', ', $layer['colours'] ) : '' ); ?>"
                           placeholder="#feef00, #211d33 (leave blank for full picker)"
                           style="width:100%;margin-top:6px;font-family:monospace;font-size:11px;box-sizing:border-box;" />
                    <small style="color:#888;display:block;margin-top:2px;line-height:1.3;font-size:11px;">Comma‑separated hex codes</small>
                </td>
                <td style="vertical-align:middle;">
                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
                        <input type="hidden" name="bespoke_layers[<?php echo $i; ?>][editable]" value="0" />
                        <input type="checkbox"
                               class="bespoke-layer-editable"
                               name="bespoke_layers[<?php echo $i; ?>][editable]"
                               value="1"
                               <?php checked( $editable, '1' ); ?> />
                        <span>Editable</span>
                    </label>
                    <p style="margin:4px 0 0 0;color:#888;font-size:11px;line-height:1.3;">Off = paints on the pad but the customer can't change its colour.</p>
                </td>
                <td>
                    <div class="bespoke-layer-file-cell">
                        <input type="hidden"
                               class="bespoke-layer-file-url"
                               name="bespoke_layers[<?php echo $i; ?>][file_url]"
                               value="<?php echo esc_attr( $file_url ); ?>" />
                        <input type="hidden"
                               class="bespoke-layer-file-name"
                               name="bespoke_layers[<?php echo $i; ?>][file_filename]"
                               value="<?php echo esc_attr( $file_name ); ?>" />
                        <span class="bespoke-layer-file-display" style="display:inline-block;min-width:140px;">
                            <?php if ( $file_url ) : ?>
                                <code style="font-size:11px;"><?php echo esc_html( $file_name ?: basename( $file_url ) ); ?></code>
                                &nbsp;<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" style="font-size:11px;">View ↗</a>
                            <?php else : ?>
                                <em style="color:#aaa;font-size:12px;"><?php echo $i === 0 ? 'Uses product Pad Base' : 'No file'; ?></em>
                            <?php endif; ?>
                        </span>
                        <button type="button" class="button bespoke-layer-upload-btn">
                            <?php echo $file_url ? 'Replace' : 'Upload'; ?>
                        </button>
                        <?php if ( $file_url ) : ?>
                            <button type="button" class="button button-link-delete bespoke-layer-file-remove">×</button>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <button type="button" class="button bespoke-remove-layer"
                            style="color:#a00;">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <button type="button" class="button button-secondary" id="bespoke-add-layer">
        + Add colour layer
    </button>

    <style>
        /* Drag-handle visuals on the colour-layers table */
        .bespoke-layer-drag-handle           { cursor: grab; color: #999; font-size: 18px; transition: color .15s; }
        .bespoke-layer-drag-handle:hover     { color: #2271b1; }
        .ui-sortable-helper                  { background: #fff !important; box-shadow: 0 6px 18px rgba(0,0,0,.12); }
        .ui-sortable-helper .bespoke-layer-drag-handle { cursor: grabbing; color: #2271b1; }
        tr.bespoke-layer-placeholder         { visibility: visible !important; background: #f0f6fc !important; outline: 2px dashed #2271b1; height: 60px; }
        tr.bespoke-layer-placeholder td      { padding: 0 !important; border: 0; }
    </style>

    <script>
    jQuery( function( $ ) {

        // ── Sync hex text input ↔ colour picker ───────────────────────────────
        $( document ).on( 'input', 'input[type="color"]', function() {
            $( this ).siblings( '.bespoke-hex-display' ).val( $( this ).val() );
        } );
        $( document ).on( 'input', '.bespoke-hex-display', function() {
            var hex = $( this ).val();
            if ( /^#[0-9A-Fa-f]{6}$/.test( hex ) ) {
                $( this ).siblings( 'input[type="color"]' ).val( hex );
            }
        } );

        // ── Add new layer row ─────────────────────────────────────────────────
        $( '#bespoke-add-layer' ).on( 'click', function() {
            var rows = $( '#bespoke-layers-body .bespoke-layer-row' ).length;
            var idx  = rows;
            var num  = rows + 1;
            var html = '<tr class="bespoke-layer-row" style="background:#fff;" data-layer-idx="' + idx + '">'
                + '<td style="vertical-align:middle;text-align:center;padding:8px 4px;">'
                + '<span class="bespoke-layer-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>'
                + '</td>'
                + '<td style="vertical-align:middle;font-weight:700;color:#888;">' + num + '</td>'
                + '<td>'
                + '<input type="text" name="bespoke_layers[' + idx + '][label]" value="" '
                + 'placeholder="e.g. Pattern" style="width:100%;" />'
                + '<small class="bespoke-css-var-hint" style="color:#aaa;">CSS variable: <code>--col-' + num + '</code></small>'
                + '</td>'
                + '<td>'
                + '<div style="display:flex;align-items:center;gap:8px;">'
                + '<input type="color" name="bespoke_layers[' + idx + '][default]" value="#000000" '
                + 'style="width:48px;height:32px;border:1px solid #ddd;border-radius:4px;cursor:pointer;" />'
                + '<input type="text" class="bespoke-hex-display" value="#000000" maxlength="7" '
                + 'style="width:80px;font-family:monospace;" />'
                + '</div>'
                + '<input type="text" name="bespoke_layers[' + idx + '][colours]" value="" '
                + 'placeholder="#feef00, #211d33 (leave blank for full picker)" '
                + 'style="width:100%;margin-top:6px;font-family:monospace;font-size:11px;box-sizing:border-box;" />'
                + '<small style="color:#888;display:block;margin-top:2px;line-height:1.3;font-size:11px;">Comma‑separated hex codes</small>'
                + '</td>'
                + '<td style="vertical-align:middle;">'
                + '<label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">'
                + '<input type="hidden" name="bespoke_layers[' + idx + '][editable]" value="0" />'
                + '<input type="checkbox" class="bespoke-layer-editable" name="bespoke_layers[' + idx + '][editable]" value="1" checked />'
                + '<span>Editable</span>'
                + '</label>'
                + '<p style="margin:4px 0 0 0;color:#888;font-size:11px;line-height:1.3;">Off = paints on the pad but the customer can\'t change its colour.</p>'
                + '</td>'
                + '<td>'
                + '<div class="bespoke-layer-file-cell">'
                + '<input type="hidden" class="bespoke-layer-file-url" name="bespoke_layers[' + idx + '][file_url]" value="" />'
                + '<input type="hidden" class="bespoke-layer-file-name" name="bespoke_layers[' + idx + '][file_filename]" value="" />'
                + '<span class="bespoke-layer-file-display" style="display:inline-block;min-width:140px;">'
                + '<em style="color:#aaa;font-size:12px;">No file</em>'
                + '</span>'
                + '<button type="button" class="button bespoke-layer-upload-btn">Upload</button>'
                + '</div>'
                + '</td>'
                + '<td><button type="button" class="button bespoke-remove-layer" '
                + 'style="color:#a00;">Remove</button></td>'
                + '</tr>';
            $( '#bespoke-layers-body' ).append( html );
            renumberRows();
        } );

        // ── Drag-to-reorder colour layers ─────────────────────────────────────
        // Uses jQuery UI Sortable (already enqueued on this admin screen).
        // After a drop, renumberRows re-writes the data-layer-idx, the visible
        // #, the CSS-variable hint, and re-indexes every input's name="…[N]…"
        // so the new order saves cleanly via the existing save_post handler.
        if ( $.fn.sortable ) {
            $( '#bespoke-layers-body' ).sortable( {
                handle:      '.bespoke-layer-drag-handle',
                placeholder: 'bespoke-layer-placeholder',
                axis:        'y',
                forcePlaceholderSize: true,
                helper: function( e, tr ) {
                    // Preserve cell widths during drag so the row doesn't collapse.
                    tr.children().each( function() { $( this ).width( $( this ).width() ); } );
                    return tr;
                },
                update: function() { renumberRows(); }
            } );
        }

        // ── Layer file upload ─────────────────────────────────────────────────
        var layerFileNonce = '<?php echo esc_js( wp_create_nonce( "bespoke_design_svg_upload" ) ); ?>';

        $( document ).on( 'click', '.bespoke-layer-upload-btn', function() {
            var $btn  = $( this );
            var $cell = $btn.closest( '.bespoke-layer-file-cell' );
            var $row  = $btn.closest( '.bespoke-layer-row' );
            var idx   = $row.attr( 'data-layer-idx' );

            var input = document.createElement( 'input' );
            input.type   = 'file';
            input.accept = '.svg,.png,.jpg,.jpeg,image/svg+xml,image/png,image/jpeg';
            input.onchange = function() {
                var file = input.files[0];
                if ( ! file ) return;
                var originalText = $btn.text();
                $btn.text( 'Uploading…' ).prop( 'disabled', true );

                var fd = new FormData();
                fd.append( 'action', 'bespoke_upload_layer_file' );
                fd.append( '_nonce', layerFileNonce );
                fd.append( 'file',   file );

                fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'include' } )
                    .then( function( r ) { return r.json(); } )
                    .then( function( res ) {
                        $btn.prop( 'disabled', false );
                        if ( res && res.success ) {
                            var sizeKb = Math.round( res.data.size / 1024 );
                            $cell.find( '.bespoke-layer-file-url'  ).val( res.data.url );
                            $cell.find( '.bespoke-layer-file-name' ).val( res.data.filename );
                            $cell.find( '.bespoke-layer-file-display' ).html(
                                '<code style="font-size:11px;">' + res.data.filename + '</code>' +
                                ' &nbsp;<a href="' + res.data.url + '" target="_blank" style="font-size:11px;">View ↗</a>' +
                                ' <small style="color:#1d8348;">(' + sizeKb + ' KB)</small>'
                            );
                            $btn.text( 'Replace' );
                            if ( ! $cell.find( '.bespoke-layer-file-remove' ).length ) {
                                $btn.after( '<button type="button" class="button button-link-delete bespoke-layer-file-remove">×</button>' );
                            }
                        } else {
                            $btn.text( originalText );
                            alert( 'Upload failed: ' + ( ( res && res.data ) || 'unknown error' ) );
                        }
                    } )
                    .catch( function( err ) {
                        $btn.prop( 'disabled', false ).text( originalText );
                        alert( 'Upload error: ' + err );
                    } );
            };
            input.click();
        } );

        // ── Remove a layer's file ─────────────────────────────────────────────
        $( document ).on( 'click', '.bespoke-layer-file-remove', function() {
            var $btn  = $( this );
            var $cell = $btn.closest( '.bespoke-layer-file-cell' );
            var $row  = $btn.closest( '.bespoke-layer-row' );
            var idx   = $row.attr( 'data-layer-idx' );

            $cell.find( '.bespoke-layer-file-url'  ).val( '' );
            $cell.find( '.bespoke-layer-file-name' ).val( '' );
            $cell.find( '.bespoke-layer-file-display' ).html(
                '<em style="color:#aaa;font-size:12px;">' +
                ( idx === '0' ? 'Uses product Pad Base' : 'No file' ) +
                '</em>'
            );
            $cell.find( '.bespoke-layer-upload-btn' ).text( 'Upload' );
            $btn.remove();
        } );

        // ── Remove a layer row ────────────────────────────────────────────────
        $( document ).on( 'click', '.bespoke-remove-layer', function() {
            if ( $( '#bespoke-layers-body .bespoke-layer-row' ).length <= 1 ) {
                alert( 'You must have at least one colour layer.' );
                return;
            }
            $( this ).closest( 'tr' ).remove();
            renumberRows();
        } );

        // ── Renumber rows after add / remove / drag-reorder ───────────────────
        function renumberRows() {
            $( '#bespoke-layers-body .bespoke-layer-row' ).each( function( i ) {
                var n     = i + 1;
                var $row  = $( this );
                $row.attr( 'data-layer-idx', i );
                // Visible "#" column is the second td now (first is the drag handle).
                $row.find( 'td' ).eq( 1 ).text( n );
                // Only update the CSS-variable hint, NOT the helper text below
                // the Allowed colours field (which is also a <small> in the row).
                $row.find( 'small.bespoke-css-var-hint' )
                    .html( 'CSS variable: <code>--col-' + n + '</code>' );
                // Re-index name attributes so POST data is a clean 0-based array.
                // Covers <input type="color">, <input type="text">, hidden URL /
                // filename inputs, the editable hidden + checkbox, and the
                // colours list — anything starting bespoke_layers[N].
                $row.find( 'input[name^="bespoke_layers"]' ).each( function() {
                    $( this ).attr( 'name', $( this ).attr( 'name' ).replace( /\[\d+\]/, '[' + i + ']' ) );
                } );
            } );
        }

    } );
    </script>
    <?php
}


/* =========================================================================
   4. SAVE META
   ========================================================================= */

add_action( 'save_post_bespoke_design', 'bespoke_design_save_meta', 10, 2 );

function bespoke_design_save_meta( $post_id, $post ) {

    // ── Security checks ───────────────────────────────────────────────────────
    if ( ! isset( $_POST['bespoke_design_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['bespoke_design_nonce'], 'bespoke_design_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // ── Active flag ───────────────────────────────────────────────────────────
    update_post_meta( $post_id, '_bespoke_active',
        isset( $_POST['bespoke_active'] ) ? '1' : '0'
    );

    // ── Display order ─────────────────────────────────────────────────────────
    update_post_meta( $post_id, '_bespoke_order',
        absint( $_POST['bespoke_order'] ?? 10 )
    );

    // ── Product assignment ────────────────────────────────────────────────────
    $raw_products = isset( $_POST['bespoke_products'] ) ? (array) $_POST['bespoke_products'] : [];
    $valid_keys   = array_keys( bespoke_get_product_types() );
    $products     = array_filter( $raw_products, fn( $p ) => in_array( $p, $valid_keys, true ) );
    update_post_meta( $post_id, '_bespoke_products', array_values( $products ) );

    // ── Thumbnail attachment ID ───────────────────────────────────────────────
    update_post_meta( $post_id, '_bespoke_thumb_id',
        absint( $_POST['bespoke_thumb_id'] ?? 0 )
    );

    // ── SVG URL ───────────────────────────────────────────────────────────────
    update_post_meta( $post_id, '_bespoke_svg_url',
        esc_url_raw( $_POST['bespoke_svg_url'] ?? '' )
    );

    // ── Colour layers ─────────────────────────────────────────────────────────
    $raw_layers = isset( $_POST['bespoke_layers'] ) ? (array) $_POST['bespoke_layers'] : [];
    $layers     = [];
    foreach ( $raw_layers as $layer ) {
        $label    = sanitize_text_field( $layer['label']         ?? '' );
        $default  = sanitize_hex_color(  $layer['default']       ?? '#000000' );
        $file_url = esc_url_raw(         $layer['file_url']      ?? '' );
        $file_fn  = sanitize_file_name(  $layer['file_filename'] ?? '' );
        // The "Customer editable" checkbox ships with a paired hidden input
        // (value="0") so that an unchecked box still posts a value. PHP keeps
        // the LAST value when two POST keys collide → checked = "1", off = "0".
        $editable = ( isset( $layer['editable'] ) && $layer['editable'] === '1' ) ? '1' : '0';
        // Parse the optional "Allowed colours" list — comma-separated hex
        // codes (#FEEF00, FF0000, etc.). Each entry is normalised to a
        // 6-digit uppercase hex with the # prefix. Invalid entries dropped.
        $colours_raw = sanitize_text_field( $layer['colours'] ?? '' );
        $colours     = [];
        foreach ( explode( ',', $colours_raw ) as $c ) {
            $c = trim( $c );
            if ( $c === '' ) continue;
            if ( $c[0] !== '#' ) $c = '#' . $c;
            if ( preg_match( '/^#[0-9a-fA-F]{3}$/', $c ) ) {
                $c = '#' . $c[1] . $c[1] . $c[2] . $c[2] . $c[3] . $c[3];
            }
            if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ) {
                $colours[] = strtoupper( $c );
            }
        }
        if ( $label ) {
            $entry = [
                'label'   => $label,
                'default' => $default ?: '#000000',
            ];
            if ( $file_url ) {
                $entry['file_url']      = $file_url;
                $entry['file_filename'] = $file_fn;
            }
            if ( ! empty( $colours ) ) {
                $entry['colours'] = $colours;
            }
            // Only persist the editable key when it's explicitly "off" —
            // designs created before this feature shipped have no key and
            // should continue to default to editable on the front end.
            if ( $editable === '0' ) {
                $entry['editable'] = '0';
            }
            $layers[] = $entry;
        }
    }
    update_post_meta( $post_id, '_bespoke_layers', $layers );
}


/* =========================================================================
   5. ADMIN LIST TABLE — extra columns
   ========================================================================= */

add_filter( 'manage_bespoke_design_posts_columns', 'bespoke_design_columns' );

function bespoke_design_columns( $columns ) {
    $new = [];
    foreach ( $columns as $key => $val ) {
        $new[ $key ] = $val;
        if ( $key === 'title' ) {
            $new['thumbnail'] = 'Thumbnail';
            $new['products']  = 'Products';
            $new['layers']    = 'Layers';
            $new['order']     = 'Order';
            $new['active']    = 'Active';
        }
    }
    return $new;
}

add_action( 'manage_bespoke_design_posts_custom_column', 'bespoke_design_column_content', 10, 2 );

function bespoke_design_column_content( $column, $post_id ) {
    switch ( $column ) {

        case 'thumbnail':
            $thumb_id = get_post_meta( $post_id, '_bespoke_thumb_id', true );
            if ( $thumb_id ) {
                echo wp_get_attachment_image( $thumb_id, [ 48, 48 ], false, [
                    'style' => 'width:48px;height:48px;object-fit:contain;border:1px solid #eee;border-radius:3px;'
                ] );
            } else {
                echo '<span style="color:#bbb;">—</span>';
            }
            break;

        case 'products':
            $products     = (array) get_post_meta( $post_id, '_bespoke_products', true );
            $product_list = bespoke_get_product_types();
            $labels       = array_map( fn( $k ) => $product_list[ $k ] ?? $k, $products );
            echo $labels
                ? esc_html( implode( ', ', $labels ) )
                : '<span style="color:#bbb;">None assigned</span>';
            break;

        case 'layers':
            $layers = (array) get_post_meta( $post_id, '_bespoke_layers', true );
            echo count( $layers ) . ' layer' . ( count( $layers ) !== 1 ? 's' : '' );
            break;

        case 'order':
            echo esc_html( get_post_meta( $post_id, '_bespoke_order', true ) ?: '10' );
            break;

        case 'active':
            $active = get_post_meta( $post_id, '_bespoke_active', true );
            $active = $active === '' ? '1' : $active;
            echo $active === '1'
                ? '<span style="color:#2E7D32;font-weight:600;">✓ Yes</span>'
                : '<span style="color:#aaa;">No</span>';
            break;
    }
}


/* =========================================================================
   5b. ADMIN LIST — drag-to-reorder + Quick Edit Order
   Two complementary ways to change a design's display order without having
   to open the design itself:
     - Drag handle in the leftmost column → drop to reposition rows
     - Quick Edit panel exposes the Order field for inline edits
   ========================================================================= */

/**
 * When viewing the designs list with no explicit sort, sort by display
 * order ascending — otherwise drag-and-drop doesn't visually match what
 * gets saved. ALSO applies the "filter by product" dropdown below if
 * the admin picked a product type from it.
 */
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'bespoke_design' ) {
        return;
    }

    // ── Product filter ────────────────────────────────────────────────
    // Dropdown in the table top-bar (see restrict_manage_posts below).
    // Matches designs whose _bespoke_products meta array contains the
    // chosen product key. LIKE on the serialised array is the standard
    // WP pattern for filtering a checkbox-multi field.
    if ( ! empty( $_GET['bespoke_product_filter'] ) ) {
        $pf = sanitize_key( wp_unslash( $_GET['bespoke_product_filter'] ) );
        if ( function_exists( 'bespoke_get_product_types' )
             && array_key_exists( $pf, bespoke_get_product_types() ) ) {
            $mq = $query->get( 'meta_query' );
            if ( ! is_array( $mq ) ) $mq = [];
            $mq[] = [
                'key'     => '_bespoke_products',
                'value'   => '"' . $pf . '"',
                'compare' => 'LIKE',
            ];
            $query->set( 'meta_query', $mq );
        }
    }

    // Respect any user-selected column sort.
    if ( $query->get( 'orderby' ) ) {
        return;
    }
    $query->set( 'meta_key', '_bespoke_order' );
    $query->set( 'orderby',  'meta_value_num' );
    $query->set( 'order',    'ASC' );
} );

/**
 * "Filter by product" dropdown shown in the designs list top-bar (next
 * to the Bulk-actions box). Submitting the form re-runs the listing
 * with ?bespoke_product_filter=… in the URL, which the pre_get_posts
 * hook above turns into a meta_query.
 */
add_action( 'restrict_manage_posts', function( $post_type ) {
    if ( $post_type !== 'bespoke_design' ) {
        return;
    }
    if ( ! function_exists( 'bespoke_get_product_types' ) ) {
        return;
    }
    $types   = bespoke_get_product_types();
    $current = isset( $_GET['bespoke_product_filter'] )
        ? sanitize_key( wp_unslash( $_GET['bespoke_product_filter'] ) )
        : '';
    ?>
    <select name="bespoke_product_filter" id="bespoke-product-filter" style="margin-right:6px;">
        <option value="">All products</option>
        <?php foreach ( $types as $key => $label ) : ?>
            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
                <?php echo esc_html( $label ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
} );

/**
 * Prepend a tiny drag-handle column to the designs list. Earlier filter
 * priority (5) so it lands at the very start, before the checkbox.
 */
add_filter( 'manage_bespoke_design_posts_columns', function( $columns ) {
    return array_merge(
        [ 'bespoke_drag' => '<span class="dashicons dashicons-menu" title="Drag to reorder" style="opacity:.6;"></span>' ],
        $columns
    );
}, 5 );

add_action( 'manage_bespoke_design_posts_custom_column', function( $column, $post_id ) {
    if ( $column === 'bespoke_drag' ) {
        echo '<span class="bespoke-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>';
    }
}, 10, 2 );

/**
 * Enqueue jQuery UI Sortable on the designs list page only.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    global $typenow;
    if ( $hook !== 'edit.php' || $typenow !== 'bespoke_design' ) {
        return;
    }
    wp_enqueue_script( 'jquery-ui-sortable' );
} );

/**
 * Drag-and-drop init + Quick Edit pre-fill script. Lives in the admin
 * footer of the designs list page so it has the table to attach to.
 */
add_action( 'admin_footer-edit.php', function() {
    global $typenow;
    if ( $typenow !== 'bespoke_design' ) {
        return;
    }
    $reorder_nonce = wp_create_nonce( 'bespoke_reorder_designs' );
    ?>
    <style>
        /* Drag-handle column visuals */
        .wp-list-table .column-bespoke_drag   { width: 32px; text-align: center; padding: 8px 4px !important; }
        .bespoke-drag-handle                  { cursor: grab; color: #999; font-size: 18px; transition: color .15s; }
        .bespoke-drag-handle:hover            { color: #2271b1; }
        .ui-sortable-helper                   { background: #fff !important; box-shadow: 0 6px 18px rgba(0,0,0,.12); }
        .ui-sortable-helper .bespoke-drag-handle { cursor: grabbing; color: #2271b1; }
        .ui-sortable-placeholder              { visibility: visible !important; background: #f0f6fc !important; outline: 2px dashed #2271b1; }
        .ui-sortable-placeholder td           { padding: 0 !important; }
        /* Subtle highlight that fades after a save */
        tr.bespoke-saved                      { background: #ecfff3 !important; transition: background 1.5s; }
    </style>
    <script>
    jQuery( function( $ ) {

        // ── 1. DRAG-TO-REORDER ─────────────────────────────────────────────
        var $tbody = $( '#the-list' );
        if ( $tbody.length ) {
            $tbody.sortable( {
                handle: '.bespoke-drag-handle',
                placeholder: 'ui-sortable-placeholder',
                axis: 'y',
                // Keep cell widths during drag so the row doesn't collapse.
                helper: function( e, tr ) {
                    tr.children().each( function() { $( this ).width( $( this ).width() ); } );
                    return tr;
                },
                update: function() {
                    var ids = $tbody.find( '> tr' ).map( function() {
                        var id = $( this ).attr( 'id' );
                        return id ? id.replace( 'post-', '' ) : null;
                    } ).get().filter( Boolean );
                    if ( ! ids.length ) return;

                    $tbody.css( 'opacity', 0.6 );

                    $.post( ajaxurl, {
                        action: 'bespoke_reorder_designs',
                        _nonce: '<?php echo esc_js( $reorder_nonce ); ?>',
                        order:  ids
                    }, function( res ) {
                        $tbody.css( 'opacity', 1 );
                        if ( ! res || ! res.success ) {
                            alert( 'Reorder failed: ' + ( res && res.data ? res.data : 'unknown error' ) );
                            return;
                        }
                        // Update visible Order column values to match what
                        // PHP just saved (so the column doesn't go stale
                        // until the user refreshes).
                        $tbody.find( '> tr' ).each( function( i ) {
                            var newOrder = ( i + 1 ) * 10;
                            $( this ).find( '.column-order' ).text( newOrder );
                            $( this ).addClass( 'bespoke-saved' );
                        } );
                        setTimeout( function() {
                            $tbody.find( '> tr' ).removeClass( 'bespoke-saved' );
                        }, 1500 );
                    } );
                }
            } );
        }

        // ── 2. QUICK EDIT — pre-fill the Order input ───────────────────────
        // WordPress's inline-edit JS doesn't know about our custom field,
        // so we wrap inlineEditPost.edit to copy the value from the row's
        // Order column into the Quick Edit form on open.
        if ( typeof inlineEditPost !== 'undefined' ) {
            var origInlineEdit = inlineEditPost.edit;
            inlineEditPost.edit = function( id ) {
                origInlineEdit.apply( this, arguments );
                var postId = ( typeof id === 'object' ) ? parseInt( this.getId( id ), 10 ) : id;
                if ( ! postId ) return;
                var current = ( $( '#post-' + postId + ' .column-order' ).text() || '' ).trim();
                $( '#edit-' + postId + ' input.bespoke-quick-order' ).val( current );
            };
        }
    } );
    </script>
    <?php
} );

/**
 * AJAX endpoint that re-saves _bespoke_order for the dragged sequence.
 * Each post in the visible list gets order = (index + 1) × 10 so the
 * sequence stays human-friendly with gaps for future inserts.
 */
add_action( 'wp_ajax_bespoke_reorder_designs', function() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Not authorised', 403 );
    }
    if ( ! check_ajax_referer( 'bespoke_reorder_designs', '_nonce', false ) ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }
    $ids = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];
    if ( empty( $ids ) ) {
        wp_send_json_error( 'No order data received' );
    }
    foreach ( $ids as $i => $id ) {
        if ( ! $id || get_post_type( $id ) !== 'bespoke_design' ) {
            continue;
        }
        update_post_meta( $id, '_bespoke_order', ( $i + 1 ) * 10 );
    }
    wp_send_json_success();
} );

/**
 * Render the Order field inside WordPress's Quick Edit panel. WordPress
 * fires this for each custom column we registered — we only want the
 * Order column.
 */
add_action( 'quick_edit_custom_box', function( $column_name, $post_type ) {
    if ( $post_type !== 'bespoke_design' || $column_name !== 'order' ) {
        return;
    }
    wp_nonce_field( 'bespoke_design_quick_edit', 'bespoke_quick_edit_nonce' );
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label>
                <span class="title">Display order</span>
                <span class="input-text-wrap">
                    <input type="number"
                           name="bespoke_order"
                           class="bespoke-quick-order"
                           value=""
                           min="1"
                           max="999"
                           style="width:80px;" />
                </span>
            </label>
            <p class="description" style="margin-top:4px;">Lower numbers appear first.</p>
        </div>
    </fieldset>
    <?php
}, 10, 2 );

/**
 * Save handler for the Quick Edit Order field. Uses its own nonce so it
 * doesn't conflict with the full-edit save handler (which expects a
 * different set of POST keys).
 */
add_action( 'save_post_bespoke_design', function( $post_id ) {
    if ( ! isset( $_POST['bespoke_quick_edit_nonce'] ) ) {
        return; // Not a Quick Edit submission
    }
    if ( ! wp_verify_nonce( $_POST['bespoke_quick_edit_nonce'], 'bespoke_design_quick_edit' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( isset( $_POST['bespoke_order'] ) ) {
        update_post_meta( $post_id, '_bespoke_order', absint( $_POST['bespoke_order'] ) );
    }
} );


/* =========================================================================
   6. AJAX ENDPOINT — bespoke_get_designs
   Called by customiser.html on page load to fetch the live design
   catalogue for the current product type.
   ========================================================================= */

add_action( 'wp_ajax_bespoke_get_designs',        'bespoke_ajax_get_designs' );
add_action( 'wp_ajax_nopriv_bespoke_get_designs', 'bespoke_ajax_get_designs' );

function bespoke_ajax_get_designs() {

    $product_type = sanitize_key( $_GET['product_type'] ?? '' );

    if ( ! $product_type ) {
        wp_send_json_error( 'No product type specified.' );
    }

    // Validate against known product types
    if ( ! array_key_exists( $product_type, bespoke_get_product_types() ) ) {
        wp_send_json_error( 'Invalid product type.' );
    }

    $args = [
        'post_type'      => 'bespoke_design',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => '_bespoke_active',
                'value' => '1',
            ],
            [
                'key'     => '_bespoke_products',
                'value'   => '"' . $product_type . '"',
                'compare' => 'LIKE',
            ],
        ],
        'meta_key' => '_bespoke_order',
        'orderby'  => 'meta_value_num',
        'order'    => 'ASC',
    ];

    $query   = new WP_Query( $args );
    $designs = [];

    foreach ( $query->posts as $post ) {
        $thumb_id  = get_post_meta( $post->ID, '_bespoke_thumb_id', true );
        $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
        $svg_url   = get_post_meta( $post->ID, '_bespoke_svg_url', true );
        $layers    = get_post_meta( $post->ID, '_bespoke_layers',  true );
        $layers    = is_array( $layers ) ? $layers : [];

        $designs[] = [
            'id'        => $post->ID,
            'name'      => $post->post_title,
            'thumb_url' => $thumb_url ?: '',
            'svg_url'   => $svg_url   ?: '',
            'layers'    => $layers,
        ];
    }

    wp_send_json_success( $designs );
}
