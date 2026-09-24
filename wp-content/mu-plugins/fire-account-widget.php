<?php
/*
Plugin Name: Fire Account Widget - Grid
Description: WooCommerce My Account Single Page Grid Panel (Dashboard always open)
Version: 3.0
Author: محمد قربانی
*/

if (!defined('ABSPATH')) exit;

add_action('init', 'fire_account_start_session', 1);
function fire_account_start_session() {
    if (!session_id() && is_user_logged_in()) {
        session_start();
    }
}

add_shortcode('fire_account_widget', 'fire_account_widget_render');
add_action('init', 'fire_account_widget_handle_forms');

function fire_account_widget_handle_forms() {
    if (!is_user_logged_in()) return;

    $user_id = get_current_user_id();

    if (isset($_POST['fire_update_account'])) {
        $email   = sanitize_email($_POST['email']);
        $display = sanitize_text_field($_POST['display_name']);

        wp_update_user([
            'ID'           => $user_id,
            'user_email'   => $email,
            'display_name' => $display
        ]);

        $_SESSION['fire_msg'] = 'اطلاعات حساب با موفقیت ذخیره شد.';
    }

    if (isset($_POST['fire_change_password'])) {
        $pass1 = $_POST['password_1'];
        $pass2 = $_POST['password_2'];

        if ($pass1 === $pass2 && !empty($pass1)) {
            wp_set_password($pass1, $user_id);
            $_SESSION['fire_msg'] = 'رمز عبور با موفقیت تغییر کرد.';
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        } else {
            $_SESSION['fire_msg'] = 'رمزها مطابقت ندارند.';
        }
    }

    if (isset($_POST['fire_save_addresses'])) {
        update_user_meta($user_id, 'billing_address_1', sanitize_textarea_field($_POST['billing']));
        update_user_meta($user_id, 'shipping_address_1', sanitize_textarea_field($_POST['shipping']));
        $_SESSION['fire_msg'] = 'آدرس‌ها ذخیره شدند.';
    }

    if (isset($_POST['fire_logout'])) {
        wp_logout();
        wp_redirect(home_url());
        exit;
    }
}

function fire_account_widget_render() {
    if (!is_user_logged_in()) {
        return '<p>برای مشاهده حساب وارد شوید.</p>';
    }

    $u = wp_get_current_user();

    ob_start();

    if (!empty($_SESSION['fire_msg'])) {
        echo '<div class="fire-msg">'.$_SESSION['fire_msg'].'</div>';
        unset($_SESSION['fire_msg']);
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
            <input type="text" name="display_name" value="<?php echo esc_attr($u->display_name); ?>" placeholder="نام نمایشی">
            <input type="email" name="email" value="<?php echo esc_attr($u->user_email); ?>" placeholder="ایمیل">
            <button name="fire_update_account">ذخیره تغییرات</button>
        </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="fire-section">
        <h3>تغییر رمز عبور</h3>
        <form method="post">
            <input type="password" name="password_1" placeholder="رمز جدید">
            <input type="password" name="password_2" placeholder="تکرار رمز">
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
            <textarea name="billing" placeholder="آدرس صورتحساب"><?php echo esc_textarea(get_user_meta(get_current_user_id(), 'billing_address_1', true)); ?></textarea>
            <textarea name="shipping" placeholder="آدرس ارسال"><?php echo esc_textarea(get_user_meta(get_current_user_id(), 'shipping_address_1', true)); ?></textarea>
            <button name="fire_save_addresses">ذخیره آدرس‌ها</button>
        </form>
    </div>

    <!-- LOGOUT -->
    <div class="fire-section">
        <h3>خروج</h3>
        <form method="post">
            <button name="fire_logout">خروج از حساب</button>
        </form>
    </div>

</div>

<?php
    return ob_get_clean();
}