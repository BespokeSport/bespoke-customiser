<?php
/**
 * Customiser wording that JavaScript writes as the customer works.
 *
 * TranslatePress can only translate text that arrives in the page's HTML. The
 * customiser builds much of its interface while the customer clicks — the
 * font dropdown, the badge cards, the extra text boxes, the message when an
 * upload fails — and none of that wording reaches TranslatePress on its own.
 *
 * Every string here goes through __(), which TranslatePress does pick up
 * (Translate Site → String Translation, or automatically). The result is
 * handed to the page as window.BESPOKE_I18N, and bespokeT() in
 * customiser.html looks each string up by its English text, falling back to
 * the English already in the script when there's no translation.
 *
 * WHAT DELIBERATELY ISN'T HERE: anything the ORDER depends on. Sizes, band
 * thickness labels, print options and font names must reach WooCommerce in
 * English, or the variation matcher stops finding the right price and
 * production gets a spec it can't read. Only wording the customer reads on
 * screen belongs in this file.
 *
 * @package BEspoke_Customiser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * English wording => its translation for the current language.
 *
 * Strings a translation hasn't changed are dropped, so the page only carries
 * what it actually needs and the script's own English stands in for the rest.
 *
 * @return array<string,string>
 */
function bespoke_customiser_ui_strings() {
	$strings = [

		// ── Badge step: the Left / Right badge cards ──────────────────────
		'Left'                              => __( 'Left', 'bespoke-customiser' ),
		'Right'                             => __( 'Right', 'bespoke-customiser' ),
		'Empty'                             => __( 'Empty', 'bespoke-customiser' ),
		'Your club badge'                   => __( 'Your club badge', 'bespoke-customiser' ),
		'Your second badge'                 => __( 'Your second badge', 'bespoke-customiser' ),
		'Leave the left side empty'         => __( 'Leave the left side empty', 'bespoke-customiser' ),
		'Leave the right side empty'        => __( 'Leave the right side empty', 'bespoke-customiser' ),

		// ── Text step: the font dropdown ──────────────────────────────────
		'Choose a font'                     => __( 'Choose a font', 'bespoke-customiser' ),
		'Font style'                        => __( 'Font style', 'bespoke-customiser' ),

		// ── Text step: extra text boxes ───────────────────────────────────
		'Text box %d'                       => __( 'Text box %d', 'bespoke-customiser' ),
		'Text box %d text'                  => __( 'Text box %d text', 'bespoke-customiser' ),
		'Text box %d size'                  => __( 'Text box %d size', 'bespoke-customiser' ),
		'Remove text box %d'                => __( 'Remove text box %d', 'bespoke-customiser' ),
		'e.g. VICE CAPTAIN or initials'     => __( 'e.g. VICE CAPTAIN or initials', 'bespoke-customiser' ),
		'Colour'                            => __( 'Colour', 'bespoke-customiser' ),
		'Small'                             => __( 'Small', 'bespoke-customiser' ),
		'Large'                             => __( 'Large', 'bespoke-customiser' ),

		// ── Put a deleted badge, name or number back ──────────────────────
		'+ Add left badge'                  => __( '+ Add left badge', 'bespoke-customiser' ),
		'+ Add right badge'                 => __( '+ Add right badge', 'bespoke-customiser' ),
		'+ Add left name'                   => __( '+ Add left name', 'bespoke-customiser' ),
		'+ Add right name'                  => __( '+ Add right name', 'bespoke-customiser' ),
		'+ Add left number'                 => __( '+ Add left number', 'bespoke-customiser' ),
		'+ Add right number'                => __( '+ Add right number', 'bespoke-customiser' ),

		// ── Double-sided armbands: the face switcher ──────────────────────
		'Which side are you designing?'     => __( 'Which side are you designing?', 'bespoke-customiser' ),
		'Outside'                           => __( 'Outside', 'bespoke-customiser' ),
		'Inside'                            => __( 'Inside', 'bespoke-customiser' ),
		'Faces out'                         => __( 'Faces out', 'bespoke-customiser' ),
		'Against the arm'                   => __( 'Against the arm', 'bespoke-customiser' ),
		'Outside face'                      => __( 'Outside face', 'bespoke-customiser' ),
		'Inside face'                       => __( 'Inside face', 'bespoke-customiser' ),

		// ── Review step ───────────────────────────────────────────────────
		'Not added'                         => __( 'Not added', 'bespoke-customiser' ),
		'%s (both sides)'                   => __( '%s (both sides)', 'bespoke-customiser' ),
		'Left: %s · Right: %s'              => __( 'Left: %s · Right: %s', 'bespoke-customiser' ),
		'empty'                             => __( 'empty', 'bespoke-customiser' ),
		'second badge'                      => __( 'second badge', 'bespoke-customiser' ),

		// ── The steps themselves ──────────────────────────────────────────
		'Badge'                             => __( 'Badge', 'bespoke-customiser' ),
		'Name'                              => __( 'Name', 'bespoke-customiser' ),
		'Number'                            => __( 'Number', 'bespoke-customiser' ),
		'Design'                            => __( 'Design', 'bespoke-customiser' ),
		'Colours'                           => __( 'Colours', 'bespoke-customiser' ),
		'Review'                            => __( 'Review', 'bespoke-customiser' ),
		'Done'                              => __( 'Done', 'bespoke-customiser' ),
		'Drag badge · Slider to resize'     => __( 'Drag badge · Slider to resize', 'bespoke-customiser' ),
		'Drag name · Slider to resize'      => __( 'Drag name · Slider to resize', 'bespoke-customiser' ),
		'Drag number · Slider to resize'    => __( 'Drag number · Slider to resize', 'bespoke-customiser' ),
		'Click a design to select'          => __( 'Click a design to select', 'bespoke-customiser' ),
		'Use colour pickers to customise'   => __( 'Use colour pickers to customise', 'bespoke-customiser' ),
		'Review your order'                 => __( 'Review your order', 'bespoke-customiser' ),

		// ── The button that moves to the next step ────────────────────────
		'Review my design →'                => __( 'Review my design →', 'bespoke-customiser' ),
		'Next: add %s →'                    => __( 'Next: add %s →', 'bespoke-customiser' ),
		'Next: choose design →'             => __( 'Next: choose design →', 'bespoke-customiser' ),
		'Next: choose colours →'            => __( 'Next: choose colours →', 'bespoke-customiser' ),
		'Next →'                            => __( 'Next →', 'bespoke-customiser' ),

		// ── The heading above the band thickness / print / frill buttons ──
		// The buttons' own labels are NOT here: WooCommerce matches them.
		'Style'                             => __( 'Style', 'bespoke-customiser' ),
		'Band thickness'                    => __( 'Band thickness', 'bespoke-customiser' ),
		'Print'                             => __( 'Print', 'bespoke-customiser' ),
		'Pennant style'                     => __( 'Pennant style', 'bespoke-customiser' ),

		// ── Colours step: a design's layers, when it doesn't name them ────
		'Layer %d'                          => __( 'Layer %d', 'bespoke-customiser' ),

		// ── Team mug ──────────────────────────────────────────────────────
		'Squad'                             => __( 'Squad', 'bespoke-customiser' ),
		'Keeper'                            => __( 'Keeper', 'bespoke-customiser' ),
		'Outfield'                          => __( 'Outfield', 'bespoke-customiser' ),
		'%d players'                        => __( '%d players', 'bespoke-customiser' ),
		'No players listed'                 => __( 'No players listed', 'bespoke-customiser' ),
		'No players listed yet.'            => __( 'No players listed yet.', 'bespoke-customiser' ),
		'%d player listed.'                 => __( '%d player listed.', 'bespoke-customiser' ),
		'%d players listed.'                => __( '%d players listed.', 'bespoke-customiser' ),
		'Choose a style above and its colours will appear here.'
			=> __( 'Choose a style above and its colours will appear here.', 'bespoke-customiser' ),
		"Open this tab to set the keeper shirt's colours."
			=> __( "Open this tab to set the keeper shirt's colours.", 'bespoke-customiser' ),
		"Open this tab to set the outfield shirt's colours."
			=> __( "Open this tab to set the outfield shirt's colours.", 'bespoke-customiser' ),

		// ── Player card stats ─────────────────────────────────────────────
		// These are PRINTED on the card, so they follow the language the
		// customer orders in (French cards read VIT / TIR / …). The order
		// carries the English stat beside each one, so production can always
		// tell which number is which. Outfield first, then goalkeeper.
		'PAC'                               => __( 'PAC', 'bespoke-customiser' ),
		'DRI'                               => __( 'DRI', 'bespoke-customiser' ),
		'SHO'                               => __( 'SHO', 'bespoke-customiser' ),
		'DEF'                               => __( 'DEF', 'bespoke-customiser' ),
		'PAS'                               => __( 'PAS', 'bespoke-customiser' ),
		'PHY'                               => __( 'PHY', 'bespoke-customiser' ),
		'DIV'                               => __( 'DIV', 'bespoke-customiser' ),
		'REF'                               => __( 'REF', 'bespoke-customiser' ),
		'HAN'                               => __( 'HAN', 'bespoke-customiser' ),
		'SPE'                               => __( 'SPE', 'bespoke-customiser' ),
		'KIC'                               => __( 'KIC', 'bespoke-customiser' ),
		'POS'                               => __( 'POS', 'bespoke-customiser' ),

		// ── Adding to the basket, and when it goes wrong ──────────────────
		'Adding...'                         => __( 'Adding...', 'bespoke-customiser' ),
		'Add to cart'                       => __( 'Add to cart', 'bespoke-customiser' ),
		'club badge'                        => __( 'club badge', 'bespoke-customiser' ),
		'preview image'                     => __( 'preview image', 'bespoke-customiser' ),
		'Your %s could not be uploaded, so we\'ve not added this to your basket. Please check your connection and try again — your design is still here.'
			=> __( 'Your %s could not be uploaded, so we\'ve not added this to your basket. Please check your connection and try again — your design is still here.', 'bespoke-customiser' ),
		'Sorry, there was a problem adding to cart: %s'
			=> __( 'Sorry, there was a problem adding to cart: %s', 'bespoke-customiser' ),
		'Please try again.'                 => __( 'Please try again.', 'bespoke-customiser' ),
		'Sorry, there was a connection problem. Please try again.'
			=> __( 'Sorry, there was a connection problem. Please try again.', 'bespoke-customiser' ),

		// ── Running tags: the pair, and what they are for ─────────────────
		'Same badges on both tags'
			=> __( 'Same badges on both tags', 'bespoke-customiser' ),
		'Turn off to give each tag its own badges'
			=> __( 'Turn off to give each tag its own badges', 'bespoke-customiser' ),
		'Right tag'
			=> __( 'Right tag', 'bespoke-customiser' ),
		'Left tag'
			=> __( 'Left tag', 'bespoke-customiser' ),
		'Design'
			=> __( 'Design', 'bespoke-customiser' ),
		'Made for running, walking and everyday trainers. Not for football, rugby or other team sports — match rules don\'t allow metal on footwear.'
			=> __( 'Made for running, walking and everyday trainers. Not for football, rugby or other team sports — match rules don\'t allow metal on footwear.', 'bespoke-customiser' ),

		// ── Reopened from the basket (Amend / Duplicate) ──────────────────
		'You\'re amending a design in your basket. Adding it replaces the one there.'
			=> __( 'You\'re amending a design in your basket. Adding it replaces the one there.', 'bespoke-customiser' ),
		'This is a copy of a design in your basket. Change what you like, then add it as another.'
			=> __( 'This is a copy of a design in your basket. Change what you like, then add it as another.', 'bespoke-customiser' ),

		// ── Boot stickers: before anything goes in the basket ─────────────
		'Choose at least one sticker.'
			=> __( 'Choose at least one sticker.', 'bespoke-customiser' ),
		'Upload your club badge, or untick the badge sticker.'
			=> __( 'Upload your club badge, or untick the badge sticker.', 'bespoke-customiser' ),
		'Add the text for your name sticker, or untick it.'
			=> __( 'Add the text for your name sticker, or untick it.', 'bespoke-customiser' ),
		'Choose a flag, or tell us the one you need.'
			=> __( 'Choose a flag, or tell us the one you need.', 'bespoke-customiser' ),

		// ── Boot stickers: under the text box ────────────────────────────
		'Your sticker will be about %dmm long and %dmm tall.'
			=> __( 'Your sticker will be about %dmm long and %dmm tall.', 'bespoke-customiser' ),
		'That may be too long for a smaller boot, so measure yours, or try initials.'
			=> __( 'That may be too long for a smaller boot, so measure yours, or try initials.', 'bespoke-customiser' ),
		'That would take it past %dmm, the longest a sticker can be.'
			=> __( 'That would take it past %dmm, the longest a sticker can be.', 'bespoke-customiser' ),
		'Your sticker would be about %dmm long. The longest we make is %dmm, so try fewer letters or a narrower font.'
			=> __( 'Your sticker would be about %dmm long. The longest we make is %dmm, so try fewer letters or a narrower font.', 'bespoke-customiser' ),
		'Your text sticker would be longer than %dmm. Try fewer letters or a narrower font.'
			=> __( 'Your text sticker would be longer than %dmm. Try fewer letters or a narrower font.', 'bespoke-customiser' ),
		'e.g. SMITH'
			=> __( 'e.g. SMITH', 'bespoke-customiser' ),

		// ── Every badge upload: the copyright notice ─────────────────────
		'Before you upload your badge'
			=> __( 'Before you upload your badge', 'bespoke-customiser' ),
		'Badges protected by copyright, such as Premier League club badges (Liverpool, Man Utd, Chelsea and others), cannot be included. They can only be ordered from an official club email address.'
			=> __( 'Badges protected by copyright, such as Premier League club badges (Liverpool, Man Utd, Chelsea and others), cannot be included. They can only be ordered from an official club email address.', 'bespoke-customiser' ),
		'Grassroots club badges are allowed.'
			=> __( 'Grassroots club badges are allowed.', 'bespoke-customiser' ),
		'Upload my badge'
			=> __( 'Upload my badge', 'bespoke-customiser' ),
		'Cancel'
			=> __( 'Cancel', 'bespoke-customiser' ),

		// ── Boot stickers: the 20mm upgrade in the sticker menu ──────────
		'Size'
			=> __( 'Size', 'bespoke-customiser' ),
		'Make my badge and flag %dmm tall'
			=> __( 'Make my badge and flag %dmm tall', 'bespoke-customiser' ),
		'Make my badge %dmm tall'
			=> __( 'Make my badge %dmm tall', 'bespoke-customiser' ),
		'Make my flag %dmm tall'
			=> __( 'Make my flag %dmm tall', 'bespoke-customiser' ),
		'Instead of the standard %dmm'
			=> __( 'Instead of the standard %dmm', 'bespoke-customiser' ),

		// ── Boot stickers: under the badge upload ────────────────────────
		'Your badge sticker will be about %dmm wide and %dmm tall.'
			=> __( 'Your badge sticker will be about %dmm wide and %dmm tall.', 'bespoke-customiser' ),
		'At %dmm tall your badge would be about %dmm wide. The widest we make is %dmm, so upload a squarer version.'
			=> __( 'At %dmm tall your badge would be about %dmm wide. The widest we make is %dmm, so upload a squarer version.', 'bespoke-customiser' ),
		'Your badge would be wider than %dmm at %dmm tall. Upload a squarer version, or untick the badge sticker.'
			=> __( 'Your badge would be wider than %dmm at %dmm tall. Upload a squarer version, or untick the badge sticker.', 'bespoke-customiser' ),
	];

	// Only what a translation actually changed.
	return array_filter(
		$strings,
		function ( $translated, $english ) {
			return is_string( $translated ) && $translated !== $english && $translated !== '';
		},
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * Hand the translated wording to the page, before customiser.html's script
 * runs, as window.BESPOKE_I18N. Prints nothing when the page is in English.
 */
function bespoke_customiser_print_ui_strings() {
	$map = bespoke_customiser_ui_strings();
	if ( empty( $map ) ) {
		return;
	}
	echo '<script id="bespoke-customiser-i18n">window.BESPOKE_I18N = '
		. wp_json_encode( $map, JSON_UNESCAPED_UNICODE )
		. ";</script>\n";
}
