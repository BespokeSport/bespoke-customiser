<?php
/**
 * Bespoke single-product template.
 *
 * Replaces the theme / Elementor template for any WooCommerce product
 * with a customiser type set (_bespoke_product_type). Renders the page
 * in the exact mockup layout — title hero above customiser, sizing chart,
 * description tabs with at-a-glance sidebar, related products.
 *
 * Loaded by the template_include filter in customiser-product-page.php.
 *
 * For non-customisable products this file is never touched — they
 * continue to use whatever template (Elementor / Astra default) the
 * site is configured for.
 */

defined( 'ABSPATH' ) || exit;

get_header(); // Astra / theme header — site nav etc.

global $post;
$product_id = $post ? $post->ID : 0;
$product    = $product_id ? wc_get_product( $product_id ) : null;

if ( ! $product ) {
    echo '<p>Product not found.</p>';
    get_footer();
    return;
}
?>

<main id="bespoke-single-product" class="bespoke-single-product" role="main">
    <div class="bs-shell">

        <?php /* Breadcrumb — WC's own renderer, themed by our CSS */ ?>
        <nav class="bs-breadcrumb" aria-label="Breadcrumb">
            <?php
            woocommerce_breadcrumb( [
                'delimiter'   => '<span class="sep">/</span>',
                'wrap_before' => '',
                'wrap_after'  => '',
                'before'      => '',
                'after'       => '',
                'home'        => 'Shop',
            ] );
            ?>
        </nav>

        <?php /* ── HERO: eyebrow + title + subtitle  |  FROM + price + suffix ── */ ?>
        <header class="bs-hero">
            <div class="bs-hero__left">
                <?php echo do_shortcode( '[bespoke_eyebrow]' ); ?>
                <h1 class="product_title entry-title"><?php echo esc_html( $product->get_name() ); ?></h1>
                <?php echo do_shortcode( '[bespoke_subtitle]' ); ?>
            </div>
            <div class="bs-hero__right">
                <?php
                // Only show the "FROM" caption when there's a price suffix
                // (otherwise it floats orphan above the £).
                $price_suffix = get_post_meta( $product_id, '_bespoke_price_suffix', true );
                if ( $price_suffix ) :
                ?>
                    <span class="bs-from">FROM</span>
                <?php endif; ?>
                <p class="price"><?php echo $product->get_price_html(); ?></p>
                <?php echo do_shortcode( '[bespoke_price_suffix]' ); ?>
            </div>
        </header>

        <?php /* ── Customiser ───────────────────────────────────────────────── */ ?>
        <section class="bs-customiser-wrap" aria-label="Customise this product">
            <?php echo do_shortcode( '[bespoke_customiser]' ); ?>
        </section>

        <?php
        /* ── Short description / lead-time notice ──
           Rendered through WC's standard markup so the CSS notice-card
           treatment (mint stripe + restyled red span) still applies. */
        $short_desc = $product->get_short_description();
        if ( $short_desc ) :
        ?>
            <div class="woocommerce-product-details__short-description">
                <?php echo apply_filters( 'woocommerce_short_description', $short_desc ); ?>
            </div>
        <?php endif; ?>

        <?php /* ── Sizing chart (BEspoke Page admin field) ───────────────────── */ ?>
        <?php echo do_shortcode( '[bespoke_sizing_chart]' ); ?>

        <?php /* ── Tabs + at-a-glance sidebar (2-col on desktop, stacked on mobile) ── */ ?>
        <section class="bs-tabs-grid">
            <div class="bs-tabs-grid__main">
                <?php
                // WC tabs (Description / Additional info / Reviews).
                // Calling woocommerce_output_product_data_tabs directly
                // avoids needing the whole woocommerce_after_single_product
                // hook chain (which would also re-render other summary bits).
                if ( function_exists( 'woocommerce_output_product_data_tabs' ) ) {
                    woocommerce_output_product_data_tabs();
                }
                ?>
            </div>
            <div class="bs-tabs-grid__side">
                <?php echo do_shortcode( '[bespoke_at_a_glance]' ); ?>
            </div>
        </section>

        <?php /* ── Related products ───────────────────────────────────────────── */ ?>
        <?php
        if ( function_exists( 'woocommerce_related_products' ) ) {
            woocommerce_related_products( [
                'posts_per_page' => 4,
                'columns'        => 4,
            ] );
        }
        ?>

    </div>
</main>

<?php
get_footer();
