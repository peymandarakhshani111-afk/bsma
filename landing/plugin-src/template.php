<?php
/**
 * Standalone template for the 3D story page.
 * It prints its own <html>: no theme header/footer, no theme/plugin CSS or JS.
 * The SEO tags (title, meta, canonical, schema) are still collected from wp_head()
 * so Rank Math and any other plugin keeps working exactly as on the original page.
 */
if (!defined('ABSPATH')) {
    exit;
}

// 1) Let every plugin build its <head>, then keep only the SEO-relevant tags.
ob_start();
wp_head();
$raw_head = ob_get_clean();

$keep = '';
if (preg_match('#<title[^>]*>.*?</title>#is', $raw_head, $m)) {
    $keep .= $m[0] . "\n";
} else {
    $keep .= '<title>' . esc_html(wp_get_document_title()) . "</title>\n";
}
if (preg_match_all('#<meta\b[^>]*>#i', $raw_head, $m)) {
    foreach ($m[0] as $tag) {
        if (preg_match('#\bcharset\s*=#i', $tag) || preg_match('#\bname\s*=\s*["\']viewport["\']#i', $tag)) {
            continue; // printed below
        }
        $keep .= $tag . "\n";
    }
}
if (preg_match_all('#<link\b[^>]*>#i', $raw_head, $m)) {
    foreach ($m[0] as $tag) {
        if (preg_match('#\brel\s*=\s*["\'](?:canonical|alternate|shortlink|icon|shortcut icon|apple-touch-icon|author|prev|next|https://api\.w\.org/)["\']#i', $tag)) {
            $keep .= $tag . "\n";
        }
    }
}
if (preg_match_all('#<script\b[^>]*type\s*=\s*["\']application/ld\+json["\'][^>]*>.*?</script>#is', $raw_head, $m)) {
    $keep .= implode("\n", $m[0]) . "\n";
}

// 2) Body markup lives in a static file; %BASE% is replaced with the plugin's asset URL.
$base = BSMA_STORY_URL . 'assets/';
$ver  = rawurlencode(BSMA_STORY_VER);
$body = (string) file_get_contents(BSMA_STORY_DIR . 'body.html');
$body = str_replace('%BASE%', esc_url($base), $body);
$head_extra = is_readable(BSMA_STORY_DIR . 'head-extra.html') ? (string) file_get_contents(BSMA_STORY_DIR . 'head-extra.html') : '';

// the UI layer first (small), the heavy 3D scene last and asynchronous: the page is readable before WebGL even starts
$scripts = array('vendor/gsap.min.js', 'vendor/ScrollTrigger.min.js', 'vendor/howler.min.js', 'js/main.js', 'js/scene.js');
$async   = array('js/scene.js');

?><!doctype html>
<html lang="fa-IR" dir="rtl">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#05060a">
<?php echo $keep; // phpcs:ignore WordPress.Security.EscapeOutput ?>
<link rel="preload" href="<?php echo esc_url($base . 'fonts/Vazirmatn-VF.woff2'); ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?php echo esc_url($base . 'img/logo.webp'); ?>" as="image" type="image/webp">
<?php
// The stylesheet is small (≈8 KB compressed), so it is printed inline: one round trip fewer before the first paint.
$css_file = BSMA_STORY_DIR . 'assets/css/style.css';
$css      = is_readable($css_file) ? (string) file_get_contents($css_file) : '';
if ($css !== '') :
    $css = str_replace('url("../fonts/', 'url("' . esc_url($base) . 'fonts/', $css);
    ?>
<style id="bsma-story-css"><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput ?></style>
<?php else : ?>
<link rel="stylesheet" href="<?php echo esc_url($base . 'css/style.css?ver=' . $ver); ?>" data-no-optimize="1">
<?php endif; ?>
<?php echo $head_extra; // phpcs:ignore WordPress.Security.EscapeOutput ?>
</head>
<body class="bsma-story">
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput ?>
<script data-no-optimize="1" data-no-defer="1">window.BSMA_BASE = <?php echo wp_json_encode($base); ?>;</script>
<?php foreach ($scripts as $s) : ?>
<script src="<?php echo esc_url($base . $s . '?ver=' . $ver); ?>"<?php echo in_array($s, $async, true) ? ' async' : ''; ?> data-no-optimize="1" data-no-defer="1"></script>
<?php endforeach; ?>
<?php
// Keep the site's own page-view counter working (it prints a tiny hidden block + script).
if (function_exists('bsma_pv_footer')) {
    bsma_pv_footer();
}
?>
</body>
</html>
