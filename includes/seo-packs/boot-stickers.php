<?php
/**
 * SEO pack: boot stickers (Nick, 17 Oct 2026).
 *
 * Personalised Football Boot Stickers had nothing on its product page yet:
 * no description, no short description, no category, no picture and no Rank
 * Math fields. Everything here is from Nick (17 Oct 2026) or from the
 * customiser itself:
 * - they come in pairs, are waterproof, are self-applied with no heat, and
 *   MUST go on a clean, smooth surface (the customiser's notice adds: not
 *   cloth or suede);
 * - 1cm or 2cm high: text is always 1cm (capitals), flags and badges 1cm or
 *   the 2cm upgrade; text and badges run to 9cm at most, and past about 5cm
 *   the customiser warns they may not fit a smaller boot;
 * - made to order, posted 1st Class Royal Mail (lead time as Nick gave it:
 *   "> 5 day" — written as up to 5 working days, to confirm with him);
 * - grassroots club badges are welcome; copyright badges such as Premier
 *   League clubs' only from an official club email address (the upload
 *   pop-up's wording).
 * Nick doesn't want the material named, so it isn't.
 *
 * The category is BEspoke, as for the other customiser products. The alt
 * text waits for a picture: applying the pack again once there is one sets it.
 *
 * Loaded only by the SEO Packs page (customiser-seo-pack.php), and possibly
 * twice in one request — so no named functions here, only closures.
 *
 * @package BEspoke_Customiser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$products = [];

$products[] = [
	'label'           => 'Personalised Football Boot Stickers',
	'match'           => [ 'personalised-football-boot-stickers' ],
	'kw'              => 'personalised football boot stickers',
	'excerpt'         => '<p>A pair of waterproof <strong>personalised football boot stickers</strong> with your name, your flag or your club badge. 1cm or 2cm high, applied by you with no heat needed, made to order and posted 1st class.</p>',
	'content'         => <<<'HTML'
<p><strong>Personalised football boot stickers</strong> put your name, your flag or your club badge on your boots. Design your pair in the customiser above: type your name, pick a font, add a flag or your club badge, and see exactly how they'll look on the heel or the side of a boot before you order.</p>
<h2>What can you put on personalised football boot stickers?</h2>
<p>Your name, your initials or a nickname, your country's flag, your club badge, or all three together. Text is 1cm high, in capital letters, and as long as your name needs, up to 9cm. Flags and badges are 1cm high as standard, or 2cm high if you'd like them to stand out more.</p>
<p>A name on the heel and a flag on the side, your initials and your club badge, or just your name in a bold font: every pair is designed by you and made to order.</p>
<h2>Club badges</h2>
<p>Grassroots club badges are welcome, so your whole team can wear their badge on their boots. Badges protected by copyright, such as Premier League club badges, can only be ordered from an official club email address.</p>
<h2>Waterproof stickers you apply yourself</h2>
<p>Every sticker is waterproof, so rain, mud and wet grass won't spoil it, and you apply them yourself, with no heat needed. They must go on a clean, smooth surface, so wipe the boot clean and let it dry first. They won't stick to fabric or suede, so choose a smooth panel on the heel or the side of the boot.</p>
<h2>Will they fit my boots?</h2>
<p>Most names fit easily. Longer names can run up to 9cm, so check your boot has a smooth, flat space that long, especially on smaller junior boots. The customiser shows the length of your text as you type, and tells you when it may be too long for a smaller boot.</p>
<h2>Made to order and posted 1st class</h2>
<p>Every pair of personalised football boot stickers is made to order, so please allow up to 5 working days before it's posted 1st Class with Royal Mail. They make a great gift for a birthday, a new season or a player of the match.</p>
<h2>Good to know about personalised football boot stickers</h2>
<h3>How many stickers do I get?</h3>
<p>They come in pairs, one for each boot.</p>
<h3>Can I order stickers for my whole team?</h3>
<p>Yes. Add as many pairs as you like to your basket, each with its own name.</p>
<h3>Can I change my design after adding it to the basket?</h3>
<p>Yes. Until you check out, every pair in your basket has an Amend link that reopens it in the customiser.</p>
<h3>What surfaces will they stick to?</h3>
<p>Clean, smooth, dry surfaces only. They won't stick to fabric, suede or textured panels.</p>
<p>Kitting out a player for match day? See our <a href="/product/personalised-shin-pads/">personalised shin pads</a> and <a href="/product/personalised-grip-socks-bespoke-sports/">personalised grip socks</a> too.</p>
HTML,
	'seo_title'       => 'Personalised Football Boot Stickers – Waterproof, Pair of 2',
	'seo_description' => 'Personalised football boot stickers with your name, flag or club badge. A waterproof pair, 1cm or 2cm high, made to order and posted 1st class.',
	'focus_keyword'   => 'personalised football boot stickers,football boot name stickers',
	'categories'      => [ 'bespoke' ],
	'image_alt'       => 'Personalised football boot stickers – a waterproof pair with a name, a flag and a club badge',
	'eyebrow'         => 'THE CUSTOMISER',
	'subtitle'        => 'Your name, your flag, your club badge — on a waterproof pair of stickers for your boots. Made to order and posted 1st class.',
	'price_suffix'    => 'Per pair · inc. VAT',
	'at_a_glance'     => [
		[ 'Height', '1cm, or 2cm for flags and badges' ],
		[ 'Length', 'Up to 9cm' ],
		[ 'Finish', 'Waterproof' ],
		[ 'Applying', 'By you, no heat needed' ],
		[ 'Quantity', 'Sold as a pair' ],
		[ 'Lead time', 'Up to 5 working days' ],
	],
];

return [
	'name'     => 'Boot stickers',
	'summary'  => 'Personalised Football Boot Stickers: a full description, short description, Rank Math title, description and keywords, the BEspoke category and the BEspoke Page fields. The picture\'s alt text follows once it has a picture.',
	'products' => $products,
];
