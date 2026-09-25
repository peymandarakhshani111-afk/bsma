<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
$fb_url = function_exists('bsma_home_cat_url') ? bsma_home_cat_url('fire-box', home_url('/product-category/fire-box/')) : home_url('/');
?>
<main id="content" class="bhm">
<section class="bhm-hero">
  <div class="bhm-wrap bhm-hero-in">
    <div class="bhm-hero-t">
      <span class="bhm-eyebrow"><i></i>تولیدکننده‌ی تجهیزات آتش‌نشانی از ۱۳۸۵</span>
      <h1>جعبه آتش‌نشانی <em>بهسازان</em>؛ ساخت کارخانه، تحویل مستقیم به پروژه‌ی شما</h1>
      <p class="bhm-sub">جعبه‌های تک‌کابین و دوکابین با درب فلزی و استیل، و سیستم اعلام حریق تکنیم به‌عنوان نماینده‌ی تکنیم در ایران؛ هر دو در وندور لیست سازمان آتش‌نشانی. به‌همراه مشاوره، نصب و خدمات پس از فروش.</p>
      <div class="bhm-ctas">
        <a class="bhm-btn bhm-red" href="#bhm-boxes"><?php echo bsma_home_ic('box'); ?>مشاهده‌ی جعبه‌ها</a>
        <a class="bhm-btn bhm-ghost" href="#bhm-quote"><?php echo bsma_home_ic('chat'); ?>استعلام قیمت پروژه</a>
      </div>
      <div class="bhm-stats">
        <div class="bhm-stat"><span class="bhm-tile bhm-steel"><?php echo bsma_home_ic('factory'); ?></span><div><b>تولید جعبه</b><span>از سال ۱۳۸۵</span></div></div>
        <div class="bhm-stat"><span class="bhm-tile bhm-amber"><?php echo bsma_home_ic('bell'); ?></span><div><b>نماینده‌ی تکنیم</b><span>اعلام حریق</span></div></div>
        <div class="bhm-stat"><span class="bhm-tile"><?php echo bsma_home_ic('shield'); ?></span><div><b>وندور لیست</b><span>آتش‌نشانی</span></div></div>
      </div>
    </div>
    <div class="bhm-showcase">
      <div class="bhm-card3d">
        <?php
        if (wp_attachment_is_image(BSMA_HOME_HERO_ID)) {
            echo wp_get_attachment_image(BSMA_HOME_HERO_ID, 'large', false, [
                'fetchpriority' => 'high', 'loading' => 'eager', 'decoding' => 'async', 'data-no-lazy' => '1',
                'sizes' => '(max-width: 760px) 280px, 520px',
                'alt' => 'جعبه آتش نشانی دو کابین درب استیل مسی بهسازان',
            ]);
        }
        ?>
        <div class="bhm-float bhm-f1"><span class="bhm-tile"><?php echo bsma_home_ic('shield'); ?></span><div>تأییدیه‌ی آتش‌نشانی<small>شماره‌ی ۶۷۵۴/۷</small></div></div>
        <div class="bhm-float bhm-f2"><span class="bhm-tile bhm-amber"><?php echo bsma_home_ic('box'); ?></span><div>دوکابین درب استیل مسی<small>تولید کارخانه‌ی بهسازان</small></div></div>
      </div>
    </div>
  </div>
</section>
<?php echo bsma_home_sections(); ?>
</main>
<?php
get_footer();
