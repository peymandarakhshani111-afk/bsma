<?php
/*
 * Plugin Name: bsma home page
 * Description: Coded home page for bsma.ir (fire boxes, Teknim fire alarm, building guide, customers, quote form to Eitaa, best sellers, FAQ, latest articles). While this file exists it replaces the Elementor front page; delete it (and purge the cache) to get the old page back. Admins can compare with ?old_home=1.
 */
if (!defined('ABSPATH')) {
    exit;
}

define('BSMA_HOME_VER', '1.0.0');

const BSMA_HOME_HERO_ID   = 26472; // behsazan-fire-box-2-cabin-copper-stainless-door-installed.jpg
const BSMA_HOME_TEKNIM    = [26250, 26253, 26244, 26236, 23580, 26257, 26266, 23588];
const BSMA_HOME_CACHE_KEY = 'bsma_home_html_v1';
// Eitaa (eitaayar.ir) credentials live in wp-config.php:
//   define('BSMA_EITAA_TOKEN', '...');  define('BSMA_EITAA_CHAT', '...');

function bsma_home_active()
{
    static $on = null;
    if (null !== $on) {
        return $on;
    }
    if (!did_action('wp')) {
        return false;
    }
    $on = is_front_page() && !is_paged() && !is_admin()
        && !isset($_GET['elementor-preview'])
        && !(isset($_GET['old_home']) && current_user_can('manage_options'));
    return $on;
}

add_filter('template_include', function ($template) {
    return bsma_home_active() ? __DIR__ . '/bsma-home/front.php' : $template;
}, 99);

add_filter('body_class', function ($c) {
    if (bsma_home_active()) {
        $c[] = 'bhm-on';
    }
    return $c;
});

add_action('wp_enqueue_scripts', function () {
    if (!bsma_home_active()) {
        return;
    }
    $base = plugins_url('bsma-home/', __FILE__);
    wp_enqueue_style('bsma-home', $base . 'home.css', [], BSMA_HOME_VER);
    wp_enqueue_script('bsma-home', $base . 'home.js', [], BSMA_HOME_VER, ['in_footer' => true, 'strategy' => 'defer']);
    wp_localize_script('bsma-home', 'BSMA_HOME', ['quote' => esc_url_raw(rest_url('bsma/v1/quote'))]);
    // The old Elementor front page is no longer rendered, so its widget/slider styles are dead weight.
    $front = (int) get_option('page_on_front');
    $drop = ['elementor-post-' . $front, 'e-animation-fadeInUp', 'swiper', 'e-swiper', 'widget-slides', 'widget-heading', 'widget-nested-carousel', 'widget-divider', 'widget-woocommerce-products', 'widget-image', 'widget-posts', 'widget-spacer'];
    foreach ($drop as $h) {
        wp_dequeue_style($h);
    }
}, 999);

add_action('wp_head', function () {
    if (!bsma_home_active() || !wp_attachment_is_image(BSMA_HOME_HERO_ID)) {
        return;
    }
    $src = wp_get_attachment_image_src(BSMA_HOME_HERO_ID, 'large');
    $set = wp_get_attachment_image_srcset(BSMA_HOME_HERO_ID, 'large');
    if ($src) {
        echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url($src[0]) . '"'
            . ($set ? ' imagesrcset="' . esc_attr($set) . '" imagesizes="(max-width: 760px) 280px, 520px"' : '') . ">\n";
    }
}, 1);

// Rendered sections are cached; any product/post change clears the cache.
function bsma_home_flush()
{
    delete_transient(BSMA_HOME_CACHE_KEY);
}
add_action('save_post_product', 'bsma_home_flush');
add_action('save_post_post', 'bsma_home_flush');
add_action('woocommerce_product_set_stock_status', 'bsma_home_flush');
add_action('deleted_post', 'bsma_home_flush');

function bsma_home_fa($s)
{
    return strtr((string) $s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function bsma_home_svg($p, $w = '1.9')
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $w . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

function bsma_home_ic($n)
{
    $i = [
        'box' => '<rect x="3.5" y="3" width="17" height="18" rx="2"/><path d="M3.5 7h17"/><circle cx="12" cy="14" r="4.6"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'shield' => '<path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'panel' => '<rect x="4" y="3" width="16" height="18" rx="2"/><rect x="7" y="6" width="10" height="4" rx="1"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01M16 17.5h.01" stroke-width="2.6"/>',
        'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10.5 20.5a1.8 1.8 0 0 0 3 0"/>',
        'home' => '<path d="M3.5 11L12 4l8.5 7"/><path d="M6 9.5V20h12V9.5"/><path d="M10 20v-5h4v5"/>',
        'office' => '<path d="M4 21V5l8-2v18M12 8l8 2v11M3 21h18"/><path d="M7 8h2M7 12h2M7 16h2M15 13h2M15 17h2"/>',
        'factory' => '<path d="M3 21V11l5 3V11l5 3V8l8 4v9z"/><path d="M7 17h2M12 17h2M17 17h2"/>',
        'refinery' => '<path d="M5 21V9h4v12M15 21V5h4v16M3 21h18"/><path d="M9 13h6M9 17h6"/>',
        'power' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'gov' => '<path d="M3 10l9-6 9 6"/><path d="M5 10v9M9.5 10v9M14.5 10v9M19 10v9M3 21h18"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'arrow' => '<path d="M15 6l-6 6 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'doc' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 11h6M9 14h6M9 17h4"/>',
    ];
    return bsma_home_svg($i[$n] ?? '', 'check' === $n || 'arrow' === $n ? '2.3' : '1.9');
}

function bsma_home_price($product)
{
    $p = (float) $product->get_price();
    return $p > 0 ? '<b>' . wp_kses_post(wc_price($p)) . '</b>' : '<span class="bhm-ask">استعلام قیمت</span>';
}

function bsma_home_card($product, $badges = '', $spec = '', $extra_attr = '')
{
    $name = $product->get_name();
    $short = trim(preg_replace('/^جعبه آتش نشانی\s*/u', '', $name));
    if ('' === $short || 0 === strpos($short, '(')) {
        $short = $name;
    }
    ob_start(); ?>
<a class="bhm-pcard" href="<?php echo esc_url($product->get_permalink()); ?>"<?php echo $extra_attr; ?>>
  <span class="bhm-pimg"><?php echo $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy', 'decoding' => 'async', 'alt' => $name]); ?><?php echo $badges; ?></span>
  <span class="bhm-pbody"><span class="bhm-pname"><?php echo esc_html($short); ?></span><?php if ($spec) : ?><span class="bhm-spec"><?php echo esc_html($spec); ?></span><?php endif; ?>
  <span class="bhm-price"><?php echo bsma_home_price($product); ?><span class="bhm-go"><?php echo bsma_home_ic('arrow'); ?></span></span></span>
</a>
<?php
    return ob_get_clean();
}

function bsma_home_cat_url($slug_or_id, $fallback)
{
    $t = is_numeric($slug_or_id) ? get_term((int) $slug_or_id, 'product_cat') : get_term_by('slug', $slug_or_id, 'product_cat');
    $u = ($t && !is_wp_error($t)) ? get_term_link($t) : '';
    return (!$u || is_wp_error($u)) ? $fallback : $u;
}

function bsma_home_sections()
{
    if (!function_exists('wc_get_products')) {
        return '';
    }
    $cached = get_transient(BSMA_HOME_CACHE_KEY);
    if (is_string($cached) && '' !== $cached) {
        return $cached;
    }
    $U = 'https://bsma.ir/product-category/';
    $fb_url = bsma_home_cat_url('fire-box', $U . 'fire-box/');
    $tk_url = bsma_home_cat_url(600, $U);
    $tk_addr = bsma_home_cat_url(601, $tk_url);
    $tk_conv = bsma_home_cat_url(602, $tk_url);
    $gfe_url = bsma_home_cat_url(599, $U);
    $C = [
        'box' => ['جعبه آتش‌نشانی', $fb_url],
        'ext' => ['کپسول آتش‌نشانی', bsma_home_cat_url(217, $U)],
        'alarm' => ['سیستم اعلام حریق', bsma_home_cat_url(122, $U)],
        'sup' => ['سیستم اطفای حریق', bsma_home_cat_url(152, $U)],
        'sign' => ['علائم هشداردهنده', bsma_home_cat_url(153, $U)],
        'safe' => ['تجهیزات ایمنی', bsma_home_cat_url(625, $U)],
        'beam' => ['بیم دتکتور', bsma_home_cat_url('beam-detector', $U)],
        'light' => ['چراغ‌قوه‌ی شارژی', bsma_home_cat_url(96, $U)],
    ];

    // fire boxes
    $boxes = wc_get_products(['status' => 'publish', 'limit' => -1, 'category' => ['fire-box'], 'orderby' => 'menu_order', 'order' => 'ASC', 'visibility' => 'catalog']);
    $boxes = array_values(array_filter($boxes, function ($p) {
        return false === mb_strpos($p->get_name(), 'هاساری');
    }));
    $counts = ['all' => 0, 'one' => 0, 'two' => 0, 'twin' => 0, 'steel' => 0, 'metal' => 0];
    $cards = '';
    foreach ($boxes as $i => $p) {
        $n = $p->get_name();
        $cat_names = wp_list_pluck(get_the_terms($p->get_id(), 'product_cat') ?: [], 'name');
        $k = ['all'];
        if (false !== mb_strpos($n, 'تک کابین')) {
            $k[] = 'one';
        }
        if (preg_match('/دو ?کابین/u', $n)) {
            $k[] = 'two';
        }
        if (false !== mb_strpos($n, 'دو قلو')) {
            $k[] = 'twin';
        }
        $steel = (bool) array_filter($cat_names, function ($c) { return false !== mb_strpos($c, 'استیل'); });
        $metal = (bool) array_filter($cat_names, function ($c) { return false !== mb_strpos($c, 'فلزی'); });
        if ($steel) {
            $k[] = 'steel';
        }
        if ($metal) {
            $k[] = 'metal';
        }
        foreach ($k as $x) {
            $counts[$x]++;
        }
        $is_new = $p->get_date_created() && $p->get_date_created()->getTimestamp() > time() - 90 * DAY_IN_SECONDS;
        $badges = '<span class="bhm-badges">' . ($is_new ? '<span class="bhm-bdg new">جدید</span>' : '') . ($steel ? '<span class="bhm-bdg st">درب استیل</span>' : '') . ($metal ? '<span class="bhm-bdg">فلزی</span>' : '') . '</span>';
        $cards .= bsma_home_card($p, $badges, '', ' data-k="' . esc_attr(implode(' ', $k)) . '"' . ($i >= 8 ? ' data-more' : ''));
    }
    $tabs = [['all', 'همه'], ['one', 'تک‌کابین'], ['two', 'دوکابین'], ['twin', 'دوقلو'], ['steel', 'درب استیل'], ['metal', 'فلزی']];

    // teknim
    $tk_products = wc_get_products(['status' => 'publish', 'include' => BSMA_HOME_TEKNIM, 'limit' => count(BSMA_HOME_TEKNIM)]);
    $tk_by = [];
    foreach ($tk_products as $p) {
        $tk_by[$p->get_id()] = $p;
    }
    $tk_cards = '';
    foreach (BSMA_HOME_TEKNIM as $id) {
        if (empty($tk_by[$id])) {
            continue;
        }
        $p = $tk_by[$id];
        $parts = array_map('trim', explode('|', $p->get_name(), 2));
        $cats = wp_list_pluck(get_the_terms($id, 'product_cat') ?: [], 'term_id');
        $conv = in_array(602, $cats, true) || false !== mb_strpos($parts[0], 'متعارف');
        $badge = '<span class="bhm-badges"><span class="bhm-bdg' . ($conv ? '' : ' st') . '">' . ($conv ? 'کانونشنال' : 'آدرس‌پذیر') . '</span></span>';
        $tk_cards .= bsma_home_card($p, $badge, $parts[1] ?? '');
    }

    // best sellers
    $pop_ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 12, 'fields' => 'ids', 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC',
        'tax_query' => [['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-catalog'], 'operator' => 'NOT IN']]]);
    $pop_cards = '';
    $pop_n = 0;
    foreach ($pop_ids as $id) {
        $p = wc_get_product($id);
        if ($p && $pop_n < 8 && false === mb_strpos($p->get_name(), 'هاساری')) {
            $pop_cards .= bsma_home_card($p);
            $pop_n++;
        }
    }

    // latest posts (without Hasari ones)
    $posts = array_values(array_filter(get_posts(['numberposts' => 8, 'post_status' => 'publish']), function ($p) {
        return false === mb_strpos($p->post_title, 'هاساری');
    }));
    $posts = array_slice($posts, 0, 3);

    $img = plugins_url('bsma-home/img/', __FILE__);
    ob_start();
    require __DIR__ . '/bsma-home/sections.php';
    $html = ob_get_clean();
    set_transient(BSMA_HOME_CACHE_KEY, $html, 30 * MINUTE_IN_SECONDS);
    return $html;
}

// ---------- quote form -> Eitaa (fallback: site e-mail) ----------
add_action('rest_api_init', function () {
    register_rest_route('bsma/v1', '/quote', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => 'bsma_home_quote',
    ]);
});

function bsma_home_digits($s)
{
    $s = strtr((string) $s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    return preg_replace('/\D+/', '', $s);
}

function bsma_home_quote(WP_REST_Request $r)
{
    $fail = function ($msg, $code = 400) {
        return new WP_REST_Response(['ok' => false, 'message' => $msg], $code);
    };
    if ('' !== trim((string) $r->get_param('website'))) {
        return new WP_REST_Response(['ok' => true], 200); // honeypot: pretend success
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $rl_key = 'bsma_q_' . md5($ip);
    $hits = (int) get_transient($rl_key);
    if ($hits >= 3) {
        return $fail('درخواست‌های شما زیاد بوده است؛ لطفاً چند دقیقه بعد دوباره امتحان کنید یا تماس بگیرید.', 429);
    }
    $name = sanitize_text_field((string) $r->get_param('name'));
    $phone = bsma_home_digits($r->get_param('phone'));
    $types = ['مسکونی', 'تجاری و اداری', 'صنعتی و کارخانه', 'پالایشگاه یا نیروگاه', 'سایر'];
    $type = in_array($r->get_param('type'), $types, true) ? $r->get_param('type') : 'سایر';
    $org = sanitize_text_field((string) $r->get_param('org'));
    $msg = sanitize_textarea_field((string) $r->get_param('msg'));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        return $fail('نام را بنویسید.');
    }
    if (!preg_match('/^(?:0|98)?9\d{9}$|^0\d{10}$/', $phone)) {
        return $fail('شماره‌ی تماس را کامل بنویسید.');
    }
    $org = mb_substr($org, 0, 120);
    $msg = mb_substr($msg, 0, 1000);
    set_transient($rl_key, $hits + 1, 15 * MINUTE_IN_SECONDS);

    $when = function_exists('wp_date') ? wp_date('Y-m-d H:i') : gmdate('Y-m-d H:i');
    if (class_exists('IntlDateFormatter')) {
        $f = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL);
        $when = (string) $f->format(time());
    }
    $text = "📩 استعلام قیمت از سایت bsma.ir\n"
        . "👤 نام: {$name}\n"
        . "📞 تماس: {$phone}\n"
        . "🏢 نوع ساختمان: {$type}\n"
        . ($org ? "🏭 شرکت/سازمان: {$org}\n" : '')
        . ($msg ? "📝 شرح: {$msg}\n" : '')
        . "🕒 {$when}";

    $sent = false;
    if (defined('BSMA_EITAA_TOKEN') && defined('BSMA_EITAA_CHAT') && BSMA_EITAA_TOKEN && BSMA_EITAA_CHAT) {
        $res = wp_remote_post('https://eitaayar.ir/api/' . rawurlencode(BSMA_EITAA_TOKEN) . '/sendMessage', [
            'timeout' => 8,
            'body' => ['chat_id' => BSMA_EITAA_CHAT, 'text' => $text],
        ]);
        if (!is_wp_error($res) && 200 === (int) wp_remote_retrieve_response_code($res)) {
            $body = json_decode(wp_remote_retrieve_body($res), true);
            $sent = is_array($body) && !empty($body['ok']);
        }
    }
    if (!$sent) {
        $sent = wp_mail(get_option('admin_email'), 'استعلام قیمت جدید از سایت: ' . $name, $text);
    }
    if (!$sent) {
        return $fail('ارسال انجام نشد؛ لطفاً با ۰۳۱-۳۶۲۴۲۵۳۲ تماس بگیرید.', 500);
    }
    return new WP_REST_Response(['ok' => true, 'message' => 'درخواست شما رسید؛ کارشناس فروش به‌زودی با شما تماس می‌گیرد.'], 200);
}
