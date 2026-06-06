<?php
/**
 * BEspoke Customiser — Contact page styling.
 *
 * Wires Claude Design's "bespoke-contact-page.css" drop-in into the
 * contact page so the form + info card pick up the dark, mint-
 * accented look that matches the rest of the BEspoke site.
 *
 * What this file does (high-level for the non-dev designer):
 *   1) Enqueues assets/bespoke-contact-page.css ONLY on the contact
 *      page. It can't leak into other pages.
 *   2) Adds a `bs-contact-page` body class so the CSS's body-class
 *      selectors fire automatically — you DON'T have to manually
 *      add the `bs-contact` CSS class to the Elementor section
 *      (though doing so still works for finer scoping).
 *   3) Adds a `bespoke-contact-styled` body class for stable CSS
 *      specificity.
 *
 * Which page counts as the contact page?
 *   By default, this looks for a page with the slug `contact`. If
 *   your contact page uses a different slug (e.g. `get-in-touch`),
 *   set the option from a Code Snippet:
 *
 *     add_filter( 'bespoke_contact_page_slug', function() {
 *         return 'get-in-touch';
 *     });
 *
 *   Or, to designate a specific page by ID:
 *
 *     add_filter( 'bespoke_contact_page_id', function() {
 *         return 42;
 *     });
 *
 * Optional one-click bonus in Elementor:
 *   Open the contact page in Elementor → click the section that
 *   wraps the form + info card → Advanced ▸ CSS Classes → enter
 *   `bs-contact`. The CSS will scope itself to that section AND
 *   you'll get extra polish (sticky info card, eyebrow above the
 *   headline). The body-class detection above gives you the dark
 *   canvas and form styling regardless.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-contact.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Enqueue the drop-in stylesheet on the contact page only.
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'bespoke_contact_enqueue_styles' );
function bespoke_contact_enqueue_styles() {
	if ( ! bespoke_contact_is_contact_page() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}
	$css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-contact-page.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style(
		'bespoke-contact-page',
		BESPOKE_PLUGIN_URL . 'assets/bespoke-contact-page.css',
		[],
		$version
	);
}

/* -------------------------------------------------------------------------
 * 2. Body classes. We add TWO:
 *      - bs-contact-page       (the design CSS keys off this — gives
 *                               you the dark canvas + form styling
 *                               without any Elementor markup change)
 *      - bespoke-contact-styled (specificity hook for any future
 *                               override rules)
 * --------------------------------------------------------------------- */
add_filter( 'body_class', 'bespoke_contact_body_class' );
function bespoke_contact_body_class( $classes ) {
	if ( bespoke_contact_is_contact_page() ) {
		$classes[] = 'bs-contact-page';
		$classes[] = 'bespoke-contact-styled';
	}
	return $classes;
}

/* -------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/**
 * Is this the BEspoke contact page?
 *
 * Detection order:
 *   1. Filter-supplied page ID (`bespoke_contact_page_id`)
 *   2. Filter-supplied slug    (`bespoke_contact_page_slug`)
 *   3. Default slug "contact"
 *
 * Returns false everywhere else.
 */
function bespoke_contact_is_contact_page() {
	if ( is_admin() ) {
		return false;
	}
	if ( ! is_page() ) {
		return false;
	}

	$override_id = (int) apply_filters( 'bespoke_contact_page_id', 0 );
	if ( $override_id > 0 ) {
		return is_page( $override_id );
	}

	$slug = apply_filters( 'bespoke_contact_page_slug', 'contact' );
	$slug = is_string( $slug ) ? trim( $slug ) : '';
	if ( $slug === '' ) {
		return false;
	}

	return is_page( $slug );
}
