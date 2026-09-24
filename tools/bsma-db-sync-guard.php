<?php
/*
 * Plugin Name: bsma DB sync guard (diagnostic)
 * Description: Finds the source of "Commands out of sync; you can't run this command now". At the start of shutdown it probes the database connection with SELECT 1. If a query earlier in the request left an unread (unbuffered) result, it writes one line to the PHP error log: the request, the cron events and Action Scheduler actions that ran, and the plugin files that were loaded (files whose name suggests a database export are listed). It changes nothing by default. With define('BSMA_DB_SYNC_GUARD_RECONNECT', true) in wp-config.php it also reconnects, so the other shutdown work (Torob lock reset, Action Scheduler, LiteSpeed) is not lost.
 */
if (!defined('ABSPATH')) {
    exit;
}

$GLOBALS['bsma_dbsg_events'] = array();

// WP-Cron: wp-cron.php reschedules/unschedules each event right before running it.
add_filter('pre_reschedule_event', function ($pre, $event) {
    if (is_object($event) && !empty($event->hook)) {
        $GLOBALS['bsma_dbsg_events'][] = 'cron:' . $event->hook;
    }
    return $pre;
}, 10, 2);
add_filter('pre_unschedule_event', function ($pre, $timestamp, $hook) {
    $GLOBALS['bsma_dbsg_events'][] = 'cron:' . $hook;
    return $pre;
}, 10, 3);

// Action Scheduler (WooCommerce, Torob, ...): remember each action that runs.
add_action('action_scheduler_before_execute', function ($action_id) {
    $hook = '#' . $action_id;
    try {
        if (class_exists('ActionScheduler')) {
            $hook = ActionScheduler::store()->fetch_action($action_id)->get_hook() . '#' . $action_id;
        }
    } catch (Throwable $e) {
    }
    $GLOBALS['bsma_dbsg_events'][] = 'as:' . $hook;
}, 0);

add_action('shutdown', 'bsma_dbsg_check', 0);
function bsma_dbsg_check() {
    global $wpdb;
    if (!isset($wpdb->dbh) || !($wpdb->dbh instanceof mysqli)) {
        return;
    }

    $errno = 0;
    try {
        $res = @mysqli_query($wpdb->dbh, 'SELECT 1');
        if ($res instanceof mysqli_result) {
            mysqli_free_result($res);
            return;
        }
        $errno = mysqli_errno($wpdb->dbh);
    } catch (mysqli_sql_exception $e) {
        $errno = (int) $e->getCode();
    } catch (Throwable $e) {
        return;
    }
    if ($errno !== 2014) { // CR_COMMANDS_OUT_OF_SYNC
        return;
    }

    $flags = array();
    if (defined('DOING_CRON') && DOING_CRON) $flags[] = 'cron';
    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) $flags[] = 'ajax';
    if (defined('REST_REQUEST') && REST_REQUEST) $flags[] = 'rest';
    if (defined('WP_CLI') && WP_CLI) $flags[] = 'wp-cli';
    if (is_admin()) $flags[] = 'admin';

    $action = '';
    if (isset($_REQUEST['action']) && is_scalar($_REQUEST['action'])) {
        $action = preg_replace('/[^A-Za-z0-9_\-.:]/', '', (string) $_REQUEST['action']);
    }

    // Plugin files loaded in this request, grouped by plugin; flag the ones that look like DB export code.
    $plugins_dir = wp_normalize_path(WP_PLUGIN_DIR) . '/';
    $per_plugin  = array();
    $suspects    = array();
    foreach (get_included_files() as $file) {
        $file = wp_normalize_path($file);
        if (strpos($file, $plugins_dir) !== 0) {
            continue;
        }
        $rel  = substr($file, strlen($plugins_dir));
        $slug = strtok($rel, '/');
        $per_plugin[$slug] = isset($per_plugin[$slug]) ? $per_plugin[$slug] + 1 : 1;
        if (preg_match('/(export|dump|backup|database|db[-_.]|mysql|sql|package|archive|chunk|batch|extract|runner|queue)/i', basename($rel))) {
            $suspects[] = $rel;
        }
    }
    arsort($per_plugin);
    $plugin_list = array();
    foreach ($per_plugin as $slug => $n) {
        $plugin_list[] = $slug . '=' . $n;
    }

    $line = sprintf(
        '[bsma-db-sync-guard] out-of-sync DB connection at shutdown | %s %s | action=%s | %s | elapsed=%.1fs | user=%d | ua=%s | events=%s | last_wpdb_query=%s | suspect_files=%s | plugin_files=%s',
        isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '-',
        isset($_SERVER['REQUEST_URI']) ? substr((string) $_SERVER['REQUEST_URI'], 0, 300) : '-',
        $action !== '' ? $action : '-',
        $flags ? implode(',', $flags) : 'front',
        function_exists('timer_float') ? timer_float() : 0,
        function_exists('get_current_user_id') ? get_current_user_id() : 0,
        isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 80) : '-',
        $GLOBALS['bsma_dbsg_events'] ? implode(',', array_slice(array_unique($GLOBALS['bsma_dbsg_events']), 0, 20)) : '-',
        substr(preg_replace('/\s+/', ' ', (string) $wpdb->last_query), 0, 160),
        $suspects ? implode(',', array_slice($suspects, 0, 15)) : '-',
        implode(',', array_slice($plugin_list, 0, 25))
    );
    error_log($line);

    if (defined('BSMA_DB_SYNC_GUARD_RECONNECT') && BSMA_DB_SYNC_GUARD_RECONNECT) {
        // Fresh connection so the remaining shutdown callbacks can still write to the database.
        $wpdb->close();
        $ok = $wpdb->db_connect(false);
        error_log('[bsma-db-sync-guard] reconnected: ' . ($ok ? 'ok' : 'failed'));
    }
}
