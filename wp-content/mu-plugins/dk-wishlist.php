<?php
/*
Plugin Name: DK Wishlist
Description: WooCommerce Wishlist for guests and logged-in users with AJAX support
Version: 1.0
Author: محمد قربانی
*/

if (!defined('ABSPATH')) exit;

/**
 * گرفتن شناسه پایدار مهمان از طریق کوکی
 */
function dk_get_guest_token() {
    if (!empty($_COOKIE['dk_wishlist_token'])) {
        return sanitize_text_field($_COOKIE['dk_wishlist_token']);
    }

    $token = wp_generate_uuid4();

    $expire = time() + (30 * DAY_IN_SECONDS);
    $path   = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

    setcookie(
        'dk_wishlist_token',
        $token,
        $expire,
        $path ?: '/',
        $domain ?: '',
        is_ssl(),
        true
    );

    $_COOKIE['dk_wishlist_token'] = $token;

    return $token;
}

/**
 * ساخت جدول علاقه‌مندی‌ها (یک بار)
 * register_activation_hook برای mu-pluginها هیچ‌وقت اجرا نمی‌شود، پس جدول اینجا ساخته می‌شود.
 */
define('DK_WISHLIST_DB_VERSION', '2');

add_action('init', 'dk_wishlist_maybe_create_table');
function dk_wishlist_maybe_create_table() {
    if (get_option('dk_wishlist_db_version') === DK_WISHLIST_DB_VERSION) {
        return;
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'dk_wishlist';
    $charset = $wpdb->get_charset_collate();

    // dbDelta needs two spaces after PRIMARY KEY.
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        session_id VARCHAR(64) NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_product (user_id,product_id),
        KEY session_product (session_id,product_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('dk_wishlist_db_version', DK_WISHLIST_DB_VERSION);
}

add_action('wp_ajax_dk_toggle_wishlist', 'dk_toggle_wishlist');
add_action('wp_ajax_nopriv_dk_toggle_wishlist', 'dk_toggle_wishlist');

function dk_toggle_wishlist() {
    global $wpdb;

    $table      = $wpdb->prefix . 'dk_wishlist';
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if (!$product_id || get_post_type($product_id) !== 'product') {
        wp_send_json_error(['message' => 'invalid_product_id'], 400);
    }

    $user_id = get_current_user_id();
    $guest_token = $user_id ? null : dk_get_guest_token();

    if ($user_id) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND product_id = %d",
            $user_id,
            $product_id
        ));
    } else {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE session_id = %s AND product_id = %d",
            $guest_token,
            $product_id
        ));
    }

    if ($exists) {
        $wpdb->delete($table, ['id' => $exists], ['%d']);
        wp_send_json(['status' => 'removed']);
    } else {
        $inserted = $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id ?: null,
                'session_id' => $user_id ? null : $guest_token,
                'product_id' => $product_id,
            ],
            ['%d','%s','%d']
        );
        if ($inserted === false) {
            wp_send_json_error(['message' => 'db_error'], 500);
        }
        wp_send_json(['status' => 'added']);
    }
}

add_action('wp_ajax_dk_check_wishlist', 'dk_check_wishlist');
add_action('wp_ajax_nopriv_dk_check_wishlist', 'dk_check_wishlist');

function dk_check_wishlist() {
    global $wpdb;

    $table      = $wpdb->prefix . 'dk_wishlist';
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    if (!$product_id) {
        wp_send_json(['in_wishlist' => false]);
    }

    $user_id = get_current_user_id();

    // A guest without a token has nothing saved yet; don't set a cookie just for a check.
    if (!$user_id && empty($_COOKIE['dk_wishlist_token'])) {
        wp_send_json(['in_wishlist' => false]);
    }

    $guest_token = $user_id ? null : dk_get_guest_token();

    if ($user_id) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND product_id = %d",
            $user_id,
            $product_id
        ));
    } else {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE session_id = %s AND product_id = %d",
            $guest_token,
            $product_id
        ));
    }

    wp_send_json(['in_wishlist' => (bool) $exists]);
}
