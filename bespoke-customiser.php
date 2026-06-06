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

// Load plugin components
add_action( 'plugins_loaded', function() {
    if ( ! bespoke_check_woocommerce() ) return;

    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-designs.php';   // design management
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-products.php';  // per-product asset uploads (background + pad base)
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-fonts.php';     // custom font upload
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-frontend.php';
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-woocommerce.php';
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-ajax.php';
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-product-page.php'; // per-product PDP content (eyebrow / sizing / etc.)
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-global-fonts.php'; // force-load Anton (independent of Elementor / theme)
    require_once BESPOKE_PLUGIN_DIR . 'includes/customiser-shop.php';         // Shop / category archive styling + "Customise →" button copy
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
