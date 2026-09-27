<?php
/*
 * Plugin Name: bsma welcome
 * Description: First-visit welcome for bsma.ir: a ~4 s full-screen brand intro (logo + «بهسازان») and, a few seconds later, the turquoise Isfahan gift card with a close button. Each is shown once per visitor (browser localStorage), on whatever page they land on; returning visitors load nothing extra. Delete this file to remove both.
 */
if (!defined('ABSPATH')) {
    exit;
}

const BSMA_W_VER = '1.0.2';

function bsma_w_active()
{
    static $on = null;
    if (null !== $on) {
        return $on;
    }
    $on = !is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST)
        && !is_feed() && !is_embed() && !is_customize_preview() && !isset($_GET['elementor-preview'])
        && !(function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page()));
    return $on;
}

// Decide before first paint (the page itself is cached, so this runs in the browser).
add_action('wp_head', function () {
    if (!bsma_w_active()) {
        return;
    }
    $gift = plugins_url('bsma-welcome/gift.js', __FILE__) . '?ver=' . BSMA_W_VER;
    ?>
<style id="bsma-w-css">
.bw-splash{display:none}
.bw-on .bw-splash{position:fixed;inset:0;z-index:2147483646;display:grid;place-items:center;overflow:hidden;cursor:pointer;direction:rtl;
  background:radial-gradient(circle at 50% 44%,#3a0d11 0,#15090b 38%,#07080b 72%);color:#fff;
  animation:bw-out .55s cubic-bezier(.6,0,.4,1) 3.6s forwards;-webkit-tap-highlight-color:transparent}
.bw-skipped .bw-splash{animation:bw-out .35s cubic-bezier(.6,0,.4,1) forwards!important}
.bw-done .bw-splash{display:none!important}
.bw-spin{position:absolute;left:50%;top:44%;width:170vmax;height:170vmax;margin:-85vmax;border-radius:50%;opacity:.55;
  background:repeating-conic-gradient(from 0deg,rgba(211,18,28,.16) 0 7.5deg,transparent 7.5deg 15deg);
  -webkit-mask:radial-gradient(circle,#000 0,rgba(0,0,0,.6) 22%,transparent 60%);mask:radial-gradient(circle,#000 0,rgba(0,0,0,.6) 22%,transparent 60%);
  animation:bw-rot 9s linear infinite,bw-fade 1.2s ease both}
.bw-ring{position:absolute;left:50%;top:44%;width:56vmin;height:56vmin;margin:-28vmin;border-radius:50%;border:2px solid rgba(240,51,59,.6);opacity:0;
  animation:bw-ring 2.4s cubic-bezier(.2,.6,.3,1) infinite}
.bw-ring:nth-child(3){animation-delay:.6s}.bw-ring:nth-child(4){animation-delay:1.2s}.bw-ring:nth-child(5){animation-delay:1.8s;border-color:rgba(60,211,203,.45)}
.bw-core{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;margin-top:-4vh;animation:bw-beat 1.2s ease-in-out 2.3s 1}
.bw-glow{position:absolute;left:50%;top:22%;width:120%;aspect-ratio:1;transform:translate(-50%,-50%);border-radius:50%;background:radial-gradient(circle,rgba(240,51,59,.35),transparent 62%);animation:bw-fade 1.4s ease .2s both}
.bw-logo{position:relative;display:block;width:min(64vw,380px);height:auto;overflow:visible}
.bw-p{fill:#E0232C;opacity:0;transform-box:fill-box;transform-origin:50% 100%;animation:bw-rise .7s cubic-bezier(.2,.9,.25,1.25) both}
.bw-p2{animation-delay:.38s}.bw-p3{animation-delay:.5s}.bw-p4{animation-delay:.62s}.bw-p1{animation-delay:.2s}
.bw-name{position:relative;margin-top:2vh;font:900 clamp(52px,15vw,112px)/1.15 Vazirmatn,Vazir,Tahoma,"Segoe UI","Noto Sans Arabic",sans-serif;letter-spacing:0;
  background:linear-gradient(100deg,#fff 40%,#ffd0d2 50%,#fff 60%) 0 0/250% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;
  animation:bw-name .9s cubic-bezier(.2,.8,.2,1) .95s both,bw-shine 1.3s ease-in-out 1.8s both}
.bw-bar{width:min(46vw,260px);height:3px;margin-top:1.4vh;border-radius:3px;background:linear-gradient(90deg,transparent,#E0232C,#3CD3CB,transparent);transform:scaleX(0);animation:bw-bar .8s cubic-bezier(.2,.8,.2,1) 1.4s forwards}
.bw-sub{margin-top:1.6vh;font:700 clamp(14px,3.8vw,20px)/1.7 Vazirmatn,Vazir,Tahoma,"Segoe UI",sans-serif;color:#c9ced8;opacity:0;animation:bw-up .7s cubic-bezier(.2,.8,.2,1) 1.6s forwards}
.bw-sub2{margin-top:.4vh;font-weight:600;font-size:clamp(13px,3.4vw,18px);color:#9fe9e4;animation-delay:1.95s}
@keyframes bw-out{to{opacity:0;visibility:hidden;transform:scale(1.07)}}
@keyframes bw-rot{to{transform:rotate(360deg)}}
@keyframes bw-fade{from{opacity:0}}
@keyframes bw-ring{0%{opacity:0;transform:scale(.15)}15%{opacity:.9}100%{opacity:0;transform:scale(2.4)}}
@keyframes bw-rise{from{opacity:0;transform:translateY(36%) scale(.7)}to{opacity:1;transform:none}}
@keyframes bw-name{from{opacity:0;transform:translateY(22px) scale(.9);filter:blur(10px)}to{opacity:1;transform:none;filter:none}}
@keyframes bw-shine{from{background-position:100% 0}to{background-position:0 0}}
@keyframes bw-bar{to{transform:scaleX(1)}}
@keyframes bw-up{from{opacity:0;transform:translate(var(--tx,0),8px)}to{opacity:1;transform:translate(var(--tx,0),0)}}
@keyframes bw-beat{0%,100%{transform:scale(1)}40%{transform:scale(1.045)}70%{transform:scale(.995)}}
</style>
<script id="bsma-w-js" data-no-optimize="1" data-no-defer="1" data-cfasync="false">
(function(d,w){try{
var n=navigator,ua=n.userAgent||'';
if(n.webdriver||/bot|crawl|spider|slurp|lighthouse|pagespeed|gtmetrix|speedvitals|pingdom|ptst|headless/i.test(ua))return;
var s=w.localStorage,k='bsma_w',v=JSON.parse(s.getItem(k)||'{}'),h=d.documentElement;
if(!v.i){v.i=Date.now();s.setItem(k,JSON.stringify(v));
 if(!(w.matchMedia&&w.matchMedia('(prefers-reduced-motion: reduce)').matches)){
  h.classList.add('bw-on');w.bsmaIntro=1;
  var end=function(){h.classList.add('bw-done');var e=d.getElementById('bw-splash');e&&e.parentNode.removeChild(e);},
      t=setTimeout(end,4200),
      skip=function(){if(h.classList.contains('bw-done')||h.classList.contains('bw-skipped'))return;clearTimeout(t);h.classList.add('bw-skipped');setTimeout(end,380);};
  d.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('#bw-splash')){e.preventDefault();e.stopPropagation();skip();}},true);
  d.addEventListener('keydown',function(e){if(e.key==='Escape'||e.key==='Enter'||e.key===' ')skip();});
 }}
if(!v.g){var j=d.createElement('script');j.src=<?php echo wp_json_encode($gift); ?>;j.async=true;j.setAttribute('data-no-optimize','1');(d.head||h).appendChild(j);}
}catch(e){}})(document,window);
</script>
    <?php
}, 0);

// Splash markup, first thing in <body> so it is painted before the page. It reuses the logo parts the
// new header defines a few lines later (the parts only fade in after 0.2 s, by which time they exist).
add_action('wp_body_open', function () {
    if (!bsma_w_active()) {
        return;
    }
    if (!function_exists('bsma_hf_active') || !bsma_hf_active()) {
        if (function_exists('bsma_hf_logo_sprite')) {
            echo bsma_hf_logo_sprite();
        }
    }
    ?>
<div id="bw-splash" class="bw-splash" aria-hidden="true">
  <div class="bw-spin"></div><div class="bw-ring"></div><div class="bw-ring"></div><div class="bw-ring"></div><div class="bw-ring"></div>
  <div class="bw-core">
    <div class="bw-glow"></div>
    <svg class="bw-logo" viewBox="8 0 700 382" focusable="false"><g mask="url(#bhf-logo-mask)"><use class="bw-p bw-p1" href="#bhf-lp-rb"/><use class="bw-p bw-p2" href="#bhf-lp-s"/><use class="bw-p bw-p3" href="#bhf-lp-m"/><use class="bw-p bw-p4" href="#bhf-lp-ab"/></g></svg>
    <div class="bw-name">بهسازان</div>
    <div class="bw-bar"></div>
    <div class="bw-sub">تولیدکننده‌ی جعبه آتش‌نشانی بهسازان</div>
    <div class="bw-sub bw-sub2">نماینده‌ی سیستم اعلام حریق تکنیم در ایران</div>
  </div>
</div>
    <?php
}, 1);
