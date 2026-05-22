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
        @file_put_contents( BESPOKE_DESIGNS_DIR . '.htaccess', "Options -Indexes\n" );
    }
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

    if ( ! @move_uploaded_file( $file['tmp_name'], $target ) ) {
        wp_send_json_error( 'Could not save file (check folder permissions on /wp-content/uploads/bespoke-designs/)' );
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

        <!-- SVG File -->
        <tr>
            <th><label>SVG file</label></th>
            <td>
                <button type="button" class="button button-primary" id="bespoke-svg-btn">
                    <?php echo $svg_url ? 'Change SVG' : 'Upload SVG'; ?>
                </button>
                <?php if ( $svg_url ) : ?>
                    <button type="button" class="button" id="bespoke-svg-remove" style="margin-left:6px;">Remove</button>
                <?php endif; ?>
                <input type="url"
                       name="bespoke_svg_url"
                       id="bespoke_svg_url"
                       value="<?php echo esc_attr( $svg_url ); ?>"
                       class="large-text"
                       placeholder="https://…"
                       style="margin-top:10px;" />
                <p class="description">
                    Click <strong>Upload SVG</strong> to add or replace the design's SVG via the Media Library, or paste a URL directly.<br />
                    <strong>Important:</strong> colour zones in your SVG must use CSS variables
                    <code>--col-1</code>, <code>--col-2</code>, <code>--col-3</code> etc.
                    to match the layers you define in the Colour Layers box below.
                </p>
                <?php if ( $svg_url ) : ?>
                    <p style="margin-top:6px;">
                        <a href="<?php echo esc_url( $svg_url ); ?>" target="_blank">View current SVG ↗</a>
                    </p>
                <?php endif; ?>
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
    <p style="margin-bottom:12px;color:#555;">
        Define the independently-colourable zones in this design's SVG.<br />
        Each layer maps to a CSS variable: Layer 1 → <code>--col-1</code>, Layer 2 → <code>--col-2</code>, etc.<br />
        The customer will see a colour picker for each layer, labelled with the name you enter here.
    </p>

    <table class="widefat" id="bespoke-layers-table" style="margin-bottom:12px;">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Layer label <span style="font-weight:400;color:#888;">(shown to customer)</span></th>
                <th style="width:140px;">Default colour</th>
                <th style="width:80px;">Remove</th>
            </tr>
        </thead>
        <tbody id="bespoke-layers-body">
            <?php foreach ( $layers as $i => $layer ) :
                $n = $i + 1;
            ?>
            <tr class="bespoke-layer-row" style="background:#fff;">
                <td style="vertical-align:middle;font-weight:700;color:#888;"><?php echo $n; ?></td>
                <td>
                    <input type="text"
                           name="bespoke_layers[<?php echo $i; ?>][label]"
                           value="<?php echo esc_attr( $layer['label'] ?? '' ); ?>"
                           placeholder="e.g. Pad background"
                           style="width:100%;" />
                    <small style="color:#aaa;">SVG variable: <code>--col-<?php echo $n; ?></code></small>
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
            var html = '<tr class="bespoke-layer-row" style="background:#fff;">'
                + '<td style="vertical-align:middle;font-weight:700;color:#888;">' + num + '</td>'
                + '<td>'
                + '<input type="text" name="bespoke_layers[' + idx + '][label]" value="" '
                + 'placeholder="e.g. Pattern" style="width:100%;" />'
                + '<small style="color:#aaa;">SVG variable: <code>--col-' + num + '</code></small>'
                + '</td>'
                + '<td>'
                + '<div style="display:flex;align-items:center;gap:8px;">'
                + '<input type="color" name="bespoke_layers[' + idx + '][default]" value="#000000" '
                + 'style="width:48px;height:32px;border:1px solid #ddd;border-radius:4px;cursor:pointer;" />'
                + '<input type="text" class="bespoke-hex-display" value="#000000" maxlength="7" '
                + 'style="width:80px;font-family:monospace;" />'
                + '</div>'
                + '</td>'
                + '<td><button type="button" class="button bespoke-remove-layer" '
                + 'style="color:#a00;">Remove</button></td>'
                + '</tr>';
            $( '#bespoke-layers-body' ).append( html );
            renumberRows();
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

        // ── Renumber rows after add/remove ────────────────────────────────────
        function renumberRows() {
            $( '#bespoke-layers-body .bespoke-layer-row' ).each( function( i ) {
                var n = i + 1;
                $( this ).find( 'td:first' ).text( n );
                $( this ).find( 'small' ).html( 'SVG variable: <code>--col-' + n + '</code>' );
                // Re-index name attributes so POST data is a clean 0-based array
                $( this ).find( 'input[name^="bespoke_layers"]' ).each( function() {
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
        $label   = sanitize_text_field( $layer['label']   ?? '' );
        $default = sanitize_hex_color(  $layer['default'] ?? '#000000' );
        if ( $label ) {
            $layers[] = [
                'label'   => $label,
                'default' => $default ?: '#000000',
            ];
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
