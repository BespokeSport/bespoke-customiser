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
 * This copies ONLY what the list below names: the two descriptions, the
 * BEspoke Page fields, the customiser setup and the Rank Math title and
 * description. Prices, stock, attributes, variations, images, categories and
 * anything else are never read and never written.
 *
 * How it's used on launch day:
 *   1. On STAGING: tick the products, download the file.
 *   2. On LIVE: upload the file. Nothing is written yet — you get a list of
 *      what would change, product by product, field by field.
 *   3. Tick what you want and apply. The previous values are kept, so one
 *      click puts them back.
 *
 * @package BEspoke_Customiser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BESPOKE_COPY_FORMAT  = 1;                        // export file version
const BESPOKE_COPY_BACKUP  = '_bespoke_copy_backup';   // per-product undo store
const BESPOKE_COPY_CAP     = 'manage_options';

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
 * fallback, and only when it really is a product with the same name.
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
		}
		$plan[] = [
			'entry'    => $entry,
			'id'       => $id,
			'name'     => $entry['name'] ?? ( $entry['slug'] ?? '(unnamed)' ),
			'slug'     => $entry['slug'] ?? '',
			'matched'  => (bool) $id,
			'changes'  => $changes,
		];
	}
	return $plan;
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
			'id'     => $id,
			'slug'   => get_post_field( 'post_name', $id ),
			'name'   => get_the_title( $id ),
			'fields' => $fields,
		];
	}

	$file = [
		'format'    => BESPOKE_COPY_FORMAT,
		'site'      => home_url(),
		'generated' => current_time( 'mysql' ),
		'products'  => $products,
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
		$last   = get_option( 'bespoke_copy_last_import', [] );
		$undone = 0;
		foreach ( (array) ( $last['products'] ?? [] ) as $id ) {
			if ( bespoke_copy_undo_product( (int) $id ) ) {
				$undone++;
			}
		}
		delete_option( 'bespoke_copy_last_import' );
		$notice = '<div class="notice notice-success"><p>Put the previous content back on '
			. intval( $undone ) . ' product' . ( 1 === $undone ? '' : 's' ) . '.</p></div>';
	}

	$pending = get_transient( bespoke_copy_pending_key() );
	$last    = get_option( 'bespoke_copy_last_import', [] );
	?>
	<div class="wrap">
		<h1>Copy product content</h1>
		<p style="max-width:70ch;">
			Moves product wording and customiser setup from one site to the other:
			both descriptions, everything on the BEspoke Page tab, and the Rank Math
			title and description. <strong>Prices, stock, options, variations, images
			and categories are never read and never written.</strong>
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

		<?php if ( ! empty( $last['products'] ) ) : ?>
			<hr/>
			<h2>Last import</h2>
			<p>
				<?php echo esc_html( $last['when'] ?? '' ); ?> —
				<?php echo intval( count( (array) $last['products'] ) ); ?> product(s) updated<?php
				echo $last['from'] ? ' from ' . esc_html( $last['from'] ) : ''; ?>.
				What they held before is still stored.
			</p>
			<form method="post" onsubmit="return confirm('Put back the content these products had before the last import?');">
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

/** Step 3: what the file would change, product by product. */
function bespoke_copy_render_review( $file ) {
	$plan     = bespoke_copy_plan( $file );
	$changing = 0;
	foreach ( $plan as $row ) {
		if ( $row['matched'] && $row['changes'] ) {
			$changing++;
		}
	}
	?>
	<h2>What this would change</h2>
	<p>
		From <code><?php echo esc_html( $file['site'] ?? 'another site' ); ?></code>,
		exported <?php echo esc_html( $file['generated'] ?? '' ); ?>.
		<strong><?php echo intval( $changing ); ?></strong> of
		<?php echo intval( count( $plan ) ); ?> products would change.
	</p>
	<form method="post">
		<?php wp_nonce_field( 'bespoke_copy_apply' ); ?>
		<table class="widefat striped" style="max-width:900px;">
			<thead><tr><th style="width:28px;"></th><th style="width:240px;">Product</th><th>Changes</th></tr></thead>
			<tbody>
			<?php foreach ( $plan as $i => $row ) : ?>
				<tr>
					<td>
						<?php if ( $row['matched'] && $row['changes'] ) : ?>
							<input type="checkbox" name="apply[]" value="<?php echo esc_attr( $i ); ?>" checked />
						<?php endif; ?>
					</td>
					<td>
						<?php echo esc_html( $row['name'] ); ?><br/>
						<code style="font-size:11px;color:#666;"><?php echo esc_html( $row['slug'] ); ?></code>
					</td>
					<td>
						<?php if ( ! $row['matched'] ) : ?>
							<span style="color:#b32d2e;">No product with that web address here — skipped.</span>
						<?php elseif ( ! $row['changes'] ) : ?>
							<span style="color:#666;">Already the same.</span>
						<?php else : ?>
							<ul style="margin:0;">
								<?php foreach ( $row['changes'] as $c ) : ?>
									<li>
										<strong><?php echo esc_html( $c['label'] ); ?></strong>:
										<span style="color:#666;"><?php echo esc_html( $c['from'] ); ?></span>
										&rarr; <?php echo esc_html( $c['to'] ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button class="button button-primary" name="bespoke_copy_apply" value="1" <?php disabled( 0, $changing ); ?>>Copy the ticked products</button>
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
	if ( (int) ( $data['format'] ?? 0 ) !== BESPOKE_COPY_FORMAT ) {
		return '<div class="notice notice-error"><p>That file was made by a different version of this tool.</p></div>';
	}
	set_transient( bespoke_copy_pending_key(), $data, HOUR_IN_SECONDS );
	return '<div class="notice notice-info"><p>File read. Nothing written yet &mdash; check the list below.</p></div>';
}

/**
 * Write the ticked products from the file waiting for review.
 *
 * @return string Admin notice mark-up.
 */
function bespoke_copy_handle_apply() {
	$file = get_transient( bespoke_copy_pending_key() );
	if ( ! is_array( $file ) ) {
		return '<div class="notice notice-error"><p>That file is no longer waiting &mdash; upload it again.</p></div>';
	}
	$plan   = bespoke_copy_plan( $file );
	$ticked = array_map( 'intval', (array) ( $_POST['apply'] ?? [] ) );
	$done   = [];
	$fields = 0;

	foreach ( $ticked as $i ) {
		if ( ! isset( $plan[ $i ] ) || ! $plan[ $i ]['matched'] || ! $plan[ $i ]['changes'] ) {
			continue;
		}
		$row     = $plan[ $i ];
		$written = bespoke_copy_apply_product( $row['id'], $row['entry'], array_keys( $row['changes'] ) );
		if ( $written ) {
			$done[]  = $row['id'];
			$fields += count( $written );
		}
	}

	delete_transient( bespoke_copy_pending_key() );
	if ( $done ) {
		update_option( 'bespoke_copy_last_import', [
			'when'     => current_time( 'mysql' ),
			'products' => $done,
			'from'     => $file['site'] ?? '',
		] );
	}
	return '<div class="notice notice-success"><p>Copied ' . intval( $fields )
		. ' field(s) across ' . count( $done ) . ' product(s). What they held before is stored, so it can be put back.</p></div>';
}
