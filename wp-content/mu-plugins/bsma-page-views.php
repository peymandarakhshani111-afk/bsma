<?php
/*
 * Plugin Name: bsma page views
 * Description: Counts page views and shows "today / this week / this Jalali month" views on every page. Counting runs in the browser after the page loads, so it also works for pages served from the LiteSpeed cache. Bots, repeat loads of the same page by the same browser within 30 minutes, and logged-in editors/admins are not counted. Shortcode [bsma_page_views] places the line anywhere; otherwise it is shown at the bottom of the page.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/bsma-page-views/lib.php';

define('BSMA_PV_DB_VERSION', '1');

add_action('init', 'bsma_pv_install');
function bsma_pv_install() {
    if (!get_option('bsma_pv_secret')) {
        update_option('bsma_pv_secret', wp_generate_password(64, true, true), true);
    }
    if (get_option('bsma_pv_db_version') === BSMA_PV_DB_VERSION) {
        return;
    }
    global $wpdb;
    $table   = bsma_pv_table();
    $charset = $wpdb->get_charset_collate();
    // dbDelta needs two spaces after PRIMARY KEY.
    $sql = "CREATE TABLE $table (
        page_hash CHAR(32) NOT NULL,
        day DATE NOT NULL,
        path VARCHAR(255) NOT NULL DEFAULT '',
        views INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY  (page_hash,day),
        KEY day (day)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('bsma_pv_db_version', BSMA_PV_DB_VERSION);
}

// REST fallback, used only when count.php cannot be reached on the host.
add_action('rest_api_init', function () {
    register_rest_route('bsma/v1', '/page-views', array(
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            $result = bsma_pv_handle(
                (string) $request->get_param('path'),
                (string) $request->get_param('sig'),
                (bool) $request->get_param('count'),
                (string) $request->get_header('user_agent')
            );
            $response = new WP_REST_Response($result['body'], $result['status']);
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            return $response;
        },
    ));
});

/** The path of the current page (no query string), decoded, which is what gets counted. */
function bsma_pv_current_path() {
    $uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $path = rawurldecode((string) strtok($uri, '?#'));
    return $path === '' ? '/' : $path;
}

function bsma_pv_should_show() {
    return !is_admin() && !is_feed() && !is_embed() && !is_404() && !is_preview() && !is_customize_preview()
        && !isset($_GET['elementor-preview']) && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST);
}

add_shortcode('bsma_page_views', function () {
    return '<div class="bsma-pv" data-manual="1" hidden></div>';
});

add_action('wp_footer', 'bsma_pv_footer', 50);
function bsma_pv_footer() {
    if (!bsma_pv_should_show() || !get_option('bsma_pv_secret')) {
        return;
    }
    $path   = bsma_pv_current_path();
    $config = array(
        'path'  => $path,
        'sig'   => bsma_pv_sign($path),
        // Editors and admins looking at their own site are not counted (they still see the numbers).
        'count' => current_user_can('edit_posts') ? 0 : 1,
        'fast'  => plugins_url('bsma-page-views/count.php', __FILE__),
        'rest'  => rest_url('bsma/v1/page-views'),
    );
    ?>
<div class="bsma-pv bsma-pv-auto" hidden></div>
<style>
.bsma-pv{direction:rtl;text-align:center;font-size:13px;line-height:1.9;color:#6b7280;padding:10px 12px;font-family:inherit}
.bsma-pv b{color:#374151;font-weight:700}
</style>
<script data-no-optimize="1" data-no-defer="1">
(function () {
    var cfg = <?php echo wp_json_encode($config); ?>;
    var REPEAT_MS = 30 * 60 * 1000;

    function fa(n) {
        try { return Number(n).toLocaleString('fa-IR'); } catch (e) { return String(n); }
    }

    function show(data) {
        if (!data || typeof data.today !== 'number') return;
        var boxes = document.querySelectorAll('.bsma-pv');
        var hasManual = document.querySelector('.bsma-pv[data-manual]');
        var html = '👁️ بازدید این صفحه — امروز: <b>' + fa(data.today) + '</b> · این هفته: <b>' + fa(data.week) + '</b> · این ماه: <b>' + fa(data.month) + '</b>';
        for (var i = 0; i < boxes.length; i++) {
            if (hasManual && boxes[i].classList.contains('bsma-pv-auto')) {
                boxes[i].parentNode.removeChild(boxes[i]);
                continue;
            }
            boxes[i].innerHTML = html;
            boxes[i].hidden = false;
        }
    }

    function send(url, body) {
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store' });
    }

    function run() {
        var key = 'bsma_pv:' + cfg.path;
        var count = cfg.count === 1 && !navigator.webdriver;
        if (count) {
            try {
                var last = parseInt(localStorage.getItem(key), 10) || 0;
                if (Date.now() - last < REPEAT_MS) count = false;
            } catch (e) {}
        }

        var body = new FormData();
        body.append('path', cfg.path);
        body.append('sig', cfg.sig);
        body.append('count', count ? '1' : '');

        send(cfg.fast, body)
            .then(function (r) {
                // Use the REST route if the fast endpoint is missing, blocked by the host (e.g. an HTML 403),
                // or failed before counting. Our own JSON 403 means a bad signature, which REST would reject too.
                var json = (r.headers.get('content-type') || '').indexOf('application/json') !== -1;
                if (r.ok || (r.status === 403 && json)) return r;
                return send(cfg.rest, body);
            }, function () {
                return send(cfg.rest, body);
            })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.counted) {
                    try { localStorage.setItem(key, String(Date.now())); } catch (e) {}
                }
                show(data);
            })
            .catch(function () {});
    }

    // A page Chrome prerenders in the background is counted only when the visitor actually opens it.
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', run, { once: true });
    } else {
        run();
    }
})();
</script>
    <?php
}
