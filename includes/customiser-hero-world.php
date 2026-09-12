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
 *     height / focus / veil — see the notes in bespoke_hero_world_shortcode().
 *
 * ── How it works ──────────────────────────────────────────────────────────
 * The animation is pre-rendered AVIF frames, NOT a video. Video was tried
 * first and rejected: browsers seek video by keyframe, so scrubbing arrived
 * in ~6-frame lumps, and H.264 softened the night sky and grass. Real frames
 * scrub exactly and keep the render quality. AVIF keeps a whole sequence to
 * ~5MB (the same frames as WebP were ~21MB).
 *
 * There are TWO renders of the same world and the script picks one by the
 * screen's orientation (swapping if a phone is turned round):
 *
 *   assets/hero-world/    widescreen 1920×1080, 181 frames (f0000 … f0180),
 *                         stops at 0 / 30 / 60 / 90 — landscape screens.
 *   assets/hero-world-m/  portrait 1080×1920, 181 frames (f0000 … f0180),
 *                         stops at 0 / 30 / 60 / 89 — portrait screens
 *                         (phones, tablets held upright). This render came
 *                         out of C4D one frame shorter, hence 89: that is 3°
 *                         short of square-on, which is invisible. Its stop
 *                         frames are encoded at full size and full colour
 *                         resolution; the in-between frames are smaller and
 *                         more compressed, since they are only ever seen in
 *                         motion. The canvas is capped to these native sizes
 *                         — see fit() — so rendering never upscales them.
 *
 * The portrait set is only offered when its frames are actually on the
 * server, so a phone falls back to the widescreen render (cover-cropped)
 * rather than a blank canvas if that folder hasn't been uploaded yet.
 *
 * Frames load by priority — the first stop first (so something is on screen
 * almost immediately), then the other three product stops, then the rest
 * working outward from each stop, because those are where the rotation
 * decelerates and the eye lingers. Until a frame has arrived the canvas shows
 * the nearest one that has, so the hero is usable while still streaming.
 *
 * ── Replacing the artwork ─────────────────────────────────────────────────
 * Drop a new numbered sequence into the relevant folder as f0000.avif … If a
 * frame COUNT changes, update BESPOKE_HERO_FRAMES / BESPOKE_HERO_FRAMES_M
 * below and the 'frame' / 'mframe' stop positions in
 * bespoke_hero_world_products().
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-hero-world.php
 * Included by:   bespoke-customiser.php
 */

defined( 'ABSPATH' ) || exit;

/** Total frames in assets/hero-world/ (f0000 … f0180) — the widescreen render, 60 frames per 90° turn. */
define( 'BESPOKE_HERO_FRAMES', 181 );

/** Total frames in assets/hero-world-m/ (f0000 … f0180) — the portrait render, 60 frames per 90° turn. */
define( 'BESPOKE_HERO_FRAMES_M', 181 );

/**
 * The four products the world stops at, in rotation order.
 *
 * 'frame' must match the frame where that product faces the camera in the
 * widescreen render, 'mframe' the same in the portrait render — the world
 * turns 90° between each, and both are rendered at 60 frames per 90°. Keep
 * these in step with the renders or the cards will announce the wrong
 * product.
 */
function bespoke_hero_world_products() {
    return [
        [
            'frame'   => 0,
            'mframe'  => 0,
            'title'   => 'Lead in <span>your</span> colours.',
            'body'    => "Captain's armbands in your club's colours, with your badge and your letter. Single or double-sided.",
            'price'   => '£10',
            'cta'     => 'Customise armbands',
            'url'     => '/product/personalised-captains-armbands/',
        ],
        [
            'frame'   => 60,
            'mframe'  => 60,
            'title'   => 'Tackle in <span>style</span>.',
            'body'    => 'Fully personalised shin pads — your badge, your name, your number. Made in the UK, built to last.',
            'price'   => '£20',
            'cta'     => 'Customise shin pads',
            'url'     => '/product/personalised-shin-pads/',
        ],
        [
            'frame'   => 120,
            'mframe'  => 120,
            'title'   => 'Grip. <span>Comfort.</span> Colour.',
            'body'    => "Anti-slip grip socks carrying your club badge and squad number, in your team's colours.",
            'price'   => '£10',
            'cta'     => 'Customise grip socks',
            'url'     => '/product/personalised-grip-socks-bespoke-sports/',
        ],
        [
            'frame'   => 180,
            'mframe'  => 180,
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
        /* Length of the scroll journey, in vh. This is the ONLY thing that
           decides how far the world turns per wheel click, and therefore
           whether the rotation feels smooth or jumpy.

           On a 900px window. The world turns 270° in all, and how much of that
           one wheel click moves depends on this number alone:

             scroll   turn per wheel click   frames per click (60fps render)
               1000          6.5°                   4.3
               1720          3.6°                   2.4
               2200          2.8°                   1.9

           (Recalculated 12 Sep 2026 when FIRST_HOLD grew: the turns now take
           4.8 of 9.4 weight rather than 4.8 of 8.73, so the same feel needs a
           longer scroll. 1720 gives what 1600 used to.)

           Frame COUNT is a different lever. On 10 Sep 2026 the renders went from
           30 to 60 frames per 90° turn. That halves the smallest possible step,
           which smooths a slow trackpad drift or a finger dragging on a phone —
           but it does NOT change how far the world turns per wheel click. Only
           this number does. (This table used to quote scroll-per-frame, a figure
           that quietly halved when the frame count doubled. Degrees per click is
           the one that describes the feel.)

           Raised from 1000 to 1600 on 6 Sep 2026: Nick reported "I scroll
           down a tiny bit and we jump lots of frames". */
        'scroll'  => '1720',
        'eyebrow' => 'BE:UNIQUE — BE:CREATIVE',
        // Visible height of the pinned window, in vh. The frames are CROPPED
        // to this (never squashed), so a letterbox costs nothing and needs no
        // re-render. 100 = full screen. On portrait screens the CSS reads the
        // same number in svh, so the phone's URL bar doesn't hide the copy.
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
        // Strength of the dark wash that sits behind the copy so white type
        // stays readable. 1 = as designed, 0.5 = half, 0 = off. It always
        // clears before the products — see the CSS. Turn it down if the
        // render can carry the type on its own.
        'veil'    => '1',
        // Scroll length, in vh, for a visitor who has skipped the intro
        // before. The journey still plays — it just passes in a fraction
        // of the scrolling, so a returning customer is not made to wind
        // through the whole thing again. Set equal to `scroll` to turn the
        // shortcut off.
        // 260 was far too aggressive: it squeezed the SAME animation into a
        // quarter of the scroll, so a returning visitor got roughly ten
        // frames per wheel click — much jumpier than the hero it was meant
        // to be a convenience for. Now a little over a third of the full
        // journey, which is still noticeably quicker without being a blur.
        'returnscroll' => '600',
    ], $atts, 'bespoke_hero_world' );

    $scroll   = max( 300, (int) $atts['scroll'] );
    $height   = min( 100, max( 40, (int) $atts['height'] ) );
    $focus    = min( 100, max( 0, (int) $atts['focus'] ) ) / 100;
    $veil     = min( 1, max( 0, (float) $atts['veil'] ) );
    $returns  = max( 150, min( $scroll, (int) $atts['returnscroll'] ) );
    $products = bespoke_hero_world_products();
    $base     = BESPOKE_PLUGIN_URL . 'assets/hero-world/';
    $stops    = wp_list_pluck( $products, 'frame' );

    // Portrait render — offered to the script only when its frames are on
    // the server, so phones degrade to the widescreen set rather than a
    // blank canvas if the folder hasn't been uploaded yet.
    $base_m   = BESPOKE_PLUGIN_URL . 'assets/hero-world-m/';
    $has_m    = file_exists( BESPOKE_PLUGIN_DIR . 'assets/hero-world-m/f0000.avif' );
    $stops_m  = wp_list_pluck( $products, 'mframe' );

    /* Version the frame URLs by when the sequence was last replaced.

       Every render reuses the same filenames, so without this a returning
       visitor's browser keeps serving the OLD frames it already has cached
       and only fetches the ones it has never seen — a jumble of two renders
       with different timing. Found when the 30fps sequence (f0000–f0090)
       was replaced by a 60fps one (f0000–f0180) on 10 Sep 2026. f0000 is
       rewritten by every upload, so its modified time changes by itself and
       there is nothing to remember next time. */
    $ver      = (string) ( @filemtime( BESPOKE_PLUGIN_DIR . 'assets/hero-world/f0000.avif' )   ?: BESPOKE_HERO_FRAMES );
    $ver_m    = (string) ( @filemtime( BESPOKE_PLUGIN_DIR . 'assets/hero-world-m/f0000.avif' ) ?: BESPOKE_HERO_FRAMES_M );

    // Poster = the first stop frame. Also the no-JS / no-AVIF fallback, so
    // the hero is never a blank box.
    $poster   = $base   . 'f' . str_pad( (string) $stops[0],   4, '0', STR_PAD_LEFT ) . '.avif?v=' . $ver;
    $poster_m = $base_m . 'f' . str_pad( (string) $stops_m[0], 4, '0', STR_PAD_LEFT ) . '.avif?v=' . $ver_m;

    // Unique id per instance. The script finds its own section by id rather
    // than by walking siblings — wpautop / Elementor can inject markup
    // between the two, which would break a sibling lookup.
    static $seq = 0;
    $uid = 'bs-world-' . ( ++$seq ) . '-' . wp_rand( 1000, 9999 );

    ob_start();
    ?>
    <section class="bs-world" id="<?php echo esc_attr( $uid ); ?>"
             style="--bs-world-scroll: <?php echo esc_attr( $scroll ); ?>vh; --bs-world-vh: <?php echo esc_attr( $height ); ?>vh; --bs-world-vhn: <?php echo esc_attr( $height ); ?>; --bs-world-veil: <?php echo esc_attr( $veil ); ?>;">
      <div class="bs-world-sticky">
        <canvas class="bs-world-canvas" aria-hidden="true"></canvas>

        <?php /* Static fallback: shown when JS is off or AVIF is unsupported. */ ?>
        <noscript>
          <picture>
            <?php if ( $has_m ) : ?>
              <source media="(orientation: portrait)" srcset="<?php echo esc_url( $poster_m ); ?>" type="image/avif">
            <?php endif; ?>
            <img class="bs-world-poster" src="<?php echo esc_url( $poster ); ?>"
                 alt="BEspoke personalised football products on a floodlit pitch">
          </picture>
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

        <?php /* Skip lives top-right, where an intro-skip is expected. It
               is a real button so it is keyboard reachable; the dots and
               the progress meter are decorative and are not. */ ?>
        <button type="button" class="bs-world-skip">Skip intro</button>
        <?php /* The scroll cue: a scrim over the whole hero with a mouse and
               chevrons in the middle, fading out as the journey starts.

               It began as a small grey word bottom-centre. Handed the phone,
               a 14-year-old tried to TAP THE DOTS - scrolling never occurred
               to him - and a brighter corner label still was not enough. A
               first-time visitor has to be told, plainly, in the middle of the
               screen.

               Inline SVG, not a GIF: nothing extra to upload, sharp at any
               pixel density, and the chevrons animate independently. */ ?>
        <div class="bs-world-hint" aria-hidden="true">
          <?php /* Nick's own animation. A GIF cannot be stopped, so a still of
                 its first frame stands in for anyone who asks for reduced
                 motion; the CSS shows one or the other. */ ?>
          <img class="bs-world-hint__icon bs-world-hint__icon--moving" alt=""
               src="<?php echo esc_url( BESPOKE_PLUGIN_URL . 'assets/bespoke-scroll-cue.gif' ); ?>"
               width="198" height="500">
          <img class="bs-world-hint__icon bs-world-hint__icon--still" alt=""
               src="<?php echo esc_url( BESPOKE_PLUGIN_URL . 'assets/bespoke-scroll-cue.png' ); ?>"
               width="198" height="500">
        </div>
        <div class="bs-world-meter"></div>
      </div>
    </section>

    <script>
    (function(){
      var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
      if (!root) return;

      /* The two renders. `tall` is null when the portrait frames aren't on
         the server yet — then every screen gets the widescreen set. */
      var SETS = {
        wide: { base:  <?php echo wp_json_encode( $base ); ?>,
                n:     <?php echo (int) BESPOKE_HERO_FRAMES; ?>,
                w: 1920, h: 1080, v: <?php echo wp_json_encode( $ver ); ?>,
                stops: <?php echo wp_json_encode( array_values( $stops ) ); ?> },
        tall: <?php echo $has_m
            ? '{ base: '   . wp_json_encode( $base_m )
            . ', n: '      . (int) BESPOKE_HERO_FRAMES_M
            . ', w: 1080, h: 1920, v: ' . wp_json_encode( $ver_m )
            . ', stops: '  . wp_json_encode( array_values( $stops_m ) ) . ' }'
            : 'null'; ?>
      };
      var portraitMQ = window.matchMedia ? window.matchMedia('(orientation: portrait)') : null;
      function pick(){ return (SETS.tall && portraitMQ && portraitMQ.matches) ? SETS.tall : SETS.wide; }

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

      /* Per-set state, (re)filled by useSet(). `gen` stamps every image
         request so a load that lands after an orientation swap can't write
         into the new set's slots. */
      var BASE, N, STOPS, SEG, imgs, ok, queue, gen = 0, flight = 0, want = 0, shown = -1;
      var NATW = 0, NATH = 0;   // the active render's native pixel size
      var VQ = '';              // the active set's ?v= cache-buster (see $ver)
      var pad = function(n){ n = String(n); while (n.length < 4) n = '0' + n; return n; };
      var url = function(i){ return BASE + 'f' + pad(i) + '.avif' + VQ; };

      /* ---- Build the hold/turn timeline from the stop frames --------------
         Each product gets a HOLD (frame frozen, its card up) and each gap a
         TURN. Holds take slightly more of the scroll than turns so the copy
         has time to be read.

         FIRST_HOLD used to be 0.18 — about 2% of the journey — on the
         reasoning that the opening frame had already been sitting there
         being read, so holding it again was just scrolling with nothing
         happening.

         The scroll cue overturned that. The copy is dimmed behind a scrim
         until you scroll, so the first thing a scroll did was fade the cue
         and take the headline away in the same movement — you never got a
         clean look at it. At 0.85 the opening card holds for about 9% of the
         journey and the cue is gone by 3.5%, leaving a clear stretch to read
         it before the world turns. */
      var HOLD = 1.25, FIRST_HOLD = 0.85, TURN = 1.6;
      function timeline(stops){
        var units = [], seg = [], totalW = 0, acc = 0;
        for (var s = 0; s < stops.length; s++){
          units.push({ hold:true, i:s, first:(s === 0) });
          if (s < stops.length - 1) units.push({ hold:false, i:s });
        }
        function weight(u){ return u.hold ? (u.first ? FIRST_HOLD : HOLD) : TURN; }
        units.forEach(function(u){ totalW += weight(u); });
        units.forEach(function(u){
          var w = weight(u) / totalW;
          seg.push({
            t0: acc, t1: acc + w,
            f0: stops[u.i],
            f1: u.hold ? stops[u.i] : stops[u.i + 1],
            card: u.hold ? u.i : -1
          });
          acc += w;
        });
        return seg;
      }

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
      function pump(){
        var g = gen;
        while (flight < 6 && queue.length){
          (function(i){
            flight++;
            var im = new Image();
            im.decoding = 'async';
            im.onload  = function(){ if (g !== gen) return; imgs[i]=im; ok[i]=1; flight--; draw(); pump(); };
            im.onerror = function(){ if (g !== gen) return; flight--; pump(); };
            im.src = url(i);
          })(queue.shift());
        }
      }

      /* Switch to a render set (on load, and again if the phone is turned):
         fresh frame slots, a timeline built from that set's stops, and the
         download queue restarted from its stop frames. */
      function useSet(set){
        gen++;
        BASE = set.base; N = set.n; STOPS = set.stops;
        NATW = set.w || 0; NATH = set.h || 0;
        VQ   = set.v ? '?v=' + encodeURIComponent(set.v) : '';
        SEG  = timeline(STOPS);
        imgs = new Array(N); ok = new Array(N);
        queue = reduce ? STOPS.slice(0,1) : order();
        flight = 0; shown = -1;
        pump();
      }

      function nearest(i){
        if (ok[i]) return i;
        for (var d=1; d<N; d++){ if (ok[i-d]) return i-d; if (ok[i+d]) return i+d; }
        return -1;
      }
      function fit(){
        // Tell the CSS how wide the vertical scrollbar is, so the full-bleed
        // width can exclude it (100vw includes it — see the stylesheet).
        root.style.setProperty('--bs-world-sbw',
          Math.max(0, window.innerWidth - document.documentElement.clientWidth) + 'px');

        /* Render at the SCREEN's real pixel density, not a flat 2x.
           Phones are commonly 2.6x-3x; drawing at 2x and letting the browser
           stretch the result was throwing away roughly a third of the detail
           the frames actually carry, which read as a soft, slightly zoomed
           picture even though the source was sharp. */
        var dpr = Math.min(window.devicePixelRatio || 1, 3);

        // Size the canvas to the PINNED WINDOW, not the viewport — the two
        // differ whenever `height` is less than 100vh (the letterbox).
        var cw = sticky.clientWidth * dpr, ch = sticky.clientHeight * dpr;

        /* Never allocate more canvas than the frames can fill. The image is
           cover-fitted, so it gets scaled by max(cw/NATW, ch/NATH); if that
           is above 1 the extra canvas pixels are pure upscale — wasted memory
           on a 4K monitor, and no sharper. Shrinking the canvas to match
           looks identical (the browser does the same stretch, once) and keeps
           a 3x cap safe on big screens. Floored at CSS size so it can never
           end up below 1x. */
        if (NATW && NATH){
          var over = Math.max(cw / NATW, ch / NATH);
          if (over > 1){
            cw = Math.max(sticky.clientWidth,  cw / over);
            ch = Math.max(sticky.clientHeight, ch / over);
          }
        }

        cv.width  = Math.round(cw);
        cv.height = Math.round(ch);
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
        var travel = root.offsetHeight - sticky.offsetHeight;

        /* Anything above the hero — typically the site header sitting in
           normal flow — has to scroll away before the sticky pins. Waiting
           for that felt like a dead patch at the very start, so we fold that
           run-up INTO the timeline: the world begins turning on the first
           pixel of scroll, while the header is still sliding away.
           Capped, so a hero placed further down a page behaves normally. */
        var absTop = r.top + window.pageYOffset;
        var pre    = Math.min(absTop, 400);
        var startY = absTop - pre;

        var p = Math.max(0, Math.min(1,
                  (window.pageYOffset - startY) / ((travel + pre) || 1)));
        var res = resolve(p);
        want = res.frame; draw();
        for (var i=0;i<cards.length;i++){
          cards[i].classList.toggle('on', i === res.card);
          if (dots[i]) dots[i].classList.toggle('on', i === res.card);
        }
        meter.style.width = (p*100) + '%';
        /* Lift the cue AS the journey starts rather than snapping it off at a
           threshold: full strength at rest, gone by 3.5% of the scroll (about
           five wheel clicks). Driven per frame, so it tracks a finger drag. */
        var _hf = Math.max(0, Math.min(1, 1 - (p / 0.035)));
        hint.style.opacity = _hf;
        hint.classList.toggle('gone', _hf === 0);
      }

      useSet(pick());

      if (reduce){
        root.classList.add('bs-world-static');
        var _sk = root.querySelector('.bs-world-skip');
        if (_sk) _sk.style.display = 'none';
        fit();
        cards.forEach(function(c){ c.classList.add('on'); });
        return;
      }

      /* ---- Skip intro --------------------------------------------------
         Jumps past the hero to whatever follows it. The choice is
         remembered, and on a later visit the journey is shortened to
         `returnscroll` rather than skipped outright — the world still
         turns, it just does not ask for the same wind-through twice.
         Deliberately NOT an auto-scroll on load: moving the page under
         someone before they touch it is disorienting and breaks the back
         button. localStorage is wrapped because a private window throws
         on access rather than returning null. */
      var SKIP_KEY = 'bsWorldSkipped';
      function skippedBefore(){
        try { return localStorage.getItem(SKIP_KEY) === '1'; } catch(e){ return false; }
      }
      function rememberSkip(){
        try { localStorage.setItem(SKIP_KEY, '1'); } catch(e){}
      }

      var skipBtn = root.querySelector('.bs-world-skip');
      if (skipBtn){
        skipBtn.addEventListener('click', function(){
          rememberSkip();
          var below = root.offsetTop + root.offsetHeight;
          try { window.scrollTo({ top: below, behavior: 'smooth' }); }
          catch(e){ window.scrollTo(0, below); }
        });
      }

      /* A returning skipper gets the short journey. Applied before the
         first onScroll() so the timeline is measured against it. */
      if (skippedBefore()){
        root.style.setProperty('--bs-world-scroll', <?php echo wp_json_encode( $returns . 'vh' ); ?>);
        root.classList.add('bs-world-quick');
      }

      fit(); onScroll();
      window.addEventListener('scroll', function(){ requestAnimationFrame(onScroll); }, { passive:true });
      window.addEventListener('resize', fit);

      /* Phone turned round: swap to the render that fits the new shape. */
      if (SETS.tall && portraitMQ){
        var flip = function(){ useSet(pick()); fit(); onScroll(); };
        if (portraitMQ.addEventListener) portraitMQ.addEventListener('change', flip);
        else if (portraitMQ.addListener) portraitMQ.addListener(flip);
      }
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
