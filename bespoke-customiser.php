<?php
/**
 * Plugin Name: BEspoke Sport Customiser
 * Plugin URI:  https://bespokesport.uk
 * Description: Custom shin pad configurator for BEspoke Sport. Integrates with WooCommerce to pass full design specifications through to orders.
 * Version:     1.1.0
 * Author:      BEspoke Sport
 * Text Domain: bespoke-customiser
 * Requires WC: 7.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'BESPOKE_VERSION',     '1.1.0' );
define( 'BESPOKE_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BESPOKE_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'BESPOKE_UPLOAD_DIR',  wp_upload_dir()['basedir'] . '/bespoke-badges/' );
define( 'BESPOKE_UPLOAD_URL',  wp_upload_dir()['baseurl'] . '/bespoke-badges/' );

// Check WooCommerce is active
function bespoke_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p><strong>BEspoke Customiser</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return false;
    }
    return true;
}

// Load plugin components.
// Each include is wrapped in file_exists() so a partial upload (one
// file missing on the server) degrades gracefully instead of fatal-
// erroring the whole site. Missing files are logged to error_log for
// the next-time-Claude-helps debugging trail.
add_action( 'plugins_loaded', function() {
    if ( ! bespoke_check_woocommerce() ) return;

    $modules = [
        'customiser-designs.php',       // design management
        'customiser-products.php',      // per-product asset uploads (background + pad base)
        'customiser-fonts.php',         // custom font upload
        'customiser-frontend.php',
        'customiser-woocommerce.php',
        'customiser-ajax.php',
        'customiser-product-page.php',  // per-product PDP content (eyebrow / sizing / etc.)
        'customiser-global-fonts.php',  // force-load Anton (independent of Elementor / theme)
        'customiser-shop.php',          // Shop / category archive styling + "Customise →" button copy
        'customiser-blog.php',          // The Locker Room (blog index + single article) styling
        'customiser-cart.php',          // Cart page styling + "Re-customise" link on each item
        'customiser-contact.php',       // Contact page styling (form + info card)
        'customiser-shortcodes.php',    // [bespoke_ticker] / [bespoke_promise] / [bespoke_clubs_say]
        'customiser-master.php',        // Master/global page theme — dark + mint + Inter on every front-end page
    ];

    $missing = [];
    foreach ( $modules as $file ) {
        $path = BESPOKE_PLUGIN_DIR . 'includes/' . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        } else {
            $missing[] = $file;
        }
    }

    if ( ! empty( $missing ) ) {
        // Log for the developer, AND surface a polite admin notice so
        // the BEspoke designer notices a partial upload immediately
        // instead of wondering why a feature isn't there.
        error_log( '[BEspoke Customiser] missing module files: ' . implode( ', ', $missing ) );
        add_action( 'admin_notices', function() use ( $missing ) {
            if ( ! current_user_can( 'activate_plugins' ) ) return;
            echo '<div class="notice notice-warning"><p><strong>BEspoke Customiser:</strong> '
                . esc_html( count( $missing ) )
                . ' module file(s) missing from <code>/wp-content/plugins/bespoke-customiser/includes/</code> — upload these and refresh: '
                . '<code>' . esc_html( implode( '</code>, <code>', $missing ) ) . '</code>.'
                . '</p></div>';
        });
    }
});

// Create badge upload directory on activation
register_activation_hook( __FILE__, function() {
    if ( ! file_exists( BESPOKE_UPLOAD_DIR ) ) {
        wp_mkdir_p( BESPOKE_UPLOAD_DIR );
        // Block directory listing AND deny execution of any
        // server-side script extensions ever written here — defence in
        // depth so a future upload bug can't plant a runnable .php.
        file_put_contents( BESPOKE_UPLOAD_DIR . '.htaccess',
            "Options -Indexes\n" .
            "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)\$\">\n" .
            "    Require all denied\n" .
            "</FilesMatch>\n"
        );
    }
});
