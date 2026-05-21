<?php
/**
 * Snippet ID:    21
 * Name:          Additional content
 * Status:        INACTIVE
 * Last modified: 2025-10-09 10:10:50
 * Attaches the Fancy Product Designer preview image (_fpd_product_thumbnail)
 * to admin "new_order" emails so Outlook/Kadence can't strip them.
 */

add_filter('woocommerce_email_attachments', function ($attachments, $email_id, $order, $email) {

    if ( ! $order instanceof WC_Order ) return $attachments;

    // Send attachments only for these email IDs:
    $target_email_ids = [
        'new_order', // Admin
        // 'customer_processing_order', // ← Uncomment to send to customers
        // 'customer_completed_order',
    ];
    if ( ! in_array( $email_id, $target_email_ids, true ) ) return $attachments;

    $uploads   = wp_upload_dir();
    $baseurl   = rtrim($uploads['baseurl'], '/');
    $basedir   = rtrim($uploads['basedir'], DIRECTORY_SEPARATOR);
    $store_dir = trailingslashit($basedir) . 'fpd-email-attachments';
    wp_mkdir_p($store_dir);

    $max_attachments = 6;

    foreach ($order->get_items() as $item_id => $item) {
        if (count($attachments) >= $max_attachments) break;
        if (!$item instanceof WC_Order_Item_Product) continue;

        $url = $item->get_meta('_fpd_product_thumbnail', true);
        if (!is_string($url) || !preg_match('#^https?://#i', $url)) continue;

        $path = null;

        // 1) If URL lives under /uploads, map to local path (no download).
        if (stripos($url, $baseurl) === 0) {
            $rel  = ltrim(substr($url, strlen($baseurl)), '/');
            $cand = $basedir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (file_exists($cand) && is_readable($cand)) $path = $cand;
        }

        // 2) Fallback: download to uploads if mapping didn't work.
        if (!$path && function_exists('download_url')) {
            $tmp = download_url($url, 20);
            if (!is_wp_error($tmp) && file_exists($tmp)) {
                $ext = 'jpg';
                if (preg_match('/\.(png)(\?.*)?$/i', $url)) $ext = 'png';
                elseif (preg_match('/\.(jpe?g)(\?.*)?$/i', $url)) $ext = 'jpg';

                $dest = trailingslashit($store_dir) . wp_unique_filename(
                    $store_dir,
                    'order' . $order->get_id() . '-item' . $item_id . '.' . $ext
                );
                $moved = @rename($tmp, $dest);
                if (!$moved) { @copy($tmp, $dest); @unlink($tmp); }
                if (file_exists($dest)) $path = $dest;
            }
        }

        if ($path) $attachments[] = $path;
    }

    return $attachments;

}, 10, 4);
