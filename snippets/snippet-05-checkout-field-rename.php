<?php
/**
 * Snippet ID:    5
 * Name:          Woocommerce Checkout Field Rename
 * Status:        INACTIVE
 * Last modified: 2023-08-15 09:28:11
 * Renames the WooCommerce checkout "Company" field to "Club Name".
 */

add_filter( 'woocommerce_checkout_fields', 'rename_woo_checkout_fields' );

function rename_woo_checkout_fields( $fields ) {
    $fields['billing']['billing_company']['placeholder'] = '';
    $fields['billing']['billing_company']['label'] = 'Club Name';
	$fields['shipping']['shipping_company']['placeholder'] = '';
    $fields['shipping']['shipping_company']['label'] = 'Club Name';
    return $fields;
}
