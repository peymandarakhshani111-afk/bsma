<?php
/*
 * Plugin Name: bsma product schema tweaks
 * Description: Adjusts Rank Math's Product JSON-LD on product pages. 1) Products with no price and no reviews get no Product entity, because Google marks a Product without offers, reviews or a rating as invalid; it comes back by itself once the product has a price. 2) Offers carry the store's 7-day return policy (return shipping is paid by the customer, confirmed by the owner) so Google does not warn about a missing return policy. Added 2026-09-24.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_filter('rank_math/json_ld', function ($data) {
    if (!is_array($data) || !function_exists('is_product') || !is_product()) {
        return $data;
    }
    $product = wc_get_product(get_queried_object_id());
    if (!$product) {
        return $data;
    }
    $has_price = (float) $product->get_price() > 0;
    $return_policy = array(
        '@type'                => 'MerchantReturnPolicy',
        'applicableCountry'    => 'IR',
        'returnPolicyCountry'  => 'IR',
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays'   => 7,
        'returnMethod'         => 'https://schema.org/ReturnByMail',
        'returnFees'           => 'https://schema.org/ReturnFeesCustomerResponsibility',
    );
    foreach ($data as $key => $entity) {
        $types = is_array($entity) && isset($entity['@type']) ? (array) $entity['@type'] : array();
        if (!array_intersect($types, array('Product', 'ProductGroup'))) {
            continue;
        }
        if (!$has_price) {
            // Without a price, a Product is still valid when it has real reviews, so keep those.
            if (empty($entity['aggregateRating']) && empty($entity['review'])) {
                unset($data[$key]);
            }
            continue;
        }
        if (empty($entity['offers']) || !is_array($entity['offers'])) {
            continue;
        }
        if (isset($entity['offers']['@type'])) {
            $data[$key]['offers']['hasMerchantReturnPolicy'] = $return_policy;
        } else {
            foreach ($entity['offers'] as $i => $offer) {
                if (is_array($offer)) {
                    $data[$key]['offers'][$i]['hasMerchantReturnPolicy'] = $return_policy;
                }
            }
        }
    }
    return $data;
}, 999);
