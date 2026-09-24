<?php
/**
 * شورت‌کد نمایش پست فعلی + کپچای Canvas (سازگار با کش)
 */

if (!defined('ABSPATH')) exit;

add_shortcode('current_post_article', function($atts) {
    if (!is_singular('post')) {
        return '<p>پست یافت نشد.</p>';
    }

    global $post;
    $post_id = $post->ID;

    $title  = get_the_title($post_id);
    $date   = get_the_date('', $post_id);
    $author = get_the_author_meta('display_name', $post->post_author);

    $cats = get_the_category($post_id);
    $cat_list = [];
    foreach ($cats as $c) {
        $cat_list[] = '<a href="' . esc_url(get_category_link($c->term_id)) . '" style="color:#fff; text-decoration:underline;">' . esc_html($c->name) . '</a>';
    }
    $cats_html = implode(', ', $cat_list);

    $thumbnail_id = get_post_thumbnail_id($post_id);
    $image_src = wp_get_attachment_image_src($thumbnail_id, 'full');

    $image_html = '';
    if ($image_src) {
        $image_url = $image_src[0];
        $image_html = '
            <div class="cpa-thumb">
                <img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" class="cpa-thumb-img" />
            </div>';
    }

    $excerpt = get_the_excerpt($post_id);
    if (empty($excerpt)) {
        $content_for_excerpt = get_post_field('post_content', $post_id);
        $excerpt = wp_trim_words($content_for_excerpt, 50, '...');
    }

    $excerpt_html = '<div class="cpa-excerpt">' . wp_kses_post($excerpt) . '</div>';
    $content = apply_filters('the_content', get_post_field('post_content', $post_id));

    // فعال‌سازی پرچم برای نمایش کپچا
    $GLOBALS['fc_captcha_active'] = true;

    ob_start(); ?>
    <div class="current-post-article cpa">
        <h2 class="cpa-title"><?php echo esc_html($title); ?></h2>

        <div class="cpa-meta">
            <?php echo 'نویسنده: ' . esc_html($author) . ' • ' . esc_html($date); ?>
            <?php if ($cats_html) echo ' • دسته‌ها: ' . $cats_html; ?>
        </div>

        <?php if ($image_html || $excerpt_html): ?>
        <div class="cpa-2col">
            <?php echo $image_html; ?>
            <?php echo $excerpt_html; ?>
        </div>
        <?php endif; ?>

        <?php
        $tags = get_the_tags($post_id);
        if ($tags) {
            echo '<div class="cpa-tags">';
            foreach ($tags as $tag) {
                echo '<a class="cpa-tag" href="' . esc_url(get_tag_link($tag->term_id)) . '">' . esc_html($tag->name) . '</a>';
            }
            echo '</div>';
        }
        ?>

        <div class="cpa-content"><?php echo $content; ?></div>

        <div class="cpa-comments-section">
            <?php comments_template(); ?>
        </div>
    </div>

    <style>
        .cpa{
            max-width:96%;
            margin:2% auto;
            padding:32px;
            border-radius:14px;
            background:#0b2a4a;
            color:#fff;
            font-family:Vazirmatn,system-ui;
            line-height:1.8;
            font-size:17px;
            box-sizing:border-box;
        }
        .cpa-title{
            font-size:30px;
            font-weight:700;
            margin:0 0 16px 0;
            border-bottom:1px solid rgba(255,255,255,0.08);
            padding-bottom:12px;
        }
        .cpa-meta{
            color:rgba(255,255,255,0.75);
            margin-bottom:24px;
            font-size:13px;
        }
        .cpa-2col{
            display:flex;
            flex-direction:column;
            gap:16px;
            align-items:center;
            margin-bottom:32px;
        }
        .cpa-thumb{ width:100%; max-width:360px; }
        .cpa-thumb-img{
            width:100%;
            aspect-ratio:1/1;
            object-fit:cover;
            border-radius:8px;
            display:block;
        }
        .cpa-excerpt{
            width:96%;
            text-align:center;
        }
        .cpa-tags{
            margin: 10px 0 25px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .cpa-tag{
            padding: 6px 12px;
            background: rgba(255,255,255,0.15);
            border-radius: 6px;
            font-size: 13px;
            color:#fff;
            text-decoration:none;
        }
        .cpa-tag:hover{
            background: rgba(255,255,255,0.3);
        }
        @media (min-width: 992px) {
            .cpa-2col{
                flex-direction:row;
                align-items:center;
                gap:25px;
                flex-wrap:nowrap;
            }
            .cpa-thumb{
                flex:0 0 40%;
                max-width:250px;
            }
            .cpa-excerpt{
                flex:1;
                min-width:0;
                text-align:right;
            }
        }
        .cpa img {
            border-radius: 10px !important;
        }

        /* ============================================================
           استایل‌های بخش نظرات و فرم دیدگاه — کاملاً رسپانسیو
           ============================================================ */
        .cpa-comments-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .cpa-comments-section .comments-title,
        .cpa-comments-section #reply-title {
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .cpa-comments-section .comment-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .cpa-comments-section .comment {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .cpa-comments-section .comment-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .cpa-comments-section .comment-author img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        .cpa-comments-section .comment-author .fn {
            color: #00D4FF;
            font-weight: 700;
            font-size: 14px;
        }
        .cpa-comments-section .comment-metadata {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        .cpa-comments-section .comment-content {
            color: rgba(255,255,255,0.85);
            font-size: 15px;
            line-height: 1.7;
            word-wrap: break-word;
        }
        .cpa-comments-section .comment-content p {
            margin: 0 0 10px 0;
        }
        .cpa-comments-section .reply a {
            color: #00D4FF;
            font-size: 13px;
            text-decoration: none;
        }

        /* ─── فرم دیدگاه ─── */
        .cpa-comments-section #commentform {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .cpa-comments-section #commentform p {
            margin: 0;
        }
        .cpa-comments-section #commentform label {
            display: block;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .cpa-comments-section #commentform input[type="text"],
        .cpa-comments-section #commentform input[type="email"],
        .cpa-comments-section #commentform input[type="url"],
        .cpa-comments-section #commentform textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-family: Vazirmatn, system-ui, Tahoma;
            font-size: 15px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .cpa-comments-section #commentform input:focus,
        .cpa-comments-section #commentform textarea:focus {
            border-color: #00D4FF;
            box-shadow: 0 0 0 3px rgba(0,212,255,0.15);
        }
        .cpa-comments-section #commentform textarea {
            min-height: 120px;
            resize: vertical;
        }
        .cpa-comments-section #commentform .comment-form-author,
        .cpa-comments-section #commentform .comment-form-email,
        .cpa-comments-section #commentform .comment-form-url {
            width: 100%;
        }
        .cpa-comments-section #commentform .form-submit input[type="submit"] {
            width: 100%;
            padding: 14px 24px;
            background: #00D4FF;
            color: #0b2a4a;
            border: none;
            border-radius: 8px;
            font-family: Vazirmatn, system-ui, Tahoma;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .cpa-comments-section #commentform .form-submit input[type="submit"]:hover {
            background: #33DDFF;
        }
        .cpa-comments-section #commentform .form-submit input[type="submit"]:active {
            transform: scale(0.98);
        }
        .cpa-comments-section #commentform .comment-notes,
        .cpa-comments-section #commentform .required-field-message {
            color: rgba(255,255,255,0.5);
            font-size: 13px;
        }
        .cpa-comments-section .comment-reply-title small a {
            color: #f44336;
            font-size: 14px;
            text-decoration: none;
            margin-right: 10px;
        }

        /* ─── چیدمان سه‌ستونه فیلدها در دسکتاپ ─── */
        @media (min-width: 768px) {
            .cpa-comments-section #commentform .comment-form-author,
            .cpa-comments-section #commentform .comment-form-email,
            .cpa-comments-section #commentform .comment-form-url {
                width: calc(33.333% - 9px);
                display: inline-block;
                vertical-align: top;
            }
            .cpa-comments-section #commentform .comment-form-author { margin-left: 13px; }
            .cpa-comments-section #commentform .comment-form-email { margin-left: 13px; }
        }

        /* ─── موبایل: فیلدها تمام‌عرض و فاصله کمتر ─── */
        @media (max-width: 767px) {
            .cpa {
                padding: 16px;
                border-radius: 10px;
                font-size: 15px;
            }
            .cpa-title {
                font-size: 22px;
            }
            .cpa-comments-section {
                margin-top: 24px;
                padding-top: 20px;
            }
            .cpa-comments-section .comments-title,
            .cpa-comments-section #reply-title {
                font-size: 18px;
            }
            .cpa-comments-section .comment {
                padding: 12px;
                margin-bottom: 12px;
            }
            .cpa-comments-section .comment-meta {
                gap: 8px;
            }
            .cpa-comments-section .comment-author img {
                width: 32px;
                height: 32px;
            }
            .cpa-comments-section .comment-content {
                font-size: 14px;
            }
            .cpa-comments-section #commentform input[type="text"],
            .cpa-comments-section #commentform input[type="email"],
            .cpa-comments-section #commentform input[type="url"],
            .cpa-comments-section #commentform textarea {
                padding: 10px 12px;
                font-size: 14px;
            }
            .cpa-comments-section #commentform .form-submit input[type="submit"] {
                padding: 12px 20px;
                font-size: 15px;
            }
        }

        /* ─── موبایل خیلی کوچک ─── */
        @media (max-width: 480px) {
            .cpa {
                padding: 12px;
                max-width: 100%;
                margin: 0;
                border-radius: 0;
            }
            .cpa-title {
                font-size: 20px;
            }
            .cpa-comments-section .comment {
                padding: 10px;
            }
            .cpa-comments-section .comment-author .fn {
                font-size: 13px;
            }
            .cpa-comments-section .comment-metadata {
                font-size: 11px;
            }
        }

        /* ================== کپچا رسپانسیو و وسط‌چین ================== */
        .fc-captcha-wrap {
            margin: 20px auto !important;
            width: 100%;
            max-width: 360px;
            box-sizing: border-box;
        }
        .fc-input-wrap {
            position: relative;
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
        }
        .fc-input-wrap input {
            width: 100%;
            padding: 12px 38px 12px 12px !important;
        }
        .fc-refresh-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }
        @media (max-width: 480px) {
            .fc-captcha-wrap {
                padding: 15px 10px !important;
                max-width: 100%;
            }
            #fc-captcha-canvas {
                width: 160px !important;
                height: 48px !important;
            }
            .fc-input-wrap {
                max-width: 100%;
            }
        }
    </style>
    <?php
    return ob_get_clean();
});

// ==========================================
// کپچای Canvas - نسخه نهایی (سازگار با کش)
// ==========================================

add_action('comment_form_after_fields', 'fc_render_captcha');
add_action('comment_form_logged_in_after', 'fc_render_captcha');

function fc_render_captcha() {
    if (empty($GLOBALS['fc_captcha_active'])) return;
    ?>
    <div class="fc-captcha-wrap" style="margin: 20px auto; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px; border: 1px solid rgba(255,255,255,0.12); max-width: 360px; text-align: center;">
        <label style="display: block; margin-bottom: 12px; color: rgba(255,255,255,0.85); font-size: 14px; font-family: Vazirmatn, system-ui, Tahoma; font-weight: 700;">
            کد امنیتی تصویر را وارد کنید:
        </label>

        <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
            <canvas id="fc-captcha-canvas" width="200" height="60" 
                    data-code=""
                    style="border-radius: 8px; cursor: pointer; background: #051e30; border: 2px solid rgba(0,212,255,0.25); box-shadow: 0 0 20px rgba(0,212,255,0.08); transition: transform 0.2s;" 
                    onmouseover="this.style.transform='scale(1.02)'" 
                    onmouseout="this.style.transform='scale(1)'"
                    onclick="fcRefreshCaptcha()"></canvas>
        </div>

        <div class="fc-input-wrap">
            <input type="text" name="fc_captcha" id="fc-captcha-input" required 
                   style="padding: 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); 
                          background: #0b2a4a; color: #fff; text-align: center; font-size: 16px; 
                          font-family: Vazirmatn, system-ui, Tahoma; letter-spacing: 5px; text-transform: uppercase; 
                          box-sizing: border-box; font-weight: 700;" 
                   placeholder="کد را اینجا بنویسید" maxlength="4" autocomplete="off">

            <span class="fc-refresh-icon" onclick="fcRefreshCaptcha()" title="تصویر جدید">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
            </span>
        </div>

        <input type="hidden" name="fc_captcha_id" id="fc-captcha-id" value="">
    </div>
    <?php
}

// ─── تولید کد از طریق AJAX (سازگار با کش) ───
add_action('wp_ajax_fc_generate_captcha', 'fc_ajax_generate_captcha');
add_action('wp_ajax_nopriv_fc_generate_captcha', 'fc_ajax_generate_captcha');

function fc_ajax_generate_captcha() {
    $code = fc_generate_code();
    $id   = wp_rand(100000, 999999);
    set_transient('fc_captcha_' . $id, $code, 5 * MINUTE_IN_SECONDS);

    wp_send_json_success([
        'code' => $code,
        'id'   => $id
    ]);
}

function fc_generate_code() {
    $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 4; $i++) {
        $code .= $chars[wp_rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// ─── اعتبارسنجی (یک بار، قبل از ذخیره) ───
// The earlier "backup" checks on wp_insert_comment / comment_post ran after this one had already
// deleted the transient, so they deleted every valid comment. One check before saving is enough.
add_filter('preprocess_comment', 'fc_verify_captcha', 1);
function fc_verify_captcha($commentdata) {
    if (current_user_can('moderate_comments')) return $commentdata;

    // The captcha is shown on blog posts; comments there must carry it, so a bot cannot skip
    // the check by leaving the field out. Product reviews and pingbacks are not affected.
    $post_id = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;
    $type    = $commentdata['comment_type'] ?? '';
    if (get_post_type($post_id) !== 'post' || in_array($type, array('pingback', 'trackback'), true)) {
        return $commentdata;
    }

    $code = strtoupper(trim(sanitize_text_field(wp_unslash($_POST['fc_captcha'] ?? ''))));
    $id   = absint($_POST['fc_captcha_id'] ?? 0);
    $expected = $id ? get_transient('fc_captcha_' . $id) : false;

    if ($code === '' || $expected === false || $code !== $expected) {
        wp_die(
            fc_captcha_error_html('کد امنیتی نادرست، خالی یا منقضی شده است.'),
            'خطای امنیتی',
            ['response' => 403, 'back_link' => false]
        );
    }

    delete_transient('fc_captcha_' . $id);
    return $commentdata;
}

function fc_captcha_error_html($message) {
    return '
    <div style="direction:rtl; text-align:center; padding:40px; font-family:Vazirmatn,system-ui; background:#0b2a4a; color:#fff; min-height:100vh;">
        <h2 style="color:#f44336; font-size:26px; margin-bottom:15px;">❌ خطای امنیتی</h2>
        <p style="font-size:16px; opacity:0.9;">' . esc_html($message) . '</p>
        <a href="javascript:history.back()" style="display:inline-block; margin-top:25px; padding:12px 28px; background:#0d3a5c; color:#fff; text-decoration:none; border-radius:8px; border:1px solid rgba(0,212,255,0.3); font-weight:700;">بازگشت به فرم</a>
    </div>';
}

// ─── JavaScript Canvas + AJAX ───
add_action('wp_footer', 'fc_captcha_js', 99);
function fc_captcha_js() {
    if (empty($GLOBALS['fc_captcha_active'])) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var fcAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

        function fcLoadCaptcha() {
            var formData = new FormData();
            formData.append('action', 'fc_generate_captcha');

            fetch(fcAjaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data) {
                    var canvas = document.getElementById('fc-captcha-canvas');
                    if (canvas) {
                        canvas.setAttribute('data-code', data.data.code);
                        fcGenerateCaptcha();
                    }
                    var idInput = document.getElementById('fc-captcha-id');
                    if (idInput) idInput.value = data.data.id;
                }
            })
            .catch(function(e) { console.error('Captcha load failed:', e); });
        }

        function fcGenerateCaptcha() {
            var canvas = document.getElementById('fc-captcha-canvas');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            var W = 200, H = 60;
            var code = canvas.getAttribute('data-code');
            if (!code || code.length !== 4) return;

            var grad = ctx.createLinearGradient(0, 0, W, H);
            grad.addColorStop(0, '#051e30');
            grad.addColorStop(1, '#0A3D5C');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, W, H);

            for (var i = 0; i < 400; i++) {
                ctx.fillStyle = 'rgba(0,212,255,' + (Math.random()*0.15) + ')';
                ctx.fillRect(Math.random()*W, Math.random()*H, 2, 2);
            }

            for (var i = 0; i < 3; i++) {
                ctx.beginPath();
                ctx.strokeStyle = 'rgba(0, 212, 255, ' + (Math.random()*0.1+0.05) + ')';
                ctx.lineWidth = Math.random()*1.5+0.5;
                var yBase = Math.random()*H, amp = Math.random()*6+3, freq = Math.random()*0.05+0.02;
                for (var x = 0; x < W; x+=2) {
                    var y = yBase + Math.sin(x*freq + Math.random()*Math.PI)*amp;
                    if (x===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
                }
                ctx.stroke();
            }

            for (var i = 0; i < 4; i++) {
                ctx.save();
                ctx.font = 'bold 28px "Courier New", monospace';
                ctx.fillStyle = '#00D4FF';
                ctx.shadowColor = 'rgba(0, 212, 255, 0.5)';
                ctx.shadowBlur = 10;
                ctx.translate(35 + i*42, 38 + (Math.random()*6-3));
                ctx.rotate((Math.random()-0.5)*0.5);
                ctx.fillText(code[i], 0, 0);
                ctx.restore();
            }

            ctx.strokeStyle = 'rgba(0, 212, 255, 0.3)';
            ctx.lineWidth = 2;
            ctx.strokeRect(0, 0, W, H);
        }

        window.fcRefreshCaptcha = function() {
            var canvas = document.getElementById('fc-captcha-canvas');
            if (canvas) {
                canvas.style.opacity = '0.5';
                setTimeout(function() {
                    fcLoadCaptcha();
                    canvas.style.opacity = '1';
                }, 150);
            }
            var input = document.getElementById('fc-captcha-input');
            if (input) { input.value = ''; input.focus(); }
        };

        if (document.getElementById('fc-captcha-canvas')) {
            fcLoadCaptcha();
        }
    });
    </script>
    <?php
}