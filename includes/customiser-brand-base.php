<?php
/**
 * BEspoke Sport – Brand base styles
 *
 * Prints assets/bespoke-brand-base.css into the <head>.
 *
 * ── Why this file exists ──────────────────────────────────────────────────
 * These styles used to live in the **Simple Custom CSS and JS** plugin (NOT
 * Code Snippets — that plugin holds only PHP entries on this site). A survey
 * of the rendered pages on 5 Sep 2026 found it printing THREE entries, all
 * set to load site-wide, all identical on every page:
 *
 *   1. CSS · "BEspoke — Range Section Styles" — the KEY SIGNINGS heading
 *              and the product grid under it.
 *   2. CSS · "BEspoke Global Styles" — the brand foundation.
 *   3. JS  · "FPD Inside/Outside Button Fix" — DEAD. FPD renders
 *            nothing on the site any more (no fpd- markup on any page), yet
 *            that entry still installs a MutationObserver over the whole
 *            document body, subtree included, on every page load. It should
 *            simply be deleted, not migrated.
 *
 * Entries 1 and 2 are both carried here, in that order, because both style
 * the Key Signings heading and the later one wins. Moving them means the
 * whole front end travels with the plugin: one thing to upload, one thing in
 * git, and nothing that can be switched off by accident.
 *
 * ── Why it is printed inline rather than enqueued ─────────────────────────
 * Every other stylesheet in this plugin is a normal wp_enqueue_style(), and
 * that would be the tidier choice here too — except it would change how the
 * page renders.
 *
 * Simple Custom CSS and JS printed this inline in the <head>, AFTER
 * Elementor's per-page CSS block. Roughly 60% of its declarations are not marked
 * !important, so for those it was winning purely on source order. Enqueued
 * stylesheets print EARLIER than Elementor's inline block, so the same CSS
 * as a file would quietly start losing those ties — a scatter of small
 * visual changes with no obvious cause.
 *
 * Printing inline at wp_head priority 99 reproduces the old position: after
 * Elementor, before the Customizer's "Additional CSS" (WordPress prints that
 * at priority 101), which is exactly where it sat before. The stylesheet is
 * still a real file in assets/, so it is edited like any other.
 *
 * A happy side effect: inline CSS cannot be mangled by SiteGround
 * Optimizer's minifier, which has previously served a stale .min.css sibling
 * for hours after a file was re-uploaded (see the note in
 * customiser-master.php).
 *
 * ── After uploading this ──────────────────────────────────────────────────
 * In **Custom Code**, deactivate "BEspoke — Range Section Styles" and
 * "BEspoke Global Styles", and delete "FPD Inside/Outside Button Fix".
 * Those three are the only ACTIVE entries; the other eight are already off. Until the CSS entries are off, the same
 * rules are simply present twice — harmless, but pointless.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-brand-base.php
 * Included by:   bespoke-customiser.php
 */

defined( 'ABSPATH' ) || exit;

/**
 * Priority 99: after Elementor's per-page CSS, before the Customizer's
 * Additional CSS at 101. See the note above — this ordering is deliberate
 * and reproduces where the snippet used to sit.
 */
add_action( 'wp_head', 'bespoke_brand_base_print_css', 99 );

function bespoke_brand_base_print_css() {
	if ( is_admin() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}

	$path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-brand-base.css';
	if ( ! file_exists( $path ) ) {
		return;
	}

	// Read once per request and hold it, in case anything ever calls this
	// twice (a template part, a shortcode rendering a second header).
	static $css = null;
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	if ( null === $css ) {
		$css = (string) file_get_contents( $path );
	}
	if ( '' === trim( $css ) ) {
		return;
	}

	// wp_strip_all_tags() guards against a stray "</style>" ever ending up in
	// the file and breaking out of the block.
	echo "\n<style id=\"bespoke-brand-base\">\n" . wp_strip_all_tags( $css ) . "\n</style>\n";
}
