<?php
/**
 * BEspoke Customiser — Homepage styling.
 *
 * Wires assets/bespoke-homepage.css into the WordPress front page so
 * the hero, the "Range" headline, and the product card grid keep
 * their bespoke look-and-feel.
 *
 * Migrated from two Custom CSS/JS snippets that the designer is
 * decommissioning:
 *   - "BEspoke Homepage Styles"        (snippet 6618)
 *   - "BEspoke — Range Section Styles" (snippet 6636, partial overlap)
 *
 * Goal of the migration: same visual output as before, but now
 * version-controlled in the plugin (git history, can be reviewed,
 * can be re-applied to other environments easily) and only loaded
 * on the homepage instead of every page.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-homepage.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Enqueue the homepage stylesheet on the front page only.
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'bespoke_homepage_enqueue_styles' );
function bespoke_homepage_enqueue_styles() {
	if ( ! bespoke_homepage_is_front_page() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}
	$css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-homepage.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style(
		'bespoke-homepage',
		BESPOKE_PLUGIN_URL . 'assets/bespoke-homepage.css',
		[],
		$version
	);
}

/* -------------------------------------------------------------------------
 * 2. Body class — `bespoke-home-styled` as a stable hook for any
 *    future inline overrides (Code Snippets etc.).
 * --------------------------------------------------------------------- */
add_filter( 'body_class', 'bespoke_homepage_body_class' );
function bespoke_homepage_body_class( $classes ) {
	if ( bespoke_homepage_is_front_page() ) {
		$classes[] = 'bespoke-home-styled';
	}
	return $classes;
}

/* -------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/**
 * Is this the WordPress front page?
 *
 * Handles both setups:
 *   1. Settings ▸ Reading = "Your latest posts" — homepage is the
 *      blog index, and is_front_page() === is_home().
 *   2. Settings ▸ Reading = "A static page" — homepage is a chosen
 *      Page; is_front_page() returns true on that Page.
 *
 * Returns false on every other page / post / archive.
 */
function bespoke_homepage_is_front_page() {
	if ( is_admin() ) {
		return false;
	}
	return is_front_page();
}
