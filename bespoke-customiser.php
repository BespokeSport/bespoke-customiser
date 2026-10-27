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
        'customiser-brand-base.php',    // brand foundation CSS (was the "BEspoke Global Styles" snippet)
        'customiser-designs.php',       // design management
        'customiser-products.php',      // per-product asset uploads (background + pad base)
        'customiser-fonts.php',         // custom font upload
        'customiser-i18n.php',          // wording the customiser's script writes, for TranslatePress
        'customiser-frontend.php',
        'customiser-woocommerce.php',
        'customiser-ajax.php',
        'customiser-product-page.php',  // per-product PDP content (eyebrow / sizing / etc.)
        'customiser-copy.php',          // move product wording + customiser setup staging → live
        'customiser-seo-pack.php',      // apply a group of products' names, wording and SEO in one go
        'customiser-launch.php',        // launch day: holding page, and staging → live in one file
        'customiser-design-import.php', // bulk-create designs from a folder of PNGs
        'customiser-global-fonts.php',  // force-load Anton (independent of Elementor / theme)
        'customiser-shop.php',          // Shop / category archive styling + "Customise →" button copy
        'customiser-blog.php',          // The Locker Room (blog index + single article) styling
        'customiser-cart.php',          // Cart page styling + "Re-customise" link on each item
        'customiser-contact.php',       // Contact page styling (form + info card)
        'customiser-shortcodes.php',    // [bespoke_ticker] / [bespoke_promise] / [bespoke_clubs_say]
        'customiser-master.php',        // Master/global page theme — dark + mint + Inter on every front-end page
        'customiser-homepage.php',      // Homepage hero + product range grid styling
        'customiser-hero-world.php',    // [bespoke_hero_world] scroll-driven rotating world hero
        'customiser-fpd-archive.php',   // makes historic Fancy Product Designer orders readable
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

/**
 * Keep the badge upload directory protected.
 *
 * Runs on every load, not just activation. It used to be activation-only
 * AND skipped entirely if the folder already existed, so a site where
 * /uploads/bespoke-badges/ predated the plugin — or where the file was ever
 * lost in a migration or restore — had an unprotected directory that
 * anonymous visitors can write into. The other three upload directories
 * (designs, product assets, fonts) already self-healed on init; this one
 * was the exception.
 *
 * Belt and braces: uploads are now named server-side from their verified
 * content type (see customiser-ajax.php), so nothing should ever write a
 * .php here. This is what catches it if something does. The rules are
 * Apache syntax and do nothing on nginx or LiteSpeed, which is the second
 * reason not to depend on them alone.
 */
/**
 * Protect every folder the plugin writes into: the customers' badges and
 * previews, design artwork, product artwork and fonts.
 *
 * Rewritten whenever the file is missing or older than this version — the
 * other three folders used to get theirs only when the folder was first made,
 * so a folder uploaded by hand had none (security review, 27 Oct 2026). The
 * rules now also catch "x.php.png", and send SVGs with a sandbox, so even a
 * hostile SVG opened straight from its address can't run script in the
 * site's origin. Headers sit in IfModule so a server without mod_headers
 * ignores them rather than erroring. Apache rules: on nginx-only serving
 * they do nothing, which is why the uploads themselves are cleaned too.
 */
function bespoke_protect_upload_dirs() {
    $up   = wp_upload_dir();
    $base = trailingslashit( $up['basedir'] );
    $rules = "# bespoke-protect 2\n"
        . "Options -Indexes\n"
        . "<FilesMatch \"\\.(php[0-9]?|phtml|phar|pl|py|cgi|sh)(\\.|$)\">\n"
        . "    Require all denied\n"
        . "</FilesMatch>\n"
        . "<IfModule mod_headers.c>\n"
        . "    Header set X-Content-Type-Options \"nosniff\"\n"
        . "    <FilesMatch \"\\.svg$\">\n"
        . "        Header set Content-Security-Policy \"sandbox\"\n"
        . "    </FilesMatch>\n"
        . "</IfModule>\n";
    foreach ( [ BESPOKE_UPLOAD_DIR, $base . 'bespoke-designs/', $base . 'bespoke-product-assets/', $base . 'bespoke-fonts/' ] as $dir ) {
        if ( BESPOKE_UPLOAD_DIR === $dir && ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        if ( ! is_dir( $dir ) ) {
            continue; // made, with its protection, the first time it's needed
        }
        $ht = $dir . '.htaccess';
        if ( ! file_exists( $ht ) || 0 !== strpos( (string) @file_get_contents( $ht, false, null, 0, 20 ), '# bespoke-protect 2' ) ) {
            @file_put_contents( $ht, $rules );
        }
    }
}
add_action( 'init', function() {
    if ( defined( 'BESPOKE_UPLOAD_DIR' ) ) {
        bespoke_protect_upload_dirs();
    }
} );

// Create the badge folder, protected, on activation.
register_activation_hook( __FILE__, 'bespoke_protect_upload_dirs' );
