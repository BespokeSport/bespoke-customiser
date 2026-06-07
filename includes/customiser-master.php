<?php
/**
 * BEspoke Customiser — Master / global page theme.
 *
 * Wires the master stylesheet (assets/bespoke-master.css) into every
 * front-end page so any NEW Page the designer creates picks up the
 * dark canvas, brand fonts, mint links and on-brand selection /
 * scrollbar / submit-button styling automatically — no per-page setup
 * needed.
 *
 * What this file does:
 *   1) Enqueues assets/bespoke-master.css on every front-end page.
 *   2) Adds a `bespoke-themed` body class so the CSS has a stable
 *      hook (everything in the stylesheet is scoped to that class).
 *   3) Respects two opt-outs:
 *        a) per-page: add `bs-light` to the page's body classes via
 *           Astra's "Body Class" page setting, or the Yoast / SEO
 *           plugin body class field. The CSS uses `:not(.bs-light)`
 *           so the dark theme reverts to whatever the theme would
 *           render normally.
 *        b) site-wide / programmatic: filter `bespoke_apply_master_theme`
 *           returning false. Useful in Code Snippets if you want to
 *           toggle the master theme off temporarily.
 *
 * Why this file is separate from customiser-global-fonts.php:
 *   The fonts loader runs at wp_enqueue_scripts priority 1 (race
 *   to top of <head> for fastest font swap). The master theme
 *   should load AFTER any theme defaults so its rules win on the
 *   normal source-order tiebreak — priority 50 keeps it out of the
 *   fonts loader's lane.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-master.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Enqueue the master stylesheet on every front-end page.
 *    Priority 50 — late enough to win over the theme's own canvas
 *    styles on a source-order tiebreak, early enough to lose to
 *    page-specific CSS (shop / blog / cart / contact / PDP) which
 *    layers on top.
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'bespoke_master_enqueue', 50 );
function bespoke_master_enqueue() {
	if ( is_admin() ) {
		return;
	}
	if ( ! bespoke_master_should_apply() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}
	$css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-master.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style(
		'bespoke-master',
		BESPOKE_PLUGIN_URL . 'assets/bespoke-master.css',
		[],
		$version
	);
}

/* -------------------------------------------------------------------------
 * 2. Body class — every selector in the master stylesheet is scoped to
 *    `body.bespoke-themed`, so without this class NOTHING in the
 *    master stylesheet fires. Anchoring the whole stylesheet on a
 *    PHP-controlled class gives us a single switch to flip if we
 *    ever need to disable the master theme everywhere.
 * --------------------------------------------------------------------- */
add_filter( 'body_class', 'bespoke_master_body_class' );
function bespoke_master_body_class( $classes ) {
	if ( is_admin() ) {
		return $classes;
	}
	if ( ! bespoke_master_should_apply() ) {
		return $classes;
	}
	$classes[] = 'bespoke-themed';
	return $classes;
}

/* -------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/**
 * Should the master theme apply on the current request?
 *
 * Returns:
 *   true   on every front-end page by default
 *   false  if the `bespoke_apply_master_theme` filter says no
 *
 * Note: per-page opt-out via the `bs-light` body class is handled
 * directly in the CSS via `:not(.bs-light)` — no PHP needed, since
 * Astra's per-page body-class field already injects whatever the
 * designer types into the body tag.
 */
function bespoke_master_should_apply() {
	return (bool) apply_filters( 'bespoke_apply_master_theme', true );
}
