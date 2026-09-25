<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array $C @var string $cards @var array $tabs @var array $counts @var string $tk_cards @var string $pop_cards @var WP_Post[] $posts */
?>
<section class="bhm-trust">
  <div class="bhm-wrap bhm-trust-in">
    <p class="bhm-trust-t">دارای تأییدیه‌ها و نشان‌های رسمی</p>
    <div class="bhm-seal bhm-vendor"><span class="bhm-ph">وندور<br>لیست</span><div><b>وندور لیست آتش‌نشانی</b><span>جعبه‌ی بهسازان و اعلام حریق تکنیم</span></div></div>
    <div class="bhm-seal"><img src="<?php echo esc_url($img . 'inso.webp'); ?>" width="58" height="58" loading="lazy" decoding="async" alt="نشان سازمان ملی استاندارد ایران"><div><b>استاندارد ملی ایران</b><span>دارای نشان استاندارد</span></div></div>
    <div class="bhm-seal"><img src="<?php echo esc_url($img . 'isfahan.webp'); ?>" width="58" height="58" loading="lazy" decoding="async" alt="نشان سازمان آتش‌نشانی و خدمات ایمنی شهرداری اصفهان"><div><b>آتش‌نشانی اصفهان</b><span>تأییدیه‌ی شماره‌ی ۶۷۵۴/۷</span></div></div>
    <div class="bhm-seal"><img src="<?php echo esc_url($img . 'tehran.webp'); ?>" width="58" height="58" loading="lazy" decoding="async" alt="نشان سازمان آتش‌نشانی و خدمات ایمنی شهرداری تهران"><div><b>آتش‌نشانی تهران</b><span>سازمان آتش‌نشانی و خدمات ایمنی</span></div></div>
  </div>
</section>

<section class="bhm-brands">
  <div class="bhm-wrap bhm-brands-in">
    <p class="bhm-trust-t">برندهای ما</p>
    <a class="bhm-brand" href="<?php echo esc_url($fb_url); ?>"><span class="bhm-blogo"><?php echo function_exists('bsma_hf_logo') ? bsma_hf_logo('bhm-bsma-logo') : '<b>بهسازان</b>'; ?></span><span><b>بهسازان</b><span>تولید جعبه‌ی آتش‌نشانی از ۱۳۸۵</span></span></a>
    <a class="bhm-brand" href="<?php echo esc_url($tk_url); ?>"><span class="bhm-blogo"><img src="<?php echo esc_url($img . 'teknim.webp'); ?>" width="285" height="64" loading="lazy" decoding="async" alt="Teknim"></span><span><b>تکنیم</b><span>نمایندگی تکنیم در ایران</span></span></a>
    <a class="bhm-brand" href="<?php echo esc_url($gfe_url); ?>"><span class="bhm-blogo"><img src="<?php echo esc_url($img . 'gfe.webp'); ?>" width="96" height="120" loading="lazy" decoding="async" alt="Global Fire Equipment"></span><span><b>Global Fire Equipment</b><span>سیستم اعلام حریق GFE</span></span></a>
  </div>
</section>

<section class="bhm-sec bhm-fb-sec" id="bhm-boxes">
  <div class="bhm-wrap">
    <div class="bhm-head">
      <div><span class="bhm-eyebrow"><i></i>محصول اصلی ما</span><h2>جعبه‌های آتش‌نشانی بهسازان</h2><p class="bhm-lead">همه‌ی مدل‌ها ساخت کارخانه‌ی خودمان است و جعبه‌ی بهسازان در وندور لیست سازمان آتش‌نشانی قرار دارد. نوع کابین یا جنس درب را انتخاب کنید.</p></div>
      <a class="bhm-more" href="<?php echo esc_url($fb_url); ?>">همه‌ی جعبه‌ها ←</a>
    </div>
    <div class="bhm-tabs" role="tablist" aria-label="نوع جعبه">
      <?php foreach ($tabs as $t) : if (!$counts[$t[0]]) { continue; } ?>
      <button class="bhm-tab" role="tab" data-f="<?php echo esc_attr($t[0]); ?>" aria-selected="<?php echo 'all' === $t[0] ? 'true' : 'false'; ?>"><?php echo esc_html($t[1]); ?><span class="bhm-n"><?php echo esc_html(bsma_home_fa($counts[$t[0]])); ?></span></button>
      <?php endforeach; ?>
    </div>
    <div class="bhm-grid bhm-collapsed" id="bhm-grid"><?php echo $cards; ?></div>
    <div class="bhm-grid-foot"><button class="bhm-btn bhm-ghost" id="bhm-more"<?php echo $counts['all'] > 8 ? '' : ' hidden'; ?>>نمایش <?php echo esc_html(bsma_home_fa(max(0, $counts['all'] - 8))); ?> مدل دیگر</button></div>
  </div>
</section>

<?php if ($tk_cards) : ?>
<section class="bhm-sec bhm-tk-sec" id="bhm-teknim">
  <div class="bhm-wrap">
    <div class="bhm-head">
      <div><span class="bhm-eyebrow"><i></i>نمایندگی تکنیم در ایران</span><h2 class="bhm-tk-h"><img src="<?php echo esc_url($img . 'teknim.webp'); ?>" width="142" height="32" loading="lazy" decoding="async" alt="Teknim">سیستم اعلام حریق تکنیم</h2><p class="bhm-lead">به‌عنوان نماینده‌ی کارخانه‌ی تکنیم در ایران، پنل‌ها، ریپیترها، ماژول‌ها و تجهیزات جانبی آدرس‌پذیر و کانونشنال (متعارف) تکنیم را عرضه، طراحی و اجرا می‌کنیم. سیستم اعلام حریق تکنیم در وندور لیست سازمان آتش‌نشانی قرار دارد.</p></div>
      <a class="bhm-more" href="<?php echo esc_url($tk_url); ?>">همه‌ی محصولات تکنیم ←</a>
    </div>
    <div class="bhm-tk-in">
      <div class="bhm-tk-side">
        <a class="bhm-kind" href="<?php echo esc_url($tk_addr); ?>"><span class="bhm-tile"><?php echo bsma_home_ic('panel'); ?></span><span><b>آدرس‌پذیر</b><span class="bhm-kp">هر دتکتور و شاسی آدرس جداگانه دارد و پنل دقیقاً نشان می‌دهد کدام نقطه اعلام کرده است. مناسب ساختمان‌های بزرگ، بیمارستان‌ها و مجموعه‌های صنعتی.</span><span class="bhm-kgo">محصولات آدرس‌پذیر ←</span></span></a>
        <a class="bhm-kind" href="<?php echo esc_url($tk_conv); ?>"><span class="bhm-tile bhm-steel"><?php echo bsma_home_ic('bell'); ?></span><span><b>کانونشنال (متعارف)</b><span class="bhm-kp">تجهیزات به‌صورت زون‌بندی به پنل وصل می‌شوند و پنل زونِ اعلام‌کننده را نشان می‌دهد. انتخاب اقتصادی برای ساختمان‌های کوچک و متوسط.</span><span class="bhm-kgo">محصولات کانونشنال ←</span></span></a>
        <div class="bhm-certs"><b>گواهی‌های استاندارد</b>
          <a href="<?php echo esc_url(content_url('/uploads/2025/11/2757AB61-01A4-88C4-EAEAECF115B4A459.pdf')); ?>"><?php echo bsma_home_ic('doc'); ?>گواهی استاندارد 1922-CPR کنترل پنل متعارف تکنیم (PDF)</a>
          <a href="<?php echo esc_url(content_url('/uploads/2025/11/54790302-D57C-4F07-5E6D9C76881FBC82.pdf')); ?>"><?php echo bsma_home_ic('doc'); ?>گواهی استاندارد annex کنترل پنل تکنیم (PDF)</a>
        </div>
      </div>
      <div class="bhm-grid bhm-tk-grid"><?php echo $tk_cards; ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="bhm-sec">
  <div class="bhm-wrap">
    <div class="bhm-head"><div><span class="bhm-eyebrow"><i></i>راهنمای انتخاب</span><h2>برای ساختمان من چه تجهیزاتی لازم است؟</h2><p class="bhm-lead">نوع ساختمان را پیدا کنید و تجهیزاتی را ببینید که معمولاً برای آن خریداری می‌شود. برای انتخاب دقیق، کارشناسان ما رایگان مشاوره می‌دهند.</p></div></div>
    <div class="bhm-finder">
      <?php foreach ([['home', 'مسکونی', 'آپارتمان‌ها و مجتمع‌های مسکونی', ['box', 'ext', 'alarm', 'sign'], ''], ['office', 'تجاری و اداری', 'مغازه، پاساژ، بیمارستان، مدرسه و ساختمان‌های اداری', ['box', 'ext', 'alarm', 'sup', 'sign', 'light'], ''], ['factory', 'صنعتی', 'کارخانه، انبار، پالایشگاه و نیروگاه', ['box', 'ext', 'sup', 'alarm', 'beam', 'safe'], ' bhm-steel']] as $b) : ?>
      <div class="bhm-bcard"><span class="bhm-tile<?php echo $b[4]; ?>"><?php echo bsma_home_ic($b[0]); ?></span><h3><?php echo esc_html($b[1]); ?></h3><p><?php echo esc_html($b[2]); ?></p><div class="bhm-chips"><?php foreach ($b[3] as $c) : ?><a href="<?php echo esc_url($C[$c][1]); ?>"><?php echo esc_html($C[$c][0]); ?></a><?php endforeach; ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="bhm-sec bhm-cust">
  <div class="bhm-wrap">
    <span class="bhm-eyebrow"><i></i>مشتریان ما</span>
    <h2>تأمین‌کننده‌ی صنایع بزرگ و سازمان‌های دولتی</h2>
    <p class="bhm-lead">پالایشگاه‌ها، نیروگاه‌ها، سازمان‌های دولتی و کارخانه‌های صنعتی تجهیزات ایمنی و آتش‌نشانی خود را از بهسازان تأمین می‌کنند.</p>
    <div class="bhm-sectors">
      <?php foreach ([['refinery', 'پالایشگاه‌ها', 'تجهیزات اطفا و اعلام حریق برای محیط‌های پرخطر', ''], ['power', 'نیروگاه‌ها', 'تأمین و اجرای سامانه‌های ایمنی و آتش‌نشانی', ' bhm-amber'], ['gov', 'سازمان‌های دولتی', 'تأمین تجهیزات ساختمان‌های اداری و عمومی', ''], ['factory', 'کارخانه‌های صنعتی', 'جعبه، کپسول و سیستم‌های اعلام و اطفای حریق', ' bhm-amber']] as $s) : ?>
      <div class="bhm-sector"><span class="bhm-tile<?php echo $s[3]; ?>"><?php echo bsma_home_ic($s[0]); ?></span><b><?php echo esc_html($s[1]); ?></b><span><?php echo esc_html($s[2]); ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="bhm-sec" id="bhm-quote">
  <div class="bhm-wrap bhm-quote">
    <div class="bhm-qside">
      <h2>استعلام قیمت برای پروژه و خرید عمده</h2>
      <p>مشخصات پروژه را بفرستید؛ کارشناس فروش با شما تماس می‌گیرد و پیش‌فاکتور می‌فرستد.</p>
      <ul>
        <li><?php echo bsma_home_ic('check'); ?>پیش‌فاکتور برای خرید عمده و پروژه‌ای</li>
        <li><?php echo bsma_home_ic('check'); ?>مشاوره‌ی رایگان برای انتخاب تجهیزات</li>
        <li><?php echo bsma_home_ic('check'); ?>نصب، اجرا و خدمات پس از فروش</li>
      </ul>
      <span class="bhm-eitaa"><b>ا</b>درخواست شما مستقیم در ایتا به کارشناس فروش می‌رسد</span>
    </div>
    <form class="bhm-qform" id="bhm-qform" novalidate>
      <div class="bhm-field"><label for="bhm-name">نام و نام خانوادگی</label><input id="bhm-name" name="name" autocomplete="name" required maxlength="80"><span class="bhm-err" id="bhm-e-name"></span></div>
      <div class="bhm-field"><label for="bhm-phone">شماره‌ی تماس</label><input id="bhm-phone" name="phone" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="0912 000 0000" required maxlength="20"><span class="bhm-err" id="bhm-e-phone"></span></div>
      <div class="bhm-field"><label for="bhm-type">نوع ساختمان</label><select id="bhm-type" name="type"><option>مسکونی</option><option>تجاری و اداری</option><option>صنعتی و کارخانه</option><option>پالایشگاه یا نیروگاه</option><option>سایر</option></select></div>
      <div class="bhm-field"><label for="bhm-org">نام شرکت یا سازمان (اختیاری)</label><input id="bhm-org" name="org" autocomplete="organization" maxlength="120"></div>
      <div class="bhm-field bhm-full"><label for="bhm-msg">چه چیزی و به چه تعداد لازم دارید؟</label><textarea id="bhm-msg" name="msg" maxlength="1000" placeholder="مثلاً: ۱۲ عدد جعبه‌ی دوکابین درب استیل، ۲۰ کپسول ۶ کیلویی پودر و گاز"></textarea></div>
      <div class="bhm-hp" aria-hidden="true"><label for="bhm-website">وب‌سایت</label><input id="bhm-website" name="website" tabindex="-1" autocomplete="off"></div>
      <button class="bhm-btn bhm-red bhm-submit" type="submit">ارسال درخواست استعلام</button>
      <div class="bhm-status" id="bhm-status" role="status" aria-live="polite"></div>
    </form>
  </div>
</section>

<?php if ($pop_cards) : ?>
<section class="bhm-sec bhm-white">
  <div class="bhm-wrap">
    <div class="bhm-head"><div><span class="bhm-eyebrow"><i></i>پرفروش‌ها</span><h2>محصولات پرفروش</h2></div><a class="bhm-more" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>">فروشگاه ←</a></div>
    <div class="bhm-rail"><?php echo $pop_cards; ?></div>
  </div>
</section>
<?php endif; ?>

<section class="bhm-sec">
  <div class="bhm-wrap bhm-faq">
    <div><span class="bhm-eyebrow"><i></i>سؤالات متداول</span><h2>پیش از خرید بدانید</h2><p class="bhm-lead">جواب کوتاه پرتکرارترین سؤال‌ها؛ برای جزئیات بیشتر، راهنماهای کامل را در بخش مقالات بخوانید.</p></div>
    <div>
      <?php
      $faq = [
          ['جعبه آتش‌نشانی تک‌کابین و دوکابین چه فرقی دارند؟', 'جعبه‌ی تک‌کابین یک محفظه برای قرقره و شیلنگ آتش‌نشانی دارد. جعبه‌ی دوکابین علاوه بر آن، یک محفظه‌ی جدا برای کپسول آتش‌نشانی هم دارد.'],
          ['درب استیل بهتر است یا درب فلزی؟', 'درب استیل در برابر زنگ‌زدگی مقاوم‌تر است و برای لابی و فضاهای اداری ظاهر شیک‌تری دارد. درب فلزی رنگ‌شده اقتصادی‌تر است و برای پارکینگ، انبار و فضاهای صنعتی انتخاب رایجی است.'],
          ['سیستم اعلام حریق آدرس‌پذیر بگیرم یا کانونشنال؟', 'در سیستم آدرس‌پذیر پنل محل دقیق هر دتکتور یا شاسی را نشان می‌دهد و برای ساختمان‌های بزرگ و صنعتی مناسب است. سیستم کانونشنال محل حریق را در حد زون نشان می‌دهد و برای ساختمان‌های کوچک و متوسط اقتصادی‌تر است. برای انتخاب دقیق، <a href="#bhm-quote">مشاوره‌ی رایگان</a> بگیرید.'],
          ['آیا نصب و راه‌اندازی هم انجام می‌دهید؟', 'بله. مشاوره، نصب و اجرا و خدمات پس از فروش سیستم‌های اعلام و اطفای حریق را با تأییدیه‌ی سازمان آتش‌نشانی اصفهان انجام می‌دهیم.'],
          ['کپسول آتش‌نشانی را هر چند وقت باید شارژ کرد؟', 'بسته به نوع کپسول (پودر و گاز، CO2 یا آبی) فرق دارد. راهنمای کامل را در <a href="' . esc_url(home_url('/?s=' . rawurlencode('شارژ کپسول'))) . '">مقاله‌های شارژ کپسول</a> بخوانید.'],
          ['برای خرید عمده یا پروژه چه کنم؟', 'فرم <a href="#bhm-quote">استعلام قیمت</a> را پر کنید یا با ۰۳۱-۳۶۲۴۲۵۳۲ تماس بگیرید؛ کارشناس فروش پیش‌فاکتور پروژه را برایتان می‌فرستد.'],
      ];
      foreach ($faq as $k => $q) :
      ?>
      <details class="bhm-qa"<?php echo 0 === $k ? ' open' : ''; ?>><summary><?php echo esc_html($q[0]); ?><span class="bhm-pm"><?php echo bsma_home_ic('plus'); ?></span></summary><div class="bhm-ans"><?php echo wp_kses($q[1], ['a' => ['href' => []]]); ?></div></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<script type="application/ld+json"><?php
echo wp_json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(function ($q) {
        return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => wp_strip_all_tags($q[1])]];
    }, $faq),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?></script>

<?php if ($posts) : ?>
<section class="bhm-sec bhm-pt0">
  <div class="bhm-wrap">
    <div class="bhm-head"><div><span class="bhm-eyebrow"><i></i>مقالات</span><h2>آخرین راهنماها و مقاله‌ها</h2></div><a class="bhm-more" href="<?php echo esc_url(home_url('/blogs/')); ?>">همه‌ی مقالات ←</a></div>
    <div class="bhm-posts">
      <?php foreach ($posts as $p) : ?>
      <a class="bhm-post" href="<?php echo esc_url(get_permalink($p)); ?>">
        <span class="bhm-pi"><?php echo get_the_post_thumbnail($p, 'medium_large', ['loading' => 'lazy', 'decoding' => 'async', 'alt' => '']); ?></span>
        <span class="bhm-pb"><time datetime="<?php echo esc_attr(get_the_date('c', $p)); ?>"><?php echo esc_html(get_the_date('', $p)); ?></time><span class="bhm-pt"><?php echo esc_html(get_the_title($p)); ?></span></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
