<?php
/*
 * Plugin Name: bsma SEO fixes
 * Description: Small, targeted fixes on top of Rank Math: home-page title (under 60 characters), meta description and 1200x630 share image, a complete Organization schema (no Article/Person on the home page), an H1 for blog posts, trimmed over-long meta descriptions, product image alt text, removal of the unsupported rating schema on shop/category pages, and crawlable/accessible markup for the Digits login modal. Delete this file to undo all of it.
 */
if (!defined('ABSPATH')) {
    exit;
}

const BSMA_SEO_HOME_TITLE = 'جعبه آتش نشانی، اعلام حریق تکنیم و کپسول | بهسازان';
const BSMA_SEO_HOME_DESC = 'تولیدکننده‌ی جعبه آتش‌نشانی بهسازان در اصفهان از ۱۳۸۵ و نماینده‌ی سیستم اعلام حریق تکنیم در ایران؛ عضو وندور لیست آتش‌نشانی، با ارسال به سراسر کشور.';
const BSMA_SEO_SAME_AS   = [
    'https://www.instagram.com/bsma.ir/',
    'https://www.aparat.com/behsazan_Company',
];

// ---------- meta description ----------
add_filter('rank_math/frontend/description', function ($d) {
    if (is_front_page()) {
        return BSMA_SEO_HOME_DESC;
    }
    $d = trim(preg_replace('/\s+/u', ' ', (string) $d));
    if (mb_strlen($d) <= 170) {
        return $d;
    }
    // Cut at the last sentence end before 165 chars, else at the last space.
    $cut = mb_substr($d, 0, 165);
    $end = max((int) mb_strrpos($cut, '.'), (int) mb_strrpos($cut, '؛'), (int) mb_strrpos($cut, '!'), (int) mb_strrpos($cut, '؟'));
    if ($end >= 90) {
        return mb_substr($cut, 0, $end + 1);
    }
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: 160), '،,:;- ') . '…';
}, 20);

// ---------- home page: title under 60 characters, same title/description on social cards ----------
add_filter('rank_math/frontend/title', function ($t) {
    return is_front_page() ? BSMA_SEO_HOME_TITLE : $t;
}, 20);
foreach (['facebook/og_title', 'twitter/twitter_title'] as $bsma_seo_k) {
    add_filter('rank_math/opengraph/' . $bsma_seo_k, function ($t) {
        return is_front_page() ? BSMA_SEO_HOME_TITLE : $t;
    }, 20);
}
foreach (['facebook/og_description', 'twitter/twitter_description'] as $bsma_seo_k) {
    add_filter('rank_math/opengraph/' . $bsma_seo_k, function ($t) {
        return is_front_page() ? BSMA_SEO_HOME_DESC : $t;
    }, 20);
}

// ---------- home-page share image: a 1200x630 brand card instead of the 300x300 favicon ----------
foreach (['facebook', 'twitter'] as $bsma_seo_net) {
    add_filter("rank_math/opengraph/{$bsma_seo_net}/image_array", function ($att) {
        if (!is_front_page()) {
            return $att;
        }
        return [
            'url' => plugins_url('bsma-seo/og-home.jpg', __FILE__),
            'width' => 1200,
            'height' => 630,
            'alt' => 'جعبه آتش‌نشانی بهسازان؛ تولیدکننده‌ی جعبه آتش‌نشانی و نماینده‌ی اعلام حریق تکنیم',
            'type' => 'image/jpeg',
        ];
    }, 20);
}

// ---------- schema: full Organization everywhere; no Article/Person on the home page ----------
add_filter('rank_math/json_ld', function ($data) {
    if (!is_array($data)) {
        return $data;
    }
    foreach ($data as $key => $item) {
        if (!is_array($item) || empty($item['@type'])) {
            continue;
        }
        $types = (array) $item['@type'];
        if (is_front_page() && array_intersect($types, ['Article', 'BlogPosting', 'NewsArticle', 'Person'])) {
            unset($data[$key]);
            continue;
        }
        if (in_array('Organization', $types, true) || in_array('LocalBusiness', $types, true)) {
            $data[$key] += [
                'alternateName' => 'بهسازان',
                'description' => 'تولیدکننده‌ی جعبه آتش‌نشانی بهسازان از ۱۳۸۵ و نماینده‌ی سیستم اعلام حریق تکنیم در ایران.',
                'foundingDate' => '2006',
                'telephone' => '+983136242532',
                'email' => 'info@bsma.ir',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'اصفهان',
                    'addressRegion' => 'اصفهان',
                    'addressCountry' => 'IR',
                ],
                'contactPoint' => [
                    ['@type' => 'ContactPoint', 'telephone' => '+983136242532', 'contactType' => 'customer service', 'areaServed' => 'IR', 'availableLanguage' => ['fa', 'ar']],
                    ['@type' => 'ContactPoint', 'telephone' => '+989306016798', 'contactType' => 'sales', 'areaServed' => 'IR', 'availableLanguage' => ['fa']],
                ],
                'sameAs' => BSMA_SEO_SAME_AS,
            ];
        }
    }
    return $data;
}, 99);

// ---------- product images without alt text get the product name ----------
add_filter('wp_get_attachment_image_attributes', function ($attr, $att) {
    if (is_admin() || (isset($attr['alt']) && '' !== trim($attr['alt']))) {
        return $attr;
    }
    $name = '';
    global $product;
    if (is_object($product) && is_a($product, 'WC_Product')) {
        $name = $product->get_name();
    } elseif (!empty($att->post_parent) && 'product' === get_post_type($att->post_parent)) {
        $name = get_the_title($att->post_parent);
    }
    if ('' !== $name) {
        $attr['alt'] = wp_strip_all_tags($name);
    }
    return $attr;
}, 20, 2);

// ---------- HTML fixes on the few templates that need them ----------
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || wp_doing_ajax()) {
        return;
    }
    $post = is_singular('post');
    $shop = function_exists('is_product_taxonomy') && (is_product_taxonomy() || is_shop());
    if (!$post && !$shop) {
        return;
    }
    ob_start(function ($html) use ($post, $shop) {
        if ($post) {
            // The post template prints its title as <h2 class="cpa-title">; a post page needs an H1.
            $html = preg_replace('~<h2 class="cpa-title">(.*?)</h2>~s', '<h1 class="cpa-title">$1</h1>', $html, 1);
        }
        if ($shop) {
            // An HTML widget on the shop/category template adds Organization/LocalBusiness/Product JSON-LD with
            // a rating (4.9 from 5000 reviews) that is not shown on the page. Google does not allow that, and
            // Rank Math already outputs the correct Organization. Keep everything else (FAQPage, Rank Math).
            $html = preg_replace_callback('~<script type="application/ld\+json">(.*?)</script>\s*~s', function ($m) {
                $j = json_decode($m[1], true);
                $t = is_array($j) && isset($j['@type']) ? (array) $j['@type'] : [];
                return array_intersect($t, ['Organization', 'LocalBusiness', 'Product']) ? '' : $m[0];
            }, $html);
            $html = str_replace('"https:/bsma.ir"', '"https://bsma.ir"', $html);
        }
        return $html;
    });
}, 1);

// ---------- Digits login modal: anchors without href become buttons, images get alt ----------
add_action('wp_footer', function () {
    if (is_admin()) {
        return;
    }
    ?>
<script id="bsma-seo-a11y" data-no-optimize="1">
(function(d){function f(){var i,a=d.querySelectorAll('a:not([href]):not([role])');for(i=0;i<a.length;i++){a[i].setAttribute('role','button');if(!a[i].hasAttribute('tabindex'))a[i].setAttribute('tabindex','0');}
var m=d.querySelectorAll('img:not([alt])');for(i=0;i<m.length;i++){m[i].setAttribute('alt',/captcha/i.test(m[i].getAttribute('data-src')||m[i].src||'')?'کد امنیتی':'');}}
if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',f);else f();})(document);
</script>
    <?php
}, 99);
