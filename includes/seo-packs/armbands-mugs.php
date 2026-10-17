<?php
/**
 * SEO pack: armbands and mugs (Nick, 17 Oct 2026).
 *
 * The twelve products Rank Math scored orange (74–79) on staging, after its
 * Update SEO Scores tool: ten captain's armbands and two mugs. Every one had
 * a description of 141–208 words with no subheadings and no links, the focus
 * keyword used once or not at all, and a few missed it in the meta
 * description, the title's start or the picture's alt text.
 *
 * Nick's own wording stays as it was — tidied (lists as lists, missing
 * spaces) but not rewritten — and gains, after it: a subheading with the
 * keyword, the cause's day with one link to the organisation behind it, the
 * official sizes (the same ages for the same lengths on every armband), care
 * from his washing guide with a link to it, and links to the range pages.
 * Names, web addresses, focus keywords and short descriptions are left alone,
 * so each entry finds its product by 'match' and names its row by 'label';
 * the one name it changes is the Hull City mug's, which ends "(Copy)" on
 * live and staging alike (Nick: fix it on live at launch).
 *
 * 'kw' isn't a field: it's the product's focus keyword as Rank Math has it,
 * for the checks run before Nick uploads.
 *
 * Loaded only by the SEO Packs page (customiser-seo-pack.php), and possibly
 * twice in one request — so no named functions here, only closures.
 *
 * @package BEspoke_Customiser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every armband's lengths, with the ages Nick gave for them (24 Sep 2026).
$sizes = <<<'HTML'
<h2>Choosing the right size</h2>
<p>Choose a 5cm or 8cm band, then the length that fits round the arm:</p>
<ul>
<li>16cm – U6–U8</li>
<li>20cm – U8–U12</li>
<li>24cm – U11–U15</li>
<li>28cm – young adult or smaller build adult</li>
<li>32cm – "average" build adult</li>
<li>36cm – bigger build adult</li>
</ul>
HTML;

$measure = <<<'HTML'
<p>If in doubt, measure round the arm where the band will sit before you order.</p>
HTML;

// From Nick's washing and care page on live (/captains-armband-washing-care/).
$care = <<<'HTML'
<h2>Looking after your armband</h2>
<p>Our armbands are made from a polyester and elastic fabric, with the design sublimation printed into it, so the artwork won't peel or crack. Hand wash, or machine wash on a gentle cycle at up to 30°C, then let it dry naturally. Our <a href="/captains-armband-washing-care/">captain's armband washing and care guide</a> has the full steps.</p>
HTML;

$range = <<<'HTML'
<p>See the full range of <a href="/pre-designed-custom-captains-armbands/">pre-designed captain's armbands</a>, or design your own from scratch with our <a href="/product/personalised-captains-armbands/">personalised captain's armband</a>.</p>
HTML;

// A themed armband: its own words, then the parts every armband shares.
$armband = function ( $own, $range_html = null ) use ( $sizes, $measure, $care, $range ) {
	return implode( "\n", array_map( 'trim', [ $own, $sizes, $measure, $care, null === $range_html ? $range : $range_html ] ) );
};

$mug_more = <<<'HTML'
<p>Supporting another team? See all our <a href="/custom-shirt-mugs/">football shirt mugs</a>, or design one for your own club with our <a href="/product/grassroots-football-mug/">grassroots football mug</a>.</p>
HTML;

$products = [];

// ── Captain's armbands ──────────────────────────────────────────────────

$products[] = [
	'label'           => 'Holocaust Memorial Day Captain Armband',
	'match'           => [ 'holocaust-memorial-day-captain-armband' ],
	'kw'              => 'Holocaust Memorial Day Captain Armband',
	'content'         => $armband( <<<'HTML'
<p>Show your respect, unity, and leadership on the pitch with our Holocaust Memorial Day Captain Armband. Thoughtfully designed to honour the memory of those lost and to stand against hate in all forms, this armband is a powerful statement of remembrance and solidarity.</p>
<p>Each armband is handmade and fully customisable, allowing you to add your team's name, logo, or message to create a truly personal tribute. Crafted from premium elasticated fabric, it offers a comfortable fit for players of all ages while staying secure throughout the game.</p>
<p>Whether you're playing in a grassroots match, school fixture, or professional setting, wearing this captain's armband shows your team's support for Holocaust Memorial Day and promotes education, respect, and unity through sport.</p>
<p>At BEspoke Sport, we believe in using football to make a difference — one captain, one message, one match at a time.</p>
<h2>Wearing a Holocaust Memorial Day captain armband on 27 January</h2>
<p>Holocaust Memorial Day takes place on 27 January each year. It remembers the six million Jewish people murdered in the Holocaust, the millions of other people killed under Nazi persecution, and the people killed in the genocides that followed in Cambodia, Rwanda, Bosnia and Darfur.</p>
<p>You could mark the day at your nearest fixture, with a minute's silence before kick-off and your captain wearing a Holocaust Memorial Day captain armband. The <a href="https://www.hmd.org.uk/" target="_blank" rel="noopener">Holocaust Memorial Day Trust</a> has resources for schools, clubs and community groups who want to take part.</p>
<h2>Make it your team's</h2>
<p>Add your team's name, your club logo or a short message, and your Holocaust Memorial Day captain armband becomes a tribute that's yours alone.</p>
HTML
	),
	'seo_title'       => 'Holocaust Memorial Day Captain Armband – 27 January',
	'seo_description' => 'Holocaust Memorial Day captain armband to mark 27 January on the pitch. Add your team name or logo; 6 lengths, 5cm or 8cm bands, made in the UK.',
];

$products[] = [
	'label'           => "International Women's Day Captain Armband",
	'match'           => [ 'international-womens-day-captain-armband' ],
	'kw'              => "International Women's Day Captain Armband",
	'content'         => $armband( <<<'HTML'
<p>Celebrate equality, empowerment, and leadership on the pitch with our International Women's Day Captain Armband. Handcrafted by BEspoke Sport, this armband is designed to honour the strength, unity and influence of women in sport — on and off the field.</p>
<p>Each armband can be fully personalised with your team logo, player name, or message, making it perfect for clubs, schools, tournaments and special events celebrating International Women's Day (8th March).</p>
<p>Made from high-quality elastic material with a secure fit and vibrant colour design, it's comfortable, durable and built to last match after match. Whether you're supporting women's football, rugby, cricket or netball — this armband sends a clear message of respect and equality.</p>
<p><strong>💜 Features:</strong></p>
<ul>
<li>Available in multiple sizes (Junior, Youth, Adult)</li>
<li>Premium elastic for comfort and performance</li>
<li>Symbolises empowerment and inclusivity</li>
<li>Ideal for matches and Women's Day events</li>
<li>Designed and printed in the UK by BEspoke Sport</li>
</ul>
<p>Show your pride and lead by example — order your International Women's Day Captain Armband today and wear your support for equality proudly.</p>
<h2>An International Women's Day captain armband for 8 March</h2>
<p>International Women's Day falls on 8 March every year. It's a day to celebrate the women and girls who play, coach, referee, volunteer and run clubs, and to keep pushing for equality in sport. An International Women's Day captain armband at your nearest fixture is a simple, visible way for your captain to lead that message.</p>
<p>Find ideas and events for your club on the <a href="https://www.internationalwomensday.com/" target="_blank" rel="noopener">International Women's Day</a> website.</p>
HTML
	),
	'seo_title'       => "International Women's Day Captain Armband – 8 March",
	'seo_description' => "International Women's Day captain armband for 8 March. Add your team logo or a name; 6 lengths, 5cm or 8cm bands, printed in the UK.",
	// The picture's alt text spelt it "Womens", so it never matched the keyword.
	'image_alt'       => "International Women's Day captain armband",
];

$products[] = [
	'label'           => 'Professional League Inspired Captain Armband',
	'match'           => [ 'professional-league-inspired-captain-armband' ],
	'kw'              => 'Professional League Inspired Captain Armband',
	'content'         => $armband( <<<'HTML'
<p>Lead like a pro with our Professional League Inspired Captain Armband, designed in the same bold, modern style seen on the biggest stages in football.</p>
<p>Made from high-quality elasticated material, this premium armband combines comfort, durability, and style. The deep purple design with bold white "C" detailing delivers that authentic top-flight look — perfect for grassroots captains who want to feel like they're leading their side in the big leagues.</p>
<p>Whether you're managing a Sunday League side or heading up a semi-pro team, this armband gives your kit that extra touch of professionalism — without using any official league logos or branding.</p>
<ul>
<li>Professional "elite match" design</li>
<li>Premium elasticated fit for all-day comfort</li>
<li>Suitable for youth and adult players</li>
<li>Durable and easy to clean</li>
<li>Designed and made in the UK by BEspoke Sport</li>
</ul>
<p>Bring the look of the pros to your next match — and lead your team in true top-flight style.</p>
<h2>Why choose a Professional League Inspired Captain Armband?</h2>
<p>Captains at the top level wear a bold, simple armband that everyone on the pitch can spot. Our Professional League Inspired Captain Armband gives grassroots, Sunday league and school captains that same clean, top-flight look, in a design of our own. It suits a cup final, a presentation day or a new captain starting the season.</p>
<h2>Good to know</h2>
<h3>Is this an official league armband?</h3>
<p>No. The Professional League Inspired Captain Armband is our own design, inspired by the look of the top leagues, with no official league logos or branding.</p>
HTML
	),
	'seo_title'       => 'Professional League Inspired Captain Armband – 6 Sizes',
	// It was the Baby Loss Awareness Week armband's description, pasted in.
	'seo_description' => 'Professional League Inspired Captain Armband with a bold top-flight look and no official logos. 6 lengths, 5cm or 8cm bands, made in the UK.',
];

$products[] = [
	'label'           => 'Pride Captains Armband',
	// Product 4967. One of its own variations has the same web address, but
	// the search only looks at products, so it can't pick that one up.
	'match'           => [ 'pride-captains-armband' ],
	'kw'              => 'pride captains armband',
	'content'         => $armband(
		<<<'HTML'
<p>Make a statement of unity and inclusion with our Pride Captains Armband. Designed in vibrant rainbow colours, this high-quality armband celebrates diversity and equality while offering a secure and comfortable fit for every match.</p>
<p>Crafted from premium elastic material, the Pride Captains Armband combines durability with flexibility — ensuring it stays in place whether you're leading your football, rugby, or netball team. The bold rainbow design stands out on the field, making it a perfect choice for clubs that want to show support for the LGBTQ+ community in sport.</p>
<p><strong>Features:</strong></p>
<ul>
<li>🌈 Bold rainbow design symbolising pride and inclusion</li>
<li>💪 Durable, stretch-fit elastic for comfort and performance</li>
<li>🧼 Machine washable</li>
<li>👕 Available in Adult and Junior sizes</li>
<li>🇬🇧 Designed and made in the UK</li>
</ul>
<p>Whether you're playing in a local league or a national tournament, the Pride Captains Armband helps you lead with confidence and show your values with pride.</p>
<h2>A pride captains armband for every match</h2>
<p>Pride Month is every June, and many clubs also join Stonewall's Rainbow Laces campaign to show that LGBTQ+ players and fans are welcome in sport. A pride captains armband on your captain's arm says the same thing every week of the season, not just on awareness days.</p>
<p>Find out more about Rainbow Laces from <a href="https://www.stonewall.org.uk/" target="_blank" rel="noopener">Stonewall</a>.</p>
<p>Looking for something more personal? Check out our <a href="/product/personalised-captains-armbands/">Fully Personalised Captains Armband</a> and create your own custom design.</p>
HTML
		,
		// Its own words already point to the personalised armband.
		'<p>See the full range of <a href="/pre-designed-custom-captains-armbands/">pre-designed captain\'s armbands</a>.</p>'
	),
	'seo_title'       => 'Pride Captains Armband – Rainbow Armband, 6 Sizes',
	'seo_description' => 'Pride captains armband in bold rainbow colours, celebrating equality and inclusion on the pitch. 6 lengths, 5cm or 8cm bands, made in the UK.',
];

$products[] = [
	'label'           => 'Mental Health Awareness Captains Armband',
	'match'           => [ 'mental-health-awareness-captain-armband' ],
	'kw'              => 'Mental Health Awareness Captain Armband',
	'content'         => $armband( <<<'HTML'
<p>Show your support both on and off the pitch with our Mental Health Awareness Captain Armband — a powerful symbol of unity, strength, and compassion in sport.</p>
<p>Crafted from premium elastic material for maximum comfort and durability, this armband features a bold green ribbon design to represent Mental Health Awareness. Whether you're leading your team in grassroots football, Sunday league, or school competitions, this armband reminds everyone that it's okay not to be okay.</p>
<p>Each armband can be personalised with your team name, player name, or message, making it a unique and meaningful addition to your matchday kit. Designed and printed in the UK by BEspoke Sport, our captain's armbands combine professional quality with a message that matters.</p>
<p>Stand tall. Lead with empathy. Play for change. 💚</p>
<p><strong>Key Features:</strong></p>
<ul>
<li>Premium-quality elastic captain's armband</li>
<li>Mental Health Awareness green ribbon design</li>
<li>Fully customisable with name, team, or logo</li>
<li>Available in multiple sizes (Junior, Youth, Adult)</li>
<li>Durable and washable for repeated use</li>
<li>Handmade in the UK by BEspoke Sport</li>
</ul>
<p><strong>Perfect for:</strong> Football, Rugby, Hockey, Netball, and all team sports where leadership and awareness go hand in hand.</p>
<h2>When to wear a Mental Health Awareness captain armband</h2>
<p>Mental Health Awareness Week takes place in May and World Mental Health Day is on 10 October, and both are good moments to wear a Mental Health Awareness captain armband and say a few words before kick-off. You could wear one all season, too, as a reminder that the dressing room is a place where anyone can talk.</p>
<p>If you, a teammate or one of your players needs support, <a href="https://www.mind.org.uk/" target="_blank" rel="noopener">Mind</a> has information and advice.</p>
<h2>Make it your team's</h2>
<p>Add your team name, a player's name or a short message, and your Mental Health Awareness captain armband becomes part of your club's own story.</p>
HTML
	),
	'seo_title'       => 'Mental Health Awareness Captain Armband – 6 Sizes',
	'seo_description' => 'Mental Health Awareness captain armband with a green ribbon design. Add your team or a name; 6 lengths, 5cm or 8cm bands, printed in the UK.',
	// It said "Mental health Day captain armband".
	'image_alt'       => 'Mental Health Awareness captain armband with a green ribbon design',
];

$products[] = [
	'label'           => 'Football vs Homophobia Captain Armband',
	'match'           => [ 'football-vs-homophobia-captain-armband' ],
	'kw'              => 'Football vs Homophobia Captain Armband',
	'content'         => $armband( <<<'HTML'
<p>Show your support for equality and inclusion with our Football vs Homophobia Captain Armband – designed to stand out both in colour and in message. Perfect for football teams, tournaments, and community events that champion diversity, respect, and unity within the sport.</p>
<p>Each armband is made from high-quality elastic, ensuring a comfortable fit and durability throughout the match. With bold colours and clear text, it's impossible to miss your message of solidarity against discrimination.</p>
<p>At BEspoke Sport, we can also personalise your captain's armband with your team name, logo, or message to make it unique to your club. Whether you're wearing it for a special awareness match or as your team's permanent captain band, this armband sends a powerful statement: ⚽ Football is for everyone.</p>
<p><strong>Product Features:</strong></p>
<ul>
<li>Strong elastic and premium stitching for lasting performance</li>
<li>Clear "Football vs Homophobia" message</li>
<li>Optional personalisation with name, logo, or number</li>
<li>Available in multiple sizes for youth and adult teams</li>
<li>Ideal for awareness campaigns, tournaments, and leagues</li>
</ul>
<p>Stand against discrimination – wear your values proudly.</p>
<h2>Why wear a Football vs Homophobia captain armband?</h2>
<p>Too many LGBTQ+ players and fans still don't feel welcome in football. A Football vs Homophobia captain armband is a visible way for your captain to say that everyone is welcome at your club, whether you wear it during LGBT+ History Month in February, at a Pride tournament in the summer or every week of the season.</p>
<p><a href="https://www.kickitout.org/" target="_blank" rel="noopener">Kick It Out</a>, English football's equality and inclusion organisation, has advice for clubs that want to tackle discrimination.</p>
<h2>Make it your team's</h2>
<p>Add your team name, your logo or a short message to your Football vs Homophobia captain armband, and make it unique to your club.</p>
HTML
	),
	'seo_title'       => 'Football vs Homophobia Captain Armband – 6 Sizes',
	'seo_description' => 'Football vs Homophobia captain armband to show football is for everyone. Add your team name; 6 lengths, 5cm or 8cm bands, made in the UK.',
];

$products[] = [
	'label'           => 'Made for this Game Captain Armband',
	'match'           => [ 'made-for-this-game-captain-armband' ],
	'kw'              => 'Made for this Game Captain Armband',
	// The quote marks round "Made for this Game" split the keyword, so Rank
	// Math never found it; the first sentence loses them.
	'content'         => $armband( <<<'HTML'
<p>Stand out on the pitch with our Made for this Game captain armband – designed for true leaders who play with passion and pride. Made by BEspoke Sport, this premium-quality armband is built to last through every match, every tackle, and every victory.</p>
<p>Crafted from durable elastic with a secure fit, it offers maximum comfort and visibility – keeping you focused on leading your team. The bold "Made for this Game" print reminds everyone that you're built for football and ready to inspire.</p>
<p>Perfect for grassroots clubs, Sunday league sides, or academy teams who want their captains to wear something that truly represents their drive and dedication.</p>
<ul>
<li>Premium elasticated fabric</li>
<li>Bold, fade-resistant "Made for this Game" print</li>
<li>Comfortable, adjustable fit for all arm sizes</li>
<li>Available in a range of colours and sizes</li>
<li>Customisable with team logos or player initials</li>
</ul>
<p>Show that you're not just a captain — you're made for this game.</p>
<h2>A Made for this Game captain armband for every captain</h2>
<p>Some captains lead with words and some lead by example. The Made for this Game captain armband suits both, with a message that sums up what being captain means: turning up, working hard and setting the standard for everyone else. It makes a great gift for a new captain, a player of the season or a coach to hand out at the start of the year.</p>
<h2>Make it your team's</h2>
<p>Add your team logo or a player's initials, and your Made for this Game captain armband is ready for kick-off.</p>
HTML
	),
	'seo_title'       => 'Made for this Game Captain Armband – 6 Sizes',
	'seo_description' => 'Made for this Game captain armband for captains who lead with passion. Bold, fade-resistant print; 6 lengths, 5cm or 8cm bands, made in the UK.',
];

$products[] = [
	'label'           => 'Love is Love Captain Armband',
	'match'           => [ 'custom-pride-football-armbands-love-is-love-captain-armband' ],
	'kw'              => 'Love is Love Captain Armband',
	'content'         => $armband( <<<'HTML'
<p>Show your pride and leadership on and off the pitch with our Love is Love Captain Armband — a vibrant symbol of unity, equality, and inclusivity in sport.</p>
<p>Crafted from high-quality elasticated fabric, this armband is designed for maximum comfort and durability during every match. Whether you're leading your local Sunday League team or representing your school or club, this bold rainbow-style captain's armband sends a clear message: Love is Love — football is for everyone.</p>
<p>Each armband can be personalised with your name, team logo, or message, making it completely unique to you. Using our custom design tool, you can easily upload your artwork or choose from our ready-made templates in just minutes.</p>
<p>Perfect for Pride Month tournaments, awareness matches, or simply showing support for inclusivity all year round.</p>
<ul>
<li>Premium elasticated fabric — fits comfortably over sleeves</li>
<li>Bold "Love is Love" design</li>
<li>Available in youth and adult sizes</li>
<li>Made in the UK by BEspoke Sport</li>
</ul>
<p>Make a statement of pride, equality, and leadership — because the game belongs to everyone.</p>
<h2>A Love is Love captain armband for Pride and beyond</h2>
<p>Pride Month is every June, with Pride tournaments and rainbow fixtures right through the summer, and the message matters all season long. A Love is Love captain armband is a simple, positive way for your captain to show that your team welcomes everyone, whoever they love.</p>
<p>Find out how clubs can support LGBTQ+ players and fans with <a href="https://www.stonewall.org.uk/" target="_blank" rel="noopener">Stonewall</a>.</p>
<h2>Make it your team's</h2>
<p>Add your name, your team logo or a short message, and your Love is Love captain armband is completely unique to you.</p>
HTML
	),
	'seo_title'       => 'Love is Love Captain Armband – Pride Armband, 6 Sizes',
	'seo_description' => 'Love is Love captain armband for Pride matches and inclusive teams. Add your name or team logo; 6 lengths, 5cm or 8cm bands, made in the UK.',
];

$products[] = [
	'label'           => 'World Cancer Day Captains Armband',
	'match'           => [ 'world-cancer-day-captains-armband' ],
	'kw'              => 'World Cancer Day Captains Armband',
	'content'         => $armband( <<<'HTML'
<p>Show your support and raise awareness with our World Cancer Day Captains Armband – a powerful symbol of unity and strength on and off the pitch. Made from premium elasticated fabric, this armband ensures a comfortable and secure fit throughout the game, allowing every captain to lead with pride and purpose.</p>
<p>Each armband is customisable with your team's name, colours, or message, creating a unique accessory that highlights your commitment to a vital cause. Whether it's for a fundraising match, awareness event, or league fixture, this armband stands out for all the right reasons.</p>
<ul>
<li>Durable, stretchy and sweat-resistant material</li>
<li>Personalised with your team's details</li>
<li>Available in multiple sizes for youth and adults</li>
<li>Designed and printed in the UK</li>
<li>Perfect for charity matches, awareness games, and community events</li>
</ul>
<p>Join teams across the UK in showing support this World Cancer Day — because together, we can make a difference both on and off the pitch.</p>
<h2>Wearing a World Cancer Day captains armband on 4 February</h2>
<p>World Cancer Day is on 4 February every year. It's a chance for clubs to raise awareness, remember the people they've lost and support everyone living with cancer today. A World Cancer Day captains armband gives your captain a visible way to lead that message, at a fundraising match or your weekend fixture.</p>
<p>For information about cancer and the research to beat it, visit <a href="https://www.cancerresearchuk.org/" target="_blank" rel="noopener">Cancer Research UK</a>.</p>
<h2>Make it your team's</h2>
<p>Add your team's name, your colours or a short message, and your World Cancer Day captains armband becomes your club's own tribute.</p>
HTML
	),
	'seo_title'       => 'World Cancer Day Captains Armband – 4 February',
	'seo_description' => 'World Cancer Day captains armband for 4 February. Add your team name or colours; 6 lengths, 5cm or 8cm bands, printed in the UK.',
];

$products[] = [
	'label'           => "Personalised Double Sided Captain's Armbands",
	'match'           => [ 'personalised-double-sided-captains-armbands' ],
	'kw'              => "Personalised Double Sided Captain's Armbands",
	// A customiser product, not a themed one: its own sizing notes and links.
	'content'         => implode( "\n", array_map( 'trim', [
		<<<'HTML'
<p>Lead your team in style with our personalised double sided captain's armbands, printed on both sides for the ultimate professional finish. Perfect for football, rugby, hockey, and more — these armbands are designed to stand out whether you're playing home or away.</p>
<p>Each armband is custom printed on both sides, meaning your message, team name, or logo can be visible from every angle. Crafted from high-quality elastic for a secure, comfortable fit, they're made to perform as hard as you do on the pitch.</p>
<ul>
<li>Personalised on both sides – show your design whichever way you wear it</li>
<li>Premium stretch fabric – durable, washable, and comfortable</li>
<li>Choose your colour, text, logo, or even upload your club badge</li>
<li>Designed and made in the UK by BEspoke Sport</li>
</ul>
<h2>Why choose personalised double sided captain's armbands?</h2>
<p>A single-sided armband only shows its design when it's facing the right way. Personalised double sided captain's armbands carry your design on both sides, so your badge, name or message is on show whichever arm your captain wears it on and whichever way it turns during the game.</p>
<h2>16 designs to start from</h2>
<p>Choose from 16 designs: Bolt, Club, Counter, Finisher, Gladiator, Hawk, Hunter, Maestro, Pace, Pride, Pro, Sniper, Strike, Team, Torn and Vintage. Then make your personalised double sided captain's armbands your own with your club colours, your badge, a name or a message in the customiser above.</p>
HTML
		,
		$sizes,
		<<<'HTML'
<p>16cm is VERY small and often used for U6/7's – 32cm is generally for an "average" adult.</p>
<p>For 16cm and 20cm bands, we recommend having "C" instead of "CAPTAIN" as this allows us to keep the badges as large as possible even on the smaller armband.</p>
<p><strong>If in doubt, please measure the arm prior to ordering, as the sizes above are just a guide and we cannot be held responsible for bands that don't fit.</strong></p>
<p>Any questions regarding size, please drop us a message on <a href="https://www.facebook.com/BEspokeSportUK/" target="_blank" rel="noopener">Facebook</a>.</p>
HTML
		,
		$care,
		'<p>Prefer a single-sided armband? See our <a href="/product/personalised-captains-armbands/">personalised captain\'s armband</a>, or browse our <a href="/pre-designed-custom-captains-armbands/">pre-designed captain\'s armbands</a>.</p>',
	] ) ),
	// It started "Lead Your Team with …", so the keyword wasn't at the front.
	'seo_title'       => "Personalised Double Sided Captain's Armbands – 16 Designs",
	'seo_description' => "Personalised double sided captain's armbands, printed on both sides with your badge, name or message. 16 designs, 6 lengths, made in the UK.",
	// It had none.
	'image_alt'       => "Personalised double sided captain's armbands, printed on both sides",
];

// ── Mugs ────────────────────────────────────────────────────────────────

$products[] = [
	'label'           => 'Wolverhampton Wanderers Inspired Mug',
	'match'           => [ 'wolverhampton-wanderers-inspired-mug' ],
	'kw'              => 'wolverhampton wanderers inspired mug',
	'content'         => implode( "\n", array_map( 'trim', [
		<<<'HTML'
<p>Wolverhampton Wanderers inspired mug created as an unofficial, fan-made celebration of Wolves and the players bringing energy and flair to Molineux. Featuring a retro illustrated shirt design, this mug lets you customise every name on the back — whether you choose current stars like Hwang Hee-chan, Gomes, Strand Larsen, Arias or Munetsi, or go for your own surname if you fancy leading the line yourself.</p>
<p>Ideal as a birthday present, matchday brew companion, office desk statement or a personal tribute to your favourite Wolves players, this custom mug delivers charm, nostalgia and a proper dose of old gold pride with every sip.</p>
<p><strong>Product Highlights:</strong></p>
<ul>
<li>Unofficial Wolverhampton Wanderers–inspired design</li>
<li>Fully customisable surnames (players, heroes or your own)</li>
<li>Retro illustrated old gold shirt artwork</li>
<li>Premium ceramic mug</li>
<li>Dishwasher &amp; microwave safe</li>
<li>Fan-made creation (no official affiliation — just pure Wolves passion)</li>
</ul>
<p>A brilliant choice for collectors, loyal Wolves supporters, and anyone who knows their tea tastes better in old gold.</p>
<h2>Make your Wolverhampton Wanderers inspired mug your own</h2>
<p>Choose the design with 5 shirts or 11 shirts, then add the surnames you'd like on the backs: your all-time Wolves XI, this season's favourites, your family or your Sunday league teammates. Every mug is made to order.</p>
<h2>Good to know</h2>
<h3>Is this an official Wolves product?</h3>
<p>No. This Wolverhampton Wanderers inspired mug is an unofficial, fan-made design with no official connection to the club.</p>
<h3>Can I put my own name on it?</h3>
<p>Yes. Every surname on the shirts can be changed, so you can add your own name or your family's.</p>
<h3>Is it dishwasher safe?</h3>
<p>Yes. It's a premium ceramic mug, safe in the dishwasher and the microwave.</p>
HTML
		,
		$mug_more,
	] ) ),
	// 219 characters before; Google shows about 155.
	'seo_description' => "Unofficial Wolverhampton Wanderers inspired mug with a retro shirt design. Add your favourite players' names or your own. Dishwasher safe.",
];

$products[] = [
	'label'           => 'Arsenal Inspired Mug',
	'match'           => [ 'arsenal-inspired-mug-unofficial-fan-made-tribute-gift' ],
	'kw'              => 'Arsenal inspired mug',
	'content'         => implode( "\n", array_map( 'trim', [
		<<<'HTML'
<p>Arsenal inspired mug celebrating classic North London greatness in a fun, unofficial, fan-made style. This nostalgic Legends Mug brings together iconic surnames on a stylised retro shirt — perfect for long-time Gooners, new supporters, and anyone who enjoys a cheerful nod to their favourite heroes with their morning brew. It's fully customisable too, so you can add your own team favourites.</p>
<p>Crafted with premium ceramic and finished with a vivid, illustrated design, this mug is ideal as a gift, a desk companion, or part of your matchday ritual. There's no official affiliation here — just pure fan passion, a splash of retro charm, and enough style to brighten up any coffee break.</p>
<p><strong>Product Highlights:</strong></p>
<ul>
<li>Unofficial Arsenal-inspired design</li>
<li>Retro illustrated shirt with iconic surnames</li>
<li>Premium ceramic mug</li>
<li>Dishwasher &amp; microwave safe</li>
<li>Designed by fans, for fans</li>
</ul>
<p>A brilliant birthday present, a matchday must-have, and a gentle reminder to your Spurs mates about where your loyalties lie.</p>
<h2>Make your Arsenal inspired mug your own</h2>
<p>Choose the design with 5 shirts or 11 shirts, then add the surnames you'd like on the backs: North London legends, today's squad, your own name or your Sunday league teammates. Every mug is made to order.</p>
<h2>Good to know</h2>
<h3>Is this an official Arsenal product?</h3>
<p>No. This Arsenal inspired mug is an unofficial, fan-made design with no official connection to the club.</p>
<h3>Can I put my own name on it?</h3>
<p>Yes. Every surname on the shirts can be changed, so you can add your own name or your family's.</p>
<h3>Is it dishwasher safe?</h3>
<p>Yes. It's a premium ceramic mug, safe in the dishwasher and the microwave.</p>
HTML
		,
		$mug_more,
	] ) ),
];

// Not orange, but its name ends "(Copy)" on live and staging alike.
$products[] = [
	'label' => 'Hull City Inspired Mug',
	'match' => [ 'hull-city-inspired-mug' ],
	'title' => 'Hull City Inspired Mug – Unofficial Custom Fan-Made Gift',
];

return [
	'name'     => 'Armbands and mugs',
	'summary'  => 'The 12 products Rank Math scored orange: 10 captain\'s armbands and 2 mugs. Your wording stays; each gains a keyword subheading, the official sizes, care and links, with Rank Math titles and descriptions where they missed. Also takes "(Copy)" off the Hull City mug\'s name.',
	'products' => $products,
];
