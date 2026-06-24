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
        'product_id'   => '',  // Empty = auto-detect from current WC context
        'product_type' => '',  // Empty = auto-detect from product meta
    ], $atts );

    $product_id   = intval( $atts['product_id'] );
    $product_type = sanitize_key( $atts['product_type'] );

    // ── Auto-detect product_id from the current page if not supplied.
    // Lets the same shortcode work on EVERY WC single-product page
    // (e.g. dropped into single-product.php once) without per-product
    // configuration.
    if ( ! $product_id ) {
        global $product, $post;
        if ( $product instanceof WC_Product ) {
            $product_id = $product->get_id();
        } elseif ( $post instanceof WP_Post && $post->post_type === 'product' ) {
            $product_id = $post->ID;
        }
    }

    // ── Auto-detect product_type from the WC product's meta if not
    // supplied. The "Customiser Type" field on the product edit screen
    // writes _bespoke_product_type. Falls back to shinpads if blank.
    if ( ! $product_type && $product_id ) {
        $meta_type    = get_post_meta( $product_id, '_bespoke_product_type', true );
        $product_type = sanitize_key( $meta_type );
    }
    if ( ! $product_type ) {
        $product_type = 'shinpads';
    }

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

    // Per-product size buttons (admin "Customiser size buttons" field).
    $cust_sizes = get_post_meta( $product_id, '_bespoke_customiser_sizes', true );
    $cust_sizes = is_array( $cust_sizes ) ? array_values( $cust_sizes ) : [];

    // Variable products — pass every variation's attribute values + price
    // through to the customiser JS so the in-page preview can update the
    // headline price when the customer picks a different size / thickness
    // / Frill option. Without this the customiser shows the parent product's
    // fallback price even though the cart line will charge the variation's
    // price after add-to-cart.
    $bespoke_variations = [];
    if ( $product->is_type( 'variable' ) ) {
        foreach ( $product->get_available_variations() as $bv ) {
            $bespoke_variations[] = [
                'variation_id' => (int) $bv['variation_id'],
                'attributes'   => $bv['attributes'],
                'price'        => (float) $bv['display_price'],
                'price_html'   => wp_kses_post( $bv['price_html'] ?: '' ),
            ];
        }
    }

    ob_start();
    ?>
    <script>
    // Pass WordPress data into the customiser JavaScript. MUST be emitted
    // BEFORE the customiser.html markup/script below: that script reads
    // window.BespokeConfig at parse time (per-product placement geometry,
    // product type for the step config, admin Save-placement gating). If
    // this loaded after it, the customiser starts up blind and falls back
    // to shin-pad defaults — wrong positions, all 5 step tabs, no bar.
    window.BespokeConfig = {
        productId:   <?php echo intval( $product_id ); ?>,
        productType: '<?php echo esc_js( $product_type ); ?>',
        price:       '£<?php echo esc_js( number_format( $price, 2 ) ); ?>',
        ajaxUrl:     '<?php echo esc_js( $ajax_url ); ?>',
        nonce:       '<?php echo esc_js( $nonce ); ?>',
        uploadUrl:   '<?php echo esc_js( BESPOKE_PLUGIN_URL . 'includes/customiser-ajax.php' ); ?>',
        // Plugin assets base URL — used by the player-card renderer to
        // load the bundled country flag PNGs from /assets/flags/.
        pluginAssetsUrl: '<?php echo esc_js( BESPOKE_PLUGIN_URL . 'assets/' ); ?>',
        // Per-product size buttons (admin "Customiser size buttons" field)
        sizes:       <?php echo wp_json_encode( $cust_sizes ); ?>,
        // Per-product placement geometry + admin "Save placement" editor wiring
        geometry:        <?php echo wp_json_encode( function_exists( 'bespoke_get_product_geometry' ) ? bespoke_get_product_geometry( $product_type ) : [] ); ?>,
        canEditGeometry: <?php echo current_user_can( 'manage_options' ) ? 'true' : 'false'; ?>,
        adminAjaxUrl:    '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        geometryNonce:   '<?php echo esc_js( wp_create_nonce( 'bespoke_save_geometry' ) ); ?>',
        // Live variation lookup table — used by the JS picker to surface
        // the right price as the customer picks size + thickness etc.
        variations:      <?php echo wp_json_encode( $bespoke_variations ); ?>
    };
    </script>

    <?php
    // Preload the product background image so the preview fills in straight
    // away on a cold first visit, instead of showing a blank frame while the
    // image downloads (and the design overlay injects it asynchronously).
    $bs_preload = function_exists( 'bespoke_get_product_assets' ) ? bespoke_get_product_assets( $product_type ) : [];
    if ( ! empty( $bs_preload['background_url'] ) ) {
        echo '<link rel="preload" as="image" href="' . esc_url( $bs_preload['background_url'] ) . '" fetchpriority="high">' . "\n";
    }
    if ( ! empty( $bs_preload['pad_base_url'] ) ) {
        echo '<link rel="preload" as="image" href="' . esc_url( $bs_preload['pad_base_url'] ) . '">' . "\n";
    }
    foreach ( [ 'highlights_url', 'shadow_url', 'background_alt_url', 'mask_url', 'mask_alt_url' ] as $bs_pk ) {
        if ( ! empty( $bs_preload[ $bs_pk ] ) ) {
            echo '<link rel="preload" as="image" href="' . esc_url( $bs_preload[ $bs_pk ] ) . '">' . "\n";
        }
    }
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

    <?php
    /* ── Registered designs integration ────────────────────────────────────
     * If the admin has registered any designs via "Customiser Designs",
     * expose them to the customiser and inject them into ALL_DESIGNS so
     * they appear in the picker alongside (or in place of) the hardcoded ones.
     */
    $registered_designs = [];
    $product_assets     = function_exists( 'bespoke_get_product_assets' )
        ? bespoke_get_product_assets( $product_type )
        : [];
    if ( function_exists( 'bespoke_get_product_types' ) ) {
        $q = new WP_Query( [
            'post_type'      => 'bespoke_design',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => '_bespoke_active', 'value' => '1' ],
                [
                    'key'     => '_bespoke_products',
                    'value'   => '"' . $product_type . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'meta_key' => '_bespoke_order',
            'orderby'  => 'meta_value_num',
            'order'    => 'ASC',
        ] );
        foreach ( $q->posts as $p ) {
            $thumb_id  = get_post_meta( $p->ID, '_bespoke_thumb_id', true );
            $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
            $svg_url   = get_post_meta( $p->ID, '_bespoke_svg_url',  true );
            $layers    = get_post_meta( $p->ID, '_bespoke_layers',   true );
            $registered_designs[] = [
                'id'      => sanitize_title( $p->post_title ),
                'label'   => $p->post_title,
                'thumb'   => $thumb_url,
                'svg_url' => $svg_url,
                'layers'  => is_array( $layers ) ? array_values( $layers ) : [],
            ];
        }
    }
    if ( ! empty( $registered_designs ) || ! empty( $product_assets ) ) : ?>
        <script id="bespoke-registered-designs">
        window.BESPOKE_REGISTERED_DESIGNS = <?php echo wp_json_encode( $registered_designs ); ?>;
        window.BESPOKE_PRODUCT_ASSETS     = <?php echo wp_json_encode( $product_assets ); ?>;

        (function(){
            var NS   = 'http://www.w3.org/2000/svg';
            var REG  = window.BESPOKE_REGISTERED_DESIGNS || [];
            var PA   = window.BESPOKE_PRODUCT_ASSETS    || {};
            var svgCache = {};

            // Build a lookup table id -> design object
            var byId = {};
            REG.forEach(function(d){ byId[d.id] = d; });

            // 1. REPLACE ALL_DESIGNS with the admin-registered designs only.
            //    This strips out the hardcoded placeholder designs (Vanguard,
            //    Boom, Scratch, etc.) that were baked into customiser.html by
            //    earlier development. The picker will show only what's been
            //    added via WP admin "Customiser Designs".
            //
            //    Safety: if there are no registered designs yet, we leave the
            //    hardcoded ones alone so the picker isn't empty.
            function patchDesigns(){
                if (!window.ALL_DESIGNS) { return setTimeout(patchDesigns, 200); }
                if (REG.length === 0) return;
                window.ALL_DESIGNS.length = 0;
                REG.forEach(function(d){
                    window.ALL_DESIGNS.push({ id: d.id, label: d.label, thumb: d.thumb });
                });
                // If the customiser's default design (vanguard) is no longer in
                // the list, switch the initial selection to the first registered.
                if (window.S && !REG.some(function(d){ return d.id === window.S.design; })) {
                    window.S.design = REG[0].id;
                }
                if (typeof window.buildDesignScroll === 'function') {
                    window.buildDesignScroll();
                }
                if (typeof window.updateDesignLayers === 'function') {
                    requestAnimationFrame(window.updateDesignLayers);
                }
            }

            // ── SVG fetch helper (with caching) ───────────────────────────────
            function fetchSvgText(url){
                if (svgCache[url]) return Promise.resolve(svgCache[url]);
                return fetch(url).then(function(r){ return r.text(); }).then(function(txt){
                    svgCache[url] = txt;
                    return txt;
                });
            }

            // Returns a parsed <svg> element, or null on parse failure.
            function parseSvg(svgText){
                var doc = new DOMParser().parseFromString(svgText, 'image/svg+xml');
                if (doc.documentElement.nodeName.toLowerCase() === 'parsererror') return null;
                return doc.documentElement;
            }

            // Detect whether an SVG has embedded raster (PNG/JPG) image data.
            // Raster-based designs need mix-blend-mode tinting; vector designs
            // can be tinted by setting `fill` on the wrapper group.
            function svgHasRasterImages(parsedSvg){
                var images = parsedSvg.querySelectorAll('image');
                for (var i = 0; i < images.length; i++) {
                    var href = images[i].getAttribute('href') || images[i].getAttribute('xlink:href') || '';
                    if (href.indexOf('data:image') === 0) return true;
                }
                return false;
            }

            // Detect if a URL points to a raster image (jpg/png/gif/webp) vs SVG.
            function isRasterUrl(url){
                return /\.(jpe?g|png|gif|webp)(\?.*)?$/i.test(url || '');
            }

            // Where pads should sit on the canvas. Matches Apex's bbox
            // (115,183 → 1093,1006) which itself matches the white pad
            // silhouettes baked into shinpads-background.jpg. Defining the
            // target here means every design auto-positions to this single
            // canonical anchor, no matter where the artist drew the pad
            // shape inside their 1200x1200 source file.
            var TARGET_PAD_CENTER_X = 603.5;
            var TARGET_PAD_CENTER_Y = 593;

            // Pad-base centering offset cache, keyed by URL.
            //
            // Designers export pad-shape SVGs with the actual paths sitting
            // wherever they happened to draw them inside the 1200x1200
            // viewBox. Apex landed at bbox (115,183) — matching the silhouettes
            // in the product background; Tramline landed at (207,55) — top-
            // right corner. Auto-detecting the bbox lets us shift any new
            // design back to the canonical position so the user doesn't have
            // to police pad alignment in every source file.
            //
            // The offset is later applied via x/y attributes on each layer's
            // nested <svg> / <image> element (NOT via a transform on the
            // wrap <g> — SVG creates a fresh coordinate context at every
            // nested <svg> viewport, so wrap transforms don't propagate into
            // the SVG's content rendering).
            var _padCenterCache = {};
            function getPadCenteringOffset(padUrl){
                return new Promise(function(resolve){
                    if (!padUrl) { resolve({tx:0, ty:0}); return; }
                    if (_padCenterCache[padUrl]) { resolve(_padCenterCache[padUrl]); return; }
                    // Raster pad-bases are <image> elements that cover the
                    // full 1200x1200 viewport, so they're already aligned.
                    if (isRasterUrl(padUrl)) {
                        _padCenterCache[padUrl] = {tx:0, ty:0};
                        resolve({tx:0, ty:0});
                        return;
                    }
                    fetchSvgText(padUrl).then(function(svgText){
                        try {
                            var parsed = parseSvg(svgText);
                            if (!parsed) { resolve({tx:0, ty:0}); return; }
                            // getBBox needs the element to be attached to
                            // the document, so we mount an off-screen probe.
                            var probe = document.createElementNS(NS, 'svg');
                            probe.setAttribute('viewBox',
                                parsed.getAttribute('viewBox') || '0 0 1200 1200'
                            );
                            probe.style.position = 'absolute';
                            probe.style.visibility = 'hidden';
                            probe.style.left = '-99999px';
                            probe.style.top  = '-99999px';
                            probe.style.width  = '100px';
                            probe.style.height = '100px';
                            Array.from(parsed.children).forEach(function(child){
                                probe.appendChild(child.cloneNode(true));
                            });
                            document.body.appendChild(probe);
                            var bb = probe.getBBox();
                            document.body.removeChild(probe);
                            var off = {tx:0, ty:0};
                            if (bb && bb.width > 0 && bb.height > 0) {
                                off.tx = TARGET_PAD_CENTER_X - (bb.x + bb.width  / 2);
                                off.ty = TARGET_PAD_CENTER_Y - (bb.y + bb.height / 2);
                            }
                            _padCenterCache[padUrl] = off;
                            resolve(off);
                        } catch(_) {
                            _padCenterCache[padUrl] = {tx:0, ty:0};
                            resolve({tx:0, ty:0});
                        }
                    }).catch(function(){
                        resolve({tx:0, ty:0});
                    });
                });
            }

            // Parse a hex colour string ("#rrggbb" or "#rgb") to [r, g, b] ints,
            // or null if not a valid hex.
            function hexToRgb(hex){
                if (!hex) return null;
                hex = hex.replace(/^#/, '');
                if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
                if (!/^[0-9a-fA-F]{6}$/.test(hex)) return null;
                return [
                    parseInt(hex.slice(0, 2), 16),
                    parseInt(hex.slice(2, 4), 16),
                    parseInt(hex.slice(4, 6), 16),
                ];
            }

            // Build a layer <g> from a URL (SVG or raster image), applying a tint colour
            // or gradient.
            //   - gradient set  → render a <rect> filled with linearGradient through
            //                     the image's alpha as a mask (raster) OR set the
            //                     gradient as fill on the layer wrapper (vector).
            //   - tintColor null            → no tinting (background wallpaper)
            //   - tintColor set + raster    → flatten-matrix tint filter
            //   - tintColor set + vector    → set fill on the wrapper (cascades to paths)
            //   - tintColor set + raster-in-SVG → feColorMatrix luminance + tint
            // Returns a promise that resolves to the layer element.
            function buildLayer(url, tintColor, label, gradient){
                // PNG/JPG/GIF/WebP — wrap in an SVG <image> element (covers viewport).
                if (isRasterUrl(url)) {
                    var imgLayer = document.createElementNS(NS, 'g');
                    imgLayer.setAttribute('data-layer', label);

                    // ── Gradient branch (raster) ────────────────────────────
                    // Use the image's alpha as a mask, then fill the visible
                    // area with a vertical linear gradient. Works for ANY
                    // source PNG colour — alpha shape only is what matters.
                    if (gradient && gradient.from && gradient.to) {
                        var rndId = Math.random().toString(36).slice(2, 8);
                        var defs = document.createElementNS(NS, 'defs');

                        var maskId = 'bcp-img-mask-' + rndId;
                        var mask = document.createElementNS(NS, 'mask');
                        mask.setAttribute('id', maskId);
                        mask.setAttribute('maskUnits', 'userSpaceOnUse');
                        mask.setAttribute('mask-type', 'alpha');
                        mask.setAttribute('x', '0');
                        mask.setAttribute('y', '0');
                        mask.setAttribute('width', '1200');
                        mask.setAttribute('height', '1200');
                        var maskImg = document.createElementNS(NS, 'image');
                        maskImg.setAttribute('x', '0');
                        maskImg.setAttribute('y', '0');
                        maskImg.setAttribute('width', '1200');
                        maskImg.setAttribute('height', '1200');
                        maskImg.setAttribute('preserveAspectRatio', 'xMidYMid slice');
                        maskImg.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', url);
                        maskImg.setAttribute('href', url);
                        mask.appendChild(maskImg);
                        defs.appendChild(mask);

                        var gradId = 'bcp-grad-' + rndId;
                        var lg = document.createElementNS(NS, 'linearGradient');
                        lg.setAttribute('id', gradId);
                        lg.setAttribute('x1', '0'); lg.setAttribute('y1', '0');
                        lg.setAttribute('x2', '0'); lg.setAttribute('y2', '1');
                        var st1 = document.createElementNS(NS, 'stop');
                        st1.setAttribute('offset', '0%');
                        st1.setAttribute('stop-color', gradient.from);
                        var st2 = document.createElementNS(NS, 'stop');
                        st2.setAttribute('offset', '100%');
                        st2.setAttribute('stop-color', gradient.to);
                        lg.appendChild(st1); lg.appendChild(st2);
                        defs.appendChild(lg);

                        imgLayer.appendChild(defs);

                        var rect = document.createElementNS(NS, 'rect');
                        rect.setAttribute('x', '0');
                        rect.setAttribute('y', '0');
                        rect.setAttribute('width', '1200');
                        rect.setAttribute('height', '1200');
                        rect.setAttribute('fill', 'url(#' + gradId + ')');
                        rect.setAttribute('mask', 'url(#' + maskId + ')');
                        imgLayer.appendChild(rect);

                        return Promise.resolve(imgLayer);
                    }

                    // ── Solid tint branch (raster) ──────────────────────────
                    var img = document.createElementNS(NS, 'image');
                    img.setAttribute('x', '0');
                    img.setAttribute('y', '0');
                    img.setAttribute('width', '1200');
                    img.setAttribute('height', '1200');
                    img.setAttribute('preserveAspectRatio', 'xMidYMid slice');
                    img.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', url);
                    img.setAttribute('href', url);

                    if (tintColor) {
                        // Flatten tint: every non-transparent pixel becomes
                        // the chosen colour; alpha is preserved. The matrix
                        // ignores the source RGB entirely and uses the tint
                        // colour as a constant. This is the simplest, most
                        // predictable behaviour — "pick a colour, the pattern
                        // becomes that colour" — and works for ANY source
                        // PNG (white, blue, photo, gradient) without producing
                        // muddy multi-multiply results.
                        //
                        // Matrix layout per row:
                        //   [R-coeff G-coeff B-coeff A-coeff constant]
                        // Row 1: 0 0 0 0 R  →  output R = constant R
                        // Row 2: 0 0 0 0 G  →  output G = constant G
                        // Row 3: 0 0 0 0 B  →  output B = constant B
                        // Row 4: 0 0 0 1 0  →  output A = input A
                        var rgb = hexToRgb(tintColor) || [0, 0, 0];
                        var rN = (rgb[0] / 255).toFixed(4);
                        var gN = (rgb[1] / 255).toFixed(4);
                        var bN = (rgb[2] / 255).toFixed(4);
                        var filterId = 'bcp-png-tint-' + Math.random().toString(36).slice(2, 8);
                        var defs = document.createElementNS(NS, 'defs');
                        var filter = document.createElementNS(NS, 'filter');
                        filter.setAttribute('id', filterId);
                        filter.setAttribute('filterUnits', 'userSpaceOnUse');
                        filter.setAttribute('x', '0');
                        filter.setAttribute('y', '0');
                        filter.setAttribute('width', '1200');
                        filter.setAttribute('height', '1200');
                        filter.setAttribute('color-interpolation-filters', 'sRGB');

                        var matrix = document.createElementNS(NS, 'feColorMatrix');
                        matrix.setAttribute('type', 'matrix');
                        matrix.setAttribute('values',
                            '0 0 0 0 ' + rN + '  ' +
                            '0 0 0 0 ' + gN + '  ' +
                            '0 0 0 0 ' + bN + '  ' +
                            '0 0 0 1 0'
                        );
                        filter.appendChild(matrix);

                        defs.appendChild(filter);
                        imgLayer.appendChild(defs);
                        img.setAttribute('filter', 'url(#' + filterId + ')');
                    }
                    imgLayer.appendChild(img);
                    return Promise.resolve(imgLayer);
                }

                // SVG path
                return fetchSvgText(url).then(function(svgText){
                    var parsed = parseSvg(svgText);
                    if (!parsed) return null;

                    // Render the source SVG at its NATURAL size (don't auto-scale).
                    // The source viewBox dimensions become the nested <svg>'s
                    // pixel width/height — so a 978×822 source draws at 978×822,
                    // and a 1200×1200 source fills the customiser canvas.
                    var sourceViewBox = parsed.getAttribute('viewBox') || '0 0 1200 1200';
                    var vbParts = sourceViewBox.split(/\s+/).map(parseFloat);
                    var sw = vbParts[2] || 1200;
                    var sh = vbParts[3] || 1200;

                    // Center within the 1200×1200 canvas when source is smaller.
                    var offsetX = Math.max(0, (1200 - sw) / 2);
                    var offsetY = Math.max(0, (1200 - sh) / 2);
                    var nested = document.createElementNS(NS, 'svg');
                    nested.setAttribute('viewBox', sourceViewBox);
                    nested.setAttribute('x', offsetX);
                    nested.setAttribute('y', offsetY);
                    nested.setAttribute('width',  sw);
                    nested.setAttribute('height', sh);

                    // Move children into the nested <svg>.
                    Array.from(parsed.children).forEach(function(child){
                        nested.appendChild(child.cloneNode(true));
                    });

                    var layer = document.createElementNS(NS, 'g');
                    layer.setAttribute('data-layer', label);
                    layer.appendChild(nested);

                    // ── Gradient branch (vector SVG) ────────────────────────
                    // Define a <linearGradient> in defs and set fill="url(#grad)"
                    // on the wrapper. Paths without their own fill inherit it.
                    if (gradient && gradient.from && gradient.to) {
                        var vGradId = 'bcp-vgrad-' + Math.random().toString(36).slice(2, 8);
                        var vDefs = document.createElementNS(NS, 'defs');
                        var vLg = document.createElementNS(NS, 'linearGradient');
                        vLg.setAttribute('id', vGradId);
                        vLg.setAttribute('x1', '0'); vLg.setAttribute('y1', '0');
                        vLg.setAttribute('x2', '0'); vLg.setAttribute('y2', '1');
                        vLg.setAttribute('gradientUnits', 'objectBoundingBox');
                        var vSt1 = document.createElementNS(NS, 'stop');
                        vSt1.setAttribute('offset', '0%');
                        vSt1.setAttribute('stop-color', gradient.from);
                        var vSt2 = document.createElementNS(NS, 'stop');
                        vSt2.setAttribute('offset', '100%');
                        vSt2.setAttribute('stop-color', gradient.to);
                        vLg.appendChild(vSt1); vLg.appendChild(vSt2);
                        vDefs.appendChild(vLg);
                        layer.insertBefore(vDefs, layer.firstChild);
                        layer.style.fill = 'url(#' + vGradId + ')';
                        return layer;
                    }

                    if (!tintColor) return layer;

                    if (svgHasRasterImages(parsed)) {
                        // Raster pattern: tint via SVG feColorMatrix filter.
                        // First matrix desaturates (RGB → luminance grayscale);
                        // second matrix multiplies each channel by the tint
                        // colour's R/G/B ratio. Whites become the tint colour,
                        // blacks stay black, alpha preserved. Texture detail kept.
                        var rgb = hexToRgb(tintColor) || [0,0,0];
                        var rN = (rgb[0] / 255).toFixed(4);
                        var gN = (rgb[1] / 255).toFixed(4);
                        var bN = (rgb[2] / 255).toFixed(4);
                        var filterId = 'bcp-tint-' + Math.random().toString(36).slice(2, 8);
                        var defs = document.createElementNS(NS, 'defs');
                        var filter = document.createElementNS(NS, 'filter');
                        filter.setAttribute('id', filterId);
                        filter.setAttribute('color-interpolation-filters', 'sRGB');
                        // 1) Desaturate to luminance
                        var m1 = document.createElementNS(NS, 'feColorMatrix');
                        m1.setAttribute('type', 'matrix');
                        m1.setAttribute('values',
                            '0.299 0.587 0.114 0 0  ' +
                            '0.299 0.587 0.114 0 0  ' +
                            '0.299 0.587 0.114 0 0  ' +
                            '0 0 0 1 0'
                        );
                        filter.appendChild(m1);
                        // 2) Stretch luminance to use the full range. Many design
                        //    textures sit in the lower brightness range, so
                        //    multiplying by tint would produce a too-dark result.
                        //    slope=3 + intercept=0 maps L:0..0.33 → 0..1, clamped.
                        var ct = document.createElementNS(NS, 'feComponentTransfer');
                        ['feFuncR', 'feFuncG', 'feFuncB'].forEach(function(fn){
                            var f = document.createElementNS(NS, fn);
                            f.setAttribute('type', 'linear');
                            f.setAttribute('slope', '3');
                            f.setAttribute('intercept', '0');
                            ct.appendChild(f);
                        });
                        filter.appendChild(ct);
                        // 3) Multiply by tint
                        var m2 = document.createElementNS(NS, 'feColorMatrix');
                        m2.setAttribute('type', 'matrix');
                        m2.setAttribute('values',
                            rN + ' 0 0 0 0  ' +
                            '0 ' + gN + ' 0 0 0  ' +
                            '0 0 ' + bN + ' 0 0  ' +
                            '0 0 0 1 0'
                        );
                        filter.appendChild(m2);
                        defs.appendChild(filter);
                        layer.insertBefore(defs, layer.firstChild);
                        nested.setAttribute('filter', 'url(#' + filterId + ')');
                        return layer;
                    } else {
                        // Vector tint: setting fill on the wrapper cascades to
                        // child paths that don't have an explicit fill attribute.
                        layer.style.fill = tintColor;
                        return layer;
                    }
                }).catch(function(e){
                    console.warn('Bespoke layer fetch failed:', url, e);
                    return null;
                });
            }

            // 2. After updateDesignLayers runs, if the active design is a registered
            //    one, build the full layer stack into every bg-layer-{stepId} group.
            //
            // For products without a Design step (e.g. Grip Socks) there's
            // no selected design — fall back to rendering just the product
            // assets (background + pad-base) so the preview still shows
            // the product silhouette instead of the default shin-pad
            // template baked into BG_INNER_TEMPLATE.
            //
            // Returns a Promise that resolves once the overlay has been
            // applied to every bg-layer in the DOM. Lets callers (e.g.
            // capturePreviewSVG) await the render before cloning the SVG.
            // Diameter-driven band-width scaling has been disabled —
            // turned out to confuse the visual more than help. The
            // customer's choice of diameter still gets recorded on the
            // order; the preview just renders at full canvas width
            // regardless. Function kept as a no-op so callers don't
            // need refactoring; if we ever want it back, swap the
            // body in for the historical logic.
            function bcpProductBandScale(){ return 1.0; }

            function applyRegisteredDesignSVG(){
                if (!window.S) return Promise.resolve();
                var d = byId[window.S.design] || null;
                // Bail only if no design AND no product assets — otherwise
                // we'd remove the placeholder shin-pad template without
                // putting anything in its place.
                if (!d && !PA.background_url && !PA.background_alt_url && !PA.pad_base_url) return Promise.resolve();

                // ─── Seed layer defaults on first paint of this design ─────
                // Without this, the customer's bgColor / patColors stay at
                // the hardcoded customiser defaults (#feef00 / #211d33) even
                // when the admin sets a different Default colour on each
                // layer in Customiser Designs. Tracked via S._bcpSeededDesign
                // so we re-seed when the customer switches design but don't
                // overwrite their colour picks within a session.
                if (d && d.layers && d.layers.length) {
                    var _designId = String(d.id || '');
                    if (window.S._bcpSeededDesign !== _designId) {
                        if (!window.S.patColors) window.S.patColors = [];
                        d.layers.forEach(function(layer, idx){
                            if (!layer || !layer.default) return;
                            if (idx === 0) {
                                // Layer 0 = pad-base ("Trophy Colour" /
                                // "Pad background") → S.bgColor.
                                window.S.bgColor = layer.default;
                                var _cpBg = document.getElementById('cp-bg');
                                if (_cpBg) _cpBg.value = layer.default;
                                var _ctBg = document.getElementById('ct-bg');
                                if (_ctBg) _ctBg.style.background = layer.default;
                            } else {
                                // Layer 1+ = pattern layers → S.patColors[idx-1].
                                var _patIdx = idx - 1;
                                window.S.patColors[_patIdx] = layer.default;
                                if (_patIdx === 0) {
                                    window.S.patColor = layer.default;
                                    var _cpPat = document.getElementById('cp-pat');
                                    if (_cpPat) _cpPat.value = layer.default;
                                    var _ctPat = document.getElementById('ct-pat');
                                    if (_ctPat) _ctPat.style.background = layer.default;
                                }
                                // Pattern 2+ swatches are rebuilt dynamically
                                // by rebuildPatternRows() from the same
                                // patColors values we just seeded.
                            }
                        });
                        window.S._bcpSeededDesign = _designId;
                    }
                }

                // Compose the layer stack (URLs + tint colours in render order)
                var stack = [];

                // 1. Background — static wallpaper, no tint.
                //    If this product has a Background (Alt) uploaded AND the
                //    customer has flipped the Frill toggle to its alt state,
                //    render the alt instead. Falls back to the main background
                //    if no alt URL exists, so non-pennant products never break.
                var _bgUseAlt = !!(window.S && window.S.useBgAlt && PA.background_alt_url);
                var _bgUrl    = _bgUseAlt ? PA.background_alt_url : PA.background_url;
                if (_bgUrl) {
                    stack.push({ url: _bgUrl, tint: null, label: 'background' });
                }

                // 2. Pad base — uses design's layer-1 file if set, else product pad base
                var padBaseUrl  = (d && d.layers && d.layers[0] && d.layers[0].file_url) || PA.pad_base_url || null;
                if (padBaseUrl) {
                    stack.push({
                        url:      padBaseUrl,
                        tint:     window.S.bgColor || '#feef00',
                        gradient: window.S.bgGradient || null,
                        label:    'pad-base'
                    });
                }

                // 3. Pattern layers (design.layers index 1+)
                //
                // Each pattern layer reads its colour from S.patColors[],
                // indexed by pattern position (layer idx-1, because pad-base
                // is layer 0). Missing entries are initialised from the
                // layer's `default` value set in the WP admin so the design
                // appears immediately on first paint.
                //
                // S.patColor is kept in sync with patColors[0] so existing
                // single-pattern code that reads S.patColor keeps working.
                if (!window.S.patColors) window.S.patColors = [];
                ((d && d.layers) || []).forEach(function(layer, idx){
                    if (idx === 0) return; // pad base handled above
                    if (!layer.file_url) return;
                    var patIdx = idx - 1; // 0-based into S.patColors
                    if (window.S.patColors[patIdx] === undefined ||
                        window.S.patColors[patIdx] === null ||
                        window.S.patColors[patIdx] === '') {
                        // First time we've seen this layer for this design —
                        // seed from admin default (or sensible fallback).
                        window.S.patColors[patIdx] =
                            layer.default || (patIdx === 0 ? '#211d33' : '#000000');
                    }
                    // Keep S.patColor mirrored to patColors[0] so the
                    // legacy "Pattern" row in the static HTML reflects state.
                    if (patIdx === 0) window.S.patColor = window.S.patColors[0];
                    stack.push({
                        url:      layer.file_url,
                        tint:     window.S.patColors[patIdx],
                        gradient: (window.S.patGradients && window.S.patGradients[patIdx]) || null,
                        label:    'pattern-' + idx
                    });
                });

                // 4. Lighting overlays — optional highlight / shadow PNGs that
                //    sit on TOP of the design (background + patterns) for depth
                //    (curved shin pads, cylindrical trophies). Untinted and NOT
                //    masked, and they live in the design layer so the badge /
                //    name / number stay crisp above them.
                //
                //    Order: Highlights first, Shadow last → Shadow renders on
                //    TOP of Highlights so depth definition shows clearly even
                //    when both layers are present. (Used to be the other way
                //    round, which left Highlights covering Shadow.)
                if (PA.highlights_url) {
                    stack.push({ url: PA.highlights_url, tint: null, label: 'highlight' });
                }
                if (PA.shadow_url) {
                    stack.push({ url: PA.shadow_url, tint: null, label: 'shadow' });
                }

                if (!stack.length) return;

                // If we have product-level background OR pad base, REPLACE the
                // customiser's hardcoded yellow pad rectangles with our stack.
                // Otherwise we'd be drawing on top of them which looks wrong.
                var hasProductBase = !!(PA.background_url || PA.background_alt_url || PA.pad_base_url ||
                    (d && d.layers && d.layers[0] && d.layers[0].file_url));

                // Build the pad-base centering offset + every layer in
                // parallel. The offset is applied per-layer (NOT to the wrap
                // group) because parent transforms don't propagate into a
                // nested <svg> — SVG resets the coordinate context at every
                // viewport boundary.
                return Promise.all([
                    getPadCenteringOffset(padBaseUrl),
                    Promise.all(stack.map(function(s){ return buildLayer(s.url, s.tint, s.label, s.gradient); }))
                ]).then(function(results){
                    var centerOffset = results[0] || {tx:0, ty:0};
                    var layers       = results[1];

                    // Shift ONLY the pad-base layer. Pattern PNGs are 1200x1200
                    // with the design content already positioned correctly
                    // inside the file — they need to stay at x=0, y=0 so they
                    // cover the canvas. The pad-base is the only layer where
                    // the source file content is mis-positioned (drawn off-
                    // centre by the designer), so it's the only one we shift.
                    //
                    // The mask is later cloned from the pad-base layer's
                    // content, so it inherits the same shift automatically.
                    // Patterns are then masked through the (now-centred) pad
                    // silhouette, revealing only the pattern content that
                    // overlaps with the pad shape.
                    if (centerOffset.tx !== 0 || centerOffset.ty !== 0) {
                        layers.forEach(function(layer, i){
                            if (!layer) return;
                            if (!stack[i] || stack[i].label !== 'pad-base') return;
                            var nested = layer.querySelector('svg');
                            if (nested) {
                                nested.setAttribute('x', centerOffset.tx);
                                nested.setAttribute('y', centerOffset.ty);
                                return;
                            }
                            var img = layer.querySelector('image');
                            if (img) {
                                img.setAttribute('x', centerOffset.tx);
                                img.setAttribute('y', centerOffset.ty);
                            }
                        });
                    }

                        var groups = document.querySelectorAll('[id^="bg-layer-"]');
                        groups.forEach(function(g, groupIdx){
                            // Remove old overlay if present
                            var prev = g.querySelector('[data-bespoke-design-overlay]');
                            if (prev) prev.remove();
                            // If we have our own pad base, clear the customiser's
                            // native pad rendering (yellow rectangles + clip paths)
                            // so we're not stacking on top of them.
                            if (hasProductBase) {
                                Array.from(g.children).forEach(function(child){
                                    // Only remove non-overlay children
                                    if (!child.hasAttribute('data-bespoke-design-overlay')) {
                                        child.remove();
                                    }
                                });
                            }
                            // Build the parent wrap and append each layer in order
                            var wrap = document.createElementNS(NS, 'g');
                            // No design selected (products like Grip Socks)
                            // — tag the wrap with a placeholder id.
                            wrap.setAttribute('data-bespoke-design-overlay', d ? d.id : '_none');

                            // Build a mask from the pad-base layer so pattern
                            // layers can be clipped to the pad silhouette
                            // (otherwise the pattern overflows past the pad).
                            // Supports both vector (SVG) and raster (PNG/JPG)
                            // pad-base files — vectors use luminance masking
                            // with forced white fill, rasters use alpha-channel
                            // masking with mask-type="alpha".
                            var padBaseIdx = stack.findIndex(function(s){ return s.label === 'pad-base'; });
                            var padMaskId = null;
                            if (padBaseIdx >= 0 && layers[padBaseIdx]) {
                                var padBaseLayer = layers[padBaseIdx];
                                var nestedSvg = padBaseLayer.querySelector('svg');
                                var rasterImg = padBaseLayer.querySelector('image');
                                if (nestedSvg || rasterImg) {
                                    var maskSlug = d ? d.id.replace(/[^a-z0-9]/g, '') : 'nodesign';
                                    padMaskId = 'bcp-padmask-' + maskSlug + '-' + groupIdx;
                                    var defs = document.createElementNS(NS, 'defs');
                                    var mask = document.createElementNS(NS, 'mask');
                                    mask.setAttribute('id', padMaskId);
                                    mask.setAttribute('maskUnits', 'userSpaceOnUse');
                                    mask.setAttribute('x', '0');
                                    mask.setAttribute('y', '0');
                                    mask.setAttribute('width', '1200');
                                    mask.setAttribute('height', '1200');
                                    if (nestedSvg) {
                                        // Vector pad base — clone with white fill.
                                        // Default luminance mask: white = show.
                                        var maskSvg = nestedSvg.cloneNode(true);
                                        maskSvg.style.fill = '#fff';
                                        mask.appendChild(maskSvg);
                                    } else {
                                        // Raster pad base — use the image's alpha
                                        // channel as the mask. Transparent edges
                                        // outside the pad shape become invisible.
                                        mask.setAttribute('mask-type', 'alpha');
                                        var maskImg = rasterImg.cloneNode(true);
                                        mask.appendChild(maskImg);
                                    }
                                    defs.appendChild(mask);
                                    wrap.appendChild(defs);
                                }
                            }

                            layers.forEach(function(l, i){
                                if (!l) return;
                                var cloned = l.cloneNode(true);
                                // Apply pad mask to pattern layers (not to
                                // background or pad-base itself).
                                if (padMaskId && stack[i] && stack[i].label && stack[i].label.indexOf('pattern-') === 0) {
                                    cloned.setAttribute('mask', 'url(#' + padMaskId + ')');
                                }
                                // Armband band-width scaling — when the
                                // product is armbands and a diameter has
                                // been picked, the background image gets
                                // squeezed horizontally so a 16cm band looks
                                // visibly shorter than a 36cm one. Affects
                                // the wallpaper background only.
                                if (stack[i] && stack[i].label === 'background') {
                                    var _bandScale = bcpProductBandScale();
                                    if (_bandScale < 1.0) {
                                        var _imgEl = cloned.querySelector('image');
                                        if (_imgEl) {
                                            var _origW = parseFloat(_imgEl.getAttribute('width')) || 1200;
                                            var _newW  = _origW * _bandScale;
                                            _imgEl.setAttribute('width', _newW);
                                            _imgEl.setAttribute('x', (1200 - _newW) / 2);
                                        }
                                    }
                                }
                                wrap.appendChild(cloned);
                            });
                            // Keep legacy CSS vars set so other CSS hooks still work
                            if (window.S.bgColor)  wrap.style.setProperty('--bg-col',  window.S.bgColor);
                            if (window.S.patColor) wrap.style.setProperty('--pat-col', window.S.patColor);
                            ((d && d.layers) || []).forEach(function(layer, idx){
                                var c = idx === 0 ? window.S.bgColor :
                                        idx === 1 ? window.S.patColor :
                                        (layer.default || '#000');
                                if (c) wrap.style.setProperty('--col-' + (idx + 1), c);
                            });

                            g.appendChild(wrap);
                        });
                    });
            }

            // Rebuild the colour-picker rows for the active design:
            //
            //   - Updates labels on the static "Pad background" + "Pattern"
            //     rows to match the admin-defined layer labels.
            //   - For designs with 3+ pattern layers, dynamically inserts
            //     extra .zone-row entries (Pattern 2, Pattern 3, ...) so
            //     the customer can tint each pattern layer independently.
            //   - Initialises window.S.patColors[] from layer defaults for
            //     any unset entries; existing user choices are preserved.
            //
            // Replaces the previous updateColourPickerLabels() which only
            // handled labels for the two static rows.
            // Per-layer "Customer editable" check. Layers default to
            // editable; the admin only stores the key when it's explicitly
            // off (saved as '0'). Locked layers still paint on the pad via
            // the SVG composer — they just don't get a colour-picker row
            // on the front end.
            function bcpIsLayerEditable(layer){
                return !layer || layer.editable !== '0';
            }

            function rebuildPatternRows(){
                if (!window.S) return;
                var d = byId[window.S.design];
                if (!d || !d.layers || !d.layers.length) return;
                var layers = d.layers;
                // Pattern layers = layers[1..]. patLayerCount = number of
                // pattern colour pickers we MIGHT need (1 = just the static
                // row, 2+ = static row + dynamic rows for the extras).
                var patLayerCount = Math.max(0, layers.length - 1);

                // ── 1. Sync window.S.patColors[] with the active design ──
                // We seed defaults for EVERY pattern index — including locked
                // ones — so the SVG composer paints them with the admin's
                // default colour even though we don't expose a picker for
                // them.
                if (!window.S.patColors) window.S.patColors = [];
                for (var i = 0; i < patLayerCount; i++) {
                    if (window.S.patColors[i] === undefined ||
                        window.S.patColors[i] === null ||
                        window.S.patColors[i] === '') {
                        var srcLayer = layers[i + 1];
                        window.S.patColors[i] = (srcLayer && srcLayer.default) ||
                            (i === 0 ? '#211d33' : '#000000');
                    }
                }
                // Trim if previous design had more pattern layers than this one.
                window.S.patColors.length = patLayerCount;
                // Mirror layer 1 onto S.patColor so legacy code keeps working.
                if (patLayerCount > 0) {
                    window.S.patColor = window.S.patColors[0];
                }

                // ── 2. Static "Pad background" row — label + visibility ──
                // Hide the row entirely when the admin has flagged layer 0
                // as non-editable, so the customer can't open the picker.
                var bgCT  = document.getElementById('ct-bg');
                var bgRow = bgCT ? bgCT.closest('.zone-row') : null;
                if (bgRow) {
                    bgRow.style.display = bcpIsLayerEditable(layers[0]) ? '' : 'none';
                }
                var lbls = document.querySelectorAll(
                    '#bespoke-customiser-root .zone-row .zone-lbl'
                );
                lbls.forEach(function(el){
                    var txt = (el.textContent || '').trim();
                    if (txt === 'Pad background' && layers[0] && layers[0].label) {
                        el.textContent = layers[0].label;
                    }
                });

                // ── 3. Find the static "Pattern" row (anchor for dynamic rows) ──
                var staticPatCT = document.getElementById('ct-pat');
                if (!staticPatCT) return;
                var staticPatRow = staticPatCT.closest('.zone-row');
                if (!staticPatRow) return;
                var container = staticPatRow.parentElement;

                // Visibility for the static Pattern row mirrors layer 1's
                // editable flag. Hidden rows still get their value synced
                // below so re-enabling the layer (or switching designs) picks
                // up the correct colour straight away.
                if (layers[1]) {
                    staticPatRow.style.display = bcpIsLayerEditable(layers[1]) ? '' : 'none';
                } else {
                    staticPatRow.style.display = 'none';
                }

                // Update the static Pattern row's label + input value to match
                // the active design's layer 1. When there are extra pattern
                // layers, label this one "Pattern 1" so the row labels stay
                // numbered consistently (Pattern 1, Pattern 2, ...) rather
                // than the awkward "Pattern, Pattern 2".
                var staticLbl = staticPatRow.querySelector('.zone-lbl');
                if (staticLbl && layers[1] && layers[1].label) {
                    staticLbl.textContent = layers[1].label;
                } else if (staticLbl) {
                    staticLbl.textContent = patLayerCount > 1 ? 'Pattern 1' : 'Pattern';
                }
                var staticInput = document.getElementById('cp-pat');
                if (staticInput && window.S.patColors[0] &&
                    document.activeElement !== staticInput) {
                    staticInput.value = window.S.patColors[0];
                    staticPatCT.style.background = window.S.patColors[0];
                }

                // ── 4. Add / update / remove dynamic Pattern N rows ──
                // Locked layers (editable === '0') are skipped entirely —
                // they still paint via the SVG composer, they just don't get
                // a colour-picker row exposed to the customer.
                var existing = container.querySelectorAll(
                    '.zone-row[data-bcp-dynamic-pat]'
                );

                // Build the shortlist of pattern layers that should actually
                // get a row: index 2+, has a file_url, and is editable.
                var visiblePats = []; // { patIdx, patNum, layer }
                for (var pi2 = 1; pi2 < patLayerCount; pi2++) {
                    var lyr = layers[pi2 + 1];
                    if (!lyr || !lyr.file_url) continue;
                    if (!bcpIsLayerEditable(lyr)) continue;
                    visiblePats.push({
                        patIdx: pi2,
                        patNum: pi2 + 1,
                        layer:  lyr
                    });
                }
                var needExtra = visiblePats.length;

                if (existing.length === needExtra) {
                    // Same row count — just refresh labels + values in place
                    // so we don't churn the DOM (which would lose any focus
                    // / mid-drag state in the picker).
                    Array.prototype.forEach.call(existing, function(row, idx){
                        var info = visiblePats[idx];
                        if (!info) return;
                        var lbl = row.querySelector('.zone-lbl');
                        if (lbl) lbl.textContent =
                            info.layer.label || ('Pattern ' + info.patNum);
                        var ct = row.querySelector('.ct');
                        var input = row.querySelector('input[type=color]');
                        var colour = window.S.patColors[info.patIdx];
                        if (colour && document.activeElement !== input) {
                            if (ct) ct.style.background = colour;
                            if (input) input.value = colour;
                        }
                    });
                    return;
                }

                // Row count differs — drop existing and rebuild from scratch.
                Array.prototype.forEach.call(existing, function(el){ el.remove(); });

                // Insert new dynamic pattern rows directly after the static
                // Pattern row, so they sit between "Pattern" and "Name text"
                // rather than at the bottom of the container.
                var insertAfter = staticPatRow;
                for (var v = 0; v < visiblePats.length; v++) {
                    var info2     = visiblePats[v];
                    var patNum    = info2.patNum;
                    var lyr2      = info2.layer;
                    var colourVal = window.S.patColors[info2.patIdx] || '#000000';
                    var labelText = lyr2.label || ('Pattern ' + patNum);

                    var row = document.createElement('div');
                    row.className = 'zone-row';
                    row.setAttribute('data-bcp-dynamic-pat', String(patNum));
                    row.innerHTML =
                        '<div class="zone-info">' +
                            '<div class="zone-lbl"></div>' +
                            '<div class="zone-sub">Pattern layer ' + patNum + ' colour</div>' +
                        '</div>' +
                        '<div class="ct" id="ct-pat' + patNum + '">' +
                            '<input type="color" id="cp-pat' + patNum + '"/>' +
                        '</div>';
                    // Set text / values via DOM (avoid HTML-injection from labels)
                    row.querySelector('.zone-lbl').textContent = labelText;
                    row.querySelector('.ct').style.background = colourVal;
                    var inp = row.querySelector('input[type=color]');
                    inp.value = colourVal;

                    // Wire to the same debounced colour pipeline as the static rows.
                    (function(zoneCode, _inp){
                        _inp.addEventListener('input', function(){
                            if (typeof window.debouncedColor === 'function') {
                                window.debouncedColor(zoneCode, _inp.value);
                            }
                        });
                    })('pat' + patNum, inp);

                    container.insertBefore(row, insertAfter.nextSibling);
                    insertAfter = row;
                }
            }

            // Backwards-compat alias for older call sites
            var updateColourPickerLabels = rebuildPatternRows;

            // Hook into updateDesignLayers, makeSVG, and syncAll so our injection
            // runs whenever the customiser's own rendering pass replaces the
            // bg-layer content (e.g. when the user navigates between steps).
            function hookCustomiser(){
                if (typeof window.updateDesignLayers !== 'function' ||
                    typeof window.makeSVG !== 'function') {
                    return setTimeout(hookCustomiser, 200);
                }
                var origUpdate = window.updateDesignLayers;
                window.updateDesignLayers = function(){
                    var ret = origUpdate.apply(this, arguments);
                    requestAnimationFrame(function(){
                        applyRegisteredDesignSVG();
                        updateColourPickerLabels();
                    });
                    return ret;
                };
                var origMakeSVG = window.makeSVG;
                window.makeSVG = function(){
                    var ret = origMakeSVG.apply(this, arguments);
                    // Defer so the new bg-layer is in the DOM before we inject.
                    requestAnimationFrame(function(){
                        applyRegisteredDesignSVG();
                    });
                    return ret;
                };
                if (typeof window.syncAll === 'function') {
                    var origSync = window.syncAll;
                    window.syncAll = function(){
                        var ret = origSync.apply(this, arguments);
                        requestAnimationFrame(applyRegisteredDesignSVG);
                        return ret;
                    };
                }
                // Hook goTo so EVERY step navigation re-applies the design
                // overlay with the latest state. Covers the case where a
                // previewed step (e.g. pb5 Review) was created before the
                // user enabled gradient — without this, the stale preview
                // shows solid colours even though state is gradient.
                if (typeof window.goTo === 'function') {
                    var origGoTo = window.goTo;
                    window.goTo = function(){
                        var ret = origGoTo.apply(this, arguments);
                        requestAnimationFrame(applyRegisteredDesignSVG);
                        return ret;
                    };
                }
                // Initial load
                applyRegisteredDesignSVG();
                updateColourPickerLabels();
            }
            var hookUpdateDesignLayers = hookCustomiser; // backwards-compat alias

            // Re-apply when state colours change (so colour picker updates the design)
            function watchColourChanges(){
                if (!window.S) { return setTimeout(watchColourChanges, 200); }
                // Hook into colour input changes
                document.addEventListener('input', function(e){
                    if (e.target && e.target.matches && e.target.matches('.ct input[type=color]')) {
                        // Debounce a tick so the state has updated
                        requestAnimationFrame(applyRegisteredDesignSVG);
                    }
                });
            }

            // Tag the badge-step screen and its sec-labels so the mobile
            // reorder CSS can target them. (CSS can't select by text content.)
            function tagBadgeStep(){
                var screens = document.querySelectorAll('#bespoke-customiser-root .screen');
                screens.forEach(function(s){
                    if (s.classList.contains('bcp-badge-step')) return;
                    var labels = s.querySelectorAll('.sec-label');
                    var labelMap = {};
                    Array.from(labels).forEach(function(l){
                        var txt = (l.textContent || '').trim().toLowerCase();
                        if (txt === 'select size')           { labelMap.size   = l; }
                        else if (txt === 'upload your club badge') { labelMap.upload = l; }
                        else if (txt === 'badge size')       { labelMap.bsize  = l; }
                    });
                    if (labelMap.size && labelMap.upload && labelMap.bsize) {
                        s.classList.add('bcp-badge-step');
                        labelMap.size.setAttribute('data-bcp',   'size');
                        labelMap.upload.setAttribute('data-bcp', 'upload');
                        labelMap.bsize.setAttribute('data-bcp',  'bsize');
                    }
                });
            }

            function init(){
                // Hook customiser functions FIRST so our injection runs whenever
                // the customiser re-renders. Then patch designs — switching the
                // default design from 'vanguard' (which no longer exists) to the
                // first registered design (e.g. Apex), and triggering an initial
                // updateDesignLayers so the preview paints on landing.
                hookUpdateDesignLayers();
                patchDesigns();
                watchColourChanges();
                tagBadgeStep();

                // Belt-and-braces initial paint: there's a timing race between
                // the customiser creating bg-layers and our hook being in
                // place. The simple retry isn't enough — applyRegisteredDesignSVG
                // silently does nothing if bg-layers don't exist yet, and
                // the customiser only creates them when makeSVG runs (which
                // is often deferred until step navigation).
                //
                // Strategy: if no bg-layers yet → force makeSVG (which our
                // hook will catch, triggering applyRegisteredDesignSVG).
                // If bg-layers exist but no overlay → call apply directly.
                // Stops as soon as our overlay is present.
                var retries = 0;
                var retryId = setInterval(function(){
                    retries++;
                    if (document.querySelector('[data-bespoke-design-overlay]')) {
                        clearInterval(retryId);
                        return;
                    }
                    if (retries > 20) {
                        clearInterval(retryId);
                        return;
                    }
                    // Prefer triggering updateDesignLayers (which iterates all
                    // existing previews and runs our hook). Falls back to
                    // direct apply if not available.
                    if (typeof window.updateDesignLayers === 'function') {
                        window.updateDesignLayers();
                    } else {
                        applyRegisteredDesignSVG();
                    }
                }, 200);

                // ── OVERRIDE capturePreviewSVG ──────────────────────────────
                // Returns a Promise<Blob> — the customer's composed design
                // rendered to a PNG image. Going via PNG sidesteps every SVG
                // namespace / xlink quirk we've been chasing, and produces a
                // file that opens and embeds cleanly anywhere.
                //
                // Pipeline:
                //   1. Find the active screen's preview SVG
                //   2. Refresh our overlay, clone the SVG
                //   3. Swap badge image hrefs to the saved server URL
                //   4. INLINE all <image> hrefs as base64 data URIs (so the
                //      SVG is self-contained — canvas rendering won't try
                //      to fetch external URLs and fail with broken icons)
                //   5. Serialize SVG → Blob → load as <img> → draw to <canvas>
                //   6. canvas.toBlob('image/png') and resolve
                //
                // Fallback: if canvas conversion fails, resolve with the SVG
                // blob so the upload still happens (PHP detects which it is).

                // Helper: fetch a URL and return a Promise resolving to a
                // base64 data URI string. Same-origin credentials are sent
                // automatically (needed for Basic Auth staging environments).
                function urlToDataURI(url){
                    return fetch(url).then(function(r){
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.blob();
                    }).then(function(blob){
                        return new Promise(function(resolve, reject){
                            var reader = new FileReader();
                            reader.onload  = function(){ resolve(reader.result); };
                            reader.onerror = function(){ reject(reader.error); };
                            reader.readAsDataURL(blob);
                        });
                    });
                }

                // Helper: walk every <image> in the SVG, fetch its external
                // href, and replace with a data URI. Returns a Promise that
                // resolves once all images are inlined (failures are non-fatal).
                function inlineAllImages(svgEl){
                    var images = svgEl.querySelectorAll('image');
                    var tasks = [];
                    for (var i = 0; i < images.length; i++) {
                        (function(img){
                            var href = img.getAttribute('href') ||
                                       img.getAttributeNS('http://www.w3.org/1999/xlink', 'href');
                            if (!href || href.indexOf('data:') === 0) return;
                            tasks.push(
                                urlToDataURI(href)
                                    .then(function(dataUri){
                                        img.setAttribute('href', dataUri);
                                        img.removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
                                    })
                                    .catch(function(err){
                                        console.warn('Bespoke: could not inline image', href, err);
                                        // Leave the original href — image may still fail to render
                                    })
                            );
                        })(images[i]);
                    }
                    return Promise.all(tasks);
                }

                window.capturePreviewSVG = function(badgeServerUrl){
                    return new Promise(function(resolve){
                        try {
                            var svg = document.querySelector('.screen.active svg.preview');
                            if (!svg) {
                                var all = document.querySelectorAll('svg.preview');
                                svg = all[all.length - 1] || all[0];
                            }
                            if (!svg) { resolve(null); return; }

                            // Force an overlay refresh THEN await its
                            // completion before cloning — otherwise the
                            // clone can race the async layer build and
                            // miss the latest gradient / colour state.
                            Promise.resolve(applyRegisteredDesignSVG()).then(function(){
                                _captureFromSvg(svg, badgeServerUrl, resolve);
                            });
                        } catch (e) {
                            console.warn('Bespoke capturePreviewSVG failed:', e);
                            resolve(null);
                        }
                    });
                };

                // Extracted from capturePreviewSVG so we can await the
                // design re-render first. Performs the clone → badge swap
                // → inline images → canvas → PNG pipeline.
                function _captureFromSvg(svg, badgeServerUrl, resolve){
                    try {
                            var clone = svg.cloneNode(true);
                            clone.setAttribute('width',  '800');
                            clone.setAttribute('height', '800');

                            if (badgeServerUrl) {
                                var badgeImgs = clone.querySelectorAll('[id^="badge-layer-"] image');
                                for (var i = 0; i < badgeImgs.length; i++) {
                                    badgeImgs[i].removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
                                    badgeImgs[i].setAttribute('href', badgeServerUrl);
                                }
                            }
                            // Belt and braces: drop xlink:href if href exists
                            var allImgs = clone.querySelectorAll('image');
                            for (var j = 0; j < allImgs.length; j++) {
                                if (allImgs[j].hasAttribute('href')) {
                                    allImgs[j].removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
                                }
                            }

                            // Inline all external image references BEFORE
                            // serializing — this is the key step that lets
                            // canvas render the SVG fully.
                            inlineAllImages(clone).then(function(){
                                var svgStr  = new XMLSerializer().serializeToString(clone);
                                var svgBlob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
                                var url     = URL.createObjectURL(svgBlob);

                                var img = new Image();
                                img.onload = function(){
                                    try {
                                        var canvas = document.createElement('canvas');
                                        canvas.width  = 800;
                                        canvas.height = 800;
                                        var ctx = canvas.getContext('2d');
                                        ctx.fillStyle = '#ffffff';
                                        ctx.fillRect(0, 0, 800, 800);
                                        ctx.drawImage(img, 0, 0, 800, 800);
                                        canvas.toBlob(function(pngBlob){
                                            URL.revokeObjectURL(url);
                                            resolve(pngBlob || svgBlob);
                                        }, 'image/png', 0.92);
                                    } catch (canvErr) {
                                        URL.revokeObjectURL(url);
                                        console.warn('Bespoke PNG conversion failed — falling back to SVG:', canvErr);
                                        resolve(svgBlob);
                                    }
                                };
                                img.onerror = function(){
                                    URL.revokeObjectURL(url);
                                    console.warn('Bespoke SVG image load failed — falling back to SVG');
                                    resolve(svgBlob);
                                };
                                img.src = url;
                            });
                        } catch (e) {
                            console.warn('Bespoke capturePreviewSVG failed:', e);
                            resolve(null);
                        }
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
    <?php endif; ?>

    <?php /* ── Customiser runtime fixes (formerly Code Snippet #29) ────────── */ ?>
    <style id="bespoke-customiser-runtime-fixes">
        /* Page title in white so it reads on the dark customiser background */
        .entry-title { color: #ffffff !important; }

        /* Hide the customiser's own top-bar (logo + "Shin pad customiser"
           wordmark) on ALL viewports. Page header already shows the brand,
           this is redundant and eats vertical space inside the customiser. */
        #bespoke-customiser-root .top-bar { display: none !important; }

        /* ── Mobile-only header + top-bar tweaks ─────────────────────────── */
        @media (max-width: 899px) {
            /* Shrink the site header (Astra) on customiser pages */
            .ast-primary-header-bar { padding: 4px 0 !important; min-height: 0 !important; }
            .ast-primary-header-bar .site-branding .custom-logo,
            .ast-primary-header-bar .site-branding img { max-height: 32px !important; width: auto !important; }
            .entry-title { font-size: clamp(22px, 5vw, 30px) !important; padding: 8px 0 !important; }

            /* Reorder the Badge step on mobile:
               Band thickness → Select size → Preview → Upload → Badge
               size slider → For-cleanest-result note.
               Scoped to .active so we don't override the customiser's
               display:none on inactive steps (would cause duplicate previews). */
            #bespoke-customiser-root .screen.bcp-badge-step.active {
                display: flex !important;
                flex-direction: column !important;
            }
            .bcp-badge-step > .bg-variant-row               { order:  1; }
            .bcp-badge-step > .sec-label[data-bcp="size"]   { order:  2; }
            .bcp-badge-step > .size-selector                { order:  3; }
            .bcp-badge-step > .preview-box                  { order:  4; }
            .bcp-badge-step > .drag-hint                    { order:  5; }
            .bcp-badge-step > .sec-label[data-bcp="upload"] { order:  6; }
            .bcp-badge-step > .upload-zone                  { order:  7; }
            .bcp-badge-step > .badge-row                    { order:  8; }
            .bcp-badge-step > .sec-label[data-bcp="bsize"]  { order:  9; }
            .bcp-badge-step > .size-row                     { order: 10; }
            .bcp-badge-step > .badge-note                   { order: 11; }
            .bcp-badge-step > p                             { order: 12; }
            .bcp-badge-step > .nav-row                      { order: 13; }
        }

        /* Badge transparency advice — green callout under the badge size
           slider. Mint-tinted panel matching the customiser accent. Shows
           on desktop (natural DOM order, after the slider) and mobile
           (order:3 above keeps it directly below the slider). */
        #bespoke-customiser-root .badge-note {
            display: flex !important;
            gap: 8px !important;
            align-items: flex-start !important;
            background: rgba(93,202,165,0.10) !important;
            border: 1px solid rgba(93,202,165,0.40) !important;
            border-radius: 10px !important;
            padding: 11px 13px !important;
            margin: 4px 0 16px !important;
            font-size: 12px !important;
            line-height: 1.5 !important;
            color: #C7E2D8 !important;
        }
        #bespoke-customiser-root .badge-note strong {
            color: #5DCAA5 !important;
            font-weight: 700 !important;
        }
        #bespoke-customiser-root .badge-note svg {
            flex-shrink: 0 !important;
            margin-top: 1px !important;
        }

        /* Page-level: stop the homepage marquee bursting the body out to 4087px wide */
        html, body { overflow-x: hidden !important; max-width: 100vw !important; }
        #bespoke-marquee { max-width: 100vw !important; overflow-x: hidden !important; box-sizing: border-box !important; }

        /* SVG name/number text: remove the heavy dark stroke around glyphs */
        #dt-svg-wrap svg text, #bespoke-customiser-root svg text {
            stroke: none !important;
            stroke-width: 0 !important;
            paint-order: normal !important;
        }

        /* Cleaner preview area — drop the green step-label + hint pills
           above the SVG (the sticky stepper at the top already shows the
           current step) and the small "Drag … to reposition" mobile hint.
           Tightens vertical space so the preview can breathe. */
        #dt-label, #dt-hint, .drag-hint { display: none !important; }
        #dt-svg-wrap { order: 2 !important; }

        /* Tighter preview box on both mobile and desktop */
        .preview-box {
            padding: 4px !important;
            margin-bottom: 6px !important;
        }

        /* Move the Back / Next buttons to sit directly under the step
           tabs at the top of every screen. Implementation uses flex
           order rather than moving HTML so we don't disturb existing
           per-step layout rules — the buttons render visually first
           while keeping their source-order position. */
        #bespoke-customiser-root .screen.active {
            display: flex !important;
            flex-direction: column !important;
        }
        /* Multi-product: a step disabled for the current product type
           (e.g. Design / Colours for Grip Socks) stays out of the DOM
           flow regardless of any .active toggling. */
        #bespoke-customiser-root .screen.bcp-step-hidden {
            display: none !important;
        }
        #bespoke-customiser-root .screen > .nav-row {
            order: -1 !important;
            position: relative !important;
            bottom: auto !important;
            margin-top: 0 !important;
            margin-bottom: 14px !important;
            border-top: none !important;
            border-bottom: 1px solid #2A2A2A !important;
            padding: 8px 0 12px !important;
        }

        /* Custom HSV colour picker ─────────────────────────────────────── */
        #bcp-overlay { position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: transparent !important; display: none; align-items: center !important; justify-content: center !important; z-index: 2147483647 !important; font-family: 'Inter', sans-serif; }
        #bcp-overlay.open { display: flex; }
        .bcp-panel { background: #1A1A1A; border: 1px solid #2A2A2A; border-radius: 12px; padding: 16px; width: 340px; max-width: 90vw; box-sizing: border-box; box-shadow: 0 12px 40px rgba(0,0,0,0.5); }
        .bcp-title { font-size: 10px; letter-spacing: 0.10em; text-transform: uppercase; color: rgba(255,255,255,0.6); flex-shrink: 0; white-space: nowrap; }
        .bcp-sv { position: relative; width: 100%; height: 180px; border-radius: 8px; touch-action: none; user-select: none; cursor: crosshair; overflow: hidden; }
        .bcp-sv-cursor { position: absolute; width: 14px; height: 14px; border: 2px solid #fff; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
        .bcp-hue { position: relative; width: 100%; height: 18px; border-radius: 9px; margin-top: 14px; background: linear-gradient(to right, #f00, #ff0, #0f0, #0ff, #00f, #f0f, #f00); touch-action: none; user-select: none; cursor: pointer; }
        .bcp-hue-cursor { position: absolute; top: 50%; width: 14px; height: 14px; border: 2px solid #fff; border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
        .bcp-row { display: flex; gap: 10px; margin-bottom: 14px; align-items: center; }
        .bcp-hex { flex: 1; min-width: 0; background: #0E0E10; border: 1px solid #2A2A2A; border-radius: 6px; padding: 7px 9px; color: #fff; font-family: 'Inter', sans-serif; font-size: 12px; outline: none; text-transform: uppercase; letter-spacing: 0.04em; }
        .bcp-hex:focus { border-color: #5DCAA5; }
        .bcp-done { flex-shrink: 0; background: #5DCAA5; color: #04342C; border: none; border-radius: 6px; padding: 8px 14px; font-family: 'Inter', sans-serif; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; cursor: pointer; text-transform: uppercase; }
        .bcp-done:hover { background: #4FB996; }
        .bcp-recent-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .bcp-recent-label { font-size: 10px; letter-spacing: 0.10em; text-transform: uppercase; color: rgba(255,255,255,0.6); white-space: nowrap; }
        .bcp-recent { display: flex; gap: 6px; }
        .bcp-recent-swatch { width: 25px; height: 25px; border-radius: 6px; border: 1px solid #2A2A2A; cursor: pointer; transition: transform 120ms ease, border-color 120ms ease; box-sizing: border-box; }
        .bcp-recent-swatch:hover { transform: scale(1.08); border-color: #5DCAA5; }
        .bcp-recent-empty { background: #0E0E10; opacity: 0.35; cursor: default; }
        .bcp-recent-empty:hover { transform: none; border-color: #2A2A2A; }
        .bcp-badge-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .bcp-badge-label { font-size: 10px; letter-spacing: 0.10em; text-transform: uppercase; color: rgba(255,255,255,0.6); white-space: nowrap; }
        .bcp-badge-swatches { display: flex; gap: 6px; }
        .bcp-badge-swatch { width: 25px; height: 25px; border-radius: 6px; border: 1px solid #2A2A2A; cursor: pointer; transition: transform 120ms ease, border-color 120ms ease; box-sizing: border-box; }
        .bcp-badge-swatch:hover { transform: scale(1.08); border-color: #5DCAA5; }

        /* Gradient toggle + From/To tabs (shown only for bg / pattern zones) */
        .bcp-grad-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .bcp-grad-label { display: flex; align-items: center; gap: 8px; font-size: 11px; color: rgba(255,255,255,0.85); cursor: pointer; user-select: none; }
        .bcp-grad-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: #5DCAA5; cursor: pointer; margin: 0; }
        .bcp-grad-tabs { display: none; gap: 4px; background: #0E0E10; padding: 3px; border-radius: 7px; }
        .bcp-grad-tab { background: transparent; border: none; color: rgba(255,255,255,0.5); font-family: 'Inter', sans-serif; font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; padding: 5px 12px; border-radius: 5px; cursor: pointer; transition: background 120ms ease, color 120ms ease; }
        .bcp-grad-tab:hover { color: rgba(255,255,255,0.85); }
        .bcp-grad-tab.sel { background: #5DCAA5; color: #04342C; }

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
        /* Restricted-palette mode — when the picker opens for a layer that
           has an "Allowed colours" list configured in the Designs admin, the
           overlay gets a .restricted class which hides the HSV / gradient /
           recent / hex / done rows and only the bcp-allowed-row is visible. */
        .bcp-allowed-row { margin-bottom: 12px; }
        .bcp-allowed-label { font-size: 10px; letter-spacing: 0.10em; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 8px; }
        .bcp-allowed-swatches { display: flex; flex-wrap: wrap; gap: 8px; }
        .bcp-allowed-swatch { width: 34px; height: 34px; border-radius: 6px; border: 2px solid #2A2A2A; cursor: pointer; transition: transform 120ms ease, border-color 120ms ease; box-sizing: border-box; }
        .bcp-allowed-swatch:hover { transform: scale(1.08); border-color: #5DCAA5; }
        .bcp-allowed-swatch.sel { border-color: #5DCAA5; box-shadow: inset 0 0 0 1px #5DCAA5; }
        #bcp-overlay.restricted .bcp-sv,
        #bcp-overlay.restricted .bcp-hue,
        #bcp-overlay.restricted .bcp-grad-row,
        #bcp-overlay.restricted .bcp-recent-row,
        #bcp-overlay.restricted .bcp-badge-row,
        #bcp-overlay.restricted .bcp-hex,
        #bcp-overlay.restricted .bcp-done { display: none !important; }
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
        overlay.innerHTML = '<div class="bcp-panel">'
          + '<div class="bcp-row"><div class="bcp-title">Choose colour</div><input class="bcp-hex" id="bcp-hex" type="text" maxlength="7" /><button class="bcp-done" id="bcp-done">Done</button></div>'
          + '<div class="bcp-grad-row" id="bcp-grad-row" style="display:none;">'
          +   '<label class="bcp-grad-label"><input type="checkbox" id="bcp-grad-toggle" /> Use gradient</label>'
          +   '<div class="bcp-grad-tabs" id="bcp-grad-tabs">'
          +     '<button type="button" class="bcp-grad-tab sel" data-tab="from">From</button>'
          +     '<button type="button" class="bcp-grad-tab" data-tab="to">To</button>'
          +   '</div>'
          + '</div>'
          + '<div class="bcp-badge-row" id="bcp-badge-row" style="display:none;"><div class="bcp-badge-label">Badge Colours</div><div class="bcp-badge-swatches" id="bcp-badge-swatches"></div></div>'
          + '<div class="bcp-allowed-row" id="bcp-allowed-row" style="display:none;"><div class="bcp-allowed-label">Choose a colour</div><div class="bcp-allowed-swatches" id="bcp-allowed-swatches"></div></div>'
          + '<div class="bcp-recent-row"><div class="bcp-recent-label">Recent Colours</div><div class="bcp-recent" id="bcp-recent"></div></div>'
          + '<div class="bcp-sv" id="bcp-sv"><div class="bcp-sv-cursor" id="bcp-sv-cursor"></div></div>'
          + '<div class="bcp-hue" id="bcp-hue"><div class="bcp-hue-cursor" id="bcp-hue-cursor"></div></div>'
          + '</div>';
        document.body.appendChild(overlay);

        var activeInput = null, h=0, s=1, v=1;
        var $ = function(id){ return document.getElementById(id); };
        var sv = $('bcp-sv'), svC = $('bcp-sv-cursor'), hue = $('bcp-hue'), hueC = $('bcp-hue-cursor'), hex = $('bcp-hex'), done = $('bcp-done');
        var gradRow = $('bcp-grad-row'), gradToggle = $('bcp-grad-toggle'), gradTabs = $('bcp-grad-tabs');

        // Gradient picker state — the zone being edited, whether gradient
        // is on, the two stop colours, and which tab the HSV is currently
        // controlling. Zones supporting gradients are 'bg' and any pattern
        // ('pat', 'pat2', 'pat3', ...) — name/number text remain solid.
        var pickerZone = null;
        var gradientMode = false;
        var gradientFrom = '#FFFFFF';
        var gradientTo   = '#000000';
        var gradientTab  = 'from';

        function hsv2rgb(h,s,v){var c=v*s,x=c*(1-Math.abs(((h/60)%2)-1)),m=v-c;var r=0,g=0,b=0;if(h<60){r=c;g=x}else if(h<120){r=x;g=c}else if(h<180){g=c;b=x}else if(h<240){g=x;b=c}else if(h<300){r=x;b=c}else{r=c;b=x}return[Math.round((r+m)*255),Math.round((g+m)*255),Math.round((b+m)*255)]}
        function rgb2hsv(r,g,b){r/=255;g/=255;b/=255;var mx=Math.max(r,g,b),mn=Math.min(r,g,b),d=mx-mn;var H=0;if(d){if(mx===r)H=((g-b)/d)%6;else if(mx===g)H=(b-r)/d+2;else H=(r-g)/d+4;H=Math.round(H*60);if(H<0)H+=360}return[H,mx===0?0:d/mx,mx]}
        function hex2rgb(x){x=x.replace(/^#/,'');if(x.length===3)x=x[0]+x[0]+x[1]+x[1]+x[2]+x[2];return[parseInt(x.slice(0,2),16),parseInt(x.slice(2,4),16),parseInt(x.slice(4,6),16)]}
        function rgb2hex(r,g,b){return'#'+[r,g,b].map(function(n){return n.toString(16).padStart(2,'0');}).join('')}
        function paintSV(){sv.style.background='linear-gradient(to top,#000,transparent),linear-gradient(to right,#fff,hsl('+h+',100%,50%))'}
        function refresh(){paintSV();svC.style.left=(s*100)+'%';svC.style.top=((1-v)*100)+'%';hueC.style.left=(h/360*100)+'%';var rgb=hsv2rgb(h,s,v);if(document.activeElement!==hex)hex.value=rgb2hex(rgb[0],rgb[1],rgb[2]).toUpperCase();push()}

        // ─── Gradient helpers ─────────────────────────────────────────────
        // Lookup / mutate window.S for the zone currently being edited.
        function isGradientZone(z){ return z === 'bg' || (z && z.indexOf('pat') === 0); }
        function getStateGrad(z){
          if (!window.S || !z) return null;
          if (z === 'bg') return window.S.bgGradient || null;
          if (z === 'pat') return (window.S.patGradients && window.S.patGradients[0]) || null;
          var m = z.match(/^pat(\d+)$/);
          if (m) return (window.S.patGradients && window.S.patGradients[parseInt(m[1],10)-1]) || null;
          return null;
        }
        function setStateGrad(z, grad){
          if (!window.S || !z) return;
          if (z === 'bg') { window.S.bgGradient = grad; return; }
          if (!window.S.patGradients) window.S.patGradients = [];
          if (z === 'pat') { window.S.patGradients[0] = grad; return; }
          var m = z.match(/^pat(\d+)$/);
          if (m) window.S.patGradients[parseInt(m[1],10)-1] = grad;
        }
        function getStateSolid(z){
          if (!window.S || !z) return '#000';
          if (z === 'bg')   return window.S.bgColor || '#000';
          if (z === 'pat')  return window.S.patColor || (window.S.patColors && window.S.patColors[0]) || '#000';
          var m = z.match(/^pat(\d+)$/);
          if (m) return (window.S.patColors && window.S.patColors[parseInt(m[1],10)-1]) || '#000';
          if (z === 'name') return window.S.nameColor || '#fff';
          if (z === 'num')  return window.S.numColor  || '#fff';
          return '#000';
        }
        function applyZoneSwatch(z){
          var ct = document.getElementById('ct-' + z);
          if (!ct) return;
          var grad = getStateGrad(z);
          ct.style.background = grad
            ? ('linear-gradient(180deg, ' + grad.from + ', ' + grad.to + ')')
            : getStateSolid(z);
        }

        // ─── Restricted palette helpers ──────────────────────────────────
        // The Designs admin can set an "Allowed colours" list per layer.
        // When present, the picker hides the HSV / gradient / recent rows
        // and shows just those swatches for that layer. Maps the picker
        // zone code back to the layer index (bg→0, pat→1, patN→N), looks
        // up the active design's layer, and returns its colours array.
        function bcpZoneLayerIdx(zone){
          if (zone === 'bg')  return 0;
          if (zone === 'pat') return 1;
          var m = String(zone || '').match(/^pat(\d+)$/);
          return m ? parseInt(m[1], 10) : null;
        }
        function bcpRestrictedColoursFor(zone){
          if (!window.S) return null;
          var idx = bcpZoneLayerIdx(zone);
          if (idx === null) return null;
          var reg = window.BESPOKE_REGISTERED_DESIGNS || [];
          var design = null;
          for (var i = 0; i < reg.length; i++) {
            if (reg[i].id === window.S.design) { design = reg[i]; break; }
          }
          if (!design || !design.layers) return null;
          var layer = design.layers[idx];
          if (!layer || !layer.colours || !layer.colours.length) return null;
          return layer.colours;
        }
        function bcpPaintAllowedSwatches(colours){
          var container = $('bcp-allowed-swatches');
          var row = $('bcp-allowed-row');
          if (!container || !row) return;
          row.style.display = '';
          var current = (activeInput && activeInput.value || '').toUpperCase();
          var html = '';
          for (var i = 0; i < colours.length; i++) {
            var c = String(colours[i] || '');
            var sel = (c.toUpperCase() === current) ? ' sel' : '';
            html += '<div class="bcp-allowed-swatch' + sel + '" data-c="' + c + '" style="background:' + c + '" title="' + c + '"></div>';
          }
          container.innerHTML = html;
          var swatches = container.querySelectorAll('.bcp-allowed-swatch');
          for (var j = 0; j < swatches.length; j++) {
            swatches[j].addEventListener('click', function(){
              var c = this.getAttribute('data-c');
              if (activeInput) {
                activeInput.value = c;
                activeInput.dispatchEvent(new Event('input', { bubbles: true }));
              }
              for (var k = 0; k < swatches.length; k++) {
                swatches[k].classList.toggle('sel', swatches[k] === this);
              }
              setTimeout(function(){
                overlay.classList.remove('open');
                overlay.classList.remove('restricted');
                activeInput = null;
              }, 100);
            });
          }
        }
        function defaultGradTo(fromHex){
          var rgb = hex2rgb(fromHex);
          return rgb2hex(
            Math.max(0, Math.floor(rgb[0] * 0.3)),
            Math.max(0, Math.floor(rgb[1] * 0.3)),
            Math.max(0, Math.floor(rgb[2] * 0.3))
          );
        }
        // Debounced repaint of the customiser previews after gradient edits.
        var _renderTimer = null;
        function triggerRender(){
          clearTimeout(_renderTimer);
          _renderTimer = setTimeout(function(){
            if (typeof window.updateDesignLayers === 'function') window.updateDesignLayers();
            if (typeof window.syncAll === 'function') window.syncAll();
          }, 40);
        }
        function updateTabSelection(){
          var tabs = gradTabs ? gradTabs.querySelectorAll('.bcp-grad-tab') : [];
          for (var i = 0; i < tabs.length; i++){
            if (tabs[i].getAttribute('data-tab') === gradientTab) tabs[i].classList.add('sel');
            else tabs[i].classList.remove('sel');
          }
        }

        var _pushRaf=false;
        function push(){
          if(_pushRaf)return;
          _pushRaf=true;
          requestAnimationFrame(function(){
            _pushRaf=false;
            var rgb = hsv2rgb(h, s, v);
            var hexValue = rgb2hex(rgb[0], rgb[1], rgb[2]);
            if (gradientMode && pickerZone){
              // Save HSV → active gradient stop, update state + swatch + render
              if (gradientTab === 'from') gradientFrom = hexValue;
              else                        gradientTo   = hexValue;
              setStateGrad(pickerZone, { from: gradientFrom, to: gradientTo });
              applyZoneSwatch(pickerZone);
              triggerRender();
            } else if (activeInput){
              // Solid mode — existing pipeline (input event → debouncedColor)
              activeInput.value = hexValue;
              activeInput.dispatchEvent(new Event('input',{bubbles:true}));
            }
          });
        }
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

        // Extract dominant colours from the customer's uploaded badge.
        // Improvements over basic quantisation:
        //   - Does NOT skip pure white or pure black (those are valid colours)
        //   - Buckets pixels by 3-bit-per-channel grid (512 buckets)
        //   - For each dominant bucket, returns the AVERAGE of pixels in it
        //     (so #FF0080 stays as #FF0080, not the bucket centre)
        //   - Euclidean distance similarity filter — avoids returning
        //     multiple near-identical pinks
        var _badgeColoursCache = { url: null, colours: null };
        function extractColoursFromImage(imgEl, maxCount){
          return new Promise(function(resolve){
            try {
              var canvas = document.createElement('canvas');
              var maxDim = 120;
              var w = imgEl.naturalWidth || imgEl.width;
              var h = imgEl.naturalHeight || imgEl.height;
              if (!w || !h) { resolve([]); return; }
              var ratio = Math.min(maxDim / w, maxDim / h, 1);
              canvas.width  = Math.max(1, Math.round(w * ratio));
              canvas.height = Math.max(1, Math.round(h * ratio));
              var ctx = canvas.getContext('2d');
              ctx.drawImage(imgEl, 0, 0, canvas.width, canvas.height);
              var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

              // Bucket pixels by quantised colour, keep running sums so
              // we can compute the actual average colour later.
              var buckets = {};
              for (var i = 0; i < data.length; i += 4) {
                if (data[i+3] < 200) continue; // only solid pixels
                var r = data[i], g = data[i+1], b = data[i+2];
                // 3 bits per channel = 8 levels = 512 buckets
                var key = ((r & 0xE0) << 8) | ((g & 0xE0) << 3) | ((b & 0xE0) >> 3);
                if (!buckets[key]) buckets[key] = { c: 0, r: 0, g: 0, b: 0 };
                var bk = buckets[key];
                bk.c++; bk.r += r; bk.g += g; bk.b += b;
              }

              // Sort buckets by pixel count (most common first)
              var keys = Object.keys(buckets).sort(function(a, b){
                return buckets[b].c - buckets[a].c;
              });

              // Detect whether a candidate colour is approximately a linear
              // blend of any pair of colours we've already picked. This
              // catches the "anti-aliased edge" artifacts where yellow meets
              // black and produces dark-olive intermediate pixels.
              function isBlendOfPicks(cand, list, tolerance){
                if (list.length < 2) return false;
                for (var i = 0; i < list.length; i++) {
                  for (var j = i + 1; j < list.length; j++) {
                    var A = list[i], B = list[j];
                    var ratios = [];
                    var ok = true;
                    for (var c = 0; c < 3; c++) {
                      var d = B[c] - A[c];
                      if (Math.abs(d) < 8) {
                        // A and B nearly identical on this channel —
                        // candidate must also be near them
                        if (Math.abs(cand[c] - A[c]) > 15) { ok = false; break; }
                      } else {
                        var r = (cand[c] - A[c]) / d;
                        if (r < -0.05 || r > 1.05) { ok = false; break; }
                        ratios.push(r);
                      }
                    }
                    if (!ok || ratios.length === 0) continue;
                    var minR = Math.min.apply(null, ratios);
                    var maxR = Math.max.apply(null, ratios);
                    // Strictly between A and B with consistent ratio = blend
                    if (maxR - minR < tolerance && minR > 0.1 && maxR < 0.9) return true;
                  }
                }
                return false;
              }

              // Pick top buckets, skipping near-duplicates and edge blends.
              var picks = [];
              var MIN_DIST = 70;
              for (var jj = 0; jj < keys.length && picks.length < maxCount; jj++) {
                var bk2 = buckets[keys[jj]];
                var R = Math.round(bk2.r / bk2.c);
                var G = Math.round(bk2.g / bk2.c);
                var B = Math.round(bk2.b / bk2.c);
                // Skip if too similar to an existing pick
                var similar = false;
                for (var k = 0; k < picks.length; k++) {
                  var dr = picks[k][0] - R;
                  var dg = picks[k][1] - G;
                  var db = picks[k][2] - B;
                  if (Math.sqrt(dr*dr + dg*dg + db*db) < MIN_DIST) { similar = true; break; }
                }
                if (similar) continue;
                // Skip if it's just an antialiased blend of existing picks
                if (isBlendOfPicks([R, G, B], picks, 0.15)) continue;
                picks.push([R, G, B]);
              }

              var hexes = picks.map(function(rgb){
                function pad(n){ var s = n.toString(16).toUpperCase(); return s.length === 1 ? '0' + s : s; }
                return '#' + pad(rgb[0]) + pad(rgb[1]) + pad(rgb[2]);
              });
              resolve(hexes);
            } catch (e) {
              console.warn('Bespoke badge colour extraction failed:', e);
              resolve([]);
            }
          });
        }

        function renderBadgeColours(colours){
          var container = $('bcp-badge-swatches');
          var row       = $('bcp-badge-row');
          if (!container || !row) return;
          if (!colours || colours.length === 0) {
            row.style.display = 'none';
            return;
          }
          var html = '';
          for (var i = 0; i < colours.length; i++) {
            html += '<div class="bcp-badge-swatch" data-c="' + colours[i] + '" style="background:' + colours[i] + '" title="' + colours[i] + '"></div>';
          }
          container.innerHTML = html;
          row.style.display = 'flex';
          Array.prototype.forEach.call(
            container.querySelectorAll('.bcp-badge-swatch[data-c]'),
            function(el){
              el.addEventListener('click', function(){
                try { setHex(el.getAttribute('data-c')); } catch(_){}
              });
            }
          );
        }

        function maybeExtractBadgeColours(){
          if (!window.S || !window.S.badgeURL || !window.S.badge) {
            renderBadgeColours(null);
            return;
          }
          if (_badgeColoursCache.url === window.S.badgeURL && _badgeColoursCache.colours) {
            renderBadgeColours(_badgeColoursCache.colours);
            return;
          }
          var img = new Image();
          img.onload = function(){
            extractColoursFromImage(img, 5).then(function(cols){
              _badgeColoursCache.url = window.S.badgeURL;
              _badgeColoursCache.colours = cols;
              renderBadgeColours(cols);
            });
          };
          img.onerror = function(){ renderBadgeColours(null); };
          img.src = window.S.badgeURL;
        }

        var RECENT_KEY = 'bcp_recent_colours';
        var MAX_RECENT = 6;
        function loadRecent(){
          try { var raw = localStorage.getItem(RECENT_KEY); return raw ? JSON.parse(raw) : []; }
          catch(e){ return []; }
        }
        function saveRecent(arr){
          try { localStorage.setItem(RECENT_KEY, JSON.stringify(arr)); } catch(e){}
        }
        function pushRecent(hexValue){
          hexValue = (hexValue || '').toUpperCase();
          if (!/^#[0-9A-F]{6}$/.test(hexValue)) return;
          var arr = loadRecent().filter(function(c){ return c !== hexValue; });
          arr.unshift(hexValue);
          arr = arr.slice(0, MAX_RECENT);
          saveRecent(arr);
          renderRecent();
        }
        function renderRecent(){
          var container = $('bcp-recent');
          if (!container) return;
          var arr = loadRecent();
          var html = '';
          for (var i = 0; i < MAX_RECENT; i++) {
            if (arr[i]) {
              html += '<div class="bcp-recent-swatch" data-c="' + arr[i] + '" style="background:' + arr[i] + '" title="' + arr[i] + '"></div>';
            } else {
              html += '<div class="bcp-recent-swatch bcp-recent-empty"></div>';
            }
          }
          container.innerHTML = html;
          Array.prototype.forEach.call(
            container.querySelectorAll('.bcp-recent-swatch[data-c]'),
            function(el){
              el.addEventListener('click', function(){
                try { setHex(el.getAttribute('data-c')); } catch(_){}
              });
            }
          );
        }
        renderRecent();

        function close(){
          var rgb = hsv2rgb(h,s,v);
          pushRecent(rgb2hex(rgb[0],rgb[1],rgb[2]));
          overlay.classList.remove('open');
          activeInput=null;
        }
        done.addEventListener('click',close);
        overlay.addEventListener('click',function(e){if(e.target===overlay)close()});

        // Disable native colour input on every existing colour swatch so
        // clicking the .ct opens our HSV picker instead of the browser one.
        document.querySelectorAll('.ct input[type=color]').forEach(function(i){
          i.style.pointerEvents = 'none';
        });

        // Open-picker helper — extracted so both click and touchend
        // (and future dynamically-added rows) share one entry point.
        function openPickerForCt(ct){
          var i = ct.querySelector('input[type=color]');
          if (!i) return;
          i.style.pointerEvents = 'none'; // safety for dynamic rows
          activeInput = i;

          // Derive the zone code from the input's ID (e.g. cp-bg → 'bg',
          // cp-pat2 → 'pat2'). Used by the gradient state helpers.
          pickerZone = (i.id || '').replace(/^cp-/, '');

          // Locked layer guard — if the admin flagged this design layer as
          // not editable, refuse to open the picker even if the row somehow
          // got clicked. rebuildPatternRows already hides locked rows, this
          // is a belt-and-braces second check.
          var _bcpLocked = (function(){
            if (!window.S) return false;
            var idx = (typeof bcpZoneLayerIdx === 'function') ? bcpZoneLayerIdx(pickerZone) : null;
            if (idx === null) return false;
            var reg = window.BESPOKE_REGISTERED_DESIGNS || [];
            var design = null;
            for (var k = 0; k < reg.length; k++) {
              if (reg[k].id === window.S.design) { design = reg[k]; break; }
            }
            if (!design || !design.layers) return false;
            var layer = design.layers[idx];
            return !!(layer && layer.editable === '0');
          })();
          if (_bcpLocked) { activeInput = null; return; }

          // Restricted palette? If this layer has an "Allowed colours" list
          // from the design admin, render just those swatches and hide the
          // HSV / gradient / recent UI (the '.restricted' class drives the
          // CSS hiding). Click on a swatch sets the colour and auto-closes.
          var _bcpAllowed = bcpRestrictedColoursFor(pickerZone);
          if (_bcpAllowed) {
            overlay.classList.add('restricted');
            bcpPaintAllowedSwatches(_bcpAllowed);
          } else {
            overlay.classList.remove('restricted');
            var _allowedRow = $('bcp-allowed-row');
            if (_allowedRow) _allowedRow.style.display = 'none';
          }

          // Show or hide the gradient toggle row based on whether this
          // zone supports gradients (bg + any pattern; not name/number).
          if (gradRow) gradRow.style.display = isGradientZone(pickerZone) ? 'flex' : 'none';

          // Load state — if this zone is already in gradient mode, set
          // the picker into gradient mode and load both stops; otherwise
          // solid mode.
          var existingGrad = getStateGrad(pickerZone);
          if (existingGrad && isGradientZone(pickerZone)) {
            gradientMode = true;
            gradientFrom = existingGrad.from;
            gradientTo   = existingGrad.to;
            gradientTab  = 'from';
            if (gradToggle) gradToggle.checked = true;
            if (gradTabs)   gradTabs.style.display = 'flex';
            updateTabSelection();
            setHex(gradientFrom);
          } else {
            gradientMode = false;
            if (gradToggle) gradToggle.checked = false;
            if (gradTabs)   gradTabs.style.display = 'none';
            setHex(i.value || getStateSolid(pickerZone) || '#ff0000');
          }

          overlay.classList.add('open');
          try { overlay.scrollIntoView({block:'center', inline:'center'}); } catch(_) {}
          hex.value = (gradientMode ? gradientFrom : (i.value || '#FF0000')).toUpperCase();
          maybeExtractBadgeColours();
        }

        // ── Gradient toggle: solid ↔ gradient for the active zone ───────
        if (gradToggle) {
          gradToggle.addEventListener('change', function(){
            if (!pickerZone) return;
            if (gradToggle.checked) {
              // Switching ON: initialise gradient from current solid colour
              // and a darker default for the "to" stop. User can edit both.
              gradientMode = true;
              var currentSolid = getStateSolid(pickerZone) || hex.value || '#FFFFFF';
              gradientFrom = currentSolid;
              gradientTo   = defaultGradTo(currentSolid);
              gradientTab  = 'from';
              setStateGrad(pickerZone, { from: gradientFrom, to: gradientTo });
              if (gradTabs) gradTabs.style.display = 'flex';
              updateTabSelection();
              setHex(gradientFrom);
              applyZoneSwatch(pickerZone);
              triggerRender();
            } else {
              // Switching OFF: clear gradient, keep current "from" as solid.
              gradientMode = false;
              setStateGrad(pickerZone, null);
              if (gradTabs) gradTabs.style.display = 'none';
              var newSolid = gradientFrom || hex.value || '#FFFFFF';
              if (activeInput) {
                activeInput.value = newSolid;
                activeInput.dispatchEvent(new Event('input', { bubbles: true }));
              }
              setHex(newSolid);
              applyZoneSwatch(pickerZone);
            }
          });
        }

        // ── Tab switch (From ↔ To) — load that stop into HSV ────────────
        if (gradTabs) {
          gradTabs.addEventListener('click', function(e){
            var tab = e.target && e.target.closest && e.target.closest('.bcp-grad-tab');
            if (!tab) return;
            e.preventDefault();
            gradientTab = tab.getAttribute('data-tab');
            updateTabSelection();
            setHex(gradientTab === 'from' ? gradientFrom : gradientTo);
          });
        }

        // Event delegation — any .ct inside the customiser opens the picker.
        // This means dynamically-injected pattern rows (Pattern 2, Pattern 3,
        // ...) work without us having to re-attach listeners after each
        // design change.
        document.addEventListener('click', function(e){
          var ct = e.target && e.target.closest && e.target.closest('.ct');
          if (!ct) return;
          if (!ct.closest('#bespoke-customiser-root')) return;
          e.preventDefault();
          e.stopPropagation();
          openPickerForCt(ct);
        });
        document.addEventListener('touchend', function(e){
          var ct = e.target && e.target.closest && e.target.closest('.ct');
          if (!ct) return;
          if (!ct.closest('#bespoke-customiser-root')) return;
          e.preventDefault();
          openPickerForCt(ct);
        }, {passive: false});
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
