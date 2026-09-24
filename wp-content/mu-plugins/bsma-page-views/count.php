<?php
/**
 * Fast page view endpoint. Loads WordPress with SHORTINIT (database and options only, no plugins or theme),
 * so counting a view on a page served from the LiteSpeed cache stays cheap.
 * If this file cannot be reached, the browser falls back to the REST route in bsma-page-views.php.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

define('SHORTINIT', true);
$bsma_wp_load = dirname(__DIR__, 3) . '/wp-load.php';
if (!is_file($bsma_wp_load)) {
    http_response_code(500);
    exit;
}
require $bsma_wp_load;
require __DIR__ . '/lib.php';

// With SHORTINIT, WordPress has not added slashes to $_POST (wp_magic_quotes() runs later), so no unslash here.
$bsma_result = bsma_pv_handle(
    isset($_POST['path']) ? $_POST['path'] : '',
    isset($_POST['sig']) ? $_POST['sig'] : '',
    !empty($_POST['count']),
    isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''
);

http_response_code($bsma_result['status']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex');
echo json_encode($bsma_result['body']);
