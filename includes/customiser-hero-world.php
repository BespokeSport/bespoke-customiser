<?php
/**
 * BEspoke Sport – Scroll-driven "World" hero
 *
 * A pinned hero where a 3D-rendered world (products staged on a floodlit
 * pitch) rotates as the visitor scrolls. It pauses at each product with an
 * info card, then turns 90° to the next. Four products, three turns.
 *
 * Usage — drop this shortcode into an Elementor HTML/Shortcode widget at the
 * top of the homepage:
 *
 *     [bespoke_hero_world]
 *
 * Optional attributes:
 *     scroll="1000"   height of the scroll journey in vh (default 1000).
 *                     Higher = gentler rotation per wheel-click.
 *     eyebrow="…"     small mint line above each headline.
 *
 * ── How it works ──────────────────────────────────────────────────────────
 * The animation is 91 pre-rendered AVIF frames in assets/hero-world/, NOT a
 * video. Video was tried first and rejected: browsers seek video by keyframe,
 * so scrubbing arrived in ~6-frame lumps, and H.264 softened the night sky
 * and grass. Real frames scrub exactly and keep the render quality. AVIF
 * keeps the whole sequence to ~4.5MB (the same frames as WebP were ~21MB).
 *
 * Frames load by priority — frame 0 first (~190KB, so something is on screen
 * almost immediately), then the other three product stops, then the rest
 * working outward from each stop, because those are where the rotation
 * decelerates and the eye lingers. Until a frame has arrived the canvas shows
 * the nearest one that has, so the hero is usable while still streaming.
 *
 * ── Replacing the artwork ─────────────────────────────────────────────────
 * Drop a new numbered sequence into assets/hero-world/ as f0000.avif …
 * f0090.avif. If the frame COUNT changes, update BESPOKE_HERO_FRAMES below
 * and the stop positions in $stops.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-hero-world.php
 * Included by:   bespoke-customiser.php
 */

defined( 'ABSPATH' ) || exit;

/** Total frames in assets/hero-world/ (f0000 … f0090). */
define( 'BESPOKE_HERO_FRAMES', 91 );

/**
 * The four products the world stops at, in rotation order.
 *
 * 'frame' must match the frame where that product faces the camera — the
 * world turns 90° between each, and the sequence was rendered at 30 frames
 * per 90°. Keep these in step with the render or the cards will announce the
 * wrong product.
 */
function bespoke_hero_world_products() {
    return [
        [
            'frame'   => 0,
            'title'   => 'Lead in <span>your</span> colours.',
            'body'    => "Captain's armbands in your club's colours, with your badge and your letter. Single or double-sided.",
            'price'   => '£10',
            'cta'     => 'Customise armbands',
            'url'     => '/product/personalised-captains-armbands/',
        ],
        [
            'frame'   => 30,
            'title'   => 'Tackle in <span>style</span>.',
            'body'    => 'Fully personalised shin pads — your badge, your name, your number. Made in the UK, built to last.',
            'price'   => '£20',
            'cta'     => 'Customise shin pads',
            'url'     => '/product/personalised-shin-pads/',
        ],
        [
            'frame'   => 60,
            'title'   => 'Grip. <span>Comfort.</span> Colour.',
            'body'    => "Anti-slip grip socks carrying your club badge and squad number, in your team's colours.",
            'price'   => '£10',
            'cta'     => 'Customise grip socks',
            'url'     => '/product/personalised-grip-socks-bespoke-sports/',
        ],
        [
            'frame'   => 90,
            'title'   => 'Reward the <span>moment</span>.',
            'body'    => 'Man &amp; Girl of the Match trophies, personalised for your club — so every performance gets remembered.',
            'price'   => '£20',
            'cta'     => 'Customise trophies',
            'url'     => '/product/personalised-man-of-the-match-trophy-traditional-football-award/',
        ],
    ];
}

add_shortcode( 'bespoke_hero_world', 'bespoke_hero_world_shortcode' );

function bespoke_hero_world_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'scroll'  => '1000',
        'eyebrow' => 'BE:UNIQUE — BE:CREATIVE',
        // Visible height of the pinned window, in vh. The 16:9 frames are
        // CROPPED to this (never squashed), so a letterbox costs nothing and
        // needs no re-render. 100 = full screen.
        'height'  => '92',
        // Which slice of the frame survives that crop: 0 = keep the very top,
        // 50 = centre, 100 = keep the very bottom. Below 50 protects the sky
        // and floodlights, which is what the composition needs.
        //
        // 92 / 27 were dialled in against the real homepage using
        // FRAMING-TOOL.html (in the World Animation folder) — a slight
        // letterbox so the next section peeks above the fold, biased upward
        // so the tops of the floodlights aren't clipped.
        'focus'   => '27',
    ], $atts, 'bespoke_hero_world' );

    $scroll   = max( 300, (int) $atts['scroll'] );
    $height   = min( 100, max( 40, (int) $atts['height'] ) );
    $focus    = min( 100, max( 0, (int) $atts['focus'] ) ) / 100;
    $products = bespoke_hero_world_products();
    $base     = BESPOKE_PLUGIN_URL . 'assets/hero-world/';
    $stops    = wp_list_pluck( $products, 'frame' );

    // Poster = the first stop frame. Also the no-JS / no-AVIF fallback, so
    // the hero is never a blank box.
    $poster = $base . 'f' . str_pad( (string) $stops[0], 4, '0', STR_PAD_LEFT ) . '.avif';

    // Unique id per instance. The script finds its own section by id rather
    // than by walking siblings — wpautop / Elementor can inject markup
    // between the two, which would break a sibling lookup.
    static $seq = 0;
    $uid = 'bs-world-' . ( ++$seq ) . '-' . wp_rand( 1000, 9999 );

    ob_start();
    ?>
    <section class="bs-world" id="<?php echo esc_attr( $uid ); ?>"
             style="--bs-world-scroll: <?php echo esc_attr( $scroll ); ?>vh; --bs-world-vh: <?php echo esc_attr( $height ); ?>vh;">
      <div class="bs-world-sticky">
        <canvas class="bs-world-canvas" aria-hidden="true"></canvas>

        <?php /* Static fallback: shown when JS is off or AVIF is unsupported. */ ?>
        <noscript>
          <img class="bs-world-poster" src="<?php echo esc_url( $poster ); ?>"
               alt="BEspoke personalised football products on a floodlit pitch">
        </noscript>

        <div class="bs-world-veil"></div>

        <div class="bs-world-copy">
          <div class="bs-world-eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></div>
          <?php foreach ( $products as $i => $p ) : ?>
            <div class="bs-world-card" data-card="<?php echo (int) $i; ?>">
              <h2><?php echo wp_kses( $p['title'], [ 'span' => [], 'br' => [] ] ); ?></h2>
              <p><?php echo wp_kses_post( $p['body'] ); ?></p>
              <div class="bs-world-price">FROM <b><?php echo esc_html( $p['price'] ); ?></b></div>
              <a class="bs-world-btn" href="<?php echo esc_url( $p['url'] ); ?>">
                <?php echo esc_html( $p['cta'] ); ?>
              </a>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="bs-world-dots">
          <?php foreach ( $products as $i => $p ) : ?>
            <span class="bs-world-dot" data-dot="<?php echo (int) $i; ?>"></span>
          <?php endforeach; ?>
        </div>

        <div class="bs-world-hint">Scroll</div>
        <div class="bs-world-meter"></div>
      </div>
    </section>

    <script>
    (function(){
      var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
      if (!root) return;

      var N     = <?php echo (int) BESPOKE_HERO_FRAMES; ?>;
      var BASE  = <?php echo wp_json_encode( $base ); ?>;
      var STOPS = <?php echo wp_json_encode( array_values( $stops ) ); ?>;

      // Respect a visitor's "reduce motion" setting — hold the first stop
      // frame and let all the cards stack as plain content instead.
      var reduce = window.matchMedia &&
                   window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      var cv     = root.querySelector('.bs-world-canvas');
      var ctx    = cv.getContext('2d', { alpha:false });
      var sticky = root.querySelector('.bs-world-sticky');
      var FOCUS  = <?php echo wp_json_encode( $focus ); ?>;
      var cards = [].slice.call(root.querySelectorAll('.bs-world-card'));
      var dots  = [].slice.call(root.querySelectorAll('.bs-world-dot'));
      var meter = root.querySelector('.bs-world-meter');
      var hint  = root.querySelector('.bs-world-hint');

      var imgs = new Array(N), ok = new Array(N), want = 0, shown = -1;
      var pad = function(n){ n = String(n); while (n.length < 4) n = '0' + n; return n; };
      var url = function(i){ return BASE + 'f' + pad(i) + '.avif'; };

      /* ---- Build the hold/turn timeline from the stop frames --------------
         Each product gets a HOLD (frame frozen, its card up) and each gap a
         TURN. Holds take slightly more of the scroll than turns so the copy
         has time to be read. */
      var SEG = [], HOLD = 1.25, TURN = 1.6, units = [];
      for (var s = 0; s < STOPS.length; s++){
        units.push({ hold:true, i:s });
        if (s < STOPS.length - 1) units.push({ hold:false, i:s });
      }
      var totalW = 0;
      units.forEach(function(u){ totalW += u.hold ? HOLD : TURN; });
      var acc = 0;
      units.forEach(function(u){
        var w = (u.hold ? HOLD : TURN) / totalW;
        SEG.push({
          t0: acc, t1: acc + w,
          f0: u.hold ? STOPS[u.i] : STOPS[u.i],
          f1: u.hold ? STOPS[u.i] : STOPS[u.i + 1],
          card: u.hold ? u.i : -1
        });
        acc += w;
      });

      function resolve(p){
        for (var i = 0; i < SEG.length; i++){
          var s = SEG[i];
          if (p >= s.t0 && p <= s.t1){
            var k = (p - s.t0) / ((s.t1 - s.t0) || 1);
            return { frame: Math.round(s.f0 + (s.f1 - s.f0) * k), card: s.card };
          }
        }
        return { frame: STOPS[STOPS.length-1], card: STOPS.length-1 };
      }

      /* ---- Priority loading: stops first, then outward from each stop ---- */
      function order(){
        var seen = {}, list = [];
        function push(i){ if (i>=0 && i<N && !seen[i]){ seen[i]=1; list.push(i); } }
        STOPS.forEach(push);
        for (var d=1; d<=Math.ceil(N/STOPS.length); d++)
          STOPS.forEach(function(s){ push(s-d); push(s+d); });
        for (var i=0;i<N;i++) push(i);
        return list;
      }
      var queue = reduce ? STOPS.slice(0,1) : order(), flight = 0;
      function pump(){
        while (flight < 6 && queue.length){
          (function(i){
            flight++;
            var im = new Image();
            im.decoding = 'async';
            im.onload  = function(){ imgs[i]=im; ok[i]=1; flight--; draw(); pump(); };
            im.onerror = function(){ flight--; pump(); };
            im.src = url(i);
          })(queue.shift());
        }
      }

      function nearest(i){
        if (ok[i]) return i;
        for (var d=1; d<N; d++){ if (ok[i-d]) return i-d; if (ok[i+d]) return i+d; }
        return -1;
      }
      function fit(){
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        // Size the canvas to the PINNED WINDOW, not the viewport — the two
        // differ whenever `height` is less than 100vh (the letterbox).
        cv.width  = Math.round(sticky.clientWidth  * dpr);
        cv.height = Math.round(sticky.clientHeight * dpr);
        shown = -1; draw();
      }
      function draw(){
        var i = nearest(want);
        if (i < 0 || i === shown) return;
        var im = imgs[i]; if (!im) return;
        shown = i;
        var cw=cv.width, ch=cv.height,
            ir=im.naturalWidth/im.naturalHeight, cr=cw/ch, dw,dh,dx,dy;
        // Cover-fit: fill the window, crop the overflow, never distort.
        // FOCUS decides which slice survives a letterbox crop.
        if (cr > ir){ dw=cw; dh=cw/ir; dx=0; dy=(ch-dh)*FOCUS; }
        else        { dh=ch; dw=ch*ir; dy=0; dx=(cw-dw)/2; }
        ctx.drawImage(im,dx,dy,dw,dh);
      }
      function onScroll(){
        var r = root.getBoundingClientRect();
        var total = root.offsetHeight - sticky.offsetHeight;
        var p = Math.max(0, Math.min(1, -r.top / (total || 1)));
        var res = resolve(p);
        want = res.frame; draw();
        for (var i=0;i<cards.length;i++){
          cards[i].classList.toggle('on', i === res.card);
          if (dots[i]) dots[i].classList.toggle('on', i === res.card);
        }
        meter.style.width = (p*100) + '%';
        hint.classList.toggle('gone', p > 0.03);
      }

      if (reduce){
        root.classList.add('bs-world-static');
        queue = [ STOPS[0] ];
        pump();
        fit();
        cards.forEach(function(c){ c.classList.add('on'); });
        return;
      }

      fit(); pump(); onScroll();
      window.addEventListener('scroll', function(){ requestAnimationFrame(onScroll); }, { passive:true });
      window.addEventListener('resize', fit);
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Front-end styles. Enqueued only when the shortcode is actually on the page
 * so no other page pays for it.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular() && ! is_front_page() ) return;
    global $post;
    $has = ( $post instanceof WP_Post ) && has_shortcode( (string) $post->post_content, 'bespoke_hero_world' );
    // Elementor keeps its content in meta rather than post_content, so also
    // allow the front page through — the CSS is small and scoped.
    if ( ! $has && ! is_front_page() ) return;

    wp_enqueue_style(
        'bespoke-hero-world',
        BESPOKE_PLUGIN_URL . 'assets/bespoke-hero-world.css',
        [],
        @filemtime( BESPOKE_PLUGIN_DIR . 'assets/bespoke-hero-world.css' ) ?: '1'
    );
} );
