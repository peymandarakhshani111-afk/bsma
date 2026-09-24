<?php
/*
 * Plugin Name: Fix Persian WooCommerce schema price (IRR store)
 * Description: Persian WooCommerce 10.0.5 treats an unset currency as Toman and multiplies the Rank Math og/schema price by 10. This store is priced in Rial, so give it the real store currency right before it converts. Added 2026-09-24.
 */
if (!defined('ABSPATH')) {
    exit;
}

function bsma_pw_set_store_currency($value) {
    if (class_exists('Persian_Woocommerce_Currencies', false) && function_exists('get_woocommerce_currency')) {
        try {
            Persian_Woocommerce_Currencies::$currency = get_woocommerce_currency();
        } catch (Throwable $e) {
            // The property was renamed or hidden in a later version; nothing to do.
        }
    }
    return $value;
}
add_filter('rank_math/opengraph/facebook/product_price_amount', 'bsma_pw_set_store_currency', 99);
add_filter('rank_math/snippet/rich_snippet_product_entity', 'bsma_pw_set_store_currency', 99);
add_filter('woocommerce_structured_data_product_offer', 'bsma_pw_set_store_currency', 9);
add_filter('wpseo_schema_offer', 'bsma_pw_set_store_currency', 9);
