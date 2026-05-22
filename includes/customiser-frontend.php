<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register the [bespoke_customiser] shortcode
 *
 * Usage examples:
 *   [bespoke_customiser product_id="1276" product_type="shinpads"]
 *   [bespoke_customiser product_id="1305" product_type="gripsocks"]
 *   [bespoke_customiser product_id="1312" product_type="armbands"]
 *
 * product_type must match one of the keys in bespoke_get_product_types()
 * in customiser-designs.php.
 */
add_shortcode( 'bespoke_customiser', 'bespoke_render_customiser' );

function bespoke_render_customiser( $atts ) {
    $atts = shortcode_atts( [
        'product_id'   => '1276',     // Default: Personalised Shin Pads
        'product_type' => 'shinpads', // Default product type for design filtering
    ], $atts );

    $product_id   = intval( $atts['product_id'] );
    $product_type = sanitize_key( $atts['product_type'] );

    // Validate product_type against the registered list
    $valid_types = array_keys( bespoke_get_product_types() );
    if ( ! in_array( $product_type, $valid_types, true ) ) {
        $product_type = 'shinpads'; // Fall back safely
    }

    $product = wc_get_product( $product_id );

    if ( ! $product ) {
        return '<p>Product not found.</p>';
    }

    $price    = $product->get_price();
    $ajax_url = admin_url( 'admin-ajax.php' );
    $nonce    = wp_create_nonce( 'bespoke_add_to_cart' );

    ob_start();
    ?>
    <div id="bespoke-customiser-root"
         data-product-id="<?php echo esc_attr( $product_id ); ?>"
         data-product-type="<?php echo esc_attr( $product_type ); ?>"
         data-price="<?php echo esc_attr( number_format( $price, 2 ) ); ?>"
         data-ajax-url="<?php echo esc_attr( $ajax_url ); ?>"
         data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <?php
        // The customiser HTML is loaded from the compiled asset file.
        // This keeps the shortcode PHP clean and the HTML maintainable.
        $customiser_file = BESPOKE_PLUGIN_DIR . 'assets/customiser.html';
        if ( file_exists( $customiser_file ) ) {
            $raw = file_get_contents( $customiser_file );

            // Pull <style> blocks out of <head> — they contain all the core CSS
            // and would be lost if we only extracted the <body>.
            $head_styles = '';
            if ( preg_match( '/<head[^>]*>(.*?)<\/head>/si', $raw, $head_match ) ) {
                preg_match_all( '/<style[^>]*>.*?<\/style>/si', $head_match[1], $style_matches );
                if ( ! empty( $style_matches[0] ) ) {
                    $head_styles = implode( "\n", $style_matches[0] );
                }
            }

            // Extract the body content
            preg_match( '/<body[^>]*>(.*)<\/body>/si', $raw, $matches );
            $body_content = isset( $matches[1] ) ? $matches[1] : $raw;

            echo $head_styles . $body_content;
        } else {
            echo '<p style="color:red;">Customiser asset not found. Please ensure customiser.html is in the plugin assets folder.</p>';
        }
        ?>

    </div>

    <script>
    // Pass WordPress data into the customiser JavaScript
    window.BespokeConfig = {
        productId:   <?php echo intval( $product_id ); ?>,
        productType: '<?php echo esc_js( $product_type ); ?>',
        price:       '£<?php echo esc_js( number_format( $price, 2 ) ); ?>',
        ajaxUrl:     '<?php echo esc_js( $ajax_url ); ?>',
        nonce:       '<?php echo esc_js( $nonce ); ?>',
        uploadUrl:   '<?php echo esc_js( BESPOKE_PLUGIN_URL . 'includes/customiser-ajax.php' ); ?>'
    };
    </script>

    <?php
    // ─── Custom fonts integration ──────────────────────────────────────────
    // If any fonts have been uploaded via the "Customiser Fonts" admin page,
    // (1) emit @font-face declarations referencing the uploaded files,
    // (2) expose them as window.BESPOKE_CUSTOM_FONTS, and
    // (3) replace the customiser's hardcoded FONTS array + rebuild the picker.
    $custom_fonts = function_exists( 'bespoke_get_custom_fonts' ) ? bespoke_get_custom_fonts() : [];
    if ( ! empty( $custom_fonts ) ) :
        // Build a JS-safe array with stable family names
        $js_fonts = [];
        $css_face = '';
        foreach ( $custom_fonts as $idx => $f ) {
            $ext    = strtolower( pathinfo( $f['filename'], PATHINFO_EXTENSION ) );
            $format = $ext === 'otf' ? 'opentype' : 'truetype';
            $family = 'bespoke-font-' . intval( $idx );
            // font-weight: 100 900 tells the browser this single file covers
            // every requested weight (otherwise SVG text at font-weight:900
            // falls back to a system font on Chrome/Safari).
            $css_face .= "@font-face{font-family:'" . esc_attr( $family ) . "';src:url('" . esc_url( $f['url'] ) . "') format('" . $format . "');font-weight:100 900;font-style:normal;font-display:swap;}\n";
            $js_fonts[] = [
                'id'      => $family,
                'label'   => $f['name'],
                'family'  => "'" . $family . "', sans-serif",
                'preview' => 'SMITH',
            ];
        }
        ?>
        <style id="bespoke-custom-font-faces">
            <?php echo $css_face; ?>
        </style>
        <script>
        window.BESPOKE_CUSTOM_FONTS = <?php echo wp_json_encode( $js_fonts ); ?>;

        (function(){
            // Wait until the customiser has populated window.FONTS, then replace it
            // with the uploaded fonts and rebuild the .font-card grid in the picker.
            function rebuildPicker() {
                if (!window.FONTS || !document.querySelector('.font-card')) {
                    return setTimeout(rebuildPicker, 200);
                }
                // Replace FONTS contents (mutate in place so internal references update)
                window.FONTS.length = 0;
                window.BESPOKE_CUSTOM_FONTS.forEach(function(f){ window.FONTS.push(f); });

                // Rebuild every .font-card grid on the page (Name + Number tabs each have one)
                var grids = new Set();
                document.querySelectorAll('.font-card').forEach(function(c){ grids.add(c.parentElement); });

                grids.forEach(function(grid){
                    // Detect which target this grid is for (name vs num) from existing cards
                    var firstCard = grid.querySelector('.font-card');
                    var isNum = grid.closest('[data-target=num], .step-num, #step-num') || (firstCard && firstCard.getAttribute('data-target') === 'num');
                    var target = isNum ? 'num' : 'name';
                    var currentFamily = window.S ? (target === 'num' ? window.S.numFontFamily : window.S.nameFontFamily) : null;

                    grid.innerHTML = '';
                    window.FONTS.forEach(function(f){
                        var card = document.createElement('div');
                        card.className = 'font-card' + (currentFamily === f.family ? ' sel' : '');
                        card.setAttribute('data-target', target);
                        card.innerHTML = '<div class="font-preview" style="font-family:' + f.family + '">' + (f.preview || 'SMITH') + '</div><div class="font-name">' + f.label + '</div>';
                        card.addEventListener('click', function(){
                            grid.querySelectorAll('.font-card').forEach(function(c){ c.classList.remove('sel'); });
                            card.classList.add('sel');
                            if (window.S) {
                                if (target === 'num') window.S.numFontFamily = f.family;
                                else window.S.nameFontFamily = f.family;
                            }
                            // Trigger SVG re-render (function name varies; try common ones)
                            if (typeof window.makeSVG === 'function') window.makeSVG();
                            if (typeof window.render === 'function') window.render();
                            if (typeof window.updatePreview === 'function') window.updatePreview();
                        });
                        grid.appendChild(card);
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', rebuildPicker);
            } else {
                rebuildPicker();
            }
        })();
        </script>
    <?php endif; ?>

    <?php

    return ob_get_clean();
}

/**
 * Enqueue customiser styles on pages that use the shortcode
 */
add_action( 'wp_enqueue_scripts', function() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bespoke_customiser' ) ) {
        wp_enqueue_style(
            'bespoke-customiser',
            BESPOKE_PLUGIN_URL . 'assets/customiser.css',
            [],
            BESPOKE_VERSION
        );
    }
} );
