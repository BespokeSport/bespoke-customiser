<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register the [bespoke_customiser] shortcode
 * Usage: [bespoke_customiser product_id="1276"]
 */
add_shortcode( 'bespoke_customiser', 'bespoke_render_customiser' );

function bespoke_render_customiser( $atts ) {
    $atts = shortcode_atts([
        'product_id' => '1276',   // Default: Personalised Shin Pads
    ], $atts );

    $product_id = intval( $atts['product_id'] );
    $product    = wc_get_product( $product_id );

    if ( ! $product ) {
        return '<p>Product not found.</p>';
    }

    $price     = $product->get_price();
    $ajax_url  = admin_url( 'admin-ajax.php' );
    $nonce     = wp_create_nonce( 'bespoke_add_to_cart' );

    ob_start();
    ?>
    <div id="bespoke-customiser-root"
         data-product-id="<?php echo esc_attr( $product_id ); ?>"
         data-price="<?php echo esc_attr( number_format( $price, 2 ) ); ?>"
         data-ajax-url="<?php echo esc_attr( $ajax_url ); ?>"
         data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <?php
        // The customiser HTML is loaded from the compiled asset file
        // This keeps the shortcode PHP clean and the HTML maintainable
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
    // Pass WordPress data into the customiser
    window.BespokeConfig = {
        productId:  <?php echo intval( $product_id ); ?>,
        price:      '£<?php echo esc_js( number_format( $price, 2 ) ); ?>',
        ajaxUrl:    '<?php echo esc_js( $ajax_url ); ?>',
        nonce:      '<?php echo esc_js( $nonce ); ?>',
        uploadUrl:  '<?php echo esc_js( BESPOKE_PLUGIN_URL . 'includes/customiser-ajax.php' ); ?>'
    };
    </script>
    <?php

    return ob_get_clean();
}

/**
 * Enqueue customiser styles on pages that use the shortcode
 */
add_action( 'wp_enqueue_scripts', function() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bespoke_customiser' ) ) {
        // Any additional styles can be added here
        wp_enqueue_style(
            'bespoke-customiser',
            BESPOKE_PLUGIN_URL . 'assets/customiser.css',
            [],
            BESPOKE_VERSION
        );
    }
});
