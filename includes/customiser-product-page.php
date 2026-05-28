<?php
/**
 * BEspoke Sport — Product Detail Page content fields + shortcodes
 *
 * Adds a "BEspoke Page" tab to the WooCommerce Product Data box where the
 * site owner fills in the content specific to the redesigned product page:
 *
 *   - Eyebrow text          (e.g. "SERIES 03 · THE CUSTOMISER")
 *   - Subtitle              (the sentence under the title)
 *   - Price suffix          (e.g. "PER PAIR · INC. VAT")
 *   - Sizing chart rows     (size / age / height / dimensions, repeatable)
 *   - At-a-glance rows      (label / value, repeatable)
 *
 * Each piece of content is exposed via a shortcode that auto-detects the
 * current WooCommerce product. The site owner drops the shortcode into the
 * Elementor template once and the content updates per product:
 *
 *   [bespoke_eyebrow]
 *   [bespoke_subtitle]
 *   [bespoke_price_suffix]
 *   [bespoke_sizing_chart]
 *   [bespoke_at_a_glance]
 *
 * Markup uses .bs-* classes that the bespoke-product-page.css drop-in
 * stylesheet (enqueued in customiser-woocommerce.php) styles to match
 * the designer mockup.
 *
 * File location: /wp-content/plugins/bespoke-customiser/includes/customiser-product-page.php
 * Included by:   bespoke-customiser.php (main plugin bootstrap)
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
   0. CUSTOM SINGLE-PRODUCT TEMPLATE
   ============================================================================
   When a WC product has _bespoke_product_type set, replace whatever
   template the theme / Elementor was going to render with our own
   templates/single-product-bespoke.php. Non-customisable products are
   untouched — they continue to use Astra / Elementor / whatever.
   Priority 999 so we beat Elementor's own template_include filter.
   ========================================================================= */

add_filter( 'template_include', 'bespoke_pdp_template_include', 999 );

function bespoke_pdp_template_include( $template ) {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return $template;
    }
    global $post;
    if ( ! $post ) {
        return $template;
    }
    $type = get_post_meta( $post->ID, '_bespoke_product_type', true );
    if ( ! $type ) {
        return $template; // standard product — leave the theme template alone
    }
    $custom = BESPOKE_PLUGIN_DIR . 'templates/single-product-bespoke.php';
    return file_exists( $custom ) ? $custom : $template;
}


/* =========================================================================
   1. PRODUCT DATA TAB
   Adds the "BEspoke Page" tab next to General / Inventory / Shipping in
   the WooCommerce Product Data box.
   ========================================================================= */

add_filter( 'woocommerce_product_data_tabs', 'bespoke_pdp_register_tab' );

function bespoke_pdp_register_tab( $tabs ) {
    $tabs['bespoke_pdp'] = [
        'label'    => 'BEspoke Page',
        'target'   => 'bespoke_pdp_data',
        // Show the tab for every product type. Without these classes
        // WooCommerce's jQuery hides the tab on page load based on the
        // selected product type (Simple / Variable / etc.) — the tab
        // would flash up briefly and then vanish.
        'class'    => [ 'show_if_simple', 'show_if_variable', 'show_if_grouped', 'show_if_external' ],
        'priority' => 25,
    ];
    return $tabs;
}


/* =========================================================================
   2. TAB CONTENT
   The fields the site owner fills in per product. Two parts:
     - Simple text fields (eyebrow, subtitle, price suffix)
     - Repeatable rows (sizing chart, at-a-glance)
   ========================================================================= */

add_action( 'woocommerce_product_data_panels', 'bespoke_pdp_render_panel' );

function bespoke_pdp_render_panel() {
    global $post;
    if ( ! $post ) return;

    $eyebrow      = get_post_meta( $post->ID, '_bespoke_eyebrow',      true );
    $subtitle     = get_post_meta( $post->ID, '_bespoke_subtitle',     true );
    $price_suffix = get_post_meta( $post->ID, '_bespoke_price_suffix', true );
    $sizing       = get_post_meta( $post->ID, '_bespoke_sizing_chart', true );
    $glance       = get_post_meta( $post->ID, '_bespoke_at_a_glance',  true );
    $cust_sizes   = get_post_meta( $post->ID, '_bespoke_customiser_sizes', true );
    if ( ! is_array( $sizing ) ) $sizing = [];
    if ( ! is_array( $glance ) ) $glance = [];
    if ( ! is_array( $cust_sizes ) ) $cust_sizes = [];

    ?>
    <div id="bespoke_pdp_data" class="panel woocommerce_options_panel show_if_simple show_if_variable show_if_grouped show_if_external">

        <div class="options_group">
            <p style="padding:8px 12px;margin:0;background:#f0f6fc;border-left:3px solid #2271b1;">
                <strong>What this tab does:</strong> these fields populate the redesigned product page (above the customiser).
                Drop the matching shortcodes into your Elementor template — they pull from these fields automatically.
            </p>
        </div>

        <!-- ── Simple text fields ─────────────────────────────────────────── -->
        <div class="options_group">
            <?php
            woocommerce_wp_text_input( [
                'id'          => '_bespoke_eyebrow',
                'label'       => 'Eyebrow text',
                'placeholder' => 'SERIES 03 · THE CUSTOMISER',
                'description' => 'Small green label above the title. Shortcode: <code>[bespoke_eyebrow]</code>',
                'desc_tip'    => false,
                'value'       => $eyebrow,
            ] );
            woocommerce_wp_textarea_input( [
                'id'          => '_bespoke_subtitle',
                'label'       => 'Subtitle',
                'placeholder' => 'Sublimated edge to edge with your crest, name & number. FA-safety compliant. Made in Hampshire — five-day turnaround.',
                'description' => 'Short paragraph under the title. Shortcode: <code>[bespoke_subtitle]</code>',
                'desc_tip'    => false,
                'value'       => $subtitle,
                'rows'        => 3,
            ] );
            woocommerce_wp_text_input( [
                'id'          => '_bespoke_price_suffix',
                'label'       => 'Price suffix',
                'placeholder' => 'PER PAIR · INC. VAT',
                'description' => 'Caption that sits below the price. Shortcode: <code>[bespoke_price_suffix]</code>',
                'desc_tip'    => false,
                'value'       => $price_suffix,
            ] );
            ?>
        </div>

        <!-- ── Customiser size buttons repeater ───────────────────────────── -->
        <div class="options_group">
            <p style="padding:0 12px;"><strong>Customiser size buttons</strong> — the size options shown <em>inside the customiser</em> (the S / M / L selector on the Badge step). Each row is one button: a short <strong>Size</strong> label plus two optional description lines. Leave all rows empty to fall back to the default S / M / L.</p>

            <table class="widefat striped bespoke-pdp-repeater" id="bespoke-pdp-sizes" style="margin:0 12px;width:calc(100% - 24px);">
                <thead>
                    <tr>
                        <th style="width:90px;">Size</th>
                        <th>Line 1</th>
                        <th>Line 2</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody class="bespoke-pdp-rows" data-prefix="_bespoke_customiser_sizes">
                    <?php
                    if ( empty( $cust_sizes ) ) $cust_sizes = [ [], [], [] ]; // empty starter rows
                    foreach ( $cust_sizes as $i => $row ) {
                        bespoke_pdp_render_size_button_row( $i, $row );
                    }
                    ?>
                </tbody>
            </table>
            <p style="padding:6px 12px;">
                <button type="button" class="button bespoke-pdp-add" data-target="#bespoke-pdp-sizes .bespoke-pdp-rows" data-template="sizes">+ Add size</button>
            </p>
        </div>

        <!-- ── Sizing chart repeater ──────────────────────────────────────── -->
        <div class="options_group">
            <p style="padding:0 12px;"><strong>Sizing chart</strong> — rows shown in the "How they measure up" table.
                Shortcode: <code>[bespoke_sizing_chart]</code>. Leave empty rows to remove.</p>

            <table class="widefat striped bespoke-pdp-repeater" id="bespoke-pdp-sizing" style="margin:0 12px;width:calc(100% - 24px);">
                <thead>
                    <tr>
                        <th style="width:80px;">Size</th>
                        <th>Age</th>
                        <th>Height</th>
                        <th>Dimensions</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody class="bespoke-pdp-rows" data-prefix="_bespoke_sizing_chart">
                    <?php
                    if ( empty( $sizing ) ) $sizing = [ [], [], [] ]; // 3 starter rows
                    foreach ( $sizing as $i => $row ) {
                        bespoke_pdp_render_sizing_row( $i, $row );
                    }
                    ?>
                </tbody>
            </table>
            <p style="padding:6px 12px;">
                <button type="button" class="button bespoke-pdp-add" data-target="#bespoke-pdp-sizing .bespoke-pdp-rows" data-template="sizing">+ Add row</button>
            </p>
        </div>

        <!-- ── At a glance repeater ───────────────────────────────────────── -->
        <div class="options_group">
            <p style="padding:0 12px;"><strong>At a glance</strong> — rows shown in the sidebar of the description tab.
                Shortcode: <code>[bespoke_at_a_glance]</code>. Leave empty rows to remove.</p>

            <table class="widefat striped bespoke-pdp-repeater" id="bespoke-pdp-glance" style="margin:0 12px;width:calc(100% - 24px);">
                <thead>
                    <tr>
                        <th style="width:30%;">Label</th>
                        <th>Value</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody class="bespoke-pdp-rows" data-prefix="_bespoke_at_a_glance">
                    <?php
                    if ( empty( $glance ) ) {
                        // Sensible starter set so the user can see the shape
                        $glance = [
                            [ 'label' => 'Print',      'value' => 'Fully sublimated' ],
                            [ 'label' => 'Compliance', 'value' => 'FA-safety approved' ],
                            [ 'label' => 'Quantity',   'value' => 'Sold as a pair' ],
                            [ 'label' => 'Lead time',  'value' => '5 working days' ],
                            [ 'label' => 'Made in',    'value' => 'Hampshire, UK' ],
                            [ 'label' => 'Returns',    'value' => 'Bespoke — non-returnable' ],
                        ];
                    }
                    foreach ( $glance as $i => $row ) {
                        bespoke_pdp_render_glance_row( $i, $row );
                    }
                    ?>
                </tbody>
            </table>
            <p style="padding:6px 12px;">
                <button type="button" class="button bespoke-pdp-add" data-target="#bespoke-pdp-glance .bespoke-pdp-rows" data-template="glance">+ Add row</button>
            </p>
        </div>

        <!-- Templates used by the JS to spawn new empty rows -->
        <script type="text/template" id="bespoke-pdp-tmpl-sizing">
            <?php bespoke_pdp_render_sizing_row( '__INDEX__', [] ); ?>
        </script>
        <script type="text/template" id="bespoke-pdp-tmpl-glance">
            <?php bespoke_pdp_render_glance_row( '__INDEX__', [] ); ?>
        </script>
        <script type="text/template" id="bespoke-pdp-tmpl-sizes">
            <?php bespoke_pdp_render_size_button_row( '__INDEX__', [] ); ?>
        </script>

        <script>
        jQuery(function($){
            // Renumber inputs so saved POST keys stay 0..n contiguous.
            function renumber( $tbody, prefix ){
                $tbody.find('tr').each(function(i){
                    $(this).find('input,textarea').each(function(){
                        var name = $(this).attr('data-key');
                        if (name) {
                            $(this).attr('name', prefix + '[' + i + '][' + name + ']');
                        }
                    });
                });
            }

            // Add row
            $(document).on('click', '.bespoke-pdp-add', function(e){
                e.preventDefault();
                var sel  = $(this).data('target');
                var tmpl = $('#bespoke-pdp-tmpl-' + $(this).data('template')).html();
                var $tbody = $(sel);
                var idx = $tbody.find('tr').length;
                var html = tmpl.replace(/__INDEX__/g, idx);
                $tbody.append(html);
                renumber( $tbody, $tbody.data('prefix') );
            });

            // Remove row
            $(document).on('click', '.bespoke-pdp-remove', function(e){
                e.preventDefault();
                var $tbody = $(this).closest('tbody');
                $(this).closest('tr').remove();
                renumber( $tbody, $tbody.data('prefix') );
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Render one row of the sizing chart repeater.
 */
function bespoke_pdp_render_sizing_row( $i, $row ) {
    $size = esc_attr( $row['size'] ?? '' );
    $age  = esc_attr( $row['age']  ?? '' );
    $hgt  = esc_attr( $row['height'] ?? '' );
    $dim  = esc_attr( $row['dimensions'] ?? '' );
    ?>
    <tr>
        <td><input type="text" data-key="size"       name="_bespoke_sizing_chart[<?php echo esc_attr( $i ); ?>][size]"       value="<?php echo $size; ?>" placeholder="S"/></td>
        <td><input type="text" data-key="age"        name="_bespoke_sizing_chart[<?php echo esc_attr( $i ); ?>][age]"        value="<?php echo $age;  ?>" placeholder="Age 5–8"/></td>
        <td><input type="text" data-key="height"     name="_bespoke_sizing_chart[<?php echo esc_attr( $i ); ?>][height]"     value="<?php echo $hgt;  ?>" placeholder='Up to 4&apos;5"'/></td>
        <td><input type="text" data-key="dimensions" name="_bespoke_sizing_chart[<?php echo esc_attr( $i ); ?>][dimensions]" value="<?php echo $dim;  ?>" placeholder='9.0 × 14.0 cm'/></td>
        <td><button type="button" class="button-link bespoke-pdp-remove" style="color:#a00;">Remove</button></td>
    </tr>
    <?php
}

/**
 * Render one row of the at-a-glance repeater.
 */
function bespoke_pdp_render_glance_row( $i, $row ) {
    $label = esc_attr( $row['label'] ?? '' );
    $value = esc_attr( $row['value'] ?? '' );
    ?>
    <tr>
        <td><input type="text" data-key="label" name="_bespoke_at_a_glance[<?php echo esc_attr( $i ); ?>][label]" value="<?php echo $label; ?>" placeholder="Lead time"/></td>
        <td><input type="text" data-key="value" name="_bespoke_at_a_glance[<?php echo esc_attr( $i ); ?>][value]" value="<?php echo $value; ?>" placeholder="5 working days"/></td>
        <td><button type="button" class="button-link bespoke-pdp-remove" style="color:#a00;">Remove</button></td>
    </tr>
    <?php
}

/**
 * Render one row of the customiser size-buttons repeater.
 */
function bespoke_pdp_render_size_button_row( $i, $row ) {
    $label = esc_attr( $row['label'] ?? '' );
    $line1 = esc_attr( $row['line1'] ?? '' );
    $line2 = esc_attr( $row['line2'] ?? '' );
    ?>
    <tr>
        <td><input type="text" data-key="label" name="_bespoke_customiser_sizes[<?php echo esc_attr( $i ); ?>][label]" value="<?php echo $label; ?>" placeholder="S"/></td>
        <td><input type="text" data-key="line1" name="_bespoke_customiser_sizes[<?php echo esc_attr( $i ); ?>][line1]" value="<?php echo $line1; ?>" placeholder="Age 5–8"/></td>
        <td><input type="text" data-key="line2" name="_bespoke_customiser_sizes[<?php echo esc_attr( $i ); ?>][line2]" value="<?php echo $line2; ?>" placeholder='Up to 4&apos;5"'/></td>
        <td><button type="button" class="button-link bespoke-pdp-remove" style="color:#a00;">Remove</button></td>
    </tr>
    <?php
}


/* =========================================================================
   3. SAVE
   Stores all the BEspoke Page fields when the product is saved.
   ========================================================================= */

add_action( 'woocommerce_process_product_meta', 'bespoke_pdp_save_panel' );

function bespoke_pdp_save_panel( $post_id ) {
    // Simple text fields
    update_post_meta( $post_id, '_bespoke_eyebrow',
        sanitize_text_field( wp_unslash( $_POST['_bespoke_eyebrow'] ?? '' ) )
    );
    update_post_meta( $post_id, '_bespoke_subtitle',
        sanitize_textarea_field( wp_unslash( $_POST['_bespoke_subtitle'] ?? '' ) )
    );
    update_post_meta( $post_id, '_bespoke_price_suffix',
        sanitize_text_field( wp_unslash( $_POST['_bespoke_price_suffix'] ?? '' ) )
    );

    // Sizing chart — drop fully-empty rows so the saved array stays clean
    $sizing_raw = isset( $_POST['_bespoke_sizing_chart'] ) ? (array) $_POST['_bespoke_sizing_chart'] : [];
    $sizing = [];
    foreach ( $sizing_raw as $row ) {
        $entry = [
            'size'       => sanitize_text_field( wp_unslash( $row['size']       ?? '' ) ),
            'age'        => sanitize_text_field( wp_unslash( $row['age']        ?? '' ) ),
            'height'     => sanitize_text_field( wp_unslash( $row['height']     ?? '' ) ),
            'dimensions' => sanitize_text_field( wp_unslash( $row['dimensions'] ?? '' ) ),
        ];
        if ( $entry['size'] || $entry['age'] || $entry['height'] || $entry['dimensions'] ) {
            $sizing[] = $entry;
        }
    }
    update_post_meta( $post_id, '_bespoke_sizing_chart', $sizing );

    // At a glance — same cleanup
    $glance_raw = isset( $_POST['_bespoke_at_a_glance'] ) ? (array) $_POST['_bespoke_at_a_glance'] : [];
    $glance = [];
    foreach ( $glance_raw as $row ) {
        $entry = [
            'label' => sanitize_text_field( wp_unslash( $row['label'] ?? '' ) ),
            'value' => sanitize_text_field( wp_unslash( $row['value'] ?? '' ) ),
        ];
        if ( $entry['label'] || $entry['value'] ) {
            $glance[] = $entry;
        }
    }
    update_post_meta( $post_id, '_bespoke_at_a_glance', $glance );

    // Customiser size buttons — keep only rows that have a Size label
    $sizes_raw  = isset( $_POST['_bespoke_customiser_sizes'] ) ? (array) $_POST['_bespoke_customiser_sizes'] : [];
    $cust_sizes = [];
    foreach ( $sizes_raw as $row ) {
        $entry = [
            'label' => sanitize_text_field( wp_unslash( $row['label'] ?? '' ) ),
            'line1' => sanitize_text_field( wp_unslash( $row['line1'] ?? '' ) ),
            'line2' => sanitize_text_field( wp_unslash( $row['line2'] ?? '' ) ),
        ];
        if ( $entry['label'] !== '' ) {
            $cust_sizes[] = $entry;
        }
    }
    update_post_meta( $post_id, '_bespoke_customiser_sizes', $cust_sizes );
}


/* =========================================================================
   4. SHORTCODES
   All five auto-detect the current product (via global $product or $post),
   so the site owner only needs to drop the bare shortcode into Elementor —
   no per-product parameters required.
   ========================================================================= */

/**
 * Internal helper — resolve the WooCommerce product ID this shortcode is
 * being rendered for. Order of preference:
 *   1. Explicit product_id attribute (for manual placement)
 *   2. Current WC product (global)
 *   3. Current post (if it's a product type)
 */
function bespoke_pdp_get_product_id( $atts ) {
    if ( ! empty( $atts['product_id'] ) ) {
        return intval( $atts['product_id'] );
    }
    global $product, $post;
    if ( $product instanceof WC_Product ) {
        return $product->get_id();
    }
    if ( $post instanceof WP_Post && $post->post_type === 'product' ) {
        return $post->ID;
    }
    return 0;
}

/* --------- [bespoke_eyebrow] -------------------------------------------- */
add_shortcode( 'bespoke_eyebrow', function( $atts ) {
    $atts = shortcode_atts( [ 'product_id' => '' ], $atts );
    $pid = bespoke_pdp_get_product_id( $atts );
    if ( ! $pid ) return '';
    $value = get_post_meta( $pid, '_bespoke_eyebrow', true );
    if ( ! $value ) return '';
    return '<div class="bs-eyebrow">' . esc_html( $value ) . '</div>';
} );

/* --------- [bespoke_subtitle] ------------------------------------------- */
add_shortcode( 'bespoke_subtitle', function( $atts ) {
    $atts = shortcode_atts( [ 'product_id' => '' ], $atts );
    $pid = bespoke_pdp_get_product_id( $atts );
    if ( ! $pid ) return '';
    $value = get_post_meta( $pid, '_bespoke_subtitle', true );
    if ( ! $value ) return '';
    // Convert line breaks to <br> so the user can lay out 2-line subtitles.
    return '<div class="bs-subtitle">' . nl2br( esc_html( $value ) ) . '</div>';
} );

/* --------- [bespoke_price_suffix] --------------------------------------- */
add_shortcode( 'bespoke_price_suffix', function( $atts ) {
    $atts = shortcode_atts( [ 'product_id' => '' ], $atts );
    $pid = bespoke_pdp_get_product_id( $atts );
    if ( ! $pid ) return '';
    $value = get_post_meta( $pid, '_bespoke_price_suffix', true );
    if ( ! $value ) return '';
    return '<div class="bs-price-suffix">' . esc_html( $value ) . '</div>';
} );

/* --------- [bespoke_sizing_chart] --------------------------------------- */
add_shortcode( 'bespoke_sizing_chart', function( $atts ) {
    $atts = shortcode_atts( [
        'product_id' => '',
        'eyebrow'    => 'SPEC / SIZING',
        'title'      => 'How they measure up.',
        'caption'    => 'Approximate measurements so you can check against a pair you already own.',
    ], $atts );
    $pid = bespoke_pdp_get_product_id( $atts );
    if ( ! $pid ) return '';
    $rows = get_post_meta( $pid, '_bespoke_sizing_chart', true );
    if ( ! is_array( $rows ) || empty( $rows ) ) return '';

    ob_start();
    ?>
    <section class="bs-sizing-chart">
        <div class="bs-sizing-chart__head"><?php echo esc_html( $atts['eyebrow'] ); ?></div>
        <div class="bs-sizing-chart__topline">
            <h2 class="bs-sizing-chart__title"><?php echo esc_html( $atts['title'] ); ?></h2>
            <p class="bs-sizing-chart__caption"><?php echo esc_html( $atts['caption'] ); ?></p>
        </div>
        <table class="bs-sizing-chart__table">
            <thead>
                <tr>
                    <th>Size</th>
                    <th>Age</th>
                    <th>Height</th>
                    <th>Dimensions (W × H)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><span class="bs-size-pill"><?php echo esc_html( $row['size'] ?? '' ); ?></span></td>
                        <td><?php echo esc_html( $row['age'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $row['height'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $row['dimensions'] ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
    return ob_get_clean();
} );

/* --------- [bespoke_at_a_glance] ---------------------------------------- */
add_shortcode( 'bespoke_at_a_glance', function( $atts ) {
    $atts = shortcode_atts( [
        'product_id' => '',
        'label'      => 'At a glance',
    ], $atts );
    $pid = bespoke_pdp_get_product_id( $atts );
    if ( ! $pid ) return '';
    $rows = get_post_meta( $pid, '_bespoke_at_a_glance', true );
    if ( ! is_array( $rows ) || empty( $rows ) ) return '';

    ob_start();
    ?>
    <aside class="bs-sidebar">
        <div class="bs-sidebar__label"><?php echo esc_html( $atts['label'] ); ?></div>
        <?php foreach ( $rows as $row ) : ?>
            <div class="bs-sidebar__row">
                <span class="k"><?php echo esc_html( $row['label'] ?? '' ); ?></span>
                <span class="v"><?php echo esc_html( $row['value'] ?? '' ); ?></span>
            </div>
        <?php endforeach; ?>
    </aside>
    <?php
    return ob_get_clean();
} );
