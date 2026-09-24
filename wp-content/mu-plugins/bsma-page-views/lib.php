<?php
/**
 * Page view counter: shared logic for the fast endpoint (count.php, SHORTINIT) and the REST fallback.
 * Only $wpdb, get_option() and wp_timezone() are used, so this works with SHORTINIT.
 */
if (!defined('ABSPATH')) {
    exit;
}

function bsma_pv_table() {
    global $wpdb;
    return $wpdb->prefix . 'bsma_page_views';
}

function bsma_pv_sign($path) {
    return hash_hmac('sha256', $path, (string) get_option('bsma_pv_secret'));
}

function bsma_pv_is_bot($user_agent) {
    if ($user_agent === '') {
        return true;
    }
    return (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|embedly|headless|lighthouse|pagespeed|pingdom|gtmetrix|uptime|monitor|preview|python|curl|wget|httpclient|okhttp|java\/|go-http|axios|node-fetch|scrapy|php\//i', $user_agent);
}

/** Gregorian to Jalali (Persian) date. */
function bsma_pv_gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2   = ($gm > 2) ? ($gy + 1) : $gy;
    $days  = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100) + (int) (($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy    = -1595 + (33 * (int) ($days / 12053));
    $days %= 12053;
    $jy   += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy  += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int) ($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return array($jy, $jm, $jd);
}

/**
 * First local day (Y-m-d) of today, this week and this Jalali month, in the site's time zone.
 * The week starts on the day set in Settings > General (start_of_week; Saturday on Persian sites).
 */
function bsma_pv_period_starts($now = null) {
    $now   = $now ? $now : new DateTimeImmutable('now', wp_timezone());
    $today = $now->setTime(0, 0);

    $week_start_day = (int) get_option('start_of_week', 6);
    $back           = ((int) $today->format('w') - $week_start_day + 7) % 7;

    $jalali = bsma_pv_gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('n'), (int) $today->format('j'));

    return array(
        'today' => $today->format('Y-m-d'),
        'week'  => $today->modify('-' . $back . ' days')->format('Y-m-d'),
        'month' => $today->modify('-' . ($jalali[2] - 1) . ' days')->format('Y-m-d'),
    );
}

/**
 * Verifies the page signature, optionally records one view, and returns today/week/month totals.
 * Returns array('status' => HTTP code, 'body' => array).
 */
function bsma_pv_handle($path, $sig, $count, $user_agent) {
    global $wpdb;

    if (!is_string($path) || $path === '' || $path[0] !== '/' || strlen($path) > 1000 || !is_string($sig)
        || !get_option('bsma_pv_secret') || !hash_equals(bsma_pv_sign($path), $sig)) {
        return array('status' => 403, 'body' => array('error' => 'bad_request'));
    }

    $table  = bsma_pv_table();
    $hash   = md5($path);
    $starts = bsma_pv_period_starts();
    $oldest = min($starts['week'], $starts['month']);

    $suppress = $wpdb->suppress_errors(true);

    // Read the totals before writing, so a failed read never leaves a counted view behind
    // (the browser then retries through the REST fallback).
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN day >= %s THEN views ELSE 0 END), 0) AS today_views,
            COALESCE(SUM(CASE WHEN day >= %s THEN views ELSE 0 END), 0) AS week_views,
            COALESCE(SUM(CASE WHEN day >= %s THEN views ELSE 0 END), 0) AS month_views
         FROM $table WHERE page_hash = %s AND day >= %s",
        $starts['today'], $starts['week'], $starts['month'], $hash, $oldest
    ), ARRAY_A);

    if ($wpdb->last_error || !is_array($row)) {
        $wpdb->suppress_errors($suppress);
        return array('status' => 500, 'body' => array('error' => 'db'));
    }

    $counted = false;
    if ($count && !bsma_pv_is_bot($user_agent)) {
        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (page_hash, day, path, views) VALUES (%s, %s, %s, 1)
             ON DUPLICATE KEY UPDATE views = views + 1",
            $hash, $starts['today'], mb_substr($path, 0, 255)
        ));
        if ($ok === false) {
            $wpdb->suppress_errors($suppress);
            return array('status' => 500, 'body' => array('error' => 'db'));
        }
        $counted = true;
    }
    $wpdb->suppress_errors($suppress);

    $add = $counted ? 1 : 0;
    return array('status' => 200, 'body' => array(
        'today'   => (int) $row['today_views'] + $add,
        'week'    => (int) $row['week_views'] + $add,
        'month'   => (int) $row['month_views'] + $add,
        'counted' => $counted,
    ));
}
