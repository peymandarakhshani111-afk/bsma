<?php
/*
Plugin Name: DigiKala Product Archive
Description: WooCommerce product archive with AJAX filters, accordion categories and load more
Version: 2.1
Author: محمد قربانی
*/

if (!defined('ABSPATH')) exit;

/* ─── Helpers ─── */

if (!function_exists('dk_get_category_tree')) {
    function dk_get_category_tree($parent_id = 0, $taxonomy = 'product_cat', $current_cat_id = null) {
        // Load every term once per request instead of one get_terms() query per tree node.
        static $by_parent = array();
        if (!isset($by_parent[$taxonomy])) {
            $by_parent[$taxonomy] = array();
            $all_terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ));
            if (!empty($all_terms) && !is_wp_error($all_terms)) {
                foreach ($all_terms as $t) {
                    $by_parent[$taxonomy][(int) $t->parent][] = $t;
                }
            }
        }
        $terms = array();
        foreach ($by_parent[$taxonomy][(int) $parent_id] ?? array() as $t) {
            $terms[] = clone $t;
        }

        // bsma 2026-09-24: sub-levels follow the WooCommerce manual category order (ties stay A-Z)
        if ($parent_id > 0 && !empty($terms) && !is_wp_error($terms)) {
            $terms = array_values($terms);
            $dk_orders = array();
            $dk_index = array();
            foreach ($terms as $i => $term) {
                $dk_orders[] = (int) get_term_meta($term->term_id, 'order', true);
                $dk_index[] = $i;
            }
            array_multisort($dk_orders, SORT_ASC, SORT_NUMERIC, $dk_index, SORT_ASC, SORT_NUMERIC, $terms);
        }
        
        $tree = array();
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $term->is_active = ($current_cat_id && $current_cat_id == $term->term_id);
                $term->is_current_parent = ($current_cat_id && term_is_ancestor_of($term->term_id, $current_cat_id, $taxonomy));
                $term->children = dk_get_category_tree($term->term_id, $taxonomy, $current_cat_id);
                $tree[] = $term;
            }
        }
        return $tree;
    }
}

if (!function_exists('dk_render_category_accordion')) {
    function dk_render_category_accordion($categories, $uid, $level = 0, $parent_expanded = false) {
        if (empty($categories)) return;

        $container_class = $level === 0 ? $uid . '-cat-root' : $uid . '-cat-children';

        echo '<div class="' . $container_class . '" data-level="' . $level . '" style="' . ($level > 0 ? 'display:none;' : '') . '">';

        foreach ($categories as $cat) {
            $has_children = !empty($cat->children);
            $is_expanded = !empty($cat->is_active) || !empty($cat->is_current_parent);
            $is_checked = !empty($cat->is_active);
            $child_class = $has_children ? 'has-children' : 'no-children';
            $expanded_class = $is_expanded ? 'expanded' : '';
            $checked_class = $is_checked ? 'checked' : '';

            $toggle_icon = '';
            if ($has_children) {
                $rotate = $is_expanded ? 'rotate(90deg)' : 'rotate(0deg)';
                $toggle_icon = '<span class="' . $uid . '-toggle-icon" style="transform: ' . $rotate . '">▸</span>';
            }

            $checkbox = '<span class="' . $uid . '-checkbox' . ($is_checked ? ' checked' : '') . '"></span>';

            echo '<div class="' . $uid . '-cat-node ' . $child_class . ' ' . $expanded_class . '" data-cat-id="' . $cat->term_id . '" data-cat-slug="' . esc_attr($cat->slug) . '" data-level="' . $level . '">';

            echo '<div class="' . $uid . '-cat-row ' . $checked_class . '" data-tip="' . esc_attr($cat->name) . '">';

            if ($has_children) {
                echo '<button type="button" class="' . $uid . '-toggle-btn" onclick="toggleCat_' . $uid . '(this, ' . $cat->term_id . ')" aria-label="باز/بسته">' . $toggle_icon . '</button>';
            } else {
                echo '<span class="' . $uid . '-toggle-spacer"></span>';
            }

            echo '<div class="' . $uid . '-cat-content" onclick="selectCat_' . $uid . '(' . $cat->term_id . ', \'' . esc_attr($cat->slug) . '\')">';
            echo $checkbox;
            echo '<span class="' . $uid . '-cat-name">' . esc_html($cat->name) . '</span>';
            echo '<span class="' . $uid . '-cat-count">' . number_format($cat->count) . '</span>';
            echo '</div>';

            echo '</div>';

            if ($has_children) {
                dk_render_category_accordion($cat->children, $uid, $level + 1, $is_expanded);
            }

            echo '</div>';
        }

        echo '</div>';
    }
}

/* ─── bsma 2026-09-24: featured product card under the sidebar filters (a different product of the category on each view) ─── */

if (!function_exists('dk_sidebar_promo')) {
    function dk_sidebar_promo($root_id) {
        $enabled = array(279); // category ids that show the card
        if (!in_array((int) $root_id, $enabled, true)) return array();
        $ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'fields' => 'ids',
            'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'),
            'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => (int) $root_id)),
            'meta_query' => array(array('key' => '_stock_status', 'value' => 'outofstock', 'compare' => '!=')),
        ));
        $items = array();
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product || !$product->get_image_id()) continue;
            $img = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
            if (!$img) continue;
            $brand = '';
            $brand_terms = get_the_terms($id, 'product_brand');
            if ($brand_terms && !is_wp_error($brand_terms)) {
                $first_brand = reset($brand_terms);
                $brand = $first_brand->name;
                foreach ($brand_terms as $brand_term) {
                    if ($brand_term->slug !== 'bsma') { $brand = $brand_term->name; break; }
                }
            }
            $items[] = array('url' => $product->get_permalink(), 'img' => $img, 'name' => $product->get_name(), 'brand' => $brand);
        }
        return $items;
    }
}

/* ─── Build WP_Query args (shared by PHP & AJAX) ─── */

function dk_build_query_args($root_cat, $cats, $mode, $min_price, $max_price, $sort, $page, $per_page) {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'publish',
        'meta_query'     => array(),
    );

    switch ($sort) {
        case 'price': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_price'; $args['order'] = 'ASC'; break;
        case 'price-desc': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_price'; $args['order'] = 'DESC'; break;
        case 'popularity': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = 'total_sales'; $args['order'] = 'DESC'; break;
        case 'menu_order': $args['orderby'] = array('menu_order' => 'ASC', 'date' => 'DESC'); break;
        default: $args['orderby'] = 'date'; $args['order'] = 'DESC';
    }

    if (!empty($cats)) {
        $cat_ids = array_unique(array_map('intval', $cats));
        $args['tax_query'] = array(array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $cat_ids,
            'operator' => 'IN'
        ));
    } elseif ($root_cat > 0) {
        $args['tax_query'] = array(array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $root_cat,
            'operator' => 'IN'
        ));
    }

    if ($min_price !== null || $max_price !== null) {
        $price_q = array('key' => '_price', 'type' => 'NUMERIC');
        if ($min_price !== null && $max_price !== null) {
            $price_q['value'] = array($min_price, $max_price);
            $price_q['compare'] = 'BETWEEN';
        } elseif ($min_price !== null) {
            $price_q['value'] = $min_price;
            $price_q['compare'] = '>=';
        } else {
            $price_q['value'] = $max_price;
            $price_q['compare'] = '<=';
        }
        $args['meta_query'][] = $price_q;
    }

    return $args;
}

/* ─── Render single product card HTML ─── */

function dk_render_product_card($product, $uid) {
    $price = $product->get_price();
    $regular_price = $product->get_regular_price();
    $has_price = !empty($price) && floatval($price) > 0;

    if ($product->is_type('variable')) {
        $price = $product->get_variation_price('min', true);
        $regular_price = $product->get_variation_regular_price('min', true);
        $has_price = !empty($price) && floatval($price) > 0;
    }

    $discount = ($has_price && $regular_price > $price) ? round((1 - $price/$regular_price)*100) : 0;

    $price_html = $has_price
        ? '<div class="' . $uid . '-price-new">' . number_format((float)$price) . ' <span>ريال</span></div>'
        : '<div class="' . $uid . '-price-contact">تماس بگیرید</div>';

    $old_price_html = ($has_price && $regular_price > $price)
        ? '<div class="' . $uid . '-price-old">' . number_format((float)$regular_price) . ' ريال</div>'
        : '<div style="height:20px;"></div>';

    $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src();

    /* bsma 2026-09-24: brand chip (prefer the maker's brand over BSMA) */
    $brand_name = '';
    $brand_terms = get_the_terms($product->get_id(), 'product_brand');
    if ($brand_terms && !is_wp_error($brand_terms)) {
        $first_brand = reset($brand_terms);
        $brand_name = $first_brand->name;
        foreach ($brand_terms as $brand_term) {
            if ($brand_term->slug !== 'bsma') { $brand_name = $brand_term->name; break; }
        }
    }

    ob_start();
    ?>
    <a href="<?php echo esc_url($product->get_permalink()); ?>" class="<?php echo $uid; ?>-product-link" aria-label="<?php echo esc_attr($product->get_name()); ?>">
        <div class="<?php echo $uid; ?>-product">
            <?php if ($discount): ?>
                <div class="<?php echo $uid; ?>-badge"><?php echo $discount; ?>%</div>
            <?php endif; ?>
            <div class="<?php echo $uid; ?>-product-img-wrap">
                <img src="<?php echo esc_url($image); ?>" class="<?php echo $uid; ?>-product-img" onerror="this.src='<?php echo wc_placeholder_img_src(); ?>'" loading="lazy" alt="<?php echo esc_attr($product->get_name()); ?>">
            </div>
            <div class="<?php echo $uid; ?>-product-body">
                <?php if ($brand_name !== ''): ?><span class="<?php echo $uid; ?>-brand"><?php echo esc_html($brand_name); ?></span><?php endif; ?>
                <h3 class="<?php echo $uid; ?>-product-title"><?php echo esc_html($product->get_name()); ?></h3>
                <div class="<?php echo $uid; ?>-product-footer">
                    <div class="<?php echo $uid; ?>-price-wrap">
                        <?php echo $old_price_html; ?>
                        <?php echo $price_html; ?>
                    </div>
                </div>
            </div>
        </div>
    </a>
    <?php
    return ob_get_clean();
}

/* ─── Shortcode ─── */

add_shortcode('digikala_archive', 'digikala_archive_accordion_shortcode');
function digikala_archive_accordion_shortcode($atts) {
    if (!class_exists('WooCommerce')) return '<p>ووکامرس فعال نیست!</p>';

    static $instance = 0;
    $instance++;
    $uid = 'dkacc_' . uniqid();
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('dk_archive_nonce');

    $current_cat = null;
    $current_cat_id = 0;
    $is_tax_page = false;
    $current_term = null;

    if (is_tax('product_cat')) {
        $current_term = get_queried_object();
        if ($current_term && !is_wp_error($current_term)) {
            $current_cat = $current_term->slug;
            $current_cat_id = $current_term->term_id;
            $is_tax_page = true;
        }
    } elseif (is_singular('product')) {
        global $post;
        $product_cats = get_the_terms($post->ID, 'product_cat');
        if ($product_cats && !is_wp_error($product_cats)) {
            $current_term = $product_cats[0];
            $current_cat = $current_term->slug;
            $current_cat_id = $current_term->term_id;
        }
    }

    $atts = shortcode_atts(array(
        'per_page'     => 12,
        'category'     => '',
        'title'        => '',
        'show_filters' => 'yes',
    ), $atts);

    $per_page = intval($atts['per_page']);
    $show_filters = $atts['show_filters'] === 'yes';
    $default_sort = $is_tax_page ? 'menu_order' : 'date'; // bsma 2026-09-24: manual order on category pages

    if (!empty($atts['category']) && $atts['category'] !== 'auto') {
        $root_cat_obj = get_term_by('slug', sanitize_text_field($atts['category']), 'product_cat');
        $root_id = $root_cat_obj ? $root_cat_obj->term_id : 0;
        $root_cat = $root_cat_obj;
    } else {
        $root_id = $current_cat_id;
        $root_cat = $current_term;
    }

    $parent_id_for_tree = $root_id ? $root_id : 0;
    $category_tree = dk_get_category_tree($parent_id_for_tree, 'product_cat', $current_cat_id);

    $title = sanitize_text_field($atts['title']);
    if (empty($title)) {
        $title = ($root_cat && !is_wp_error($root_cat)) ? $root_cat->name : 'همه محصولات';
    }

    /* ─── SERVER-SIDE INITIAL QUERY ─── */
    $initial_args = dk_build_query_args($root_id, array(), 'expanded', null, null, $default_sort, 1, $per_page);
    $initial_query = new WP_Query($initial_args);
    $initial_products_html = '';
    $initial_total = $initial_query->found_posts;
    $initial_total_pages = $initial_query->max_num_pages;
    $initial_has_more = $initial_total_pages > 1;

    /* bsma 2026-09-24: hide the price filter when most products have no price */
    $show_price_filter = true;
    if ($show_filters) {
        $priced_args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(array('key' => '_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC')),
        );
        if ($root_id > 0) {
            $priced_args['tax_query'] = array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $root_id, 'operator' => 'IN'));
        }
        $priced_query = new WP_Query($priced_args);
        $show_price_filter = $priced_query->found_posts >= 3 && $priced_query->found_posts * 2 >= $initial_total;
    }
    $promo = $show_filters ? dk_sidebar_promo($root_id) : null;
    $has_sidebar = !empty($category_tree) || $show_price_filter || !empty($promo);

    if ($initial_query->have_posts()) {
        while ($initial_query->have_posts()) {
            $initial_query->the_post();
            global $product;
            if ($product) {
                $initial_products_html .= dk_render_product_card($product, $uid);
            }
        }
    }
    wp_reset_postdata();

    ob_start();
    ?>

<style>
@font-face {
    font-family: 'Vazirmatn';
    src: local('Vazirmatn Regular'), local('Vazirmatn-Regular'),
         url('<?php echo get_stylesheet_directory_uri(); ?>/fonts/Vazirmatn-Regular.woff2') format('woff2'),
         url('<?php echo get_stylesheet_directory_uri(); ?>/fonts/Vazirmatn-Regular.woff') format('woff');
    font-weight: 400; font-style: normal; font-display: swap;
}
@font-face {
    font-family: 'Vazirmatn';
    src: local('Vazirmatn Medium'), local('Vazirmatn-Medium'),
         url('<?php echo get_stylesheet_directory_uri(); ?>/fonts/Vazirmatn-Medium.woff2') format('woff2'),
         url('<?php echo get_stylesheet_directory_uri(); ?>/fonts/Vazirmatn-Medium.woff') format('woff');
    font-weight: 500; font-style: normal; font-display: swap;
}
@font-face {
    font-family: 'Vazirmatn';
    src: local('Vazirmatn Bold'), local('Vazirmatn-Bold'),
         url('<?php echo get_stylesheet_directory_uri(); ?>/fonts/Vazirmatn-Bold.woff2') format('woff2'),
         url('<?php echo get_stylesheet_directory_uri(); ?>/fonts/Vazirmatn-Bold.woff') format('woff');
    font-weight: 700; font-style: normal; font-display: swap;
}

.<?php echo $uid; ?> {
    --dk-primary: #ef394e; --dk-primary-hover: #d32f2f; --dk-dark: #232933;
    --dk-text: #424750; --dk-text-light: #a1a3a8; --dk-border: #e0e0e2;
    --dk-bg: #f5f5f5; --dk-white: #ffffff; --dk-radius: 8px; --dk-radius-lg: 12px;
    --dk-shadow-sm: 0 1px 1px rgba(0,0,0,0.04); --dk-shadow: 0 2px 8px rgba(0,0,0,0.08);
    --dk-shadow-hover: 0 8px 24px rgba(0,0,0,0.12); --dk-shadow-drawer: -4px 0 24px rgba(0,0,0,0.16);
    font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif; background: var(--dk-bg);
    min-height: 100vh; padding-bottom: 40px; color: var(--dk-text); line-height: 1.6;
    overflow-x: hidden; overflow-x: clip; position: relative; /* clip keeps the sidebar sticky */
}
.<?php echo $uid; ?>-header { max-width: 1400px; margin: 0 auto; padding: 24px 20px 16px; background: var(--dk-white); border-bottom: 1px solid var(--dk-border); }
.<?php echo $uid; ?>-title-wrap { display: flex; align-items: baseline; gap: 16px; flex-wrap: wrap; }
.<?php echo $uid; ?>-title { font-size: 26px; font-weight: 800; color: var(--dk-dark); margin: 0; letter-spacing: -0.5px; }
.<?php echo $uid; ?>-count { font-size: 14px; color: var(--dk-text-light); font-weight: 500; }
.<?php echo $uid; ?>-layout { max-width: 1400px; margin: 0 auto; display: flex; gap: 20px; padding: 24px 20px; align-items: flex-start; }
.<?php echo $uid; ?>-sidebar { width: 280px; flex-shrink: 0; position: sticky; top: var(--dk-sticky-top, 24px); height: fit-content; max-height: calc(100vh - var(--dk-sticky-top, 24px) - 24px); overflow-y: auto; scrollbar-width: thin; }
.<?php echo $uid; ?>-box { background: var(--dk-white); border-radius: var(--dk-radius-lg); padding: 20px; margin-bottom: 16px; box-shadow: var(--dk-shadow-sm); border: 1px solid var(--dk-border); transition: box-shadow 0.2s; }
.<?php echo $uid; ?>-box:hover { box-shadow: var(--dk-shadow); }
.<?php echo $uid; ?>-box-title { font-size: 16px; font-weight: 700; color: var(--dk-dark); margin: 0 0 20px; display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid #f0f0f1; }
.<?php echo $uid; ?>-clear { font-size: 13px; color: var(--dk-primary); cursor: pointer; opacity: 0; transition: all 0.2s; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
.<?php echo $uid; ?>-clear:hover { background: #fff5f5; }
.<?php echo $uid; ?>-clear.active { opacity: 1; }
.<?php echo $uid; ?>-cat-root { display: flex; flex-direction: column; gap: 4px; }
.<?php echo $uid; ?>-cat-node { display: flex; flex-direction: column; }
.<?php echo $uid; ?>-cat-row { display: flex; align-items: center; gap: 10px; padding: 10px 8px; border-radius: 8px; cursor: pointer; transition: all 0.2s; position: relative; }
.<?php echo $uid; ?>-cat-row:hover { background: #f8f9fa; }
.<?php echo $uid; ?>-cat-row.checked { background: #fff0f2; }
.<?php echo $uid; ?>-toggle-btn { width: 28px; height: 28px; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s; flex-shrink: 0; color: var(--dk-text-light); }
.<?php echo $uid; ?>-toggle-btn:hover { background: #e8e8e8; color: var(--dk-dark); }
.<?php echo $uid; ?>-toggle-icon { display: inline-block; transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); font-size: 14px; }
.<?php echo $uid; ?>-cat-node.expanded > .<?php echo $uid; ?>-cat-row .<?php echo $uid; ?>-toggle-icon { transform: rotate(90deg); }
.<?php echo $uid; ?>-toggle-spacer { width: 28px; flex-shrink: 0; }
.<?php echo $uid; ?>-cat-content { flex: 1; display: flex; align-items: center; gap: 10px; min-width: 0; }
.<?php echo $uid; ?>-checkbox { width: 20px; height: 20px; border: 2px solid #c0c2c5; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; background: white; }
.<?php echo $uid; ?>-checkbox.checked { background: var(--dk-primary); border-color: var(--dk-primary); }
.<?php echo $uid; ?>-checkbox::after { content: ''; width: 6px; height: 10px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg) translate(-1px, -1px); opacity: 0; transition: opacity 0.2s; }
.<?php echo $uid; ?>-checkbox.checked::after { opacity: 1; }
.<?php echo $uid; ?>-cat-name { font-size: 14px; color: var(--dk-dark); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
.<?php echo $uid; ?>-cat-row.<?php echo $uid; ?>-trunc:hover::after { content: attr(data-tip); position: absolute; bottom: calc(100% + 6px); right: 56px; width: max-content; max-width: 180px; background: var(--dk-dark); color: #fff; font-size: 12.5px; font-weight: 600; line-height: 1.6; padding: 6px 12px; border-radius: 8px; white-space: normal; z-index: 6; box-shadow: 0 6px 18px rgba(0,0,0,0.18); pointer-events: none; animation: dkTipIn 0.15s ease-out; }
.<?php echo $uid; ?>-cat-row.<?php echo $uid; ?>-trunc:hover::before { content: ''; position: absolute; bottom: calc(100% - 6px); right: 76px; border: 6px solid transparent; border-top-color: var(--dk-dark); z-index: 6; pointer-events: none; animation: dkTipIn 0.15s ease-out; }
@keyframes dkTipIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
.<?php echo $uid; ?>-cat-row.checked .<?php echo $uid; ?>-cat-name { color: var(--dk-primary); font-weight: 700; }
.<?php echo $uid; ?>-cat-count { font-size: 12px; color: var(--dk-text-light); background: #f5f5f5; padding: 3px 8px; border-radius: 12px; flex-shrink: 0; font-weight: 500; min-width: 24px; text-align: center; }
.<?php echo $uid; ?>-cat-row.checked .<?php echo $uid; ?>-cat-count { background: rgba(239, 57, 78, 0.1); color: var(--dk-primary); }
.<?php echo $uid; ?>-cat-children { margin-right: 38px; border-right: 2px solid #f0f0f1; padding-right: 8px; margin-top: 4px; display: none; }
.<?php echo $uid; ?>-cat-node.expanded > .<?php echo $uid; ?>-cat-children { display: block; animation: slideDown 0.25s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
.<?php echo $uid; ?>-cat-children[data-level="2"] { margin-right: 32px; }
.<?php echo $uid; ?>-cat-children[data-level="3"] { margin-right: 26px; }
.<?php echo $uid; ?>-promo { display: block; position: relative; overflow: hidden; text-decoration: none; text-align: center; color: var(--dk-dark); background: linear-gradient(180deg, #ffffff 0%, #fff5f6 100%); border: 1px solid var(--dk-border); border-radius: var(--dk-radius-lg); padding: 12px 14px 14px; box-shadow: var(--dk-shadow-sm); transition: border-color 0.25s, box-shadow 0.25s; }
.<?php echo $uid; ?>-promo:hover { text-decoration: none; border-color: rgba(239, 57, 78, 0.45); box-shadow: 0 12px 28px rgba(31, 37, 48, 0.10); }
.<?php echo $uid; ?>-promo-badge { position: absolute; top: 12px; right: 12px; z-index: 2; background: var(--dk-primary); color: #fff; font-size: 11px; font-weight: 800; line-height: 1.8; padding: 1px 10px; border-radius: 20px; box-shadow: 0 2px 8px rgba(239, 57, 78, 0.3); }
.<?php echo $uid; ?>-promo-stage { display: flex; align-items: center; justify-content: center; position: relative; height: 118px; margin: 4px 0 2px; }
.<?php echo $uid; ?>-promo-stage::before { content: ''; position: absolute; top: 50%; left: 50%; width: 104px; height: 104px; margin: -52px 0 0 -52px; border-radius: 50%; border: 2px dashed rgba(239, 57, 78, 0.35); animation: dkPromoRing 18s linear infinite; }
.<?php echo $uid; ?>-promo-stage::after { content: ''; position: absolute; bottom: 0; left: 50%; width: 70px; height: 9px; margin-left: -35px; border-radius: 50%; background: radial-gradient(closest-side, rgba(35, 41, 51, 0.22), rgba(35, 41, 51, 0)); animation: dkPromoShadow 5s ease-in-out infinite; }
.<?php echo $uid; ?>-promo-stage img { position: relative; width: 110px; height: 110px; object-fit: contain; mix-blend-mode: multiply; transform-origin: 50% 85%; animation: dkPromoTurn 5s ease-in-out infinite; }
.<?php echo $uid; ?>-promo[hidden] { display: none; }
.<?php echo $uid; ?>-promo-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 14px; font-weight: 800; line-height: 1.7; }
.<?php echo $uid; ?>.<?php echo $uid; ?>-promo-float .<?php echo $uid; ?>-sidebar { position: relative; top: auto; align-self: stretch; height: auto; max-height: none; overflow: visible; }
.<?php echo $uid; ?>.<?php echo $uid; ?>-promo-float .<?php echo $uid; ?>-promo-wrap { position: sticky; top: var(--dk-sticky-top, 24px); }
.<?php echo $uid; ?>-promo-tag { display: block; margin-top: 2px; font-size: 12px; color: #6b7685; line-height: 1.7; }
.<?php echo $uid; ?>-promo-cta { display: inline-block; margin-top: 8px; background: #fff1f3; color: #c8233a; font-size: 13px; font-weight: 800; line-height: 1.6; padding: 6px 14px; border-radius: 10px; transition: background 0.2s, color 0.2s; }
.<?php echo $uid; ?>-promo:hover .<?php echo $uid; ?>-promo-cta { background: var(--dk-primary); color: #fff; }
@keyframes dkPromoTurn { 0%, 100% { transform: perspective(600px) rotateY(-28deg) translateY(0); } 50% { transform: perspective(600px) rotateY(28deg) translateY(-8px); } }
@keyframes dkPromoRing { to { transform: rotate(360deg); } }
@keyframes dkPromoShadow { 0%, 100% { transform: scaleX(1); opacity: 1; } 50% { transform: scaleX(0.78); opacity: 0.6; } }
@media (prefers-reduced-motion: reduce) { .<?php echo $uid; ?>-promo-stage img, .<?php echo $uid; ?>-promo-stage::before, .<?php echo $uid; ?>-promo-stage::after { animation: none; } }
.<?php echo $uid; ?>-content { flex: 1; min-width: 0; position: relative; }
.<?php echo $uid; ?>-toolbar { background: var(--dk-white); border-radius: var(--dk-radius-lg); padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--dk-shadow-sm); border: 1px solid var(--dk-border); position: relative; z-index: 1; gap: 12px; }
.<?php echo $uid; ?>-sort { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 500; flex-shrink: 0; }
.<?php echo $uid; ?>-sort select { border: 1px solid var(--dk-border); border-radius: var(--dk-radius); padding: 8px 12px; font-size: 14px; cursor: pointer; background: white; font-family: 'Vazirmatn', 'Tahoma', sans-serif; color: var(--dk-dark); min-width: 130px; max-width: 150px; outline: none; transition: border-color 0.2s; }
.<?php echo $uid; ?>-sort select:focus { border-color: var(--dk-primary); }
.<?php echo $uid; ?>-active-filters-wrap { font-size: 13px; color: var(--dk-text-light); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.<?php echo $uid; ?>-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; position: relative; }
.<?php echo $uid; ?>-load-more-wrap { text-align: center; margin-top: 40px; margin-bottom: 20px; }
.<?php echo $uid; ?>-load-more-btn { background: var(--dk-white); border: 2px solid var(--dk-border); color: var(--dk-dark); padding: 14px 48px; border-radius: var(--dk-radius-lg); font-family: 'Vazirmatn', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: var(--dk-shadow-sm); position: relative; overflow: hidden; }
.<?php echo $uid; ?>-load-more-btn:hover { background: var(--dk-primary); border-color: var(--dk-primary); color: white; box-shadow: 0 4px 16px rgba(239, 57, 78, 0.3); transform: translateY(-2px); }
.<?php echo $uid; ?>-load-more-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; background: var(--dk-bg); border-color: var(--dk-border); color: var(--dk-text-light); }
.<?php echo $uid; ?>-load-more-btn.loading { color: transparent; }
.<?php echo $uid; ?>-load-more-btn.loading::after { content: ''; position: absolute; width: 20px; height: 20px; top: 50%; left: 50%; margin-left: -10px; margin-top: -10px; border: 2px solid var(--dk-border); border-top-color: var(--dk-primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
.<?php echo $uid; ?>-product-link { text-decoration: none; color: inherit; display: block; transition: transform 0.3s; }
.<?php echo $uid; ?>-product-link:hover { text-decoration: none; }
.<?php echo $uid; ?>-product-link:active .<?php echo $uid; ?>-product { transform: scale(0.98); }
.<?php echo $uid; ?>-product { background: var(--dk-white); border-radius: 16px; overflow: hidden; border: 1px solid transparent; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; position: relative; box-shadow: var(--dk-shadow-sm); cursor: pointer; height: 100%; }
.<?php echo $uid; ?>-product:hover { transform: translateY(-6px); box-shadow: var(--dk-shadow-hover); border-color: var(--dk-border); }
.<?php echo $uid; ?>-product-img-wrap { position: relative; padding-top: 100%; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); overflow: hidden; }
.<?php echo $uid; ?>-product-img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; padding: 20px; transition: transform 0.4s; }
.<?php echo $uid; ?>-product:hover .<?php echo $uid; ?>-product-img { transform: scale(1.05); }
.<?php echo $uid; ?>-badge { position: absolute; top: 12px; left: 12px; background: var(--dk-primary); color: white; font-size: 12px; font-weight: 800; padding: 6px 10px; border-radius: 16px; z-index: 2; box-shadow: 0 2px 8px rgba(239, 57, 78, 0.3); min-width: 36px; text-align: center; }
.<?php echo $uid; ?>-product-body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 12px; }
.<?php echo $uid; ?>-product-title { font-size: 14px; line-height: 1.8; color: var(--dk-text); margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 50px; font-weight: 500; font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif; }
.<?php echo $uid; ?>-brand { align-self: flex-start; font-size: 11.5px; font-weight: 800; color: #6b7685; background: #f4f5f7; border-radius: 6px; padding: 2px 8px; line-height: 1.7; margin-bottom: -4px; font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif; }
.<?php echo $uid; ?>-product-footer { margin-top: auto; display: flex; justify-content: flex-start; align-items: flex-end; gap: 12px; padding-top: 8px; border-top: 1px solid #f5f5f5; }
.<?php echo $uid; ?>-price-wrap { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.<?php echo $uid; ?>-price-old { font-size: 13px; color: var(--dk-text-light); text-decoration: line-through; font-weight: 500; font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif; }
.<?php echo $uid; ?>-price-new { font-size: 18px; font-weight: 800; color: var(--dk-dark); direction: rtl; display: flex; align-items: center; gap: 6px; letter-spacing: -0.5px; font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif; }
.<?php echo $uid; ?>-price-new span { font-size: 12px; font-weight: 600; color: var(--dk-text-light); }
.<?php echo $uid; ?>-price-contact { font-size: 15px; color: var(--dk-text-light); font-weight: 700; display: flex; align-items: center; gap: 6px; font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif; }
.<?php echo $uid; ?>-price-contact::before { content: '📞'; font-size: 14px; }
.<?php echo $uid; ?>-loading, .<?php echo $uid; ?>-empty { text-align: center; padding: 80px 20px; background: white; border-radius: var(--dk-radius-lg); display: none; box-shadow: var(--dk-shadow-sm); border: 1px solid var(--dk-border); }
.<?php echo $uid; ?>-loading.active { display: block; }
.<?php echo $uid; ?>-spinner { width: 48px; height: 48px; border: 4px solid #f0f0f1; border-top-color: var(--dk-primary); border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; }
@keyframes spin { to { transform: rotate(360deg); } }
.<?php echo $uid; ?>-mobile-filter-btn { display: none; background: white; border: 1px solid var(--dk-border); border-radius: 10px; padding: 10px 18px; font-size: 14px; font-weight: 600; color: var(--dk-dark); cursor: pointer; align-items: center; gap: 8px; box-shadow: var(--dk-shadow-sm); white-space: nowrap; flex-shrink: 0; }
.<?php echo $uid; ?>-mobile-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 3; opacity: 0; transition: opacity 0.3s; }
.<?php echo $uid; ?>-mobile-overlay.active { display: block; opacity: 1; }
.<?php echo $uid; ?>-sidebar-close { display: none; position: absolute; top: 16px; left: 16px; width: 36px; height: 36px; background: #f5f5f5; border: none; border-radius: 10px; cursor: pointer; align-items: center; justify-content: center; font-size: 20px; color: var(--dk-dark); transition: all 0.2s; z-index: 5; }
.<?php echo $uid; ?>-sidebar-close:hover { background: var(--dk-border); }

@media (max-width: 968px) {
    .<?php echo $uid; ?> { padding-bottom: 60px; overflow-x: hidden; }
    .<?php echo $uid; ?>-header { padding: 16px; }
    .<?php echo $uid; ?>-title { font-size: 20px; }
    .<?php echo $uid; ?>-layout { flex-direction: column; padding: 12px; gap: 12px; }
    .<?php echo $uid; ?>-toolbar { position: sticky; top: 0; z-index: 1; padding: 12px; gap: 8px; flex-wrap: wrap; }
    .<?php echo $uid; ?>-toolbar > div:first-child { width: 100%; justify-content: space-between; }
    .<?php echo $uid; ?>-sort { gap: 8px; }
    .<?php echo $uid; ?>-sort label { font-size: 13px; white-space: nowrap; }
    .<?php echo $uid; ?>-sort select { min-width: 120px; max-width: none; font-size: 13px; padding: 8px; }
    .<?php echo $uid; ?>-active-filters-wrap { width: 100%; text-align: center; font-size: 12px; max-width: none; padding-top: 8px; border-top: 1px solid var(--dk-border); }
    .<?php echo $uid; ?>-sidebar { position: fixed; top: 0; right: 0; width: 85%; max-width: 320px; height: 100%; height: 100dvh; background: var(--dk-white); z-index: 4; transform: translateX(110%); overflow-y: auto; overflow-x: hidden; padding: 60px 20px 20px; box-shadow: var(--dk-shadow-drawer); border-radius: 0; visibility: hidden; transition: transform 0.3s ease, visibility 0.3s; will-change: transform; -webkit-overflow-scrolling: touch; }
    .<?php echo $uid; ?>-sidebar.active { transform: translateX(0); visibility: visible; }
    .<?php echo $uid; ?>-sidebar-close { display: flex; }
    .<?php echo $uid; ?>-box { margin-bottom: 16px; box-shadow: none; border: 1px solid var(--dk-border); }
    .<?php echo $uid; ?>-mobile-filter-btn { display: inline-flex; }
    .<?php echo $uid; ?>-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .<?php echo $uid; ?>-product { border-radius: 12px; }
    .<?php echo $uid; ?>-product-img-wrap { padding-top: 90%; }
    .<?php echo $uid; ?>-product-img { padding: 8px; }
    .<?php echo $uid; ?>-badge { top: 6px; left: 6px; font-size: 10px; padding: 3px 6px; min-width: 28px; }
    .<?php echo $uid; ?>-product-body { padding: 10px; gap: 6px; }
    .<?php echo $uid; ?>-product-title { font-size: 12px; line-height: 1.6; min-height: 38px; }
    .<?php echo $uid; ?>-brand { font-size: 10.5px; padding: 1px 6px; margin-bottom: -2px; }
    .<?php echo $uid; ?>-cat-name { white-space: normal; line-height: 1.5; }
    .<?php echo $uid; ?>-promo { display: none; }
    .<?php echo $uid; ?>-price-new { font-size: 14px; gap: 4px; }
    .<?php echo $uid; ?>-price-new span { font-size: 10px; }
    .<?php echo $uid; ?>-price-old { font-size: 11px; }
    .<?php echo $uid; ?>-price-contact { font-size: 12px; }
    .<?php echo $uid; ?>-load-more-btn { padding: 12px 32px; font-size: 14px; width: 90%; max-width: 300px; }
}
@media (max-width: 480px) {
    .<?php echo $uid; ?>-sort label { display: none; }
    .<?php echo $uid; ?>-mobile-filter-btn { padding: 8px 12px; font-size: 13px; }
    .<?php echo $uid; ?>-mobile-filter-btn svg { width: 16px; height: 16px; }
    .<?php echo $uid; ?>-grid { gap: 6px; }
    .<?php echo $uid; ?>-product-body { padding: 8px; }
}
</style>

<div class="<?php echo $uid; ?>" id="<?php echo $uid; ?>"
     data-ajax-url="<?php echo esc_url($ajax_url); ?>"
     data-nonce="<?php echo esc_attr($nonce); ?>"
     data-root-cat="<?php echo intval($root_id); ?>"
     data-per-page="<?php echo intval($per_page); ?>"
     data-instance="<?php echo intval($instance); ?>">

    <div class="<?php echo $uid; ?>-header">
        <div class="<?php echo $uid; ?>-title-wrap">
            <h1 class="<?php echo $uid; ?>-title"><?php echo esc_html($title); ?></h1>
            <span class="<?php echo $uid; ?>-count" id="<?php echo $uid; ?>-count"><?php echo number_format_i18n($initial_total); ?> کالا</span>
        </div>
    </div>

    <div class="<?php echo $uid; ?>-layout">

        <?php if ($show_filters && $has_sidebar): ?>
        <div class="<?php echo $uid; ?>-mobile-overlay" onclick="closeMobileSidebar_<?php echo $instance; ?>()"></div>

        <aside class="<?php echo $uid; ?>-sidebar" id="<?php echo $uid; ?>-sidebar">
            <button class="<?php echo $uid; ?>-sidebar-close" onclick="closeMobileSidebar_<?php echo $instance; ?>()">×</button>

            <?php if (!empty($category_tree)): ?>
            <div class="<?php echo $uid; ?>-box">
                <div class="<?php echo $uid; ?>-box-title">
                    <span>دسته‌بندی‌ها</span>
                    <span class="<?php echo $uid; ?>-clear" onclick="clearAllCats_<?php echo $instance; ?>()">حذف همه</span>
                </div>
                <?php dk_render_category_accordion($category_tree, $uid, 0, true); ?>
            </div>
            <?php endif; ?>

            <?php if ($show_price_filter): ?>
            <div class="<?php echo $uid; ?>-box">
                <div class="<?php echo $uid; ?>-box-title">محدوده قیمت</div>
                <div style="display:flex;gap:10px;margin-bottom:16px;">
                    <input type="number" id="<?php echo $uid; ?>-min" placeholder="از ريال" style="width:50%;height:44px;border:1px solid var(--dk-border);border-radius:10px;padding:0 14px;font-size:14px;font-family:inherit;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='#ef394e'" onblur="this.style.borderColor='#e0e0e2'">
                    <input type="number" id="<?php echo $uid; ?>-max" placeholder="تا ريال" style="width:50%;height:44px;border:1px solid var(--dk-border);border-radius:10px;padding:0 14px;font-size:14px;font-family:inherit;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='#ef394e'" onblur="this.style.borderColor='#e0e0e2'">
                </div>
                <button onclick="applyPrice_<?php echo $instance; ?>()" style="width:100%;height:46px;background:var(--dk-primary);color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:15px;font-family:inherit;transition:all 0.2s;box-shadow:0 4px 12px rgba(239,57,78,0.25);" onmouseover="this.style.background='#d32f2f'" onmouseout="this.style.background='#ef394e'">
                    اعمال فیلتر
                </button>
            </div>
            <?php endif; ?>
            <?php if (!empty($promo)): ?>
            <div class="<?php echo $uid; ?>-promo-wrap">
                <?php foreach ($promo as $promo_i => $promo_item): ?>
                <a href="<?php echo esc_url($promo_item['url']); ?>" class="<?php echo $uid; ?>-promo"<?php echo $promo_i > 0 ? ' hidden' : ''; ?>>
                    <span class="<?php echo $uid; ?>-promo-badge">پیشنهاد ویژه</span>
                    <span class="<?php echo $uid; ?>-promo-stage"><img src="<?php echo esc_url($promo_item['img']); ?>" alt="<?php echo esc_attr($promo_item['name']); ?>" width="300" height="300" loading="lazy" decoding="async"></span>
                    <span class="<?php echo $uid; ?>-promo-name"><?php echo esc_html($promo_item['name']); ?></span>
                    <?php if ($promo_item['brand'] !== ''): ?><span class="<?php echo $uid; ?>-promo-tag">برند <?php echo esc_html($promo_item['brand']); ?></span><?php endif; ?>
                    <span class="<?php echo $uid; ?>-promo-cta">مشاهده و استعلام قیمت</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </aside>
        <?php endif; ?>

        <main class="<?php echo $uid; ?>-content">
            <div class="<?php echo $uid; ?>-toolbar">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <?php if ($show_filters && $has_sidebar): ?>
                    <button class="<?php echo $uid; ?>-mobile-filter-btn" onclick="openMobileSidebar_<?php echo $instance; ?>()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                        فیلترها
                    </button>
                    <?php endif; ?>

                    <div class="<?php echo $uid; ?>-sort">
                        <label>مرتب‌سازی:</label>
                        <select id="<?php echo $uid; ?>-sort" onchange="changeSort_<?php echo $instance; ?>(this.value)">
                            <?php if ($is_tax_page): ?><option value="menu_order">پیشنهادی</option><?php endif; ?>
                            <option value="date">جدیدترین</option>
                            <option value="price">ارزان‌ترین</option>
                            <option value="price-desc">گران‌ترین</option>
                            <option value="popularity">پرفروش‌ترین</option>
                        </select>
                    </div>
                </div>
                <div class="<?php echo $uid; ?>-active-filters-wrap">
                    <span id="<?php echo $uid; ?>-active-filters"></span>
                </div>
            </div>

            <div class="<?php echo $uid; ?>-loading" id="<?php echo $uid; ?>-loading">
                <div class="<?php echo $uid; ?>-spinner"></div>
                <div style="color:var(--dk-text-light);font-weight:500;">در حال بارگذاری...</div>
            </div>

            <div class="<?php echo $uid; ?>-grid" id="<?php echo $uid; ?>-grid">
                <?php echo $initial_products_html; ?>
            </div>

            <div class="<?php echo $uid; ?>-empty" id="<?php echo $uid; ?>-empty" style="<?php echo $initial_total > 0 ? 'display:none;' : 'display:block;'; ?>">
                <div style="font-size:56px;margin-bottom:20px;opacity:0.7;">📦</div>
                <h3 style="margin:0 0 12px;color:var(--dk-dark);font-size:18px;font-weight:700;">محصولی یافت نشد</h3>
                <p style="margin:0;color:var(--dk-text-light);font-size:15px;">فیلترهای انتخابی را تغییر دهید یا دسته‌بندی دیگری را امتحان کنید</p>
            </div>

            <div class="<?php echo $uid; ?>-load-more-wrap" id="<?php echo $uid; ?>-load-more-wrap" style="<?php echo $initial_has_more ? 'display:block;' : 'display:none;'; ?>">
                <button class="<?php echo $uid; ?>-load-more-btn" id="<?php echo $uid; ?>-load-more-btn" onclick="loadMore_<?php echo $instance; ?>()">
                    نمایش بیشتر
                </button>
            </div>
        </main>
    </div>
</div>

<script>
(function() {
    const container = document.getElementById('<?php echo $uid; ?>');
    const uid = '<?php echo $uid; ?>';
    const instance = <?php echo $instance; ?>;

    const ajaxUrl = container.dataset.ajaxUrl;
    const nonce = container.dataset.nonce;
    const rootCat = parseInt(container.dataset.rootCat, 10) || 0;
    const perPage = parseInt(container.dataset.perPage, 10) || 12;

    let state = {
        expanded: new Set(rootCat ? [rootCat] : []),
        selected: new Set(),
        min_price: '',
        max_price: '',
        sort: '<?php echo esc_js($default_sort); ?>',
        page: 1,
        totalPages: <?php echo intval($initial_total_pages); ?>,
        loading: false,
        hasMore: <?php echo $initial_has_more ? 'true' : 'false'; ?>
    };

    state.expanded.forEach(id => {
        const node = document.querySelector(`#${uid} [data-cat-id="${id}"]`);
        if (node) node.classList.add('expanded');
    });

    /* bsma 2026-09-24: full-name tooltip for cut-off categories + sticky sidebar below the site header */
    container.addEventListener('mouseover', function(e) {
        const row = e.target.closest(`.${uid}-cat-row`);
        if (!row) return;
        const name = row.querySelector(`.${uid}-cat-name`);
        if (name) row.classList.toggle(`${uid}-trunc`, name.scrollWidth > name.clientWidth + 1);
    });
    
    const stickySidebar = document.getElementById(`${uid}-sidebar`);
    function syncStickySidebar() {
        if (!stickySidebar) return;
        container.classList.remove(`${uid}-promo-float`);
        if (window.innerWidth <= 968) {
            container.style.removeProperty('--dk-sticky-top');
            return;
        }
        const header = document.querySelector('.elementor-location-header') || document.querySelector('header');
        let top = 16;
        if (header) {
            const hs = getComputedStyle(header);
            if (hs.position === 'sticky' || hs.position === 'fixed') top += (parseFloat(hs.top) || 0) + header.offsetHeight;
        }
        container.style.setProperty('--dk-sticky-top', top + 'px');
        if (stickySidebar.querySelector(`.${uid}-promo-wrap`) && stickySidebar.scrollHeight > stickySidebar.clientHeight + 1) {
            container.classList.add(`${uid}-promo-float`);
        }
    }
    const promoCards = container.querySelectorAll(`.${uid}-promo`);
    if (promoCards.length > 1) {
        const promoKey = 'dkPromo_' + rootCat;
        let lastPick = -1;
        try { lastPick = parseInt(localStorage.getItem(promoKey), 10); } catch (e) {}
        let pick = Math.floor(Math.random() * promoCards.length);
        if (pick === lastPick) pick = (pick + 1) % promoCards.length;
        promoCards.forEach((card, i) => { card.hidden = i !== pick; });
        try { localStorage.setItem(promoKey, String(pick)); } catch (e) {}
    }
    syncStickySidebar();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(syncStickySidebar);
    window.addEventListener('resize', syncStickySidebar);
    window.addEventListener('load', syncStickySidebar);
    
    window['toggleCat_' + uid] = function(btn, catId) {
        const node = btn.closest(`.${uid}-cat-node`);
        const isExpanded = node.classList.contains('expanded');
        if (isExpanded) {
            node.classList.remove('expanded');
            state.expanded.delete(catId);
        } else {
            node.classList.add('expanded');
            state.expanded.add(catId);
            if (state.selected.size === 0) loadProducts(false, true);
        }
    };

    window['selectCat_' + uid] = function(catId, catSlug) {
        const node = document.querySelector(`#${uid} [data-cat-id="${catId}"]`);
        if (!node) return;

        const row = node.querySelector(`.${uid}-cat-row`);
        const checkbox = row.querySelector(`.${uid}-checkbox`);
        const isChecked = row.classList.contains('checked');

        if (isChecked) {
            row.classList.remove('checked');
            checkbox.classList.remove('checked');
            state.selected.delete(catId);
        } else {
            state.selected.clear();
            document.querySelectorAll(`#${uid} .${uid}-cat-row`).forEach(r => {
                r.classList.remove('checked');
                r.querySelector(`.${uid}-checkbox`)?.classList.remove('checked');
            });
            row.classList.add('checked');
            checkbox.classList.add('checked');
            state.selected.add(catId);
        }

        updateClearButton();
        loadProducts(false, false);

        if (window.innerWidth <= 968) {
            setTimeout(() => closeMobileSidebar_<?php echo $instance; ?>(), 250);
        }
    };

    window['clearAllCats_' + instance] = function() {
        state.selected.clear();
        document.querySelectorAll(`#${uid} .${uid}-cat-row`).forEach(r => {
            r.classList.remove('checked');
            r.querySelector(`.${uid}-checkbox`)?.classList.remove('checked');
        });
        updateClearButton();
        loadProducts(false, true);
    };

    window['applyPrice_' + instance] = function() {
        state.min_price = document.getElementById(`${uid}-min`).value;
        state.max_price = document.getElementById(`${uid}-max`).value;
        loadProducts(false, state.selected.size === 0);
        if (window.innerWidth <= 968) closeMobileSidebar_<?php echo $instance; ?>();
    };

    window['changeSort_' + instance] = function(val) {
        state.sort = val;
        loadProducts(false, state.selected.size === 0);
    };

    window['loadMore_' + instance] = function() {
        if (state.loading || !state.hasMore) return;
        state.page++;
        loadProducts(true, state.selected.size === 0);
    };

    window['openMobileSidebar_' + instance] = function() {
        const sidebar = document.getElementById(`${uid}-sidebar`);
        const overlay = document.querySelector(`.${uid}-mobile-overlay`);
        if (!sidebar || !overlay) return;
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.style.touchAction = 'none';
        document.documentElement.style.overflow = 'hidden';
    };

    window['closeMobileSidebar_' + instance] = function() {
        const sidebar = document.getElementById(`${uid}-sidebar`);
        const overlay = document.querySelector(`.${uid}-mobile-overlay`);
        if (!sidebar || !overlay) return;
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        document.body.style.touchAction = '';
        document.documentElement.style.overflow = '';
    };

    function updateClearButton() {
        const btn = document.querySelector(`#${uid} .${uid}-clear`);
        if (btn) btn.classList.toggle('active', state.selected.size > 0);
        const filterText = state.selected.size > 0 ? `${state.selected.size} دسته انتخاب شده` : '';
        document.getElementById(`${uid}-active-filters`).textContent = filterText;
    }

    function getActiveCategories(forExpanded) {
        if (!forExpanded && state.selected.size > 0) return Array.from(state.selected);
        const cats = new Set();
        state.expanded.forEach(expId => {
            if (expId > 0) {
                cats.add(expId);
                const node = document.querySelector(`#${uid} [data-cat-id="${expId}"]`);
                if (node) {
                    node.querySelectorAll(`.${uid}-cat-node`).forEach(cn => {
                        const cid = parseInt(cn.dataset.catId, 10);
                        if (cid > 0) cats.add(cid);
                    });
                }
            }
        });
        return Array.from(cats);
    }

    function loadProducts(append = false, useExpanded = false) {
        if (state.loading) return;
        state.loading = true;

        const prevPage = state.page;
        const prevHasMore = state.hasMore;

        if (!append) {
            state.page = 1;
            state.hasMore = true;
        }

        const loadingEl = document.getElementById(`${uid}-loading`);
        const gridEl = document.getElementById(`${uid}-grid`);
        const loadMoreBtn = document.getElementById(`${uid}-load-more-btn`);
        const loadMoreWrap = document.getElementById(`${uid}-load-more-wrap`);
        const emptyEl = document.getElementById(`${uid}-empty`);

        if (!append) {
            loadingEl.classList.add('active');
            gridEl.style.display = 'none';
            emptyEl.style.display = 'none';
            loadMoreWrap.style.display = 'none';
        } else {
            if (loadMoreBtn) {
                loadMoreBtn.classList.add('loading');
                loadMoreBtn.disabled = true;
            }
        }

        const cats = getActiveCategories(useExpanded);

        const url = ajaxUrl + (ajaxUrl.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now();

        const formData = new FormData();
        formData.append('action', 'dk_load_accordion_products');
        formData.append('nonce', nonce);
        formData.append('uid', uid);  // ← FIX: send uid so CSS classes match
        formData.append('root_cat', rootCat);
        formData.append('cats', JSON.stringify(cats));
        formData.append('mode', state.selected.size > 0 ? 'selected' : 'expanded');
        formData.append('min_price', state.min_price);
        formData.append('max_price', state.max_price);
        formData.append('sort', state.sort);
        formData.append('page', state.page);
        formData.append('per_page', perPage);

        // On failure (e.g. an expired nonce on a cached page) put the previous products back
        // instead of leaving the grid hidden.
        function restoreAfterFailure() {
            state.page = append ? prevPage - 1 : prevPage;
            state.hasMore = prevHasMore;
            if (append) return;
            if (gridEl.children.length > 0) {
                gridEl.style.display = 'grid';
                loadMoreWrap.style.display = state.hasMore ? 'block' : 'none';
            } else {
                emptyEl.style.display = 'block';
            }
        }

        fetch(url, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            state.loading = false;
            loadingEl.classList.remove('active');

            if (loadMoreBtn) {
                loadMoreBtn.classList.remove('loading');
                loadMoreBtn.disabled = false;
            }

            if (!res || !res.success) {
                restoreAfterFailure();
                return;
            }

            if (!append) gridEl.innerHTML = '';

            if (res.data.products && res.data.products.length > 0) {
                res.data.products.forEach(p => {
                    gridEl.insertAdjacentHTML('beforeend', p.html);
                });

                gridEl.style.display = 'grid';
                emptyEl.style.display = 'none';

                const totalPages = parseInt(res.data.total_pages) || 1;
                const currentPage = parseInt(res.data.current_page) || 1;
                state.totalPages = totalPages;
                state.hasMore = currentPage < totalPages;

                loadMoreWrap.style.display = state.hasMore ? 'block' : 'none';
            } else {
                if (!append) {
                    gridEl.innerHTML = '';
                    gridEl.style.display = 'none';
                    emptyEl.style.display = 'block';
                    loadMoreWrap.style.display = 'none';
                } else {
                    state.hasMore = false;
                    loadMoreWrap.style.display = 'none';
                }
            }

            document.getElementById(`${uid}-count`).textContent = (res.data.total || 0).toLocaleString('fa-IR') + ' کالا';
        })
        .catch(err => {
            state.loading = false;
            loadingEl.classList.remove('active');
            if (loadMoreBtn) {
                loadMoreBtn.classList.remove('loading');
                loadMoreBtn.disabled = false;
            }
            restoreAfterFailure();
            console.error('Error:', err);
        });
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeMobileSidebar_<?php echo $instance; ?>();
    });
})();
</script>

<?php
    return ob_get_clean();
}

/* ─── AJAX Handler ─── */

add_action('wp_ajax_dk_load_accordion_products', 'dk_load_accordion_products_callback');
add_action('wp_ajax_nopriv_dk_load_accordion_products', 'dk_load_accordion_products_callback');
function dk_load_accordion_products_callback() {
    // No nonce check: this read-only endpoint returns public product listings only, and a nonce
    // baked into a cached page expires after 12-24h, which broke filters and "load more".

    $root_cat = intval($_POST['root_cat'] ?? 0);
    $cats = json_decode(stripslashes($_POST['cats'] ?? ''), true);
    $mode = sanitize_text_field($_POST['mode'] ?? '');
    $page = intval($_POST['page'] ?? 0) ?: 1;
    $per_page = min(intval($_POST['per_page'] ?? 0) ?: 12, 100);
    if ($per_page < 1) { $per_page = 12; }
    $sort = sanitize_text_field($_POST['sort'] ?? '') ?: 'date';
    $uid = sanitize_text_field($_POST['uid'] ?? 'dkacc');  // ← FIX: read uid from request
    // bsma: $uid is echoed unescaped into HTML class attributes in dk_render_product_card(),
    // so it must not contain quotes or other attribute-breaking characters.
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $uid);
    if ($uid === '') { $uid = 'dkacc'; }

    if (!is_array($cats)) $cats = array();
    $cats = array_filter(array_map('intval', $cats), function($id) { return $id > 0; });

    $min = isset($_POST['min_price']) && $_POST['min_price'] !== '' ? floatval($_POST['min_price']) : null;
    $max = isset($_POST['max_price']) && $_POST['max_price'] !== '' ? floatval($_POST['max_price']) : null;

    $args = dk_build_query_args($root_cat, $cats, $mode, $min, $max, $sort, $page, $per_page);
    $query = new WP_Query($args);
    $products = array();

    while ($query->have_posts()) {
        $query->the_post();
        global $product;
        if (!$product) continue;

        $products[] = array(
            'id' => $product->get_id(),
            'html' => dk_render_product_card($product, $uid),
        );
    }

    wp_reset_postdata();

    wp_send_json_success(array(
        'products' => $products,
        'total' => $query->found_posts,
        'total_pages' => $query->max_num_pages,
        'current_page' => $page,
        'mode' => $mode
    ));
}