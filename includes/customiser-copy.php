<?php
/**
 * Copy product content from staging to live.
 *
 * WooCommerce's own product export can't do this job: it silently drops any
 * field holding a list — which is exactly how At a glance, the sizing charts
 * and the customiser's size buttons are stored — and its import then
 * overwrites every column in the file, so a full export would push staging's
 * prices and stock onto live as well.
 *
 * For a product that is ALREADY on live, this copies only what the list
 * below names: the two descriptions, the BEspoke Page fields, the customiser
 * setup, the Rank Math title and description, and which designs it shows.
 * Its prices, stock, options, variations, images and categories are never
 * read and never written.
 *
 * A product that is NOT on live yet — the running tags, the awareness tags,
 * the boot stickers — can be created there as a DRAFT, which no customer
 * sees until it is published: its type, prices, options and variations,
 * postage class, categories and pictures come with it, then its wording and
 * customiser setup as for any other product.
 *
 * And the settings the customiser keeps outside the products: which artwork
 * each product type uses (Product Setup), the saved placements, and any
 * product types added on Product Setup.
 *
 * How it's used on launch day:
 *   1. On STAGING: tick the products, download the file.
 *   2. On LIVE: upload the file. Nothing is written yet — you get a list of
 *      what would change, product by product and setting by setting.
 *   3. Tick what you want and apply. The previous values are kept and new
 *      products are only drafts, so one click puts it all back.
 *
 * @package BEspoke_Customiser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BESPOKE_COPY_FORMAT  = 2;                        // export file version (1 is still read)
const BESPOKE_COPY_BACKUP  = '_bespoke_copy_backup';   // per-product undo store
const BESPOKE_COPY_CAP     = 'manage_options';
const BESPOKE_COPY_SOURCE  = '_bespoke_copy_source';   // where a copied picture came from
const BESPOKE_COPY_DESIGNS = '_bespoke_design_allow';  // "Designs shown" on a product

/** The artwork Product Setup holds for each product type. */
const BESPOKE_COPY_ARTWORK = [
	'background'     => 'background',
	'background_alt' => 'second background',
	'pad_base'       => 'pad base',
	'highlights'     => 'highlights',
	'shadow'         => 'shadow',
	'mask'           => 'mask / stencil',
	'mask_alt'       => 'second mask',
];

/**
 * Everything the tool will carry, and nothing else.
 *
 * 'post' fields live on the product itself; 'meta' fields are post meta.
 * Anything not named here is never read from staging nor written to live.
 *
 * @return array<string,array{label:string,where:string,kind:string}>
 */
function bespoke_copy_fields() {
	return [
		'post_content'               => [ 'label' => 'Description',            'where' => 'post', 'kind' => 'html' ],
		'post_excerpt'               => [ 'label' => 'Short description',      'where' => 'post', 'kind' => 'html' ],
		'_bespoke_product_type'      => [ 'label' => 'Customiser type',        'where' => 'meta', 'kind' => 'text' ],
		'_bespoke_eyebrow'           => [ 'label' => 'Eyebrow',                'where' => 'meta', 'kind' => 'text' ],
		'_bespoke_subtitle'          => [ 'label' => 'Subtitle',               'where' => 'meta', 'kind' => 'text' ],
		'_bespoke_price_suffix'      => [ 'label' => 'Price suffix',           'where' => 'meta', 'kind' => 'text' ],
		'_bespoke_at_a_glance'       => [ 'label' => 'At a glance',            'where' => 'meta', 'kind' => 'rows' ],
		'_bespoke_customiser_sizes'  => [ 'label' => 'Customiser size buttons','where' => 'meta', 'kind' => 'rows' ],
		'_bespoke_sizing_headers'    => [ 'label' => 'Sizing chart headings',  'where' => 'meta', 'kind' => 'rows' ],
		'_bespoke_sizing_chart'      => [ 'label' => 'Sizing chart rows',      'where' => 'meta', 'kind' => 'rows' ],
		'rank_math_title'            => [ 'label' => 'SEO title',              'where' => 'meta', 'kind' => 'text' ],
		'rank_math_description'      => [ 'label' => 'SEO description',        'where' => 'meta', 'kind' => 'text' ],
		'rank_math_focus_keyword'    => [ 'label' => 'SEO focus keyword',      'where' => 'meta', 'kind' => 'text' ],
		'_bespoke_design_dropdown'   => [ 'label' => 'Designs as a dropdown',  'where' => 'meta', 'kind' => 'text' ],
	];
}

/**
 * One product's copyable content, exactly as stored.
 *
 * @param  int $id Product ID.
 * @return array<string,mixed>
 */
function bespoke_copy_read_product( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'product' !== $post->post_type ) {
		return [];
	}
	$out = [];
	foreach ( bespoke_copy_fields() as $key => $field ) {
		if ( 'post' === $field['where'] ) {
			$out[ $key ] = (string) $post->{$key};
		} else {
			$value = get_post_meta( $id, $key, true );
			$out[ $key ] = ( '' === $value ) ? '' : $value;
		}
	}
	return $out;
}

/**
 * Are two stored values the same? Lists are compared by their content, not
 * by how PHP happens to have them keyed.
 *
 * @param  mixed $a First value.
 * @param  mixed $b Second value.
 * @return bool
 */
function bespoke_copy_same( $a, $b ) {
	if ( is_array( $a ) || is_array( $b ) ) {
		return wp_json_encode( $a ) === wp_json_encode( $b );
	}
	return (string) $a === (string) $b;
}

/**
 * A short, readable description of a value, for the preview table.
 *
 * @param  mixed  $value The stored value.
 * @param  string $kind  'text', 'html' or 'rows'.
 * @return string
 */
function bespoke_copy_describe( $value, $kind ) {
	if ( 'rows' === $kind ) {
		if ( ! is_array( $value ) || ! $value ) {
			return 'empty';
		}
		$filled = 0;
		foreach ( $value as $row ) {
			if ( is_array( $row ) ) {
				foreach ( $row as $cell ) {
					if ( '' !== trim( (string) $cell ) ) { $filled++; break; }
				}
			} elseif ( '' !== trim( (string) $row ) ) {
				$filled++;
			}
		}
		return $filled . ( 1 === $filled ? ' row' : ' rows' );
	}
	$text = trim( wp_strip_all_tags( (string) $value ) );
	if ( '' === $text ) {
		return 'empty';
	}
	if ( 'html' === $kind ) {
		return number_format_i18n( str_word_count( $text ) ) . ' words';
	}
	return mb_strlen( $text ) > 60 ? mb_substr( $text, 0, 57 ) . '…' : $text;
}

/**
 * Find the product on THIS site that an exported one refers to.
 *
 * Web address first, because it survives a rebuild; the ID only as a
 * fallback, and only when it really is a product with the same name. Drafts
 * count, so a product this tool created on an earlier run is found again
 * rather than made twice.
 *
 * @param  array $entry One product from an export file.
 * @return int  Product ID, or 0 when there's no safe match.
 */
function bespoke_copy_match_product( $entry ) {
	$slug = isset( $entry['slug'] ) ? sanitize_title( $entry['slug'] ) : '';
	if ( $slug ) {
		$found = get_posts( [
			'post_type'        => 'product',
			'name'             => $slug,
			'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		] );
		if ( $found ) {
			return (int) $found[0];
		}
	}
	$id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
	if ( $id && 'product' === get_post_type( $id ) ) {
		$here = get_post( $id );
		if ( $here && isset( $entry['name'] ) && $here->post_title === $entry['name'] ) {
			return $id;
		}
	}
	return 0;
}

/* =========================================================================
   DESIGNS SHOWN
   A product can be limited to some of its type's designs (the tick-list in
   its BEspoke customiser box). They're stored as design numbers, which are
   different on every site, so they travel by name.
   ========================================================================= */

/**
 * The designs a product is limited to, by names that mean the same on both
 * sites. Empty when it shows every design of its type.
 *
 * @param  int $id Product ID.
 * @return array[] [ 'slug' => …, 'name' => … ]
 */
function bespoke_copy_design_refs( $id ) {
	$out = [];
	foreach ( (array) get_post_meta( $id, BESPOKE_COPY_DESIGNS, true ) as $did ) {
		$did = (int) $did;
		if ( $did && 'bespoke_design' === get_post_type( $did ) ) {
			$out[] = [
				'slug' => (string) get_post_field( 'post_name', $did ),
				'name' => (string) get_the_title( $did ),
			];
		}
	}
	return $out;
}

/**
 * The design on THIS site a reference names, or 0. Web address first, then
 * the title.
 *
 * @param  array $ref [ 'slug' => …, 'name' => … ]
 * @return int
 */
function bespoke_copy_find_design( $ref ) {
	$tries = [
		[ 'name'  => sanitize_title( (string) ( $ref['slug'] ?? '' ) ) ],
		[ 'title' => (string) ( $ref['name'] ?? '' ) ],
	];
	foreach ( $tries as $by ) {
		if ( '' === (string) reset( $by ) ) {
			continue;
		}
		$found = get_posts( $by + [
			'post_type'        => 'bespoke_design',
			'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		] );
		if ( $found ) {
			return (int) $found[0];
		}
	}
	return 0;
}

/**
 * What a product's "Designs shown" would be here: the matching designs, the
 * names not found, and whether that differs from what it has now.
 *
 * A list with names missing is never written. Written short it would offer
 * fewer designs than staging; written empty it would offer every design of
 * the type. The design library has to come across first.
 *
 * @param  int   $id   Product here (0 for one not made yet).
 * @param  array $refs From the export file.
 * @return array{ids:int[],missing:string[],changed:bool,from:string,to:string}
 */
function bespoke_copy_design_plan( $id, $refs ) {
	$ids     = [];
	$missing = [];
	foreach ( (array) $refs as $ref ) {
		$found = bespoke_copy_find_design( (array) $ref );
		if ( $found ) {
			$ids[] = $found;
		} else {
			$missing[] = (string) ( $ref['name'] ?? ( $ref['slug'] ?? '?' ) );
		}
	}
	$current = $id ? array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, BESPOKE_COPY_DESIGNS, true ) ) ) ) : [];
	$a = $ids;
	$b = $current;
	sort( $a );
	sort( $b );
	$names = function ( $list ) {
		if ( ! $list ) {
			return 'every design of its type';
		}
		return implode( ', ', array_map( 'get_the_title', $list ) );
	};
	if ( $missing ) {
		$to = 'not found here: ' . implode( ', ', $missing );
	} else {
		$to = $names( $ids );
	}
	return [
		'ids'     => $ids,
		'missing' => $missing,
		'changed' => $missing ? true : ( $a !== $b ),
		'from'    => $names( $current ),
		'to'      => $to,
	];
}

/**
 * Work out what an export file would change on this site, without changing
 * anything. Each product comes back with its match and a per-field verdict.
 *
 * @param  array $file Decoded export file.
 * @return array
 */
function bespoke_copy_plan( $file ) {
	$fields = bespoke_copy_fields();
	$plan   = [];

	foreach ( (array) ( $file['products'] ?? [] ) as $entry ) {
		$id      = bespoke_copy_match_product( $entry );
		$changes = [];
		if ( $id ) {
			$here = bespoke_copy_read_product( $id );
			foreach ( $fields as $key => $field ) {
				if ( ! array_key_exists( $key, $entry['fields'] ?? [] ) ) {
					continue;
				}
				$incoming = $entry['fields'][ $key ];
				$current  = $here[ $key ] ?? '';
				if ( bespoke_copy_same( $incoming, $current ) ) {
					continue;
				}
				$changes[ $key ] = [
					'label' => $field['label'],
					'from'  => bespoke_copy_describe( $current, $field['kind'] ),
					'to'    => bespoke_copy_describe( $incoming, $field['kind'] ),
				];
			}
			// "Designs shown" — files made before it existed don't carry it,
			// and leave it as it is.
			if ( array_key_exists( 'design_allow', $entry ) ) {
				$dp = bespoke_copy_design_plan( $id, $entry['design_allow'] );
				if ( $dp['changed'] ) {
					$changes[ BESPOKE_COPY_DESIGNS ] = [
						'label'   => 'Designs shown',
						'from'    => $dp['from'],
						'to'      => $dp['to'],
						'blocked' => (bool) $dp['missing'],
					];
				}
			}
		}
		$plan[] = [
			'entry'    => $entry,
			'id'       => $id,
			'name'     => $entry['name'] ?? ( $entry['slug'] ?? '(unnamed)' ),
			'slug'     => $entry['slug'] ?? '',
			'matched'  => (bool) $id,
			'changes'  => $changes,
			'creatable'=> ! $id && ! empty( $entry['create'] ) && is_array( $entry['create'] ),
		];
	}
	return $plan;
}

/** The fields of a planned change that can actually be written. */
function bespoke_copy_writable( $changes ) {
	$keys = [];
	foreach ( (array) $changes as $key => $c ) {
		if ( empty( $c['blocked'] ) ) {
			$keys[] = $key;
		}
	}
	return $keys;
}

/**
 * Write one product's incoming values, keeping the old ones so the import
 * can be undone. Returns the fields actually written.
 *
 * @param  int   $id     Product on this site.
 * @param  array $entry  That product's entry from the export file.
 * @param  array $only   Field keys to write.
 * @return string[]
 */
function bespoke_copy_apply_product( $id, $entry, $only ) {
	$fields  = bespoke_copy_fields();
	$written = [];
	$backup  = [];
	$post    = [];

	foreach ( $only as $key ) {
		// "Designs shown": by name, and never with a name missing.
		if ( BESPOKE_COPY_DESIGNS === $key ) {
			if ( ! array_key_exists( 'design_allow', $entry ) ) {
				continue;
			}
			$dp = bespoke_copy_design_plan( $id, $entry['design_allow'] );
			if ( $dp['missing'] ) {
				continue;
			}
			$backup[ $key ] = get_post_meta( $id, $key, true );
			if ( $dp['ids'] ) {
				update_post_meta( $id, $key, $dp['ids'] );
			} else {
				delete_post_meta( $id, $key );
			}
			$written[] = $key;
			continue;
		}
		if ( ! isset( $fields[ $key ] ) || ! array_key_exists( $key, $entry['fields'] ?? [] ) ) {
			continue;
		}
		$field    = $fields[ $key ];
		$incoming = $entry['fields'][ $key ];

		if ( 'post' === $field['where'] ) {
			$backup[ $key ] = (string) get_post_field( $key, $id );
			// wp_kses_post keeps the shortcodes and mark-up product
			// descriptions use, and drops anything a description has no
			// business carrying.
			$post[ $key ]   = wp_kses_post( (string) $incoming );
		} else {
			$backup[ $key ] = get_post_meta( $id, $key, true );
			update_post_meta( $id, $key, bespoke_copy_clean( $incoming ) );
		}
		$written[] = $key;
	}

	if ( $post ) {
		$post['ID'] = $id;
		wp_update_post( $post );
	}
	if ( $written ) {
		update_post_meta( $id, BESPOKE_COPY_BACKUP, [
			'when'   => current_time( 'mysql' ),
			'values' => $backup,
		] );
	}
	return $written;
}

/**
 * Clean an incoming meta value: strings are sanitised as text, lists are
 * walked and each cell sanitised, so nothing arrives as mark-up or objects.
 *
 * @param  mixed $value Incoming value.
 * @return mixed
 */
function bespoke_copy_clean( $value ) {
	if ( is_array( $value ) ) {
		$out = [];
		foreach ( $value as $k => $v ) {
			$out[ is_int( $k ) ? $k : sanitize_key( $k ) ] = bespoke_copy_clean( $v );
		}
		return $out;
	}
	if ( is_bool( $value ) || is_numeric( $value ) ) {
		return $value;
	}
	return sanitize_textarea_field( (string) $value );
}

/**
 * Put back what the last import on this product overwrote.
 *
 * @param  int $id Product ID.
 * @return bool Whether anything was restored.
 */
function bespoke_copy_undo_product( $id ) {
	$backup = get_post_meta( $id, BESPOKE_COPY_BACKUP, true );
	if ( ! is_array( $backup ) || empty( $backup['values'] ) ) {
		return false;
	}
	$fields = bespoke_copy_fields();
	$post   = [];
	foreach ( $backup['values'] as $key => $old ) {
		if ( BESPOKE_COPY_DESIGNS === $key ) {
			if ( $old ) {
				update_post_meta( $id, $key, $old );
			} else {
				delete_post_meta( $id, $key );
			}
			continue;
		}
		if ( ! isset( $fields[ $key ] ) ) {
			continue;
		}
		if ( 'post' === $fields[ $key ]['where'] ) {
			$post[ $key ] = $old;
		} else {
			update_post_meta( $id, $key, $old );
		}
	}
	if ( $post ) {
		$post['ID'] = $id;
		wp_update_post( $post );
	}
	delete_post_meta( $id, BESPOKE_COPY_BACKUP );
	return true;
}

/* =========================================================================
   NEW PRODUCTS
   A product not on live yet is created there as a DRAFT: what it is, what it
   costs, its options and variations, postage class, categories and pictures.
   Nothing about a product already on live goes through here.
   ========================================================================= */

/** A picture, by its address and words, so it can be fetched on the other site. */
function bespoke_copy_image_ref( $att_id ) {
	$att_id = (int) $att_id;
	$url    = $att_id ? wp_get_attachment_url( $att_id ) : '';
	if ( ! $url ) {
		return null;
	}
	return [
		'url'   => $url,
		'alt'   => (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true ),
		'title' => (string) get_the_title( $att_id ),
	];
}

/** A postage class by name and web address, or null. */
function bespoke_copy_class_ref( $class_id ) {
	$t = $class_id ? get_term( (int) $class_id, 'product_shipping_class' ) : null;
	return ( $t && ! is_wp_error( $t ) ) ? [ 'slug' => $t->slug, 'name' => $t->name ] : null;
}

/**
 * Everything needed to make this product on another site: what it is, what
 * it costs, its options and variations, postage class, categories, tags and
 * pictures. Its wording travels in the ordinary fields.
 *
 * @param  int $id Product ID.
 * @return array|null
 */
function bespoke_copy_product_shape( $id ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return null;
	}
	$p = wc_get_product( $id );
	if ( ! $p ) {
		return null;
	}

	$cats = [];
	foreach ( (array) $p->get_category_ids( 'edit' ) as $cid ) {
		$t = get_term( (int) $cid, 'product_cat' );
		if ( ! $t || is_wp_error( $t ) ) {
			continue;
		}
		$parent = $t->parent ? get_term( (int) $t->parent, 'product_cat' ) : null;
		$ok     = $parent && ! is_wp_error( $parent );
		$cats[] = [
			'slug'        => $t->slug,
			'name'        => $t->name,
			'parent_slug' => $ok ? $parent->slug : '',
			'parent_name' => $ok ? $parent->name : '',
		];
	}
	$tags = [];
	foreach ( (array) $p->get_tag_ids( 'edit' ) as $tid ) {
		$t = get_term( (int) $tid, 'product_tag' );
		if ( $t && ! is_wp_error( $t ) ) {
			$tags[] = $t->name;
		}
	}

	$attributes = [];
	foreach ( $p->get_attributes( 'edit' ) as $key => $attr ) {
		if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
			continue;
		}
		$is_tax = $attr->is_taxonomy();
		$names  = [];
		$slugs  = [];
		if ( $is_tax ) {
			foreach ( (array) $attr->get_terms() as $term ) {
				$names[] = $term->name;
				$slugs[] = $term->slug;
			}
		} else {
			$names = array_values( array_map( 'strval', (array) $attr->get_options() ) );
		}
		$attributes[] = [
			'key'       => (string) $key,
			'taxonomy'  => $is_tax ? $attr->get_name() : '',
			'label'     => $is_tax ? wc_attribute_label( $attr->get_name() ) : $attr->get_name(),
			'options'   => $names,
			'slugs'     => $slugs,
			'visible'   => (bool) $attr->get_visible(),
			'variation' => (bool) $attr->get_variation(),
			'position'  => (int) $attr->get_position(),
		];
	}

	$variations = [];
	if ( $p->is_type( 'variable' ) ) {
		foreach ( $p->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v ) {
				continue;
			}
			$variations[] = [
				'attributes'     => (array) $v->get_attributes( 'edit' ),
				'regular_price'  => (string) $v->get_regular_price( 'edit' ),
				'sale_price'     => (string) $v->get_sale_price( 'edit' ),
				'enabled'        => 'publish' === $v->get_status( 'edit' ),
				'shipping_class' => bespoke_copy_class_ref( $v->get_shipping_class_id( 'edit' ) ),
				'menu_order'     => (int) $v->get_menu_order( 'edit' ),
				'description'    => (string) $v->get_description( 'edit' ),
			];
		}
	}

	return [
		'type'               => $p->get_type(),
		'regular_price'      => (string) $p->get_regular_price( 'edit' ),
		'sale_price'         => (string) $p->get_sale_price( 'edit' ),
		'tax_status'         => (string) $p->get_tax_status( 'edit' ),
		'tax_class'          => (string) $p->get_tax_class( 'edit' ),
		'shipping_class'     => bespoke_copy_class_ref( $p->get_shipping_class_id( 'edit' ) ),
		'weight'             => (string) $p->get_weight( 'edit' ),
		'length'             => (string) $p->get_length( 'edit' ),
		'width'              => (string) $p->get_width( 'edit' ),
		'height'             => (string) $p->get_height( 'edit' ),
		'menu_order'         => (int) $p->get_menu_order( 'edit' ),
		'catalog_visibility' => (string) $p->get_catalog_visibility( 'edit' ),
		'categories'         => $cats,
		'tags'               => $tags,
		'image'              => bespoke_copy_image_ref( $p->get_image_id( 'edit' ) ),
		'gallery'            => array_values( array_filter( array_map( 'bespoke_copy_image_ref', (array) $p->get_gallery_image_ids( 'edit' ) ) ) ),
		'attributes'         => $attributes,
		'variations'         => $variations,
	];
}

/**
 * A short description of a product the file would create, for the preview.
 *
 * @param  array $shape From the export file.
 * @return string
 */
function bespoke_copy_describe_shape( $shape ) {
	$bits = [];
	if ( 'variable' === ( $shape['type'] ?? '' ) ) {
		$prices = [];
		foreach ( (array) ( $shape['variations'] ?? [] ) as $v ) {
			if ( '' !== (string) ( $v['regular_price'] ?? '' ) ) {
				$prices[] = (float) $v['regular_price'];
			}
		}
		$n      = count( (array) ( $shape['variations'] ?? [] ) );
		$bits[] = $n . ' option' . ( 1 === $n ? '' : 's' )
			. ( $prices ? ', £' . number_format( min( $prices ), 2 ) . ( max( $prices ) > min( $prices ) ? '–£' . number_format( max( $prices ), 2 ) : '' ) : ', no prices yet' );
	} else {
		$price  = (string) ( $shape['regular_price'] ?? '' );
		$bits[] = '' !== $price ? '£' . number_format( (float) $price, 2 ) : 'no price yet';
	}
	if ( ! empty( $shape['shipping_class']['name'] ) ) {
		$bits[] = 'postage: ' . $shape['shipping_class']['name'];
	}
	$pics = ( empty( $shape['image'] ) ? 0 : 1 ) + count( (array) ( $shape['gallery'] ?? [] ) );
	$bits[] = $pics . ' picture' . ( 1 === $pics ? '' : 's' );
	$cats = [];
	foreach ( (array) ( $shape['categories'] ?? [] ) as $c ) {
		$cats[] = ( ! empty( $c['parent_name'] ) ? $c['parent_name'] . ' › ' : '' ) . ( $c['name'] ?? '' );
	}
	if ( $cats ) {
		$bits[] = implode( ', ', $cats );
	}
	return implode( ' · ', $bits );
}

/**
 * A term on this site, found by its web address, or made (under its parent)
 * if it isn't here yet.
 *
 * @return int Term ID, or 0.
 */
function bespoke_copy_term( $taxonomy, $slug, $name, $parent = 0 ) {
	$slug = sanitize_title( (string) $slug );
	$name = sanitize_text_field( (string) $name );
	if ( '' === $slug && '' === $name ) {
		return 0;
	}
	if ( '' !== $slug ) {
		$t = get_term_by( 'slug', $slug, $taxonomy );
		if ( $t ) {
			return (int) $t->term_id;
		}
	}
	$made = wp_insert_term( '' !== $name ? $name : $slug, $taxonomy, [ 'slug' => $slug, 'parent' => (int) $parent ] );
	if ( is_wp_error( $made ) ) {
		// Already here under that name: use it.
		$existing = $made->get_error_data( 'term_exists' );
		return $existing ? (int) $existing : 0;
	}
	return (int) $made['term_id'];
}

/**
 * A picture from the other site, brought into this one's Media Library — once.
 * A second run finds the copy it made the first time.
 *
 * @param  array|null $ref      From bespoke_copy_image_ref().
 * @param  int        $post_id  The product it belongs to.
 * @param  string[]   $warnings Anything that went wrong, in words.
 * @return int Attachment ID, or 0.
 */
function bespoke_copy_picture( $ref, $post_id, &$warnings ) {
	if ( empty( $ref['url'] ) ) {
		return 0;
	}
	$url = esc_url_raw( (string) $ref['url'] );
	$had = get_posts( [
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => BESPOKE_COPY_SOURCE,   // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'  => $url,                  // phpcs:ignore WordPress.DB.SlowDBQuery
	] );
	if ( $had ) {
		return (int) $had[0];
	}
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$att = media_sideload_image( $url, $post_id, sanitize_text_field( (string) ( $ref['title'] ?? '' ) ), 'id' );
	if ( is_wp_error( $att ) ) {
		$warnings[] = 'a picture (' . basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) . ') could not be fetched: '
			. $att->get_error_message() . ' — add it on the product by hand.';
		return 0;
	}
	update_post_meta( $att, BESPOKE_COPY_SOURCE, $url );
	if ( ! empty( $ref['alt'] ) ) {
		update_post_meta( $att, '_wp_attachment_image_alt', sanitize_text_field( (string) $ref['alt'] ) );
	}
	return (int) $att;
}

/**
 * Make a product from an export entry, as a DRAFT.
 *
 * Simple and variable products only — every product this shop sells through
 * the customiser is one or the other. An option list that is a shared
 * WooCommerce attribute on staging but doesn't exist here is made as the
 * product's own instead, which needs nothing set up first; the customiser
 * reads options by their words, so it works the same.
 *
 * @param  array    $entry    From the export file.
 * @param  string[] $warnings Anything worth saying, in words.
 * @return int|WP_Error The new product's ID.
 */
function bespoke_copy_create_product( $entry, &$warnings ) {
	$shape = $entry['create'] ?? null;
	if ( ! is_array( $shape ) || ! function_exists( 'wc_get_product' ) ) {
		return new WP_Error( 'shape', 'the file does not say how to make it.' );
	}
	$type = (string) ( $shape['type'] ?? 'simple' );
	if ( ! in_array( $type, [ 'simple', 'variable' ], true ) ) {
		return new WP_Error( 'type', 'only simple and variable products can be made here, and this one is ' . $type . '.' );
	}
	// Pictures are fetched from the other site; give each product time.
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	try {
		$p = ( 'variable' === $type ) ? new WC_Product_Variable() : new WC_Product_Simple();
		$p->set_name( sanitize_text_field( (string) ( $entry['name'] ?? '' ) ) );
		$p->set_slug( sanitize_title( (string) ( $entry['slug'] ?? '' ) ) );
		$p->set_status( 'draft' );

		if ( 'simple' === $type ) {
			$p->set_regular_price( wc_format_decimal( (string) ( $shape['regular_price'] ?? '' ) ) );
			$p->set_sale_price( wc_format_decimal( (string) ( $shape['sale_price'] ?? '' ) ) );
		}
		if ( in_array( $shape['tax_status'] ?? '', [ 'taxable', 'shipping', 'none' ], true ) ) {
			$p->set_tax_status( $shape['tax_status'] );
		}
		$p->set_tax_class( sanitize_title( (string) ( $shape['tax_class'] ?? '' ) ) );
		foreach ( [ 'weight', 'length', 'width', 'height' ] as $dim ) {
			$value = wc_format_decimal( (string) ( $shape[ $dim ] ?? '' ) );
			call_user_func( [ $p, 'set_' . $dim ], $value );
		}
		$p->set_menu_order( (int) ( $shape['menu_order'] ?? 0 ) );
		if ( in_array( $shape['catalog_visibility'] ?? '', [ 'visible', 'catalog', 'search', 'hidden' ], true ) ) {
			$p->set_catalog_visibility( $shape['catalog_visibility'] );
		}

		// Postage class, by its web address. Made if it isn't here — but a
		// class made here has no prices yet, so say so.
		if ( ! empty( $shape['shipping_class']['slug'] ) ) {
			$had_class = get_term_by( 'slug', sanitize_title( $shape['shipping_class']['slug'] ), 'product_shipping_class' );
			$class_id  = bespoke_copy_term( 'product_shipping_class', $shape['shipping_class']['slug'], $shape['shipping_class']['name'] ?? '' );
			if ( $class_id ) {
				$p->set_shipping_class_id( $class_id );
				if ( ! $had_class ) {
					$warnings[] = 'the postage class "' . ( $shape['shipping_class']['name'] ?? $shape['shipping_class']['slug'] )
						. '" was not on this site, so it was made — give it its prices in WooCommerce → Settings → Shipping.';
				}
			}
		}

		// Categories (with their parents) and tags.
		$cat_ids = [];
		foreach ( (array) ( $shape['categories'] ?? [] ) as $c ) {
			$parent = ! empty( $c['parent_slug'] ) ? bespoke_copy_term( 'product_cat', $c['parent_slug'], $c['parent_name'] ?? '' ) : 0;
			$cid    = bespoke_copy_term( 'product_cat', $c['slug'] ?? '', $c['name'] ?? '', $parent );
			if ( $cid ) {
				$cat_ids[] = $cid;
			}
		}
		if ( $cat_ids ) {
			$p->set_category_ids( $cat_ids );
		}
		$tag_ids = [];
		foreach ( (array) ( $shape['tags'] ?? [] ) as $tag ) {
			$tid = bespoke_copy_term( 'product_tag', sanitize_title( (string) $tag ), (string) $tag );
			if ( $tid ) {
				$tag_ids[] = $tid;
			}
		}
		if ( $tag_ids ) {
			$p->set_tag_ids( $tag_ids );
		}

		// Options. $keymap carries each staging option list to its key here,
		// and each of its values to the value a variation is stored under.
		$attrs  = [];
		$keymap = [];
		foreach ( (array) ( $shape['attributes'] ?? [] ) as $a ) {
			$label = sanitize_text_field( (string) ( $a['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$obj    = new WC_Product_Attribute();
			$tax    = (string) ( $a['taxonomy'] ?? '' );
			$names  = array_values( array_map( 'sanitize_text_field', array_map( 'strval', (array) ( $a['options'] ?? [] ) ) ) );
			$slugs  = array_values( array_map( 'strval', (array) ( $a['slugs'] ?? [] ) ) );
			$values = [];
			if ( '' !== $tax && taxonomy_exists( $tax ) && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
				$term_ids = [];
				foreach ( $slugs as $i => $slug ) {
					$tid = bespoke_copy_term( $tax, $slug, $names[ $i ] ?? $slug );
					if ( $tid ) {
						$term_ids[] = $tid;
					}
					$values[ $slug ] = sanitize_title( $slug );
				}
				$obj->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
				$obj->set_name( $tax );
				$obj->set_options( $term_ids );
				$keymap[ (string) ( $a['key'] ?? $tax ) ] = [ 'key' => $tax, 'values' => $values ];
			} else {
				if ( '' !== $tax ) {
					$warnings[] = 'the "' . $label . '" options are a shared WooCommerce attribute that is not on this site, so they were made as this product\'s own.';
					foreach ( $slugs as $i => $slug ) {
						$values[ $slug ] = $names[ $i ] ?? $slug;
					}
				} else {
					foreach ( $names as $n ) {
						$values[ $n ] = $n;
					}
				}
				$obj->set_id( 0 );
				$obj->set_name( $label );
				$obj->set_options( $names );
				$keymap[ (string) ( $a['key'] ?? sanitize_title( $label ) ) ] = [ 'key' => sanitize_title( $label ), 'values' => $values ];
			}
			$obj->set_visible( ! empty( $a['visible'] ) );
			$obj->set_variation( ! empty( $a['variation'] ) );
			$obj->set_position( (int) ( $a['position'] ?? 0 ) );
			$attrs[] = $obj;
		}
		if ( $attrs ) {
			$p->set_attributes( $attrs );
		}

		$id = (int) $p->save();
		if ( ! $id ) {
			return new WP_Error( 'save', 'WooCommerce would not save it.' );
		}

		// Pictures, now there is a product to hang them on.
		$image   = bespoke_copy_picture( $shape['image'] ?? null, $id, $warnings );
		$gallery = [];
		foreach ( (array) ( $shape['gallery'] ?? [] ) as $g ) {
			$gid = bespoke_copy_picture( $g, $id, $warnings );
			if ( $gid ) {
				$gallery[] = $gid;
			}
		}
		if ( $image || $gallery ) {
			$p = wc_get_product( $id );
			if ( $image ) {
				$p->set_image_id( $image );
			}
			if ( $gallery ) {
				$p->set_gallery_image_ids( $gallery );
			}
			$p->save();
		}

		// Variations, each with its options, prices and postage class.
		if ( 'variable' === $type ) {
			foreach ( (array) ( $shape['variations'] ?? [] ) as $vd ) {
				$v  = new WC_Product_Variation();
				$v->set_parent_id( $id );
				$va = [];
				foreach ( (array) ( $vd['attributes'] ?? [] ) as $k => $val ) {
					$map = $keymap[ (string) $k ] ?? null;
					if ( ! $map ) {
						continue;
					}
					$val = (string) $val;
					$va[ $map['key'] ] = ( '' === $val ) ? '' : ( $map['values'][ $val ] ?? $val );
				}
				$v->set_attributes( $va );
				$v->set_regular_price( wc_format_decimal( (string) ( $vd['regular_price'] ?? '' ) ) );
				$v->set_sale_price( wc_format_decimal( (string) ( $vd['sale_price'] ?? '' ) ) );
				$v->set_status( ! empty( $vd['enabled'] ) ? 'publish' : 'private' );
				$v->set_menu_order( (int) ( $vd['menu_order'] ?? 0 ) );
				if ( ! empty( $vd['shipping_class']['slug'] ) ) {
					$v->set_shipping_class_id( bespoke_copy_term( 'product_shipping_class', $vd['shipping_class']['slug'], $vd['shipping_class']['name'] ?? '' ) );
				}
				if ( ! empty( $vd['description'] ) ) {
					$v->set_description( wp_kses_post( (string) $vd['description'] ) );
				}
				$v->save();
			}
			WC_Product_Variable::sync( $id );
		}
		return $id;
	} catch ( Exception $e ) {
		return new WP_Error( 'woocommerce', $e->getMessage() );
	}
}

/* =========================================================================
   SETTINGS
   What the customiser keeps outside the products: which artwork each product
   type uses, the saved placements, and the product types added on Product
   Setup. The artwork FILES go up with the plugin's folders on launch day;
   these say which file is which.
   ========================================================================= */

/** This site's settings, for the export file. Artwork goes by file name. */
function bespoke_copy_read_settings() {
	$assets = get_option( 'bespoke_customiser_product_assets', [] );
	$geo    = get_option( 'bespoke_customiser_product_geometry', [] );
	$types  = get_option( 'bespoke_customiser_custom_product_types', [] );
	$files  = [];
	foreach ( (array) $assets as $type => $set ) {
		foreach ( array_keys( BESPOKE_COPY_ARTWORK ) as $k ) {
			if ( is_array( $set ) && ! empty( $set[ $k . '_filename' ] ) ) {
				$files[ $type ][ $k ] = (string) $set[ $k . '_filename' ];
			}
		}
	}
	return [
		'artwork'    => $files,
		'placements' => is_array( $geo ) ? $geo : [],
		'types'      => is_array( $types ) ? $types : [],
	];
}

/** A product type's name as the admin knows it. */
function bespoke_copy_type_label( $type ) {
	$all = function_exists( 'bespoke_get_product_types' ) ? bespoke_get_product_types() : [];
	return $all[ $type ] ?? $type;
}

/** A saved placement, cleaned to numbers inside the ranges Save placement allows. */
function bespoke_copy_clean_geometry( $geo ) {
	$clean = [];
	foreach ( [ 'badgeL', 'badgeR', 'nameL', 'nameR', 'numL', 'numR', 'flag' ] as $key ) {
		if ( isset( $geo[ $key ]['x'], $geo[ $key ]['y'] ) ) {
			$clean[ $key ] = [
				'x' => max( -300, min( 1500, (float) $geo[ $key ]['x'] ) ),
				'y' => max( -300, min( 1500, (float) $geo[ $key ]['y'] ) ),
			];
		}
	}
	foreach ( [ 'badgeSize' => [ 20, 500 ], 'nameSize' => [ 8, 300 ], 'numSize' => [ 8, 220 ] ] as $key => $r ) {
		if ( isset( $geo[ $key ] ) ) {
			$clean[ $key ] = max( $r[0], min( $r[1], (int) $geo[ $key ] ) );
		}
	}
	return $clean;
}

/**
 * What the file's settings would change here. One row per product type and
 * kind, each with what it would write, and — for artwork — any file that
 * isn't on this site yet, which is never pointed at.
 *
 * @param  array $settings From the export file.
 * @return array[]
 */
function bespoke_copy_settings_plan( $settings ) {
	$rows = [];

	// Product types added on Product Setup — first, since artwork and
	// placements can belong to them.
	$here_types = get_option( 'bespoke_customiser_custom_product_types', [] );
	$here_types = is_array( $here_types ) ? $here_types : [];
	foreach ( (array) ( $settings['types'] ?? [] ) as $key => $cfg ) {
		$key = sanitize_key( $key );
		if ( ! $key || ! is_array( $cfg ) ) {
			continue;
		}
		$want = [
			'label'    => sanitize_text_field( (string) ( $cfg['label'] ?? $key ) ),
			'inherits' => sanitize_key( (string) ( $cfg['inherits'] ?? '' ) ),
		];
		$have = $here_types[ $key ] ?? null;
		if ( is_array( $have ) && bespoke_copy_same( $want, [ 'label' => (string) ( $have['label'] ?? '' ), 'inherits' => (string) ( $have['inherits'] ?? '' ) ] ) ) {
			continue;
		}
		$rows[] = [
			'key'     => 'type:' . $key,
			'label'   => 'Product type: ' . $want['label'],
			'from'    => is_array( $have ) ? 'set up differently' : 'not on this site',
			'to'      => $want['inherits'] ? 'behaves like ' . bespoke_copy_type_label( $want['inherits'] ) : 'the standard customiser',
			'value'   => $want,
			'missing' => [],
		];
	}

	// Artwork: which file is each type's background, stencil and so on.
	// Built from the file NAME, at this site's address, and only for a file
	// that is here — never left pointing at the other site.
	$here_assets = get_option( 'bespoke_customiser_product_assets', [] );
	$here_assets = is_array( $here_assets ) ? $here_assets : [];
	foreach ( (array) ( $settings['artwork'] ?? [] ) as $type => $files ) {
		$type = sanitize_key( $type );
		if ( ! $type || ! is_array( $files ) ) {
			continue;
		}
		$have    = isset( $here_assets[ $type ] ) && is_array( $here_assets[ $type ] ) ? $here_assets[ $type ] : [];
		$set     = [];
		$missing = [];
		$names   = [];
		$same    = true;
		foreach ( $files as $k => $fn ) {
			if ( ! isset( BESPOKE_COPY_ARTWORK[ $k ] ) ) {
				continue;
			}
			$fn   = sanitize_file_name( basename( (string) $fn ) );
			$path = BESPOKE_PRODUCT_ASSETS_DIR . $fn;
			if ( '' === $fn || ! file_exists( $path ) ) {
				$missing[] = '' !== $fn ? $fn : $k;
				continue;
			}
			$set[ $k . '_url' ]      = BESPOKE_PRODUCT_ASSETS_URL . $fn . '?v=' . (int) filemtime( $path );
			$set[ $k . '_filename' ] = $fn;
			$names[]                 = BESPOKE_COPY_ARTWORK[ $k ];
			if ( ( $have[ $k . '_filename' ] ?? '' ) !== $fn ) {
				$same = false;
			}
		}
		if ( $same && ! $missing ) {
			continue;
		}
		$had = [];
		foreach ( BESPOKE_COPY_ARTWORK as $k => $label ) {
			if ( ! empty( $have[ $k . '_filename' ] ) ) {
				$had[] = $label;
			}
		}
		$rows[] = [
			'key'     => 'art:' . $type,
			'label'   => 'Artwork: ' . bespoke_copy_type_label( $type ),
			'from'    => $had ? implode( ', ', $had ) : 'none',
			'to'      => $names ? implode( ', ', $names ) : 'nothing it can use yet',
			'value'   => $set,
			'missing' => $missing,
		];
	}

	// Saved placements.
	$here_geo = get_option( 'bespoke_customiser_product_geometry', [] );
	$here_geo = is_array( $here_geo ) ? $here_geo : [];
	foreach ( (array) ( $settings['placements'] ?? [] ) as $type => $geo ) {
		$type = sanitize_key( $type );
		if ( ! $type || ! is_array( $geo ) ) {
			continue;
		}
		$clean = bespoke_copy_clean_geometry( $geo );
		if ( ! $clean ) {
			continue;
		}
		$have = $here_geo[ $type ] ?? null;
		if ( is_array( $have ) && bespoke_copy_same( $clean, $have ) ) {
			continue;
		}
		$sizes = [];
		if ( isset( $clean['nameSize'] ) ) {
			$sizes[] = 'text ' . $clean['nameSize'];
		}
		if ( isset( $clean['badgeSize'] ) ) {
			$sizes[] = 'badge ' . $clean['badgeSize'];
		}
		$rows[] = [
			'key'     => 'geo:' . $type,
			'label'   => 'Placement: ' . bespoke_copy_type_label( $type ),
			'from'    => is_array( $have ) ? 'a different saved layout' : 'the built-in layout',
			'to'      => 'staging\'s saved layout' . ( $sizes ? ' (' . implode( ', ', $sizes ) . ')' : '' ),
			'value'   => $clean,
			'missing' => [],
		];
	}
	return $rows;
}

/** Which option a settings row writes. */
function bespoke_copy_setting_option( $kind ) {
	$options = [
		'type' => 'bespoke_customiser_custom_product_types',
		'art'  => 'bespoke_customiser_product_assets',
		'geo'  => 'bespoke_customiser_product_geometry',
	];
	return $options[ $kind ] ?? '';
}

/**
 * Write one settings row, and hand back what was there before so it can be
 * put back. Artwork only adds and replaces the files the row names; anything
 * else this site already has for that type is left alone.
 *
 * @return array|null
 */
function bespoke_copy_apply_setting( $row ) {
	list( $kind, $type ) = array_pad( explode( ':', (string) $row['key'], 2 ), 2, '' );
	$option = bespoke_copy_setting_option( $kind );
	if ( ! $option || '' === $type || empty( $row['value'] ) ) {
		return null;
	}
	$all = get_option( $option, [] );
	$all = is_array( $all ) ? $all : [];
	$before = array_key_exists( $type, $all ) ? $all[ $type ] : null;
	if ( 'art' === $kind ) {
		$all[ $type ] = array_merge( is_array( $before ) ? $before : [], (array) $row['value'] );
	} else {
		$all[ $type ] = $row['value'];
	}
	update_option( $option, $all );
	return [ 'option' => $option, 'type' => $type, 'before' => $before ];
}

/** Put one setting back as it was before an import. */
function bespoke_copy_undo_setting( $backup ) {
	if ( ! is_array( $backup ) || empty( $backup['option'] ) || ! isset( $backup['type'] ) ) {
		return;
	}
	if ( ! in_array( $backup['option'], [ 'bespoke_customiser_custom_product_types', 'bespoke_customiser_product_assets', 'bespoke_customiser_product_geometry' ], true ) ) {
		return;
	}
	$all = get_option( $backup['option'], [] );
	$all = is_array( $all ) ? $all : [];
	if ( null === $backup['before'] ) {
		unset( $all[ $backup['type'] ] );
	} else {
		$all[ $backup['type'] ] = $backup['before'];
	}
	update_option( $backup['option'], $all );
}

/**
 * Products worth offering: everything the customiser drives, plus anything
 * else carrying BEspoke Page content, plus every other published product so
 * a description-only copy (the club mugs) is one tick away.
 *
 * @return array[] id, name, slug, bespoke (bool)
 */
function bespoke_copy_product_list() {
	$ids = get_posts( [
		'post_type'        => 'product',
		'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
		'numberposts'      => -1,
		'orderby'          => 'title',
		'order'            => 'ASC',
		'fields'           => 'ids',
		'suppress_filters' => false,
	] );
	$list = [];
	foreach ( $ids as $id ) {
		$type    = get_post_meta( $id, '_bespoke_product_type', true );
		$eyebrow = get_post_meta( $id, '_bespoke_eyebrow', true );
		$glance  = get_post_meta( $id, '_bespoke_at_a_glance', true );
		$list[] = [
			'id'      => (int) $id,
			'name'    => get_the_title( $id ),
			'slug'    => get_post_field( 'post_name', $id ),
			'bespoke' => ( $type || $eyebrow || ( is_array( $glance ) && $glance ) ),
		];
	}
	return $list;
}

/* =========================================================================
   ADMIN PAGE
   Staging exports a file; live reads it, shows what would change, and writes
   only what's ticked.
   ========================================================================= */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=bespoke_design',
		'Copy Product Content',
		'Copy to Live',
		BESPOKE_COPY_CAP,
		'bespoke-copy',
		'bespoke_copy_render_page'
	);
} );

/** Where a read-but-not-yet-applied file waits, per user. */
function bespoke_copy_pending_key() {
	return 'bespoke_copy_pending_' . get_current_user_id();
}

/**
 * Send the ticked products to the browser as a file. Runs on admin_init,
 * before any mark-up: a download can't follow page output.
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['bespoke_copy_export'] ) ) {
		return;
	}
	if ( ! current_user_can( BESPOKE_COPY_CAP ) || ! check_admin_referer( 'bespoke_copy_export' ) ) {
		wp_die( 'You do not have permission to do that.' );
	}
	$ids = array_values( array_filter( array_map( 'intval', (array) ( $_POST['products'] ?? [] ) ) ) );
	if ( ! $ids ) {
		return; // nothing ticked — fall through and re-render the page
	}

	$products = [];
	foreach ( $ids as $id ) {
		$fields = bespoke_copy_read_product( $id );
		if ( ! $fields ) {
			continue;
		}
		$products[] = [
			'id'           => $id,
			'slug'         => get_post_field( 'post_name', $id ),
			'name'         => get_the_title( $id ),
			'fields'       => $fields,
			'design_allow' => bespoke_copy_design_refs( $id ),
			// Only ever used where the product isn't on the other site yet.
			'create'       => bespoke_copy_product_shape( $id ),
		];
	}

	$file = [
		'format'    => BESPOKE_COPY_FORMAT,
		'site'      => home_url(),
		'generated' => current_time( 'mysql' ),
		'products'  => $products,
		'settings'  => bespoke_copy_read_settings(),
	];

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bespoke-product-content-' . gmdate( 'Y-m-d-Hi' ) . '.json"' );
	echo wp_json_encode( $file, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
} );

/** The page: export, upload, review, apply, undo. */
function bespoke_copy_render_page() {
	if ( ! current_user_can( BESPOKE_COPY_CAP ) ) {
		wp_die( 'You do not have permission to access this page.' );
	}

	$notice = '';

	if ( isset( $_POST['bespoke_copy_cancel'] ) && check_admin_referer( 'bespoke_copy_apply' ) ) {
		delete_transient( bespoke_copy_pending_key() );
		$notice = '<div class="notice notice-info"><p>Left everything as it was.</p></div>';
	} elseif ( isset( $_POST['bespoke_copy_upload'] ) && check_admin_referer( 'bespoke_copy_upload' ) ) {
		$notice = bespoke_copy_handle_upload();
	} elseif ( isset( $_POST['bespoke_copy_apply'] ) && check_admin_referer( 'bespoke_copy_apply' ) ) {
		$notice = bespoke_copy_handle_apply();
	} elseif ( isset( $_POST['bespoke_copy_undo'] ) && check_admin_referer( 'bespoke_copy_undo' ) ) {
		$notice = bespoke_copy_handle_undo();
	}

	$pending = get_transient( bespoke_copy_pending_key() );
	$last    = get_option( 'bespoke_copy_last_import', [] );
	?>
	<div class="wrap">
		<h1>Copy product content</h1>
		<p style="max-width:75ch;">
			Moves product wording and customiser setup from one site to the other:
			both descriptions, everything on the BEspoke Page tab, the Rank Math
			title and description, and which designs each product shows.
			<strong>For a product already on this site, its prices, stock, options,
			variations, images and categories are never written.</strong>
		</p>
		<p style="max-width:75ch;">
			A product <strong>not</strong> on this site yet can be made here as a
			<strong>draft</strong> — nobody sees it until you publish it — with its
			prices, options, postage class, categories and pictures. The customiser's
			own settings come too: each product type's artwork, the saved placements,
			and the product types added on Product Setup.
		</p>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<?php
		if ( is_array( $pending ) ) {
			bespoke_copy_render_review( $pending );
		} else {
			bespoke_copy_render_export();
			bespoke_copy_render_upload();
		}
		?>

		<?php if ( ! empty( $last['products'] ) || ! empty( $last['created'] ) || ! empty( $last['settings'] ) ) : ?>
			<hr/>
			<h2>Last import</h2>
			<p>
				<?php echo esc_html( $last['when'] ?? '' ); ?> —
				<?php echo intval( count( (array) ( $last['products'] ?? [] ) ) ); ?> product(s) updated,
				<?php echo intval( count( (array) ( $last['created'] ?? [] ) ) ); ?> made as drafts,
				<?php echo intval( count( (array) ( $last['settings'] ?? [] ) ) ); ?> setting(s) changed<?php
				echo ! empty( $last['from'] ) ? ' from ' . esc_html( $last['from'] ) : ''; ?>.
				What was there before is still stored.
			</p>
			<form method="post" onsubmit="return confirm('Put back what was there before the last import? Products it made are moved to the bin.');">
				<?php wp_nonce_field( 'bespoke_copy_undo' ); ?>
				<button class="button" name="bespoke_copy_undo" value="1">Undo last import</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/** Step 1, on staging: tick products, download the file. */
function bespoke_copy_render_export() {
	$list = bespoke_copy_product_list();
	?>
	<h2>1. On staging — make the file</h2>
	<p style="max-width:75ch;">The customiser's settings always go in the file. Tick the products to go with them.</p>
	<form method="post">
		<?php wp_nonce_field( 'bespoke_copy_export' ); ?>
		<p>
			<button type="button" class="button" onclick="document.querySelectorAll('.bespoke-copy-pick').forEach(function(c){c.checked=true;});">Tick all</button>
			<button type="button" class="button" onclick="document.querySelectorAll('.bespoke-copy-pick').forEach(function(c){c.checked=false;});">Untick all</button>
			<button type="button" class="button" onclick="document.querySelectorAll('.bespoke-copy-pick').forEach(function(c){c.checked=(c.dataset.bespoke==='1');});">Just the customiser products</button>
		</p>
		<table class="widefat striped" style="max-width:760px;">
			<thead><tr><th style="width:28px;"></th><th>Product</th><th style="width:120px;">Customiser</th></tr></thead>
			<tbody>
			<?php foreach ( $list as $p ) : ?>
				<tr>
					<td><input type="checkbox" class="bespoke-copy-pick" name="products[]" value="<?php echo esc_attr( $p['id'] ); ?>" data-bespoke="<?php echo $p['bespoke'] ? '1' : '0'; ?>" <?php checked( $p['bespoke'] ); ?> /></td>
					<td><?php echo esc_html( $p['name'] ); ?><br/><code style="font-size:11px;color:#666;"><?php echo esc_html( $p['slug'] ); ?></code></td>
					<td><?php echo $p['bespoke'] ? 'Yes' : '&mdash;'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><button class="button button-primary" name="bespoke_copy_export" value="1">Download export file</button></p>
	</form>
	<?php
}

/** Step 2, on live: upload a file made on the other site. */
function bespoke_copy_render_upload() {
	?>
	<hr/>
	<h2>2. On live — read the file</h2>
	<p>Nothing is written at this point. You see what would change first.</p>
	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'bespoke_copy_upload' ); ?>
		<input type="file" name="bespoke_copy_file" accept="application/json,.json" required />
		<button class="button button-primary" name="bespoke_copy_upload" value="1">Read the file</button>
	</form>
	<?php
}

/** Step 3: what the file would change, product by product and setting by setting. */
function bespoke_copy_render_review( $file ) {
	$plan     = bespoke_copy_plan( $file );
	$settings = bespoke_copy_settings_plan( $file['settings'] ?? [] );
	$changing = 0;
	$new      = 0;
	foreach ( $plan as $row ) {
		if ( $row['matched'] && bespoke_copy_writable( $row['changes'] ) ) {
			$changing++;
		}
		if ( $row['creatable'] ) {
			$new++;
		}
	}
	$setting_rows = 0;
	foreach ( $settings as $s ) {
		if ( $s['value'] ) {
			$setting_rows++;
		}
	}
	$anything = $changing + $new + $setting_rows;
	?>
	<h2>What this would change</h2>
	<p>
		From <code><?php echo esc_html( $file['site'] ?? 'another site' ); ?></code>,
		exported <?php echo esc_html( $file['generated'] ?? '' ); ?>.
		<strong><?php echo intval( $changing ); ?></strong> of <?php echo intval( count( $plan ) ); ?> products would change,
		<strong><?php echo intval( $new ); ?></strong> would be made as drafts,
		and <strong><?php echo intval( $setting_rows ); ?></strong> setting(s) would change.
	</p>
	<form method="post">
		<?php wp_nonce_field( 'bespoke_copy_apply' ); ?>
		<table class="widefat striped" style="max-width:960px;">
			<thead><tr><th style="width:28px;"></th><th style="width:240px;">Product</th><th>Changes</th></tr></thead>
			<tbody>
			<?php foreach ( $plan as $i => $row ) : ?>
				<?php $writable = bespoke_copy_writable( $row['changes'] ); ?>
				<tr>
					<td>
						<?php if ( $row['matched'] && $writable ) : ?>
							<input type="checkbox" name="apply[]" value="<?php echo esc_attr( $i ); ?>" checked />
						<?php elseif ( $row['creatable'] ) : ?>
							<input type="checkbox" name="create[]" value="<?php echo esc_attr( $i ); ?>" checked />
						<?php endif; ?>
					</td>
					<td>
						<?php echo esc_html( $row['name'] ); ?><br/>
						<code style="font-size:11px;color:#666;"><?php echo esc_html( $row['slug'] ); ?></code>
					</td>
					<td>
						<?php if ( ! $row['matched'] && $row['creatable'] ) : ?>
							<strong>Not on this site yet — make it as a draft.</strong><br/>
							<span style="color:#666;"><?php echo esc_html( bespoke_copy_describe_shape( $row['entry']['create'] ) ); ?>.
							Then its wording and customiser setup, as for any other product.</span>
						<?php elseif ( ! $row['matched'] ) : ?>
							<span style="color:#b32d2e;">No product with that web address here — skipped.</span>
						<?php elseif ( ! $row['changes'] ) : ?>
							<span style="color:#666;">Already the same.</span>
						<?php else : ?>
							<ul style="margin:0;">
								<?php foreach ( $row['changes'] as $c ) : ?>
									<li<?php echo ! empty( $c['blocked'] ) ? ' style="color:#b32d2e;"' : ''; ?>>
										<strong><?php echo esc_html( $c['label'] ); ?></strong>:
										<span style="color:#666;"><?php echo esc_html( $c['from'] ); ?></span>
										&rarr; <?php echo esc_html( $c['to'] ); ?>
										<?php if ( ! empty( $c['blocked'] ) ) : ?>
											— left as it is until the design library is brought across.
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $new ) : ?>
			<p style="max-width:75ch;color:#666;">Pictures are fetched from the other site as each new product is made, which takes a little while. With a lot to make, untick some and do them in batches of about five; anything already made is found again, never made twice.</p>
		<?php endif; ?>

		<?php if ( $settings ) : ?>
			<h3 style="margin-top:22px;">The customiser's settings</h3>
			<table class="widefat striped" style="max-width:960px;">
				<thead><tr><th style="width:28px;"></th><th style="width:240px;">Setting</th><th>Change</th></tr></thead>
				<tbody>
				<?php foreach ( $settings as $s ) : ?>
					<tr>
						<td>
							<?php if ( $s['value'] ) : ?>
								<input type="checkbox" name="settings[]" value="<?php echo esc_attr( $s['key'] ); ?>" checked />
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $s['label'] ); ?></td>
						<td>
							<span style="color:#666;"><?php echo esc_html( $s['from'] ); ?></span>
							&rarr; <?php echo esc_html( $s['to'] ); ?>
							<?php if ( $s['missing'] ) : ?>
								<br/><span style="color:#b32d2e;">Not on this site yet, so not used:
								<?php echo esc_html( implode( ', ', $s['missing'] ) ); ?> — upload the product-assets folder first.</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p>
			<button class="button button-primary" name="bespoke_copy_apply" value="1" <?php disabled( 0, $anything ); ?>>Copy what's ticked</button>
			<button class="button" name="bespoke_copy_cancel" value="1" formnovalidate>Cancel</button>
		</p>
	</form>
	<?php
}

/**
 * Read an uploaded export file and hold it for review.
 *
 * @return string Admin notice mark-up.
 */
function bespoke_copy_handle_upload() {
	$file = $_FILES['bespoke_copy_file'] ?? null;
	if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
		return '<div class="notice notice-error"><p>No file arrived. Try again.</p></div>';
	}
	if ( (int) $file['size'] > 8 * MB_IN_BYTES ) {
		return '<div class="notice notice-error"><p>That file is over 8MB, which an export of this kind never is.</p></div>';
	}
	$raw  = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$data = json_decode( (string) $raw, true );
	if ( ! is_array( $data ) || empty( $data['products'] ) || ! is_array( $data['products'] ) ) {
		return '<div class="notice notice-error"><p>That does not look like an export file from this tool.</p></div>';
	}
	if ( ! in_array( (int) ( $data['format'] ?? 0 ), [ 1, BESPOKE_COPY_FORMAT ], true ) ) {
		return '<div class="notice notice-error"><p>That file was made by a different version of this tool.</p></div>';
	}
	set_transient( bespoke_copy_pending_key(), $data, HOUR_IN_SECONDS );
	return '<div class="notice notice-info"><p>File read. Nothing written yet &mdash; check the list below.</p></div>';
}

/**
 * Write what's ticked from the file waiting for review: the settings, then
 * the new products (as drafts), then the products already here.
 *
 * @return string Admin notice mark-up.
 */
function bespoke_copy_handle_apply() {
	$file = get_transient( bespoke_copy_pending_key() );
	if ( ! is_array( $file ) ) {
		return '<div class="notice notice-error"><p>That file is no longer waiting &mdash; upload it again.</p></div>';
	}
	$plan     = bespoke_copy_plan( $file );
	$ticked   = array_map( 'intval', (array) ( $_POST['apply'] ?? [] ) );
	$create   = array_map( 'intval', (array) ( $_POST['create'] ?? [] ) );
	$set_keys = array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) ( $_POST['settings'] ?? [] ) ) );
	$done     = [];
	$made     = [];
	$changed  = [];
	$fields   = 0;
	$notes    = [];

	// 1. Settings — product types first, as the plan lists them.
	foreach ( bespoke_copy_settings_plan( $file['settings'] ?? [] ) as $row ) {
		if ( ! in_array( $row['key'], $set_keys, true ) ) {
			continue;
		}
		$backup = bespoke_copy_apply_setting( $row );
		if ( $backup ) {
			$changed[] = $backup;
		}
	}

	// 2. New products, as drafts, then their wording and customiser setup.
	foreach ( $create as $i ) {
		if ( ! isset( $plan[ $i ] ) || ! $plan[ $i ]['creatable'] ) {
			continue;
		}
		$row      = $plan[ $i ];
		$warnings = [];
		$id       = bespoke_copy_create_product( $row['entry'], $warnings );
		foreach ( $warnings as $w ) {
			$notes[] = $row['name'] . ': ' . $w;
		}
		if ( is_wp_error( $id ) ) {
			$notes[] = $row['name'] . ': not made — ' . $id->get_error_message();
			continue;
		}
		$made[] = $id;
		$keys   = array_keys( bespoke_copy_fields() );
		if ( array_key_exists( 'design_allow', $row['entry'] ) && $row['entry']['design_allow'] ) {
			$dp = bespoke_copy_design_plan( $id, $row['entry']['design_allow'] );
			if ( $dp['missing'] ) {
				$notes[] = $row['name'] . ': its designs (' . implode( ', ', $dp['missing'] ) . ') are not on this site yet, so for now it offers every design of its type. Bring the design library across and run this again.';
			} else {
				$keys[] = BESPOKE_COPY_DESIGNS;
			}
		}
		bespoke_copy_apply_product( $id, $row['entry'], $keys );
	}

	// 3. Products already here.
	foreach ( $ticked as $i ) {
		if ( ! isset( $plan[ $i ] ) || ! $plan[ $i ]['matched'] ) {
			continue;
		}
		$row  = $plan[ $i ];
		$keys = bespoke_copy_writable( $row['changes'] );
		if ( ! $keys ) {
			continue;
		}
		$written = bespoke_copy_apply_product( $row['id'], $row['entry'], $keys );
		if ( $written ) {
			$done[]  = $row['id'];
			$fields += count( $written );
		}
	}

	delete_transient( bespoke_copy_pending_key() );
	if ( $done || $made || $changed ) {
		update_option( 'bespoke_copy_last_import', [
			'when'     => current_time( 'mysql' ),
			'products' => $done,
			'created'  => $made,
			'settings' => $changed,
			'from'     => $file['site'] ?? '',
		] );
	}

	$html = '<div class="notice notice-success"><p>Copied ' . intval( $fields ) . ' field(s) across '
		. count( $done ) . ' product(s), made ' . count( $made ) . ' new product(s) as drafts, and changed '
		. count( $changed ) . ' setting(s). What was there before is stored, so it can be put back.</p>';
	if ( $made ) {
		$links = [];
		foreach ( $made as $id ) {
			$links[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
		}
		$html .= '<p>New drafts, to check and publish: ' . implode( ', ', $links ) . '.</p>';
	}
	$html .= '</div>';
	if ( $notes ) {
		$html .= '<div class="notice notice-warning"><p><strong>Worth a look:</strong></p><ul style="list-style:disc;margin-left:20px;">';
		foreach ( $notes as $n ) {
			$html .= '<li>' . esc_html( $n ) . '</li>';
		}
		$html .= '</ul></div>';
	}
	return $html;
}

/**
 * Put back everything the last import did: the products' previous content,
 * the settings as they were, and the products it made into the bin.
 *
 * @return string Admin notice mark-up.
 */
function bespoke_copy_handle_undo() {
	$last   = get_option( 'bespoke_copy_last_import', [] );
	$undone = 0;
	foreach ( (array) ( $last['products'] ?? [] ) as $id ) {
		if ( bespoke_copy_undo_product( (int) $id ) ) {
			$undone++;
		}
	}
	$binned = 0;
	foreach ( (array) ( $last['created'] ?? [] ) as $id ) {
		$id = (int) $id;
		if ( 'product' === get_post_type( $id ) && wp_trash_post( $id ) ) {
			$binned++;
		}
	}
	$settings = (array) ( $last['settings'] ?? [] );
	foreach ( array_reverse( $settings ) as $backup ) {
		bespoke_copy_undo_setting( $backup );
	}
	delete_option( 'bespoke_copy_last_import' );
	return '<div class="notice notice-success"><p>Put the previous content back on ' . intval( $undone )
		. ' product' . ( 1 === $undone ? '' : 's' ) . ', moved ' . intval( $binned ) . ' new draft'
		. ( 1 === $binned ? '' : 's' ) . ' to the bin, and put ' . count( $settings ) . ' setting'
		. ( 1 === count( $settings ) ? '' : 's' ) . ' back.</p></div>';
}
