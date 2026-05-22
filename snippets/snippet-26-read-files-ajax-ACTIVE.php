<?php
/**
 * Snippet ID:    26
 * Name:          BESPOKE: Read plugin files (AJAX debug)
 * Status:        ACTIVE
 * Last modified: 2026-05-22 (path fix)
 * AJAX endpoint that returns the contents of the plugin's PHP/HTML files.
 * Useful for previous Claude sessions to inspect server-side code.
 *
 * Previously broken because of "bespoke-sport" path — corrected to
 * "bespoke-customiser" on 2026-05-22.
 */

add_action('wp_ajax_bespoke_read_files', function() {
    $plugin_dir = WP_PLUGIN_DIR . '/bespoke-customiser/';
    $files = [
        'main' => $plugin_dir . 'bespoke-customiser.php',
        'ajax' => $plugin_dir . 'includes/customiser-ajax.php',
        'frontend' => $plugin_dir . 'includes/customiser-frontend.php',
        'woo' => $plugin_dir . 'includes/customiser-woocommerce.php',
        'html' => $plugin_dir . 'assets/customiser.html',
    ];
    $out = [];
    foreach ($files as $key => $path) {
        $out[$key] = file_exists($path) ? file_get_contents($path) : 'NOT FOUND: ' . $path;
    }
    wp_send_json_success($out);
});
