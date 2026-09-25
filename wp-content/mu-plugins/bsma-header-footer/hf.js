(function () {
  'use strict';
  var d = document, w = window;
  var $ = function (id) { return d.getElementById(id); };
  var hdr = $('bhf-hdr');
  if (!hdr) return;
  // The mega menu is printed at the end of the page so the content above it reaches the browser sooner.
  var megaEl = $('bhf-mega');
  if (megaEl) { hdr.appendChild(megaEl); megaEl.removeAttribute('hidden'); }
  var reduce = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var canHover = w.matchMedia && w.matchMedia('(hover: hover)').matches;

  function play(ic) {
    if (!ic || reduce) return;
    ic.classList.remove('play'); void ic.getBoundingClientRect(); ic.classList.add('play');
    setTimeout(function () { ic.classList.remove('play'); }, 1400);
  }

  var toast = $('bhf-toast'), tt;
  function say(m) {
    if (!toast) return;
    toast.textContent = m; toast.classList.add('show');
    clearTimeout(tt); tt = setTimeout(function () { toast.classList.remove('show'); }, 2000);
  }

  // header: slide the top bar away after scrolling (transform only)
  var top = $('bhf-top');
  if (top && 'IntersectionObserver' in w) {
    new IntersectionObserver(function (e) { hdr.classList.toggle('bhf-small', !e[0].isIntersecting); }, { rootMargin: '60px 0px 0px 0px' }).observe(top);
  }

  // ticker
  var tk = $('bhf-ticker');
  if (tk && !reduce && tk.children.length > 1) {
    var ti = 0;
    setInterval(function () {
      if (d.hidden) return;
      ti = (ti + 1) % tk.children.length;
      tk.style.transform = 'translateY(' + (-38 * ti) + 'px)';
    }, 4200);
  }

  // mega menu
  var megaBtn = $('bhf-mega-btn'), mega = $('bhf-mega'), mt;
  function openMega() { clearTimeout(mt); mega.classList.add('open'); megaBtn.setAttribute('aria-expanded', 'true'); }
  function closeMega() { mega.classList.remove('open'); megaBtn.setAttribute('aria-expanded', 'false'); }
  if (megaBtn && mega) {
    megaBtn.addEventListener('click', function () { mega.classList.contains('open') ? closeMega() : openMega(); });
    [megaBtn, mega].forEach(function (el) {
      el.addEventListener('mouseenter', function () { if (canHover) openMega(); });
      el.addEventListener('mouseleave', function () { if (canHover) mt = setTimeout(closeMega, 220); });
    });
    d.addEventListener('click', function (e) { if (!e.target.closest('#bhf-mega,#bhf-mega-btn')) closeMega(); });
  }

  // drawer
  var drawer = $('bhf-drawer'), veil = $('bhf-veil'), burger = $('bhf-burger'), lastFocus;
  function openDrawer(focusSearch) {
    if (!drawer) return;
    lastFocus = d.activeElement;
    drawer.removeAttribute('inert'); drawer.classList.add('open'); veil.classList.add('open');
    if (burger) burger.setAttribute('aria-expanded', 'true');
    d.documentElement.style.overflow = 'hidden';
    drawer.querySelectorAll('.bhf-d-list .bhf-ic').forEach(function (ic, k) { setTimeout(function () { play(ic); }, 120 + k * 60); });
    setTimeout(function () { var f = focusSearch ? $('bhf-dq') : $('bhf-d-close'); if (f) f.focus({ preventScroll: true }); }, 320);
  }
  function closeDrawer() {
    if (!drawer || !drawer.classList.contains('open')) return;
    drawer.classList.remove('open'); veil.classList.remove('open'); drawer.setAttribute('inert', '');
    if (burger) burger.setAttribute('aria-expanded', 'false');
    d.documentElement.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }
  if (burger) burger.addEventListener('click', function () { openDrawer(false); });
  [['bhf-d-close', closeDrawer], ['bhf-veil', closeDrawer]].forEach(function (p) { var el = $(p[0]); if (el) el.addEventListener('click', p[1]); });
  [['bhf-tab-cats', false], ['bhf-tab-search', true], ['bhf-m-search', true]].forEach(function (p) {
    var el = $(p[0]); if (el) el.addEventListener('click', function () { openDrawer(p[1]); });
  });

  // messenger sheet
  var sheet = $('bhf-msg'), sheetVeil = $('bhf-msg-veil');
  function openSheet() {
    if (!sheet) return;
    closeDrawer();
    sheet.removeAttribute('inert'); sheet.classList.add('open'); sheetVeil.classList.add('open');
    sheet.querySelectorAll('.bhf-ic').forEach(function (ic, n) { setTimeout(function () { play(ic); }, 150 + n * 90); });
    setTimeout(function () { var c = $('bhf-msg-close'); if (c) c.focus({ preventScroll: true }); }, 60);
  }
  function closeSheet() {
    if (!sheet || !sheet.classList.contains('open')) return;
    sheet.classList.remove('open'); sheetVeil.classList.remove('open'); sheet.setAttribute('inert', '');
  }
  d.querySelectorAll('.bhf-msg-open').forEach(function (b) { b.addEventListener('click', openSheet); });
  [['bhf-msg-close', closeSheet], ['bhf-msg-veil', closeSheet]].forEach(function (p) { var el = $(p[0]); if (el) el.addEventListener('click', p[1]); });

  function copy(text, okMsg, el) {
    var done = function () { say(okMsg); };
    var fallback = function () {
      if (el) { var r = d.createRange(); r.selectNodeContents(el); var s = w.getSelection(); s.removeAllRanges(); s.addRange(r); }
      say('شماره انتخاب شد؛ آن را کپی کنید');
    };
    try { navigator.clipboard.writeText(text).then(done, fallback); } catch (e) { fallback(); }
  }
  d.querySelectorAll('[data-copy-app]').forEach(function (a) {
    a.addEventListener('click', function () { copy('09306016798', 'شماره کپی شد؛ در ' + a.getAttribute('data-copy-app') + ' جست‌وجو کنید'); });
  });
  d.querySelectorAll('.bhf-copy').forEach(function (b) {
    b.addEventListener('click', function () { var v = b.getAttribute('data-copy'); copy(v, 'شماره کپی شد: ' + v, b.previousElementSibling); });
  });

  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeMega(); closeDrawer(); closeSheet(); closeSugg(); }
  });

  // search suggestions from the real category links in the mega menu
  var q = $('bhf-q'), sugg = $('bhf-sugg');
  var cats = [];
  d.querySelectorAll('#bhf-mega .bhf-fb-card, #bhf-mega .bhf-cat').forEach(function (a) {
    var name = a.classList.contains('bhf-fb-card') ? 'جعبه آتش‌نشانی بهسازان' : (a.querySelector('b') || a).textContent;
    var tile = a.querySelector('.bhf-tile');
    cats.push({ n: name.trim(), u: a.href, t: tile ? tile.outerHTML : '' });
  });
  var norm = function (s) { return s.replace(/[‌\s]+/g, ' ').replace(/ي/g, 'ی').replace(/ك/g, 'ک').toLowerCase(); };
  function closeSugg() { if (sugg) { sugg.classList.remove('open'); q.setAttribute('aria-expanded', 'false'); } }
  function esc(s) { return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function renderSugg() {
    var v = norm(q.value.trim());
    var list = v ? cats.filter(function (c) { return norm(c.n).indexOf(v) > -1; }) : cats.slice(0, 5);
    sugg.innerHTML = (v ? '' : '<p>دسته‌های پرجست‌وجو</p>') + (list.length
      ? list.slice(0, 6).map(function (c) { return '<a role="option" href="' + esc(c.u) + '">' + c.t + esc(c.n) + '</a>'; }).join('')
      : '<p>دسته‌ای پیدا نشد؛ Enter را بزنید تا در همه‌ی محصولات جست‌وجو شود.</p>');
    sugg.classList.add('open'); q.setAttribute('aria-expanded', 'true');
  }
  if (q && sugg) {
    q.addEventListener('focus', renderSugg);
    q.addEventListener('input', renderSugg);
    d.addEventListener('click', function (e) { if (!e.target.closest('.bhf-search')) closeSugg(); });
  }

  // office address opens in the phone's own maps app
  var ua = navigator.userAgent;
  d.querySelectorAll('a.bhf-map').forEach(function (a) {
    var ll = a.getAttribute('data-ll'), name = encodeURIComponent(a.getAttribute('data-q') || '');
    if (/iPhone|iPad|iPod/.test(ua)) a.href = 'https://maps.apple.com/?ll=' + ll + '&q=' + name;
    else if (/Android/.test(ua)) a.href = 'geo:' + ll + '?q=' + ll + '(' + name + ')';
  });

  // Digits login modal for guests, same call the old header used
  d.querySelectorAll('[data-bhf-login]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var jq = w.jQuery;
      if (jq && jq.fn && jq.fn.digits_login_modal) { e.preventDefault(); jq(a).digits_login_modal(jq(a)); }
    });
  });

  // back to top
  var tb = $('bhf-to-top');
  if (tb) tb.addEventListener('click', function () { w.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });

  // logo motion: replay every 14s (only while visible) and on hover
  var sym = $('bhf-logo'), last = Date.now();
  function replay() {
    if (!sym || reduce) return;
    last = Date.now();
    sym.classList.remove('run'); void sym.getBoundingClientRect();
    w.requestAnimationFrame(function () { sym.classList.add('run'); });
  }
  if (sym && !reduce) {
    setInterval(function () { if (!d.hidden && w.scrollY < 400) replay(); }, 14000);
    d.querySelectorAll('.bhf-logo, .bhf-plate').forEach(function (l) {
      l.addEventListener('pointerenter', function () { if (Date.now() - last > 4000) replay(); });
    });
  }
  var logo = hdr.querySelector('.bhf-logo'), lsvg = logo && logo.querySelector('.bhf-logo-svg');
  if (logo && lsvg && canHover && !reduce) {
    logo.addEventListener('pointermove', function (e) {
      var r = logo.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
      lsvg.style.transform = 'rotateY(' + (x * 22) + 'deg) rotateX(' + (-y * 18) + 'deg) translateZ(6px)';
    });
    logo.addEventListener('pointerleave', function () { lsvg.style.transform = ''; });
  }

  // nav icons wave once after load
  if (!reduce) {
    hdr.querySelectorAll('.bhf-nav .bhf-ic, .bhf-cart .bhf-ic').forEach(function (ic, k) { setTimeout(function () { play(ic); }, 1300 + k * 140); });
  }
  d.querySelectorAll('.bhf-tabbar a, .bhf-tabbar button').forEach(function (b) {
    b.addEventListener('click', function () { play(b.querySelector('.bhf-ic')); });
  });

  // footer ambience runs only while the footer is on screen
  var ftr = $('bhf-ftr');
  if (ftr && !reduce && 'IntersectionObserver' in w) {
    var emb = ftr.querySelector('.bhf-embers');
    for (var k = 0; k < 12; k++) {
      var i = d.createElement('i');
      i.style.left = (5 + Math.random() * 90) + '%';
      i.style.animationDelay = (Math.random() * 9).toFixed(2) + 's';
      i.style.animationDuration = (7 + Math.random() * 5).toFixed(2) + 's';
      emb.appendChild(i);
    }
    new IntersectionObserver(function (e) { ftr.classList.toggle('live', e[0].isIntersecting); }).observe(ftr);
  }
})();
