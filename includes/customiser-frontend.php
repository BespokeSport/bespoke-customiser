<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register the [bespoke_customiser] shortcode
 *
 * Usage examples:
 *   [bespoke_customiser product_id="1276" product_type="shinpads"]
 *   [bespoke_customiser product_id="1305" product_type="gripsocks"]
 *   [bespoke_customiser product_id="1312" product_type="armbands"]
 *
 * product_type must match one of the keys in bespoke_get_product_types()
 * in customiser-designs.php.
 */
add_shortcode( 'bespoke_customiser', 'bespoke_render_customiser' );

function bespoke_render_customiser( $atts ) {
    $atts = shortcode_atts( [
        'product_id'   => '1276',     // Default: Personalised Shin Pads
        'product_type' => 'shinpads', // Default product type for design filtering
    ], $atts );

    $product_id   = intval( $atts['product_id'] );
    $product_type = sanitize_key( $atts['product_type'] );

    // Validate product_type against the registered list
    $valid_types = array_keys( bespoke_get_product_types() );
    if ( ! in_array( $product_type, $valid_types, true ) ) {
        $product_type = 'shinpads'; // Fall back safely
    }

    $product = wc_get_product( $product_id );

    if ( ! $product ) {
        return '<p>Product not found.</p>';
    }

    $price    = $product->get_price();
    $ajax_url = admin_url( 'admin-ajax.php' );
    $nonce    = wp_create_nonce( 'bespoke_add_to_cart' );

    ob_start();
    ?>
    <div id="bespoke-customiser-root"
         data-product-id="<?php echo esc_attr( $product_id ); ?>"
         data-product-type="<?php echo esc_attr( $product_type ); ?>"
         data-price="<?php echo esc_attr( number_format( $price, 2 ) ); ?>"
         data-ajax-url="<?php echo esc_attr( $ajax_url ); ?>"
         data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <?php
        // The customiser HTML is loaded from the compiled asset file.
        // This keeps the shortcode PHP clean and the HTML maintainable.
        $customiser_file = BESPOKE_PLUGIN_DIR . 'assets/customiser.html';
        if ( file_exists( $customiser_file ) ) {
            $raw = file_get_contents( $customiser_file );

            // Pull <style> blocks out of <head> — they contain all the core CSS
            // and would be lost if we only extracted the <body>.
            $head_styles = '';
            if ( preg_match( '/<head[^>]*>(.*?)<\/head>/si', $raw, $head_match ) ) {
                preg_match_all( '/<style[^>]*>.*?<\/style>/si', $head_match[1], $style_matches );
                if ( ! empty( $style_matches[0] ) ) {
                    $head_styles = implode( "\n", $style_matches[0] );
                }
            }

            // Extract the body content
            preg_match( '/<body[^>]*>(.*)<\/body>/si', $raw, $matches );
            $body_content = isset( $matches[1] ) ? $matches[1] : $raw;

            echo $head_styles . $body_content;
        } else {
            echo '<p style="color:red;">Customiser asset not found. Please ensure customiser.html is in the plugin assets folder.</p>';
        }
        ?>

    </div>

    <script>
    // Pass WordPress data into the customiser JavaScript
    window.BespokeConfig = {
        productId:   <?php echo intval( $product_id ); ?>,
        productType: '<?php echo esc_js( $product_type ); ?>',
        price:       '£<?php echo esc_js( number_format( $price, 2 ) ); ?>',
        ajaxUrl:     '<?php echo esc_js( $ajax_url ); ?>',
        nonce:       '<?php echo esc_js( $nonce ); ?>',
        uploadUrl:   '<?php echo esc_js( BESPOKE_PLUGIN_URL . 'includes/customiser-ajax.php' ); ?>'
    };
    </script>

    <?php
    // ─── Custom fonts integration ──────────────────────────────────────────
    // If any fonts have been uploaded via the "Customiser Fonts" admin page,
    // (1) emit @font-face declarations referencing the uploaded files,
    // (2) expose them as window.BESPOKE_CUSTOM_FONTS, and
    // (3) replace the customiser's hardcoded FONTS array + rebuild the picker.
    $custom_fonts = function_exists( 'bespoke_get_custom_fonts' ) ? bespoke_get_custom_fonts() : [];
    if ( ! empty( $custom_fonts ) ) :
        // Build a JS-safe array with stable family names
        $js_fonts = [];
        $css_face = '';
        foreach ( $custom_fonts as $idx => $f ) {
            $ext    = strtolower( pathinfo( $f['filename'], PATHINFO_EXTENSION ) );
            $format = $ext === 'otf' ? 'opentype' : 'truetype';
            $family = 'bespoke-font-' . intval( $idx );
            // font-weight: 100 900 tells the browser this single file covers
            // every requested weight (otherwise SVG text at font-weight:900
            // falls back to a system font on Chrome/Safari).
            $css_face .= "@font-face{font-family:'" . esc_attr( $family ) . "';src:url('" . esc_url( $f['url'] ) . "') format('" . $format . "');font-weight:100 900;font-style:normal;font-display:swap;}\n";
            $js_fonts[] = [
                'id'      => $family,
                'label'   => $f['name'],
                'family'  => "'" . $family . "', sans-serif",
                'preview' => 'SMITH',
            ];
        }
        ?>
        <style id="bespoke-custom-font-faces">
            <?php echo $css_face; ?>
        </style>
        <script>
        window.BESPOKE_CUSTOM_FONTS = <?php echo wp_json_encode( $js_fonts ); ?>;

        (function(){
            // Wait until the customiser has populated window.FONTS, then replace it
            // with the uploaded fonts and rebuild the .font-card grid in the picker.
            function rebuildPicker() {
                if (!window.FONTS || !document.querySelector('.font-card')) {
                    return setTimeout(rebuildPicker, 200);
                }
                // Replace FONTS contents (mutate in place so internal references update)
                window.FONTS.length = 0;
                window.BESPOKE_CUSTOM_FONTS.forEach(function(f){ window.FONTS.push(f); });

                // Rebuild every .font-card grid on the page (Name + Number tabs each have one)
                var grids = new Set();
                document.querySelectorAll('.font-card').forEach(function(c){ grids.add(c.parentElement); });

                grids.forEach(function(grid){
                    // Detect which target this grid is for (name vs num) from existing cards
                    var firstCard = grid.querySelector('.font-card');
                    var isNum = grid.closest('[data-target=num], .step-num, #step-num') || (firstCard && firstCard.getAttribute('data-target') === 'num');
                    var target = isNum ? 'num' : 'name';
                    var currentFamily = window.S ? (target === 'num' ? window.S.numFontFamily : window.S.nameFontFamily) : null;

                    grid.innerHTML = '';
                    window.FONTS.forEach(function(f){
                        var card = document.createElement('div');
                        card.className = 'font-card' + (currentFamily === f.family ? ' sel' : '');
                        card.setAttribute('data-target', target);
                        card.innerHTML = '<div class="font-preview" style="font-family:' + f.family + '">' + (f.preview || 'SMITH') + '</div><div class="font-name">' + f.label + '</div>';
                        card.addEventListener('click', function(){
                            grid.querySelectorAll('.font-card').forEach(function(c){ c.classList.remove('sel'); });
                            card.classList.add('sel');
                            if (window.S) {
                                if (target === 'num') window.S.numFontFamily = f.family;
                                else window.S.nameFontFamily = f.family;
                            }
                            // Trigger SVG re-render (function name varies; try common ones)
                            if (typeof window.makeSVG === 'function') window.makeSVG();
                            if (typeof window.render === 'function') window.render();
                            if (typeof window.updatePreview === 'function') window.updatePreview();
                        });
                        grid.appendChild(card);
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', rebuildPicker);
            } else {
                rebuildPicker();
            }
        })();
        </script>
    <?php endif; ?>

    <?php /* ── Customiser runtime fixes (formerly Code Snippet #29) ────────── */ ?>
    <style id="bespoke-customiser-runtime-fixes">
        /* Page title in white so it reads on the dark customiser background */
        .entry-title { color: #ffffff !important; }

        /* Page-level: stop the homepage marquee bursting the body out to 4087px wide */
        html, body { overflow-x: hidden !important; max-width: 100vw !important; }
        #bespoke-marquee { max-width: 100vw !important; overflow-x: hidden !important; box-sizing: border-box !important; }

        /* SVG name/number text: remove the heavy dark stroke around glyphs */
        #dt-svg-wrap svg text, #bespoke-customiser-root svg text {
            stroke: none !important;
            stroke-width: 0 !important;
            paint-order: normal !important;
        }

        /* Stage label + helper text as BEspoke green pills, hint moved under label */
        #dt-label, #dt-hint {
            align-self: center !important;
            background: #5DCAA5 !important;
            color: #04342C !important;
            border-radius: 999px !important;
            font-family: 'Inter', sans-serif !important;
            position: relative !important;
            z-index: 5 !important;
        }
        #dt-label {
            order: 0 !important;
            font-size: 18px !important;
            font-weight: 600 !important;
            letter-spacing: 0.12em !important;
            text-transform: uppercase !important;
            padding: 10px 24px !important;
            margin-bottom: 6px !important;
        }
        #dt-hint {
            order: 1 !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            letter-spacing: 0.02em !important;
            padding: 6px 14px !important;
            margin-bottom: 8px !important;
        }
        #dt-svg-wrap { order: 2 !important; }

        /* Custom HSV colour picker ─────────────────────────────────────── */
        #bcp-overlay { position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: transparent !important; display: none; align-items: center !important; justify-content: center !important; z-index: 2147483647 !important; font-family: 'Inter', sans-serif; }
        #bcp-overlay.open { display: flex; }
        .bcp-panel { background: #1A1A1A; border: 1px solid #2A2A2A; border-radius: 12px; padding: 20px; width: 300px; max-width: 90vw; box-sizing: border-box; box-shadow: 0 12px 40px rgba(0,0,0,0.5); }
        .bcp-title { font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-bottom: 14px; }
        .bcp-sv { position: relative; width: 100%; height: 180px; border-radius: 8px; touch-action: none; user-select: none; cursor: crosshair; overflow: hidden; }
        .bcp-sv-cursor { position: absolute; width: 14px; height: 14px; border: 2px solid #fff; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
        .bcp-hue { position: relative; width: 100%; height: 18px; border-radius: 9px; margin-top: 14px; background: linear-gradient(to right, #f00, #ff0, #0f0, #0ff, #00f, #f0f, #f00); touch-action: none; user-select: none; cursor: pointer; }
        .bcp-hue-cursor { position: absolute; top: 50%; width: 14px; height: 14px; border: 2px solid #fff; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
        .bcp-row { display: flex; gap: 8px; margin-top: 14px; align-items: center; }
        .bcp-hex { flex: 1; background: #0E0E10; border: 1px solid #2A2A2A; border-radius: 6px; padding: 8px 10px; color: #fff; font-family: 'Inter', sans-serif; font-size: 13px; outline: none; text-transform: uppercase; letter-spacing: 0.04em; }
        .bcp-hex:focus { border-color: #5DCAA5; }
        .bcp-done { background: #5DCAA5; color: #04342C; border: none; border-radius: 6px; padding: 9px 18px; font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600; letter-spacing: 0.06em; cursor: pointer; text-transform: uppercase; }
        .bcp-done:hover { background: #4FB996; }

        /* Desktop: picker left-aligned so preview stays visible on the right */
        @media (min-width: 900px) {
            #bcp-overlay { justify-content: flex-start !important; padding-left: 60px !important; box-sizing: border-box !important; }
        }

        /* Mobile: picker as a bottom sheet so the preview above stays visible */
        @media (max-width: 899px) {
            #bcp-overlay { align-items: flex-end !important; justify-content: center !important; padding: 0 !important; }
            .bcp-panel { width: 100% !important; max-width: 100% !important; border-radius: 16px 16px 0 0 !important; padding: 16px !important; }
            .bcp-sv { height: 160px !important; }
        }
    </style>

    <script>
    /* Custom HSV colour picker (formerly Code Snippet #29) */
    (function(){
      function init(){
        if (window.__bcp_loaded) return;
        if (!document.querySelector('.ct input[type=color]')) { setTimeout(init, 200); return; }
        window.__bcp_loaded = true;

        var overlay = document.createElement('div');
        overlay.id = 'bcp-overlay';
        overlay.innerHTML = '<div class="bcp-panel"><div class="bcp-title">Choose colour</div><div class="bcp-sv" id="bcp-sv"><div class="bcp-sv-cursor" id="bcp-sv-cursor"></div></div><div class="bcp-hue" id="bcp-hue"><div class="bcp-hue-cursor" id="bcp-hue-cursor"></div></div><div class="bcp-row"><input class="bcp-hex" id="bcp-hex" type="text" maxlength="7" /><button class="bcp-done" id="bcp-done">Done</button></div></div>';
        document.body.appendChild(overlay);

        var activeInput = null, h=0, s=1, v=1;
        var $ = function(id){ return document.getElementById(id); };
        var sv = $('bcp-sv'), svC = $('bcp-sv-cursor'), hue = $('bcp-hue'), hueC = $('bcp-hue-cursor'), hex = $('bcp-hex'), done = $('bcp-done');

        function hsv2rgb(h,s,v){var c=v*s,x=c*(1-Math.abs(((h/60)%2)-1)),m=v-c;var r=0,g=0,b=0;if(h<60){r=c;g=x}else if(h<120){r=x;g=c}else if(h<180){g=c;b=x}else if(h<240){g=x;b=c}else if(h<300){r=x;b=c}else{r=c;b=x}return[Math.round((r+m)*255),Math.round((g+m)*255),Math.round((b+m)*255)]}
        function rgb2hsv(r,g,b){r/=255;g/=255;b/=255;var mx=Math.max(r,g,b),mn=Math.min(r,g,b),d=mx-mn;var H=0;if(d){if(mx===r)H=((g-b)/d)%6;else if(mx===g)H=(b-r)/d+2;else H=(r-g)/d+4;H=Math.round(H*60);if(H<0)H+=360}return[H,mx===0?0:d/mx,mx]}
        function hex2rgb(x){x=x.replace(/^#/,'');if(x.length===3)x=x[0]+x[0]+x[1]+x[1]+x[2]+x[2];return[parseInt(x.slice(0,2),16),parseInt(x.slice(2,4),16),parseInt(x.slice(4,6),16)]}
        function rgb2hex(r,g,b){return'#'+[r,g,b].map(function(n){return n.toString(16).padStart(2,'0');}).join('')}
        function paintSV(){sv.style.background='linear-gradient(to top,#000,transparent),linear-gradient(to right,#fff,hsl('+h+',100%,50%))'}
        function refresh(){paintSV();svC.style.left=(s*100)+'%';svC.style.top=((1-v)*100)+'%';hueC.style.left=(h/360*100)+'%';var rgb=hsv2rgb(h,s,v);if(document.activeElement!==hex)hex.value=rgb2hex(rgb[0],rgb[1],rgb[2]).toUpperCase();push()}

        var _pushRaf=false;
        function push(){if(!activeInput)return;if(_pushRaf)return;_pushRaf=true;requestAnimationFrame(function(){_pushRaf=false;if(!activeInput)return;var rgb=hsv2rgb(h,s,v);activeInput.value=rgb2hex(rgb[0],rgb[1],rgb[2]);activeInput.dispatchEvent(new Event('input',{bubbles:true}))})}
        function setHex(x){var rgb=hex2rgb(x);var hsv=rgb2hsv(rgb[0],rgb[1],rgb[2]);h=hsv[0];s=hsv[1];v=hsv[2];refresh()}

        function drag(el,fn){
          function down(e){
            e.preventDefault(); fn(e);
            function mv(e){fn(e)}
            function up(){document.removeEventListener('mousemove',mv);document.removeEventListener('touchmove',mv);document.removeEventListener('mouseup',up);document.removeEventListener('touchend',up)}
            document.addEventListener('mousemove',mv);
            document.addEventListener('touchmove',mv,{passive:false});
            document.addEventListener('mouseup',up);
            document.addEventListener('touchend',up);
          }
          el.addEventListener('mousedown',down);
          el.addEventListener('touchstart',down,{passive:false});
        }

        drag(sv,function(e){
          var r=sv.getBoundingClientRect(),t=e.touches?e.touches[0]:e,
            x=Math.max(0,Math.min(r.width,t.clientX-r.left)),
            y=Math.max(0,Math.min(r.height,t.clientY-r.top));
          s=x/r.width; v=1-(y/r.height);
          svC.style.left=(s*100)+'%'; svC.style.top=((1-v)*100)+'%';
          var rgb=hsv2rgb(h,s,v);
          if(document.activeElement!==hex) hex.value=rgb2hex(rgb[0],rgb[1],rgb[2]).toUpperCase();
          push();
        });
        drag(hue,function(e){
          var r=hue.getBoundingClientRect(),t=e.touches?e.touches[0]:e,
            x=Math.max(0,Math.min(r.width,t.clientX-r.left));
          h=(x/r.width)*360;
          refresh();
        });

        hex.addEventListener('input',function(e){
          var v=e.target.value.replace(/[^0-9a-fA-F]/g,'').slice(0,6);
          if(v.length===6||v.length===3){try{setHex('#'+v)}catch(_){}}
        });

        function close(){overlay.classList.remove('open');activeInput=null}
        done.addEventListener('click',close);
        overlay.addEventListener('click',function(e){if(e.target===overlay)close()});

        document.querySelectorAll('.ct input[type=color]').forEach(function(i){i.style.pointerEvents='none'});
        document.querySelectorAll('.ct').forEach(function(ct){
          function open(){
            var i=ct.querySelector('input[type=color]');
            if(!i)return;
            activeInput=i;
            setHex(i.value||'#ff0000');
            overlay.classList.add('open');
            try{overlay.scrollIntoView({block:'center',inline:'center'})}catch(_){}
            hex.value=(i.value||'#FF0000').toUpperCase();
          }
          ct.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();open()});
          ct.addEventListener('touchend',function(e){e.preventDefault();open()},{passive:false});
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
    })();
    </script>

    <?php

    return ob_get_clean();
}

/**
 * Enqueue customiser styles on pages that use the shortcode
 */
add_action( 'wp_enqueue_scripts', function() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bespoke_customiser' ) ) {
        wp_enqueue_style(
            'bespoke-customiser',
            BESPOKE_PLUGIN_URL . 'assets/customiser.css',
            [],
            BESPOKE_VERSION
        );
    }
} );
