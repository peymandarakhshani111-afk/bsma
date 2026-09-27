<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Inspection story: the fire department inspector approves an installed box,
 * then the standards inspector marks a box on the production line.
 * Pure SVG + CSS (transform/opacity only); plays once when scrolled into view.
 * @var string $img
 */

// inspector figure, feet at 0,0, facing left
$person = function ($coat, $coat2, $pants, $helmet, $stripe) {
    ob_start(); ?>
<g class="bhm-flip">
  <g transform="translate(3,-62)"><g class="bhm-leg bhm-leg-b"><rect x="-6" y="0" width="12" height="60" rx="5" fill="<?php echo $pants; ?>" opacity=".8"/><rect x="-13" y="55" width="19" height="8" rx="3" fill="#111"/></g></g>
  <g transform="translate(-2,-62)"><g class="bhm-leg"><rect x="-6" y="0" width="12" height="60" rx="5" fill="<?php echo $pants; ?>"/><rect x="-13" y="55" width="19" height="8" rx="3" fill="#1b1b1b"/></g></g>
  <g transform="translate(5,-114)"><rect x="-5" y="0" width="10" height="46" rx="5" fill="<?php echo $coat2; ?>"/><rect x="-20" y="30" width="20" height="26" rx="2" fill="#C98300"/><rect x="-18" y="33" width="16" height="21" rx="1" fill="#fff"/><path d="M-15 39h10M-15 44h10M-15 49h7" stroke="#9aa3b2" stroke-width="1.6"/></g>
  <rect x="-18" y="-122" width="36" height="64" rx="13" fill="<?php echo $coat; ?>"<?php echo $stripe ? '' : ' stroke="#AEB8C6" stroke-width="1.5"'; ?>/>
  <?php if ($stripe) : ?><rect x="-18" y="-98" width="36" height="4" fill="<?php echo $stripe; ?>"/><rect x="-18" y="-80" width="36" height="5" fill="<?php echo $stripe; ?>"/><?php else : ?><path d="M0 -121v60" stroke="#C9D0DA" stroke-width="1.5"/><rect x="-14" y="-104" width="10" height="7" rx="2" fill="#1D4F91"/><?php endif; ?>
  <circle cx="0" cy="-135" r="13" fill="#E6B48A"/><circle cx="-7" cy="-136" r="1.7" fill="#222"/><path d="M-9 -129q3 2 6 0" stroke="#8a5a3c" stroke-width="1.4" fill="none"/>
  <path d="M-15 -137a15 15 0 0 1 30 0z" fill="<?php echo $helmet; ?>"/><rect x="-20" y="-139" width="22" height="4" rx="2" fill="<?php echo $helmet; ?>"/>
  <g transform="translate(-3,-114)"><g class="bhm-arm"><rect x="-5" y="0" width="10" height="50" rx="5" fill="<?php echo $coat2; ?>"/><circle cx="0" cy="52" r="6" fill="#E6B48A"/><rect x="-3.5" y="55" width="7" height="12" rx="2" fill="#6B4A2B"/><rect x="-9" y="66" width="18" height="7" rx="2" fill="<?php echo $stripe ? '#D3121C' : '#1D4F91'; ?>"/></g></g>
</g>
<?php
    return ob_get_clean();
};

$chip = function ($x, $label, $n) {
    return '<g transform="translate(' . $x . ',58)"><g class="bhm-chip bhm-c' . $n . '"><rect width="78" height="26" rx="13" fill="#fff" stroke="#E2E6EC"/><text x="30" y="17.5" text-anchor="middle" font-size="11" font-weight="700" fill="#16191F">' . $label . '</text><circle cx="65" cy="13" r="8" fill="#1F9D55"/><path d="M61 13l3 3 5-6" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></g></g>';
};

$badge = function ($src, $label) {
    return '<g class="bhm-badge"><rect x="278" y="10" width="190" height="44" rx="12" fill="#fff" stroke="#E2E6EC"/><image href="' . esc_url($src) . '" x="426" y="14" width="36" height="36"/><text x="351" y="37" text-anchor="middle" font-size="12" font-weight="800" fill="#16191F">' . $label . '</text></g>';
};

$ribbon = function ($label) {
    return '<g transform="translate(16,262)"><g class="bhm-ribbon"><rect width="206" height="28" rx="14" fill="#1F9D55"/><text x="92" y="18.5" text-anchor="middle" font-size="12" font-weight="800" fill="#fff">' . $label . '</text><path d="M180 14l4 4 7-8" stroke="#fff" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></g></g>';
};

$firebox = function ($w, $h) {
    $r = min($w, $h) * .24;
    return '<rect width="' . $w . '" height="' . $h . '" rx="6" fill="url(#bhm-sc-red)"/><rect x="6" y="6" width="' . ($w - 12) . '" height="' . ($h - 12) . '" rx="4" fill="#B20F17" stroke="#F0333B" stroke-width="1.5"/>'
        . '<rect x="10" y="10" width="' . ($w - 20) . '" height="' . ($h * .62) . '" rx="3" fill="#fff" opacity=".14"/>'
        . '<circle cx="' . ($w * .42) . '" cy="' . ($h * .42) . '" r="' . $r . '" fill="none" stroke="#fff" stroke-width="' . max(3, $r * .22) . '" opacity=".85"/><circle cx="' . ($w * .42) . '" cy="' . ($h * .42) . '" r="' . ($r * .3) . '" fill="#fff" opacity=".85"/>'
        . '<rect x="' . ($w - 16) . '" y="' . ($h * .45) . '" width="5" height="' . ($h * .16) . '" rx="2" fill="#fff" opacity=".9"/>';
};
?>
<section class="bhm-sec bhm-white bhm-insp" id="bhm-inspect">
  <div class="bhm-wrap">
    <div class="bhm-head">
      <div>
        <span class="bhm-eyebrow"><i></i>تأییدیه‌ها در عمل</span>
        <h2>هر جعبه، زیر نگاه آتش‌نشانی و اداره‌ی استاندارد</h2>
        <p class="bhm-lead">نماینده‌ی آتش‌نشانی نصب را در پروژه بازدید و تأیید می‌کند و کارشناس استاندارد خط تولید را می‌بیند و نشان استاندارد را درج می‌کند.</p>
      </div>
      <button type="button" class="bhm-replay" hidden><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>پخش دوباره</button>
    </div>
    <div class="bhm-scenes">
      <figure class="bhm-sc" data-sc="a">
        <svg viewBox="0 0 480 300" role="img" aria-label="نماینده‌ی سازمان آتش‌نشانی اصفهان جعبه‌ی نصب‌شده در پروژه را بازدید می‌کند و مهر تأیید می‌زند.">
          <defs><linearGradient id="bhm-sc-red" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F0333B"/><stop offset="1" stop-color="#B20F17"/></linearGradient></defs>
          <rect width="480" height="300" fill="#EEF1F5"/>
          <path d="M0 62h480M0 124h480M0 186h480M120 0v62M300 62v62M60 124v62M390 124v62M200 186v64" stroke="#E1E6ED" stroke-width="2"/>
          <rect x="30" y="92" width="72" height="84" rx="4" fill="#DCE8F3" stroke="#C7D2DE" stroke-width="2"/><path d="M66 92v84M30 134h72" stroke="#C7D2DE" stroke-width="2"/>
          <rect y="250" width="480" height="50" fill="#DDE2E9"/><path d="M0 250h480" stroke="#C9D0DA" stroke-width="2"/>
          <rect x="136" y="106" width="130" height="140" rx="6" fill="#16191F" opacity=".12"/>
          <g transform="translate(130,100)"><g class="bhm-box"><?php echo $firebox(130, 140); ?></g></g>
          <g transform="translate(226,150) rotate(-14)"><circle class="bhm-ripple" r="22" fill="none" stroke="#D3121C" stroke-width="3"/><g class="bhm-mark"><circle r="22" fill="#fff" fill-opacity=".92" stroke="#D3121C" stroke-width="3"/><circle r="17" fill="none" stroke="#D3121C" stroke-width="1" stroke-dasharray="2 2"/><text y="4.5" text-anchor="middle" font-size="12.5" font-weight="900" fill="#D3121C">تأیید</text></g></g>
          <?php echo $chip(256, 'نصب', 1), $chip(170, 'شیلنگ', 2), $chip(84, 'دسترسی', 3); ?>
          <g transform="translate(338,265)"><g class="bhm-walker"><?php echo $person('#1F3A66', '#264A80', '#1E2A44', '#D3121C', '#FFB21A'); ?></g></g>
          <?php echo $badge($img . 'isfahan.webp', 'آتش‌نشانی اصفهان'), $ribbon('نصب تأیید شد'); ?>
        </svg>
        <figcaption><b>۱</b>بازدید نماینده‌ی آتش‌نشانی از پروژه و مهر تأیید نصب</figcaption>
      </figure>
      <figure class="bhm-sc" data-sc="b">
        <svg viewBox="0 0 480 300" role="img" aria-label="کارشناس اداره‌ی استاندارد خط تولید جعبه‌های آتش‌نشانی بهسازان را بازدید می‌کند و نشان استاندارد ملی ایران را درج می‌کند.">
          <rect width="480" height="300" fill="#EEF1F5"/>
          <path d="M0 0v44l40-30v30l40-30v30l40-30v30l40-30v30l40-30v30l40-30v30l40-30v30l40-30v30l40-30v30l40-30v30l40-30v30l40-30V0z" fill="#DDE3EA"/>
          <path d="M60 120h60v40H60zM190 100h60v30h-60z" fill="#E4E9EF"/>
          <rect y="250" width="480" height="50" fill="#DDE2E9"/><path d="M0 250h480" stroke="#C9D0DA" stroke-width="2"/>
          <path d="M40 212v38M150 212v38M260 212v38" stroke="#465063" stroke-width="6"/>
          <rect x="10" y="200" width="300" height="14" rx="7" fill="#2E3542"/>
          <path class="bhm-belt" d="M16 201h288" stroke="#8A94A6" stroke-width="2" stroke-dasharray="10 8"/>
          <g class="bhm-line"><?php foreach ([36, 128, 220] as $x) : ?><g transform="translate(<?php echo $x; ?>,132)"><?php echo $firebox(60, 68); ?></g><?php endforeach; ?></g>
          <g transform="translate(252,158) rotate(-10)"><circle class="bhm-ripple bhm-blue" r="19" fill="none" stroke="#1D4F91" stroke-width="3"/><g class="bhm-mark"><circle r="19" fill="#fff" stroke="#1D4F91" stroke-width="3"/><image href="<?php echo esc_url($img . 'inso.webp'); ?>" x="-13" y="-13" width="26" height="26"/></g></g>
          <?php echo $chip(256, 'ابعاد', 1), $chip(170, 'ورق', 2), $chip(84, 'رنگ', 3); ?>
          <g transform="translate(338,265)"><g class="bhm-walker"><?php echo $person('#FFFFFF', '#E9EEF4', '#2E3542', '#1D4F91', ''); ?></g></g>
          <?php echo $badge($img . 'inso.webp', 'سازمان ملی استاندارد'), $ribbon('نشان استاندارد درج شد'); ?>
        </svg>
        <figcaption><b>۲</b>بازدید کارشناس استاندارد از خط تولید و درج نشان استاندارد</figcaption>
      </figure>
    </div>
  </div>
</section>
