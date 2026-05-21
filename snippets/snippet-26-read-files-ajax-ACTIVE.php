<?php
/**
 * Snippet ID:    26
 * Name:          (no name set in Code Snippets)
 * Status:        ACTIVE
 * Last modified: 2026-05-18 16:59:51
 * AJAX endpoint that returns the contents of the plugin's PHP/HTML files.
 *
 * KNOWN BUG: hardcodes plugin folder as "bespoke-sport" but the actual
 * folder is "bespoke-customiser". This snippet is currently broken / will
 * return "NOT FOUND" for every file. Needs path corrected before re-use.
 */

add_action('wp_ajax_bespoke_read_files', function() {
    $plugin_dir = WP_PLUGIN_DIR . '/bespoke-sport/';
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
