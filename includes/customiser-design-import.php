<?php
/**
 * BEspoke Customiser — Bulk design import
 *
 * Twenty themed designs added one at a time is an hour of the same six clicks.
 * This takes a folder of PNGs and makes a design out of each.
 *
 * The filename is the instruction:
 *
 *   Autism.png                     → a design called "Autism", one layer
 *   Autism--Background.png         → "Autism", layer "Background"
 *   Autism--Pattern.png            → …and its second layer, in that order
 *   Autism--thumb.png              → the picker thumbnail for "Autism"
 *
 * Everything before the double dash names the design, so the parts of one
 * design arrive together whatever order they are picked in. Underscores and
 * hyphens in the name become spaces, so `baby-loss.png` reads "Baby Loss".
 *
 * Nothing is guessed about colour: a layer's default is black unless the
 * filename ends in a hex code, as in `Autism--Pattern-#1F6FEB.png`. Designs
 * whose colours are locked — the awareness ones — need no colours at all,
 * since the product that shows them has no colour step.
 *
 * An import records what it created, so one button puts it back.
 */

defined( 'ABSPATH' ) || exit;

const BESPOKE_IMPORT_CAP      = 'manage_options';
const BESPOKE_IMPORT_LAST     = 'bespoke_design_import_last';
const BESPOKE_IMPORT_MAX_FILE = 8; // MB

/* =========================================================================
   FILENAME → MEANING
   ========================================================================= */

/**
 * Read one filename.
 *
 * @param  string $filename e.g. "baby-loss--Pattern-#1F6FEB.png"
 * @return array{design:string,part:string,label:string,colour:string}
 */
function bespoke_import_read_name( $filename ) {
	$stem = pathinfo( $filename, PATHINFO_FILENAME );
	$bits = explode( '--', $stem, 2 );

	$design = trim( str_replace( [ '-', '_' ], ' ', $bits[0] ) );
	// Title case, except for words already written in capitals. MS, LGBT+ and
	// IWD are not spelling mistakes, and lower-casing them first turns MS into
	// Ms, which is somebody's title rather than multiple sclerosis.
	$words  = preg_split( '/\s+/', $design );
	$design = implode( ' ', array_map( function ( $word ) {
		if ( $word === '' ) {
			return $word;
		}
		$letters = preg_replace( '/[^A-Za-z]/', '', $word );
		if ( $letters !== '' && $letters === strtoupper( $letters ) ) {
			return $word;
		}
		return ucfirst( strtolower( $word ) );
	}, $words ) );

	$part   = isset( $bits[1] ) ? trim( $bits[1] ) : '';
	$colour = '';

	// A trailing hex code sets that layer's default colour.
	if ( $part && preg_match( '/^(.*?)[-_]?(#[0-9A-Fa-f]{6})$/', $part, $m ) ) {
		$part   = trim( $m[1] );
		$colour = strtoupper( $m[2] );
	}

	return [
		'design' => $design ?: 'Untitled',
		'part'   => $part,
		'label'  => $part !== '' ? ucwords( str_replace( [ '-', '_' ], ' ', $part ) ) : 'Design',
		'colour' => $colour,
	];
}

/** Is this the thumbnail rather than a layer? */
function bespoke_import_is_thumb( $part ) {
	return in_array( strtolower( $part ), [ 'thumb', 'thumbnail', 'preview' ], true );
}

/* =========================================================================
   THE IMPORT
   ========================================================================= */

/**
 * The one step that cannot run outside a real request: taking the browser's
 * temporary file and putting it where it belongs. Wrapped, and declared only if
 * nothing has declared it already, so the rest of this file can be tested
 * without a web server in front of it.
 */
if ( ! function_exists( 'bespoke_import_move' ) ) {
	function bespoke_import_move( $from, $to ) {
		return is_uploaded_file( $from ) && @move_uploaded_file( $from, $to ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}

/**
 * Save one uploaded file into the designs folder.
 *
 * @return string|WP_Error The URL it can be read from.
 */
function bespoke_import_store( $tmp_name, $original ) {
	$ext = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, [ 'png', 'jpg', 'jpeg', 'svg' ], true ) ) {
		return new WP_Error( 'type', 'Only .png, .jpg and .svg files are accepted' );
	}
	if ( ! defined( 'BESPOKE_DESIGNS_DIR' ) || ! defined( 'BESPOKE_DESIGNS_URL' ) ) {
		return new WP_Error( 'config', 'The designs folder is not configured' );
	}
	if ( ! file_exists( BESPOKE_DESIGNS_DIR ) ) {
		wp_mkdir_p( BESPOKE_DESIGNS_DIR );
	}

	$safe = sanitize_file_name( $original );
	$base = pathinfo( $safe, PATHINFO_FILENAME );
	$try  = $base . '.' . $ext;
	$n    = 2;
	// Never overwrite: a second import of the same name is a new file, so an
	// earlier design keeps the artwork it was built with.
	while ( file_exists( trailingslashit( BESPOKE_DESIGNS_DIR ) . $try ) ) {
		$try = $base . '-' . $n . '.' . $ext;
		$n++;
	}

	$dest = trailingslashit( BESPOKE_DESIGNS_DIR ) . $try;
	if ( ! bespoke_import_move( $tmp_name, $dest ) ) {
		return new WP_Error( 'move', 'Could not save ' . esc_html( $original ) );
	}
	if ( $ext === 'svg' && function_exists( 'bespoke_sanitise_svg' ) ) {
		bespoke_sanitise_svg( $dest );
	}
	return trailingslashit( BESPOKE_DESIGNS_URL ) . rawurlencode( $try );
}

/**
 * Group the uploaded files into designs, create one post each, and say what
 * happened.
 *
 * @return array{made:array,skipped:array,errors:array}
 */
function bespoke_import_run( $files, $products, $active, $start_order ) {
	$made = [];
	$skipped = [];
	$errors = [];

	// Gather by design name first, so the layers of one design stay together
	// however the browser ordered them.
	$grouped = [];
	$count = isset( $files['name'] ) ? count( (array) $files['name'] ) : 0;
	for ( $i = 0; $i < $count; $i++ ) {
		if ( (int) $files['error'][ $i ] !== UPLOAD_ERR_OK ) {
			continue;
		}
		if ( (int) $files['size'][ $i ] > BESPOKE_IMPORT_MAX_FILE * MB_IN_BYTES ) {
			$errors[] = $files['name'][ $i ] . ' is over ' . BESPOKE_IMPORT_MAX_FILE . 'MB';
			continue;
		}
		$read = bespoke_import_read_name( $files['name'][ $i ] );
		$url  = bespoke_import_store( $files['tmp_name'][ $i ], $files['name'][ $i ] );
		if ( is_wp_error( $url ) ) {
			$errors[] = $files['name'][ $i ] . ': ' . $url->get_error_message();
			continue;
		}
		$grouped[ $read['design'] ][] = [
			'read'     => $read,
			'url'      => $url,
			'filename' => sanitize_file_name( $files['name'][ $i ] ),
		];
	}

	$order = max( 0, (int) $start_order );
	foreach ( $grouped as $name => $parts ) {
		// An existing design of the same name is left alone rather than
		// quietly rewritten — a re-run should not undo hand edits.
		$existing = get_posts( [
			'post_type'   => 'bespoke_design',
			'post_status' => [ 'publish', 'draft' ],
			'title'       => $name,
			'numberposts' => 1,
			'fields'      => 'ids',
		] );
		if ( $existing ) {
			$skipped[] = $name;
			continue;
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'bespoke_design',
			'post_status' => 'publish',
			'post_title'  => $name,
		], true );
		if ( is_wp_error( $post_id ) ) {
			$errors[] = $name . ': ' . $post_id->get_error_message();
			continue;
		}

		$layers = [];
		foreach ( $parts as $p ) {
			if ( bespoke_import_is_thumb( $p['read']['part'] ) ) {
				update_post_meta( $post_id, '_bespoke_thumb_url', $p['url'] );
				continue;
			}
			$layers[] = [
				'label'         => $p['read']['label'],
				'default'       => $p['read']['colour'] ?: '#000000',
				'file_url'      => $p['url'],
				'file_filename' => $p['filename'],
			];
		}

		update_post_meta( $post_id, '_bespoke_layers', $layers );
		update_post_meta( $post_id, '_bespoke_products', array_values( (array) $products ) );
		update_post_meta( $post_id, '_bespoke_active', $active ? '1' : '0' );
		$order += 10;
		update_post_meta( $post_id, '_bespoke_order', $order );

		$made[] = [ 'id' => $post_id, 'name' => $name, 'layers' => count( $layers ) ];
	}

	if ( $made ) {
		update_option( BESPOKE_IMPORT_LAST, [
			'when' => current_time( 'mysql' ),
			'ids'  => wp_list_pluck( $made, 'id' ),
		], false );
	}

	return [ 'made' => $made, 'skipped' => $skipped, 'errors' => $errors ];
}

/** Put the last import back. */
function bespoke_import_undo() {
	$last = get_option( BESPOKE_IMPORT_LAST, [] );
	$ids  = isset( $last['ids'] ) ? (array) $last['ids'] : [];
	$gone = 0;
	foreach ( $ids as $id ) {
		if ( get_post_type( $id ) === 'bespoke_design' && wp_trash_post( $id ) ) {
			$gone++;
		}
	}
	delete_option( BESPOKE_IMPORT_LAST );
	return $gone;
}

/* =========================================================================
   THE PAGE
   ========================================================================= */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=bespoke_design',
		'Bulk Import Designs',
		'Bulk Import',
		BESPOKE_IMPORT_CAP,
		'bespoke-design-import',
		'bespoke_import_render_page'
	);
} );

function bespoke_import_render_page() {
	if ( ! current_user_can( BESPOKE_IMPORT_CAP ) ) {
		wp_die( 'You do not have permission to access this page.' );
	}

	$result = null;
	$undone = null;

	if ( isset( $_POST['bespoke_import_undo'] ) && check_admin_referer( 'bespoke_import' ) ) {
		$undone = bespoke_import_undo();
	} elseif ( isset( $_POST['bespoke_import_go'] ) && check_admin_referer( 'bespoke_import' ) ) {
		$products = isset( $_POST['products'] ) ? array_map( 'sanitize_key', (array) $_POST['products'] ) : [];
		if ( ! $products ) {
			$result = [ 'made' => [], 'skipped' => [], 'errors' => [ 'Pick at least one product first.' ] ];
		} elseif ( empty( $_FILES['designs'] ) ) {
			$result = [ 'made' => [], 'skipped' => [], 'errors' => [ 'No files arrived.' ] ];
		} else {
			$result = bespoke_import_run(
				$_FILES['designs'],
				$products,
				! empty( $_POST['active'] ),
				isset( $_POST['start_order'] ) ? (int) $_POST['start_order'] : 0
			);
		}
	}

	$types = function_exists( 'bespoke_get_product_types' ) ? bespoke_get_product_types() : [];
	$last  = get_option( BESPOKE_IMPORT_LAST, [] );
	?>
	<div class="wrap">
		<h1>Bulk import designs</h1>

		<?php if ( $undone !== null ) : ?>
			<div class="notice notice-success"><p><?php echo intval( $undone ); ?> design(s) moved to the bin.</p></div>
		<?php endif; ?>

		<?php if ( $result ) : ?>
			<?php if ( $result['errors'] ) : ?>
				<div class="notice notice-error"><p><strong>Problems:</strong></p><ul style="margin-left:18px;list-style:disc;">
					<?php foreach ( $result['errors'] as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?>
				</ul></div>
			<?php endif; ?>
			<?php if ( $result['skipped'] ) : ?>
				<div class="notice notice-warning"><p>
					Already existed, so left alone: <?php echo esc_html( implode( ', ', $result['skipped'] ) ); ?>
				</p></div>
			<?php endif; ?>
			<?php if ( $result['made'] ) : ?>
				<div class="notice notice-success"><p><strong><?php echo count( $result['made'] ); ?> design(s) created.</strong></p></div>
				<table class="widefat striped" style="max-width:620px;margin-bottom:16px;">
					<thead><tr><th>Design</th><th style="width:90px;">Layers</th><th style="width:90px;"></th></tr></thead>
					<tbody>
					<?php foreach ( $result['made'] as $m ) : ?>
						<tr>
							<td><?php echo esc_html( $m['name'] ); ?></td>
							<td><?php echo intval( $m['layers'] ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $m['id'] ) ); ?>">Edit</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<p>Pick the files for every design at once. The filename says what each one is:</p>
		<table class="widefat striped" style="max-width:620px;margin-bottom:18px;">
			<thead><tr><th style="width:290px;">File</th><th>What it makes</th></tr></thead>
			<tbody>
				<tr><td><code>Autism.png</code></td><td>A design called “Autism”, one layer</td></tr>
				<tr><td><code>baby-loss.png</code></td><td>“Baby Loss” — dashes and underscores become spaces</td></tr>
				<tr><td><code>Autism--Background.png</code></td><td>“Autism”, layer “Background”</td></tr>
				<tr><td><code>Autism--Pattern.png</code></td><td>…and its next layer, in the order listed here</td></tr>
				<tr><td><code>Autism--Pattern-#1F6FEB.png</code></td><td>Same, with that as the layer’s starting colour</td></tr>
				<tr><td><code>Autism--thumb.png</code></td><td>The picker thumbnail for “Autism”</td></tr>
			</tbody>
		</table>
		<p class="description" style="max-width:620px;">
			A design whose name already exists is left alone, so running this twice is safe.
			Layer colours default to black; designs on a product with no colour step — the themed
			tags and the pre-designed armbands — never show them anyway.
		</p>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'bespoke_import' ); ?>
			<h2>1. Which products are these for?</h2>
			<div style="column-width:220px;max-width:700px;margin-bottom:18px;">
				<?php foreach ( $types as $key => $label ) : ?>
					<label style="display:block;padding:3px 0;">
						<input type="checkbox" name="products[]" value="<?php echo esc_attr( $key ); ?>" />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<h2>2. The files</h2>
			<p><input type="file" name="designs[]" multiple accept=".png,.jpg,.jpeg,.svg" required /></p>

			<h2>3. Then</h2>
			<p>
				<label><input type="checkbox" name="active" value="1" checked /> Show these in the customiser straight away</label><br/>
				<label>Start the display order at
					<input type="number" name="start_order" value="0" style="width:80px;" />
					<span class="description">— each design gets the next ten. Leave at 0 unless you want them after existing ones.</span>
				</label>
			</p>
			<p><button class="button button-primary" name="bespoke_import_go" value="1">Create the designs</button></p>
		</form>

		<?php if ( ! empty( $last['ids'] ) ) : ?>
			<hr/>
			<h2>Undo</h2>
			<p>
				The last import created <?php echo count( (array) $last['ids'] ); ?> design(s)
				on <?php echo esc_html( $last['when'] ); ?>.
			</p>
			<form method="post" onsubmit="return confirm('Move those designs to the bin?');">
				<?php wp_nonce_field( 'bespoke_import' ); ?>
				<button class="button" name="bespoke_import_undo" value="1">Undo that import</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
