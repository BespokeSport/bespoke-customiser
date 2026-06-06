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
	if ( ! bespoke_blog_is_blog_context() ) {
		return $classes;
	}
	$classes[] = 'bespoke-blog-styled';

	// Spoof body.blog / body.single-post on the designated Page so
	// the design CSS — which keys off WordPress's natural body
	// classes — fires automatically. Without this, the Page at
	// /the-locker-room/ would have body.page but neither body.blog
	// nor body.single-post, so none of the styling would apply.
	if ( bespoke_blog_is_designated_index_page() && ! in_array( 'blog', $classes, true ) ) {
		$classes[] = 'blog';
	}
	return $classes;
}

/* -------------------------------------------------------------------------
 * Elementor Posts widget compatibility layer.
 *
 * The /the-locker-room/ page renders its posts via Elementor's
 * `elementor-widget-posts` widget, which emits its OWN markup
 * (.elementor-posts-container, .elementor-post, .elementor-post__title
 * etc) rather than the standard WP loop markup the Claude Design
 * CSS keys off. Without this glue, the dark canvas applies but the
 * post cards stay light.
 *
 * This inline rule-set re-paints the Elementor Posts widget using
 * the BEspoke design tokens. It's printed in <head> via wp_head so
 * it loads after the main stylesheet AND wins on specificity (the
 * combined `body.bespoke-blog-styled` + `.elementor-post` selector
 * has higher specificity than Elementor's defaults).
 *
 * Kept in PHP rather than the CSS file so re-running Claude Design
 * and overwriting bespoke-blog-page.css leaves the glue intact.
 * --------------------------------------------------------------- */
add_action( 'wp_head', 'bespoke_blog_print_elementor_compat_css', 99 );
function bespoke_blog_print_elementor_compat_css() {
	if ( ! bespoke_blog_is_blog_context() ) {
		return;
	}
	?>
	<style id="bespoke-blog-elementor-compat">
	/* Dark canvas on the designated blog page even though it's a
	   page-template-default page (the regular CSS keys off body.blog
	   which IS present thanks to the spoof above; this is a safety
	   net for any selector chain where the spoof isn't enough). */
	body.bespoke-blog-styled,
	body.bespoke-blog-styled .site,
	body.bespoke-blog-styled .site-content,
	body.bespoke-blog-styled #primary,
	body.bespoke-blog-styled .content-area {
		background: #0E0E10 !important;
		color: rgba(255,255,255,0.85) !important;
	}

	/* Elementor Posts widget — grid of post cards */
	body.bespoke-blog-styled .elementor-widget-posts .elementor-posts-container {
		gap: 20px !important;
	}
	body.bespoke-blog-styled .elementor-post {
		background: #141417 !important;
		border: 1px solid rgba(255,255,255,0.10) !important;
		border-radius: 12px !important;
		overflow: hidden !important;
		transition: border-color 0.2s ease, transform 0.2s ease;
	}
	body.bespoke-blog-styled .elementor-post:hover {
		border-color: rgba(127,236,184,0.45) !important;
		transform: translateY(-2px) !important;
	}
	body.bespoke-blog-styled .elementor-post__thumbnail img {
		aspect-ratio: 16/10 !important;
		object-fit: cover !important;
		width: 100% !important;
		background: #1A1A1E !important;
	}
	body.bespoke-blog-styled .elementor-post__text {
		padding: 20px 22px 24px !important;
	}
	body.bespoke-blog-styled .elementor-post__title,
	body.bespoke-blog-styled .elementor-post__title a {
		color: #fff !important;
		font-family: 'Inter', system-ui, sans-serif !important;
		font-weight: 700 !important;
		font-size: 22px !important;
		line-height: 1.12 !important;
		letter-spacing: -0.01em !important;
		text-decoration: none !important;
	}
	body.bespoke-blog-styled .elementor-post__title a:hover {
		color: #7FECB8 !important;
	}
	body.bespoke-blog-styled .elementor-post__meta-data {
		font-family: 'JetBrains Mono', SFMono-Regular, monospace !important;
		font-size: 10px !important;
		letter-spacing: 0.12em !important;
		text-transform: uppercase !important;
		color: rgba(255,255,255,0.55) !important;
		margin-bottom: 14px !important;
	}
	body.bespoke-blog-styled .elementor-post__meta-data a {
		color: #7FECB8 !important;
	}
	body.bespoke-blog-styled .elementor-post__excerpt,
	body.bespoke-blog-styled .elementor-post__excerpt p {
		font-size: 14px !important;
		line-height: 1.55 !important;
		color: rgba(255,255,255,0.55) !important;
		margin: 0 !important;
	}
	body.bespoke-blog-styled .elementor-post__read-more {
		margin-top: 18px !important;
		font-family: 'JetBrains Mono', SFMono-Regular, monospace !important;
		font-size: 11px !important;
		letter-spacing: 0.12em !important;
		text-transform: uppercase !important;
		color: #fff !important;
		text-decoration: none !important;
		display: inline-flex !important;
		align-items: center !important;
		gap: 8px !important;
	}
	body.bespoke-blog-styled .elementor-post__read-more:hover {
		color: #7FECB8 !important;
	}
	</style>
	<?php
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
	// Fires on the blog index AND on the Page the designer has
	// nominated as the locker room (e.g. /the-locker-room/).
	if ( ! is_home() && ! bespoke_blog_is_designated_index_page() ) {
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
 *   - the Page designated as the blog index (default slug
 *     'the-locker-room' — override via the
 *     `bespoke_blog_index_page_slug` / `bespoke_blog_index_page_id`
 *     filters)
 * Returns false on other pages, products, the shop, the cart, etc.
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
	if ( bespoke_blog_is_designated_index_page() ) {
		return true;
	}
	return false;
}

/**
 * Is this a list-view (index / archive / category / tag / the Page
 * the user has designated as the blog index)? Used to decide when
 * to wrap the loop in `.bs-blog-grid` and inject the masthead.
 */
function bespoke_blog_is_list_view() {
	if ( is_admin() ) {
		return false;
	}
	if ( is_singular( 'post' ) ) {
		return false;
	}
	if ( is_home() || is_category() || is_tag()
		|| ( is_archive() && ! is_post_type_archive( 'product' ) ) ) {
		return true;
	}
	return bespoke_blog_is_designated_index_page();
}

/**
 * Is the current request the Page the user has designated as the
 * blog index? The BEspoke site has its blog at /the-locker-room/
 * built with Elementor (as a regular Page using Elementor's Posts
 * widget) rather than the standard WP Posts page. We treat that
 * Page as a blog index so the dark styling, masthead, and post
 * grid layout all apply.
 *
 * Override the default slug:
 *     add_filter( 'bespoke_blog_index_page_slug', fn() => 'news' );
 * Or override by post ID (faster, slug-independent):
 *     add_filter( 'bespoke_blog_index_page_id',   fn() => 5273 );
 */
function bespoke_blog_is_designated_index_page() {
	if ( ! is_page() ) {
		return false;
	}
	$page_id = (int) apply_filters( 'bespoke_blog_index_page_id', 0 );
	if ( $page_id > 0 ) {
		return is_page( $page_id );
	}
	$slug = apply_filters( 'bespoke_blog_index_page_slug', 'the-locker-room' );
	$slug = is_string( $slug ) ? trim( $slug ) : '';
	if ( $slug === '' ) {
		return false;
	}
	return is_page( $slug );
}
