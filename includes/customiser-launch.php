<?php
/**
 * BEspoke Customiser — Launch
 *
 * Everything the live shop needs from staging, in one file, applied in small
 * steps with one Undo — so launch day is a handful of clicks, not a checklist
 * of twenty manual jobs on the live site (Nick, 19 Oct 2026: "as simple and
 * quick and easy as possible").
 *
 * Why not copy staging's database over live: it would lose every order and
 * customer since staging was copied, the pages made on live since, and live's
 * payment and Meta connections. So the database is never moved; this carries
 * what changed, by web address, and records what it replaced.
 *
 *   On STAGING — Designs → Launch → "Make the launch file". It holds:
 *     - every published product (the Copy to Live file, with what's needed to
 *       make the ones live hasn't got yet, as drafts)
 *     - the customiser's settings (artwork, placements, product types)
 *     - the design library, the font list, and the artwork and font FILES
 *       (listed, not packed: live fetches them from staging)
 *     - every Elementor page and template, with the products, categories,
 *       templates and pictures they point at named by web address
 *     - the review settings
 *
 *   On LIVE — the same page, after the plugin is uploaded:
 *     1. Holding page: on. Visitors see "We're updating our shop" (503, so
 *        Google treats it as temporary); anyone logged in sees the real site.
 *     2. Upload the file. Nothing is written: you get the list of changes,
 *        section by section, all ticked except where live's copy is newer.
 *     3. Start. A progress bar works through it a few items at a time, so
 *        nothing times out; the SEO packs run at the end, then the four
 *        dropped products go to the bin and Fancy Product Designer is
 *        switched off.
 *     4. Look round the site while logged in, then Publish: the new
 *        products and pages go live together.
 *     5. Holding page: off.
 *   Undo puts back everything the run replaced, in reverse order.
 *
 * The product parts reuse Copy to Live (customiser-copy.php), and the SEO
 * parts the SEO packs (customiser-seo-pack.php).
 *
 * @package BEspoke_Customiser
 */

defined( 'ABSPATH' ) || exit;

const BESPOKE_LAUNCH_CAP     = 'manage_options';
const BESPOKE_LAUNCH_FORMAT  = 1;
const BESPOKE_LAUNCH_FILE    = 'bespoke_launch_file';     // the uploaded file, waiting (autoload off)
const BESPOKE_LAUNCH_RUN     = 'bespoke_launch_run';      // the run: jobs, results, undo records
const BESPOKE_LAUNCH_HOLDING = 'bespoke_launch_holding';  // '1' while the holding page is up

/** The upload folders whose files travel: design artwork, product artwork, fonts. */
const BESPOKE_LAUNCH_DIRS = [ 'bespoke-designs', 'bespoke-product-assets', 'bespoke-fonts' ];

/** File types those folders may hold. Anything else is never fetched. */
const BESPOKE_LAUNCH_EXT = [ 'png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'svg', 'ttf', 'otf', 'woff', 'woff2' ];

/**
 * Products live has and the new shop drops (Nick, 6 Sep 2026): they go to
 * the bin, never deleted, so Undo brings them back.
 */
const BESPOKE_LAUNCH_DROPPED = [ 'boys-creator-pack', 'girls-creator-pack', 'personalised-thermal-cups', 'personalised-water-bottles' ];

/** The review settings, as set on staging (Nick, 19 Oct 2026): on, verified owners only, held for approval. */
const BESPOKE_LAUNCH_REVIEW_OPTIONS = [
	'woocommerce_enable_reviews',
	'woocommerce_review_rating_verification_label',
	'woocommerce_review_rating_verification_required',
	'woocommerce_enable_review_rating',
	'woocommerce_review_rating_required',
	'comment_moderation',
	'comment_previously_approved',
];

/** Rank Math fields a page carries. Never the canonical: on staging the armbands page's is still broken. */
const BESPOKE_LAUNCH_PAGE_SEO = [ 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_robots' ];

/* =========================================================================
   HOLDING PAGE
   Nick's page, built in rather than an Elementor template: the plugin is the
   first thing to reach live, so the holding page can go up before anything
   else changes. Without Elementor's Canvas layout its Maintenance Mode let
   the shop and product templates show through (checked 19 Oct 2026); this
   answers before any template is chosen.
   ========================================================================= */

/** Is the holding page up? */
function bespoke_launch_holding_on() {
	return '1' === get_option( BESPOKE_LAUNCH_HOLDING );
}

/** Turn the holding page on or off, and clear the page cache so it shows at once. */
function bespoke_launch_set_holding( $on ) {
	update_option( BESPOKE_LAUNCH_HOLDING, $on ? '1' : '0', true );
	bespoke_launch_purge_caches();
}

add_action( 'template_redirect', function () {
	$preview = isset( $_GET['bespoke_holding'] ) && current_user_can( BESPOKE_LAUNCH_CAP ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $preview ) {
		if ( ! bespoke_launch_holding_on() ) {
			return;
		}
		// Staff see the real shop, so they can check it before it opens.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( is_robots() || is_feed() ) {
			return;
		}
		// 503 tells Google the shop is only briefly away; nothing is cached.
		status_header( 503 );
		header( 'Retry-After: 3600' );
	}
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	echo bespoke_launch_holding_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}, 0 );

/** Show anyone logged in that the holding page is up. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! bespoke_launch_holding_on() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$bar->add_node( [
		'id'    => 'bespoke-holding',
		'title' => '<span style="color:#5dcaa5;font-weight:700;">Holding page ON</span>',
		'href'  => admin_url( 'edit.php?post_type=bespoke_design&page=bespoke-launch' ),
	] );
}, 100 );

/** The holding page itself: Nick's wording (19 Oct 2026), black and mint, Anton and Roboto. */
function bespoke_launch_holding_html() {
	$logo_id  = (int) get_theme_mod( 'custom_logo' );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	$name     = get_bloginfo( 'name' );
	$facebook = 'https://www.facebook.com/BEspokeSportUK';
	$insta    = 'https://www.instagram.com/bespokesportuk';
	ob_start();
	?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Back shortly – <?php echo esc_html( $name ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Roboto:ital,wght@0,400;0,700;1,700&display=swap">
<style>
	*{box-sizing:border-box}
	html,body{margin:0;min-height:100%;background:#0d0d0d;color:#fff}
	body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;
		font-family:Roboto,"Helvetica Neue",Arial,sans-serif;text-align:center}
	main{max-width:760px}
	.logo{display:block;margin:0 auto 36px;max-width:120px;height:auto}
	h1{font-family:Anton,"Arial Narrow",sans-serif;font-weight:400;text-transform:uppercase;
		font-size:clamp(38px,8vw,64px);line-height:1;letter-spacing:.01em;margin:0 0 22px}
	p{font-size:clamp(17px,2.6vw,21px);line-height:1.5;margin:0 auto 14px;max-width:44ch;font-weight:700;font-style:italic}
	.buttons{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:30px}
	a.button{display:inline-block;padding:14px 22px;border-radius:999px;background:#5dcaa5;color:#0d0d0d;
		font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.06em;font-size:15px}
	a.button:hover,a.button:focus-visible{background:#fff;outline:none}
</style>
</head>
<body>
<main>
	<?php if ( $logo_url ) : ?>
		<img class="logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?>">
	<?php endif; ?>
	<h1>We're updating our shop</h1>
	<p>We'll be back very shortly with our brand-new customiser.</p>
	<p>Want to order in the meantime? Drop us a message and we'll sort it for you.</p>
	<div class="buttons">
		<a class="button" href="<?php echo esc_url( $facebook ); ?>" rel="noopener">Message us on Facebook</a>
		<a class="button" href="<?php echo esc_url( $insta ); ?>" rel="noopener">Message us on Instagram</a>
	</div>
</main>
</body>
</html>
	<?php
	return ob_get_clean();
}

/** Empty every cache that could show a visitor the old page: SiteGround's, Elementor's, WooCommerce's. */
function bespoke_launch_purge_caches() {
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
	}
	if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients();
	}
	wp_cache_flush();
}

/* =========================================================================
   SMALL HELPERS
   ========================================================================= */

/** This site's uploads folder and address. */
function bespoke_launch_uploads() {
	$u = wp_upload_dir( null, false );
	return [ 'dir' => trailingslashit( $u['basedir'] ), 'url' => trailingslashit( $u['baseurl'] ) ];
}

/**
 * Every string in a value with the other site's addresses swapped for this
 * one's: its uploads address first (so files point here), then its home.
 *
 * @param  mixed $value Anything.
 * @param  array $from  [ 'home' => …, 'uploads' => … ] of the other site.
 * @return mixed
 */
function bespoke_launch_swap_urls( $value, $from ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = bespoke_launch_swap_urls( $v, $from );
		}
		return $value;
	}
	if ( ! is_string( $value ) || '' === $value ) {
		return $value;
	}
	$here  = bespoke_launch_uploads();
	$pairs = [];
	if ( ! empty( $from['uploads'] ) ) {
		$pairs[ trailingslashit( $from['uploads'] ) ] = $here['url'];
	}
	if ( ! empty( $from['home'] ) ) {
		$pairs[ untrailingslashit( $from['home'] ) ] = untrailingslashit( home_url() );
	}
	foreach ( $pairs as $a => $b ) {
		if ( $a === $b || '' === $a ) {
			continue;
		}
		$value = str_replace( $a, $b, $value );
		// JSON inside a value escapes its slashes.
		$value = str_replace( str_replace( '/', '\/', $a ), str_replace( '/', '\/', $b ), $value );
	}
	return $value;
}

/** The other site's addresses, from a launch file. */
function bespoke_launch_from( $file ) {
	return [
		'home'    => (string) ( $file['site'] ?? '' ),
		'uploads' => (string) ( $file['uploads'] ?? '' ),
	];
}

/** A product on this site by web address (drafts included), or 0. */
function bespoke_launch_find_post( $type, $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return 0;
	}
	$found = get_posts( [
		'post_type'        => $type,
		'name'             => $slug,
		'post_status'      => [ 'publish', 'draft', 'pending', 'private', 'future' ],
		'numberposts'      => 1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	] );
	return $found ? (int) $found[0] : 0;
}

/** Is this a path inside one of the launch folders, with an allowed file type? */
function bespoke_launch_safe_path( $rel ) {
	$rel = str_replace( '\\', '/', (string) $rel );
	if ( '' === $rel || false !== strpos( $rel, '..' ) || '/' === $rel[0] ) {
		return false;
	}
	$top = strtok( $rel, '/' );
	if ( ! in_array( $top, BESPOKE_LAUNCH_DIRS, true ) ) {
		return false;
	}
	return in_array( strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ), BESPOKE_LAUNCH_EXT, true );
}

/* =========================================================================
   MAKING THE FILE (on staging)
   ========================================================================= */

/** Every artwork and font file in the launch folders, with its size and fingerprint. */
function bespoke_launch_read_files() {
	$up  = bespoke_launch_uploads();
	$out = [];
	foreach ( BESPOKE_LAUNCH_DIRS as $dir ) {
		$base = $up['dir'] . $dir;
		if ( ! is_dir( $base ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( ! $f->isFile() ) {
				continue;
			}
			$rel = $dir . '/' . ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( $base ) ) ), '/' );
			if ( ! bespoke_launch_safe_path( $rel ) ) {
				continue;
			}
			$out[] = [ 'path' => $rel, 'size' => (int) $f->getSize(), 'md5' => (string) md5_file( $f->getPathname() ) ];
		}
	}
	usort( $out, function ( $a, $b ) {
		return strcmp( $a['path'], $b['path'] );
	} );
	return $out;
}

/** The design library: each design by web address, its meta, and its picker thumbnail as a picture to fetch. */
function bespoke_launch_read_designs() {
	// Published only: customers never see the rest, and a draft has no web
	// address to be matched by (staging had five, leftover copies).
	$ids = get_posts( [
		'post_type'        => 'bespoke_design',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'orderby'          => 'menu_order title',
		'order'            => 'ASC',
		'fields'           => 'ids',
		'suppress_filters' => true,
	] );
	$out = [];
	foreach ( $ids as $id ) {
		$meta = [];
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( 0 !== strpos( $key, '_bespoke_' ) || '_bespoke_thumb_id' === $key ) {
				continue;
			}
			$meta[ $key ] = maybe_unserialize( $values[0] );
		}
		ksort( $meta );
		$out[] = [
			'slug'       => (string) get_post_field( 'post_name', $id ),
			'title'      => (string) get_post_field( 'post_title', $id ),
			'status'     => (string) get_post_status( $id ),
			'menu_order' => (int) get_post_field( 'menu_order', $id ),
			'meta'       => $meta,
			'thumb'      => bespoke_copy_image_ref( get_post_meta( $id, '_bespoke_thumb_id', true ) ),
		];
	}
	return $out;
}

/** Element settings that hold the IDs of posts (products, templates) or terms (categories). */
function bespoke_launch_ref_kind( $key ) {
	$key = (string) $key;
	if ( 'template_id' === $key || preg_match( '/(^|_)(posts_ids|exclude_ids)$/', $key ) ) {
		return 'post';
	}
	if ( preg_match( '/(include_term_ids|exclude_term_ids)$/', $key ) ) {
		return 'term';
	}
	return '';
}

/**
 * Walk Elementor data, calling $visit on every settings array (and
 * collecting what it returns into $refs).
 */
function bespoke_launch_el_walk( &$elements, $visit ) {
	if ( ! is_array( $elements ) ) {
		return;
	}
	foreach ( $elements as &$el ) {
		if ( ! is_array( $el ) ) {
			continue;
		}
		if ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) {
			$visit( $el['settings'] );
		}
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			bespoke_launch_el_walk( $el['elements'], $visit );
		}
	}
	unset( $el );
}

/** The posts and terms an Elementor document names by ID, by web address instead. */
function bespoke_launch_el_refs( $data, $settings ) {
	$refs  = [ 'posts' => [], 'terms' => [] ];
	$visit = function ( &$s ) use ( &$refs ) {
		foreach ( $s as $key => $value ) {
			$kind = bespoke_launch_ref_kind( $key );
			if ( '' === $kind ) {
				continue;
			}
			foreach ( (array) $value as $id ) {
				$id = (int) $id;
				if ( ! $id ) {
					continue;
				}
				if ( 'post' === $kind ) {
					$p = get_post( $id );
					if ( $p ) {
						$refs['posts'][ $id ] = [ 'type' => $p->post_type, 'slug' => $p->post_name ];
					}
				} else {
					$t = get_term( $id );
					if ( $t && ! is_wp_error( $t ) ) {
						$refs['terms'][ $id ] = [ 'taxonomy' => $t->taxonomy, 'slug' => $t->slug, 'name' => $t->name ];
					}
				}
			}
		}
	};
	bespoke_launch_el_walk( $data, $visit );
	if ( is_array( $settings ) ) {
		$visit( $settings );
	}
	return $refs;
}

/** Every Elementor page and template on this site, ready to travel. */
function bespoke_launch_read_elementor() {
	$ids = get_posts( [
		'post_type'        => [ 'page', 'elementor_library' ],
		'post_status'      => [ 'publish' ],
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
		'meta_query'       => [ [ 'key' => '_elementor_data', 'compare' => 'EXISTS' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
	] );
	$kit = (int) get_option( 'elementor_active_kit' );
	if ( $kit && ! in_array( $kit, $ids, true ) ) {
		$ids[] = $kit;
	}
	$front = (int) get_option( 'page_on_front' );
	$out   = [];
	foreach ( $ids as $id ) {
		$raw  = get_post_meta( $id, '_elementor_data', true );
		$data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			$data = [];
		}
		$settings = get_post_meta( $id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : [];
		$seo      = [];
		foreach ( BESPOKE_LAUNCH_PAGE_SEO as $key ) {
			$v = get_post_meta( $id, $key, true );
			if ( '' !== $v && null !== $v ) {
				$seo[ $key ] = $v;
			}
		}
		$conditions = get_post_meta( $id, '_elementor_conditions', true );
		$lib_types  = wp_get_object_terms( $id, 'elementor_library_type', [ 'fields' => 'slugs' ] );
		$out[] = [
			'post_type'     => get_post_type( $id ),
			'slug'          => (string) get_post_field( 'post_name', $id ),
			'title'         => (string) get_post_field( 'post_title', $id ),
			'content'       => (string) get_post_field( 'post_content', $id ),
			'modified'      => (string) get_post_field( 'post_modified_gmt', $id ),
			'front'         => $id === $front,
			'kit'           => $id === $kit,
			'template_type' => (string) get_post_meta( $id, '_elementor_template_type', true ),
			'library_type'  => is_wp_error( $lib_types ) ? [] : array_values( (array) $lib_types ),
			'page_template' => (string) get_post_meta( $id, '_wp_page_template', true ),
			'version'       => (string) get_post_meta( $id, '_elementor_version', true ),
			'conditions'    => is_array( $conditions ) ? array_values( $conditions ) : [],
			'data'          => $data,
			'settings'      => $settings,
			'seo'           => $seo,
			'refs'          => bespoke_launch_el_refs( $data, $settings ),
		];
	}
	return $out;
}

/** The whole launch file. */
function bespoke_launch_make_file() {
	$up       = bespoke_launch_uploads();
	$products = [];
	$ids      = get_posts( [
		'post_type'        => 'product',
		'post_status'      => [ 'publish' ],
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'fields'           => 'ids',
		'suppress_filters' => true,
	] );
	foreach ( $ids as $id ) {
		$fields = bespoke_copy_read_product( $id );
		if ( ! $fields ) {
			continue;
		}
		$extra = [];
		foreach ( [ '_links_to', '_links_to_target', 'rank_math_robots' ] as $key ) {
			$v = get_post_meta( $id, $key, true );
			if ( '' !== $v && null !== $v ) {
				$extra[ $key ] = $v;
			}
		}
		$products[] = [
			'id'           => $id,
			'slug'         => get_post_field( 'post_name', $id ),
			'name'         => get_the_title( $id ),
			'modified'     => (string) get_post_field( 'post_modified_gmt', $id ),
			'fields'       => $fields,
			'design_allow' => bespoke_copy_design_refs( $id ),
			'create'       => bespoke_copy_product_shape( $id ),
			'extra'        => $extra,
		];
	}
	$reviews = [];
	foreach ( BESPOKE_LAUNCH_REVIEW_OPTIONS as $opt ) {
		$reviews[ $opt ] = get_option( $opt );
	}
	return [
		'kind'      => 'bespoke-launch',
		'format'    => BESPOKE_LAUNCH_FORMAT,
		'site'      => home_url(),
		'uploads'   => $up['url'],
		'generated' => current_time( 'mysql' ),
		'copy'      => [
			'format'   => BESPOKE_COPY_FORMAT,
			'site'     => home_url(),
			'products' => $products,
			'settings' => bespoke_copy_read_settings(),
		],
		'files'     => bespoke_launch_read_files(),
		'fonts'     => get_option( 'bespoke_customiser_fonts', [] ),
		'designs'   => bespoke_launch_read_designs(),
		'elementor' => bespoke_launch_read_elementor(),
		'reviews'   => $reviews,
	];
}

/* =========================================================================
   WHAT IT WOULD CHANGE (on live)
   One list of rows, in the order the run takes them. Each row has a 'pick'
   key for its tick box and what its job needs.
   ========================================================================= */

/** Files live is missing, or has different. */
function bespoke_launch_plan_files( $file ) {
	$up   = bespoke_launch_uploads();
	$need = [];
	foreach ( (array) ( $file['files'] ?? [] ) as $f ) {
		$rel = (string) ( $f['path'] ?? '' );
		if ( ! bespoke_launch_safe_path( $rel ) ) {
			continue;
		}
		$here = $up['dir'] . $rel;
		if ( file_exists( $here ) && md5_file( $here ) === (string) ( $f['md5'] ?? '' ) ) {
			continue;
		}
		$need[] = [ 'path' => $rel, 'md5' => (string) ( $f['md5'] ?? '' ), 'size' => (int) ( $f['size'] ?? 0 ), 'had' => file_exists( $here ) ];
	}
	return $need;
}

/** The font list as it would be here: every address pointing at this site's fonts folder. */
function bespoke_launch_fonts_target( $file ) {
	$up    = bespoke_launch_uploads();
	$fonts = [];
	foreach ( (array) ( $file['fonts'] ?? [] ) as $f ) {
		if ( ! is_array( $f ) || empty( $f['filename'] ) ) {
			continue;
		}
		$fn      = sanitize_file_name( basename( (string) $f['filename'] ) );
		$fonts[] = [
			'name'        => sanitize_text_field( (string) ( $f['name'] ?? $fn ) ),
			'filename'    => $fn,
			'url'         => $up['url'] . 'bespoke-fonts/' . $fn,
			'uploaded_at' => (int) ( $f['uploaded_at'] ?? time() ),
		];
	}
	return $fonts;
}

/** A design's meta as it would be here: addresses swapped. */
function bespoke_launch_design_target( $d, $from ) {
	$meta = [];
	foreach ( (array) ( $d['meta'] ?? [] ) as $k => $v ) {
		if ( 0 === strpos( (string) $k, '_bespoke_' ) && '_bespoke_thumb_id' !== $k ) {
			$meta[ $k ] = bespoke_launch_swap_urls( $v, $from );
		}
	}
	ksort( $meta );
	return $meta;
}

/**
 * The design here with this web address, or 0.
 *
 * By address only, never by name (as Copy to Live falls back to): staging
 * has 52 names shared by two to four designs (four "Apex", one per
 * product), so matching a name would write each one over the first made.
 */
function bespoke_launch_find_design( $slug ) {
	return bespoke_launch_find_post( 'bespoke_design', $slug );
}

/** Designs to make or change here. */
function bespoke_launch_plan_designs( $file ) {
	$from = bespoke_launch_from( $file );
	$rows = [];
	foreach ( (array) ( $file['designs'] ?? [] ) as $i => $d ) {
		if ( '' === (string) ( $d['slug'] ?? '' ) ) {
			continue; // a draft, from a file made before drafts were left out
		}
		$id   = bespoke_launch_find_design( $d['slug'] );
		$want = bespoke_launch_design_target( $d, $from );
		if ( $id ) {
			$have = [];
			foreach ( array_keys( $want ) as $k ) {
				$have[ $k ] = get_post_meta( $id, $k, true );
			}
			$same = bespoke_copy_same( $want, $have )
				&& (string) get_post_field( 'post_title', $id ) === (string) ( $d['title'] ?? '' )
				&& (int) get_post_field( 'menu_order', $id ) === (int) ( $d['menu_order'] ?? 0 );
			if ( $same && ( ! empty( $d['thumb'] ) ? (int) get_post_meta( $id, '_bespoke_thumb_id', true ) : true ) ) {
				continue;
			}
		}
		$rows[] = [ 'index' => $i, 'id' => $id, 'title' => (string) ( $d['title'] ?? '' ) ];
	}
	return $rows;
}

/** An Elementor document's data, settings and SEO as they would be here, without fetching anything. */
function bespoke_launch_el_target( $doc, $from, $fetch = false, &$warnings = [], $post_id = 0 ) {
	$images = [];
	$map    = function ( $value ) use ( &$map, $from, $fetch, &$warnings, $post_id, &$images, $doc ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		// An image control: { url, id, … }. Same file here → its attachment;
		// otherwise fetched (when allowed) into this site's Media Library.
		if ( isset( $value['url'] ) && array_key_exists( 'id', $value ) && is_string( $value['url'] ) && '' !== $value['url'] ) {
			$value = bespoke_launch_el_image( $value, $from, $fetch, $warnings, $post_id, $images );
		}
		foreach ( $value as $k => $v ) {
			$kind = bespoke_launch_ref_kind( $k );
			if ( '' !== $kind && ( is_array( $v ) || is_numeric( $v ) ) ) {
				$value[ $k ] = bespoke_launch_el_map_ids( $v, $kind, $doc['refs'] ?? [], $warnings, $fetch );
				continue;
			}
			$value[ $k ] = $map( $v );
		}
		return $value;
	};
	$data     = bespoke_launch_swap_urls( $map( (array) ( $doc['data'] ?? [] ) ), $from );
	$settings = bespoke_launch_swap_urls( $map( (array) ( $doc['settings'] ?? [] ) ), $from );
	return [ 'data' => $data, 'settings' => $settings ];
}

/** One image control, pointed at this site. */
function bespoke_launch_el_image( $img, $from, $fetch, &$warnings, $post_id, &$cache ) {
	$url  = (string) $img['url'];
	$up   = bespoke_launch_uploads();
	$base = trailingslashit( (string) ( $from['uploads'] ?? '' ) );
	if ( '' === $base || 0 !== strpos( $url, $base ) ) {
		return $img; // not one of the other site's uploads (an icon, a placeholder): left as it is
	}
	if ( isset( $cache[ $url ] ) ) {
		return array_merge( $img, $cache[ $url ] );
	}
	$rel      = substr( $url, strlen( $base ) );
	$here_url = $up['url'] . $rel;
	$att      = file_exists( $up['dir'] . $rel ) ? attachment_url_to_postid( $here_url ) : 0;
	if ( ! $att && $fetch ) {
		$att = bespoke_copy_picture( [ 'url' => $url, 'alt' => (string) ( $img['alt'] ?? '' ), 'title' => '' ], $post_id, $warnings );
		if ( $att ) {
			$here_url = (string) wp_get_attachment_url( $att );
		}
	}
	$set = $att ? [ 'url' => $here_url, 'id' => $att ] : [ 'url' => $here_url, 'id' => $img['id'] ];
	if ( ! $att && ! $fetch ) {
		$set['pending'] = 1; // marks it as different: fetched when the run comes to it
	}
	$cache[ $url ] = $set;
	return array_merge( $img, $set );
}

/**
 * Product, template and category IDs, mapped to this site's by web address.
 * While planning, something the run will make first (a new product, a new
 * category) isn't here yet: it's kept as a marker, so the page still shows
 * as changing. When the run writes the page, anything still missing is left
 * out, with a warning.
 */
function bespoke_launch_el_map_ids( $value, $kind, $refs, &$warnings, $final = true ) {
	$single = ! is_array( $value );
	$out    = [];
	foreach ( (array) $value as $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			continue;
		}
		if ( 'post' === $kind ) {
			$ref  = $refs['posts'][ $id ] ?? ( $refs['posts'][ (string) $id ] ?? null );
			$here = $ref ? bespoke_launch_find_post( $ref['type'], $ref['slug'] ) : 0;
			if ( ! $here ) {
				if ( ! $final ) {
					$out[] = 'to-come:' . ( $ref['slug'] ?? $id );
					continue;
				}
				$warnings[] = 'a link to "' . ( $ref['slug'] ?? '#' . $id ) . '" has nothing to point at here yet.';
				continue;
			}
		} else {
			$ref  = $refs['terms'][ $id ] ?? ( $refs['terms'][ (string) $id ] ?? null );
			$term = $ref ? get_term_by( 'slug', $ref['slug'], $ref['taxonomy'] ) : null;
			if ( ! $term ) {
				if ( ! $final ) {
					$out[] = 'to-come:' . ( $ref['slug'] ?? $id );
					continue;
				}
				$warnings[] = 'the category "' . ( $ref['name'] ?? '#' . $id ) . '" is not on this site yet.';
				continue;
			}
			$here = (int) $term->term_id;
		}
		$out[] = (string) $here;
	}
	if ( $single ) {
		return $out ? $out[0] : '';
	}
	return $out;
}

/** Which Elementor document here an exported one is: the front page by role, the kit by role, the rest by web address. */
function bespoke_launch_el_match( $doc ) {
	if ( ! empty( $doc['front'] ) ) {
		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			return $front;
		}
	}
	if ( ! empty( $doc['kit'] ) ) {
		$kit = (int) get_option( 'elementor_active_kit' );
		if ( $kit ) {
			return $kit;
		}
	}
	return bespoke_launch_find_post( (string) ( $doc['post_type'] ?? 'page' ), (string) ( $doc['slug'] ?? '' ) );
}

/** This site's copy of an Elementor document, in the shape the file uses. */
function bespoke_launch_el_current( $id ) {
	$raw      = get_post_meta( $id, '_elementor_data', true );
	$data     = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	$settings = get_post_meta( $id, '_elementor_page_settings', true );
	$cond     = get_post_meta( $id, '_elementor_conditions', true );
	$seo      = [];
	foreach ( BESPOKE_LAUNCH_PAGE_SEO as $key ) {
		$v = get_post_meta( $id, $key, true );
		if ( '' !== $v && null !== $v ) {
			$seo[ $key ] = $v;
		}
	}
	return [
		'data'       => is_array( $data ) ? $data : [],
		'settings'   => is_array( $settings ) ? $settings : [],
		'conditions' => is_array( $cond ) ? array_values( $cond ) : [],
		'seo'        => $seo,
		'template'   => (string) get_post_meta( $id, '_wp_page_template', true ),
	];
}

/** Elementor pages and templates to make or change here. */
function bespoke_launch_plan_elementor( $file ) {
	$from = bespoke_launch_from( $file );
	$rows = [];
	foreach ( (array) ( $file['elementor'] ?? [] ) as $i => $doc ) {
		$id       = bespoke_launch_el_match( $doc );
		$warnings = [];
		$want     = bespoke_launch_el_target( $doc, $from, false, $warnings );
		$label    = bespoke_launch_el_label( $doc );
		if ( ! $id ) {
			$rows[] = [ 'index' => $i, 'id' => 0, 'label' => $label, 'what' => 'new here', 'newer_here' => false, 'warnings' => $warnings ];
			continue;
		}
		$have  = bespoke_launch_el_current( $id );
		$diffs = [];
		if ( wp_json_encode( $want['data'] ) !== wp_json_encode( $have['data'] ) ) {
			$diffs[] = 'layout';
		}
		if ( wp_json_encode( $want['settings'] ) !== wp_json_encode( $have['settings'] ) ) {
			$diffs[] = 'page settings';
		}
		if ( 'elementor_library' === ( $doc['post_type'] ?? '' ) && ! bespoke_copy_same( (array) ( $doc['conditions'] ?? [] ), $have['conditions'] ) ) {
			$diffs[] = 'where it shows';
		}
		foreach ( (array) ( $doc['seo'] ?? [] ) as $k => $v ) {
			if ( ! bespoke_copy_same( $v, $have['seo'][ $k ] ?? '' ) ) {
				$diffs[] = 'SEO';
				break;
			}
		}
		if ( ! $diffs ) {
			continue;
		}
		$newer = strcmp( (string) get_post_field( 'post_modified_gmt', $id ), (string) ( $doc['modified'] ?? '' ) ) > 0;
		$rows[] = [ 'index' => $i, 'id' => $id, 'label' => $label, 'what' => implode( ', ', $diffs ), 'newer_here' => $newer, 'warnings' => $warnings ];
	}
	return $rows;
}

/** What a document is, in words. */
function bespoke_launch_el_label( $doc ) {
	if ( ! empty( $doc['front'] ) ) {
		return 'Homepage';
	}
	if ( ! empty( $doc['kit'] ) ) {
		return 'Elementor site settings (colours and fonts)';
	}
	$kind = 'page' === ( $doc['post_type'] ?? '' ) ? 'Page' : ucfirst( str_replace( '-', ' ', (string) ( $doc['template_type'] ?: 'template' ) ) ) . ' template';
	return $kind . ': ' . ( $doc['title'] ?? $doc['slug'] ?? '' );
}

/** The plan: every section, with its rows. */
function bespoke_launch_plan( $file ) {
	$sections = [];

	// 1. Files.
	$need = bespoke_launch_plan_files( $file );
	if ( $need ) {
		$bytes = array_sum( array_column( $need, 'size' ) );
		$sections[] = [
			'key'   => 'files',
			'title' => 'Artwork and font files',
			'rows'  => [ [
				'pick'  => 'files',
				'label' => count( $need ) . ' file(s) to fetch from staging (' . size_format( $bytes ) . ')',
				'note'  => 'Design artwork, product artwork and fonts, into the same folders here.',
			] ],
		];
	}

	// 2. Fonts.
	$fonts = bespoke_launch_fonts_target( $file );
	if ( $fonts && ! bespoke_copy_same( $fonts, get_option( 'bespoke_customiser_fonts', [] ) ) ) {
		$sections[] = [
			'key'   => 'fonts',
			'title' => 'Font list',
			'rows'  => [ [ 'pick' => 'fonts', 'label' => count( $fonts ) . ' font(s): ' . implode( ', ', array_column( $fonts, 'name' ) ) ] ],
		];
	}

	// 3. Customiser settings.
	$settings = bespoke_copy_settings_plan( $file['copy']['settings'] ?? [] );
	$rows     = [];
	foreach ( $settings as $s ) {
		// A row whose artwork isn't here yet still belongs: the files step
		// fetches it first, and the settings step reads the plan again then.
		if ( ! $s['value'] && ! $s['missing'] ) {
			continue;
		}
		$rows[] = [
			'pick'  => 'setting:' . $s['key'],
			'label' => $s['label'],
			'note'  => $s['from'] . ' → ' . $s['to'] . ( $s['missing'] ? ' (once its files are here: ' . implode( ', ', $s['missing'] ) . ')' : '' ),
		];
	}
	if ( $rows ) {
		$sections[] = [ 'key' => 'settings', 'title' => 'Customiser settings', 'rows' => $rows ];
	}

	// 4. Designs.
	$rows = [];
	foreach ( bespoke_launch_plan_designs( $file ) as $d ) {
		$rows[] = [ 'pick' => 'design:' . $d['index'], 'label' => $d['title'], 'note' => $d['id'] ? 'changed on staging' : 'new here' ];
	}
	if ( $rows ) {
		$sections[] = [ 'key' => 'designs', 'title' => 'Design library', 'rows' => $rows ];
	}

	// 5. Products: made as drafts, then the ones already here.
	$plan = bespoke_copy_plan( bespoke_launch_copy_file( $file ) );
	$new  = [];
	$opts = [];
	$upd  = [];
	foreach ( $plan as $i => $row ) {
		if ( $row['creatable'] ) {
			$new[] = [ 'pick' => 'create:' . $i, 'label' => $row['name'], 'note' => 'made as a draft: ' . bespoke_copy_describe_shape( $row['entry']['create'] ) ];
			continue;
		}
		$op = $row['matched'] ? bespoke_launch_options_plan( $row['entry'], $row['id'] ) : null;
		if ( $op ) {
			$opts[] = $op['error']
				? [ 'pick' => 'options:' . $i, 'label' => $row['name'], 'note' => 'Left for doing by hand: ' . $op['error'] . '.', 'blocked' => true ]
				: [ 'pick' => 'options:' . $i, 'label' => $row['name'], 'note' => $op['note'] ];
		}
		if ( ! $row['matched'] || ! bespoke_copy_writable( $row['changes'] ) ) {
			continue;
		}
		$newer  = strcmp( (string) get_post_field( 'post_modified_gmt', $row['id'] ), (string) ( $row['entry']['modified'] ?? '' ) ) > 0;
		$labels = [];
		foreach ( $row['changes'] as $c ) {
			if ( empty( $c['blocked'] ) ) {
				$labels[] = $c['label'];
			}
		}
		$upd[] = [
			'pick'    => 'product:' . $i,
			'label'   => $row['name'],
			'note'    => implode( ', ', $labels ) . ( $newer ? ' — live\'s copy was edited more recently, so this is left unticked: check it first' : '' ),
			'unticked'=> $newer,
		];
	}
	if ( $new ) {
		$sections[] = [ 'key' => 'create', 'title' => 'New products (made as drafts — nobody sees them until Publish)', 'rows' => $new ];
	}
	if ( $opts ) {
		$sections[] = [ 'key' => 'options', 'title' => 'Products already here: options, as on staging', 'rows' => $opts ];
	}
	if ( $upd ) {
		$sections[] = [ 'key' => 'products', 'title' => 'Products already here: wording, customiser setup and SEO', 'rows' => $upd ];
	}

	// 6. Elementor pages and templates.
	$rows = [];
	foreach ( bespoke_launch_plan_elementor( $file ) as $d ) {
		$rows[] = [
			'pick'     => 'doc:' . $d['index'],
			'label'    => $d['label'],
			'note'     => ( $d['id'] ? $d['what'] : 'new here' . ( 'page' === ( $file['elementor'][ $d['index'] ]['post_type'] ?? '' ) ? ', made as a draft' : '' ) )
				. ( $d['newer_here'] ? ' — live\'s copy was edited more recently, so this is left unticked: check it first' : '' ),
			'unticked' => $d['newer_here'],
		];
	}
	if ( $rows ) {
		$sections[] = [ 'key' => 'elementor', 'title' => 'Pages and templates', 'rows' => $rows ];
	}

	// 7. SEO packs.
	$rows = [];
	if ( function_exists( 'bespoke_seo_packs' ) ) {
		foreach ( bespoke_seo_packs() as $key => $pack ) {
			$rows[] = [ 'pick' => 'pack:' . $key, 'label' => $pack['name'] ?? $key, 'note' => 'applied after the products and pages are here' ];
		}
	}
	if ( $rows ) {
		$sections[] = [ 'key' => 'packs', 'title' => 'SEO packs', 'rows' => $rows ];
	}

	// 8. Reviews.
	$want = (array) ( $file['reviews'] ?? [] );
	$diff = [];
	foreach ( $want as $opt => $v ) {
		if ( in_array( $opt, BESPOKE_LAUNCH_REVIEW_OPTIONS, true ) && (string) get_option( $opt ) !== (string) $v ) {
			$diff[] = $opt;
		}
	}
	$closed = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'product' AND comment_status = 'closed' AND post_status IN ('publish','draft','private')" ); // phpcs:ignore WordPress.DB
	if ( $diff || $closed ) {
		$sections[] = [
			'key'   => 'reviews',
			'title' => 'Reviews',
			'rows'  => [ [ 'pick' => 'reviews', 'label' => 'Reviews on, as on staging', 'note' => 'verified owners only, each held for approval' . ( $closed ? '; ' . $closed . ' product(s) opened to reviews' : '' ) ] ],
		];
	}

	// 9. Tidy up.
	$rows    = [];
	$exports = array_column( (array) ( $file['copy']['products'] ?? [] ), 'slug' );
	$dropped = [];
	foreach ( BESPOKE_LAUNCH_DROPPED as $slug ) {
		$id = bespoke_launch_find_post( 'product', $slug );
		if ( $id && ! in_array( $slug, $exports, true ) ) {
			$dropped[] = get_the_title( $id );
		}
	}
	if ( $dropped ) {
		$rows[] = [ 'pick' => 'bin', 'label' => 'Move the dropped products to the bin', 'note' => implode( ', ', $dropped ) ];
	}
	$fpd = bespoke_launch_fpd_plugin();
	if ( $fpd && is_plugin_active( $fpd ) ) {
		$rows[] = [ 'pick' => 'fpd', 'label' => 'Switch off Fancy Product Designer', 'note' => 'the customiser replaces it; its old orders stay readable' ];
	}
	$rows[] = [ 'pick' => 'caches', 'label' => 'Clear the caches', 'note' => 'SiteGround, Elementor and WooCommerce, so the new pages show' ];
	$sections[] = [ 'key' => 'tidy', 'title' => 'Tidy up', 'rows' => $rows ];

	return $sections;
}

/** The products part of a launch file, as Copy to Live reads it — its wording pointed at this site. */
function bespoke_launch_copy_file( $file ) {
	$copy = (array) ( $file['copy'] ?? [] );
	$from = bespoke_launch_from( $file );
	foreach ( (array) ( $copy['products'] ?? [] ) as $i => $p ) {
		if ( isset( $p['fields'] ) ) {
			$copy['products'][ $i ]['fields'] = bespoke_launch_swap_urls( $p['fields'], $from );
		}
	}
	return $copy;
}

/* =========================================================================
   OPTIONS
   Copy to Live never touches a product's options, prices or variations, so
   the products whose options were changed on staging (shin pads made one
   product, the pennant's option names, the armband Length labels — the
   runbook's three changes, 13–25 Sep 2026) are brought in line here. Prices
   stay as they are here; a product that would need a new option or a
   price is shown and left for doing by hand.
   ========================================================================= */

/**
 * Options renamed on staging, old name → new, by option list (Nick, 13 and
 * 21 Sep 2026: the customiser's frill switch only works with these names).
 */
const BESPOKE_LAUNCH_RENAMED_OPTIONS = [
	'pennant type' => [ 'frilled' => 'With Frill', 'non frilled' => 'No Frill' ],
];

/** Text compared loosely: case, spaces and HTML entities don't count. */
function bespoke_launch_norm( $s ) {
	return strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
}

/** A product's option lists, label → options, for comparing. */
function bespoke_launch_option_lists( $shape ) {
	$out = [];
	foreach ( (array) ( $shape['attributes'] ?? [] ) as $a ) {
		$out[ bespoke_launch_norm( $a['label'] ?? '' ) ] = array_map( 'bespoke_launch_norm', (array) ( $a['options'] ?? [] ) );
	}
	ksort( $out );
	return $out;
}

/**
 * One of staging's option lists as it would be stored here, without making
 * anything: its key here, and each option name → the value a variation
 * holds. Matches bespoke_copy_make_attributes().
 */
function bespoke_launch_option_target( $a ) {
	$tax    = (string) ( $a['taxonomy'] ?? '' );
	$names  = array_values( array_map( 'sanitize_text_field', array_map( 'strval', (array) ( $a['options'] ?? [] ) ) ) );
	$slugs  = array_values( array_map( 'strval', (array) ( $a['slugs'] ?? [] ) ) );
	// 'stored': option name → value here; 'values': staging's value → value here.
	$stored = [];
	$values = [];
	if ( '' !== $tax && taxonomy_exists( $tax ) && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
		foreach ( $names as $i => $n ) {
			$slug                = $slugs[ $i ] ?? $n;
			$stored[ $n ]        = sanitize_title( $slug );
			$values[ $slug ]     = sanitize_title( $slug );
		}
		return [ 'key' => $tax, 'stored' => $stored, 'values' => $values ];
	}
	foreach ( $names as $i => $n ) {
		$stored[ $n ]                                  = $n;
		$values[ '' !== $tax ? ( $slugs[ $i ] ?? $n ) : $n ] = $n;
	}
	return [ 'key' => sanitize_title( sanitize_text_field( (string) ( $a['label'] ?? '' ) ) ), 'stored' => $stored, 'values' => $values ];
}

/**
 * Which of staging's options an option here becomes: the same words; a
 * rename Nick made; or, for sizes, the one option with the same leading
 * measurement ("24cm (approx U14-U16)" → "24cm Band (U11–U15)"). Null when
 * there is no single answer.
 */
function bespoke_launch_match_option( $name, $options, $list ) {
	$n = bespoke_launch_norm( $name );
	foreach ( $options as $o ) {
		if ( bespoke_launch_norm( $o ) === $n ) {
			return $o;
		}
	}
	$renamed = BESPOKE_LAUNCH_RENAMED_OPTIONS[ bespoke_launch_norm( $list ) ][ $n ] ?? null;
	if ( null !== $renamed ) {
		foreach ( $options as $o ) {
			if ( bespoke_launch_norm( $o ) === bespoke_launch_norm( $renamed ) ) {
				return $o;
			}
		}
	}
	if ( preg_match( '/^(\d+(?:\.\d+)?)\s*cm\b/i', $n, $m ) ) {
		$hits = [];
		foreach ( $options as $o ) {
			if ( preg_match( '/^(\d+(?:\.\d+)?)\s*cm\b/i', bespoke_launch_norm( $o ), $mo ) && $mo[1] === $m[1] ) {
				$hits[] = $o;
			}
		}
		if ( 1 === count( $hits ) ) {
			return $hits[0];
		}
	}
	return null;
}

/** "£12.00" */
function bespoke_launch_money( $v ) {
	return '£' . number_format( (float) $v, 2 );
}

/**
 * How this product's options here would change to match staging's, or null
 * when they already match.
 *
 * @return array|null [ 'kind' => 'to_simple'|'relabel', 'note' => words,
 *                      'error' => why it's left for doing by hand, or '',
 *                      'vars' => [ variation ID => its options here after ] ]
 */
function bespoke_launch_options_plan( $entry, $id ) {
	$want = is_array( $entry['create'] ?? null ) ? $entry['create'] : null;
	$p    = ( $want && function_exists( 'wc_get_product' ) ) ? wc_get_product( $id ) : null;
	if ( ! $p ) {
		return null;
	}
	$have = bespoke_copy_product_shape( $id );
	if ( (string) ( $want['type'] ?? '' ) === $p->get_type() && bespoke_launch_option_lists( $want ) === bespoke_launch_option_lists( $have ) ) {
		return null;
	}
	$lists = function ( $shape ) {
		$bits = [];
		foreach ( (array) ( $shape['attributes'] ?? [] ) as $a ) {
			$bits[] = ( $a['label'] ?? '' ) . ': ' . implode( ' | ', (array) ( $a['options'] ?? [] ) );
		}
		return $bits ? implode( '; ', $bits ) : 'no options';
	};
	$plan = [ 'kind' => '', 'note' => '', 'error' => '', 'vars' => [] ];

	// Options here, one product on staging (shin pads: the size is picked in
	// the customiser). The old options go to the bin, so Undo brings them back.
	if ( 'simple' === ( $want['type'] ?? '' ) && $p->is_type( 'variable' ) ) {
		$price  = '' !== (string) ( $want['sale_price'] ?? '' ) ? $want['sale_price'] : ( $want['regular_price'] ?? '' );
		$prices = [];
		foreach ( $p->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( $v ) {
				$prices[] = (float) $v->get_price( 'edit' );
			}
		}
		$plan['kind'] = 'to_simple';
		if ( '' === (string) $price ) {
			$plan['error'] = 'staging has no price for it';
			return $plan;
		}
		$same          = $prices && min( $prices ) === (float) $price && max( $prices ) === (float) $price;
		$plan['note']  = $lists( $have ) . ' → one product at ' . bespoke_launch_money( $price )
			. ( $same ? ', as now' : ( $prices ? ' (its options here are ' . bespoke_launch_money( min( $prices ) ) . ( max( $prices ) > min( $prices ) ? '–' . bespoke_launch_money( max( $prices ) ) : '' ) . ')' : '' ) )
			. '. Its ' . count( $prices ) . ' old options go to the bin, so Undo brings them back.';
		return $plan;
	}

	if ( ! ( 'variable' === ( $want['type'] ?? '' ) && $p->is_type( 'variable' ) ) ) {
		$plan['error'] = 'it is ' . $p->get_type() . ' here and ' . ( $want['type'] ?? '?' ) . ' on staging — change it by hand';
		return $plan;
	}

	// Both have options: reword the lists, and point each option here at its
	// new words. Prices stay as they are.
	$plan['kind'] = 'relabel';
	$here         = []; // list name → [ key here, stored value → option name ]
	foreach ( $p->get_attributes( 'edit' ) as $key => $attr ) {
		if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
			continue;
		}
		$label = $attr->get_name();
		if ( $attr->is_taxonomy() ) {
			// Its name as WooCommerce lists it ("Pennant Type"), read straight
			// from the attribute list rather than relying on it being registered.
			$labels = wc_get_attribute_taxonomy_labels();
			$label  = $labels[ wc_attribute_taxonomy_slug( $label ) ] ?? wc_attribute_label( $label );
		}
		$names = [];
		if ( $attr->is_taxonomy() ) {
			foreach ( (array) $attr->get_terms() as $t ) {
				$names[ $t->slug ] = $t->name;
			}
		} else {
			foreach ( (array) $attr->get_options() as $o ) {
				$names[ (string) $o ]             = (string) $o;
				$names[ sanitize_title( $o ) ] = (string) $o;
			}
		}
		$here[ bespoke_launch_norm( $label ) ] = [ 'key' => (string) $key, 'names' => $names, 'label' => $label, 'variation' => $attr->get_variation(), 'options' => array_values( array_unique( $names ) ) ];
	}
	$wanted = [];
	foreach ( (array) ( $want['attributes'] ?? [] ) as $a ) {
		$wanted[ bespoke_launch_norm( $a['label'] ?? '' ) ] = [ 'a' => $a ] + bespoke_launch_option_target( $a );
	}
	foreach ( $here as $name => $h ) {
		if ( $h['variation'] && ( ! isset( $wanted[ $name ] ) || empty( $wanted[ $name ]['a']['variation'] ) ) ) {
			$plan['error'] = 'its "' . $h['label'] . '" options are not on staging — change it by hand';
			return $plan;
		}
	}
	$notes = [];
	foreach ( $wanted as $name => $w ) {
		$a = $w['a'];
		$h = $here[ $name ] ?? null;
		if ( ! $h ) {
			if ( ! empty( $a['variation'] ) ) {
				$plan['error'] = 'staging has "' . $a['label'] . '" options that it hasn\'t got here — change it by hand';
				return $plan;
			}
			$notes[] = $a['label'] . ' added';
			continue;
		}
		if ( bespoke_launch_option_lists( [ 'attributes' => [ $a ] ] ) === bespoke_launch_option_lists( [ 'attributes' => [ [ 'label' => $a['label'], 'options' => $h['options'] ] ] ] ) ) {
			continue;
		}
		$pairs = [];
		foreach ( $h['options'] as $old ) {
			$new = bespoke_launch_match_option( $old, array_keys( $w['stored'] ), $a['label'] );
			if ( null !== $new && bespoke_launch_norm( $new ) !== bespoke_launch_norm( $old ) ) {
				$pairs[] = '"' . $old . '" → "' . $new . '"';
			}
		}
		$notes[] = $a['label'] . ': ' . ( $pairs ? count( $pairs ) . ' reworded, ' . implode( ', ', array_slice( $pairs, 0, 2 ) ) . ( count( $pairs ) > 2 ? ' and ' . ( count( $pairs ) - 2 ) . ' more' : '' ) : $lists( [ 'attributes' => [ $a ] ] ) );
	}
	foreach ( $here as $name => $h ) {
		if ( ! isset( $wanted[ $name ] ) ) {
			$notes[] = $h['label'] . ' goes';
		}
	}

	// Each option here, with its new words.
	$seen   = [];
	$prices = [];
	foreach ( $p->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( ! $v ) {
			continue;
		}
		$old = (array) $v->get_attributes( 'edit' );
		$new = [];
		foreach ( $wanted as $name => $w ) {
			if ( empty( $w['a']['variation'] ) ) {
				continue;
			}
			$h   = $here[ $name ];
			$raw = (string) ( $old[ $h['key'] ] ?? '' );
			if ( '' === $raw ) {
				$new[ $w['key'] ] = ''; // "any"
				continue;
			}
			$label = $h['names'][ $raw ] ?? $raw;
			$to    = bespoke_launch_match_option( $label, array_keys( $w['stored'] ), $w['a']['label'] );
			if ( null === $to ) {
				$plan['error'] = '"' . $label . '" has no match in staging\'s ' . $w['a']['label'] . ' options — change it by hand';
				return $plan;
			}
			$new[ $w['key'] ] = $w['stored'][ $to ];
		}
		ksort( $new );
		$sig = wp_json_encode( $new );
		if ( isset( $seen[ $sig ] ) ) {
			$plan['error'] = 'two of its options would become the same — change it by hand';
			return $plan;
		}
		$seen[ $sig ]         = true;
		$plan['vars'][ $vid ] = $new;
		$prices[]             = implode( ' ', array_filter( array_map( function ( $k ) use ( $new, $wanted ) {
			foreach ( $wanted as $w ) {
				if ( $w['key'] === $k && '' !== $new[ $k ] ) {
					return (string) array_search( $new[ $k ], $w['stored'], true );
				}
			}
			return '';
		}, array_keys( $new ) ) ) ) . ' ' . bespoke_launch_money( $v->get_price( 'edit' ) );
	}

	// Every option staging sells must be here once this is done.
	foreach ( (array) ( $want['variations'] ?? [] ) as $vd ) {
		$combo = [];
		foreach ( $wanted as $w ) {
			if ( empty( $w['a']['variation'] ) ) {
				continue;
			}
			$val = (string) ( ( (array) ( $vd['attributes'] ?? [] ) )[ $w['a']['key'] ?? '' ] ?? '' );
			$combo[ $w['key'] ] = '' === $val ? '' : (string) ( $w['values'][ $val ] ?? $val );
		}
		ksort( $combo );
		if ( ! isset( $seen[ wp_json_encode( $combo ) ] ) ) {
			$plan['error'] = 'staging sells an option it hasn\'t got here (' . implode( ', ', array_filter( $combo ) ) . ') — add it by hand';
			return $plan;
		}
	}
	$plan['note'] = implode( '; ', $notes ) . '. Prices stay as they are' . ( count( $prices ) <= 3 ? ' (' . implode( ', ', array_map( 'trim', $prices ) ) . ')' : '' ) . '.';
	return $plan;
}

/** A product's option lists, as plain data that can be stored and put back. */
function bespoke_launch_attr_data( $p ) {
	$out = [];
	foreach ( $p->get_attributes( 'edit' ) as $attr ) {
		if ( is_a( $attr, 'WC_Product_Attribute' ) ) {
			$out[] = $attr->get_data();
		}
	}
	return $out;
}

/** …and back into WooCommerce's objects. */
function bespoke_launch_attrs_from_data( $rows ) {
	$out = [];
	foreach ( (array) $rows as $d ) {
		$a = new WC_Product_Attribute();
		$a->set_id( (int) ( $d['id'] ?? 0 ) );
		$a->set_name( (string) ( $d['name'] ?? '' ) );
		$a->set_options( (array) ( $d['options'] ?? [] ) );
		$a->set_position( (int) ( $d['position'] ?? 0 ) );
		$a->set_visible( ! empty( $d['visible'] ) );
		$a->set_variation( ! empty( $d['variation'] ) );
		$out[] = $a;
	}
	return $out;
}

/** Fancy Product Designer's plugin file here, or ''. */
function bespoke_launch_fpd_plugin() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( get_plugins() as $file => $data ) {
		if ( false !== stripos( (string) ( $data['Name'] ?? '' ), 'Fancy Product Designer' ) ) {
			return $file;
		}
	}
	return '';
}

/* =========================================================================
   THE RUN
   The ticked rows become a queue of small jobs. The page's script asks for
   them one at a time, so a slow picture or a big page never times out, and
   a failure stops where it is and can be tried again.
   ========================================================================= */

/** Turn the ticked rows into jobs, in the order they must run. */
function bespoke_launch_jobs( $file, $picks ) {
	$picks = array_flip( $picks );
	$jobs  = [];

	if ( isset( $picks['files'] ) ) {
		$need = bespoke_launch_plan_files( $file );
		foreach ( array_chunk( $need, 8 ) as $n => $chunk ) {
			$jobs[] = [ 'kind' => 'files', 'args' => $chunk, 'label' => 'Fetching files ' . ( $n * 8 + 1 ) . '–' . ( $n * 8 + count( $chunk ) ) . ' of ' . count( $need ) ];
		}
	}
	if ( isset( $picks['fonts'] ) ) {
		$jobs[] = [ 'kind' => 'fonts', 'args' => [], 'label' => 'Font list' ];
	}
	$set_keys = [];
	foreach ( array_keys( $picks ) as $p ) {
		if ( 0 === strpos( $p, 'setting:' ) ) {
			$set_keys[] = substr( $p, 8 );
		}
	}
	if ( $set_keys ) {
		$jobs[] = [ 'kind' => 'settings', 'args' => $set_keys, 'label' => 'Customiser settings' ];
	}
	$designs = [];
	foreach ( array_keys( $picks ) as $p ) {
		if ( 0 === strpos( $p, 'design:' ) ) {
			$designs[] = (int) substr( $p, 7 );
		}
	}
	foreach ( array_chunk( $designs, 5 ) as $chunk ) {
		$jobs[] = [ 'kind' => 'designs', 'args' => $chunk, 'label' => 'Designs: ' . implode( ', ', array_map( function ( $i ) use ( $file ) {
			return (string) ( $file['designs'][ $i ]['title'] ?? $i );
		}, $chunk ) ) ];
	}
	$products = (array) ( $file['copy']['products'] ?? [] );
	foreach ( array_keys( $picks ) as $p ) {
		if ( 0 === strpos( $p, 'create:' ) ) {
			$i      = (int) substr( $p, 7 );
			$jobs[] = [ 'kind' => 'create', 'args' => [ $i ], 'label' => 'New product: ' . ( $products[ $i ]['name'] ?? $i ) ];
		}
	}
	// Options before wording: the setup then lands on a product in its final shape.
	foreach ( array_keys( $picks ) as $p ) {
		if ( 0 === strpos( $p, 'options:' ) ) {
			$i      = (int) substr( $p, 8 );
			$jobs[] = [ 'kind' => 'options', 'args' => [ $i ], 'label' => 'Options: ' . ( $products[ $i ]['name'] ?? $i ) ];
		}
	}
	$update = [];
	foreach ( array_keys( $picks ) as $p ) {
		if ( 0 === strpos( $p, 'product:' ) ) {
			$update[] = (int) substr( $p, 8 );
		}
	}
	foreach ( array_chunk( $update, 10 ) as $chunk ) {
		$jobs[] = [ 'kind' => 'products', 'args' => $chunk, 'label' => 'Products: ' . implode( ', ', array_map( function ( $i ) use ( $products ) {
			return (string) ( $products[ $i ]['name'] ?? $i );
		}, $chunk ) ) ];
	}
	$docs = (array) ( $file['elementor'] ?? [] );
	$any  = false;
	foreach ( array_keys( $picks ) as $p ) {
		if ( 0 === strpos( $p, 'doc:' ) ) {
			$i      = (int) substr( $p, 4 );
			$jobs[] = [ 'kind' => 'doc', 'args' => [ $i ], 'label' => bespoke_launch_el_label( $docs[ $i ] ?? [] ) ];
			$any    = true;
		}
	}
	if ( $any ) {
		$jobs[] = [ 'kind' => 'doc_finish', 'args' => [], 'label' => 'Elementor: where templates show, and fresh styles' ];
	}
	foreach ( array_keys( $picks ) as $p ) {
		if ( 0 === strpos( $p, 'pack:' ) ) {
			$jobs[] = [ 'kind' => 'pack', 'args' => [ substr( $p, 5 ) ], 'label' => 'SEO pack: ' . substr( $p, 5 ) ];
		}
	}
	foreach ( [ 'reviews' => 'Reviews', 'bin' => 'Dropped products to the bin', 'fpd' => 'Fancy Product Designer off', 'caches' => 'Caches cleared' ] as $key => $label ) {
		if ( isset( $picks[ $key ] ) ) {
			$jobs[] = [ 'kind' => $key, 'args' => [], 'label' => $label ];
		}
	}
	return $jobs;
}

/**
 * Do one job. Returns [ 'message' => words, 'undo' => what to put back,
 * 'made' => [ 'products' => ids, 'pages' => ids ] ].
 */
function bespoke_launch_run_job( $job, $file ) {
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$from = bespoke_launch_from( $file );
	switch ( $job['kind'] ) {

		case 'files':
			$up      = bespoke_launch_uploads();
			$base    = trailingslashit( (string) ( $file['uploads'] ?? '' ) );
			$made    = [];
			$moved   = [];
			$failed  = [];
			foreach ( (array) $job['args'] as $f ) {
				$rel = (string) $f['path'];
				if ( ! bespoke_launch_safe_path( $rel ) || '' === $base ) {
					continue;
				}
				$url  = $base . implode( '/', array_map( 'rawurlencode', explode( '/', $rel ) ) );
				$resp = wp_remote_get( $url, [ 'timeout' => 60 ] );
				$body = is_wp_error( $resp ) ? '' : wp_remote_retrieve_body( $resp );
				if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) || md5( $body ) !== (string) $f['md5'] ) {
					$failed[] = $rel;
					continue;
				}
				$dest = $up['dir'] . $rel;
				wp_mkdir_p( dirname( $dest ) );
				if ( file_exists( $dest ) ) {
					// Kept beside it, so Undo can put it back.
					rename( $dest, $dest . '.before-launch' );
					$moved[] = $rel;
				} else {
					$made[] = $rel;
				}
				file_put_contents( $dest, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			if ( $failed ) {
				throw new Exception( count( $failed ) . ' file(s) could not be fetched from staging: ' . implode( ', ', array_slice( $failed, 0, 5 ) ) . '. Check staging is up, then try again.' );
			}
			return [ 'message' => ( count( $made ) + count( $moved ) ) . ' file(s) fetched', 'undo' => [ 'made' => $made, 'moved' => $moved ] ];

		case 'fonts':
			$before = get_option( 'bespoke_customiser_fonts', [] );
			update_option( 'bespoke_customiser_fonts', bespoke_launch_fonts_target( $file ) );
			return [ 'message' => 'font list set', 'undo' => [ 'before' => $before ] ];

		case 'settings':
			$changed = [];
			foreach ( bespoke_copy_settings_plan( $file['copy']['settings'] ?? [] ) as $row ) {
				if ( in_array( $row['key'], (array) $job['args'], true ) ) {
					$backup = bespoke_copy_apply_setting( $row );
					if ( $backup ) {
						$changed[] = $backup;
					}
				}
			}
			return [ 'message' => count( $changed ) . ' setting(s) changed', 'undo' => [ 'settings' => $changed ] ];

		case 'designs':
			$undo     = [ 'made' => [], 'changed' => [] ];
			$warnings = [];
			foreach ( (array) $job['args'] as $i ) {
				$d = $file['designs'][ $i ] ?? null;
				if ( ! is_array( $d ) || '' === (string) ( $d['slug'] ?? '' ) ) {
					continue;
				}
				$id   = bespoke_launch_find_design( $d['slug'] );
				$meta = bespoke_launch_design_target( $d, $from );
				if ( ! $id ) {
					$id = wp_insert_post( [
						'post_type'   => 'bespoke_design',
						'post_title'  => sanitize_text_field( (string) ( $d['title'] ?? '' ) ),
						'post_name'   => sanitize_title( (string) ( $d['slug'] ?? '' ) ),
						'post_status' => in_array( $d['status'] ?? '', [ 'publish', 'draft', 'pending', 'private' ], true ) ? $d['status'] : 'publish',
						'menu_order'  => (int) ( $d['menu_order'] ?? 0 ),
					], true );
					if ( is_wp_error( $id ) ) {
						throw new Exception( 'the design "' . ( $d['title'] ?? '' ) . '" could not be made: ' . $id->get_error_message() );
					}
					$undo['made'][] = (int) $id;
				} else {
					$old = [ 'title' => get_post_field( 'post_title', $id ), 'menu_order' => (int) get_post_field( 'menu_order', $id ), 'thumb' => get_post_meta( $id, '_bespoke_thumb_id', true ), 'meta' => [] ];
					foreach ( array_keys( $meta ) as $k ) {
						$old['meta'][ $k ] = metadata_exists( 'post', $id, $k ) ? get_post_meta( $id, $k, true ) : null;
					}
					$undo['changed'][ $id ] = $old;
					wp_update_post( [ 'ID' => $id, 'post_title' => sanitize_text_field( (string) ( $d['title'] ?? '' ) ), 'menu_order' => (int) ( $d['menu_order'] ?? 0 ) ] );
				}
				foreach ( $meta as $k => $v ) {
					update_post_meta( $id, $k, wp_slash( $v ) );
				}
				if ( ! empty( $d['thumb']['url'] ) ) {
					$thumb = bespoke_launch_local_attachment( $d['thumb']['url'], $from );
					if ( ! $thumb ) {
						$thumb = bespoke_copy_picture( $d['thumb'], (int) $id, $warnings );
					}
					if ( $thumb ) {
						update_post_meta( $id, '_bespoke_thumb_id', $thumb );
					}
				}
			}
			return [ 'message' => count( $undo['made'] ) . ' design(s) made, ' . count( $undo['changed'] ) . ' changed' . ( $warnings ? ' (' . implode( ' ', $warnings ) . ')' : '' ), 'undo' => $undo ];

		case 'create':
			$copy  = bespoke_launch_copy_file( $file );
			$plan  = bespoke_copy_plan( $copy );
			$i     = (int) $job['args'][0];
			$row   = $plan[ $i ] ?? null;
			if ( ! $row ) {
				throw new Exception( 'that product is no longer in the file.' );
			}
			if ( $row['matched'] ) {
				return [ 'message' => 'already here — left as it is', 'undo' => [] ];
			}
			$warnings = [];
			$id       = bespoke_copy_create_product( $row['entry'], $warnings );
			if ( is_wp_error( $id ) ) {
				throw new Exception( $id->get_error_message() );
			}
			$keys = array_keys( bespoke_copy_fields() );
			if ( ! empty( $row['entry']['design_allow'] ) ) {
				$dp = bespoke_copy_design_plan( $id, $row['entry']['design_allow'] );
				if ( $dp['missing'] ) {
					$warnings[] = 'its designs (' . implode( ', ', $dp['missing'] ) . ') are not here, so it offers every design of its type';
				} else {
					$keys[] = BESPOKE_COPY_DESIGNS;
				}
			}
			bespoke_copy_apply_product( $id, $row['entry'], $keys );
			foreach ( (array) ( $row['entry']['extra'] ?? [] ) as $k => $v ) {
				if ( in_array( $k, [ '_links_to', '_links_to_target', 'rank_math_robots' ], true ) ) {
					update_post_meta( $id, $k, bespoke_launch_swap_urls( $v, $from ) );
				}
			}
			return [ 'message' => 'made as a draft' . ( $warnings ? ' (' . implode( '; ', $warnings ) . ')' : '' ), 'undo' => [ 'made' => [ (int) $id ] ], 'made' => [ 'products' => [ (int) $id ] ] ];

		case 'options':
			$plan = bespoke_copy_plan( bespoke_launch_copy_file( $file ) );
			$row  = $plan[ (int) $job['args'][0] ] ?? null;
			if ( ! $row || ! $row['matched'] ) {
				throw new Exception( 'that product is not here.' );
			}
			$id = (int) $row['id'];
			$op = bespoke_launch_options_plan( $row['entry'], $id );
			if ( ! $op ) {
				return [ 'message' => 'already as on staging', 'undo' => [] ];
			}
			if ( $op['error'] ) {
				throw new Exception( $op['error'] . '.' );
			}
			$want     = $row['entry']['create'];
			$p        = wc_get_product( $id );
			$warnings = [];
			list( $attrs ) = bespoke_copy_make_attributes( $want, $warnings );

			if ( 'to_simple' === $op['kind'] ) {
				$before = [ 'kind' => 'to_simple', 'attrs' => bespoke_launch_attr_data( $p ), 'children' => [] ];
				foreach ( $p->get_children() as $vid ) {
					$before['children'][ (int) $vid ] = get_post_status( $vid );
				}
				// WooCommerce deletes the options for good when a product stops
				// having them; to the bin instead, so Undo can bring them back.
				add_filter( 'woocommerce_delete_variations_on_product_type_change', '__return_false' );
				try {
					delete_post_meta( $id, '_price' ); // one per option; the save writes the one price
					$s = new WC_Product_Simple( $id );
					$s->set_attributes( $attrs );
					$s->set_regular_price( wc_format_decimal( (string) ( $want['regular_price'] ?? '' ) ) );
					$s->set_sale_price( wc_format_decimal( (string) ( $want['sale_price'] ?? '' ) ) );
					$s->save();
				} finally {
					remove_filter( 'woocommerce_delete_variations_on_product_type_change', '__return_false' );
				}
				foreach ( array_keys( $before['children'] ) as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v ) {
						$v->delete( false );
					}
				}
				wc_delete_product_transients( $id );
				return [ 'message' => 'now one product at ' . bespoke_launch_money( $s->get_price( 'edit' ) ) . '; ' . count( $before['children'] ) . ' old option(s) in the bin', 'undo' => [ $id => $before ] ];
			}

			// Each option's choices straight from the database, before the
			// product's lists change: WooCommerce reads them through the
			// product's lists, so afterwards the old ones read as blank.
			$before = [ 'kind' => 'relabel', 'attrs' => bespoke_launch_attr_data( $p ), 'vars' => [] ];
			foreach ( array_keys( $op['vars'] ) as $vid ) {
				$before['vars'][ (int) $vid ] = [];
				foreach ( get_post_meta( $vid ) as $k => $vals ) {
					if ( 0 === strpos( $k, 'attribute_' ) ) {
						$before['vars'][ (int) $vid ][ substr( $k, 10 ) ] = (string) $vals[0];
					}
				}
			}
			$p->set_attributes( $attrs );
			$p->save();
			foreach ( $op['vars'] as $vid => $new ) {
				$v = wc_get_product( $vid );
				if ( $v ) {
					$v->set_attributes( $new );
					$v->save();
				}
			}
			WC_Product_Variable::sync( $id );
			wc_delete_product_transients( $id );
			return [ 'message' => 'options reworded' . ( $warnings ? ' (' . implode( ' ', $warnings ) . ')' : '' ), 'undo' => [ $id => $before ] ];

		case 'products':
			$copy = bespoke_launch_copy_file( $file );
			$plan = bespoke_copy_plan( $copy );
			$done = [];
			foreach ( (array) $job['args'] as $i ) {
				$row = $plan[ $i ] ?? null;
				if ( ! $row || ! $row['matched'] ) {
					continue;
				}
				$keys = bespoke_copy_writable( $row['changes'] );
				if ( ! $keys ) {
					continue;
				}
				// Copy to Live keeps one backup per product; the run keeps its own too.
				$before = bespoke_copy_read_product( $row['id'] );
				$before[ BESPOKE_COPY_DESIGNS ] = get_post_meta( $row['id'], BESPOKE_COPY_DESIGNS, true );
				if ( bespoke_copy_apply_product( $row['id'], $row['entry'], $keys ) ) {
					$done[ $row['id'] ] = [ 'keys' => $keys, 'before' => $before ];
				}
			}
			return [ 'message' => count( $done ) . ' product(s) updated', 'undo' => [ 'products' => $done ] ];

		case 'doc':
			return bespoke_launch_apply_doc( $file['elementor'][ (int) $job['args'][0] ] ?? null, $from );

		case 'doc_finish':
			bespoke_launch_el_finish();
			return [ 'message' => 'done', 'undo' => [] ];

		case 'pack':
			if ( ! function_exists( 'bespoke_seo_apply' ) ) {
				throw new Exception( 'the SEO packs are not loaded.' );
			}
			$key    = (string) $job['args'][0];
			$before = get_option( BESPOKE_SEO_RUN );
			$result = bespoke_seo_apply( $key );
			if ( is_string( $result ) ) {
				throw new Exception( $result );
			}
			return [ 'message' => intval( $result ) . ' post(s) changed', 'undo' => [ 'run' => get_option( BESPOKE_SEO_RUN ), 'before' => $before ] ];

		case 'reviews':
			global $wpdb;
			$before = [];
			foreach ( (array) ( $file['reviews'] ?? [] ) as $opt => $v ) {
				if ( in_array( $opt, BESPOKE_LAUNCH_REVIEW_OPTIONS, true ) ) {
					$before[ $opt ] = get_option( $opt );
					update_option( $opt, is_scalar( $v ) ? (string) $v : '' );
				}
			}
			$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND comment_status = 'closed' AND post_status IN ('publish','draft','private')" ) ); // phpcs:ignore WordPress.DB
			if ( $ids ) {
				$wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'open' WHERE ID IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB
				foreach ( $ids as $id ) {
					clean_post_cache( $id );
				}
			}
			return [ 'message' => 'reviews on; ' . count( $ids ) . ' product(s) opened to reviews', 'undo' => [ 'options' => $before, 'opened' => $ids ] ];

		case 'bin':
			$exports = array_column( (array) ( $file['copy']['products'] ?? [] ), 'slug' );
			$binned  = [];
			foreach ( BESPOKE_LAUNCH_DROPPED as $slug ) {
				$id = bespoke_launch_find_post( 'product', $slug );
				if ( $id && ! in_array( $slug, $exports, true ) && wp_trash_post( $id ) ) {
					$binned[] = $id;
				}
			}
			return [ 'message' => count( $binned ) . ' product(s) moved to the bin', 'undo' => [ 'binned' => $binned ] ];

		case 'fpd':
			$plugin = bespoke_launch_fpd_plugin();
			if ( $plugin && is_plugin_active( $plugin ) ) {
				deactivate_plugins( $plugin );
				return [ 'message' => 'switched off', 'undo' => [ 'plugin' => $plugin ] ];
			}
			return [ 'message' => 'already off', 'undo' => [] ];

		case 'caches':
			bespoke_launch_purge_caches();
			return [ 'message' => 'cleared', 'undo' => [] ];
	}
	throw new Exception( 'unknown step.' );
}

/** This site's attachment for one of the other site's uploads, if the same file is already here. */
function bespoke_launch_local_attachment( $url, $from ) {
	$base = trailingslashit( (string) ( $from['uploads'] ?? '' ) );
	if ( '' === $base || 0 !== strpos( (string) $url, $base ) ) {
		return 0;
	}
	$up  = bespoke_launch_uploads();
	$rel = substr( (string) $url, strlen( $base ) );
	return file_exists( $up['dir'] . $rel ) ? (int) attachment_url_to_postid( $up['url'] . $rel ) : 0;
}

/** Make or update one Elementor page or template. */
function bespoke_launch_apply_doc( $doc, $from ) {
	if ( ! is_array( $doc ) ) {
		throw new Exception( 'that page is no longer in the file.' );
	}
	$id       = bespoke_launch_el_match( $doc );
	$type     = in_array( $doc['post_type'] ?? '', [ 'page', 'elementor_library' ], true ) ? $doc['post_type'] : 'page';
	$warnings = [];
	$undo     = [];
	$made     = [];
	if ( ! $id ) {
		// Pages wait as drafts for Publish; templates must be published to
		// work (the holding page is up while this runs).
		$id = wp_insert_post( [
			'post_type'    => $type,
			'post_title'   => sanitize_text_field( (string) ( $doc['title'] ?? '' ) ),
			'post_name'    => sanitize_title( (string) ( $doc['slug'] ?? '' ) ),
			'post_status'  => 'page' === $type ? 'draft' : 'publish',
			'post_content' => wp_kses_post( bespoke_launch_swap_urls( (string) ( $doc['content'] ?? '' ), $from ) ),
		], true );
		if ( is_wp_error( $id ) ) {
			throw new Exception( $id->get_error_message() );
		}
		$undo['made'] = (int) $id;
		if ( 'page' === $type ) {
			$made['pages'] = [ (int) $id ];
		}
	} else {
		$have          = bespoke_launch_el_current( $id );
		$undo['id']    = (int) $id;
		$undo['before'] = [
			'_elementor_data'          => get_post_meta( $id, '_elementor_data', true ),
			'_elementor_page_settings' => get_post_meta( $id, '_elementor_page_settings', true ),
			'_elementor_conditions'    => get_post_meta( $id, '_elementor_conditions', true ),
			'_wp_page_template'        => $have['template'],
			'seo'                      => $have['seo'],
			'content'                  => (string) get_post_field( 'post_content', $id ),
		];
	}
	$want = bespoke_launch_el_target( $doc, $from, true, $warnings, (int) $id );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $want['data'] ) ) );
	update_post_meta( $id, '_elementor_page_settings', $want['settings'] );
	if ( ! empty( $doc['template_type'] ) ) {
		update_post_meta( $id, '_elementor_template_type', sanitize_key( $doc['template_type'] ) );
	}
	if ( ! empty( $doc['version'] ) ) {
		update_post_meta( $id, '_elementor_version', sanitize_text_field( $doc['version'] ) );
	}
	if ( '' !== (string) ( $doc['page_template'] ?? '' ) ) {
		update_post_meta( $id, '_wp_page_template', sanitize_text_field( $doc['page_template'] ) );
	}
	if ( 'elementor_library' === $type ) {
		if ( ! empty( $doc['library_type'] ) ) {
			wp_set_object_terms( $id, array_map( 'sanitize_key', (array) $doc['library_type'] ), 'elementor_library_type' );
		}
		update_post_meta( $id, '_elementor_conditions', array_map( 'sanitize_text_field', (array) ( $doc['conditions'] ?? [] ) ) );
	} elseif ( isset( $undo['before'] ) ) {
		// A page already here keeps its wording-only copy in step with Elementor.
		wp_update_post( [ 'ID' => $id, 'post_content' => wp_kses_post( bespoke_launch_swap_urls( (string) ( $doc['content'] ?? '' ), $from ) ) ] );
	}
	foreach ( (array) ( $doc['seo'] ?? [] ) as $k => $v ) {
		if ( in_array( $k, BESPOKE_LAUNCH_PAGE_SEO, true ) ) {
			update_post_meta( $id, $k, is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_text_field( (string) $v ) );
		}
	}
	delete_post_meta( $id, '_elementor_css' );
	delete_post_meta( $id, '_elementor_element_cache' );
	return [ 'message' => ( isset( $undo['made'] ) ? 'made' . ( 'page' === $type ? ' as a draft' : '' ) : 'updated' ) . ( $warnings ? ' (' . implode( ' ', array_unique( $warnings ) ) . ')' : '' ), 'undo' => $undo, 'made' => $made ];
}

/** After the pages and templates: let Elementor Pro work out again where each template shows, and rebuild its styles. */
function bespoke_launch_el_finish() {
	if ( class_exists( '\ElementorPro\Plugin' ) ) {
		try {
			$tb = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
			if ( $tb && method_exists( $tb, 'get_conditions_manager' ) ) {
				$tb->get_conditions_manager()->get_cache()->regenerate();
			}
		} catch ( Throwable $e ) {
			// Elementor rebuilds it itself the next time a template is saved.
			unset( $e );
		}
	}
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}

/** Put one job back. */
function bespoke_launch_undo_job( $job ) {
	$u = (array) ( $job['undo'] ?? [] );
	switch ( $job['kind'] ) {
		case 'files':
			$up = bespoke_launch_uploads();
			foreach ( (array) ( $u['made'] ?? [] ) as $rel ) {
				if ( bespoke_launch_safe_path( $rel ) && file_exists( $up['dir'] . $rel ) ) {
					wp_delete_file( $up['dir'] . $rel );
				}
			}
			foreach ( (array) ( $u['moved'] ?? [] ) as $rel ) {
				if ( bespoke_launch_safe_path( $rel ) && file_exists( $up['dir'] . $rel . '.before-launch' ) ) {
					rename( $up['dir'] . $rel . '.before-launch', $up['dir'] . $rel );
				}
			}
			break;
		case 'fonts':
			update_option( 'bespoke_customiser_fonts', $u['before'] ?? [] );
			break;
		case 'settings':
			foreach ( array_reverse( (array) ( $u['settings'] ?? [] ) ) as $b ) {
				bespoke_copy_undo_setting( $b );
			}
			break;
		case 'designs':
			foreach ( (array) ( $u['made'] ?? [] ) as $id ) {
				if ( 'bespoke_design' === get_post_type( $id ) ) {
					wp_trash_post( $id );
				}
			}
			foreach ( (array) ( $u['changed'] ?? [] ) as $id => $old ) {
				wp_update_post( [ 'ID' => (int) $id, 'post_title' => $old['title'], 'menu_order' => (int) $old['menu_order'] ] );
				foreach ( (array) $old['meta'] as $k => $v ) {
					if ( null === $v ) {
						delete_post_meta( $id, $k );
					} else {
						update_post_meta( $id, $k, wp_slash( $v ) );
					}
				}
				if ( $old['thumb'] ) {
					update_post_meta( $id, '_bespoke_thumb_id', $old['thumb'] );
				} else {
					delete_post_meta( $id, '_bespoke_thumb_id' );
				}
			}
			break;
		case 'create':
			foreach ( (array) ( $u['made'] ?? [] ) as $id ) {
				if ( 'product' === get_post_type( $id ) ) {
					wp_trash_post( $id );
				}
			}
			break;
		case 'options':
			foreach ( $u as $id => $b ) {
				$id = (int) $id;
				if ( 'to_simple' === ( $b['kind'] ?? '' ) ) {
					// The old options first, so the product finds them when it saves.
					foreach ( (array) $b['children'] as $vid => $status ) {
						if ( 'trash' === get_post_status( $vid ) ) {
							wp_untrash_post( (int) $vid );
						}
						wp_update_post( [ 'ID' => (int) $vid, 'post_status' => $status ] );
					}
					wc_delete_product_transients( $id );
					$p = new WC_Product_Variable( $id );
					$p->set_attributes( bespoke_launch_attrs_from_data( $b['attrs'] ) );
					$p->set_regular_price( '' );
					$p->set_sale_price( '' );
					$p->save();
				} else {
					$p = wc_get_product( $id );
					if ( ! $p ) {
						continue;
					}
					$p->set_attributes( bespoke_launch_attrs_from_data( $b['attrs'] ) );
					$p->save();
					foreach ( (array) ( $b['vars'] ?? [] ) as $vid => $attrs ) {
						$v = wc_get_product( (int) $vid );
						if ( $v ) {
							$v->set_attributes( (array) $attrs );
							$v->save();
						}
					}
				}
				wc_delete_product_transients( $id );
				WC_Product_Variable::sync( $id );
				wc_delete_product_transients( $id );
			}
			break;
		case 'products':
			$fields = bespoke_copy_fields();
			foreach ( (array) ( $u['products'] ?? [] ) as $id => $r ) {
				$post = [];
				foreach ( (array) $r['keys'] as $k ) {
					$old = $r['before'][ $k ] ?? '';
					if ( BESPOKE_COPY_DESIGNS === $k ) {
						if ( $old ) {
							update_post_meta( $id, $k, $old );
						} else {
							delete_post_meta( $id, $k );
						}
					} elseif ( isset( $fields[ $k ] ) && 'post' === $fields[ $k ]['where'] ) {
						$post[ $k ] = $old;
					} elseif ( '' === $old ) {
						delete_post_meta( $id, $k );
					} else {
						update_post_meta( $id, $k, $old );
					}
				}
				if ( $post ) {
					$post['ID'] = (int) $id;
					wp_update_post( wp_slash( $post ) );
				}
			}
			break;
		case 'doc':
			if ( ! empty( $u['made'] ) ) {
				wp_trash_post( (int) $u['made'] );
			} elseif ( ! empty( $u['id'] ) ) {
				$id = (int) $u['id'];
				$b  = (array) $u['before'];
				foreach ( [ '_elementor_data', '_elementor_page_settings', '_elementor_conditions', '_wp_page_template' ] as $k ) {
					if ( '' === $b[ $k ] || null === $b[ $k ] ) {
						delete_post_meta( $id, $k );
					} else {
						update_post_meta( $id, $k, '_elementor_data' === $k ? wp_slash( $b[ $k ] ) : $b[ $k ] );
					}
				}
				foreach ( BESPOKE_LAUNCH_PAGE_SEO as $k ) {
					if ( isset( $b['seo'][ $k ] ) ) {
						update_post_meta( $id, $k, $b['seo'][ $k ] );
					} else {
						delete_post_meta( $id, $k );
					}
				}
				wp_update_post( wp_slash( [ 'ID' => $id, 'post_content' => $b['content'] ] ) );
				delete_post_meta( $id, '_elementor_css' );
			}
			break;
		case 'doc_finish':
			bespoke_launch_el_finish();
			break;
		case 'pack':
			if ( function_exists( 'bespoke_seo_undo' ) && ! empty( $u['run'] ) ) {
				update_option( BESPOKE_SEO_RUN, $u['run'], false );
				bespoke_seo_undo();
				if ( ! empty( $u['before'] ) ) {
					update_option( BESPOKE_SEO_RUN, $u['before'], false );
				}
			}
			break;
		case 'reviews':
			global $wpdb;
			foreach ( (array) ( $u['options'] ?? [] ) as $opt => $v ) {
				update_option( $opt, $v );
			}
			$ids = array_map( 'intval', (array) ( $u['opened'] ?? [] ) );
			if ( $ids ) {
				$wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'closed' WHERE ID IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB
				foreach ( $ids as $id ) {
					clean_post_cache( $id );
				}
			}
			break;
		case 'bin':
			add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
			foreach ( (array) ( $u['binned'] ?? [] ) as $id ) {
				wp_untrash_post( (int) $id );
			}
			break;
		case 'fpd':
			if ( ! empty( $u['plugin'] ) ) {
				activate_plugin( $u['plugin'] );
			}
			break;
		case 'caches':
			bespoke_launch_purge_caches();
			break;
	}
}

/** Publish what the run made: the new products and pages, together. */
function bespoke_launch_publish() {
	$run = get_option( BESPOKE_LAUNCH_RUN );
	if ( ! is_array( $run ) ) {
		return 0;
	}
	$done = [];
	foreach ( (array) ( $run['jobs'] ?? [] ) as $job ) {
		foreach ( [ 'products', 'pages' ] as $kind ) {
			foreach ( (array) ( $job['made'][ $kind ] ?? [] ) as $id ) {
				$id = (int) $id;
				if ( 'draft' !== get_post_status( $id ) ) {
					continue;
				}
				if ( 'product' === get_post_type( $id ) && function_exists( 'wc_get_product' ) ) {
					$p = wc_get_product( $id );
					if ( $p ) {
						$p->set_status( 'publish' );
						$p->save();
					}
				} else {
					wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] );
				}
				$done[] = $id;
			}
		}
	}
	$run['published'] = array_values( array_unique( array_merge( (array) ( $run['published'] ?? [] ), $done ) ) );
	update_option( BESPOKE_LAUNCH_RUN, $run, false );
	bespoke_launch_purge_caches();
	return count( $done );
}

/* =========================================================================
   AJAX: one job, or one undo, at a time
   ========================================================================= */

add_action( 'wp_ajax_bespoke_launch_step', function () {
	check_ajax_referer( 'bespoke_launch_step' );
	if ( ! current_user_can( BESPOKE_LAUNCH_CAP ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to do that.' ] );
	}
	$run  = get_option( BESPOKE_LAUNCH_RUN );
	$file = get_option( BESPOKE_LAUNCH_FILE );
	if ( ! is_array( $run ) || ! is_array( $file ) ) {
		wp_send_json_error( [ 'message' => 'There is no launch waiting. Upload the file again.' ] );
	}
	$undoing = ! empty( $_POST['undo'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$jobs    = (array) $run['jobs'];

	if ( $undoing ) {
		// Undo the last job not yet undone.
		for ( $i = count( $jobs ) - 1; $i >= 0; $i-- ) {
			if ( 'done' === ( $jobs[ $i ]['status'] ?? '' ) ) {
				try {
					bespoke_launch_undo_job( $jobs[ $i ] );
				} catch ( Throwable $e ) {
					wp_send_json_error( [ 'message' => $jobs[ $i ]['label'] . ': could not be put back — ' . $e->getMessage() ] );
				}
				$run['jobs'][ $i ]['status'] = 'undone';
				update_option( BESPOKE_LAUNCH_RUN, $run, false );
				$left = 0;
				foreach ( $run['jobs'] as $j ) {
					$left += ( 'done' === ( $j['status'] ?? '' ) ) ? 1 : 0;
				}
				wp_send_json_success( [ 'message' => 'Put back: ' . $jobs[ $i ]['label'], 'left' => $left, 'total' => count( $jobs ) ] );
			}
		}
		bespoke_launch_purge_caches();
		$run['undone'] = current_time( 'mysql' );
		update_option( BESPOKE_LAUNCH_RUN, $run, false );
		wp_send_json_success( [ 'message' => 'Everything is back as it was.', 'left' => 0, 'total' => count( $jobs ), 'finished' => true ] );
	}

	foreach ( $jobs as $i => $job ) {
		if ( 'done' === ( $job['status'] ?? '' ) ) {
			continue;
		}
		try {
			$result = bespoke_launch_run_job( $job, $file );
		} catch ( Throwable $e ) {
			$run['jobs'][ $i ]['status'] = 'failed';
			$run['jobs'][ $i ]['error']  = $e->getMessage();
			update_option( BESPOKE_LAUNCH_RUN, $run, false );
			wp_send_json_error( [ 'message' => $job['label'] . ': ' . $e->getMessage(), 'index' => $i, 'total' => count( $jobs ) ] );
		}
		$run['jobs'][ $i ]['status']  = 'done';
		$run['jobs'][ $i ]['message'] = (string) $result['message'];
		$run['jobs'][ $i ]['undo']    = $result['undo'] ?? [];
		$run['jobs'][ $i ]['made']    = $result['made'] ?? [];
		update_option( BESPOKE_LAUNCH_RUN, $run, false );
		wp_send_json_success( [ 'message' => $job['label'] . ': ' . $result['message'], 'index' => $i, 'total' => count( $jobs ), 'finished' => $i === count( $jobs ) - 1 ] );
	}
	$run['finished'] = current_time( 'mysql' );
	update_option( BESPOKE_LAUNCH_RUN, $run, false );
	wp_send_json_success( [ 'message' => 'All done.', 'index' => count( $jobs ), 'total' => count( $jobs ), 'finished' => true ] );
} );

/* =========================================================================
   THE PAGE: Designs → Launch
   ========================================================================= */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=bespoke_design',
		'Launch',
		'Launch',
		BESPOKE_LAUNCH_CAP,
		'bespoke-launch',
		'bespoke_launch_render_page'
	);
} );

/** The download: runs before any page output. */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['bespoke_launch_make'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! current_user_can( BESPOKE_LAUNCH_CAP ) || ! check_admin_referer( 'bespoke_launch_make' ) ) {
		wp_die( 'You do not have permission to do that.' );
	}
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$file = bespoke_launch_make_file();
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bespoke-launch-' . gmdate( 'Y-m-d-Hi' ) . '.json"' );
	echo wp_json_encode( $file, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
} );

/** Handle the page's buttons; returns a notice. */
function bespoke_launch_handle_post() {
	if ( empty( $_POST['bespoke_launch_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}
	check_admin_referer( 'bespoke_launch' );
	$action = sanitize_key( wp_unslash( $_POST['bespoke_launch_action'] ) );
	switch ( $action ) {
		case 'holding_on':
			bespoke_launch_set_holding( true );
			return '<div class="notice notice-success"><p>The holding page is up. Visitors see it; you, logged in, see the real shop.</p></div>';
		case 'holding_off':
			bespoke_launch_set_holding( false );
			return '<div class="notice notice-success"><p>The holding page is down. The shop is open.</p></div>';
		case 'upload':
			$f = $_FILES['bespoke_launch_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) ) {
				return '<div class="notice notice-error"><p>No file arrived. Try again.</p></div>';
			}
			if ( (int) $f['size'] > 32 * MB_IN_BYTES ) {
				return '<div class="notice notice-error"><p>That file is over 32MB, which a launch file never is.</p></div>';
			}
			$data = json_decode( (string) file_get_contents( $f['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! is_array( $data ) || 'bespoke-launch' !== ( $data['kind'] ?? '' ) ) {
				return '<div class="notice notice-error"><p>That isn\'t a launch file. Make one on staging with "Make the launch file".</p></div>';
			}
			if ( (int) ( $data['format'] ?? 0 ) !== BESPOKE_LAUNCH_FORMAT ) {
				return '<div class="notice notice-error"><p>That file was made by a different version of the plugin. Upload the same plugin to both sites, then make the file again.</p></div>';
			}
			if ( untrailingslashit( (string) ( $data['site'] ?? '' ) ) === untrailingslashit( home_url() ) ) {
				return '<div class="notice notice-error"><p>That file was made on this site. Upload it on the other one.</p></div>';
			}
			update_option( BESPOKE_LAUNCH_FILE, $data, false );
			delete_option( BESPOKE_LAUNCH_RUN );
			return '<div class="notice notice-info"><p>File read. Nothing written yet — check the list below, then Start.</p></div>';
		case 'start':
			$file = get_option( BESPOKE_LAUNCH_FILE );
			if ( ! is_array( $file ) ) {
				return '<div class="notice notice-error"><p>No launch file is waiting. Upload it again.</p></div>';
			}
			$picks = array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) ( $_POST['pick'] ?? [] ) ) );
			$jobs  = bespoke_launch_jobs( $file, $picks );
			if ( ! $jobs ) {
				return '<div class="notice notice-warning"><p>Nothing was ticked.</p></div>';
			}
			update_option( BESPOKE_LAUNCH_RUN, [ 'started' => current_time( 'mysql' ), 'jobs' => $jobs, 'from' => $file['site'] ?? '' ], false );
			return '';
		case 'publish':
			$n = bespoke_launch_publish();
			return '<div class="notice notice-success"><p>Published ' . intval( $n ) . ' new product(s) and page(s). Turn the holding page off when you\'re ready.</p></div>';
		case 'forget':
			delete_option( BESPOKE_LAUNCH_FILE );
			delete_option( BESPOKE_LAUNCH_RUN );
			return '<div class="notice notice-info"><p>Cleared. Nothing on the site was changed by this.</p></div>';
	}
	return '';
}

/** The page. */
function bespoke_launch_render_page() {
	if ( ! current_user_can( BESPOKE_LAUNCH_CAP ) ) {
		wp_die( 'You do not have permission to access this page.' );
	}
	$notice  = bespoke_launch_handle_post();
	$file    = get_option( BESPOKE_LAUNCH_FILE );
	$run     = get_option( BESPOKE_LAUNCH_RUN );
	$holding = bespoke_launch_holding_on();
	$self    = admin_url( 'edit.php?post_type=bespoke_design&page=bespoke-launch' );
	?>
	<div class="wrap bespoke-launch">
		<h1>Launch</h1>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<div class="card" style="max-width:820px;">
			<h2>Holding page: <?php echo $holding ? '<span style="color:#008a20;">ON</span>' : 'off'; ?></h2>
			<p><?php echo $holding
				? 'Visitors see "We\'re updating our shop", with your Facebook and Instagram. Anyone logged in sees the real shop.'
				: 'When it\'s on, visitors see "We\'re updating our shop", with your Facebook and Instagram, while anyone logged in sees the real shop.'; ?>
				<a href="<?php echo esc_url( home_url( '/?bespoke_holding=preview' ) ); ?>" target="_blank" rel="noopener">See it</a></p>
			<form method="post">
				<?php wp_nonce_field( 'bespoke_launch' ); ?>
				<button class="button button-primary" name="bespoke_launch_action" value="<?php echo $holding ? 'holding_off' : 'holding_on'; ?>">
					<?php echo $holding ? 'Turn the holding page off' : 'Turn the holding page on'; ?>
				</button>
			</form>
		</div>

		<?php if ( is_array( $run ) && ! empty( $run['jobs'] ) ) : ?>
			<?php bespoke_launch_render_run( $run ); ?>
		<?php elseif ( is_array( $file ) ) : ?>
			<?php bespoke_launch_render_plan( $file ); ?>
		<?php else : ?>
			<div class="card" style="max-width:820px;">
				<h2>On staging: make the launch file</h2>
				<p>Everything live needs, in one file: the products, the designs and their artwork, the fonts, the customiser settings, the Elementor pages and templates, and the review settings. Making it changes nothing.</p>
				<form method="post">
					<?php wp_nonce_field( 'bespoke_launch_make' ); ?>
					<button class="button" name="bespoke_launch_make" value="1">Make the launch file</button>
				</form>
			</div>
			<div class="card" style="max-width:820px;">
				<h2>On live: read the launch file</h2>
				<p>Nothing is written yet: you'll see everything it would change first.</p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'bespoke_launch' ); ?>
					<input type="file" name="bespoke_launch_file" accept="application/json,.json" required>
					<button class="button button-primary" name="bespoke_launch_action" value="upload">Read the file</button>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/** The list of changes, with a tick for each, and Start. */
function bespoke_launch_render_plan( $file ) {
	$sections = bespoke_launch_plan( $file );
	$count    = 0;
	foreach ( $sections as $s ) {
		$count += count( $s['rows'] );
	}
	?>
	<div class="card" style="max-width:980px;">
		<h2>What this would change</h2>
		<p>From <code><?php echo esc_html( $file['site'] ?? '' ); ?></code>, made <?php echo esc_html( $file['generated'] ?? '' ); ?>.
			Everything ticked runs in this order. What each step replaces is kept, so Undo puts it all back.</p>
		<form method="post">
			<?php wp_nonce_field( 'bespoke_launch' ); ?>
			<?php foreach ( $sections as $s ) : ?>
				<h3 style="margin:22px 0 6px;">
					<label><input type="checkbox" class="bespoke-launch-all" data-section="<?php echo esc_attr( $s['key'] ); ?>" checked>
					<?php echo esc_html( $s['title'] ); ?></label>
					<span style="font-weight:400;color:#666;">(<?php echo intval( count( $s['rows'] ) ); ?>)</span>
				</h3>
				<?php if ( count( $s['rows'] ) > 6 ) : ?><details><summary>Show them</summary><?php endif; ?>
				<table class="widefat striped" style="max-width:940px;">
					<tbody>
					<?php foreach ( $s['rows'] as $r ) : ?>
						<tr>
							<?php if ( ! empty( $r['blocked'] ) ) : ?>
								<td style="width:28px;">—</td>
							<?php else : ?>
								<td style="width:28px;"><input type="checkbox" class="bespoke-launch-pick" data-section="<?php echo esc_attr( $s['key'] ); ?>" name="pick[]" value="<?php echo esc_attr( $r['pick'] ); ?>" <?php checked( empty( $r['unticked'] ) ); ?>></td>
							<?php endif; ?>
							<td style="width:34%;"><?php echo esc_html( $r['label'] ); ?></td>
							<td style="color:<?php echo ! empty( $r['blocked'] ) ? '#b32d2e' : '#555'; ?>;"><?php echo esc_html( $r['note'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $s['rows'] ) > 6 ) : ?></details><?php endif; ?>
			<?php endforeach; ?>
			<p style="margin-top:22px;">
				<?php if ( ! bespoke_launch_holding_on() ) : ?>
					<strong style="color:#b32d2e;">The holding page is off — turn it on first, so visitors don't see the change half done.</strong><br>
				<?php endif; ?>
				<button class="button button-primary button-hero" name="bespoke_launch_action" value="start" <?php disabled( 0, $count ); ?>>Start</button>
				<button class="button" name="bespoke_launch_action" value="forget" formnovalidate>Cancel</button>
			</p>
		</form>
	</div>
	<script>
	document.querySelectorAll('.bespoke-launch-all').forEach(function (all) {
		all.addEventListener('change', function () {
			document.querySelectorAll('.bespoke-launch-pick[data-section="' + all.dataset.section + '"]').forEach(function (c) { c.checked = all.checked; });
		});
	});
	</script>
	<?php
}

/** The run: progress, then Publish and Undo. */
function bespoke_launch_render_run( $run ) {
	$total    = count( $run['jobs'] );
	$done     = 0;
	$undone   = 0;
	$failed   = '';
	foreach ( $run['jobs'] as $j ) {
		$done   += ( 'done' === ( $j['status'] ?? '' ) ) ? 1 : 0;
		$undone += ( 'undone' === ( $j['status'] ?? '' ) ) ? 1 : 0;
		if ( 'failed' === ( $j['status'] ?? '' ) ) {
			$failed = $j['label'] . ': ' . ( $j['error'] ?? '' );
		}
	}
	$finished = ! empty( $run['finished'] ) || ( $done === $total );
	$nonce    = wp_create_nonce( 'bespoke_launch_step' );
	?>
	<div class="card" style="max-width:980px;">
		<h2><?php echo ! empty( $run['undone'] ) ? 'Undone' : ( $finished ? 'Done' : 'Bringing staging across' ); ?></h2>
		<div style="background:#eee;border-radius:4px;height:14px;max-width:900px;overflow:hidden;">
			<div id="bespoke-launch-bar" style="background:#5dcaa5;height:14px;width:<?php echo esc_attr( $total ? round( 100 * $done / $total ) : 0 ); ?>%;"></div>
		</div>
		<p id="bespoke-launch-status"><?php echo ! empty( $run['undone'] ) ? 'All ' . intval( $undone ) . ' steps put back.' : intval( $done ) . ' of ' . intval( $total ) . ' steps done.'; ?></p>
		<?php if ( $failed ) : ?><p style="color:#b32d2e;"><strong>Stopped:</strong> <?php echo esc_html( $failed ); ?></p><?php endif; ?>
		<ol id="bespoke-launch-log" style="max-height:340px;overflow:auto;background:#fafafa;border:1px solid #ddd;padding:10px 10px 10px 34px;max-width:900px;">
			<?php foreach ( $run['jobs'] as $j ) : ?>
				<?php if ( in_array( $j['status'] ?? '', [ 'done', 'undone' ], true ) ) : ?>
					<li><?php echo esc_html( $j['label'] . ': ' . ( $j['message'] ?? '' ) . ( 'undone' === $j['status'] ? ' — put back' : '' ) ); ?></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ol>
		<p>
			<?php if ( ! $finished && empty( $run['undone'] ) ) : ?>
				<button class="button button-primary button-hero" id="bespoke-launch-go"><?php echo $done || $failed ? 'Carry on' : 'Go'; ?></button>
			<?php endif; ?>
			<?php if ( $finished && empty( $run['undone'] ) && empty( $run['published'] ) ) : ?>
				<form method="post" style="display:inline;">
					<?php wp_nonce_field( 'bespoke_launch' ); ?>
					<button class="button button-primary button-hero" name="bespoke_launch_action" value="publish">Publish the new products and pages</button>
				</form>
			<?php endif; ?>
			<?php if ( $done && empty( $run['undone'] ) ) : ?>
				<button class="button" id="bespoke-launch-undo">Undo the launch</button>
			<?php endif; ?>
			<?php if ( ! empty( $run['undone'] ) || ( $finished && ! empty( $run['published'] ) ) ) : ?>
				<form method="post" style="display:inline;">
					<?php wp_nonce_field( 'bespoke_launch' ); ?>
					<button class="button" name="bespoke_launch_action" value="forget">Clear this page</button>
				</form>
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $run['published'] ) && empty( $run['undone'] ) ) : ?>
			<p>Published <?php echo intval( count( $run['published'] ) ); ?> new product(s) and page(s).</p>
		<?php endif; ?>
	</div>
	<script>
	(function () {
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce = <?php echo wp_json_encode( $nonce ); ?>;
		var bar = document.getElementById('bespoke-launch-bar');
		var status = document.getElementById('bespoke-launch-status');
		var log = document.getElementById('bespoke-launch-log');
		function say(text, bad) {
			var li = document.createElement('li');
			li.textContent = text;
			if (bad) { li.style.color = '#b32d2e'; li.style.fontWeight = '700'; }
			log.appendChild(li);
			log.scrollTop = log.scrollHeight;
		}
		function step(undo) {
			var body = new FormData();
			body.append('action', 'bespoke_launch_step');
			body.append('_ajax_nonce', nonce);
			if (undo) { body.append('undo', '1'); }
			fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					var d = r.data || {};
					if (!r.success) { say(d.message || 'Something went wrong.', true); status.textContent = 'Stopped. Fix what it says, then reload this page and press Carry on.'; return; }
					say(d.message);
					if (undo) {
						bar.style.width = (d.total ? Math.round(100 * d.left / d.total) : 0) + '%';
						status.textContent = d.left + ' step(s) still to put back.';
					} else {
						bar.style.width = (d.total ? Math.round(100 * Math.min(d.index + 1, d.total) / d.total) : 100) + '%';
						status.textContent = Math.min(d.index + 1, d.total) + ' of ' + d.total + ' steps done.';
					}
					if (d.finished) { location.reload(); return; }
					step(undo);
				})
				.catch(function () { say('The connection dropped. Reload this page and press Carry on.', true); });
		}
		var go = document.getElementById('bespoke-launch-go');
		if (go) { go.addEventListener('click', function () { go.disabled = true; step(false); }); }
		var undo = document.getElementById('bespoke-launch-undo');
		if (undo) { undo.addEventListener('click', function () {
			if (!confirm('Put back everything the launch changed? New products and pages go to the bin.')) { return; }
			undo.disabled = true; step(true);
		}); }
	})();
	</script>
	<?php
}
