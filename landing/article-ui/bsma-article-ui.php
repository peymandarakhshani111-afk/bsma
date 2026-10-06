<?php
/**
 * Plugin Name: BSMA Article UI
 * Description: One shared stylesheet for the blog posts of bsma.ir (headings, images, tables, embeds, callouts, product-ad cards, subtle hover/fade effects) so every article looks the same without per-post CSS.
 * Version: 1.1.1
 * Author: Behsazan Saray-e Mehr Ahang
 * Text Domain: bsma-article-ui
 */
if (!defined('ABSPATH')) { exit; }

define('BSMA_ART_UI_VER', '1.1.1');
define('BSMA_ART_UI_SKIP', 23430); // the 3D landing page has its own template

add_action('wp_enqueue_scripts', function () {
    if (!is_singular('post') || (int) get_queried_object_id() === BSMA_ART_UI_SKIP) {
        return;
    }
    wp_enqueue_style('bsma-article-ui', plugins_url('assets/article.css', __FILE__), array(), BSMA_ART_UI_VER);
}, 99);
