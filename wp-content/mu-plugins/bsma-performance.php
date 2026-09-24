<?php
/*
 * Plugin Name: bsma performance
 * Description: Removes WordPress's emoji detection script, emoji styles and the s.w.org DNS prefetch from front-end pages. Every current browser draws emoji itself, so these only add weight to each page. The admin area is left unchanged.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles'); // WordPress 6.4+
    remove_action('wp_print_styles', 'print_emoji_styles');         // before WordPress 6.4
});

add_filter('wp_resource_hints', function ($urls, $relation_type) {
    if ('dns-prefetch' !== $relation_type || is_admin()) {
        return $urls;
    }
    return array_filter($urls, function ($url) {
        $href = is_array($url) ? (isset($url['href']) ? $url['href'] : '') : (string) $url;
        return false === strpos($href, 's.w.org/images/core/emoji');
    });
}, 10, 2);
