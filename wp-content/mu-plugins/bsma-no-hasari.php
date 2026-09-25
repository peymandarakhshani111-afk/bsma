<?php
/**
 * Plugin Name: BSMA – No Hasari
 * Description: هر محصول یا نوشته‌ای که نام «هاساری / HAS ARI» در عنوان، متن یا نامک آن باشد از کل سایت (فروشگاه، دسته‌ها، جستجو، وبلاگ، منو، محصولات مرتبط، نقشه‌ی سایت) حذف می‌شود و آدرسش با ۳۰۱ به دسته‌ی جعبه آتش‌نشانی می‌رود. برای بازگشت فقط همین فایل را حذف کنید.
 */

if (!defined('ABSPATH')) {
    exit;
}

const BSMA_NH_CACHE = 'bsma_no_hasari_ids';

/** IDs of published/private products and posts that mention Hasari (cached, rebuilt on save). */
function bsma_nh_ids()
{
    static $ids = null;
    if (null !== $ids) {
        return $ids;
    }
    $ids = get_transient(BSMA_NH_CACHE);
    if (is_array($ids)) {
        return $ids;
    }
    global $wpdb;
    $like = [];
    foreach (['هاساری', 'has ari', 'hasari', 'has-ari', rawurlencode('هاساری')] as $w) {
        $l = '%' . $wpdb->esc_like(strtolower($w)) . '%';
        $like[] = $wpdb->prepare('(LOWER(post_title) LIKE %s OR LOWER(post_content) LIKE %s OR LOWER(post_excerpt) LIKE %s OR LOWER(post_name) LIKE %s)', $l, $l, $l, $l);
    }
    $ids = array_map('intval', $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','post') AND post_status IN ('publish','private','future') AND (" . implode(' OR ', $like) . ')'
    ));
    set_transient(BSMA_NH_CACHE, $ids, 12 * HOUR_IN_SECONDS);
    return $ids;
}

function bsma_nh_flush()
{
    delete_transient(BSMA_NH_CACHE);
    delete_transient('bsma_home_html_v1');
}
add_action('save_post_product', 'bsma_nh_flush');
add_action('save_post_post', 'bsma_nh_flush');
add_action('deleted_post', 'bsma_nh_flush');
add_action('trashed_post', 'bsma_nh_flush');

/** Visitor-facing request (editors keep full access in wp-admin, admin-ajax and REST). */
function bsma_nh_front()
{
    if (is_admin() && !wp_doing_ajax()) {
        return false;
    }
    $api = wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST);
    return !($api && current_user_can('edit_posts'));
}

// every front-end query: shop, categories, search, blog, widgets, Elementor loops, wc_get_products
add_action('pre_get_posts', function ($q) {
    if (!bsma_nh_front() || !($ids = bsma_nh_ids())) {
        return;
    }
    if ($q->is_singular() && $q->is_main_query()) {
        return; // single URL is redirected below
    }
    $pt = (array) ($q->get('post_type') ?: 'post');
    if (!array_intersect($pt, ['product', 'post', 'any'])) {
        return;
    }
    $in = array_map('intval', (array) $q->get('post__in'));
    if ($in) {
        $in = array_values(array_diff($in, $ids));
        $q->set('post__in', $in ?: [0]);
        return;
    }
    $q->set('post__not_in', array_values(array_unique(array_merge(array_map('intval', (array) $q->get('post__not_in')), $ids))));
});

// related, upsell, cross-sell, and any direct visibility check
add_filter('woocommerce_related_products', function ($r) {
    return array_values(array_diff(array_map('intval', $r), bsma_nh_ids()));
});
foreach (['woocommerce_product_get_upsell_ids', 'woocommerce_product_get_cross_sell_ids'] as $h) {
    add_filter($h, function ($r) {
        return bsma_nh_front() ? array_values(array_diff(array_map('intval', (array) $r), bsma_nh_ids())) : $r;
    });
}
add_filter('woocommerce_product_is_visible', function ($v, $id) {
    return in_array((int) $id, bsma_nh_ids(), true) ? false : $v;
}, 10, 2);
add_filter('woocommerce_is_purchasable', function ($v, $p) {
    return bsma_nh_front() && in_array((int) $p->get_id(), bsma_nh_ids(), true) ? false : $v;
}, 10, 2);

// menus
add_filter('wp_nav_menu_objects', function ($items) {
    $ids = bsma_nh_ids();
    return array_filter($items, function ($i) use ($ids) {
        return !('post_type' === $i->type && in_array((int) $i->object_id, $ids, true));
    });
});

// old URLs → fire-box category (301 tells Google to drop them)
add_action('template_redirect', function () {
    if (!is_singular(['product', 'post']) || !in_array((int) get_queried_object_id(), bsma_nh_ids(), true) || current_user_can('edit_posts')) {
        return;
    }
    $t = get_term_by('slug', 'fire-box', 'product_cat');
    $to = $t ? get_term_link($t) : home_url('/');
    wp_safe_redirect(is_wp_error($to) ? home_url('/') : $to, 301);
    exit;
}, 1);

// sitemaps: Rank Math + WordPress core
add_filter('rank_math/sitemap/entry', function ($url, $type, $post) {
    return ('post' === $type && is_object($post) && in_array((int) $post->ID, bsma_nh_ids(), true)) ? false : $url;
}, 10, 3);
add_filter('wp_sitemaps_posts_query_args', function ($args) {
    $args['post__not_in'] = array_merge((array) ($args['post__not_in'] ?? []), bsma_nh_ids());
    return $args;
});

// admin: list the hidden items so they can be edited, drafted or deleted
add_action('admin_notices', function () {
    $s = get_current_screen();
    if (!$s || !in_array($s->id, ['dashboard', 'edit-product', 'edit-post'], true) || !current_user_can('edit_posts') || !($ids = bsma_nh_ids())) {
        return;
    }
    echo '<div class="notice notice-warning"><p><b>این موارد نام «هاساری» دارند و از سایت پنهان شده‌اند</b> (برای ویرایش، پیش‌نویس یا حذف روی نام بزنید):</p><ul style="list-style:disc;margin-inline-start:20px">';
    foreach ($ids as $id) {
        printf('<li><a href="%s">%s</a> — %s</li>', esc_url(get_edit_post_link($id)), esc_html(get_the_title($id) ?: '#' . $id), 'product' === get_post_type($id) ? 'محصول' : 'نوشته');
    }
    echo '</ul></div>';
});
