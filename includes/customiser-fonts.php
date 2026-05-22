<?php
/**
 * BEspoke Sport – Custom Font Management
 *
 * Adds a "Customiser Fonts" admin page where you can upload your own
 * .ttf and .otf font files. The fonts are stored in
 * /wp-content/uploads/bespoke-fonts/ and the metadata is stored in the
 * `bespoke_customiser_fonts` WordPress option.
 *
 * Public API:
 *   bespoke_get_custom_fonts()  →  array of [name, filename, url, uploaded_at]
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-fonts.php
 * Included by:   bespoke-customiser.php (main plugin bootstrap)
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
   1. CONSTANTS & DIRECTORY SETUP
   ========================================================================= */

if ( ! defined( 'BESPOKE_FONTS_DIR' ) ) {
    define( 'BESPOKE_FONTS_DIR', wp_upload_dir()['basedir'] . '/bespoke-fonts/' );
    define( 'BESPOKE_FONTS_URL', wp_upload_dir()['baseurl'] . '/bespoke-fonts/' );
}

// Create the fonts directory on init if it doesn't exist yet.
add_action( 'init', function() {
    if ( ! file_exists( BESPOKE_FONTS_DIR ) ) {
        wp_mkdir_p( BESPOKE_FONTS_DIR );
        // Prevent directory listing.
        @file_put_contents( BESPOKE_FONTS_DIR . '.htaccess', "Options -Indexes\n" );
    }
} );


/* =========================================================================
   2. ADMIN MENU
   ========================================================================= */

add_action( 'admin_menu', function() {
    add_menu_page(
        'Customiser Fonts',        // page title
        'Customiser Fonts',        // menu title
        'manage_options',          // capability
        'bespoke-fonts',           // menu slug
        'bespoke_render_fonts_admin_page',
        'dashicons-editor-textcolor',
        57
    );
} );


/* =========================================================================
   3. ADMIN PAGE RENDERER
   ========================================================================= */

function bespoke_render_fonts_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have permission to access this page.' );
    }

    // Handle delete (POST)
    if ( isset( $_POST['bespoke_delete_font'] ) && check_admin_referer( 'bespoke_delete_font_action' ) ) {
        $idx = isset( $_POST['font_index'] ) ? intval( $_POST['font_index'] ) : -1;
        bespoke_delete_font_by_index( $idx );
    }

    // Handle reorder (POST)
    if ( isset( $_POST['bespoke_move_font'] ) && check_admin_referer( 'bespoke_move_font_action' ) ) {
        $idx       = isset( $_POST['font_index'] ) ? intval( $_POST['font_index'] ) : -1;
        $direction = isset( $_POST['direction'] ) ? sanitize_key( $_POST['direction'] ) : '';
        bespoke_move_font( $idx, $direction );
    }

    // Handle upload (POST)
    if ( isset( $_POST['bespoke_upload_font'] ) && check_admin_referer( 'bespoke_upload_font_action' ) ) {
        $name = isset( $_POST['font_name'] ) ? sanitize_text_field( $_POST['font_name'] ) : '';
        if ( ! empty( $_FILES['font_file'] ) && $_FILES['font_file']['error'] !== UPLOAD_ERR_NO_FILE ) {
            bespoke_handle_font_upload( $_FILES['font_file'], $name );
        } else {
            add_settings_error( 'bespoke_fonts', 'no_file', 'Please choose a font file to upload.', 'error' );
        }
    }

    $fonts = get_option( 'bespoke_customiser_fonts', [] );
    settings_errors( 'bespoke_fonts' );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Customiser Fonts</h1>
        <p>Upload <code>.ttf</code> or <code>.otf</code> font files to use in the shin pad customiser. Uploaded fonts replace the hardcoded font list.</p>

        <h2>Upload a new font</h2>
        <form method="post" enctype="multipart/form-data" style="margin-bottom: 40px;">
            <?php wp_nonce_field( 'bespoke_upload_font_action' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="font_name">Display name</label></th>
                    <td>
                        <input name="font_name" id="font_name" type="text" class="regular-text" placeholder="e.g. Anton, Roboto Bold" />
                        <p class="description">How the font appears in the customiser font picker. If left blank, the filename is used.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="font_file">Font file</label></th>
                    <td>
                        <input name="font_file" id="font_file" type="file" accept=".ttf,.otf,font/ttf,font/otf" required />
                        <p class="description">Only <code>.ttf</code> and <code>.otf</code> files are accepted.</p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="bespoke_upload_font" class="button button-primary" value="Upload Font" /></p>
        </form>

        <h2>Uploaded fonts (<?php echo count( $fonts ); ?>)</h2>
        <?php if ( empty( $fonts ) ) : ?>
            <p><em>No fonts uploaded yet. Upload one above to get started.</em></p>
        <?php else : ?>
            <style>
                <?php foreach ( $fonts as $idx => $f ) : ?>
                @font-face {
                    font-family: 'bespoke-preview-<?php echo (int) $idx; ?>';
                    src: url('<?php echo esc_url( $f['url'] ); ?>');
                    font-display: swap;
                }
                <?php endforeach; ?>
                .bespoke-font-preview {
                    font-size: 28px;
                    line-height: 1.2;
                    color: #1d2327;
                }
            </style>
            <p class="description" style="margin-bottom: 10px;">
                Fonts appear in the customiser in the order shown below. Use the &uarr; and &darr; buttons to reorder them.
            </p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 8%;">Order</th>
                        <th style="width: 16%;">Display name</th>
                        <th style="width: 20%;">Filename</th>
                        <th style="width: 12%;">Uploaded</th>
                        <th style="width: 34%;">Preview</th>
                        <th style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $last_idx = count( $fonts ) - 1;
                    foreach ( $fonts as $idx => $f ) : ?>
                        <tr>
                            <td>
                                <strong style="display: inline-block; min-width: 1.5em;"><?php echo (int) $idx + 1; ?>.</strong>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field( 'bespoke_move_font_action' ); ?>
                                    <input type="hidden" name="font_index" value="<?php echo (int) $idx; ?>" />
                                    <input type="hidden" name="direction" value="up" />
                                    <button type="submit" name="bespoke_move_font" class="button button-small" title="Move up" <?php echo $idx === 0 ? 'disabled' : ''; ?>>&uarr;</button>
                                </form>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field( 'bespoke_move_font_action' ); ?>
                                    <input type="hidden" name="font_index" value="<?php echo (int) $idx; ?>" />
                                    <input type="hidden" name="direction" value="down" />
                                    <button type="submit" name="bespoke_move_font" class="button button-small" title="Move down" <?php echo $idx === $last_idx ? 'disabled' : ''; ?>>&darr;</button>
                                </form>
                            </td>
                            <td><strong><?php echo esc_html( $f['name'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $f['filename'] ); ?></code></td>
                            <td><?php echo esc_html( date( 'Y-m-d H:i', $f['uploaded_at'] ) ); ?></td>
                            <td>
                                <span class="bespoke-font-preview" style="font-family: 'bespoke-preview-<?php echo (int) $idx; ?>', sans-serif;">
                                    AaBbCc 123 — The quick brown fox
                                </span>
                            </td>
                            <td>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete &quot;<?php echo esc_js( $f['name'] ); ?>&quot;? This cannot be undone.');">
                                    <?php wp_nonce_field( 'bespoke_delete_font_action' ); ?>
                                    <input type="hidden" name="font_index" value="<?php echo (int) $idx; ?>" />
                                    <input type="submit" name="bespoke_delete_font" class="button button-link-delete" value="Delete" />
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}


/* =========================================================================
   4. UPLOAD HANDLER
   ========================================================================= */

function bespoke_handle_font_upload( $file, $display_name ) {
    $allowed_exts = [ 'ttf', 'otf' ];
    $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

    if ( ! in_array( $ext, $allowed_exts, true ) ) {
        add_settings_error( 'bespoke_fonts', 'bad_ext', 'Only .ttf and .otf font files are accepted.', 'error' );
        return;
    }
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        add_settings_error( 'bespoke_fonts', 'upload_err', 'Upload failed (error code ' . intval( $file['error'] ) . ').', 'error' );
        return;
    }
    if ( ! file_exists( BESPOKE_FONTS_DIR ) ) {
        wp_mkdir_p( BESPOKE_FONTS_DIR );
    }

    // Build a safe filename. Avoid collisions by appending -1, -2, ...
    $safe = sanitize_file_name( $file['name'] );
    $base = pathinfo( $safe, PATHINFO_FILENAME );
    $target = BESPOKE_FONTS_DIR . $safe;
    $counter = 1;
    while ( file_exists( $target ) ) {
        $target = BESPOKE_FONTS_DIR . $base . '-' . $counter . '.' . $ext;
        $counter++;
    }

    if ( ! @move_uploaded_file( $file['tmp_name'], $target ) ) {
        add_settings_error( 'bespoke_fonts', 'move_failed', 'Could not save the uploaded file. Check folder permissions on /wp-content/uploads/bespoke-fonts/.', 'error' );
        return;
    }

    $fonts   = get_option( 'bespoke_customiser_fonts', [] );
    $fonts[] = [
        'name'        => $display_name !== '' ? $display_name : $base,
        'filename'    => basename( $target ),
        'url'         => BESPOKE_FONTS_URL . basename( $target ),
        'uploaded_at' => time(),
    ];
    update_option( 'bespoke_customiser_fonts', $fonts );

    add_settings_error( 'bespoke_fonts', 'uploaded', 'Font uploaded successfully.', 'updated' );
}


/* =========================================================================
   5. DELETE HANDLER
   ========================================================================= */

function bespoke_delete_font_by_index( $idx ) {
    $fonts = get_option( 'bespoke_customiser_fonts', [] );
    if ( ! isset( $fonts[ $idx ] ) ) {
        add_settings_error( 'bespoke_fonts', 'no_such', 'Font not found.', 'error' );
        return;
    }
    $f = $fonts[ $idx ];
    $path = BESPOKE_FONTS_DIR . $f['filename'];
    if ( file_exists( $path ) ) {
        @unlink( $path );
    }
    array_splice( $fonts, $idx, 1 );
    update_option( 'bespoke_customiser_fonts', array_values( $fonts ) );

    add_settings_error( 'bespoke_fonts', 'deleted', 'Font deleted.', 'updated' );
}


/* =========================================================================
   6. REORDER HANDLER
   ========================================================================= */

function bespoke_move_font( $idx, $direction ) {
    $fonts = get_option( 'bespoke_customiser_fonts', [] );
    if ( ! isset( $fonts[ $idx ] ) ) {
        add_settings_error( 'bespoke_fonts', 'no_such', 'Font not found.', 'error' );
        return;
    }
    $swap_with = $direction === 'up' ? $idx - 1 : ( $direction === 'down' ? $idx + 1 : -1 );
    if ( ! isset( $fonts[ $swap_with ] ) ) {
        return; // Edge of list — silently do nothing.
    }
    $tmp                 = $fonts[ $idx ];
    $fonts[ $idx ]       = $fonts[ $swap_with ];
    $fonts[ $swap_with ] = $tmp;
    update_option( 'bespoke_customiser_fonts', array_values( $fonts ) );
    add_settings_error( 'bespoke_fonts', 'moved', 'Order updated.', 'updated' );
}


/* =========================================================================
   7. PUBLIC API (for use by the customiser frontend integration)
   ========================================================================= */

/**
 * Returns the list of uploaded custom fonts.
 *
 * @return array Array of fonts, each with [name, filename, url, uploaded_at].
 */
function bespoke_get_custom_fonts() {
    return get_option( 'bespoke_customiser_fonts', [] );
}
