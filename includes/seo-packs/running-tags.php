<?php
/**
 * SEO pack: running tags (Nick, 17 Oct 2026).
 *
 * Personalised Running Tags and the 15 awareness tags: each product's name
 * ("… Awareness Running Tags" — every one is a pair), its web address, both
 * descriptions, Rank Math title, description and focus keyword, categories
 * (Running Tags, and Awareness Running Tags for the themed ones), the
 * picture's alt text and the BEspoke Page fields. The awareness tags leave
 * the shop (search only, not the shop) for the Awareness Running Tags page,
 * where they come after Personalised Running Tags: they're ordered from 101,
 * past anything the shop's own order uses, so Personalised Running Tags keeps
 * its place in the shop and still comes first on the page.
 *
 * Every awareness description is written around its own cause, so fifteen
 * pages don't read as copies of each other. Links inside the site are
 * relative, so they work on staging and live alike.
 *
 * Loaded only by the SEO Packs page (customiser-seo-pack.php), and possibly
 * twice in one request — so no named functions here, only closures.
 *
 * @package BEspoke_Customiser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// What every pair is, in the order the At a glance sidebar lists it.
$glance = [
	[ 'Size', '45 × 12mm each' ],
	[ 'Material', 'Aluminium, printed in full colour' ],
	[ 'Finish', 'Waterproof' ],
	[ 'Quantity', 'Sold as a pair' ],
	[ 'Lead time', '3-5 working days' ],
];

// The parts every awareness description shares. {kw} is the product's name
// as it reads in a sentence, e.g. "autism awareness running tags".
$personalise = <<<'HTML'
<h2>Make your pair your own</h2>
<p>Your {kw} start with the cause's own wording, and every word can be changed in the customiser above. Add a name, a date, your club or a few words that matter to you, then pick a font and a text colour. You can add your club badge too, with room for up to two badges on each tag. Keep both tags the same, or give the left and right tag their own words and badges.</p>
HTML;

$spec = <<<'HTML'
<h2>Waterproof tags, made for running</h2>
<p>Each tag is 45 × 12mm of aluminium, printed in full colour and fully waterproof, so rain, mud and puddles won't touch the print. They thread straight onto your laces in seconds and stay put, mile after mile, and they weigh next to nothing, so you won't notice them on your feet. Your {kw} are made for running, walking, the gym and everyday trainers. They're not for football, rugby or other team sports, where the rules don't allow metal on footwear.</p>
HTML;

$delivery = <<<'HTML'
<h2>Made to order in 3–5 working days</h2>
<p>Every pair is made to order, so please allow 3–5 working days before it's posted 1st class. A pair of {kw} makes a thoughtful gift for any runner, whether it's for a birthday, a race day or a fundraising challenge.</p>
HTML;

$faq = <<<'HTML'
<h2>Good to know about {kw}</h2>
<h3>Will the tags fall off?</h3>
<p>No. They thread onto your laces and are held in place once your shoes are laced up, so they stay put on long runs, muddy trails and race days.</p>
<h3>Can I order more than one pair?</h3>
<p>Yes. Add as many pairs as you like to your basket, with the same wording or something different for each runner in your family, club or fundraising team.</p>
<h3>Can I change my design after adding it to the basket?</h3>
<p>Yes. Until you check out, every pair in your basket has an Amend link that reopens it in the customiser, and a Duplicate link for a second pair with small changes.</p>
<h3>What will they fit on?</h3>
<p>Any shoes with laces: running shoes, trail shoes, walking boots and everyday trainers. Each tag has a hole at each end, so the lace goes in through one and out through the other.</p>
<h3>Will the print fade?</h3>
<p>The design is printed into the tag's coating in full colour, and it's waterproof, so it's made to stay bright and easy to read through wet runs, muddy trails and long training blocks.</p>
HTML;

$more = <<<'HTML'
<p>See every design in our <a href="/awareness-running-tags/">awareness running tags</a> range, or start from scratch with <a href="/product/personalised-running-tags/">personalised running tags</a>.{armband}</p>
HTML;

// One awareness product, from its own words and the shared parts.
$awareness = function ( $p ) use ( $glance, $personalise, $spec, $delivery, $faq, $more ) {
	$fill = function ( $tpl ) use ( $p ) {
		return str_replace( '{kw}', $p['kw'], $tpl );
	};
	$armband = '';
	if ( ! empty( $p['armband'] ) ) {
		$armband = ' Captain of a team? There\'s a matching <a href="/product/' . $p['armband'][0] . '/">' . $p['armband'][1] . '</a> too.';
	}
	$content = implode( "\n", [
		trim( $p['intro'] ),
		trim( $p['why'] ),
		trim( $fill( $personalise ) ),
		trim( $fill( $spec ) ),
		trim( $fill( $delivery ) ),
		trim( $fill( $faq ) ),
		trim( $p['find_out'] ),
		trim( str_replace( '{armband}', $armband, $more ) ),
	] );
	return [
		'match'           => $p['old'],
		'title'           => $p['name'],
		'slug'            => $p['slug'],
		'excerpt'         => '<p>A pair of waterproof aluminium <strong>' . $p['kw'] . '</strong> for your trainers, with your own words and badges. Made to order in 3–5 working days and posted 1st class.</p>',
		'content'         => $content,
		'seo_title'       => $p['seo_title'],
		'seo_description' => $p['seo_description'],
		'focus_keyword'   => $p['keywords'],
		'categories'      => [ 'running-tags', 'awareness-running-tags' ],
		'visibility'      => 'search',
		'menu_order'      => $p['order'],
		'image_alt'       => $p['name'] . ' – a pair of waterproof aluminium tags for running shoe laces',
		'eyebrow'         => 'THE CUSTOMISER',
		'subtitle'        => $p['subtitle'],
		'price_suffix'    => 'Per pair · inc. VAT',
		'at_a_glance'     => $glance,
	];
};

$products = [];

// ── Personalised Running Tags: first on the page, and still in the shop ──
$products[] = [
	'match'           => [ 'personalised-running-tags' ],
	'title'           => 'Personalised Running Tags',
	'slug'            => 'personalised-running-tags',
	'excerpt'         => '<p>A pair of waterproof aluminium <strong>personalised running tags</strong> with your name, message, colours and club badge. Made to order in 3–5 working days and posted 1st class.</p>',
	'content'         => <<<'HTML'
<p><strong>Personalised running tags</strong> put your name, your message or your club on your laces. Design your pair in the customiser above: pick a pattern and colours, add your words and badges, and we'll make it to order.</p>
<h2>What can you put on personalised running tags?</h2>
<p>Almost anything that fits on a tag 45mm long: your name or initials, a nickname, a target time or a new PB, the race you're training for, a few words to keep you going at mile 20, or the name of someone you're running for. Add your running club's badge, with room for up to two badges on each tag, and choose a font and text colour to match.</p>
<p>Junior runners get a kick out of seeing their own name on their shoes, and a pair with a parent's phone number on can help if a child gets separated at a busy event. The two tags in your pair can match exactly, or each can carry its own words and badges, so the left and right tag can say different things.</p>
<h2>Your designs, your colours</h2>
<p>Choose from a range of patterns, then set the background and design colours to your own favourites or your club's colours. What you see in the preview is what we print, in full colour.</p>
<p>Not sure where to start? Try your club colours with your initials on one tag and your club badge on the other, or a bold pattern with the name of the race you're training for.</p>
<h2>Waterproof running shoe tags</h2>
<p>Each tag is 45 × 12mm of aluminium, printed in full colour and fully waterproof. They thread onto your laces in seconds and stay put through rain, mud and race-day puddles, and they weigh next to nothing, so you won't notice them on your feet. Personalised running tags are made for running, walking, the gym and everyday trainers. They're not for football, rugby or other team sports, where the rules don't allow metal on footwear.</p>
<h2>A gift for every runner</h2>
<p>A pair of personalised running tags makes a great present for a birthday, Christmas, a first marathon or a club prize, and they make good end-of-season awards for a whole club or junior squad. Every pair is made to order in 3–5 working days and posted 1st class.</p>
<h2>Good to know about personalised running tags</h2>
<h3>Will the tags fall off?</h3>
<p>No. They thread onto your laces and are held in place once your shoes are laced up, so they stay put on long runs, muddy trails and race days.</p>
<h3>Can I order tags for my whole club?</h3>
<p>Yes. Add as many pairs as you like, each with its own names, numbers or badges. There's no minimum order.</p>
<h3>Can I change my design after adding it to the basket?</h3>
<p>Yes. Until you check out, every pair in your basket has an Amend link that reopens it in the customiser, and a Duplicate link for a second pair with small changes, which is handy for a team.</p>
<h3>What will they fit on?</h3>
<p>Any shoes with laces: running shoes, trail shoes, walking boots and everyday trainers. Each tag has a hole at each end, so the lace goes in through one and out through the other.</p>
<h3>Will the print fade?</h3>
<p>The design is printed into the tag's coating in full colour, and it's waterproof, so it's made to stay bright and easy to read through wet runs, muddy trails and long training blocks.</p>
<p>Want a ready-made design for a cause close to your heart? See our <a href="/awareness-running-tags/">awareness running tags</a>, from autism and mental health to Pride and Remembrance. Looking for somewhere to run them in? Find your nearest free, weekly 5k at <a href="https://www.parkrun.org.uk/" target="_blank" rel="noopener">parkrun</a>.</p>
HTML,
	'seo_title'       => 'Personalised Running Tags – Waterproof Shoe Tags, Pair of 2',
	'seo_description' => 'Personalised running tags for your trainers: a waterproof aluminium pair with your name, message or club badge. Made to order in 3–5 days, posted 1st class.',
	'focus_keyword'   => 'personalised running tags,running shoe tags',
	'categories'      => [ 'running-tags' ],
	'image_alt'       => 'Personalised Running Tags – a pair of waterproof aluminium tags for running shoe laces',
	'eyebrow'         => 'THE CUSTOMISER',
	'subtitle'        => 'Your name, your message, your club badge — on a waterproof pair of tags for your laces. Made to order in 3-5 days.',
	'price_suffix'    => 'Per pair · inc. VAT',
	'at_a_glance'     => $glance,
];

// ── The awareness tags, A to Z ──────────────────────────────────────────

$products[] = $awareness( [
	'old'             => [ 'alzheimers-awareness-running-tag' ],
	'name'            => "Alzheimer's Awareness Running Tags",
	'slug'            => 'alzheimers-awareness-running-tags',
	'kw'              => "Alzheimer's awareness running tags",
	'keywords'        => "alzheimer's awareness running tags,alzheimer's awareness gifts",
	'order'           => 101,
	'seo_title'       => "Alzheimer's Awareness Running Tags – Waterproof Pair of 2",
	'seo_description' => "Alzheimer's awareness running tags: a waterproof aluminium pair for your laces, personalised with a name or message. Made in 3–5 days, posted 1st class.",
	'subtitle'        => "Run for someone you love. A waterproof pair of Alzheimer's awareness tags for your laces, with your own words.",
	'intro'           => <<<'HTML'
<p>Wear your support on every run with <strong>Alzheimer's awareness running tags</strong>, a pair of waterproof aluminium tags that thread onto your laces. Whether you're running for someone you love, training for a fundraising walk or simply want people talking about Alzheimer's, these tags carry the message with you.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear Alzheimer's awareness running tags?</h2>
<p>Alzheimer's disease is the most common cause of dementia, and it touches millions of families. It changes memory, thinking and everyday life, slowly and differently for every person, and the people caring for them carry a great deal too. Talking about it openly helps people get support sooner and makes it easier for families to ask for help.</p>
<p>September is World Alzheimer's Month, with World Alzheimer's Day on 21 September, and runners and walkers raise money and awareness all through the year. A tag on your laces is a quiet reminder of the person you're running for, and an easy way to start a conversation at the start line.</p>
<p>If you're taking part in a sponsored walk or run, add the name of the person you're doing it for, or the date of the event, and the tags become a keepsake long after the finish line. They're a small way to keep someone's story going, one step at a time.</p>
HTML,
	'find_out'        => '<p>For information and support, visit <a href="https://www.alzheimers.org.uk/" target="_blank" rel="noopener">Alzheimer\'s Society</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'autism-awareness-running-tags' ],
	'name'            => 'Autism Awareness Running Tags',
	'slug'            => 'autism-awareness-running-tags',
	'kw'              => 'autism awareness running tags',
	'keywords'        => 'autism awareness running tags,autism awareness gifts',
	'order'           => 102,
	'seo_title'       => 'Autism Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Autism awareness running tags: a waterproof aluminium pair for your laces, personalised with your own words or club badge. Made in 3–5 days, posted 1st class.',
	'subtitle'        => 'Celebrate autistic people and the difference understanding makes. A waterproof pair of tags for your laces, with your own words.',
	'intro'           => <<<'HTML'
<p><strong>Autism awareness running tags</strong> are a simple way to celebrate autistic people and the difference that understanding makes. This pair of waterproof aluminium tags threads onto your laces and goes wherever you run, from the local park to race day.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear autism awareness running tags?</h2>
<p>Autism is a lifelong difference in how people communicate, experience the world and connect with others. Every autistic person is different, and what helps most is understanding from family, friends, schools, workplaces and sports clubs. Many people now talk about autism acceptance as well as awareness: seeing autistic people as they are, and making room for them.</p>
<p>World Autism Acceptance Week runs around World Autism Awareness Day on 2 April each year, and plenty of runners choose events then to fundraise. Whether you're running for your child, a brother or sister, a friend or yourself, the tags say it without a word.</p>
<p>A pair with a child's name on is a lovely way to bring them along in spirit, and a short message such as "different, not less" starts conversations wherever you run. If your club or school is fundraising, matching pairs for the whole team show you're in it together.</p>
HTML,
	'find_out'        => '<p>To learn more about autism, visit the <a href="https://www.autism.org.uk/" target="_blank" rel="noopener">National Autistic Society</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'baby-loss-awareness-running-tag' ],
	'name'            => 'Baby Loss Awareness Running Tags',
	'slug'            => 'baby-loss-awareness-running-tags',
	'kw'              => 'baby loss awareness running tags',
	'keywords'        => 'baby loss awareness running tags,baby loss remembrance gifts',
	'order'           => 103,
	'seo_title'       => 'Baby Loss Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Baby loss awareness running tags: a waterproof aluminium pair for your laces, personalised with a name or date. Made in 3–5 days and posted 1st class.',
	'subtitle'        => 'Always loved, never forgotten. A waterproof pair of tags for your laces, with a name or a date that matters to you.',
	'armband'         => [ 'baby-loss-awareness-week-captain-armband', 'Baby Loss Awareness Week captain\'s armband' ],
	'intro'           => <<<'HTML'
<p><strong>Baby loss awareness running tags</strong> are a gentle way to remember a baby who is always loved and never forgotten. This pair of waterproof aluminium tags threads onto your laces, so their name or a special date can come with you on every run.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear baby loss awareness running tags?</h2>
<p>Losing a baby during pregnancy or soon after birth is a loss that too many families still feel they can't talk about. Wearing a sign of it on a run, a walk or a fundraising race tells other parents they aren't alone, and gives everyone a way to say their baby's name.</p>
<p>Baby Loss Awareness Week takes place every year from 9 to 15 October, ending with the Wave of Light, when people light a candle at 7pm to remember every baby who has died. Many parents and families take on runs and walks in their baby's memory, and these tags are made with that in mind.</p>
<p>You might add your baby's name, initials or a special date, or order a pair for each parent so you both carry the same words. However you choose to personalise them, they're made with care, to be worn on the runs and walks that matter most to you.</p>
HTML,
	'find_out'        => '<p>If you need support, <a href="https://www.sands.org.uk/" target="_blank" rel="noopener">Sands</a> is there for anyone affected by the death of a baby.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'cancer-awareness-running-tag' ],
	'name'            => 'Cancer Awareness Running Tags',
	'slug'            => 'cancer-awareness-running-tags',
	'kw'              => 'cancer awareness running tags',
	'keywords'        => 'cancer awareness running tags,running for cancer',
	'order'           => 104,
	'seo_title'       => 'Cancer Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Cancer awareness running tags: a waterproof aluminium pair for your laces, personalised with a name or message for the people you run for. Made in 3–5 days.',
	'subtitle'        => 'Run for the people you love. A waterproof pair of cancer awareness tags for your laces, with a name or message.',
	'armband'         => [ 'world-cancer-day-captains-armband', 'World Cancer Day captain\'s armband' ],
	'intro'           => <<<'HTML'
<p>Run for the people you love with <strong>cancer awareness running tags</strong>, a pair of waterproof aluminium tags that thread onto your laces and stay there through every training run and race.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear cancer awareness running tags?</h2>
<p>Cancer Research UK estimates that 1 in 2 people in the UK will get cancer in their lifetime, so almost everyone runs for someone. Whether you're fundraising, marking a loved one's treatment, celebrating someone in remission or remembering someone you've lost, a tag on your laces keeps them with you.</p>
<p>World Cancer Day falls on 4 February, and every year thousands of runners take on races and challenges to raise money for cancer research and care. Add a name or a message and your tags become part of that story, from the first training run to the finish line.</p>
<p>If you're running as a team, matching pairs for everyone show you're in it together, and you can add each runner's name or the name of the person you're all running for. They also make a meaningful gift for someone starting their own fundraising challenge.</p>
HTML,
	'find_out'        => '<p>Find out more about cancer, and how to support research, at <a href="https://www.cancerresearchuk.org/" target="_blank" rel="noopener">Cancer Research UK</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'dementia-awareness-running-tag' ],
	'name'            => 'Dementia Awareness Running Tags',
	'slug'            => 'dementia-awareness-running-tags',
	'kw'              => 'dementia awareness running tags',
	'keywords'        => 'dementia awareness running tags,dementia awareness gifts',
	'order'           => 105,
	'seo_title'       => 'Dementia Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Dementia awareness running tags: a waterproof aluminium pair for your laces, personalised with a name, date or message. Made in 3–5 days, posted 1st class.',
	'subtitle'        => 'Carry someone with you on every run. A waterproof pair of dementia awareness tags for your laces, with your own words.',
	'intro'           => <<<'HTML'
<p><strong>Dementia awareness running tags</strong> help you carry someone with you on every run. This pair of waterproof aluminium tags threads onto your laces and can be personalised with a name, a date or a message.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear dementia awareness running tags?</h2>
<p>Dementia isn't one illness. It's the name for a group of conditions that affect memory, thinking, mood and communication, and that get worse over time. It affects the person living with it and everyone around them, especially the family members and friends who care for them.</p>
<p>Runners and walkers raise money for dementia charities all year round, and Dementia Action Week each May is a chance to get more people talking. Specialist dementia nurses, research and practical support all depend on that money and that conversation, and a tag on your laces is an easy way to start it.</p>
<p>You might add the name of a parent or grandparent, the date of your sponsored walk, or a few words they always used to say. Small details like that turn a pair of tags into something you'll keep, and something that reminds people why you're running.</p>
HTML,
	'find_out'        => '<p>For advice and support, visit <a href="https://www.dementiauk.org/" target="_blank" rel="noopener">Dementia UK</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'heart-disease-awareness-running-tag' ],
	'name'            => 'Heart Disease Awareness Running Tags',
	'slug'            => 'heart-disease-awareness-running-tags',
	'kw'              => 'heart disease awareness running tags',
	'keywords'        => 'heart disease awareness running tags,heart health gifts',
	'order'           => 106,
	'seo_title'       => 'Heart Disease Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Heart disease awareness running tags: a waterproof aluminium pair for your laces, personalised with a name or message. Made in 3–5 days, posted 1st class.',
	'subtitle'        => 'Keep heart health front of mind on every run. A waterproof pair of tags for your laces, with your own words.',
	'intro'           => <<<'HTML'
<p>Keep heart health front of mind with <strong>heart disease awareness running tags</strong>, a pair of waterproof aluminium tags that thread onto your laces and go with you on every run.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear heart disease awareness running tags?</h2>
<p>Heart and circulatory diseases affect millions of people in the UK, and many families have a story of a heart attack, a stroke or a long illness. Running for a heart charity, or in memory of someone, is one of the most common reasons people sign up for their first race.</p>
<p>World Heart Day is on 29 September each year. It's a good moment to talk about heart health, check in with the people you love and learn CPR, a skill that could save a life at a race or on your local running route.</p>
<p>If you're running in memory of someone, or for someone recovering from heart surgery, add their name or a date to make the tags your own. A matching pair for everyone in a fundraising team is a simple way to show you're running together.</p>
HTML,
	'find_out'        => '<p>Find out more about heart and circulatory diseases, and free CPR training, at the <a href="https://www.bhf.org.uk/" target="_blank" rel="noopener">British Heart Foundation</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'lgbtq-awareness-running-tag' ],
	'name'            => 'LGBTQ+ Awareness Running Tags',
	'slug'            => 'lgbtq-awareness-running-tags',
	'kw'              => 'LGBTQ+ awareness running tags',
	'keywords'        => 'lgbtq+ awareness running tags,lgbtq gifts for runners',
	'order'           => 107,
	'seo_title'       => 'LGBTQ+ Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'LGBTQ+ awareness running tags: a waterproof aluminium pair for your laces, personalised with your own words. Show your support on every run. Made in 3–5 days.',
	'subtitle'        => 'Sport is for everyone. A waterproof pair of LGBTQ+ awareness tags for your laces, with your own words.',
	'armband'         => [ 'lgbt-captain-armband', 'LGBT captain\'s armband' ],
	'intro'           => <<<'HTML'
<p>Show everyone they belong with <strong>LGBTQ+ awareness running tags</strong>, a pair of waterproof aluminium tags that thread onto your laces and carry your support on every run.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear LGBTQ+ awareness running tags?</h2>
<p>Sport should be for everyone, but many LGBTQ+ people still don't feel safe or welcome in it. Visible support from teammates, clubs and fellow runners makes a real difference, because it tells people they belong. Stonewall's Rainbow Laces campaign was built on exactly that idea, and a tag on your laces carries the same message.</p>
<p>LGBT+ History Month is in February and Pride Month is in June, and running clubs and Pride runs around the country celebrate both. Wear your tags at Pride, on your club's training night or every single day.</p>
<p>Captains, coaches and club members can all wear them to show their club is a welcoming place. Add your club badge or a few words of your own, and make sure everyone you run with knows they're welcome as they are.</p>
HTML,
	'find_out'        => '<p>Learn more about LGBTQ+ inclusion in sport from <a href="https://www.stonewall.org.uk/" target="_blank" rel="noopener">Stonewall</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'mental-health-awareness-running-tag' ],
	'name'            => 'Mental Health Awareness Running Tags',
	'slug'            => 'mental-health-awareness-running-tags',
	'kw'              => 'mental health awareness running tags',
	'keywords'        => 'mental health awareness running tags,mental health awareness gifts',
	'order'           => 108,
	'seo_title'       => 'Mental Health Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Mental health awareness running tags: a waterproof aluminium pair for your laces, with your own words. A reminder that it\'s OK not to be OK. Made in 3–5 days.',
	'subtitle'        => 'It\'s OK not to be OK. A waterproof pair of mental health awareness tags for your laces, with your own words.',
	'armband'         => [ 'mental-health-awareness-captain-armband', 'Mental Health Awareness captain\'s armband' ],
	'intro'           => <<<'HTML'
<p><strong>Mental health awareness running tags</strong> are for everyone who runs for their head as much as their legs. This pair of waterproof aluminium tags threads onto your laces, a small, visible reminder that it's OK not to be OK.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear mental health awareness running tags?</h2>
<p>Mind says that 1 in 4 people in England will experience a mental health problem of some kind each year. Talking about it openly is one of the most powerful things we can do: it helps people ask for help sooner and reminds them they're not alone.</p>
<p>Mental Health Awareness Week takes place in May and World Mental Health Day on 10 October, and running clubs everywhere use them to check in with each other. Wear your tags on group runs and solo miles alike. They're an easy way to start a conversation that matters.</p>
<p>Some runners add a few words that help them on a hard day, while others add the name of a friend they're running for. Running clubs can order matching pairs for a whole group, a simple sign that everyone there is someone you can talk to.</p>
HTML,
	'find_out'        => '<p>If you need support, or want to support someone else, visit <a href="https://www.mind.org.uk/" target="_blank" rel="noopener">Mind</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'ms-awareness-running-tag' ],
	'name'            => 'MS Awareness Running Tags',
	'slug'            => 'ms-awareness-running-tags',
	'kw'              => 'MS awareness running tags',
	'keywords'        => 'ms awareness running tags,multiple sclerosis awareness gifts',
	'order'           => 109,
	'seo_title'       => 'MS Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'MS awareness running tags: a waterproof aluminium pair for your laces, personalised with a name or message. Support everyone living with MS. Made in 3–5 days.',
	'subtitle'        => 'Support everyone living with multiple sclerosis. A waterproof pair of tags for your laces, with your own words.',
	'armband'         => [ 'ms-awareness-captain-armband', 'MS Awareness captain\'s armband' ],
	'intro'           => <<<'HTML'
<p>Show your support for everyone living with multiple sclerosis with <strong>MS awareness running tags</strong>, a pair of waterproof aluminium tags that thread onto your laces and come with you on every run.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear MS awareness running tags?</h2>
<p>Multiple sclerosis is a lifelong condition that affects the brain and spinal cord. Its symptoms, from fatigue to problems with balance, sight and movement, vary from person to person and from day to day, and many of them can't be seen. Raising awareness helps people understand what living with MS is really like.</p>
<p>World MS Day is on 30 May, and the UK's MS Awareness Week usually falls in late April. Running for someone with MS, or running with MS yourself, is something to be proud of, and a tag on your laces shares that with everyone you pass.</p>
<p>Add the name of someone you're running for, the date of a fundraising event or a few words of encouragement for the miles ahead. A pair also makes a thoughtful gift for a friend or relative with MS who loves to run, walk or stay active in their own way.</p>
HTML,
	'find_out'        => '<p>Find information and support from the <a href="https://www.mssociety.org.uk/" target="_blank" rel="noopener">MS Society</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'pride-heart-awareness-running-tag' ],
	'name'            => 'Pride Heart Awareness Running Tags',
	'slug'            => 'pride-heart-awareness-running-tags',
	'kw'              => 'Pride Heart awareness running tags',
	'keywords'        => 'pride heart awareness running tags,pride running tags',
	'order'           => 110,
	'seo_title'       => 'Pride Heart Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Pride Heart awareness running tags: a waterproof aluminium pair for your laces, personalised with your words. Wear your pride on every run. Made in 3–5 days.',
	'subtitle'        => 'Wear your heart on your laces. A waterproof pair of Pride tags for every run, with your own words.',
	'armband'         => [ 'pride-captains-armband', 'Pride captain\'s armband' ],
	'intro'           => <<<'HTML'
<p>Wear your heart on your laces with <strong>Pride Heart awareness running tags</strong>, a pair of waterproof aluminium tags that celebrate love, pride and belonging on every run.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear Pride Heart awareness running tags?</h2>
<p>Pride began as a protest and has grown into a celebration of LGBTQ+ people everywhere, and it still matters. A heart on your laces says you're an ally, a friend or part of the community yourself, and that everyone is welcome to run alongside you.</p>
<p>Pride Month is every June, with Pride events, Pride runs and club celebrations up and down the country all summer. These tags are just as at home on a Tuesday-night club run as they are at a Pride parade.</p>
<p>Add your club badge to show your team is a welcoming one, or a few words of your own for a Pride run. Matching pairs for friends or club members look great in a group photo and say, together, that everyone belongs.</p>
HTML,
	'find_out'        => '<p>Find out how to support LGBTQ+ people in sport with <a href="https://www.stonewall.org.uk/" target="_blank" rel="noopener">Stonewall</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'pride-love-awareness-running-tag' ],
	'name'            => 'Pride Love Awareness Running Tags',
	'slug'            => 'pride-love-awareness-running-tags',
	'kw'              => 'Pride Love awareness running tags',
	'keywords'        => 'pride love awareness running tags,love is love running tags',
	'order'           => 111,
	'seo_title'       => 'Pride Love Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Pride Love awareness running tags: a waterproof aluminium pair for your laces that says love is love on every run. Personalised, made in 3–5 days.',
	'subtitle'        => 'Love is love. A waterproof pair of Pride tags for your laces, with your own words.',
	'armband'         => [ 'custom-pride-football-armbands-love-is-love-captain-armband', 'Love is Love captain\'s armband' ],
	'intro'           => <<<'HTML'
<p>Love is love, and <strong>Pride Love awareness running tags</strong> say it on every run. This pair of waterproof aluminium tags threads onto your laces and celebrates the freedom to love whoever you love.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear Pride Love awareness running tags?</h2>
<p>Being able to love openly, without fear, is something many LGBTQ+ people still can't take for granted. Wearing a message of love is a small act of allyship that people notice, at the start line, in the changing room and in every race photo.</p>
<p>Pride Month in June is the obvious time to wear them, but they're made for every day of the year: training runs, races, walks with friends, or a gift for someone who deserves to feel seen.</p>
<p>You might add a name, an anniversary or a few words of your own to make them personal, or keep the design exactly as it is. They make a thoughtful gift for a partner, a friend or anyone who runs with pride.</p>
HTML,
	'find_out'        => '<p>Learn more about supporting LGBTQ+ people from <a href="https://www.stonewall.org.uk/" target="_blank" rel="noopener">Stonewall</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'racism-awareness-running-tag' ],
	'name'            => 'Racism Awareness Running Tags',
	'slug'            => 'racism-awareness-running-tags',
	'kw'              => 'racism awareness running tags',
	'keywords'        => 'racism awareness running tags,anti-racism running tags',
	'order'           => 112,
	'seo_title'       => 'Racism Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Racism awareness running tags: a waterproof aluminium pair for your laces that says there\'s no place for racism in sport. Personalised, made in 3–5 days.',
	'subtitle'        => 'No place for racism, in sport or anywhere. A waterproof pair of tags for your laces, with your own words.',
	'armband'         => [ 'no-room-for-racism-captain-armband', 'No Room for Racism captain\'s armband' ],
	'intro'           => <<<'HTML'
<p>Take a stand on every run with <strong>racism awareness running tags</strong>, a pair of waterproof aluminium tags that thread onto your laces and say, simply, that there's no place for racism in sport or anywhere else.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear racism awareness running tags?</h2>
<p>Racism still happens, at grassroots games, at the top level, online and in everyday life. Standing against it means saying so out loud, and showing it. Teams, clubs and athletes across the UK have made anti-racism part of who they are, and a tag on your laces lets you do the same.</p>
<p>Black History Month in October is a time to celebrate the people who shaped sport and society, and many schools and clubs mark it with anti-racism events. Wear your tags then, and all year round.</p>
<p>Clubs and teams can order matching pairs for every member, with the club badge on each, so the whole squad shows where it stands. Add your own words, such as "equality" or "respect", and keep the message moving every time you run.</p>
HTML,
	'find_out'        => '<p>Find anti-racism education and resources from <a href="https://www.theredcard.org/" target="_blank" rel="noopener">Show Racism the Red Card</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'remembrance-day-awareness-running-tag' ],
	'name'            => 'Remembrance Day Awareness Running Tags',
	'slug'            => 'remembrance-day-awareness-running-tags',
	'kw'              => 'Remembrance Day awareness running tags',
	'keywords'        => 'remembrance day awareness running tags,remembrance running tags',
	'order'           => 113,
	'seo_title'       => 'Remembrance Day Awareness Running Tags – Pair of 2',
	'seo_description' => 'Remembrance Day awareness running tags: a waterproof aluminium pair for your laces, personalised with a name or message. Lest we forget. Made in 3–5 days.',
	'subtitle'        => 'We will remember them. A waterproof pair of Remembrance tags for your laces, with a name or message.',
	'armband'         => [ 'remembrance-day-armband', 'Remembrance Day armband' ],
	'intro'           => <<<'HTML'
<p><strong>Remembrance Day awareness running tags</strong> let you carry a moment of remembrance with you on every run. This pair of waterproof aluminium tags threads onto your laces, a quiet and lasting way to say we will remember them.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear Remembrance Day awareness running tags?</h2>
<p>On 11 November we remember the moment the guns fell silent at the end of the First World War, and everyone who has served and sacrificed since. Remembrance Sunday falls on the second Sunday of November, and a two-minute silence is held across the country at 11am.</p>
<p>Many clubs and runners mark Remembrance with special runs, and a tag on your laces is a respectful way to join them, perhaps with the name of a relative who served. Lest we forget.</p>
<p>You might add the name, rank or regiment of a family member, or simply the words "Lest we forget". Clubs that hold a Remembrance run can order matching pairs for every runner, a respectful way to remember together.</p>
HTML,
	'find_out'        => '<p>Find out more about Remembrance from the <a href="https://www.britishlegion.org.uk/" target="_blank" rel="noopener">Royal British Legion</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'stroke-awareness-running-tag' ],
	'name'            => 'Stroke Awareness Running Tags',
	'slug'            => 'stroke-awareness-running-tags',
	'kw'              => 'stroke awareness running tags',
	'keywords'        => 'stroke awareness running tags,stroke awareness gifts',
	'order'           => 114,
	'seo_title'       => 'Stroke Awareness Running Tags – Waterproof Pair of 2',
	'seo_description' => 'Stroke awareness running tags: a waterproof aluminium pair for your laces, personalised with a name or message. Help spread the FAST message. Made in 3–5 days.',
	'subtitle'        => 'Spot it fast, act FAST. A waterproof pair of stroke awareness tags for your laces, with your own words.',
	'intro'           => <<<'HTML'
<p>Run for everyone affected by stroke with <strong>stroke awareness running tags</strong>, a pair of waterproof aluminium tags that thread onto your laces and help keep an important message moving.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear stroke awareness running tags?</h2>
<p>A stroke can happen to anyone, at any age, and spotting it fast saves lives. Remember FAST: Face, has their face fallen on one side? Arms, can they raise both arms and keep them there? Speech, is their speech slurred? Time to call 999 if you see any of these signs.</p>
<p>World Stroke Day is on 29 October, and runners and walkers raise money all year round for stroke research and recovery support. Whether you're running for a survivor, in memory of someone or simply to spread the FAST message, your tags help people remember it.</p>
<p>If you're running for someone recovering from a stroke, add their name or a few words of encouragement. Every tag that gets someone asking about FAST is one more person who knows what to look out for, and what to do next.</p>
HTML,
	'find_out'        => '<p>Find out more about stroke and recovery support from the <a href="https://www.stroke.org.uk/" target="_blank" rel="noopener">Stroke Association</a>.</p>',
] );

$products[] = $awareness( [
	'old'             => [ 'suicide-prevention-awareness-running-tag' ],
	'name'            => 'Suicide Prevention Awareness Running Tags',
	'slug'            => 'suicide-prevention-awareness-running-tags',
	'kw'              => 'suicide prevention awareness running tags',
	'keywords'        => 'suicide prevention awareness running tags,suicide prevention awareness gifts',
	'order'           => 115,
	'seo_title'       => 'Suicide Prevention Awareness Running Tags – Pair of 2',
	'seo_description' => 'Suicide prevention awareness running tags: a waterproof aluminium pair for your laces with a simple message, talking saves lives. Made in 3–5 days.',
	'subtitle'        => 'Talking saves lives. A waterproof pair of tags for your laces, with your own words.',
	'intro'           => <<<'HTML'
<p><strong>Suicide prevention awareness running tags</strong> carry a simple message: talking saves lives. This pair of waterproof aluminium tags threads onto your laces and goes with you on every run, as a reminder to check in on the people around you.</p>
HTML,
	'why'             => <<<'HTML'
<h2>Why wear suicide prevention awareness running tags?</h2>
<p>Suicide can be prevented, and one of the most powerful things any of us can do is ask someone how they're really doing, and listen. Many runners know how much a run with a friend can help, and running clubs have become places where people talk openly about how they feel.</p>
<p>World Suicide Prevention Day is on 10 September each year. Wear your tags in memory of someone, to support a fundraising run, or simply to show that you're someone people can talk to.</p>
<p>You might add the name of someone you're running for, or a few words such as "you are not alone". A pair also makes a thoughtful gift for a friend who has been there for you, or for someone who needs to know you're there for them.</p>
HTML,
	'find_out'        => '<p>If you\'re struggling, you don\'t have to cope alone. You can call <a href="https://www.samaritans.org/" target="_blank" rel="noopener">Samaritans</a> free, day or night, on 116 123.</p>',
] );

return [
	'name'       => 'Running tags',
	'summary'    => 'Personalised Running Tags and the 15 awareness tags: names, web addresses, descriptions, Rank Math, categories, alt text and the BEspoke Page fields. The awareness tags leave the shop for the Awareness Running Tags page, after Personalised Running Tags.',
	'categories' => [
		[
			'slug'        => 'running-tags',
			'name'        => 'Running Tags',
			'description' => 'Personalised running tags for your trainers: waterproof aluminium pairs with your name, message and club badge.',
		],
		[
			'slug'        => 'awareness-running-tags',
			'name'        => 'Awareness Running Tags',
			'parent'      => 'running-tags',
			'description' => 'Running tags for the causes close to your heart, from autism and mental health to Pride and Remembrance.',
		],
	],
	'products'   => $products,
	'pages'      => [
		[
			'slug'            => 'awareness-running-tags',
			'title'           => 'Awareness Running Tags (page)',
			'seo_title'       => 'Awareness Running Tags – Personalised Tags for 15 Causes',
			'seo_description' => 'Awareness running tags for 15 causes, from autism and mental health to Pride and Remembrance. Waterproof aluminium pairs, personalised and made in 3–5 days.',
			'focus_keyword'   => 'awareness running tags,charity running tags',
			// The page's own words, for Elementor — shown on the SEO Packs
			// screen with a Copy button each, in the order the page uses them.
			'elementor'       => [
				[ 'Main heading (H1)', 'Awareness Running Tags' ],
				[ 'Intro', "Our awareness running tags put the causes closest to your heart on your laces. Each pair is two waterproof aluminium tags, printed in full colour with a design made for its cause, and ready for your own words: a name, a date, a message or your club badge.\n\nWhether you're running for someone you love, raising money for a charity or simply want to start a conversation at the start line, there's a pair here for you. Every pair is made to order in 3–5 working days and posted 1st class." ],
				[ 'Second heading (H2)', 'Choose your cause. Run with purpose.' ],
				[ 'Button', 'Design your own personalised running tags from scratch – click here' ],
				[ 'Button link', '/product/personalised-running-tags/' ],
				[ 'Questions heading (H2)', 'Awareness running tags: your questions' ],
				[ 'Question 1 (H3)', 'Are the tags waterproof?' ],
				[ 'Answer 1', 'Yes. Every tag is aluminium, printed in full colour and fully waterproof, so rain, mud and puddles won\'t touch the print.' ],
				[ 'Question 2 (H3)', 'What size are they?' ],
				[ 'Answer 2', 'Each tag is 45 × 12mm, and they come as a pair: one for each shoe. They thread straight onto your laces.' ],
				[ 'Question 3 (H3)', 'Can I add a name or my club badge?' ],
				[ 'Answer 3', 'Yes. Change the wording, add a name, a date or a message, and add up to two badges on each tag. The two tags can match, or each can say something different.' ],
				[ 'Question 4 (H3)', 'Can I wear them for football or rugby?' ],
				[ 'Answer 4', 'No. They\'re made for running, walking, the gym and everyday trainers. Football, rugby and other team sports don\'t allow metal on footwear.' ],
				[ 'Question 5 (H3)', 'How long do they take?' ],
				[ 'Answer 5', 'Every pair is made to order in 3–5 working days and posted 1st class.' ],
			],
		],
	],
];
