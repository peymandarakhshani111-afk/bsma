<?php
/**
 * Plugin Name: BSMA Backdraft Story
 * Description: Shows the cinematic 3D "backdraft vs flashover" page on ONE post (ID 23430) instead of its normal template. Deactivate this plugin to bring the original post template back instantly. Add ?bsma_orig=1 to the URL to preview the original template without deactivating.
 * Version: 1.0.4
 * Author: BSMA
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BSMA_STORY_VER', '1.0.4');
define('BSMA_STORY_POST_ID', 23430);
define('BSMA_STORY_DIR', plugin_dir_path(__FILE__));
define('BSMA_STORY_URL', plugin_dir_url(__FILE__));

/** True only on the front-end view of the one target post. */
function bsma_story_is_target()
{
    return !is_admin()
        && is_singular('post')
        && (int) get_queried_object_id() === BSMA_STORY_POST_ID
        && !is_preview()
        && !is_customize_preview();
}

/** Swap the template for the target post only; every other URL is untouched. */
function bsma_story_template($template)
{
    if (!bsma_story_is_target() || isset($_GET['bsma_orig'])) {
        return $template;
    }
    $tpl  = BSMA_STORY_DIR . 'template.php';
    $body = BSMA_STORY_DIR . 'body.html';
    if (!is_readable($tpl) || !is_readable($body)) {
        return $template; // never break the post if a file is missing
    }
    return $tpl;
}
add_filter('template_include', 'bsma_story_template', 99);

/** The "original template" preview must not become an indexable duplicate. */
function bsma_story_noindex_orig()
{
    if (bsma_story_is_target() && isset($_GET['bsma_orig'])) {
        header('X-Robots-Tag: noindex, nofollow');
    }
}
add_action('template_redirect', 'bsma_story_noindex_orig');

/** Fresh meta description that matches the new content (title and canonical stay as set in Rank Math). */
function bsma_story_description($desc)
{
    if (!bsma_story_is_target()) {
        return $desc;
    }
    return 'بک‌درفت (Backdraft)، فلش‌اور (Flashover) و انفجار دود چه تفاوتی دارند؟ روایت سه‌بعدی و تعاملی بر پایه‌ی آخرین پژوهش‌های UL FSRI و NFPA؛ علائم، پیشگیری و تجهیزات.';
}
add_filter('rank_math/frontend/description', 'bsma_story_description');
add_filter('rank_math/opengraph/facebook/og_description', 'bsma_story_description');
add_filter('rank_math/opengraph/twitter/twitter_description', 'bsma_story_description');

/** Drop the cached copy of this one page when the plugin is switched on or off. */
function bsma_story_purge()
{
    do_action('litespeed_purge_post', BSMA_STORY_POST_ID);
    clean_post_cache(BSMA_STORY_POST_ID);
}
register_activation_hook(__FILE__, 'bsma_story_purge');
register_deactivation_hook(__FILE__, 'bsma_story_purge');
