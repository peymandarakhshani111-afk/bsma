/* bsma welcome gift: the Isfahan poster as a small card in the bottom-right corner, shown once per visitor.
   Tapping the card opens it large; the X removes it. Injected by bsma-welcome.php only while it hasn't been shown. */
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
  var X = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 3l10 10M13 3L3 13"/></svg>';
  var LABEL = 'هدیه‌ی بهسازان: اصفهان، شهر صنعت، هنر و عشق';

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
  var animate = function (host) {
    var a = host.querySelector('.pz-art');
    if (a && !reduce) a.classList.add('pz-anim');
  };
  var busy = function () {
    var a = d.activeElement;
    return a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName);
  };

  var build = function (svg) {
    var root = d.createElement('div');
    root.className = 'pz';
    root.innerHTML = '<div class="pz-dock" role="region" aria-label="' + LABEL + '">'
      + '<button class="pz-close pz-x" type="button" aria-label="بستن">' + X + '</button>'
      + '<button class="pz-open" type="button" aria-label="نمایش بزرگ‌تر">' + svg + '</button>'
      + '<span class="pz-hint" aria-hidden="true">برای دیدن بزرگ‌تر بزنید</span></div>';
    d.body.appendChild(root);
    var dock = root.firstChild;
    animate(dock);

    var overlay = null, last = null;
    var onKey = function (e) {
      if (e.key === 'Escape') { e.preventDefault(); shrink(); }
      else if (e.key === 'Tab') { e.preventDefault(); overlay.querySelector('.pz-close').focus(); }
    };
    var shrink = function () {
      if (!overlay) return;
      var o = overlay;
      overlay = null;
      o.classList.remove('is-open');
      d.removeEventListener('keydown', onKey);
      setTimeout(function () { o.parentNode && o.parentNode.removeChild(o); }, 600);
      if (last && last.focus) { try { last.focus({ preventScroll: true }); } catch (e) {} }
    };
    var expand = function () {
      if (overlay) return;
      last = d.activeElement;
      overlay = d.createElement('div');
      overlay.className = 'pz-overlay';
      overlay.innerHTML = '<div class="pz-card" role="dialog" aria-modal="true" aria-label="' + LABEL + '" tabindex="-1">'
        + '<button class="pz-close" type="button" aria-label="بستن">' + X + '</button>' + svg + '</div>';
      root.appendChild(overlay);
      animate(overlay);
      var card = overlay.firstChild;
      card.firstChild.addEventListener('click', shrink);
      overlay.addEventListener('click', function (e) { if (e.target === overlay) shrink(); });
      d.addEventListener('keydown', onKey);
      requestAnimationFrame(function () {
        overlay.classList.add('is-open');
        setTimeout(function () { try { card.focus({ preventScroll: true }); } catch (e) { card.focus(); } }, 80);
      });
    };
    dock.querySelector('.pz-open').addEventListener('click', expand);
    dock.querySelector('.pz-x').addEventListener('click', function () {
      dock.classList.remove('is-open');
      setTimeout(function () { root.parentNode && root.parentNode.removeChild(root); }, 700);
    });
    requestAnimationFrame(function () { requestAnimationFrame(function () { dock.classList.add('is-open'); }); });
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
      // wait for the stylesheet so the card never flashes unstyled
      if (css.sheet) build(svg); else css.addEventListener('load', function () { build(svg); });
    }).catch(function () { art = null; });
  };

  var start = function () {
    // after the intro (if it played on this page) plus a few seconds on the site
    var delay = w.bsmaIntro ? 6000 : 3000;
    setTimeout(function () { getArt(); }, Math.max(0, delay - 2500));
    setTimeout(open, delay);
  };
  if (d.readyState === 'complete') start(); else w.addEventListener('load', start);
})(document, window);
