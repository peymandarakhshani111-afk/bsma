<?php
/*
 * Plugin Name: bsma header & footer
 * Description: Site-wide header, footer, mobile drawer and mobile tab bar for bsma.ir. While this file exists it replaces the Elementor Pro header/footer templates and the Hello theme fallback; delete it (and purge the cache) to get the old header and footer back.
 */
if (!defined('ABSPATH')) {
    exit;
}

define('BSMA_HF_VER', '1.0.0');

const BSMA_HF_PHONE      = '+983136242532';
const BSMA_HF_PHONE_TXT  = '031-3624 2532';
const BSMA_HF_CHAT       = '989306016798';
const BSMA_HF_CHAT_TXT   = '0930 601 6798';
const BSMA_HF_AR_URL     = 'https://bsma.ir/صناديق-إطفاء-الحريق/';
const BSMA_HF_CATALOG    = 'https://bsma.ir/wp-content/uploads/2024/08/%DA%A9%D8%A7%D8%AA%D8%A7%D9%84%D9%88%DA%AF-%D8%A8%D9%87%D8%B3%D8%A7%D8%B2%D8%A7%D9%86.pdf';
const BSMA_HF_OFFICE_LL  = '32.629738,51.638484';
// Usernames on Eitaa / Bale / Rubika (without @). Empty = the button copies the phone number and opens the app.
const BSMA_HF_EITAA      = '';
const BSMA_HF_BALE       = '';
const BSMA_HF_RUBIKA     = '';

function bsma_hf_active()
{
    static $on = null;
    if (null !== $on) {
        return $on;
    }
    if (!did_action('wp')) {
        return false;
    }
    $on = !is_admin() && !wp_doing_ajax() && !is_feed() && !is_embed()
        && !is_singular('elementor_library')
        && !(is_singular() && 'elementor_canvas' === get_page_template_slug(get_queried_object_id()));
    return $on;
}

// Returning 0 makes Elementor Pro find no header/footer template, so it prints nothing and loads none of their CSS.
add_filter('elementor/theme/get_location_templates/template_id', function ($id, $location = '') {
    return (('header' === $location || 'footer' === $location) && bsma_hf_active()) ? 0 : $id;
}, 10, 2);

add_filter('hello_elementor_header_footer', function ($show) {
    return bsma_hf_active() ? false : $show;
});

function bsma_hf_show_tabbar()
{
    return !(function_exists('is_product') && (is_product() || is_checkout()));
}

add_filter('body_class', function ($classes) {
    if (bsma_hf_active()) {
        $classes[] = 'bhf-on';
        if (bsma_hf_show_tabbar()) {
            $classes[] = 'bhf-tab';
        }
    }
    return $classes;
});

add_action('wp_enqueue_scripts', function () {
    if (!bsma_hf_active()) {
        return;
    }
    $base = plugins_url('bsma-header-footer/', __FILE__);
    wp_enqueue_style('bsma-hf', $base . 'hf.css', [], BSMA_HF_VER);
    wp_enqueue_script('bsma-hf', $base . 'hf.js', [], BSMA_HF_VER, ['in_footer' => true, 'strategy' => 'defer']);
}, 20);

function bsma_hf_fa($s)
{
    return strtr((string) $s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function bsma_hf_jyear()
{
    $now = new DateTime('now', new DateTimeZone('Asia/Tehran'));
    $y = (int) $now->format('Y');
    return ((int) $now->format('md') >= 321) ? $y - 621 : $y - 622;
}

function bsma_hf_cart_count()
{
    return (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
}

function bsma_hf_count_html()
{
    $n = bsma_hf_cart_count();
    return '<span class="bhf-count" data-n="' . $n . '">' . esc_html(bsma_hf_fa($n)) . '</span>';
}

function bsma_hf_total_html()
{
    $t = (function_exists('WC') && WC()->cart) ? WC()->cart->get_cart_total() : '';
    return '<span class="bhf-total">' . wp_kses_post($t) . '</span>';
}

add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    $fragments['span.bhf-count'] = bsma_hf_count_html();
    $fragments['span.bhf-total'] = bsma_hf_total_html();
    return $fragments;
});

function bsma_hf_url($page, $fallback)
{
    if (function_exists('wc_get_page_permalink')) {
        $u = wc_get_page_permalink($page);
        if ($u) {
            return $u;
        }
    }
    return home_url($fallback);
}

function bsma_hf_icon($name)
{
    static $i = null;
    if (null === $i) {
        $i = [
            'grid' => '<rect class="sq" x="4" y="4" width="7" height="7" rx="1.6"/><rect class="sq b" x="13" y="4" width="7" height="7" rx="1.6"/><rect class="sq c" x="4" y="13" width="7" height="7" rx="1.6"/><rect class="sq d" x="13" y="13" width="7" height="7" rx="1.6"/>',
            'cabinet' => '<rect x="3.5" y="3" width="17" height="18" rx="2"/><path d="M3.5 7h17"/><g class="reel"><circle cx="12" cy="14" r="4.6"/><circle cx="12" cy="14" r="1.2"/><path d="M12 9.4v2.2M12 16.4v2.2M7.4 14h2.2M14.4 14h2.2"/></g>',
            'ext' => '<path d="M9 8h5a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><path d="M10 8V5.5h3V8M11.5 5.5V4M13 4.5l3.5-1.5M16.5 3c1.5 1 2 2.5 1.5 4"/><path d="M8 13h7"/><circle class="puff" cx="19" cy="8.5" r="1.6"/><circle class="puff b" cx="20.5" cy="6" r="1.2"/>',
            'bell' => '<g class="bell"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path class="clap" d="M10.5 20.5a1.8 1.8 0 0 0 3 0"/></g><path class="wave" d="M3 9c0-2 1-3.5 2-4.5"/><path class="wave b" d="M21 9c0-2-1-3.5-2-4.5"/>',
            'sprinkler' => '<path d="M12 3v5M8 8h8l-1.5 3h-5z"/><path d="M6 13.5h12"/><circle class="drop" cx="8" cy="17" r="1"/><circle class="drop b" cx="12" cy="18.5" r="1"/><circle class="drop c" cx="16" cy="17" r="1"/>',
            'detector' => '<path d="M5 9h14l-2 4H7z"/><path d="M9 13v1.5h6V13"/><circle class="ring" cx="12" cy="19" r="2"/><circle class="ring b" cx="12" cy="19" r="2"/><path d="M12 5v4"/>',
            'beam' => '<rect x="2.5" y="8" width="4" height="8" rx="1"/><rect x="17.5" y="8" width="4" height="8" rx="1"/><path class="dash" d="M6.5 12h11"/><path d="M6.5 12h11" opacity=".35"/>',
            'shield' => '<path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path class="draw" d="M8.5 12l2.5 2.5 4.5-5"/>',
            'doc' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path class="draw" d="M9 11h6"/><path class="draw b" d="M9 14h6"/><path class="draw c" d="M9 17h4"/>',
            'mega' => '<path d="M4 10v4h3l7 4V6L7 10z"/><path class="wave" d="M17 9.5c1 1.5 1 3.5 0 5"/><path class="wave b" d="M19.5 7.5c2 2.5 2 6.5 0 9"/>',
            'phone' => '<g class="shake"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></g>',
            'building' => '<path d="M4 21V5l8-2v18M12 8l8 2v11M3 21h18"/><path class="blink" d="M7 8h2M7 12h2M7 16h2M15 13h2M15 17h2"/>',
            'book' => '<path d="M12 6c-2-1.5-5-2-8-1.5v14c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5v-14c-3-.5-6 0-8 1.5z"/><path class="page" d="M12 6v14"/>',
            'search' => '<circle class="nod" cx="10.5" cy="10.5" r="6"/><path d="M15 15l5 5"/><path class="draw" d="M8 8.5a3 3 0 0 1 3-1.5"/>',
            'user' => '<g class="nod"><circle cx="12" cy="8" r="4"/></g><path d="M4 21c1-4.5 4-6.5 8-6.5s7 2 8 6.5"/>',
            'cart' => '<g class="roll"><path d="M3 4h2.5l2.2 11h10.6l2-8H6.8"/><circle class="wheel" cx="9.5" cy="19" r="1.6"/><circle class="wheel" cx="16.5" cy="19" r="1.6"/></g>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><ellipse class="globe-m" cx="12" cy="12" rx="4" ry="9"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path class="hand" d="M12 12V7.5"/><path d="M12 12h3.5"/>',
            'gear' => '<g class="gear"><circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/></g>',
            'compressor' => '<rect x="3" y="9" width="12" height="9" rx="4.5"/><circle cx="18" cy="8" r="3.2"/><path class="needle" d="M18 8l1.6-1.6"/><path d="M6 18v2.5M12 18v2.5"/>',
            'panel' => '<rect x="4" y="3" width="16" height="18" rx="2"/><rect x="7" y="6" width="10" height="4" rx="1"/><path class="blink" d="M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01M16 17.5h.01" stroke-width="2.6"/>',
            'flash' => '<path d="M9 3h6v5l-1.5 2.5V21h-3V10.5L9 8z"/><path class="wave" d="M5 5l-2-1.5M5 8.5H2.5M19 5l2-1.5M19 8.5h2.5"/>',
            'fire' => '<path d="M12 21c-4 0-6.5-2.8-6.5-6.3 0-4 3.3-5.7 3.8-9.7 2.5 1.6 3.4 3.8 3.4 5.7 1-1 1.6-2.3 1.7-3.5 2.3 1.9 4.1 4.6 4.1 7.5 0 3.5-2.5 6.3-6.5 6.3z"/><path class="draw" d="M12 21c-1.8 0-3-1.2-3-2.9 0-2 1.6-2.8 1.9-4.6 1.9 1.4 4.1 2.6 4.1 4.6 0 1.7-1.2 2.9-3 2.9z"/>',
            'door' => '<path d="M4 21V4h16v17"/><path class="door" d="M7 21V6.5l9 1.5v13"/><path d="M13.5 14h.01" stroke-width="2.6"/>',
            'radar' => '<circle cx="12" cy="15" r="1.4"/><path class="wave" d="M8.5 11.5a5 5 0 0 1 7 0"/><path class="wave b" d="M6 9a8.5 8.5 0 0 1 12 0"/><path d="M12 16.5V21M9 21h6"/>',
            'warn' => '<path d="M12 3.5l9 16H3z"/><path class="blink" d="M12 10v4.5M12 17.2h.01"/>',
            'spray' => '<rect x="7" y="8" width="7" height="13" rx="1.5"/><path d="M8.5 8V5h4v3M12.5 5.5h2.5"/><circle class="puff" cx="18" cy="5.5" r="1.3"/><circle class="puff b" cx="20" cy="3.8" r="1"/>',
            'home' => '<path d="M3.5 11L12 4l8.5 7"/><path d="M6 9.5V20h12V9.5"/><path class="blink" d="M10 20v-5h4v5"/>',
            'tg' => '<path d="M21 4.5L3 11.5l5.5 2 2 6 3-4 4.5 3.5z"/><path class="draw" d="M8.5 13.5l12.5-9"/>',
            'ig' => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle class="ring" cx="12" cy="12" r="3.5"/><circle cx="12" cy="12" r="3.5"/><path d="M16.8 7.2h.01" stroke-width="2.6"/>',
            'yt' => '<rect x="3" y="6" width="18" height="12" rx="3.5"/><path class="nod" d="M10.5 9.5v5l4.5-2.5z" fill="currentColor"/>',
            'ap' => '<circle cx="12" cy="12" r="8.5"/><g class="reel"><circle cx="12" cy="7.6" r="1.7"/><circle cx="16.4" cy="12" r="1.7"/><circle cx="12" cy="16.4" r="1.7"/><circle cx="7.6" cy="12" r="1.7"/></g>',
            'wa' => '<path d="M4 20l1.2-3.6A8 8 0 1 1 8 19z"/><g class="shake"><path d="M9 9c0 3 2.5 5.8 6 6.2l1.2-1.6-2-1-1 .8c-1-.4-2-1.4-2.4-2.4l.8-1-1-2z"/></g>',
            'chat' => '<path d="M4 5h16v11H9l-5 4z"/><path class="draw" d="M8 9.5h8"/><path class="draw b" d="M8 12.5h5"/>',
            'pin' => '<g class="nod"><path d="M12 21s-6-5.6-6-10.5A6 6 0 0 1 18 10.5C18 15.4 12 21 12 21z"/><circle cx="12" cy="10.5" r="2.2"/></g>',
            'std' => '<circle cx="12" cy="10" r="6.5"/><path d="M8.5 15.5L7 21l5-2.5 5 2.5-1.5-5.5"/><path class="draw" d="M9.3 10l1.9 1.9 3.6-3.6"/>',
            'truck' => '<path d="M2.5 6h11v10h-11zM13.5 9h4l3 3.5V16h-7"/><circle class="wheel" cx="6.5" cy="17.5" r="1.8"/><circle class="wheel" cx="17" cy="17.5" r="1.8"/>',
            'chev' => '<path d="M6 9l6 6 6-6"/>',
            'up' => '<path class="arrow" d="M12 19V5M6 11l6-6 6 6"/>',
        ];
    }
    return '<svg class="bhf-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($i[$name] ?? '') . '</svg>';
}

function bsma_hf_tile($icon, $cls = '')
{
    return '<span class="bhf-tile' . ($cls ? ' ' . $cls : '') . '">' . bsma_hf_icon($icon) . '</span>';
}

function bsma_hf_logo($cls = 'bhf-logo-svg')
{
    return '<svg class="' . esc_attr($cls) . '" viewBox="8 0 700 382" aria-hidden="true" focusable="false"><use href="#bhf-logo"/></svg>';
}

function bsma_hf_catalog()
{
    $u = 'https://bsma.ir/product-category/';
    $al = $u . '%d8%b3%db%8c%d8%b3%d8%aa%d9%85-%d8%a7%d8%b9%d9%84%d8%a7%d9%85-%d8%ad%d8%b1%db%8c%d9%82/';
    return [
        'firebox' => [
            'url' => $u . 'fire-box/',
            'subs' => [
                ['جعبه آتش‌نشانی فلزی', $u . 'fire-box/%d8%a8%d9%87%d8%b3%d8%a7%d8%b2%d8%a7%d9%86/metal-fire-box/'],
                ['جعبه آتش‌نشانی استیل', $u . 'fire-box/%d8%ac%d8%b9%d8%a8%d9%87-%d8%a2%d8%aa%d8%b4-%d9%86%d8%b4%d8%a7%d9%86%db%8c-%d8%a7%d8%b3%d8%aa%db%8c%d9%84/'],
            ],
        ],
        'groups' => [
            ['t' => 'اطفای حریق و تجهیزات آتش‌نشانی', 'c' => 'g1', 'items' => [
                ['ext', 'کپسول آتش‌نشانی', 'پودر و گاز، CO2', $u . '%d8%a7%d9%86%d9%88%d8%a7%d8%b9-%da%a9%d9%be%d8%b3%d9%88%d9%84-%d8%a2%d8%aa%d8%b4-%d9%86%d8%b4%d8%a7%d9%86%db%8c/'],
                ['sprinkler', 'سیستم اطفای حریق', '', $u . '%d8%b3%db%8c%d8%b3%d8%aa%d9%85-%d8%a7%d8%b7%d9%81%d8%a7-%d8%ad%d8%b1%db%8c%d9%82/'],
                ['compressor', 'کمپرسور و سیستم پرکن کپسول', '', $u . '%da%a9%d9%85%d9%be%d8%b1%d8%b3%d9%88%d8%b1-%d9%81%d8%b4%d8%a7%d8%b1-%d8%a8%d8%a7%d9%84%d8%a7-%d9%88-%d8%b3%db%8c%d8%b3%d8%aa%d9%85-%d9%be%d8%b1%da%a9%d9%86-%da%a9%d9%be%d8%b3%d9%88%d9%84/'],
                ['fire', 'تجهیزات آتش‌نشانی', '', $u . '%d8%aa%d8%ac%d9%87%db%8c%d8%b2%d8%a7%d8%aa-%d8%a2%d8%aa%d8%b4-%d9%86%d8%b4%d8%a7%d9%86%db%8c/'],
                ['door', 'درب ضد حریق (دودبند)', '', 'https://bsma.ir/product/%d8%af%d8%b1%d8%a8-%d8%af%d9%88%d8%af%d8%a8%d9%86%d8%af/'],
            ]],
            ['t' => 'اعلام حریق', 'c' => 'g2', 'items' => [
                ['bell', 'سیستم اعلام حریق', 'همه‌ی برندها', $al],
                ['beam', 'بیم دتکتور', '', $u . 'beam-detector/'],
                ['spray', 'تجهیزات تست و نگهداری', 'برای سیستم‌های اعلام حریق', $u . '%d8%aa%d8%ac%d9%87%db%8c%d8%b2%d8%a7%d8%aa-%d8%aa%d8%b3%d8%aa-%d9%88-%d9%86%da%af%d9%87%d8%af%d8%a7%d8%b1%db%8c-%d8%b3%db%8c%d8%b3%d8%aa%d9%85%d9%87%d8%a7%db%8c-%d8%a7%d8%b9%d9%84%d8%a7%d9%85/'],
            ], 'chips' => [
                ['GFE', $al . '%d8%a7%d8%b9%d9%84%d8%a7%d9%85-%d8%ad%d8%b1%db%8c%d9%82-gfe/'],
                ['GFE آدرس‌پذیر', $al . '%d8%a7%d8%b9%d9%84%d8%a7%d9%85-%d8%ad%d8%b1%db%8c%d9%82-gfe/%d8%a2%d8%af%d8%b1%d8%b3-%d9%be%d8%b0%db%8c%d8%b1-%d8%a7%d8%b9%d9%84%d8%a7%d9%85-%d8%ad%d8%b1%db%8c%d9%82-gfe/'],
                ['GFE کانونشنال', $al . '%d8%a7%d8%b9%d9%84%d8%a7%d9%85-%d8%ad%d8%b1%db%8c%d9%82-gfe/%da%a9%d8%a7%d9%86%d9%88%d9%86%d8%b4%d9%86%d8%a7%d9%84-%d8%a7%d8%b9%d9%84%d8%a7%d9%85-%d8%ad%d8%b1%db%8c%d9%82-gfe/'],
                ['تکنیم', $al . '%d8%a7%d8%b9%d9%84%d8%a7%d9%85-%d8%ad%d8%b1%db%8c%d9%82-%d8%aa%da%a9%d9%86%db%8c%d9%85/'],
                ['سایر برندها', $al . 'other-fire-alarms/'],
            ]],
            ['t' => 'ایمنی و امنیت', 'c' => 'g3', 'items' => [
                ['shield', 'تجهیزات ایمنی', '', $u . '%d8%aa%d8%ac%d9%87%db%8c%d8%b2%d8%a7%d8%aa-%d8%a7%db%8c%d9%85%d9%86%db%8c/'],
                ['warn', 'علائم هشداردهنده', '', $u . '%d8%b9%d9%84%d8%a7%d8%a6%d9%85-%d9%87%d8%b4%d8%af%d8%a7%d8%b1-%d8%af%d9%87%d9%86%d8%af%d9%87/'],
                ['flash', 'چراغ‌قوه‌ی شارژی', '', $u . '%da%86%d8%b1%d8%a7%d8%ba-%d9%82%d9%88%d9%87-%d8%b4%d8%a7%d8%b1%da%98%db%8c/'],
                ['radar', 'دستگاه زنده‌یاب', '', $u . '%d8%af%d8%b3%d8%aa%da%af%d8%a7%d9%87-%d8%b2%d9%86%d8%af%d9%87-%db%8c%d8%a7%d8%a8/'],
                ['detector', 'دتکتور دزدگیر', '', $u . 'burglar-alarm-equipment/detector/'],
                ['panel', 'کنترل پنل دزدگیر', '', $u . 'burglar-alarm-equipment/alarm-control-panel/'],
            ]],
        ],
    ];
}

function bsma_hf_firebox_card($extra_cls = '')
{
    $c = bsma_hf_catalog();
    ob_start(); ?>
<a class="bhf-fb-card<?php echo $extra_cls ? ' ' . esc_attr($extra_cls) : ''; ?>" href="<?php echo esc_url($c['firebox']['url']); ?>">
<svg class="bhf-fb-art" viewBox="0 0 120 150" aria-hidden="true" focusable="false"><rect x="14" y="8" width="92" height="134" rx="8" fill="#fff" fill-opacity=".14" stroke="#fff" stroke-width="3"/><rect x="22" y="16" width="76" height="16" rx="3" fill="#fff" fill-opacity=".9"/><text x="60" y="28" text-anchor="middle" font-size="10" font-weight="900" fill="#C00E17">آتش‌نشانی</text><g class="bhf-fb-reel"><circle cx="60" cy="72" r="26" fill="none" stroke="#fff" stroke-width="3"/><circle cx="60" cy="72" r="17" fill="none" stroke="#FFB21A" stroke-width="5" stroke-dasharray="6 4"/><circle cx="60" cy="72" r="5" fill="#fff"/><path d="M60 46v52M34 72h52" stroke="#fff" stroke-width="2" opacity=".5"/></g><path d="M86 94c6 8 8 20 2 30" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/><g transform="translate(30 104)"><rect y="6" width="14" height="30" rx="4" fill="#fff"/><rect x="3" width="8" height="7" rx="2" fill="#fff"/><rect y="18" width="14" height="4" fill="#C00E17" opacity=".5"/></g><g class="bhf-fb-door"><rect x="14" y="8" width="92" height="134" rx="8" fill="#fff" fill-opacity=".08" stroke="#fff" stroke-width="3"/><path d="M26 40l20-20M26 70l40-40" stroke="#fff" stroke-width="2" opacity=".35" stroke-linecap="round"/><rect x="94" y="66" width="5" height="18" rx="2.5" fill="#FFB21A"/></g></svg>
<span class="bhf-fb-badge">تولید کارخانه‌ی بهسازان</span>
<b>جعبه آتش‌نشانی</b>
<span class="bhf-fb-sub">ساخت خودمان از ۱۳۸۵، با تأییدیه‌ی سازمان آتش‌نشانی اصفهان</span>
<span class="bhf-fb-all">مشاهده‌ی همه‌ی جعبه‌ها ←</span>
</a>
<div class="bhf-fb-links"><?php foreach ($c['firebox']['subs'] as $s) : ?><a href="<?php echo esc_url($s[1]); ?>"><?php echo bsma_hf_tile('cabinet', 'bhf-light'); ?><?php echo esc_html($s[0]); ?></a><?php endforeach; ?></div>
<?php
    return ob_get_clean();
}

function bsma_hf_logo_sprite()
{
    $p = [
    'rb' => 'M 359 37C358 37 357 37 356 38C355 38 353 39 352 40C350 40 349 41 348 42C347 42 345 43 344 44C342 44 340 45 339 46C335 48 327 52 326 52C326 52 324 52 322 53C320 55 316 56 314 57C312 58 308 60 305 61C302 63 299 64 299 64C298 65 297 65 296 66C295 66 293 67 291 68C290 68 288 69 287 70C287 70 285 71 283 72C282 72 279 73 278 74C275 75 267 79 265 80C264 80 262 81 261 82C259 83 258 84 257 84C256 84 252 86 249 87C245 89 241 91 240 92C238 92 236 93 235 94C235 94 233 95 231 96C230 96 227 97 226 98C225 98 223 99 222 100C221 100 219 101 219 102C218 102 216 103 214 103C212 104 210 105 208 106C207 107 206 108 205 108C205 108 203 109 200 110C198 111 195 112 195 112C195 112 194 113 192 114C191 115 189 116 189 116C188 116 184 117 175 122C173 123 171 124 171 124C171 124 169 124 166 126C160 129 154 131 152 132C151 132 149 133 149 134C148 134 146 135 144 136C143 136 141 137 140 138C138 139 137 140 137 140C136 140 134 140 132 141C130 143 128 144 127 144C126 144 124 145 122 146C118 148 117 148 116 144C116 142 116 126 116 107C116 69 116 70 113 70C112 70 105 70 96 70L81 70 80 72L79 74 79 119C79 148 79 164 79 165C78 166 74 169 66 172C65 172 64 173 63 174C62 174 60 175 59 175C57 176 53 178 49 180C46 182 42 183 41 184C39 184 37 185 37 186C36 186 33 187 32 188C30 189 28 189 28 190C28 190 26 191 25 191C23 192 15 196 13 197C12 197 11 198 11 200C10 202 10 202 12 206C13 210 16 215 17 216C19 217 23 216 25 215C27 214 29 213 30 212C31 212 35 210 39 209C47 204 54 202 57 201C58 201 61 200 63 199C74 193 78 192 79 194C79 194 79 226 79 264C79 316 79 335 80 337C81 340 82 340 96 341C134 342 160 341 165 339C166 338 168 337 169 337C176 334 183 327 186 322L188 318 189 306C189 299 190 293 190 292C191 289 191 226 190 221C189 219 189 213 189 207C188 191 188 189 185 181C182 172 173 167 164 170C162 171 160 171 158 172C157 172 154 173 153 174C151 174 149 175 147 176C146 176 144 177 143 178C143 178 141 179 139 180C137 180 135 182 133 182C126 186 120 189 118 189C118 189 117 188 117 187C116 185 116 175 117 174C118 173 127 168 128 168C129 168 130 167 131 166C133 165 135 164 136 164C137 164 139 163 140 163C141 162 143 161 144 161C145 160 147 159 148 159C153 156 161 153 163 152C164 152 165 151 165 151C165 150 167 150 168 149C170 148 175 146 179 144C183 142 187 141 187 141C187 141 190 139 194 137C198 135 203 133 205 132C206 132 208 131 209 130C209 130 211 129 212 129C214 128 216 127 217 126C219 126 221 125 223 124C224 123 226 122 227 122C228 121 230 120 231 120C233 119 234 119 235 118C236 118 237 117 238 117C238 117 241 116 243 115C252 110 256 108 257 108C257 108 258 107 260 107C261 106 263 105 264 104C266 104 270 102 273 100C276 99 280 97 282 96C283 96 285 95 286 94C288 93 289 93 289 93C290 93 291 92 293 91C295 90 297 89 299 88C300 88 302 87 303 86C304 86 306 85 307 84C309 84 310 83 311 83C312 82 314 81 316 81C317 80 319 79 320 78C321 78 323 77 324 77C326 76 328 75 329 75C329 74 331 73 333 73C334 72 336 71 337 70C338 70 340 69 341 69C343 68 345 67 346 66C347 66 350 65 351 64C353 64 355 63 356 62C359 60 362 60 367 62C369 64 371 65 372 65C373 65 375 66 375 66C376 67 377 68 379 68C381 69 383 70 384 71C384 71 386 72 387 72C388 72 390 74 392 75C394 76 397 77 397 77C397 77 398 77 399 78C400 79 402 80 404 80C405 81 407 82 409 82C410 83 413 84 414 85C415 85 417 86 417 86C419 87 427 91 430 92C432 93 434 94 435 95C436 95 438 96 439 97C440 97 442 98 443 98C448 101 457 105 457 105C458 105 459 106 460 106C461 107 463 108 464 108C465 109 469 111 473 113C476 114 480 116 481 116C482 117 484 118 486 119C488 120 490 121 490 121C491 121 492 121 493 122C495 123 497 124 498 125C500 125 502 126 504 127C505 128 507 129 507 129C508 129 509 129 510 130C511 131 514 132 516 133C517 133 519 134 520 134C520 135 522 136 523 136C525 137 527 138 528 139C529 139 531 140 532 141C534 141 536 142 536 142C536 143 538 143 539 144C541 144 543 145 544 146C547 147 555 151 558 153C560 153 562 154 562 154C564 156 575 161 575 161C576 161 577 161 578 162C579 163 581 164 583 164C584 165 586 166 588 167C589 167 591 168 592 169C594 169 602 173 604 175C605 175 607 176 608 176C610 177 612 178 613 178C615 179 616 180 617 181C618 181 620 182 621 182C622 183 624 184 626 184C627 185 629 186 630 186C630 187 632 188 634 188C636 189 638 190 639 191C640 191 642 192 644 193C645 193 647 194 648 195C648 195 650 196 652 197C653 197 655 198 655 199C656 199 658 200 660 200C661 201 663 202 664 202C665 203 667 204 668 204C670 205 671 206 672 206C673 207 674 208 674 208C674 208 677 209 679 210C682 211 684 212 685 212C686 213 688 214 690 215C694 217 697 217 699 215C700 214 702 211 705 206L707 202 706 200C705 197 704 197 701 196C699 195 697 194 697 194C696 193 694 192 693 192C691 191 690 191 689 190C688 189 686 188 685 188C684 188 681 187 680 185C678 184 676 184 676 184C675 184 674 183 673 182C672 181 670 180 669 180C668 179 666 178 665 178C664 177 662 176 660 176C658 175 656 174 656 174C656 174 654 173 652 172C651 171 648 170 647 169C646 169 645 168 644 168C643 168 639 166 635 164C631 162 627 160 626 160C624 159 622 158 621 158C621 157 619 156 618 156C617 155 615 155 614 154C613 153 610 152 608 152C607 151 605 150 605 150C604 149 594 144 591 144C590 143 589 142 588 142C587 141 585 140 584 140C582 139 581 138 580 138C579 137 577 136 576 136C575 136 572 135 570 134C563 130 561 129 558 128C557 127 554 126 553 126C552 125 550 124 550 124C549 124 547 123 544 121C542 120 540 120 540 120C539 120 538 119 537 118C536 117 533 116 532 116C530 115 528 114 527 113C526 113 524 112 524 112C523 112 521 111 520 110C519 109 517 108 515 108C514 107 511 106 510 105C508 105 505 103 502 102C500 101 496 99 494 98C493 97 490 96 489 96C487 95 485 94 484 93C483 93 482 92 481 92C478 91 470 87 468 86C467 85 466 84 464 84C463 84 460 82 458 81C457 80 455 80 454 80C454 80 453 79 452 78C451 78 448 76 447 76C445 75 443 74 442 74C441 73 439 72 437 72C436 71 434 70 434 70C434 69 432 69 430 68C427 67 425 66 424 66C424 65 422 64 421 64C420 63 418 62 417 62C416 61 414 60 412 60C410 59 408 58 408 58C408 58 407 57 405 56C403 55 401 54 400 53C400 53 398 52 397 52C396 51 393 50 391 49C382 45 380 44 379 44C378 44 377 43 375 42C374 41 372 40 371 40C370 40 367 39 366 38C362 36 362 36 359 37',
    'm' => 'M 359 77C359 78 357 79 356 80C355 80 353 81 352 82C352 82 350 83 349 84C348 85 346 86 344 87C342 88 340 90 339 91C338 92 336 93 336 93C335 94 334 95 333 96C329 98 330 89 330 219C330 283 330 336 330 337C330 338 331 339 332 340C334 341 335 341 348 340C362 340 366 340 367 338C368 338 368 319 368 234C368 127 368 125 366 122C366 121 365 119 364 117C363 112 360 109 356 109C354 109 353 109 352 107C350 103 354 98 359 97C361 97 362 97 365 98C367 99 370 100 371 100C375 101 386 109 388 111C388 112 389 113 389 114C392 117 395 121 397 126C399 130 400 130 400 136C400 139 401 143 401 145C402 148 402 164 402 243C402 307 402 337 403 338C404 340 405 341 422 341C438 341 439 340 440 338C441 337 441 312 441 247C441 149 441 155 444 146C445 142 446 142 448 140C454 137 463 141 468 149C468 151 469 152 470 153C471 154 471 156 472 160C472 163 473 167 474 170L475 173 475 248C475 290 475 327 475 331C476 341 474 340 494 340L510 340 512 339L513 338 513 262C513 180 513 179 511 174C510 172 509 169 509 166C507 159 505 155 498 147C493 140 484 135 476 132C474 131 473 130 472 130C471 129 469 128 468 128C466 127 465 127 464 126C463 125 461 124 459 124C458 123 456 122 455 121C454 121 451 119 448 118C445 117 442 115 441 114C435 112 428 108 427 108C426 108 424 107 423 106C422 105 420 104 418 104C417 103 415 102 414 102C413 101 412 100 410 100C409 99 407 98 407 98C407 97 405 97 403 96C402 96 400 95 399 94C398 93 396 92 394 92C393 91 391 90 390 89C389 89 387 88 386 88C385 87 384 86 383 86C382 85 380 84 378 84C377 83 375 82 375 82C374 81 372 81 371 80C369 79 367 78 366 78C363 76 361 76 359 77',
    's' => 'M 300 106C299 106 298 107 297 107C296 108 292 110 284 113C282 114 279 116 278 116C277 116 276 117 274 118C273 118 270 119 269 120C267 121 265 121 264 122C263 123 261 124 260 124C258 125 256 126 255 127C252 129 248 131 247 132C244 132 236 137 236 137C235 138 234 139 232 139C230 140 228 142 226 144C221 149 217 154 216 156C215 157 214 158 214 159C212 161 209 170 208 178C206 192 206 196 206 206C206 223 208 234 211 239C213 244 214 244 219 245C222 246 223 246 226 246C228 245 231 245 232 245C234 245 237 244 239 243C246 241 259 240 264 241C268 242 272 248 273 256C273 259 273 263 274 264C274 265 274 276 274 287C274 310 274 312 271 318C271 320 267 323 265 324C264 324 262 325 261 326C259 326 256 327 254 327C249 327 249 327 247 325C246 324 244 323 244 322C244 321 243 320 242 320C241 317 240 310 239 290C239 278 240 278 223 280C209 281 208 281 206 284L204 286 205 304L205 322 207 326C208 328 210 330 210 331C212 333 217 336 220 337C222 337 224 338 226 338C234 341 279 341 285 338C287 338 290 337 291 337C298 335 306 329 308 323C309 322 310 320 310 319C312 317 313 309 313 305C313 304 313 301 314 299C315 294 315 252 314 248C314 246 313 242 313 238C312 230 312 226 310 224C309 223 308 222 308 220C307 218 304 215 301 213C298 212 292 212 288 213C286 214 282 215 278 215C275 216 271 217 269 218C265 219 257 219 254 218C254 218 252 217 251 216C249 215 249 216 247 207C246 205 246 203 246 202C245 201 245 192 245 183C245 167 245 167 246 164C247 163 248 160 248 159C249 157 249 155 250 154C250 154 251 152 252 151C256 145 263 139 269 136C270 136 272 135 273 134C274 134 277 133 281 132C284 132 287 131 288 130C290 129 292 129 299 130C303 131 306 130 307 127C308 125 308 107 307 105C306 104 302 104 300 106',
    'ab' => 'M 546 170C541 171 537 174 536 177C534 183 534 211 535 214C536 217 539 218 545 220C547 221 550 222 551 222C553 223 557 224 559 224C562 225 565 225 567 226C571 227 570 227 571 225C572 224 572 221 573 210C573 195 573 195 576 192L578 191 582 191C585 192 587 193 590 194C591 196 593 197 593 197C594 197 599 202 600 204C600 204 601 206 602 208L603 211 603 226C604 245 604 245 599 244C595 244 589 243 587 242C586 241 584 240 582 240C579 239 577 238 575 238C574 237 570 236 567 236C564 235 561 234 560 234C558 233 556 233 552 233C542 233 536 236 533 243C532 245 530 258 530 263C530 266 530 269 529 271C528 276 528 301 529 304C530 307 531 312 532 318C533 327 538 335 547 339C550 341 557 342 563 343C572 343 601 343 604 342C606 341 609 341 613 341L618 341 619 342C621 344 621 356 619 358L619 359 350 359L82 359 81 360C78 362 78 376 81 379L82 380 360 380L638 380 639 379L641 378 641 303C641 222 641 224 638 220C638 219 637 217 636 216C636 214 635 213 634 212C633 211 629 209 627 206C624 203 620 201 619 200C618 199 617 199 616 198C614 196 609 194 606 192C604 191 602 190 601 190C600 189 598 188 597 188C595 187 593 186 593 186C592 185 590 184 589 184C587 183 585 182 584 182C583 181 581 180 580 180C579 179 577 178 576 178C575 177 573 176 571 176C569 175 567 175 566 174C562 172 550 170 546 170',
    ];
    return '<svg class="bhf-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>'
        . '<path id="bhf-lp-rb" d="' . $p['rb'] . '"/><path id="bhf-lp-m" d="' . $p['m'] . '"/><path id="bhf-lp-s" d="' . $p['s'] . '"/><path id="bhf-lp-ab" d="' . $p['ab'] . '"/>'
        . '<clipPath id="bhf-logo-clip"><use href="#bhf-lp-rb"/><use href="#bhf-lp-m"/><use href="#bhf-lp-s"/><use href="#bhf-lp-ab"/></clipPath>'
        . '<mask id="bhf-logo-mask" maskUnits="userSpaceOnUse" x="0" y="-160" width="720" height="600"><rect x="0" y="-160" width="720" height="600" fill="#fff"/><ellipse cx="129" cy="262" rx="22" ry="44" fill="#000"/><ellipse cx="593" cy="291" rx="22" ry="31" fill="#000"/></mask>'
        . '<linearGradient id="bhf-glint-g" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#fff" stop-opacity=".75"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>'
        . '<radialGradient id="bhf-flame-g" cx=".5" cy=".75" r=".7"><stop offset="0" stop-color="#FFE27A"/><stop offset=".45" stop-color="#FFB21A"/><stop offset="1" stop-color="#F0333B"/></radialGradient>'
        . '<symbol id="bhf-logo" class="run" viewBox="8 0 700 382">'
        . '<g class="lb" mask="url(#bhf-logo-mask)"><g class="pt p-s"><use href="#bhf-lp-s"/></g><g class="pt p-m"><use href="#bhf-lp-m"/></g><g class="pt p-ab"><use href="#bhf-lp-ab"/></g><g class="pt p-rb"><use href="#bhf-lp-rb"/></g></g>'
        . '<g clip-path="url(#bhf-logo-clip)"><rect class="glint" x="0" y="0" width="150" height="420" fill="url(#bhf-glint-g)" transform="skewX(-20)"/></g>'
        . '<g class="flame"><path class="core" d="M359 34c-9-6-11-14-6-22 1 5 4 7 6 7-1-6 1-12 7-17-2 7 2 12 5 16 3 5 1 12-4 16z" fill="url(#bhf-flame-g)"/></g>'
        . '<g class="pf" fill="#fff" stroke="#DDE3EA" stroke-width="1.5"><circle cx="352" cy="20" r="8"/><circle cx="364" cy="14" r="10"/><circle cx="374" cy="22" r="7"/></g>'
        . '</symbol></defs></svg>';
}

add_action('wp_body_open', function () {
    if (!bsma_hf_active()) {
        return;
    }
    $c = bsma_hf_catalog();
    $logged = is_user_logged_in();
    $acc = bsma_hf_url('myaccount', '/my-account/');
    echo bsma_hf_logo_sprite(); ?>
<span id="bhf-top" aria-hidden="true"></span>
<header class="bhf bhf-hdr" id="bhf-hdr">
  <div class="bhf-util"><div class="bhf-util-in">
    <div class="bhf-ticker"><ul id="bhf-ticker">
      <li><?php echo bsma_hf_icon('shield'); ?>دارای تأییدیه‌ی سازمان آتش‌نشانی و خدمات ایمنی شهرداری اصفهان</li>
      <li><?php echo bsma_hf_icon('std'); ?>دارای نشان استاندارد ملی ایران</li>
      <li><?php echo bsma_hf_icon('chat'); ?>مشاوره‌ی رایگان برای طراحی و اجرای سیستم اعلام و اطفای حریق</li>
    </ul></div>
    <a class="bhf-tel" href="tel:<?php echo esc_attr(BSMA_HF_PHONE); ?>" dir="ltr"><?php echo bsma_hf_icon('phone'); ?><?php echo esc_html(BSMA_HF_PHONE_TXT); ?></a>
  </div></div>

  <div class="bhf-bar"><div class="bhf-bar-in">
    <button class="bhf-burger bhf-m" id="bhf-burger" aria-expanded="false" aria-controls="bhf-drawer" aria-label="باز کردن منو"><i></i></button>
    <a class="bhf-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="بهسازان سرای مهر آهنگ، صفحه‌ی اصلی">
      <?php echo bsma_hf_logo(); ?>
      <span class="bhf-logo-cap"><b>بهسازان سرای مهر آهنگ</b><span>تولیدکننده‌ی تجهیزات آتش‌نشانی از ۱۳۸۵</span></span>
    </a>
    <form class="bhf-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <label class="bhf-sr" for="bhf-q">جست‌وجو در محصولات</label>
      <input id="bhf-q" type="search" name="s" placeholder="جست‌وجو: کپسول، دتکتور…" autocomplete="off" aria-controls="bhf-sugg" aria-expanded="false" value="<?php echo esc_attr(get_search_query()); ?>">
      <input type="hidden" name="post_type" value="product">
      <button class="bhf-s-ic" aria-label="جست‌وجو"><?php echo bsma_hf_tile('search'); ?></button>
      <div class="bhf-sugg" id="bhf-sugg" role="listbox"></div>
    </form>
    <div class="bhf-actions">
      <button class="bhf-icon-btn bhf-m" id="bhf-m-search" aria-label="جست‌وجو"><?php echo bsma_hf_tile('search', 'bhf-light'); ?></button>
      <a class="bhf-lang" href="<?php echo esc_url(BSMA_HF_AR_URL); ?>" hreflang="ar">
        <span class="bhf-flag"><?php echo bsma_hf_icon('globe'); ?></span>
        <span class="bhf-lang-t"><span lang="ar">العربية</span><small>نسخه‌ی عربی سایت</small></span>
      </a>
      <a class="bhf-acc<?php echo $logged ? '' : ' digits-login-modal'; ?>" href="<?php echo esc_url($acc); ?>"<?php echo $logged ? '' : ' data-bhf-login attr-disclick="1" type="1"'; ?>><?php echo bsma_hf_tile('user', 'bhf-steel'); ?><?php echo $logged ? 'حساب من' : 'ورود | ثبت‌نام'; ?></a>
      <a class="bhf-cart" href="<?php echo esc_url(bsma_hf_url('cart', '/cart/')); ?>" aria-label="سبد خرید">
        <?php echo bsma_hf_tile('cart'); ?><?php echo bsma_hf_count_html(); ?>
        <span class="bhf-sum">سبد خرید<b><?php echo bsma_hf_total_html(); ?></b></span>
      </a>
    </div>
  </div></div>

  <div class="bhf-navrow"><div class="bhf-nav-in">
    <nav class="bhf-nav" aria-label="منوی اصلی">
      <button class="bhf-mega-btn" id="bhf-mega-btn" aria-expanded="false" aria-controls="bhf-mega"><?php echo bsma_hf_tile('grid'); ?>محصولات<?php echo bsma_hf_icon('chev'); ?></button>
      <a class="bhf-feat" href="<?php echo esc_url($c['firebox']['url']); ?>"><?php echo bsma_hf_tile('cabinet'); ?>جعبه آتش‌نشانی<span class="bhf-mini">تولید ما</span></a>
      <a href="<?php echo esc_url($c['groups'][0]['items'][0][3]); ?>"><?php echo bsma_hf_tile('ext'); ?>کپسول آتش‌نشانی</a>
      <a href="<?php echo esc_url($c['groups'][1]['items'][0][3]); ?>"><?php echo bsma_hf_tile('bell'); ?>اعلام حریق</a>
      <a class="bhf-opt" href="<?php echo esc_url($c['groups'][0]['items'][1][3]); ?>"><?php echo bsma_hf_tile('sprinkler'); ?>اطفای حریق</a>
      <a href="<?php echo esc_url(home_url('/blogs/')); ?>"><?php echo bsma_hf_tile('doc', 'bhf-steel'); ?>مقالات</a>
      <a class="bhf-opt" href="<?php echo esc_url(home_url('/about-us/')); ?>"><?php echo bsma_hf_tile('building', 'bhf-steel'); ?>درباره‌ی ما</a>
      <a href="<?php echo esc_url(home_url('/contact-2/')); ?>"><?php echo bsma_hf_tile('phone', 'bhf-steel'); ?>تماس با ما</a>
    </nav>
  </div></div>

</header>
<?php
}, 5);

add_action('wp_footer', function () {
    if (!bsma_hf_active()) {
        return;
    }
    $c = bsma_hf_catalog();
    $g = $c['groups'];
    $foot_cats = [['cabinet', 'جعبه آتش‌نشانی بهسازان', '', $c['firebox']['url']], $g[0]['items'][0], $g[0]['items'][1], $g[1]['items'][0], $g[1]['items'][1], $g[2]['items'][0]];
    $apps = [
        ['wa', 'واتساپ', 'https://wa.me/' . BSMA_HF_CHAT, ''],
        ['tg', 'تلگرام', 'https://t.me/+' . BSMA_HF_CHAT, ''],
        ['et', 'ایتا', BSMA_HF_EITAA ? 'https://eitaa.com/' . BSMA_HF_EITAA : 'https://eitaa.com/', BSMA_HF_EITAA ? '' : 'ایتا'],
        ['bl', 'بله', BSMA_HF_BALE ? 'https://ble.ir/' . BSMA_HF_BALE : 'https://web.bale.ai/', BSMA_HF_BALE ? '' : 'بله'],
        ['rb', 'روبیکا', BSMA_HF_RUBIKA ? 'https://rubika.ir/' . BSMA_HF_RUBIKA : 'https://web.rubika.ir/', BSMA_HF_RUBIKA ? '' : 'روبیکا'],
    ];
    $glyph = ['et' => 'ا', 'bl' => 'ب', 'rb' => 'ر'];
    $acc = bsma_hf_url('myaccount', '/my-account/');
    ?>
<footer class="bhf bhf-ftr" id="bhf-ftr">
  <div class="bhf-embers" aria-hidden="true"></div>
  <div class="bhf-ftr-in">
    <section class="bhf-cta">
      <?php echo bsma_hf_tile('bell', 'bhf-light bhf-big'); ?>
      <div>
        <p class="bhf-cta-t">سیستم اعلام و اطفای حریق برای پروژه‌تان می‌خواهید؟</p>
        <p>مشاوره، نصب و اجرا و خدمات پس از فروش، با تأییدیه‌ی سازمان آتش‌نشانی اصفهان</p>
      </div>
      <div class="bhf-cta-btns">
        <a class="bhf-cbtn bhf-w" href="tel:<?php echo esc_attr(BSMA_HF_PHONE); ?>"><?php echo bsma_hf_tile('phone'); ?><span dir="ltr"><?php echo esc_html(BSMA_HF_PHONE_TXT); ?></span></a>
        <button class="bhf-cbtn bhf-g bhf-msg-open"><?php echo bsma_hf_tile('chat', 'bhf-wa'); ?>ارسال پیام</button>
      </div>
    </section>

    <div class="bhf-trust">
      <div class="bhf-tr"><?php echo bsma_hf_tile('clock', 'bhf-amber'); ?><div><b>از سال ۱۳۸۵</b><span>تولید جعبه و قرقره‌ی آتش‌نشانی</span></div></div>
      <div class="bhf-tr"><?php echo bsma_hf_tile('shield'); ?><div><b>تأییدیه‌ی آتش‌نشانی</b><span>شهرداری اصفهان، شماره‌ی ۶۷۵۴/۷</span></div></div>
      <div class="bhf-tr"><?php echo bsma_hf_tile('std', 'bhf-amber'); ?><div><b>استاندارد ملی ایران</b><span>دارای نشان استاندارد</span></div></div>
      <div class="bhf-tr"><?php echo bsma_hf_tile('gear', 'bhf-steel'); ?><div><b>نصب و خدمات</b><span>اجرا و پس از فروش</span></div></div>
    </div>

    <div class="bhf-cols">
      <div class="bhf-about">
        <a class="bhf-plate" href="<?php echo esc_url(home_url('/')); ?>" aria-label="صفحه‌ی اصلی"><?php echo bsma_hf_logo(); ?></a>
        <p>شرکت بهسازان سرای مهر آهنگ از سال ۱۳۸۵ جعبه و قرقره‌ی آتش‌نشانی تولید می‌کند و امروز همه‌ی تجهیزات مبارزه با حریق را برای کارخانه‌ها، ساختمان‌های مسکونی و مجتمع‌های تجاری تأمین می‌کند؛ از مشاوره تا نصب، اجرا و خدمات پس از فروش.</p>
      </div>
      <div>
        <p class="bhf-col-t">دسته‌بندی‌ها</p>
        <ul class="bhf-flinks">
          <?php foreach ($foot_cats as $it) : ?><li><a href="<?php echo esc_url($it[3]); ?>"><?php echo bsma_hf_tile($it[0]); ?><?php echo esc_html($it[1]); ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <div>
        <p class="bhf-col-t">دسترسی سریع</p>
        <ul class="bhf-flinks">
          <li><a href="<?php echo esc_url(home_url('/blogs/')); ?>"><?php echo bsma_hf_tile('doc', 'bhf-steel'); ?>مقالات و راهنماها</a></li>
          <li><a href="<?php echo esc_url(home_url('/news/')); ?>"><?php echo bsma_hf_tile('mega', 'bhf-steel'); ?>اخبار سایت</a></li>
          <li><a href="<?php echo esc_url(home_url('/about-us/')); ?>"><?php echo bsma_hf_tile('building', 'bhf-steel'); ?>درباره‌ی ما</a></li>
          <li><a href="<?php echo esc_url(home_url('/contact-2/')); ?>"><?php echo bsma_hf_tile('phone', 'bhf-steel'); ?>تماس با ما</a></li>
          <li><a href="<?php echo esc_url(BSMA_HF_CATALOG); ?>"><?php echo bsma_hf_tile('book', 'bhf-amber'); ?>کاتالوگ محصولات</a></li>
          <li><a href="<?php echo esc_url(BSMA_HF_AR_URL); ?>" hreflang="ar"><?php echo bsma_hf_tile('globe', 'bhf-steel'); ?>نسخه‌ی عربی (العربية)</a></li>
        </ul>
      </div>
      <div>
        <p class="bhf-col-t">تماس و نشانی</p>
        <div class="bhf-office">
          <p class="bhf-office-t"><?php echo bsma_hf_tile('building'); ?>دفتر اصفهان</p>
          <a class="bhf-map" data-ll="<?php echo esc_attr(BSMA_HF_OFFICE_LL); ?>" data-q="بهسازان سرای مهر آهنگ" href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo esc_attr(BSMA_HF_OFFICE_LL); ?>">
            <span>اصفهان، نبش خیابان وحید و محتشم کاشانی، مجتمع تجاری عزیزخانی، طبقه‌ی ۳، واحد ۳۳</span>
            <span class="bhf-go"><?php echo bsma_hf_tile('pin'); ?>مسیریابی</span>
          </a>
          <div class="bhf-phones">
            <div class="bhf-ph"><span>تلفن</span><a href="tel:+983136242532" dir="ltr">031-3624 2532</a><button class="bhf-copy" data-copy="03136242532">کپی</button></div>
            <div class="bhf-ph"><span>تلفن</span><a href="tel:+983136247584" dir="ltr">031-3624 7584</a><button class="bhf-copy" data-copy="03136247584">کپی</button></div>
            <div class="bhf-ph"><span>همراه</span><a href="tel:+989135454643" dir="ltr">0913 545 4643</a><button class="bhf-copy" data-copy="09135454643">کپی</button></div>
          </div>
        </div>
        <div class="bhf-social">
          <a href="https://t.me/bsmacompany" aria-label="کانال تلگرام"><?php echo bsma_hf_tile('tg', 'bhf-tg'); ?></a>
          <a href="https://www.instagram.com/bsma.ir/" aria-label="اینستاگرام"><?php echo bsma_hf_tile('ig', 'bhf-ig'); ?></a>
          <a href="https://www.youtube.com/watch?v=GWzQerp3CQo&amp;feature=share" aria-label="یوتیوب"><?php echo bsma_hf_tile('yt', 'bhf-yt'); ?></a>
          <a href="https://www.aparat.com/behsazan_Company/%D8%B4%D8%B1%DA%A9%D8%AA_%D8%A8%D9%87_%D8%B3%D8%A7%D8%B2%D8%A7%D9%86_%D8%B3%D8%B1%D8%A7%DB%8C_%D9%85%D9%87%D8%B1_%D8%A2%D9%87%D9%86%DA%AF" aria-label="آپارات"><?php echo bsma_hf_tile('ap', 'bhf-ap'); ?></a>
        </div>
        <a class="bhf-enamad" href="https://trustseal.enamad.ir/?id=373387&amp;Code=ujU72RbhZN1NH2ip4m02" target="_blank" rel="nofollow noopener" referrerpolicy="origin">
          <img src="<?php echo esc_url(content_url('/uploads/2024/01/enemad.webp')); ?>" width="125" height="136" loading="lazy" decoding="async" alt="نماد اعتماد الکترونیکی">
        </a>
      </div>
    </div>

    <div class="bhf-bottom">
      <span>© ۱۳۸۵–<?php echo esc_html(bsma_hf_fa(bsma_hf_jyear())); ?> تمامی حقوق این سایت برای شرکت بهسازان سرای مهر آهنگ محفوظ است.</span>
      <button class="bhf-top-btn" id="bhf-to-top"><?php echo bsma_hf_tile('up'); ?>بازگشت به بالا</button>
    </div>
  </div>
</footer>

<?php if (bsma_hf_show_tabbar()) : ?>
<nav class="bhf bhf-tabbar" aria-label="دسترسی سریع">
  <a href="<?php echo esc_url(home_url('/')); ?>"<?php echo is_front_page() ? ' class="bhf-cur" aria-current="page"' : ''; ?>><?php echo bsma_hf_tile('home', 'bhf-light'); ?>خانه</a>
  <button id="bhf-tab-cats"><?php echo bsma_hf_tile('grid', 'bhf-light'); ?>دسته‌ها</button>
  <button id="bhf-tab-search"><?php echo bsma_hf_tile('search', 'bhf-light'); ?>جست‌وجو</button>
  <a class="bhf-cart-tab" href="<?php echo esc_url(bsma_hf_url('cart', '/cart/')); ?>"><?php echo bsma_hf_tile('cart', 'bhf-light'); ?><?php echo bsma_hf_count_html(); ?>سبد خرید</a>
  <a href="<?php echo esc_url($acc); ?>"><?php echo bsma_hf_tile('user', 'bhf-light'); ?>حساب من</a>
</nav>
<?php endif; ?>

<div class="bhf bhf-veil" id="bhf-veil"></div>
<aside class="bhf bhf-drawer" id="bhf-drawer" aria-label="منوی موبایل" inert>
  <div class="bhf-d-head">
    <a class="bhf-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="صفحه‌ی اصلی"><?php echo bsma_hf_logo(); ?></a>
    <button class="bhf-burger bhf-open" id="bhf-d-close" aria-label="بستن منو"><i></i></button>
  </div>
  <div class="bhf-d-body">
    <form class="bhf-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <label class="bhf-sr" for="bhf-dq">جست‌وجو در محصولات</label>
      <input id="bhf-dq" type="search" name="s" placeholder="جست‌وجو در محصولات…" autocomplete="off">
      <input type="hidden" name="post_type" value="product">
      <button class="bhf-s-ic" aria-label="جست‌وجو"><?php echo bsma_hf_tile('search'); ?></button>
    </form>
    <div class="bhf-d-feat"><?php echo bsma_hf_firebox_card('bhf-d-fb'); ?></div>
    <div class="bhf-d-list">
      <details class="bhf-acc-d">
        <summary><?php echo bsma_hf_tile('grid'); ?>همه‌ی محصولات<?php echo bsma_hf_icon('chev'); ?></summary>
        <div class="bhf-sub">
          <?php foreach ($g as $grp) : ?>
          <p class="bhf-grp-t2"><?php echo esc_html($grp['t']); ?></p>
            <?php foreach ($grp['items'] as $it) : ?><a href="<?php echo esc_url($it[3]); ?>"><?php echo bsma_hf_tile($it[0], 'g3' === $grp['c'] ? 'bhf-steel' : ''); ?><?php echo esc_html($it[1]); ?></a><?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </details>
      <a href="<?php echo esc_url(home_url('/blogs/')); ?>"><?php echo bsma_hf_tile('doc', 'bhf-steel'); ?>مقالات و راهنماها</a>
      <a href="<?php echo esc_url(home_url('/news/')); ?>"><?php echo bsma_hf_tile('mega', 'bhf-steel'); ?>اخبار سایت</a>
      <a href="<?php echo esc_url(home_url('/about-us/')); ?>"><?php echo bsma_hf_tile('building', 'bhf-steel'); ?>درباره‌ی ما</a>
      <a href="<?php echo esc_url(home_url('/contact-2/')); ?>"><?php echo bsma_hf_tile('phone', 'bhf-steel'); ?>تماس با ما</a>
      <a href="<?php echo esc_url(BSMA_HF_CATALOG); ?>"><?php echo bsma_hf_tile('book', 'bhf-amber'); ?>کاتالوگ محصولات</a>
    </div>
    <div class="bhf-d-quick">
      <a href="tel:<?php echo esc_attr(BSMA_HF_PHONE); ?>"><?php echo bsma_hf_tile('phone'); ?>تماس فوری</a>
      <button class="bhf-msg-open"><?php echo bsma_hf_tile('chat', 'bhf-wa'); ?>ارسال پیام</button>
    </div>
    <a class="bhf-d-lang" href="<?php echo esc_url(BSMA_HF_AR_URL); ?>" hreflang="ar"><?php echo bsma_hf_tile('globe', 'bhf-green'); ?><span><b lang="ar">العربية</b><span>مشاهده‌ی نسخه‌ی عربی سایت</span></span><span class="bhf-go2">ورود ←</span></a>
  </div>
</aside>

<div class="bhf bhf-sheet-veil" id="bhf-msg-veil"></div>
<div class="bhf bhf-sheet" id="bhf-msg" role="dialog" aria-modal="true" aria-labelledby="bhf-msg-t" inert>
  <div class="bhf-grip"></div>
  <p class="bhf-sheet-t" id="bhf-msg-t">پیام به کارشناس فروش</p>
  <p>پیام‌رسان خودتان را انتخاب کنید. همه به شماره‌ی <b dir="ltr"><?php echo esc_html(BSMA_HF_CHAT_TXT); ?></b> وصل‌اند.</p>
  <div class="bhf-apps">
    <?php foreach ($apps as $a) : ?>
    <a class="bhf-app" href="<?php echo esc_url($a[2]); ?>" target="_blank" rel="noopener"<?php echo $a[3] ? ' data-copy-app="' . esc_attr($a[3]) . '"' : ''; ?>>
      <span class="bhf-tile bhf-<?php echo esc_attr($a[0]); ?>"><?php echo isset($glyph[$a[0]]) ? '<b class="bhf-glyph">' . esc_html($glyph[$a[0]]) . '</b>' : bsma_hf_icon($a[0]); ?></span><?php echo esc_html($a[1]); ?>
    </a>
    <?php endforeach; ?>
  </div>
  <button class="bhf-sheet-close" id="bhf-msg-close">بستن</button>
</div>
<div class="bhf bhf-toast" id="bhf-toast" role="status" aria-live="polite"></div>
  <div class="bhf-mega" id="bhf-mega" role="region" aria-label="دسته‌بندی محصولات" hidden>
  <div class="bhf-mega-in">
    <div class="bhf-fb"><?php echo bsma_hf_firebox_card(); ?></div>
    <div class="bhf-cats">
      <?php foreach ($c['groups'] as $g) : ?>
      <div class="bhf-grp">
        <p class="bhf-grp-t <?php echo esc_attr($g['c']); ?>"><i></i><?php echo esc_html($g['t']); ?></p>
        <div class="bhf-list">
          <?php foreach ($g['items'] as $it) : ?>
          <a class="bhf-cat" href="<?php echo esc_url($it[3]); ?>" data-icon="<?php echo esc_attr($it[0]); ?>"><?php echo bsma_hf_tile($it[0], 'g3' === $g['c'] ? 'bhf-steel' : ''); ?><span><b><?php echo esc_html($it[1]); ?></b><?php if ($it[2]) : ?><span><?php echo esc_html($it[2]); ?></span><?php endif; ?></span></a>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($g['chips'])) : ?>
        <div class="bhf-chips"><?php foreach ($g['chips'] as $ch) : ?><a href="<?php echo esc_url($ch[1]); ?>"><?php echo esc_html($ch[0]); ?></a><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <aside class="bhf-mega-side">
      <p class="bhf-side-t">برای پروژه‌تان مطمئن نیستید چه لازم دارید؟</p>
      <p>کارشناسان ما برای انتخاب تجهیزات و طراحی سیستم اعلام و اطفای حریق رایگان مشاوره می‌دهند.</p>
      <div class="bhf-side-links">
        <a href="<?php echo esc_url(BSMA_HF_CATALOG); ?>"><?php echo bsma_hf_tile('book', 'bhf-amber'); ?>دریافت کاتالوگ محصولات</a>
        <a href="<?php echo esc_url(home_url('/news/')); ?>"><?php echo bsma_hf_tile('mega', 'bhf-steel'); ?>اخبار سایت</a>
        <a href="<?php echo esc_url(home_url('/about-us/')); ?>"><?php echo bsma_hf_tile('building', 'bhf-steel'); ?>درباره‌ی ما</a>
      </div>
    </aside>
  </div>
</div>
<?php
}, 1);
