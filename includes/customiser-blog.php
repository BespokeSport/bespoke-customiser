<?php
/**
 * BEspoke Customiser — Blog / archive / single-post styling.
 *
 * Wires Claude Design's "bespoke-blog-page.css" drop-in into Astra
 * so the blog index, category / tag archives, and single articles
 * render in the dark, mint-accented look that matches the rest of
 * the BEspoke site.
 *
 * What this file does (high-level for the non-dev designer):
 *   1) Enqueues assets/bespoke-blog-page.css ONLY on post / blog
 *      templates (index, archive, category, tag, single post). It
 *      can't leak into pages, the shop, the cart, or anywhere else.
 *   2) Adds a `bespoke-blog-styled` body class for CSS specificity.
 *   3) On the blog index, injects the editorial masthead block
 *      (mint eyebrow + big headline + intro paragraph) so the
 *      design's full banner renders even though Astra's default
 *      blog index has no headline element of its own.
 *   4) Wraps the post loop in `.bs-blog-grid > .bs-post-list` so
 *      the magazine grid layout in the CSS kicks in. Without the
 *      wrapper, the grid rules don't apply (descendant selectors).
 *   5) Filters post_class so the FIRST article on the index gets
 *      the `bs-featured` class — the CSS uses that to lay it out
 *      as a 2-column split (image | text) at hero size.
 *
 * To change the headline + intro on the blog index:
 *   apply_filters( 'bespoke_blog_masthead_eyebrow',  'TEXT' )
 *   apply_filters( 'bespoke_blog_masthead_title',    'TEXT' )
 *   apply_filters( 'bespoke_blog_masthead_intro',    'TEXT' )
 * Drop a small snippet in Code Snippets to override any of them.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-blog.php
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Enqueue the drop-in stylesheet on every blog / archive / single
 *    post template.
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'bespoke_blog_enqueue_styles' );
function bespoke_blog_enqueue_styles() {
	if ( ! bespoke_blog_is_blog_context() ) {
		return;
	}
	if ( ! defined( 'BESPOKE_PLUGIN_URL' ) || ! defined( 'BESPOKE_PLUGIN_DIR' ) ) {
		return;
	}
	$css_path = BESPOKE_PLUGIN_DIR . 'assets/bespoke-blog-page.css';
	$version  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0';
	wp_enqueue_style(
		'bespoke-blog-page',
		BESPOKE_PLUGIN_URL . 'assets/bespoke-blog-page.css',
		[],
		$version
	);
}

/* -------------------------------------------------------------------------
 * 2. Body class — stable specificity hook.
 * --------------------------------------------------------------------- */
add_filter( 'body_class', 'bespoke_blog_body_class' );
function bespoke_blog_body_class( $classes ) {
	if ( bespoke_blog_is_blog_context() ) {
		$classes[] = 'bespoke-blog-styled';
	}
	return $classes;
}

/* -------------------------------------------------------------------------
 * 3. Mark the first article on the blog index with `bs-featured` so
 *    the CSS lays it out as a 2-column hero split. We only flip the
 *    flag on the first iteration of the main query on a list view —
 *    not on single posts, not on widgets, not on sidebars.
 * --------------------------------------------------------------------- */
add_filter( 'post_class', 'bespoke_blog_featured_class' );
function bespoke_blog_featured_class( $classes ) {
	static $first_marked = false;

	if ( $first_marked ) {
		return $classes;
	}
	if ( ! in_the_loop() || ! is_main_query() ) {
		return $classes;
	}
	if ( ! ( is_home() || is_category() || is_tag() || ( is_archive() && ! is_post_type_archive( 'product' ) ) ) ) {
		return $classes;
	}
	// Only on page 1 — page 2, 3, etc. don't have a "featured" post.
	if ( absint( get_query_var( 'paged' ) ) > 1 ) {
		return $classes;
	}

	$classes[]    = 'bs-featured';
	$first_marked = true;
	return $classes;
}

/* -------------------------------------------------------------------------
 * 4. Inject the editorial masthead on the blog index. Astra's default
 *    blog page emits no banner element of its own — we draw it here.
 *    Category / tag archives keep Astra's own .ast-archive-description
 *    which the drop-in CSS already styles.
 * --------------------------------------------------------------------- */
add_action( 'astra_primary_content_top', 'bespoke_blog_render_masthead', 5 );
function bespoke_blog_render_masthead() {
	if ( ! is_home() ) {
		return;
	}
	if ( absint( get_query_var( 'paged' ) ) > 1 ) {
		return;
	}

	$eyebrow = apply_filters( 'bespoke_blog_masthead_eyebrow', __( 'The Locker Room · Field notes', 'bespoke-customiser' ) );
	$title   = apply_filters( 'bespoke_blog_masthead_title',   __( 'The Locker Room', 'bespoke-customiser' ) );
	$intro   = apply_filters( 'bespoke_blog_masthead_intro',   __( 'Field notes from the BEspoke workshop — design tips, club spotlights, how-tos, and the occasional opinion on what makes great match-day kit.', 'bespoke-customiser' ) );

	echo '<header class="bs-blog-masthead">';
	if ( $eyebrow !== '' ) {
		echo '<span class="bs-eyebrow">' . esc_html( $eyebrow ) . '</span>';
	}
	if ( $title !== '' ) {
		echo '<h1>' . esc_html( $title ) . '</h1>';
	}
	if ( $intro !== '' ) {
		echo '<p class="bs-blog-intro">' . esc_html( $intro ) . '</p>';
	}
	echo '</header>';
}

/* -------------------------------------------------------------------------
 * 5. Wrap the blog / archive loop in `.bs-blog-grid > .bs-post-list`
 *    so the CSS grid layout (3-up, 4-up wide, 2-up tablet, 1-up
 *    mobile) actually applies. The CSS descendant selectors need
 *    these two wrapper elements to fire.
 *
 *    Opens the wrappers via astra_primary_content_top (priority 8,
 *    after the masthead) and closes them via
 *    astra_primary_content_bottom (priority 50, after Astra's own
 *    content but before pagination).
 * --------------------------------------------------------------------- */
add_action( 'astra_primary_content_top',    'bespoke_blog_open_grid_wrapper',  8 );
add_action( 'astra_primary_content_bottom', 'bespoke_blog_close_grid_wrapper', 5 );

function bespoke_blog_open_grid_wrapper() {
	if ( ! bespoke_blog_is_list_view() ) {
		return;
	}
	echo '<div class="bs-blog-grid"><div class="bs-post-list">';
}

function bespoke_blog_close_grid_wrapper() {
	if ( ! bespoke_blog_is_list_view() ) {
		return;
	}
	echo '</div></div>';
}

/* -------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/**
 * Is this any kind of blog / post page?
 *   - blog index (static or default)
 *   - category, tag, or date archive
 *   - single post
 * Returns false on pages, products, the shop, the cart, etc.
 */
function bespoke_blog_is_blog_context() {
	if ( is_admin() ) {
		return false;
	}
	if ( is_singular( 'post' ) ) {
		return true;
	}
	if ( is_home() ) {
		return true;
	}
	if ( is_category() || is_tag() ) {
		return true;
	}
	if ( is_archive() && ! is_post_type_archive( 'product' ) && function_exists( 'is_shop' ) && ! is_shop() ) {
		return true;
	}
	return false;
}

/**
 * Is this a list-view (index / archive / category / tag)?
 * Used to decide when to wrap the loop in `.bs-blog-grid`.
 */
function bespoke_blog_is_list_view() {
	if ( is_admin() ) {
		return false;
	}
	if ( is_singular() ) {
		return false;
	}
	return is_home()
		|| is_category()
		|| is_tag()
		|| ( is_archive() && ! is_post_type_archive( 'product' ) );
}
