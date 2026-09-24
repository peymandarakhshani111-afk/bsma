<?php
/*
Plugin Name: Fire Account Widget - Grid
Description: WooCommerce My Account Single Page Grid Panel (Dashboard always open)
Version: 3.0
Author: محمد قربانی
*/

if (!defined('ABSPATH')) exit;

add_shortcode('fire_account_widget', 'fire_account_widget_render');
add_action('init', 'fire_account_widget_handle_forms');

// One-time message stored per user (replaces the PHP session, which locked every request).
function fire_account_set_msg($user_id, $msg) {
    set_transient('fire_msg_' . $user_id, $msg, 5 * MINUTE_IN_SECONDS);
}

function fire_account_widget_handle_forms() {
    if (!is_user_logged_in()) return;

    $actions = array('fire_update_account', 'fire_change_password', 'fire_save_addresses', 'fire_logout');
    $submitted = false;
    foreach ($actions as $action) {
        if (isset($_POST[$action])) { $submitted = true; break; }
    }
    if (!$submitted) return;

    // CSRF protection: every form carries this nonce.
    $user_id = get_current_user_id();

    if (!isset($_POST['fire_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['fire_nonce'])), 'fire_account_action')) {
        fire_account_set_msg($user_id, 'اعتبار فرم تمام شده است؛ صفحه را تازه کنید و دوباره تلاش کنید.');
        return;
    }

    if (isset($_POST['fire_update_account'])) {
        $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $display = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));

        if (!is_email($email)) {
            fire_account_set_msg($user_id, 'ایمیل وارد شده معتبر نیست.');
        } else {
            $result = wp_update_user([
                'ID'           => $user_id,
                'user_email'   => $email,
                'display_name' => $display
            ]);
            fire_account_set_msg($user_id, is_wp_error($result)
                ? 'ذخیره انجام نشد: ' . $result->get_error_message()
                : 'اطلاعات حساب با موفقیت ذخیره شد.');
        }
    }

    if (isset($_POST['fire_change_password'])) {
        // Passwords are not sanitized, only unslashed, so special characters are kept exactly.
        $current = wp_unslash($_POST['password_current'] ?? '');
        $pass1   = wp_unslash($_POST['password_1'] ?? '');
        $pass2   = wp_unslash($_POST['password_2'] ?? '');
        $user    = get_userdata($user_id);

        if (!$user || !wp_check_password($current, $user->user_pass, $user_id)) {
            fire_account_set_msg($user_id, 'رمز فعلی درست نیست.');
        } elseif ($pass1 === '' || $pass1 !== $pass2) {
            fire_account_set_msg($user_id, 'رمزها مطابقت ندارند.');
        } else {
            // wp_update_user() keeps the user logged in after the change (wp_set_password() logs them out).
            wp_update_user(['ID' => $user_id, 'user_pass' => $pass1]);
            fire_account_set_msg($user_id, 'رمز عبور با موفقیت تغییر کرد.');
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
    }

    if (isset($_POST['fire_save_addresses'])) {
        update_user_meta($user_id, 'billing_address_1', sanitize_textarea_field(wp_unslash($_POST['billing'] ?? '')));
        update_user_meta($user_id, 'shipping_address_1', sanitize_textarea_field(wp_unslash($_POST['shipping'] ?? '')));
        fire_account_set_msg($user_id, 'آدرس‌ها ذخیره شدند.');
    }

    if (isset($_POST['fire_logout'])) {
        wp_logout();
        wp_safe_redirect(home_url());
        exit;
    }
}

function fire_account_widget_render() {
    if (!is_user_logged_in()) {
        return '<p>برای مشاهده حساب وارد شوید.</p>';
    }

    $u = wp_get_current_user();

    ob_start();

    $msg = get_transient('fire_msg_' . $u->ID);
    if ($msg) {
        echo '<div class="fire-msg">' . esc_html($msg) . '</div>';
        delete_transient('fire_msg_' . $u->ID);
    }

    ?>

<style>
/* ================= GRID LAYOUT ================ */
.fire-account-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    max-width: 100%;
    margin: 25px auto;
    background: #1b1d22;
    padding: 20px;
    border-radius: 10px;
    font-family: sans-serif;
}

/* Dashboard Always Open */
.fire-dashboard {
    grid-column: span 2;
    background: #23262d;
    padding: 20px;
    border-radius: 8px;
    color: #f2f2f2;
}

.fire-dashboard h2 {
    margin-top: 0;
    color: #9cb8ff;
}

/* Sections */
.fire-section {
    background: #2b2f36;
    padding: 18px;
    border-radius: 8px;
    color: #dcdcdc;
}

.fire-section h3 {
    margin-top: 0;
    color: #9cb8ff;
}

.fire-section input,
.fire-section textarea,
.fire-section button {
    width: 100%;
    background: #3a3f47;
    border: 1px solid #444;
    padding: 10px;
    color: #eee;
    border-radius: 6px;
    margin-bottom: 12px;
}

.fire-section button {
    background: #4866ff;
    border: none;
    font-weight: 600;
}
.fire-section button:hover {
    background: #2750ff;
}

/* Orders */
.fire-orders ul {
    margin: 0;
    padding-right: 20px;
}
.fire-orders li {
    margin-bottom: 8px;
}

/* Message */
.fire-msg {
    background: #234220;
    padding: 12px;
    border-radius: 5px;
    color: #b6ffb0;
    margin-bottom: 10px;
    border: 1px solid #397a2b;
}
</style>


<div class="fire-account-grid">

    <!-- DASHBOARD -->
    <div class="fire-dashboard">
        <h2>پیشخوان</h2>
        <p>سلام <?php echo esc_html($u->display_name); ?> عزیز، خوش آمدید.</p>
    </div>

    <!-- UPDATE ACCOUNT -->
    <div class="fire-section">
        <h3>ویرایش حساب</h3>
        <form method="post">
            <?php wp_nonce_field('fire_account_action', 'fire_nonce'); ?>
            <input type="text" name="display_name" value="<?php echo esc_attr($u->display_name); ?>" placeholder="نام نمایشی">
            <input type="email" name="email" value="<?php echo esc_attr($u->user_email); ?>" placeholder="ایمیل">
            <button name="fire_update_account">ذخیره تغییرات</button>
        </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="fire-section">
        <h3>تغییر رمز عبور</h3>
        <form method="post">
            <?php wp_nonce_field('fire_account_action', 'fire_nonce'); ?>
            <input type="password" name="password_current" placeholder="رمز فعلی" autocomplete="current-password">
            <input type="password" name="password_1" placeholder="رمز جدید" autocomplete="new-password">
            <input type="password" name="password_2" placeholder="تکرار رمز" autocomplete="new-password">
            <button name="fire_change_password">تغییر رمز</button>
        </form>
    </div>

    <!-- ORDERS -->
    <div class="fire-section fire-orders">
        <h3>سفارش‌ها</h3>
        <ul>
            <?php
            $orders = wc_get_orders(['customer_id' => get_current_user_id(), 'limit' => -1]);
            if ($orders) {
                foreach ($orders as $order) {
                    echo '<li>سفارش #' . $order->get_id() . ' - ' . wc_price($order->get_total()) . '</li>';
                }
            } else {
                echo '<li>سفارشی یافت نشد.</li>';
            }
            ?>
        </ul>
    </div>

    <!-- ADDRESSES -->
    <div class="fire-section">
        <h3>آدرس‌ها</h3>
        <form method="post">
            <?php wp_nonce_field('fire_account_action', 'fire_nonce'); ?>
            <textarea name="billing" placeholder="آدرس صورتحساب"><?php echo esc_textarea(get_user_meta(get_current_user_id(), 'billing_address_1', true)); ?></textarea>
            <textarea name="shipping" placeholder="آدرس ارسال"><?php echo esc_textarea(get_user_meta(get_current_user_id(), 'shipping_address_1', true)); ?></textarea>
            <button name="fire_save_addresses">ذخیره آدرس‌ها</button>
        </form>
    </div>

    <!-- LOGOUT -->
    <div class="fire-section">
        <h3>خروج</h3>
        <form method="post">
            <?php wp_nonce_field('fire_account_action', 'fire_nonce'); ?>
            <button name="fire_logout">خروج از حساب</button>
        </form>
    </div>

</div>

<?php
    return ob_get_clean();
}