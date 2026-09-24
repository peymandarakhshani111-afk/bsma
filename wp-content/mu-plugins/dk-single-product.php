<?php
/*
Plugin Name: DK Single Product
Description: Custom WooCommerce single product page with lightbox, tabs, schema and sticky bar
Version: 1.0
Author: محمد قربانی
*/

if (!defined('ABSPATH')) exit;

function dk2_product_shortcode() {

    if (!is_singular('product')) return '';

    global $product;
    if (!$product || !is_a($product, 'WC_Product')) return '';

    ob_start();

    $raw_price  = $product->get_price();
    $price_html = $product->get_price_html();
    $has_price  = !(empty($raw_price) || $raw_price === '0');

    $short_desc = $product->get_short_description();
    $attributes = $product->get_attributes();
    $rating_html = wc_get_rating_html( $product->get_average_rating() );

    $product_id = $product->get_id();
    $main_image_id  = $product->get_image_id();
    $main_image_url = wp_get_attachment_image_url($main_image_id, 'large');
    $gallery_ids    = $product->get_gallery_image_ids();
    $brands = get_the_terms($product->get_id(), 'product_brand'); // cached, unlike wp_get_post_terms()
    $average   = $product->get_average_rating();
    $reviews   = $product->get_review_count();
    $product_url = get_permalink($product->get_id());
    $currency = get_woocommerce_currency();
    $sku = $product->get_sku();
    $brand_name = (!empty($brands) && !is_wp_error($brands)) ? $brands[0]->name : get_bloginfo('name');
    $availability = $product->is_in_stock() ? "https://schema.org/InStock" : "https://schema.org/OutOfStock";

?>

<style>
.dk2-trust-badges {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin: 20px 0;
    padding: 15px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.dk2-trust-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
    border: 1px solid #f1f5f9;
}

.dk2-trust-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

.dk2-trust-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.dk2-trust-item.shipping .dk2-trust-icon {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
}

.dk2-trust-item.return .dk2-trust-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.dk2-trust-item.brand .dk2-trust-icon {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.dk2-trust-icon svg {
    width: 22px;
    height: 22px;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill: none;
    stroke: currentColor;
}

.dk2-trust-content {
    display: flex;
    flex-direction: column;
}

.dk2-trust-title {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    line-height: 1.2;
}

.dk2-trust-desc {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
    font-weight: 500;
}

@media (max-width: 640px) {
    .dk2-trust-badges {
        grid-template-columns: 1fr;
        gap: 8px;
        padding: 12px;
    }
    .dk2-trust-item {
        padding: 10px;
    }
}
</style>

<div class="dk2-wrapper">
    <div class="dk2-image-wrapper">
        <div class="dk2-img-wrapper">
            <a href="<?php echo esc_url($main_image_url); ?>"
               class="dk2-lightbox-trigger"
               data-gallery="<?php echo esc_attr($product_id); ?>">
                <?php // The main image is usually the page's largest element (LCP): load it first and never lazily. ?>
                <img src="<?php echo esc_url($main_image_url); ?>"
                     class="dk2-product-img skip-lazy"
                     alt="<?php echo esc_attr($product->get_title()); ?>"
                     fetchpriority="high" loading="eager" data-no-lazy="1">
            </a>

            <?php if (!empty($brands) && !is_wp_error($brands)) : ?>
                <div class="dk2-brands-on-image">
                    <?php foreach ($brands as $brand) : ?>
                        <span class="dk2-brand-item"><?php echo esc_html($brand->name); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($gallery_ids)) : ?>
                <?php foreach ($gallery_ids as $gid) : ?>
                    <?php $img_url = wp_get_attachment_image_url($gid, 'large'); ?>
                    <?php if (!$img_url) continue; // A deleted image would open the current page URL in the lightbox. ?>
                    <a href="<?php echo esc_url($img_url); ?>"
                       class="dk2-lightbox-hidden"
                       data-gallery="<?php echo esc_attr($product_id); ?>"></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="dk2-mini-badges">
            <div class="dk2-mini-badge dk2-mini-rating">
                ⭐ <?php echo $average ? $average : '0'; ?>
            </div>
            <div id="dk2-mini-count" class="dk2-mini-badge dk2-mini-count">
                💬 <?php echo $reviews; ?> دیدگاه
            </div>
            <div id="dk2-mini-add" class="dk2-mini-badge dk2-mini-add">
                📝 ارسال دیدگاه
            </div>
            <div class="dk2-icons-left">
                <div class="dk2-icon-like" title="لایک" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                    <svg class="dk2-icon-svg" viewBox="0 0 24 24">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5
                                 2 5.42 4.42 3 7.5 3c1.74 0 3.41 0.81 4.5 2.09
                                 C13.09 3.81 14.76 3 16.5 3
                                 19.58 3 22 5.42 22 8.5
                                 c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                              fill="none" 
                              stroke="currentColor" 
                              stroke-width="2" 
                              stroke-linecap="round" 
                              stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="dk2-icon-share" title="اشتراک‌گذاری" data-url="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                    <svg class="dk2-icon-svg" viewBox="0 0 24 24">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                </div>
            </div>

            <div class="dk2-badges">
                <div class="dk2-badge badge-like">💖 ۱۰۰۰+ نفر در لیست علاقه‌مندی</div>
                <div class="dk2-badge badge-view">👁️ ۱۰۰+ بازدید در ۲۴ ساعت اخیر</div>
                <div class="dk2-badge badge-sale">🛒 ۲۰+ فروش در هفته گذشته</div>
            </div>
        </div>
    </div>

    <div class="dk2-left">
        <h1 class="dk2-title"><?php echo esc_html($product->get_name()); ?></h1>

        <div class="dk2-price">
            <?php 
                if ($has_price) echo $price_html;
                else echo '<span class="dk2-call">تماس بگیرید</span>';
            ?>
        </div>

        <div class="dk2-buy">
            <?php if ($has_price) woocommerce_template_single_add_to_cart(); ?>
        </div>

        <div class="dk2-trust-badges">
            <div class="dk2-trust-item shipping" title="هزینه ارسال بر اساس حجم و مقصد">
                <div class="dk2-trust-icon">
                    <svg viewBox="0 0 24 24">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        <path d="M12 12h2v2h-2z" style="fill: currentColor; stroke: none; opacity: 0.8;"></path>
                    </svg>
                </div>
                <div class="dk2-trust-content">
                    <span class="dk2-trust-title">هزینه ارسال</span>
                    <span class="dk2-trust-desc">بر عهده خریدار (برآورد در تسویه)</span>
                </div>
            </div>

            <div class="dk2-trust-item return" title="ضمانت بازگشت کالا">
                <div class="dk2-trust-icon">
                    <svg viewBox="0 0 24 24">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        <path d="M9 14l-2 2 2 2"></path>
                        <path d="M7 16h6"></path>
                    </svg>
                </div>
                <div class="dk2-trust-content">
                    <span class="dk2-trust-title">۷ روز ضمانت</span>
                    <span class="dk2-trust-desc">بازگشت بدون قید و شرط</span>
                </div>
            </div>

            <div class="dk2-trust-item brand" title="برند اصلی و معتبر">
                <div class="dk2-trust-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                        <circle cx="12" cy="12" r="3" style="fill: rgba(255,255,255,0.3); stroke: none;"></circle>
                    </svg>
                </div>
                <div class="dk2-trust-content">
                    <span class="dk2-trust-title"><?php echo esc_html($brand_name); ?></span>
                    <span class="dk2-trust-desc">برند معتبر و ضمانت‌شده</span>
                </div>
            </div>
        </div>

        <?php if ($short_desc): ?>
            <div class="dk2-shortdesc"><?php echo wpautop($short_desc); ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="dk2-tabs">
    <ul class="dk2-tab-nav">
        <li class="active" data-scroll="desc">توضیحات کامل</li>
        <li data-scroll="specs">مشخصات فنی</li>
        <li id="dk2-tab-reviews" data-scroll="reviews">نظرات</li>
    </ul>

    <div id="desc" class="dk2-tab-content active">
        <?php echo wpautop($product->get_description()); ?>
    </div>

    <div id="specs" class="dk2-tab-content">
        <ul class="dk2-specs-list">
            <?php if (!empty($attributes)): ?>
                <?php foreach ($attributes as $attribute): ?>
                    <?php
                    // Global (taxonomy) attributes store term IDs in get_options(), so read the term names instead.
                    $attr_values = $attribute->is_taxonomy()
                        ? wc_get_product_terms($product_id, $attribute->get_name(), array('fields' => 'names'))
                        : $attribute->get_options();
                    ?>
                    <li>
                        <strong><?php echo esc_html(wc_attribute_label($attribute->get_name(), $product)); ?>:</strong>
                        <?php echo esc_html(implode('، ', $attr_values)); ?>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>مشخصات فنی ثبت نشده است.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div id="reviews" class="dk2-tab-content">
        <?php comments_template(); ?>
    </div>
</div>

<div class="dk2-tags">
    <?php
    $tags = get_the_terms($product->get_id(), 'product_tag');
    if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
        foreach ( $tags as $tag ) {
            $tag_link = get_term_link( $tag );
            if ( is_wp_error( $tag_link ) ) {
                continue;
            }
            echo '<a class="dk2-tag" href="' . esc_url( $tag_link ) . '">'
                    . esc_html( $tag->name ) .
                 '</a>';
        }
    }
    ?>
</div>

<div class="dk2-sticky-bar">
    <div class="dk2-sticky-inner">
        <div class="dk2-sticky-price">
            <?php echo $product->get_price_html(); ?>
        </div>
        <?php if ($has_price && $product->is_in_stock()) : ?>
        <div class="dk2-sticky-btn">
            <?php if ($product->is_type('simple')) : ?>
            <form class="cart" action="#" method="post">
                <div class="quantity" style="display:none"></div>
                <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt">
                    افزودن به سبد خرید
                </button>
            </form>
            <?php else : // Variable/grouped products need their options chosen in the main form first. ?>
            <button type="button" class="single_add_to_cart_button button alt" onclick="document.querySelector('.dk2-buy').scrollIntoView({behavior: 'smooth', block: 'center'});">
                افزودن به سبد خرید
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$schema_data = [
    "@context"    => "https://schema.org",
    "@type"       => "Product",
    "name"        => $product->get_name(),
    "image"       => $main_image_url,
    "description" => wp_strip_all_tags(strip_shortcodes($product->get_description())),
    "sku"         => $sku ? $sku : (string)$product_id,
    "brand"       => [
        "@type" => "Brand",
        "name"  => $brand_name
    ],
    "offers"      => [
        "@type"              => "Offer",
        "url"                => $product_url,
        "price"              => $raw_price ? $raw_price : "0",
        "priceCurrency"      => $currency,
        "availability"       => $availability,
        "itemCondition"      => "https://schema.org/NewCondition",
        "priceValidUntil"    => date('Y-12-31'),
        "hasMerchantReturnPolicy" => [
            "@type"                 => "MerchantReturnPolicy",
            "returnPolicyCategory"  => "https://schema.org/MerchantReturnFiniteReturnWindow",
            "merchantReturnDays"    => 7,
            "returnMethod"          => "https://schema.org/ReturnByMail",
            "returnFees"            => "https://schema.org/FreeReturn"
        ]
    ]
];

if ($average > 0) {
    $schema_data["aggregateRating"] = [
        "@type"       => "AggregateRating",
        "ratingValue" => $average,
        "reviewCount" => $reviews
    ];
}

$gtin = $product->get_meta('_gtin') ?: $product->get_meta('gtin');
if (!empty($gtin)) {
    $schema_data['gtin13'] = $gtin;
}
?>
<?php if (apply_filters('dk2_output_product_schema', false)) : // Rank Math already outputs the Product schema, so this duplicate is off (2026-09-24). ?>
<script type="application/ld+json">
<?php echo json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
</script>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".dk2-lightbox-trigger").forEach(function(el) {
        el.addEventListener("click", function(e) {
            e.preventDefault();
            let galleryId = this.getAttribute("data-gallery");
            let items = document.querySelectorAll('[data-gallery="'+galleryId+'"]');
            let imgs = [];
            items.forEach(a => imgs.push(a.href));
            dk2OpenLightbox(imgs);
        });
    });
});

function dk2OpenLightbox(images) {
    let index = 0;
    const overlay = document.createElement("div");
    overlay.className = "dk2-lightbox-overlay";
    const img = document.createElement("img");
    img.className = "dk2-lightbox-img";
    const closeBtn = document.createElement("div");
    closeBtn.className = "dk2-lightbox-close";
    closeBtn.innerHTML = "&times;";
    overlay.appendChild(img);
    overlay.appendChild(closeBtn);

    const counter = document.createElement("div");
    counter.style.cssText = "position:absolute;bottom:20px;left:50%;transform:translateX(-50%);color:#fff;background:rgba(0,0,0,.5);padding:4px 12px;border-radius:12px;font-size:14px;direction:ltr";

    function show(i) {
        index = (i + images.length) % images.length;
        img.src = images[index];
        counter.textContent = (index + 1) + " / " + images.length;
    }
    function close() {
        overlay.remove();
        document.removeEventListener("keydown", onKey);
    }
    // RTL layout: the next image is on the left.
    function onKey(e) {
        if (e.key === "Escape") close();
        else if (e.key === "ArrowLeft") show(index + 1);
        else if (e.key === "ArrowRight") show(index - 1);
    }

    if (images.length > 1) {
        [["‹", "left", 1], ["›", "right", -1]].forEach(function (cfg) {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.textContent = cfg[0];
            btn.setAttribute("aria-label", cfg[2] > 0 ? "تصویر بعدی" : "تصویر قبلی");
            btn.style.cssText = "position:absolute;top:50%;" + cfg[1] + ":16px;transform:translateY(-50%);width:44px;height:44px;border:none;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;font-size:28px;line-height:1;cursor:pointer;z-index:2";
            btn.onclick = function (e) { e.stopPropagation(); show(index + cfg[2]); };
            overlay.appendChild(btn);
        });
        overlay.appendChild(counter);

        let touchX = null;
        overlay.addEventListener("touchstart", e => { touchX = e.touches[0].clientX; }, { passive: true });
        overlay.addEventListener("touchend", e => {
            if (touchX === null) return;
            const dx = e.changedTouches[0].clientX - touchX;
            if (Math.abs(dx) > 40) show(index + (dx > 0 ? 1 : -1));
            touchX = null;
        });
    }

    show(0);
    document.body.appendChild(overlay);
    // The overlay's CSS lives outside this file; the nav buttons need it to be a positioned box.
    if (getComputedStyle(overlay).position === "static") overlay.style.position = "relative";
    document.addEventListener("keydown", onKey);
    closeBtn.onclick = close;
    overlay.onclick = e => { if (e.target === overlay) close(); };
}
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const navItems = document.querySelectorAll(".dk2-tab-nav li");

    function activateTabAndScroll(tabLi) {
        if (!tabLi) return;
        navItems.forEach(n => n.classList.remove("active"));
        tabLi.classList.add("active");
        const targetId = tabLi.dataset.scroll;
        const target   = document.getElementById(targetId);
        if (target) {
            const y = target.getBoundingClientRect().top + window.pageYOffset - 80;
            window.scrollTo({ top: y, behavior: "smooth" });
            const contents = document.querySelectorAll(".dk2-tab-content");
            contents.forEach(c => c.classList.remove("active"));
            target.classList.add("active");
        }
    }

    navItems.forEach(item => {
        item.addEventListener("click", function() {
            activateTabAndScroll(this);
        });
    });

    const reviewsTabLi   = document.getElementById("dk2-tab-reviews");
    const miniCountBadge = document.getElementById("dk2-mini-count");
    const miniAddBadge   = document.getElementById("dk2-mini-add");

    function openReviewsTab() {
        activateTabAndScroll(reviewsTabLi);
    }

    if (miniCountBadge) {
        miniCountBadge.style.cursor = "pointer";
        miniCountBadge.addEventListener("click", openReviewsTab);
    }

    if (miniAddBadge) {
        miniAddBadge.style.cursor = "pointer";
        miniAddBadge.addEventListener("click", openReviewsTab);
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const brandItems = document.querySelectorAll('.dk2-brands-on-image .dk2-brand-item');
    let current = 0;
    if (brandItems.length > 1) {
        brandItems[current].classList.add('active');
        setInterval(() => {
            brandItems[current].classList.remove('active');
            current = (current + 1) % brandItems.length;
            brandItems[current].classList.add('active');
        }, 2500);
    } else if (brandItems.length === 1) {
        brandItems[0].classList.add('active');
    }
});
</script>

<?php
return ob_get_clean();
}

add_shortcode('dk_product_v2', 'dk2_product_shortcode');