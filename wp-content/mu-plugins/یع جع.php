<?php
/**
 * Plugin Name: معرض صناديق إطفاء الحريق والمعدات
 * Description: إضافة ملف واحد لعرض منتجات بهسازان بنافذة منبثقة ذات تبويبات، SEO قوي، Lazy Load، مشاركة
 * Version: 3.3
 * Author: Developer
 * Text Domain: arabic-products
 */

if (!defined('ABSPATH')) exit;

$arabic_products_data = [];

class Arabic_Products_Showcase {

    private $products = [];
    private $phone    = '989306016798';
    private $json_file;

    public function __construct() {
        $this->json_file = dirname(__FILE__) . '/arabic-products.json';
        add_shortcode('arabic_products', [$this, 'render_shortcode']);
        add_action('wp_head', [$this, 'inject_seo_tags']);
        add_action('wp_footer', [$this, 'inject_assets']);
    }

    private function load_products() {
        if (!empty($this->products)) return;

        if (file_exists($this->json_file)) {
            $json = file_get_contents($this->json_file);
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data)) {
                $this->products = $data;
                return;
            }
        }

        $filtered = apply_filters('arabic_products_data', []);
        if (!empty($filtered) && is_array($filtered)) {
            $this->products = $filtered;
            return;
        }

        global $arabic_products_data;
        if (!empty($arabic_products_data) && is_array($arabic_products_data)) {
            $this->products = $arabic_products_data;
            return;
        }

        $this->products = [];
    }

    public function render_shortcode($atts) {
        $this->load_products();
        if (empty($this->products)) {
            return '<p style="text-align:center;padding:40px">لا توجد منتجات حالياً.</p>';
        }

        $atts = shortcode_atts([
            'columns'       => 3,
            'initial_limit' => 6,
            'per_page'      => 6,
        ], $atts, 'arabic_products');

        $columns       = max(2, min(4, intval($atts['columns'])));
        $initial_limit = max(2, intval($atts['initial_limit']));
        $per_page      = max(2, intval($atts['per_page']));
        $total         = count($this->products);
        $has_more      = $total > $initial_limit;

        $products_json = wp_json_encode($this->products, JSON_UNESCAPED_UNICODE);

        ob_start();
        ?>
        <section class="arpro-section" dir="rtl" lang="ar" data-products='<?php echo esc_attr($products_json); ?>'
                 data-initial="<?php echo esc_attr($initial_limit); ?>"
                 data-perpage="<?php echo esc_attr($per_page); ?>">

            <div class="arpro-header">
                <h1 class="arpro-section-title">معرض صناديق إطفاء الحريق والمعدات</h1>
                <p class="arpro-section-subtitle">خزانات حريق ستانلس ستيل، فايربوكس، بكرات خراطيم — شهادات معتمدة</p>
            </div>

            <div class="arpro-container">
                <div class="arpro-grid arpro-cols-<?php echo esc_attr($columns); ?>" id="arpro-grid">
                    <?php $this->render_product_cards(array_slice($this->products, 0, $initial_limit)); ?>
                </div>

                <?php if ($has_more): ?>
                <div class="arpro-loadmore-wrap" id="arpro-loadmore-wrap">
                    <button class="arpro-loadmore" onclick="arproLoadMore()" id="arpro-loadmore-btn">
                        <span class="arpro-loadmore-text">عرض المزيد</span>
                        <span class="arpro-loadmore-count">(<?php echo esc_html($total - $initial_limit); ?> منتج)</span>
                        <span class="arpro-loadmore-icon">&#x25BC;</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php $this->render_modals(); ?>

        <?php
        return ob_get_clean();
    }

    private function render_product_cards($products) {
        foreach ($products as $product):
            $wa_text = urlencode('مرحباً، أرغب في طلب: ' . $product['title'] . ' (رمز: ' . $product['sku'] . ')');
        ?>
        <article class="arpro-card" data-id="<?php echo intval($product['id']); ?>"
                 itemscope itemtype="https://schema.org/Product">
            <meta itemprop="sku" content="<?php echo esc_attr($product['sku']); ?>" />
            <meta itemprop="brand" content="<?php echo esc_attr($product['brand'] ?? 'بهسازان'); ?>" />

            <div class="arpro-image-wrap" onclick="arproOpen(<?php echo intval($product['id']); ?>)">
                <div class="arpro-skeleton"></div>
                <img src="<?php echo esc_url($product['image']); ?>"
                     alt="<?php echo esc_attr($product['title']); ?>"
                     loading="lazy" itemprop="image"
                     onload="this.previousElementSibling.style.display='none'" />
                <?php if (!empty($product['brand'])): ?>
                <span class="arpro-brand-badge"><?php echo esc_html($product['brand']); ?></span>
                <?php endif; ?>
                <?php if (!empty($product['price']) && $product['price'] !== 'اطلب عرض سعر'): ?>
                <span class="arpro-price-badge"><?php echo esc_html($product['price']); ?></span>
                <?php endif; ?>
            </div>

            <div class="arpro-body">
                <h2 class="arpro-title" itemprop="name">
                    <a href="javascript:void(0)" onclick="arproOpen(<?php echo intval($product['id']); ?>)">
                        <?php echo esc_html($product['title']); ?>
                    </a>
                </h2>
                <p class="arpro-excerpt" itemprop="description">
                    <?php echo esc_html($product['excerpt']); ?>
                </p>
                <?php if (!empty($product['tags'])): ?>
                <div class="arpro-tags">
                    <?php foreach (array_slice($product['tags'], 0, 3) as $tag): ?>
                    <span class="arpro-tag"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="arpro-actions">
                    <button class="arpro-btn" onclick="arproOpen(<?php echo intval($product['id']); ?>)">
                        عرض التفاصيل
                    </button>
                    <a href="https://wa.me/<?php echo esc_attr($this->phone); ?>?text=<?php echo esc_attr($wa_text); ?>"
                       class="arpro-btn-wa" target="_blank" rel="noopener noreferrer" title="واتساب">
                        <span>&#x1F4AC;</span>
                    </a>
                </div>
            </div>
        </article>
        <?php endforeach;
    }

    private function render_modals() {
        $current_url = get_permalink();
        foreach ($this->products as $product):
            $wa_text = urlencode('مرحباً، أرغب في طلب: ' . $product['title'] . ' (رمز: ' . $product['sku'] . ')');
            $share_url = urlencode($current_url . '#product-' . $product['id']);
            $share_text = urlencode($product['title']);
        ?>
        <div id="arpro-modal-<?php echo intval($product['id']); ?>" class="arpro-overlay" dir="rtl" lang="ar">
            <div class="arpro-modal" role="dialog" aria-modal="true" aria-labelledby="arpro-title-<?php echo intval($product['id']); ?>">
                <button class="arpro-close" onclick="arproClose(<?php echo intval($product['id']); ?>)" aria-label="إغلاق">&times;</button>

                <div class="arpro-modal-inner">
                    <div class="arpro-modal-header">
                        <h2 id="arpro-title-<?php echo intval($product['id']); ?>" class="arpro-modal-title" itemprop="name">
                            <?php echo esc_html($product['title']); ?>
                        </h2>
                        <div class="arpro-modal-meta">
                            <span class="arpro-meta-sku">&#x1F4E6; رمز: <?php echo esc_html($product['sku']); ?></span>
                            <?php if (!empty($product['brand'])): ?>
                            <span class="arpro-meta-brand">&#x1F3E2; <?php echo esc_html($product['brand']); ?></span>
                            <?php endif; ?>
                            <span class="arpro-meta-price">&#x1F4B0; <?php echo esc_html($product['price']); ?></span>
                        </div>
                    </div>

                    <!-- Share Bar -->
                    <div class="arpro-share">
                        <span>مشاركة:</span>
                        <a href="https://wa.me/?text=<?php echo esc_attr($share_text . '%20' . $share_url); ?>" target="_blank" rel="noopener" class="arpro-share-wa" title="واتساب">&#x1F4AC;</a>
                        <a href="https://t.me/share/url?url=<?php echo esc_attr($share_url); ?>&text=<?php echo esc_attr($share_text); ?>" target="_blank" rel="noopener" class="arpro-share-tg" title="تيليجرام">&#x2709;</a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr($share_url); ?>" target="_blank" rel="noopener" class="arpro-share-fb" title="فيسبوك">f</a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo esc_attr($share_url); ?>&text=<?php echo esc_attr($share_text); ?>" target="_blank" rel="noopener" class="arpro-share-tw" title="تويتر">X</a>
                    </div>

                    <!-- Tabs -->
                    <div class="arpro-tabs">
                        <button class="arpro-tab active" onclick="arproTab(<?php echo intval($product['id']); ?>, 0)">&#x1F441; نظرة سريعة</button>
                        <button class="arpro-tab" onclick="arproTab(<?php echo intval($product['id']); ?>, 1)">&#x1F4CB; المواصفات</button>
                        <button class="arpro-tab" onclick="arproTab(<?php echo intval($product['id']); ?>, 2)">&#x1F6D2; اطلب الآن</button>
                    </div>

                    <!-- Tab 0: Overview -->
                    <div class="arpro-panel active" data-panel="0">
                        <div class="arpro-panel-overview">
                            <div class="arpro-panel-img">
                                <div class="arpro-skeleton"></div>
                                <img src="<?php echo esc_url($product['image_full']); ?>" alt="<?php echo esc_attr($product['title']); ?>" loading="lazy" onload="this.previousElementSibling.style.display='none'" />
                                <?php if (!empty($product['brand'])): ?>
                                <span class="arpro-img-brand"><?php echo esc_html($product['brand']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="arpro-panel-desc">
                                <div class="arpro-panel-badge">
                                    <span class="arpro-badge-cert">&#x2705; شهادة معتمدة</span>
                                    <span class="arpro-badge-stock">&#x1F7E2; متوفر</span>
                                </div>
                                <p class="arpro-panel-excerpt"><?php echo esc_html($product['excerpt']); ?></p>
                                <p class="arpro-long-desc"><?php echo esc_html($product['description']); ?></p>
                                <?php if (!empty($product['tags'])): ?>
                                <div class="arpro-panel-tags">
                                    <?php foreach ($product['tags'] as $tag): ?>
                                    <span class="arpro-panel-tag"><?php echo esc_html($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 1: Specs -->
                    <div class="arpro-panel" data-panel="1">
                        <?php if (!empty($product['features'])): ?>
                        <div class="arpro-specs">
                            <h3>&#x1F4CB; المواصفات الفنية</h3>
                            <div class="arpro-specs-grid">
                                <?php foreach ($product['features'] as $key => $value): ?>
                                <div class="arpro-spec-item">
                                    <span class="arpro-spec-key"><?php echo esc_html($key); ?></span>
                                    <span class="arpro-spec-val"><?php echo esc_html($value); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 2: Order -->
                    <div class="arpro-panel" data-panel="2">
                        <div class="arpro-order">
                            <div class="arpro-order-product">
                                <img src="<?php echo esc_url($product['image']); ?>" alt="" />
                                <div>
                                    <strong><?php echo esc_html($product['title']); ?></strong>
                                    <span>رمز: <?php echo esc_html($product['sku']); ?></span>
                                </div>
                            </div>
                            <p class="arpro-order-note">&#x1F4E9; أرسل طلبك مباشرة عبر أحد قنوات التواصل:</p>
                            <div class="arpro-order-actions">
                                <a href="https://wa.me/<?php echo esc_attr($this->phone); ?>?text=<?php echo esc_attr($wa_text); ?>"
                                   class="arpro-order-btn arpro-wa" target="_blank" rel="noopener noreferrer">
                                    <span class="arpro-icon">&#x1F4AC;</span>
                                    <span>واتساب</span>
                                </a>
                                <a href="https://t.me/+<?php echo esc_attr($this->phone); ?>"
                                   class="arpro-order-btn arpro-tg" target="_blank" rel="noopener noreferrer">
                                    <span class="arpro-icon">&#x2709;</span>
                                    <span>تيليجرام</span>
                                </a>
                                <a href="tel:+<?php echo esc_attr($this->phone); ?>" class="arpro-order-btn arpro-call">
                                    <span class="arpro-icon">&#x1F4DE;</span>
                                    <span>اتصال</span>
                                </a>
                            </div>
                            <div class="arpro-order-phone">
                                <span>&#x1F4F1; الرقم المباشر:</span>
                                <strong dir="ltr">+98 930 601 6798</strong>
                            </div>
                            <p class="arpro-order-hint">&#x23F0; الرد خلال ۵ دقائق في أوقات العمل</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach;
    }

    public function inject_seo_tags() {
        if (!is_singular()) return;
        global $post;
        if (empty($post) || !has_shortcode($post->post_content, 'arabic_products')) return;

        $this->load_products();
        if (empty($this->products)) return;

        $current_url = get_permalink($post->ID);
        $site_name   = get_bloginfo('name');
        $first_img   = $this->products[0]['image_full'] ?? '';
        $desc        = 'معدات إطفاء الحريق والسلامة من بهسازان — خزانات حريق ستانلس ستيل، فايربوكس، بكرات خراطيم. شهادات معتمدة. طلب مباشر عبر واتساب.';

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'ItemList',
            'name'     => 'معرض صناديق إطفاء الحريق والمعدات — بهسازان',
            'description' => $desc,
            'url'      => $current_url,
            'itemListElement' => [],
        ];
        foreach ($this->products as $i => $p) {
            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => [
                    '@type' => 'Product',
                    'name'  => $p['title'],
                    'image' => $p['image_full'] ?? $p['image'],
                    'description' => $p['excerpt'],
                    'sku'   => $p['sku'],
                    'brand' => ['@type' => 'Brand', 'name' => $p['brand'] ?? 'بهسازان'],
                    'aggregateRating' => [
                        '@type' => 'AggregateRating',
                        'ratingValue' => '4.8',
                        'reviewCount' => '12',
                        'bestRating' => '5'
                    ],
                    'offers' => [
                        '@type' => 'Offer',
                        'availability' => 'https://schema.org/InStock',
                        'priceCurrency' => 'SAR',
                        'url' => $current_url . '#product-' . $p['id'],
                        'seller' => [
                            '@type' => 'Organization',
                            'name' => 'بهسازان',
                            'telephone' => '+98-930-601-6798',
                            'url' => $current_url,
                        ],
                    ],
                ],
            ];
        }

        $faq_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'ما هي خزانة إطفاء الحريق؟',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'خزانة إطفاء الحريق هي صندوق معدني مصمم لتخزين معدات الإطفاء مثل الخراطيم والطفايات والقرقرات. تستخدم في المباني السكنية والتجارية والصناعية.'
                    ]
                ],
                [
                    '@type' => 'Question',
                    'name' => 'هل منتجات بهسازان حاصلة على شهادات؟',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'نعم، جميع منتجات بهسازان حاصلة على شهادات معتمدة من منظمة إطفاء الحريق وخدمات السلامة في أصفهان.'
                    ]
                ],
                [
                    '@type' => 'Question',
                    'name' => 'كيف يمكن طلب المنتجات؟',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'يمكن الطلب مباشرة عبر واتساب أو تيليجرام أو الاتصال الهاتفي على الرقم +98 930 601 6798.'
                    ]
                ],
            ],
        ];
        ?>
        <meta name="description" content="<?php echo esc_attr($desc); ?>" />
        <meta name="keywords" content="خزانة حريق, ستانلس ستيل, fire cabinet, fire box, معدات إطفاء, سلامة مباني, بهسازان, خزانة إطفاء, مقاوم للصدأ, hose reel, فايربوكس" />
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large" />
        <link rel="canonical" href="<?php echo esc_url($current_url); ?>" />

        <meta property="og:type" content="product.group" />
        <meta property="og:title" content="معرض صناديق إطفاء الحريق والمعدات — بهسازان" />
        <meta property="og:description" content="<?php echo esc_attr($desc); ?>" />
        <meta property="og:url" content="<?php echo esc_url($current_url); ?>" />
        <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>" />
        <meta property="og:image" content="<?php echo esc_url($first_img); ?>" />
        <meta property="og:image:width" content="750" />
        <meta property="og:image:height" content="500" />
        <meta property="og:locale" content="ar_AR" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="معرض صناديق إطفاء الحريق والمعدات — بهسازان" />
        <meta name="twitter:description" content="<?php echo esc_attr($desc); ?>" />
        <meta name="twitter:image" content="<?php echo esc_url($first_img); ?>" />

        <script type="application/ld+json"><?php echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
        <script type="application/ld+json"><?php echo wp_json_encode($faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
        <script type="application/ld+json">{"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"الرئيسية","item":"<?php echo esc_url(home_url('/')); ?>"},{"@type":"ListItem","position":2,"name":"معرض صناديق إطفاء الحريق","item":"<?php echo esc_url($current_url); ?>"}]}</script>
        <?php
    }

    public function inject_assets() {
        if (!is_singular()) return;
        global $post;
        if (empty($post) || !has_shortcode($post->post_content, 'arabic_products')) return;
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
        :root{--arpro-red:#c81e1e;--arpro-dark:#0f172a;--arpro-gray:#f8fafc;--arpro-border:#e2e8f0;--arpro-gold:#d97706;--arpro-green:#059669}
        .arpro-section{font-family:'Cairo','Segoe UI',Tahoma,sans-serif;direction:rtl;padding:0;color:#334155;line-height:1.6}
        .arpro-header{text-align:center;padding:40px 16px 28px;background:linear-gradient(135deg,#f8fafc 0%,#e2e8f0 50%,#f1f5f9 100%);border-radius:0 0 24px 24px;margin-bottom:28px;position:relative;overflow:hidden}
        .arpro-header::before{content:'';position:absolute;top:0;right:0;left:0;height:4px;background:linear-gradient(90deg,var(--arpro-red),var(--arpro-gold))}
        .arpro-section-title{font-size:32px;font-weight:800;color:var(--arpro-dark);margin:0 0 10px;letter-spacing:-.5px}
        .arpro-section-subtitle{font-size:16px;color:#64748b;margin:0 0 24px;font-weight:500}
        .arpro-container{max-width:1200px;margin:0 auto;padding:0 12px 40px}
        .arpro-grid{display:grid;gap:20px}
        .arpro-cols-3{grid-template-columns:repeat(3,1fr)}
        @media(max-width:992px){.arpro-cols-3{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:576px){.arpro-cols-3{grid-template-columns:repeat(2,1fr);gap:10px}}

        /* Card - Desktop */
        .arpro-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);border:1px solid var(--arpro-border);transition:transform .3s cubic-bezier(.4,0,.2,1),box-shadow .3s;display:flex;flex-direction:column;position:relative}
        .arpro-card:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.12)}
        .arpro-image-wrap{position:relative;cursor:pointer;overflow:hidden;aspect-ratio:4/3;background:var(--arpro-gray)}
        .arpro-skeleton{position:absolute;inset:0;background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:arproSkeleton 1.5s infinite}
        @keyframes arproSkeleton{0%{background-position:200% 0}100%{background-position:-200% 0}}
        .arpro-image-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .5s cubic-bezier(.4,0,.2,1);position:relative;z-index:1}
        .arpro-card:hover .arpro-image-wrap img{transform:scale(1.08)}
        .arpro-brand-badge{position:absolute;top:10px;right:10px;background:rgba(15,23,42,.75);color:#fff;padding:5px 14px;border-radius:20px;font-size:11px;font-weight:700;z-index:2;backdrop-filter:blur(4px)}
        .arpro-price-badge{position:absolute;bottom:10px;left:10px;background:var(--arpro-red);color:#fff;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;z-index:2;box-shadow:0 2px 8px rgba(200,30,30,.3)}
        .arpro-body{padding:18px;display:flex;flex-direction:column;flex:1}
        .arpro-title{font-size:15px;font-weight:700;margin:0 0 10px;line-height:1.5;color:var(--arpro-dark)}
        .arpro-title a{color:inherit;text-decoration:none;transition:color .2s}
        .arpro-title a:hover{color:var(--arpro-red)}
        .arpro-excerpt{font-size:13px;color:#64748b;line-height:1.7;margin:0 0 12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1}
        .arpro-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
        .arpro-tag{background:#f1f5f9;color:#475569;padding:4px 12px;border-radius:12px;font-size:11px;font-weight:600}
        .arpro-actions{display:flex;gap:8px}
        .arpro-btn{flex:1;background:var(--arpro-red);color:#fff;border:none;padding:11px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit;box-shadow:0 2px 8px rgba(200,30,30,.2)}
        .arpro-btn:hover{background:#991b1b;transform:translateY(-2px);box-shadow:0 4px 12px rgba(200,30,30,.3)}
        .arpro-btn-wa{width:42px;height:42px;display:flex;align-items:center;justify-content:center;background:#25d366;color:#fff;border:none;border-radius:10px;font-size:18px;text-decoration:none;transition:all .2s;box-shadow:0 2px 8px rgba(37,211,102,.2)}
        .arpro-btn-wa:hover{background:#128c7e;transform:translateY(-2px);box-shadow:0 4px 12px rgba(37,211,102,.3)}

        /* Card - Mobile compact */
        @media(max-width:576px){
          .arpro-card{border-radius:12px}
          .arpro-body{padding:10px}
          .arpro-title{font-size:13px;margin:0 0 6px;line-height:1.4}
          .arpro-excerpt{font-size:11.5px;line-height:1.5;margin:0 0 8px;-webkit-line-clamp:1}
          .arpro-tags{gap:4px;margin-bottom:8px}
          .arpro-tag{padding:3px 8px;font-size:10px;border-radius:8px}
          .arpro-actions{gap:6px}
          .arpro-btn{padding:8px 10px;font-size:12px;border-radius:8px}
          .arpro-btn-wa{width:34px;height:34px;font-size:15px;border-radius:8px;flex-shrink:0}
          .arpro-brand-badge{padding:3px 8px;font-size:9px;top:6px;right:6px}
          .arpro-price-badge{padding:3px 8px;font-size:10px;bottom:6px;left:6px}
        }

        /* Load More */
        .arpro-loadmore-wrap{text-align:center;padding:32px 0}
        .arpro-loadmore{background:#fff;border:2px solid var(--arpro-border);color:var(--arpro-dark);padding:14px 36px;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;display:inline-flex;align-items:center;gap:8px}
        .arpro-loadmore:hover{border-color:var(--arpro-red);color:var(--arpro-red);transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.08)}
        .arpro-loadmore-count{color:#94a3b8;font-size:13px}
        .arpro-loadmore-icon{font-size:12px;transition:transform .2s}
        .arpro-loadmore.loading .arpro-loadmore-icon{animation:arproSpin 1s linear infinite}
        @keyframes arproSpin{to{transform:rotate(360deg)}}

        /* Modal */
        .arpro-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.65);z-index:99999;padding:16px;backdrop-filter:blur(8px);align-items:center;justify-content:center;overflow-y:auto}
        .arpro-overlay.active{display:flex}
        .arpro-modal{background:#fff;border-radius:20px;width:100%;max-width:780px;max-height:92vh;overflow:hidden;display:flex;flex-direction:column;position:relative;box-shadow:0 24px 60px rgba(0,0,0,.25);animation:arproIn .35s cubic-bezier(.16,1,.3,1)}
        @keyframes arproIn{from{opacity:0;transform:translateY(30px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
        .arpro-close{position:absolute;top:14px;left:14px;background:#fff;border:none;width:40px;height:40px;border-radius:50%;font-size:24px;cursor:pointer;z-index:10;box-shadow:0 2px 12px rgba(0,0,0,.1);display:flex;align-items:center;justify-content:center;color:#475569;transition:all .2s}
        .arpro-close:hover{color:var(--arpro-red);background:#fef2f2;transform:rotate(90deg)}
        .arpro-modal-inner{overflow-y:auto;padding:28px 32px 32px}
        .arpro-modal-header{margin-bottom:16px;padding-left:48px}
        .arpro-modal-title{font-size:24px;font-weight:800;color:var(--arpro-dark);margin:0 0 10px;line-height:1.35}
        .arpro-modal-meta{display:flex;flex-wrap:wrap;gap:6px;font-size:13px}
        .arpro-modal-meta span{background:#f1f5f9;padding:6px 14px;border-radius:8px;color:#475569;display:flex;align-items:center;gap:5px}
        .arpro-meta-price{background:#fef3c7!important;color:#92400e!important;font-weight:700}

        /* Modal meta - mobile wrap each on new line */
        @media(max-width:576px){
          .arpro-modal-inner{padding:20px 16px 24px}
          .arpro-modal-header{padding-left:40px;margin-bottom:12px}
          .arpro-modal-title{font-size:18px}
          .arpro-modal-meta{flex-direction:column;align-items:flex-start;gap:5px}
          .arpro-modal-meta span{font-size:12px;padding:5px 12px;width:fit-content}
        }

        /* Share */
        .arpro-share{display:flex;align-items:center;gap:8px;margin-bottom:18px;padding:12px 16px;background:#f8fafc;border-radius:12px;font-size:13px;color:#64748b;flex-wrap:wrap}
        .arpro-share a{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;transition:transform .2s}
        .arpro-share a:hover{transform:translateY(-2px)}
        .arpro-share-wa{background:#25d366;color:#fff}
        .arpro-share-tg{background:#0088cc;color:#fff}
        .arpro-share-fb{background:#1877f2;color:#fff}
        .arpro-share-tw{background:#000;color:#fff}

        /* Tabs */
        .arpro-tabs{display:flex;gap:4px;border-bottom:2px solid var(--arpro-border);margin-bottom:20px;background:#f8fafc;padding:5px;border-radius:14px}
        .arpro-tab{flex:1;background:transparent;border:none;padding:11px 8px;border-radius:10px;font-size:13px;font-weight:700;color:#64748b;cursor:pointer;font-family:inherit;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:5px}
        .arpro-tab.active{background:#fff;color:var(--arpro-red);box-shadow:0 1px 6px rgba(0,0,0,.08)}
        .arpro-tab:hover{color:var(--arpro-dark)}

        /* Panels */
        .arpro-panel{display:none}
        .arpro-panel.active{display:block;animation:arproFade .3s ease}
        @keyframes arproFade{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

        /* Overview */
        .arpro-panel-overview{display:grid;grid-template-columns:260px 1fr;gap:24px;align-items:start}
        @media(max-width:640px){.arpro-panel-overview{grid-template-columns:1fr}}
        .arpro-panel-img{position:relative;border-radius:16px;overflow:hidden;background:var(--arpro-gray);box-shadow:0 4px 12px rgba(0,0,0,.06)}
        .arpro-panel-img img{width:100%;height:auto;display:block}
        .arpro-img-brand{position:absolute;bottom:12px;left:12px;background:rgba(15,23,42,.7);color:#fff;padding:6px 16px;border-radius:10px;font-size:12px;font-weight:700;backdrop-filter:blur(4px)}
        .arpro-panel-badge{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
        .arpro-badge-cert,.arpro-badge-stock{font-size:12px;padding:6px 14px;border-radius:20px;font-weight:600}
        .arpro-badge-cert{background:#dcfce7;color:#166534}
        .arpro-badge-stock{background:#dbeafe;color:#1e40af}
        .arpro-panel-desc p{font-size:14px;line-height:1.85;color:#475569;margin:0 0 12px}
        .arpro-panel-excerpt{font-size:15px!important;color:var(--arpro-dark)!important;font-weight:600}
        .arpro-long-desc{font-size:13.5px;color:#64748b}
        .arpro-panel-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:16px}
        .arpro-panel-tag{background:#fef3c7;color:#92400e;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #fde68a}

        /* Specs */
        .arpro-specs h3{font-size:17px;margin:0 0 16px;color:var(--arpro-dark);display:flex;align-items:center;gap:8px}
        .arpro-specs-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media(max-width:480px){.arpro-specs-grid{grid-template-columns:1fr}}
        .arpro-spec-item{background:#f8fafc;border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:4px;border-right:4px solid var(--arpro-green);transition:transform .15s}
        .arpro-spec-item:hover{transform:translateX(-3px);background:#f1f5f9}
        .arpro-spec-key{font-size:12px;color:#94a3b8;font-weight:600}
        .arpro-spec-val{font-size:14px;color:#1e293b;font-weight:700}

        /* Order */
        .arpro-order{text-align:center;padding:8px 0}
        .arpro-order-product{display:flex;align-items:center;gap:16px;background:#f8fafc;padding:16px;border-radius:16px;margin-bottom:20px;text-align:right;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .arpro-order-product img{width:72px;height:72px;object-fit:cover;border-radius:12px}
        .arpro-order-product div{flex:1}
        .arpro-order-product strong{display:block;font-size:16px;color:var(--arpro-dark);margin-bottom:4px}
        .arpro-order-product span{font-size:13px;color:#64748b}
        .arpro-order-note{font-size:15px;color:#475569;margin-bottom:20px;font-weight:500}
        .arpro-order-actions{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;max-width:520px;margin:0 auto 18px}
        @media(max-width:480px){.arpro-order-actions{grid-template-columns:1fr}}
        .arpro-order-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px 18px;border-radius:12px;text-decoration:none;font-weight:800;font-size:14px;color:#fff;transition:all .2s;border:none;cursor:pointer;font-family:inherit}
        .arpro-order-btn:hover{transform:translateY(-3px)}
        .arpro-wa{background:#25d366}.arpro-wa:hover{box-shadow:0 6px 20px rgba(37,211,102,.35)}
        .arpro-tg{background:#0088cc}.arpro-tg:hover{box-shadow:0 6px 20px rgba(0,136,204,.35)}
        .arpro-call{background:#64748b}.arpro-call:hover{box-shadow:0 6px 20px rgba(100,116,139,.35)}
        .arpro-icon{font-size:18px}
        .arpro-order-phone{background:#f8fafc;padding:14px;border-radius:12px;font-size:14px;color:#475569;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px}
        .arpro-order-phone strong{color:var(--arpro-red);font-size:18px;letter-spacing:.5px}
        .arpro-order-hint{font-size:12px;color:#94a3b8;margin-top:14px}

        body.arpro-noscroll{overflow:hidden}
        @media print{.arpro-overlay{display:none!important}.arpro-header{background:#fff!important}}
        </style>

        <script>
        (function(){
            var allProducts=[],visibleCount=0,initialLimit=6,perPage=6;

            function init(){
                var sec=document.querySelector('.arpro-section');
                if(!sec)return;
                try{allProducts=JSON.parse(sec.dataset.products||'[]');}catch(e){console.error('ArPro: invalid JSON',e);}
                initialLimit=parseInt(sec.dataset.initial||'6',10);
                perPage=parseInt(sec.dataset.perpage||'6',10);
                visibleCount=initialLimit;
            }

            window.arproOpen=function(id){
                var el=document.getElementById('arpro-modal-'+id);
                if(el){el.classList.add('active');document.body.classList.add('arpro-noscroll');if(history.pushState){history.pushState(null,null,'#product-'+id);}}
            };
            window.arproClose=function(id){
                var el=document.getElementById('arpro-modal-'+id);
                if(el){el.classList.remove('active');document.body.classList.remove('arpro-noscroll');if(history.pushState){history.pushState(null,null,window.location.pathname+window.location.search);}}
            };
            window.arproTab=function(id,idx){
                var modal=document.getElementById('arpro-modal-'+id);
                if(!modal)return;
                modal.querySelectorAll('.arpro-tab').forEach(function(t,i){t.classList.toggle('active',i===idx);});
                modal.querySelectorAll('.arpro-panel').forEach(function(p){p.classList.toggle('active',parseInt(p.dataset.panel,10)===idx);});
            };

            window.arproLoadMore=function(){
                var btn=document.getElementById('arpro-loadmore-btn');
                if(btn)btn.classList.add('loading');
                var toShow=allProducts.slice(visibleCount,visibleCount+perPage);
                var grid=document.getElementById('arpro-grid');
                toShow.forEach(function(p){
                    var card=buildCard(p);
                    grid.appendChild(card);
                    requestAnimationFrame(function(){
                        card.style.opacity='0';
                        card.style.transform='translateY(20px)';
                        requestAnimationFrame(function(){
                            card.style.transition='opacity .4s ease, transform .4s ease';
                            card.style.opacity='1';
                            card.style.transform='translateY(0)';
                        });
                    });
                });
                visibleCount+=toShow.length;
                if(visibleCount>=allProducts.length){
                    var wrap=document.getElementById('arpro-loadmore-wrap');
                    if(wrap)wrap.style.display='none';
                }
                if(btn)btn.classList.remove('loading');
            };

            function buildCard(p){
                var wa='https://wa.me/989306016798?text='+encodeURIComponent('مرحباً، أرغب في طلب: '+p.title+' (رمز: '+p.sku+')');
                var tags=(p.tags||[]).slice(0,3).map(function(t){return '<span class="arpro-tag">'+escapeHtml(t)+'</span>';}).join('');
                var article=document.createElement('article');
                article.className='arpro-card';
                article.setAttribute('data-id',p.id);
                article.setAttribute('itemscope','');
                article.setAttribute('itemtype','https://schema.org/Product');

                article.innerHTML=
                    '<div class="arpro-image-wrap" onclick="arproOpen('+parseInt(p.id,10)+')">'+
                    '<div class="arpro-skeleton"></div>'+
                    '<img src="'+escapeHtml(p.image)+'" alt="'+escapeHtml(p.title)+'" loading="lazy" itemprop="image" onload="this.previousElementSibling.style.display=\'none\'" />'+
                    (p.brand?'<span class="arpro-brand-badge">'+escapeHtml(p.brand)+'</span>':'')+
                    '</div>'+
                    '<div class="arpro-body">'+
                    '<h2 class="arpro-title" itemprop="name"><a href="javascript:void(0)" onclick="arproOpen('+parseInt(p.id,10)+')">'+escapeHtml(p.title)+'</a></h2>'+
                    '<p class="arpro-excerpt" itemprop="description">'+escapeHtml(p.excerpt)+'</p>'+
                    (tags?'<div class="arpro-tags">'+tags+'</div>':'')+
                    '<div class="arpro-actions">'+
                    '<button class="arpro-btn" onclick="arproOpen('+parseInt(p.id,10)+')">عرض التفاصيل</button>'+
                    '<a href="'+wa+'" class="arpro-btn-wa" target="_blank" rel="noopener noreferrer">&#x1F4AC;</a>'+
                    '</div></div>';
                return article;
            }

            function escapeHtml(text){
                if(!text)return '';
                var div=document.createElement('div');
                div.textContent=text;
                return div.innerHTML;
            }

            document.addEventListener('click',function(e){
                if(e.target.classList.contains('arpro-overlay')){e.target.classList.remove('active');document.body.classList.remove('arpro-noscroll');}
            });
            document.addEventListener('keydown',function(e){
                if(e.key==='Escape'){
                    document.querySelectorAll('.arpro-overlay.active').forEach(function(el){el.classList.remove('active');});
                    document.body.classList.remove('arpro-noscroll');
                }
            });
            window.addEventListener('load',function(){
                init();
                var h=window.location.hash;
                if(h&&h.startsWith('#product-')){var id=h.replace('#product-','');setTimeout(function(){arproOpen(id);},400);}
            });
        })();
        </script>
        <?php
    }
}

new Arabic_Products_Showcase();
























/**
 * شورتکد آیکون ترنسپرنت با منوی کشویی
 * استفاده: [translate_dropdown]
 */
function bsma_translate_dropdown_final_shortcode() {
    static $instance = 0;
    $instance++;
    $uid = 'bsma-td-' . $instance;
    
    ob_start();
    ?>
    
    <style type="text/css">
        .<?php echo $uid; ?>-wrap {
            position: relative;
            display: inline-block;
        }
        
        .<?php echo $uid; ?>-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: transparent;
            border: none;
            cursor: pointer;
            outline: none;
            padding: 0;
        }
        
        .<?php echo $uid; ?>-btn svg {
            width: 22px;
            height: 22px;
            fill: #2d2d2d;
            transition: fill 0.2s ease;
        }
        
        .<?php echo $uid; ?>-btn:hover svg,
        .<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-btn svg {
            fill: #000000;
        }
        
        .<?php echo $uid; ?>-menu {
            position: absolute;
            top: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%) translateY(-6px);
            background: #ffffff;
            border: 1px solid #c0c0c0;
            border-radius: 8px;
            padding: 4px;
            min-width: 200px;
            width: max-content;
            max-width: 90vw;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 99999;
            pointer-events: none;
            box-sizing: border-box;
        }
        
        .<?php echo $uid; ?>-menu::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%) rotate(45deg);
            width: 10px;
            height: 10px;
            background: #ffffff;
            border-top: 1px solid #c0c0c0;
            border-left: 1px solid #c0c0c0;
            z-index: 1;
        }
        
        .<?php echo $uid; ?>-wrap:hover .<?php echo $uid; ?>-menu {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
            pointer-events: all;
        }
        
        .<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-menu {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
            pointer-events: all;
        }
        
        .<?php echo $uid; ?>-menu a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            color: #2d2d2d;
            text-decoration: none !important;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            border-radius: 6px;
            white-space: nowrap;
        }
        
        .<?php echo $uid; ?>-menu a:hover {
            background: #f0f0f0;
            color: #000000;
        }
        
        .<?php echo $uid; ?>-menu a svg {
            width: 16px;
            height: 16px;
            fill: #2d2d2d;
            flex-shrink: 0;
        }
        
        .<?php echo $uid; ?>-menu a .ar-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #2d2d2d;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 4px;
            margin-right: auto;
            font-family: Arial, sans-serif;
        }
        
        /* موبایل: fixed + z-index فوق‌العاده بالا */
        @media (max-width: 768px) {
            .<?php echo $uid; ?>-wrap:hover .<?php echo $uid; ?>-menu {
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
            }
            .<?php echo $uid; ?>-menu {
                position: fixed;
                left: auto;
                right: auto;
                top: auto;
                transform: none;
                max-width: calc(100vw - 32px);
                z-index: 999999;
            }
            .<?php echo $uid; ?>-menu::before {
                display: none;
            }
            .<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-menu {
                opacity: 1;
                visibility: visible;
                pointer-events: all;
            }
            /* وقتی منو باز است (active) — رنگ‌های معکوس */
.<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-btn {
    background: #2d2d2d;
    border-radius: 8px;
}

.<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-btn svg {
    fill: #ffffff;
}

/* وقتی منو باز است — لینک‌ها هم معکوس شوند */
.<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-menu a:hover {
    background: #2d2d2d;
    color: #ffffff;
}

.<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-menu a:hover svg {
    fill: #ffffff;
}

.<?php echo $uid; ?>-wrap.active .<?php echo $uid; ?>-menu a:hover .ar-badge {
    background: #ffffff;
    color: #2d2d2d;
}
        }
    </style>
    
    <div class="<?php echo $uid; ?>-wrap" id="<?php echo $uid; ?>">
        <button class="<?php echo $uid; ?>-btn" id="<?php echo $uid; ?>-btn" aria-label="Translate" onclick="bsmaToggle<?php echo $instance; ?>(event)">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
            </svg>
        </button>
        
        <div class="<?php echo $uid; ?>-menu" id="<?php echo $uid; ?>-menu">
            <a href="https://bsma.ir/صناديق-إطفاء-الحريق/" target="_blank" rel="noopener noreferrer">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                </svg>
                <span>صندوق إطفاء الحريق</span>
                <span class="ar-badge">Ar</span>
            </a>
        </div>
    </div>
    
    <script>
        (function(){
            var uid = '<?php echo $uid; ?>';
            var wrap = document.getElementById(uid);
            var btn = document.getElementById(uid + '-btn');
            var menu = document.getElementById(uid + '-menu');
            
            function isMobile() {
                return window.innerWidth <= 768;
            }
            
            function resetMenuStyles() {
                menu.style.top = '';
                menu.style.left = '';
                menu.style.right = '';
                menu.style.transform = '';
            }
            
            function positionMenu() {
                if (!isMobile() || !wrap.classList.contains('active')) return;
                
                var rect = btn.getBoundingClientRect();
                var menuWidth = menu.offsetWidth || 200;
                var menuHeight = menu.offsetHeight || 100;
                var vw = window.innerWidth;
                var vh = window.innerHeight;
                var margin = 10;
                
                // --- موقعیت عمودی ---
                var top = rect.bottom + margin;
                
                // اگر از پایین صفحه بیرون زد، بالای دکمه باز شود
                if (top + menuHeight > vh - margin) {
                    top = rect.top - menuHeight - margin;
                    if (top < margin) top = margin;
                }
                
                // --- موقعیت افقی ---
                // منو را تراز با چپ دکمه شروع می‌کنیم
                var left = rect.left;
                
                // اگر از راست صفحه بیرون زد، به چپ می‌کشیم
                if (left + menuWidth > vw - margin) {
                    left = vw - menuWidth - margin;
                }
                
                // اگر از چپ صفحه بیرون زد، به راست می‌کشیم
                if (left < margin) {
                    left = margin;
                }
                
                menu.style.top = top + 'px';
                menu.style.left = left + 'px';
                menu.style.right = 'auto';
                menu.style.transform = 'none';
            }
            
            window['bsmaToggle<?php echo $instance; ?>'] = function(e) {
                e.stopPropagation();
                wrap.classList.toggle('active');
                if (wrap.classList.contains('active')) {
                    positionMenu();
                } else {
                    resetMenuStyles();
                }
            };
            
            document.addEventListener('click', function(e) {
                if (!wrap.contains(e.target)) {
                    wrap.classList.remove('active');
                    resetMenuStyles();
                }
            });
            
            window.addEventListener('resize', function() {
                if (!wrap.classList.contains('active')) return;
                if (isMobile()) {
                    positionMenu();
                } else {
                    resetMenuStyles();
                }
            });
            
            window.addEventListener('scroll', function() {
                if (wrap.classList.contains('active') && isMobile()) {
                    positionMenu();
                }
            });
        })();
    </script>
    
    <?php
    return ob_get_clean();
}

add_shortcode('translate_dropdown', 'bsma_translate_dropdown_final_shortcode');