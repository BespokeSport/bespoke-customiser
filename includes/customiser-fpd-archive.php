<?php
/**
 * BEspoke Sport – Fancy Product Designer order archive
 *
 * Makes historic FPD orders readable now that the plugin itself is gone.
 *
 * ── Why this exists ───────────────────────────────────────────────────────
 * Before this plugin's customiser, personalisation was handled by Fancy
 * Product Designer. FPD was deactivated on 6 Sep 2026 once every product had
 * been moved across (see HANDOVER.md §6.7b). Deactivating a plugin does not
 * delete anything, so every past order still carries exactly what the
 * customer designed — but with FPD gone there is nothing left to draw it, so
 * the admin order screen shows the raw stored values instead:
 *
 *   _fpd_product_thumbnail   a base64 PNG — the actual picture of the design
 *   _fpd_data                JSON: the design, its elements and their colours
 *   _fpd_print_order         JSON: fonts used, custom images, print data
 *
 * The information was all there; it just arrived as a screenful of
 * unreadable text. That matters the first time a customer asks for a repeat
 * of something they ordered last season.
 *
 * This file hides those three raw rows and renders a small panel in their
 * place: the design as a picture, the fonts it used, and the artwork it
 * referenced. Nothing is written or migrated — it only reads what is already
 * on the order.
 *
 * ── When this can be deleted ──────────────────────────────────────────────
 * When no order old enough to carry FPD data is still worth reprinting.
 * There is no hurry: it does nothing at all on an order without that meta.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-fpd-archive.php
 * Included by:   bespoke-customiser.php
 */

defined( 'ABSPATH' ) || exit;

/** The three keys FPD wrote onto each order line. */
function bespoke_fpd_meta_keys() {
	return [ '_fpd_data', '_fpd_print_order', '_fpd_product_thumbnail' ];
}

/**
 * Hide the raw FPD rows from the order screen.
 *
 * They are HIDDEN, never deleted — the values stay on the order, and this
 * file's panel renders them properly below. Removing this plugin puts the
 * raw rows straight back.
 */
add_filter( 'woocommerce_hidden_order_itemmeta', 'bespoke_fpd_hide_raw_meta' );

function bespoke_fpd_hide_raw_meta( $hidden ) {
	return array_merge( (array) $hidden, bespoke_fpd_meta_keys() );
}

/**
 * Is this string a data: URI for an image we are willing to render?
 *
 * The value comes from our own database, but it is still rendered into an
 * admin page, so it is checked rather than trusted: an image MIME type we
 * recognise, followed by base64 characters only. Anything else is shown as
 * text rather than put inside an img tag.
 *
 * @param string $uri
 * @return bool
 */
function bespoke_fpd_is_safe_image_uri( $uri ) {
	return (bool) preg_match(
		'~^data:image/(png|jpeg|jpg|gif|webp);base64,[A-Za-z0-9+/]+={0,2}$~',
		trim( (string) $uri )
	);
}

/**
 * Pull the readable parts out of _fpd_data.
 *
 * The shape varies between FPD versions, so every step is defensive: a shape
 * we do not recognise returns nothing rather than a warning, and the
 * thumbnail below is shown regardless. That picture is the important part.
 *
 * @param string $json
 * @return array{title:string,elements:array<int,array{title:string,colour:string}>}
 */
function bespoke_fpd_parse_data( $json ) {
	$out = [ 'title' => '', 'elements' => [] ];

	$data = json_decode( (string) $json, true );
	if ( ! is_array( $data ) ) {
		return $out;
	}

	// FPD nests the views under 'product'; older exports are a bare list.
	$views = $data['product'] ?? $data;
	if ( ! is_array( $views ) ) {
		return $out;
	}

	foreach ( $views as $view ) {
		if ( ! is_array( $view ) ) {
			continue;
		}
		if ( $out['title'] === '' && ! empty( $view['title'] ) ) {
			$out['title'] = sanitize_text_field( (string) $view['title'] );
		}
		foreach ( (array) ( $view['elements'] ?? [] ) as $el ) {
			if ( ! is_array( $el ) || empty( $el['title'] ) ) {
				continue;
			}
			// 'fill' is the chosen colour on a recolourable element. It can
			// also be an array (a gradient) — only a plain hex is shown.
			$fill = $el['fill'] ?? ( $el['parameters']['fill'] ?? '' );
			$out['elements'][] = [
				'title'  => sanitize_text_field( (string) $el['title'] ),
				'colour' => ( is_string( $fill ) && preg_match( '/^#[0-9a-f]{3,8}$/i', $fill ) ) ? strtoupper( $fill ) : '',
			];
		}
	}

	return $out;
}

/**
 * Fonts named in _fpd_print_order, so a reprint can use the same ones.
 *
 * @param string $json
 * @return array<int,string>
 */
function bespoke_fpd_parse_fonts( $json ) {
	$data = json_decode( (string) $json, true );
	if ( ! is_array( $data ) ) {
		return [];
	}
	$names = [];
	foreach ( (array) ( $data['used_fonts'] ?? [] ) as $font ) {
		$name = is_array( $font ) ? ( $font['name'] ?? '' ) : $font;
		if ( $name !== '' ) {
			$names[] = sanitize_text_field( (string) $name );
		}
	}
	return array_values( array_unique( $names ) );
}

/**
 * Render the panel under an order line that carries FPD data.
 *
 * Fires on the admin order screen only. Silent on every line without that
 * meta, which is every order placed since the move to our own customiser.
 */
add_action( 'woocommerce_after_order_itemmeta', 'bespoke_fpd_render_archive_panel', 10, 2 );

function bespoke_fpd_render_archive_panel( $item_id, $item ) {
	if ( ! is_admin() || ! ( $item instanceof WC_Order_Item ) ) {
		return;
	}

	$thumb = (string) $item->get_meta( '_fpd_product_thumbnail', true );
	$data  = (string) $item->get_meta( '_fpd_data', true );
	$print = (string) $item->get_meta( '_fpd_print_order', true );

	if ( $thumb === '' && $data === '' && $print === '' ) {
		return; // Not an FPD order — the normal case from here on.
	}

	$parsed = bespoke_fpd_parse_data( $data );
	$fonts  = bespoke_fpd_parse_fonts( $print );

	echo '<div style="margin:10px 0 4px;padding:12px 14px;border:1px solid #c3c4c7;border-left:4px solid #d63638;background:#fff;max-width:760px;">';

	echo '<strong style="display:block;margin-bottom:8px;">Designed in Fancy Product Designer (archived)</strong>';
	echo '<p style="margin:0 0 10px;color:#666;font-size:12px;">That plugin is no longer installed, so this is a read-only record of what the customer designed. Everything below came off this order.</p>';

	if ( bespoke_fpd_is_safe_image_uri( $thumb ) ) {
		// The customer's actual design. Opening it in a new tab gives the
		// full-size image, which is what a reprint is set up from.
		echo '<a href="' . esc_attr( $thumb ) . '" target="_blank" rel="noopener" title="Open full size in a new tab">';
		echo '<img src="' . esc_attr( $thumb ) . '" alt="Customer design" style="max-width:280px;height:auto;border:1px solid #ddd;background:#fafafa;display:block;margin-bottom:10px;" />';
		echo '</a>';
	} elseif ( $thumb !== '' ) {
		echo '<p style="margin:0 0 10px;color:#b32d2e;">A preview image is stored on this order but is not in a format we will display. It is still in the database under <code>_fpd_product_thumbnail</code>.</p>';
	}

	if ( $parsed['title'] !== '' ) {
		echo '<p style="margin:0 0 6px;"><strong>View:</strong> ' . esc_html( $parsed['title'] ) . '</p>';
	}

	if ( ! empty( $parsed['elements'] ) ) {
		echo '<p style="margin:0 0 4px;"><strong>Design elements</strong></p><ul style="margin:0 0 10px 18px;">';
		foreach ( $parsed['elements'] as $el ) {
			echo '<li style="margin:2px 0;">' . esc_html( $el['title'] );
			if ( $el['colour'] !== '' ) {
				echo ' — <span style="display:inline-block;width:11px;height:11px;vertical-align:-1px;border:1px solid #999;background:' . esc_attr( $el['colour'] ) . ';"></span> ' . esc_html( $el['colour'] );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	if ( ! empty( $fonts ) ) {
		echo '<p style="margin:0;"><strong>Fonts used:</strong> ' . esc_html( implode( ', ', $fonts ) ) . '</p>';
	}

	echo '</div>';
}
