<?php
/**
 * BEspoke Sport — Global brand fonts
 *
 * Force-loads the brand font (Anton) on every page with display=swap
 * and matching preconnect hints, independent of Elementor's font loader.
 *
 * Why: Elementor's font loader is deferred. On slower / first-visit
 * mobile networks the Anton font file sometimes doesn't arrive before
 * the headline renders, and the page locks to the system sans-serif
 * fallback for the rest of the session — even though the CSS says
 * `font-family: Anton, sans-serif`.
 *
 * Loading Anton ourselves at the top of wp_head (priority 1) makes
 * the font part of the page's earliest network requests, guarantees
 * a `font-display: swap` fallback during the brief loading window,
 * and stays in lockstep with whatever Elementor / Astra is doing
 * (it's the same font file — extra requests are short-circuited by
 * the browser cache).
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-global-fonts.php
 * Included by:   bespoke-customiser.php (main plugin bootstrap)
 */

defined( 'ABSPATH' ) || exit;


/**
 * Drop preconnect tags early in <head> so the browser can warm up
 * the connection to Google Fonts before our stylesheet link is parsed.
 * Priority 1 = as soon as possible after wp_head fires.
 */
add_action( 'wp_head', 'bespoke_global_fonts_preconnect', 1 );

function bespoke_global_fonts_preconnect() {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}


/**
 * Enqueue Anton from Google Fonts on every front-end page.
 * Priority 1 so it lands in <head> before theme / Elementor stylesheets.
 *
 * display=swap → render with fallback immediately, swap to Anton when
 * loaded. Eliminates FOIT (flash of invisible text) and the worst
 * mobile failure mode (system fallback that never swaps back).
 */
add_action( 'wp_enqueue_scripts', 'bespoke_global_fonts_enqueue', 1 );

function bespoke_global_fonts_enqueue() {
    // Front-end only — no need to load brand fonts in the WP admin.
    if ( is_admin() ) {
        return;
    }
    wp_enqueue_style(
        'bespoke-brand-fonts',
        'https://fonts.googleapis.com/css2?family=Anton&display=swap',
        [],
        null   // no version query string → match Google's cache headers
    );

    // Global brand styles (mobile nav dropdown, etc.) — loaded site-wide,
    // not just on product pages. Cache-busted from the file's mtime.
    if ( defined( 'BESPOKE_PLUGIN_URL' ) && defined( 'BESPOKE_PLUGIN_DIR' ) ) {
        $global_css = BESPOKE_PLUGIN_DIR . 'assets/bespoke-global.css';
        wp_enqueue_style(
            'bespoke-global',
            BESPOKE_PLUGIN_URL . 'assets/bespoke-global.css',
            [],
            file_exists( $global_css ) ? filemtime( $global_css ) : '1.0'
        );
    }
}
