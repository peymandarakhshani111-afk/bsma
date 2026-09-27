/* bsma welcome gift: the Isfahan poster card, shown once per visitor. Injected by bsma-welcome.php only while it hasn't been shown. */
(function (d, w) {
  'use strict';
  var KEY = 'bsma_w';
  var me = d.currentScript || d.querySelector('script[src*="bsma-welcome/gift.js"]');
  if (!me) return;
  var base = me.src.replace(/gift\.js(\?.*)?$/, '');
  var ver = (me.src.match(/\?ver=([^&]+)/) || [])[1] || '1';
  var state = function () { try { return JSON.parse(w.localStorage.getItem(KEY) || '{}'); } catch (e) { return null; } };
  var save = function (s) { try { w.localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {} };
  var reduce = !!(w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches);

  var css = d.createElement('link');
  css.rel = 'stylesheet';
  css.href = base + 'gift.css?ver=' + ver;
  d.head.appendChild(css);

  var art = null;
  var getArt = function () {
    if (!art) {
      art = fetch(base + 'art.svg?ver=' + ver, { credentials: 'omit' }).then(function (r) {
        if (!r.ok) throw new Error(r.status);
        return r.text();
      });
    }
    return art;
  };

  var busy = function () {
    var a = d.activeElement;
    return a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName);
  };

  var open = function () {
    var s = state();
    if (!s || s.g) return;
    if (busy() || d.visibilityState === 'hidden') { setTimeout(open, 3000); return; }
    getArt().then(function (svg) {
      s = state();
      if (!s || s.g) return;
      s.g = Date.now();
      save(s);

      var root = d.createElement('div');
      root.className = 'pz';
      root.innerHTML = '<div class="pz-overlay"><div class="pz-card" role="dialog" aria-modal="true" aria-label="هدیه‌ی بهسازان: اصفهان، شهر صنعت، هنر و عشق" tabindex="-1">'
        + '<button class="pz-close" type="button" aria-label="بستن"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 3l10 10M13 3L3 13"/></svg></button>'
        + svg + '</div></div>';
      d.body.appendChild(root);
      var overlay = root.firstChild, card = overlay.firstChild, btn = card.firstChild;
      var artEl = card.querySelector('.pz-art');
      if (artEl && !reduce) artEl.classList.add('pz-anim');
      var last = d.activeElement;

      var onKey = function (e) {
        if (e.key === 'Escape') { e.preventDefault(); close(); }
        else if (e.key === 'Tab') { e.preventDefault(); btn.focus(); }
      };
      var close = function () {
        overlay.classList.remove('is-open');
        d.removeEventListener('keydown', onKey);
        if (last && last.focus) { try { last.focus({ preventScroll: true }); } catch (e) {} }
        setTimeout(function () { root.parentNode && root.parentNode.removeChild(root); }, 600);
      };
      btn.addEventListener('click', close);
      overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
      d.addEventListener('keydown', onKey);

      // wait for the stylesheet so the card never flashes unstyled
      var show = function () {
        requestAnimationFrame(function () {
          overlay.classList.add('is-open');
          setTimeout(function () { try { card.focus({ preventScroll: true }); } catch (e) { card.focus(); } }, 80);
        });
      };
      if (css.sheet) show(); else { css.addEventListener('load', show); css.addEventListener('error', close); }
    }).catch(function () { art = null; });
  };

  var start = function () {
    // after the intro (if it played on this page) plus a few seconds on the site
    var delay = w.bsmaIntro ? 8000 : 4000;
    setTimeout(function () { getArt(); }, Math.max(0, delay - 2500));
    setTimeout(open, delay);
  };
  if (d.readyState === 'complete') start(); else w.addEventListener('load', start);
})(document, window);
