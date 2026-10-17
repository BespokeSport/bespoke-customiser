<?php
/**
 * SEO packs — a group of products' names, wording and SEO, written in one go.
 *
 * Doing a product's SEO by hand means a dozen boxes on every product: its
 * name and web address, both descriptions, Rank Math's title, description
 * and focus keyword, its categories, whether the shop lists it, its picture's
 * alt text and the BEspoke Page fields. A pack holds all of that for a group
 * of products in one file (includes/seo-packs/), and this page applies it:
 *
 *   1. Pick a pack and look at what it would change, product by product.
 *      Nothing is written yet.
 *   2. Apply. Each product keeps what it had before, so Undo puts the last
 *      pack applied back as it was.
 *
 * Products are found by web address — the pack's new one, or an old one it
 * names — never by ID, so the same pack works on staging and, after Copy to
 * Live has made the products there, on live. A pack can also give a page its
 * Rank Math title, description and focus keyword (the page's own words live
 * in Elementor). Prices, stock, options, variations and pictures are never
 * touched.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-seo-pack.php
 *
 * @package BEspoke_Customiser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BESPOKE_SEO_CAP    = 'manage_options';
const BESPOKE_SEO_BACKUP = '_bespoke_seo_backup';   // per-post undo store
const BESPOKE_SEO_RUN    = 'bespoke_seo_last_run';  // what the last apply touched

/** What a page entry may set: a page's words live in Elementor, not here. */
const BESPOKE_SEO_PAGE_FIELDS = [ 'seo_title', 'seo_description', 'focus_keyword' ];

/** The fields a pack can set, in the words the review screen uses. */
function bespoke_seo_fields() {
	return [
		'title'           => 'Name',
		'slug'            => 'Web address',
		'excerpt'         => 'Short description',
		'content'         => 'Description',
		'seo_title'       => 'Rank Math title',
		'seo_description' => 'Rank Math description',
		'focus_keyword'   => 'Focus keyword',
		'categories'      => 'Categories',
		'visibility'      => 'Listed in',
		'menu_order'      => 'Order',
		'image_alt'       => 'Picture alt text',
		'eyebrow'         => 'Eyebrow',
		'subtitle'        => 'Subtitle',
		'price_suffix'    => 'Price suffix',
		'at_a_glance'     => 'At a glance',
	];
}

/** Where a product shows, in plain words. */
function bespoke_seo_visibility_label( $v ) {
	$labels = [
		'visible' => 'the shop and search',
		'catalog' => 'the shop only',
		'search'  => 'search only (not the shop)',
		'hidden'  => 'neither the shop nor search',
	];
	return $labels[ $v ] ?? $v;
}

/** Every pack in includes/seo-packs/, by file name. */
function bespoke_seo_packs() {
	// Each pack file runs in a scope of its own, so the variables it builds
	// its wording with can't collide with anything here.
	$load  = function ( $file ) {
		return include $file;
	};
	$packs = [];
	foreach ( glob( BESPOKE_PLUGIN_DIR . 'includes/seo-packs/*.php' ) ?: [] as $file ) {
		$pack = $load( $file );
		if ( is_array( $pack ) && ( ! empty( $pack['products'] ) || ! empty( $pack['pages'] ) ) ) {
			$packs[ basename( $file, '.php' ) ] = $pack;
		}
	}
	return $packs;
}

/** The first post of this type at any of these web addresses, or 0. */
function bespoke_seo_find( $slugs, $type ) {
	foreach ( array_filter( (array) $slugs ) as $slug ) {
		$found = get_posts( [
			'post_type'        => $type,
			'name'             => sanitize_title( $slug ),
			'post_status'      => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		] );
		if ( $found ) {
			return (int) $found[0];
		}
	}
	return 0;
}

/** Everything a pack can set, as the post has it now. */
function bespoke_seo_read( $id ) {
	$product = ( 'product' === get_post_type( $id ) && function_exists( 'wc_get_product' ) ) ? wc_get_product( $id ) : null;
	$thumb   = get_post_thumbnail_id( $id );
	$cats    = wp_get_object_terms( $id, 'product_cat', [ 'fields' => 'slugs' ] );
	$glance  = get_post_meta( $id, '_bespoke_at_a_glance', true );
	return [
		'title'           => (string) get_post_field( 'post_title', $id ),
		'slug'            => (string) get_post_field( 'post_name', $id ),
		'excerpt'         => (string) get_post_field( 'post_excerpt', $id ),
		'content'         => (string) get_post_field( 'post_content', $id ),
		'seo_title'       => (string) get_post_meta( $id, 'rank_math_title', true ),
		'seo_description' => (string) get_post_meta( $id, 'rank_math_description', true ),
		'focus_keyword'   => (string) get_post_meta( $id, 'rank_math_focus_keyword', true ),
		'categories'      => is_wp_error( $cats ) ? [] : array_values( $cats ),
		'visibility'      => $product ? $product->get_catalog_visibility() : '',
		'menu_order'      => (int) get_post_field( 'menu_order', $id ),
		'image_alt'       => $thumb ? (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) : '',
		'eyebrow'         => (string) get_post_meta( $id, '_bespoke_eyebrow', true ),
		'subtitle'        => (string) get_post_meta( $id, '_bespoke_subtitle', true ),
		'price_suffix'    => (string) get_post_meta( $id, '_bespoke_price_suffix', true ),
		'at_a_glance'     => is_array( $glance ) ? array_values( $glance ) : [],
	];
}

/** What a pack entry asks for, in the same shape bespoke_seo_read() gives. */
function bespoke_seo_target( $entry, $type ) {
	$allowed = ( 'page' === $type ) ? BESPOKE_SEO_PAGE_FIELDS : array_keys( bespoke_seo_fields() );
	$out     = [];
	foreach ( $allowed as $f ) {
		if ( ! array_key_exists( $f, $entry ) ) {
			continue;
		}
		$v = $entry[ $f ];
		if ( 'categories' === $f ) {
			$v = array_values( array_map( 'sanitize_title', (array) $v ) );
		} elseif ( 'menu_order' === $f ) {
			$v = (int) $v;
		} elseif ( 'at_a_glance' === $f ) {
			$rows = [];
			foreach ( (array) $v as $row ) {
				$rows[] = [
					'label' => (string) ( $row[0] ?? $row['label'] ?? '' ),
					'value' => (string) ( $row[1] ?? $row['value'] ?? '' ),
				];
			}
			$v = $rows;
		} elseif ( 'slug' === $f ) {
			$v = sanitize_title( $v );
		} else {
			$v = trim( (string) $v );
		}
		$out[ $f ] = $v;
	}
	return $out;
}

/** Is this field already what the pack asks for? */
function bespoke_seo_same( $f, $a, $b ) {
	if ( is_array( $a ) || is_array( $b ) ) {
		$a = (array) $a;
		$b = (array) $b;
		if ( 'categories' === $f ) {
			sort( $a );
			sort( $b );
		}
		return wp_json_encode( $a ) === wp_json_encode( $b );
	}
	$norm = function ( $s ) {
		return trim( str_replace( "\r\n", "\n", (string) $s ) );
	};
	return $norm( $a ) === $norm( $b );
}

/**
 * What a pack would do here: for each entry, the post it found (or 0) and
 * each field that would change, as [ now, new ].
 */
function bespoke_seo_plan( $pack ) {
	$rows = [];
	$sets = [
		'product' => (array) ( $pack['products'] ?? [] ),
		'page'    => (array) ( $pack['pages'] ?? [] ),
	];
	foreach ( $sets as $type => $entries ) {
		foreach ( $entries as $entry ) {
			// The new web address first: on a site where the pack has been
			// applied (or where Copy to Live made the product), that's the one.
			$slugs = array_merge( [ $entry['slug'] ?? '' ], (array) ( $entry['match'] ?? [] ) );
			$id    = bespoke_seo_find( $slugs, $type );
			$row   = [
				'type'    => $type,
				'entry'   => $entry,
				'id'      => $id,
				// 'label' names a row whose product name the pack leaves alone.
				'label'   => $entry['label'] ?? ( $entry['title'] ?? ( $entry['slug'] ?? ( $entry['match'][0] ?? '' ) ) ),
				'changes' => [],
				'notes'   => [],
			];
			if ( $id ) {
				$now    = bespoke_seo_read( $id );
				$target = bespoke_seo_target( $entry, $type );
				// A web address another post already has would come out as "-2".
				if ( isset( $target['slug'] ) && $target['slug'] !== $now['slug'] ) {
					$other = bespoke_seo_find( [ $target['slug'] ], $type );
					if ( $other && $other !== $id ) {
						unset( $target['slug'] );
						$row['notes'][] = sprintf( 'The web address "%s" belongs to another %s (#%d), so this one keeps its own.', $entry['slug'], $type, $other );
					}
				}
				if ( isset( $target['image_alt'] ) && ! get_post_thumbnail_id( $id ) ) {
					unset( $target['image_alt'] );
					$row['notes'][] = 'It has no picture, so there is no alt text to set.';
				}
				foreach ( $target as $f => $v ) {
					if ( ! bespoke_seo_same( $f, $now[ $f ], $v ) ) {
						$row['changes'][ $f ] = [ $now[ $f ], $v ];
					}
				}
			}
			$rows[] = $row;
		}
	}
	return $rows;
}

/** The categories a pack names that this site doesn't have yet. */
function bespoke_seo_missing_categories( $pack ) {
	$missing = [];
	foreach ( (array) ( $pack['categories'] ?? [] ) as $c ) {
		$slug = sanitize_title( $c['slug'] ?? '' );
		if ( $slug && ! get_term_by( 'slug', $slug, 'product_cat' ) ) {
			$missing[] = $c;
		}
	}
	return $missing;
}

/** Make the pack's categories that are missing, parents first. Returns the new term IDs. */
function bespoke_seo_ensure_categories( $pack ) {
	$created = [];
	foreach ( bespoke_seo_missing_categories( $pack ) as $c ) {
		$parent = 0;
		if ( ! empty( $c['parent'] ) ) {
			$p      = get_term_by( 'slug', sanitize_title( $c['parent'] ), 'product_cat' );
			$parent = $p ? (int) $p->term_id : 0;
		}
		$made = wp_insert_term( $c['name'] ?? $c['slug'], 'product_cat', [
			'slug'        => sanitize_title( $c['slug'] ),
			'parent'      => $parent,
			'description' => (string) ( $c['description'] ?? '' ),
		] );
		if ( ! is_wp_error( $made ) ) {
			$created[] = (int) $made['term_id'];
		}
	}
	return $created;
}

/**
 * Write these fields to a post. Product fields go through WooCommerce, so
 * its caches and the old-address redirect WordPress keeps for a changed web
 * address both follow; the rest is post meta.
 */
function bespoke_seo_write( $id, $values ) {
	if ( 'product' === get_post_type( $id ) && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $id );
		if ( $product ) {
			$setters = [
				'title'      => 'set_name',
				'slug'       => 'set_slug',
				'excerpt'    => 'set_short_description',
				'content'    => 'set_description',
				'menu_order' => 'set_menu_order',
				'visibility' => 'set_catalog_visibility',
			];
			$touched = false;
			foreach ( $setters as $f => $method ) {
				if ( array_key_exists( $f, $values ) ) {
					$product->$method( $values[ $f ] );
					$touched = true;
				}
			}
			if ( array_key_exists( 'categories', $values ) ) {
				$ids = [];
				foreach ( (array) $values['categories'] as $slug ) {
					$term = get_term_by( 'slug', $slug, 'product_cat' );
					if ( $term ) {
						$ids[] = (int) $term->term_id;
					}
				}
				$product->set_category_ids( $ids );
				$touched = true;
			}
			if ( $touched ) {
				$product->save();
			}
		}
	}

	$meta = [
		'seo_title'       => 'rank_math_title',
		'seo_description' => 'rank_math_description',
		'focus_keyword'   => 'rank_math_focus_keyword',
		'eyebrow'         => '_bespoke_eyebrow',
		'subtitle'        => '_bespoke_subtitle',
		'price_suffix'    => '_bespoke_price_suffix',
		'at_a_glance'     => '_bespoke_at_a_glance',
	];
	foreach ( $meta as $f => $key ) {
		if ( ! array_key_exists( $f, $values ) ) {
			continue;
		}
		$v = $values[ $f ];
		if ( '' === $v || [] === $v ) {
			delete_post_meta( $id, $key );
		} else {
			update_post_meta( $id, $key, wp_slash( $v ) );
		}
	}

	if ( array_key_exists( 'image_alt', $values ) ) {
		$thumb = get_post_thumbnail_id( $id );
		if ( $thumb ) {
			update_post_meta( $thumb, '_wp_attachment_image_alt', wp_slash( $values['image_alt'] ) );
		}
	}
}

/**
 * Apply a pack. Each post it changes keeps what it had first (once — applying
 * again doesn't overwrite that), and the run is remembered for Undo.
 *
 * @return int|string How many posts changed, or why nothing could be done.
 */
function bespoke_seo_apply( $key ) {
	$packs = bespoke_seo_packs();
	if ( empty( $packs[ $key ] ) ) {
		return 'That pack is no longer in the plugin.';
	}
	$pack = $packs[ $key ];

	$run = get_option( BESPOKE_SEO_RUN );
	if ( ! is_array( $run ) || ( $run['pack'] ?? '' ) !== $key ) {
		$run = [ 'pack' => $key, 'ids' => [], 'terms' => [] ];
	}
	$run['terms'] = array_values( array_unique( array_merge( (array) $run['terms'], bespoke_seo_ensure_categories( $pack ) ) ) );

	$done = 0;
	foreach ( bespoke_seo_plan( $pack ) as $row ) {
		if ( ! $row['id'] || ! $row['changes'] ) {
			continue;
		}
		$id = $row['id'];
		if ( ! get_post_meta( $id, BESPOKE_SEO_BACKUP, true ) ) {
			update_post_meta( $id, BESPOKE_SEO_BACKUP, wp_slash( [
				'pack'   => $key,
				'values' => bespoke_seo_read( $id ),
			] ) );
		}
		$values = [];
		foreach ( $row['changes'] as $f => $pair ) {
			$values[ $f ] = $pair[1];
		}
		bespoke_seo_write( $id, $values );
		$run['ids'][] = $id;
		$done++;
	}

	$run['ids']  = array_values( array_unique( array_map( 'intval', $run['ids'] ) ) );
	$run['when'] = current_time( 'mysql' );
	update_option( BESPOKE_SEO_RUN, $run, false );
	return $done;
}

/** Put back what the last pack changed, and remove categories it made that are now empty. */
function bespoke_seo_undo() {
	$run = get_option( BESPOKE_SEO_RUN );
	if ( ! is_array( $run ) ) {
		return 0;
	}
	$n = 0;
	foreach ( (array) ( $run['ids'] ?? [] ) as $id ) {
		$backup = get_post_meta( $id, BESPOKE_SEO_BACKUP, true );
		if ( ! is_array( $backup ) || empty( $backup['values'] ) ) {
			continue;
		}
		$values = $backup['values'];
		if ( 'page' === get_post_type( $id ) ) {
			$values = array_intersect_key( $values, array_flip( BESPOKE_SEO_PAGE_FIELDS ) );
		}
		bespoke_seo_write( $id, $values );
		delete_post_meta( $id, BESPOKE_SEO_BACKUP );
		$n++;
	}
	// Made parent first, so removed child first.
	foreach ( array_reverse( (array) ( $run['terms'] ?? [] ) ) as $term_id ) {
		$term = get_term( (int) $term_id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
			wp_delete_term( (int) $term_id, 'product_cat' );
		}
	}
	delete_option( BESPOKE_SEO_RUN );
	return $n;
}

/* =========================================================================
   ADMIN PAGE — Designs → SEO Packs, beside Copy to Live.
   ========================================================================= */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=bespoke_design',
		'SEO Packs',
		'SEO Packs',
		BESPOKE_SEO_CAP,
		'bespoke-seo',
		'bespoke_seo_render_page'
	);
} );

/**
 * A field's value, short enough for the review table. $names: category
 * names by slug from the pack, for categories this site hasn't made yet.
 */
function bespoke_seo_show( $f, $v, $names = [] ) {
	if ( 'content' === $f || 'excerpt' === $f ) {
		$words = str_word_count( wp_strip_all_tags( (string) $v ) );
		return $words ? $words . ' words' : 'empty';
	}
	if ( 'categories' === $f ) {
		$list = [];
		foreach ( (array) $v as $slug ) {
			$term   = get_term_by( 'slug', $slug, 'product_cat' );
			$list[] = $term ? $term->name : ( $names[ $slug ] ?? $slug );
		}
		return ! empty( $list ) ? implode( ', ', $list ) : 'none';
	}
	if ( 'visibility' === $f ) {
		return bespoke_seo_visibility_label( $v );
	}
	if ( 'at_a_glance' === $f ) {
		$n = count( (array) $v );
		return $n ? $n . ' rows' : 'none';
	}
	$v = (string) $v;
	return '' === $v ? 'empty' : $v;
}

function bespoke_seo_render_page() {
	if ( ! current_user_can( BESPOKE_SEO_CAP ) ) {
		wp_die( 'You do not have permission to access this page.' );
	}

	$notice = '';
	if ( isset( $_POST['bespoke_seo_apply'] ) && check_admin_referer( 'bespoke_seo_apply' ) ) {
		$done = bespoke_seo_apply( sanitize_key( wp_unslash( $_POST['pack'] ?? '' ) ) );
		$notice = is_int( $done )
			? '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Done: %d updated. What each had before is kept, so Undo puts it back.', $done ) ) . '</p></div>'
			: '<div class="notice notice-error"><p>' . esc_html( $done ) . '</p></div>';
	} elseif ( isset( $_POST['bespoke_seo_undo'] ) && check_admin_referer( 'bespoke_seo_undo' ) ) {
		$n      = bespoke_seo_undo();
		$notice = '<div class="notice notice-info"><p>' . esc_html( sprintf( 'Put back %d as they were before.', $n ) ) . '</p></div>';
	}

	$packs = bespoke_seo_packs();
	$key   = sanitize_key( wp_unslash( $_GET['pack'] ?? '' ) );
	$run   = get_option( BESPOKE_SEO_RUN );
	$base  = admin_url( 'edit.php?post_type=bespoke_design&page=bespoke-seo' );
	?>
	<div class="wrap">
		<h1>SEO Packs</h1>
		<p style="max-width:75ch;">
			Each pack holds the names, web addresses, descriptions, Rank Math title,
			description and focus keyword, categories and alt text for a group of
			products — written for search, so you don't type them in one by one.
			<strong>Nothing changes until you press Apply</strong>, and Undo puts the
			last pack back as it was. Prices, stock, options and pictures are never
			touched.
		</p>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<?php if ( ! $packs ) : ?>
			<p>There are no packs in <code>includes/seo-packs/</code>.</p>
		<?php endif; ?>

		<?php if ( $key && isset( $packs[ $key ] ) ) : ?>
			<?php bespoke_seo_render_review( $key, $packs[ $key ], $base ); ?>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px;">
				<thead><tr><th>Pack</th><th>What it covers</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $packs as $k => $p ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $p['name'] ?? $k ); ?></strong></td>
						<td><?php echo esc_html( $p['summary'] ?? '' ); ?></td>
						<td><a class="button" href="<?php echo esc_url( add_query_arg( 'pack', $k, $base ) ); ?>">Look at the changes</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( is_array( $run ) && ! empty( $run['ids'] ) ) : ?>
			<hr/>
			<h2>Last applied</h2>
			<p>
				<?php echo esc_html( ( $packs[ $run['pack'] ]['name'] ?? $run['pack'] ) . ' — ' . ( $run['when'] ?? '' ) . ' — ' . count( $run['ids'] ) . ' updated.' ); ?>
				What they had before is still stored.
			</p>
			<form method="post" onsubmit="return confirm('Put back the names, web addresses, wording and SEO these had before the pack?');">
				<?php wp_nonce_field( 'bespoke_seo_undo' ); ?>
				<button class="button" name="bespoke_seo_undo" value="1">Undo</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/** The review: what the pack would change here, and the button that does it. */
function bespoke_seo_render_review( $key, $pack, $base ) {
	$plan    = bespoke_seo_plan( $pack );
	$fields  = bespoke_seo_fields();
	$missing = bespoke_seo_missing_categories( $pack );
	$todo    = 0;
	$names   = [];
	foreach ( (array) ( $pack['categories'] ?? [] ) as $c ) {
		$names[ sanitize_title( $c['slug'] ?? '' ) ] = $c['name'] ?? ( $c['slug'] ?? '' );
	}
	?>
	<h2><?php echo esc_html( $pack['name'] ?? $key ); ?></h2>
	<p><a href="<?php echo esc_url( $base ); ?>">&larr; All packs</a></p>
	<?php if ( $missing ) : ?>
		<p><strong>New categories:</strong>
			<?php echo esc_html( implode( ', ', array_map( function ( $c ) { return $c['name'] ?? $c['slug']; }, $missing ) ) ); ?>
		</p>
	<?php endif; ?>
	<table class="widefat striped" style="max-width:1100px;">
		<thead><tr><th style="width:24%;">In the pack</th><th style="width:22%;">On this site</th><th>What changes</th></tr></thead>
		<tbody>
		<?php foreach ( $plan as $row ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $row['label'] ); ?></strong><br/><span style="color:#777;"><?php echo esc_html( $row['type'] ); ?></span></td>
				<td>
					<?php if ( $row['id'] ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>">#<?php echo intval( $row['id'] ); ?></a>
						<?php echo esc_html( get_the_title( $row['id'] ) . ' (' . get_post_status( $row['id'] ) . ')' ); ?>
					<?php elseif ( 'page' === $row['type'] ) : ?>
						<span style="color:#b26a00;">Not here yet — make the page, then apply again.</span>
					<?php else : ?>
						<span style="color:#b26a00;">Not here yet — make it with Copy to Live first.</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $row['id'] && ! $row['changes'] ) : ?>
						<span style="color:#1e7e34;">Already done.</span>
					<?php endif; ?>
					<?php if ( $row['changes'] ) : $todo++; ?>
						<ul style="margin:0;">
						<?php foreach ( $row['changes'] as $f => $pair ) : ?>
							<li><strong><?php echo esc_html( $fields[ $f ] ?? $f ); ?>:</strong>
								<?php if ( in_array( $f, [ 'title', 'slug', 'visibility', 'menu_order', 'categories', 'content', 'excerpt', 'at_a_glance' ], true ) ) : ?>
									<?php echo esc_html( bespoke_seo_show( $f, $pair[0], $names ) ); ?> &rarr;
								<?php endif; ?>
								<?php echo esc_html( bespoke_seo_show( $f, $pair[1], $names ) ); ?>
							</li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php foreach ( $row['notes'] as $note ) : ?>
						<p style="margin:4px 0 0;color:#b26a00;"><?php echo esc_html( $note ); ?></p>
					<?php endforeach; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $todo || $missing ) : ?>
		<form method="post" style="margin-top:16px;" onsubmit="return confirm('Apply this pack? What each has now is kept, so Undo puts it back.');">
			<?php wp_nonce_field( 'bespoke_seo_apply' ); ?>
			<input type="hidden" name="pack" value="<?php echo esc_attr( $key ); ?>"/>
			<button class="button button-primary" name="bespoke_seo_apply" value="1">Apply this pack</button>
		</form>
	<?php else : ?>
		<p style="margin-top:16px;color:#1e7e34;"><strong>Everything in this pack is already done here.</strong></p>
	<?php endif; ?>

	<?php
	// A page's own words live in Elementor, so the pack can't write them:
	// they're here to copy in, block by block.
	foreach ( $plan as $row ) :
		if ( 'page' !== $row['type'] || empty( $row['entry']['elementor'] ) ) {
			continue;
		}
		?>
		<hr/>
		<h2>Words for the <?php echo esc_html( preg_replace( '/\s*\(page\)$/', '', $row['label'] ) ); ?> page</h2>
		<p style="max-width:75ch;">Paste these into the page in Elementor, in this order. The Rank Math title, description and focus keyword above are set by the pack itself once the page exists.</p>
		<table class="widefat" style="max-width:900px;">
			<tbody>
			<?php foreach ( (array) $row['entry']['elementor'] as $i => $block ) : ?>
				<tr>
					<td style="width:28%;vertical-align:top;"><strong><?php echo esc_html( $block[0] ?? '' ); ?></strong></td>
					<td>
						<textarea readonly rows="<?php echo esc_attr( max( 1, min( 8, (int) ceil( strlen( (string) ( $block[1] ?? '' ) ) / 90 ) + substr_count( (string) ( $block[1] ?? '' ), "\n" ) ) ) ); ?>" style="width:100%;" id="bespoke-seo-copy-<?php echo intval( $i ); ?>"><?php echo esc_textarea( $block[1] ?? '' ); ?></textarea>
						<button type="button" class="button bespoke-seo-copy" data-for="bespoke-seo-copy-<?php echo intval( $i ); ?>">Copy</button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>
	<script>
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.bespoke-seo-copy') : null;
		if (!b) return;
		var box = document.getElementById(b.getAttribute('data-for'));
		if (!box) return;
		var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(box.value).then(done, function () { box.select(); document.execCommand('copy'); done(); });
		} else {
			box.select(); document.execCommand('copy'); done();
		}
	});
	</script>
	<?php
}
