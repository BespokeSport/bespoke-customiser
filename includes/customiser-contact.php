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
 * WPForms compatibility layer.
 *
 * The Claude Design CSS file is written for Elementor's form widget
 * (`.elementor-form .elementor-field-group ...`). The actual contact
 * page on the BEspoke site uses the WPForms widget instead — so
 * the form-styling rules in the CSS file don't match anything and
 * the form keeps WPForms' default light-theme look.
 *
 * This inline rule-set re-paints WPForms using the same BEspoke
 * design tokens (dark ink inputs, mono labels, mint focus ring,
 * mint submit button). Printed in <head> via wp_head so it loads
 * after the main stylesheet AND beats WPForms' defaults on
 * specificity (body.bespoke-contact-styled + .wpforms-* is more
 * specific than .wpforms-* alone).
 *
 * Kept in PHP rather than the CSS file so re-running Claude Design
 * and overwriting bespoke-contact-page.css leaves the glue intact.
 * --------------------------------------------------------------- */
add_action( 'wp_head', 'bespoke_contact_print_wpforms_compat_css', 99 );
function bespoke_contact_print_wpforms_compat_css() {
	if ( ! bespoke_contact_is_contact_page() ) {
		return;
	}
	?>
	<style id="bespoke-contact-wpforms-compat">
	/* ─── Page canvas — force dark behind every Elementor wrapper ─────
	   Elementor sections on the contact page each carry their own
	   background-color the designer set in the editor; if any is
	   white, it punches through our body-level dark BG. Force every
	   Elementor section / column / container on the contact page to
	   transparent so the dark canvas wins, EXCEPT sections whose
	   class includes the word 'marquee' (so the mint scrolling
	   marquee at the top of the page keeps its mint background).
	*/
	body.bespoke-contact-styled .elementor-section:not([class*="marquee"]):not([class*="bs-marquee"]),
	body.bespoke-contact-styled .e-con:not([class*="marquee"]):not([class*="bs-marquee"]),
	body.bespoke-contact-styled .elementor-column,
	body.bespoke-contact-styled .e-con-inner {
		background-color: transparent !important;
		background-image: none !important;
	}

	/* Text fallback — any default-coloured paragraph / heading inside
	   the contact page should read as white-on-dark, not the body
	   theme's default colour. */
	body.bespoke-contact-styled .elementor-widget-heading h1,
	body.bespoke-contact-styled .elementor-widget-heading h2,
	body.bespoke-contact-styled .elementor-widget-heading h3,
	body.bespoke-contact-styled .elementor-widget-heading h4,
	body.bespoke-contact-styled .elementor-widget-text-editor,
	body.bespoke-contact-styled .elementor-widget-text-editor p {
		color: #fff !important;
	}
	body.bespoke-contact-styled .elementor-widget-icon-box .elementor-icon-box-title,
	body.bespoke-contact-styled .elementor-widget-icon-box .elementor-icon-box-description {
		color: #fff !important;
	}

	/* WPForms — Container */
	body.bespoke-contact-styled .wpforms-container {
		background: transparent !important;
		max-width: 100% !important;
	}
	body.bespoke-contact-styled .wpforms-container .wpforms-form {
		background: transparent !important;
	}

	/* Field rows */
	body.bespoke-contact-styled .wpforms-field {
		padding: 0 0 18px !important;
	}

	/* Labels */
	body.bespoke-contact-styled .wpforms-field-label,
	body.bespoke-contact-styled .wpforms-field > label {
		font-family: 'JetBrains Mono', SFMono-Regular, monospace !important;
		font-size: 11px !important;
		letter-spacing: 0.12em !important;
		text-transform: uppercase !important;
		color: rgba(255,255,255,0.55) !important;
		font-weight: 500 !important;
		margin-bottom: 10px !important;
		display: block !important;
	}
	body.bespoke-contact-styled .wpforms-field-sublabel {
		color: rgba(255,255,255,0.40) !important;
		font-size: 11px !important;
	}

	/* Inputs + textarea */
	body.bespoke-contact-styled .wpforms-field input[type="text"],
	body.bespoke-contact-styled .wpforms-field input[type="email"],
	body.bespoke-contact-styled .wpforms-field input[type="tel"],
	body.bespoke-contact-styled .wpforms-field input[type="url"],
	body.bespoke-contact-styled .wpforms-field input[type="number"],
	body.bespoke-contact-styled .wpforms-field select,
	body.bespoke-contact-styled .wpforms-field textarea {
		width: 100% !important;
		background: #0E0E10 !important;
		border: 1px solid rgba(255,255,255,0.10) !important;
		border-radius: 10px !important;
		color: #fff !important;
		padding: 15px 16px !important;
		font-family: 'Inter', system-ui, sans-serif !important;
		font-size: 15px !important;
		line-height: 1.5 !important;
		box-shadow: none !important;
		transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
		-webkit-appearance: none;
		appearance: none;
	}
	body.bespoke-contact-styled .wpforms-field textarea {
		min-height: 160px !important;
		resize: vertical !important;
	}
	body.bespoke-contact-styled .wpforms-field input::placeholder,
	body.bespoke-contact-styled .wpforms-field textarea::placeholder {
		color: rgba(255,255,255,0.40) !important;
	}
	body.bespoke-contact-styled .wpforms-field input:focus,
	body.bespoke-contact-styled .wpforms-field textarea:focus,
	body.bespoke-contact-styled .wpforms-field select:focus {
		outline: 0 !important;
		border-color: #7FECB8 !important;
		box-shadow: 0 0 0 3px rgba(127,236,184,0.10) !important;
	}

	/* Submit button — mint pill */
	body.bespoke-contact-styled .wpforms-submit-container {
		padding-top: 6px !important;
	}
	body.bespoke-contact-styled button.wpforms-submit,
	body.bespoke-contact-styled .wpforms-submit-container button[type="submit"] {
		width: 100% !important;
		background: #7FECB8 !important;
		color: #0E0E10 !important;
		border: 0 !important;
		border-radius: 999px !important;
		padding: 17px 28px !important;
		font-family: 'Inter', system-ui, sans-serif !important;
		font-size: 14px !important;
		font-weight: 700 !important;
		letter-spacing: 0.1em !important;
		text-transform: uppercase !important;
		cursor: pointer !important;
		box-shadow: none !important;
		transition: filter 0.2s ease !important;
	}
	body.bespoke-contact-styled button.wpforms-submit:hover {
		filter: brightness(1.08) !important;
	}

	/* Validation + success messages */
	body.bespoke-contact-styled .wpforms-error,
	body.bespoke-contact-styled label.wpforms-error {
		color: #E66467 !important;
		font-family: 'JetBrains Mono', SFMono-Regular, monospace !important;
		font-size: 11px !important;
		letter-spacing: 0.06em !important;
		margin-top: 6px !important;
		display: block !important;
	}
	body.bespoke-contact-styled .wpforms-field input.wpforms-error,
	body.bespoke-contact-styled .wpforms-field textarea.wpforms-error,
	body.bespoke-contact-styled .wpforms-field select.wpforms-error {
		border-color: #E66467 !important;
	}
	body.bespoke-contact-styled div.wpforms-confirmation-container-full {
		background: rgba(127,236,184,0.10) !important;
		border: 1px solid #7FECB8 !important;
		border-radius: 10px !important;
		color: #7FECB8 !important;
		padding: 14px 16px !important;
		font-size: 14px !important;
	}
	</style>
	<?php
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
