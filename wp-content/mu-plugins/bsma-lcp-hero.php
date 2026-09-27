<?php
/*
 * Plugin Name: bsma LCP hero fix
 * Description: Gives the home page hero slider (Elementor Slides widget 61df9cd) its full width from the first paint. Its parent container uses "align-items: center", so without this the slider is 0px wide until Swiper's JavaScript runs, and the hero image cannot be painted before then.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', function () {
    if (!is_page(18649)) {
        return;
    }
    echo '<style id="bsma-lcp-hero">.elementor-18649 .elementor-element.elementor-element-61df9cd{width:100%;align-self:stretch}</style>' . "\n";
}, 99);
