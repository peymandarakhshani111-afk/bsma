/* UI layer: GSAP + ScrollTrigger drive the HTML, Howler.js drives sound.
   The WebGL scene (js/scene.js) reads scroll by itself and exposes window.BSMA. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const fa = (n) => Math.round(n).toLocaleString('fa-IR', { useGrouping: false });
  const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
  const root = document.documentElement;
  root.classList.add('js');

  // the 3D scene (js/scene.js) loads asynchronously and adopts this object when it starts
  const BSMA0 = (window.BSMA = window.BSMA || {});
  BSMA0.ui = Object.assign({ sign: -1, node: -1, product: 0, dragging: false, dragVel: 0, dragRot: 0, photoMode: true }, BSMA0.ui);
  const BS = () => window.BSMA || {};
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const touch = matchMedia('(hover: none), (pointer: coarse)').matches;
  const phone = () => innerWidth < 820;
  // storage can be blocked (private mode, embedded views): every access is guarded
  const store = (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* ignore */ } };
  const recall = (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } };

  /* ───────────────────────── sound (Howler.js) ───────────────────────── */
  const BASE = window.BSMA_BASE || ''; // set by the WordPress plugin; empty when opened standalone
  const html5 = location.protocol === 'file:'; // Web Audio cannot XHR files from file://
  if (html5) Howler.html5PoolSize = 24;
  // nothing is downloaded until the visitor switches sound on (the files are tiny, but the first paint should not compete with them)
  let SFX = null;
  function loadSounds() {
    if (SFX) return;
    const mk = (name, volume) => new Howl({ src: [BASE + 'audio/' + name + '.mp3'], html5, preload: true, volume });
    SFX = {
      hover: mk('hover', 0.2),
      click: mk('click', 0.32),
      section: mk('section', 0.28),
      whoosh: mk('whoosh', 0.4),
      boom: mk('boom', 0.5),
      ambient: new Howl({ src: [BASE + 'audio/ambient.ogg', BASE + 'audio/ambient.mp3'], html5, preload: true, loop: true, volume: 0 }),
    };
  }
  let soundOn = false;
  let lastHover = 0;
  const soundBtn = $('#sound');

  function play(name, vol) {
    if (!soundOn || !SFX || !SFX[name]) return;
    const id = SFX[name].play();
    if (vol != null) SFX[name].volume(vol, id);
  }
  function setSound(on) {
    soundOn = on;
    soundBtn.setAttribute('aria-pressed', String(on));
    soundBtn.setAttribute('aria-label', on ? 'صدا: روشن' : 'صدا: خاموش');
    store('bsma-sound', on ? '1' : '0');
    if (on) {
      loadSounds();
      if (!SFX.ambient.playing()) SFX.ambient.play();
      SFX.ambient.fade(SFX.ambient.volume(), 0.16, 1600);
    } else if (SFX) {
      SFX.ambient.fade(SFX.ambient.volume(), 0, 500);
      setTimeout(() => !soundOn && SFX.ambient.pause(), 560);
    }
  }
  soundBtn.addEventListener('click', () => { setSound(!soundOn); if (soundOn) play('click'); });

  // soft feedback on every interactive element that opts in
  document.addEventListener('pointerenter', (e) => {
    const t = e.target.closest && e.target.closest('[data-sfx], .card, .sign-list button');
    if (!t || e.target !== t) return;
    const now = performance.now();
    if (now - lastHover > 90) { lastHover = now; play('hover'); }
  }, true);
  document.addEventListener('click', (e) => {
    if (e.target.closest && e.target.closest('[data-sfx], .card, .sign-list button')) play('click');
  });

  /* cues fired by the 3D scene (door opens, flashover, fireball) */
  function bindCues() {
    BS().cue = (name) => {
      if (name === 'doorOpen') play('whoosh');
      else if (name === 'fireball') play('boom');
      else if (name === 'flashover') play('boom', 0.32);
    };
  }

  /* ───────────────────────── start ─────────────────────────
     There is no start screen: the page is readable at once and the 3D canvas fades in when the scene is ready. */
  bindCues();
  (function waitScene() {
    if (BS().ready) { root.classList.add('scene-on'); BS().measure && BS().measure(); ScrollTrigger.refresh(); }
    else setTimeout(waitScene, 80);
  })();

  /* ───────────────────────── GSAP ───────────────────────── */
  gsap.registerPlugin(ScrollTrigger);
  gsap.ticker.lagSmoothing(0); // keep UI animation on real time even when WebGL hitches
  ScrollTrigger.config({ ignoreMobileResize: true });

  gsap.set('.hero-title .line', { yPercent: 115 });
  gsap.set('.eyebrow, .hero-lead, .stats li', { autoAlpha: 0, y: 24 });
  function intro() {
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    tl.to('.hero-title .line', { yPercent: 0, duration: 1.2, stagger: 0.14 }, 0.1)
      .to('.eyebrow', { autoAlpha: 1, y: 0, duration: 0.8 }, 0.3)
      .to('.hero-lead', { autoAlpha: 1, y: 0, duration: 0.9 }, 0.7)
      .to('.stats li', { autoAlpha: 1, y: 0, duration: 0.8, stagger: 0.1 }, 1.0);
  }
  if (reduced) { gsap.set('.hero-title .line', { yPercent: 0 }); gsap.set('.eyebrow, .hero-lead, .stats li', { autoAlpha: 1, y: 0 }); }
  else requestAnimationFrame(intro);

  // the hero copy lifts away as the camera starts to move
  gsap.timeline({ scrollTrigger: { trigger: '#hero', start: 'top top', end: '+=70%', scrub: 0.4 } })
    .to('.hero-top', { autoAlpha: 0, y: -70, ease: 'none' }, 0)
    .to('.hero-bottom', { autoAlpha: 0, y: 50, ease: 'none' }, 0)
    .to('.scroll-cue', { autoAlpha: 0, ease: 'none' }, 0);

  /* beats: each text card fades in/out over a fraction of the chapter's scroll */
  const STORY = []; // every scrolled chapter, in page order: used by story mode (the self-playing film)
  function beats(section, ranges, extra, focus) {
    const el = $(section);
    const list = $$('.beat', el);
    STORY.push({ el, ranges, list, focus: focus || [] });
    const tl = gsap.timeline({ defaults: { ease: 'none' }, scrollTrigger: { trigger: el, start: 'top top', end: 'bottom bottom', scrub: 0.5 } });
    const f = 0.035;
    const fl = touch || phone() ? {} : { filter: 'blur(10px)' };
    const fl0 = touch || phone() ? {} : { filter: 'blur(0px)' };
    list.forEach((b, i) => {
      const [s, e] = ranges[i];
      tl.fromTo(b, { autoAlpha: 0, y: 46, ...fl }, { autoAlpha: 1, y: 0, ...fl0, duration: f }, s);
      if (i < list.length - 1) tl.to(b, { autoAlpha: 0, y: -46, ...fl, duration: f }, e - f);
    });
    extra && extra(tl);
    tl.set({}, {}, 1); // pin the timeline length to exactly 1 so positions are scroll fractions
    return tl;
  }
  beats('#story', [[0, 0.34], [0.34, 0.67], [0.67, 1]], null, [null, null, 0.97]);
  beats('#flashover', [[0, 0.46], [0.52, 0.78], [0.8, 1]], (tl) => {
    $$('#flashover .fill').forEach((f, i) => tl.fromTo(f, { width: '0%' }, { width: f.dataset.w + '%', duration: 0.1 }, 0.58 + i * 0.03));
    $$('#flashover .thermo i').forEach((f, i) => tl.fromTo(f, { height: '0px' }, { height: (f.dataset.h / 100) * (innerWidth < 820 ? 100 : 130) + 'px', duration: 0.1 }, 0.84 + i * 0.03));
  }, [null, 0.75, 0.97]);
  beats('#backdraft', [[0, 0.13], [0.14, 0.26], [0.27, 0.34], [0.35, 0.52], [0.58, 0.78], [0.8, 1]], null, [null, null, null, null, null, 0.97]);

  /* each card says where it is in its chapter ("2 از 3") and what comes next, so the visitor is never left wondering whether to scroll */
  STORY.forEach(({ list }) => list.forEach((b, i) => {
    const tag = $('.tag', b);
    if (tag && list.length > 1) tag.append(Object.assign(document.createElement('span'), { className: 'step', textContent: fa(i + 1) + ' از ' + fa(list.length) }));
    const txt = b.dataset.next || (i < list.length - 1 ? 'ادامه' : '');
    if (txt) b.append(Object.assign(document.createElement('p'), { className: 'nextline', textContent: '↓ ' + txt }));
  }));

  // a card or panel that simply holds its place (interactive chapters)
  ['#features', '#research', '#portfolio'].forEach((sel) => {
    const p = $(sel + ' .panel');
    gsap.fromTo(p, { autoAlpha: 0, y: 50 }, { autoAlpha: 1, y: 0, ease: 'power2.out', scrollTrigger: { trigger: sel, start: 'top 55%', end: 'top 15%', scrub: 0.4 } });
  });
  gsap.from('.contact-panel, .sources', { autoAlpha: 0, y: 60, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '#contact', start: 'top 70%' } });

  /* ───────────────────────── per-frame UI sync (HUD, nav, dots, sounds) ───────────────────────── */
  const hudRoom = $('#hud-room'), hudBd = $('#hud-bd');
  const elStage = $('#hud-stage'), elTemp = $('#hud-temp'), elTempBar = $('#hud-temp-bar'), elO2 = $('#hud-o2'), elO2Bar = $('#hud-o2-bar');
  let lastStage = '', lastStep = -1;

  const syncUI = () => {
    const b = BS();
    if (!b.s) return;
    const T = b.T || 0;

    hudRoom.classList.toggle('on', T > 0.72 && T < 2.72);
    const rt = b.s.state.roomT || 0, heat = b.s.state.roomHeat || 0;
    const temp = 20 + 580 * heat;
    elTemp.textContent = fa(Math.round(temp / 10) * 10) + '°C';
    elTempBar.style.width = Math.round(heat * 100) + '%';
    const o2 = 1 - 0.78 * clamp((rt - 0.04) / 0.5);
    elO2Bar.style.width = Math.round(o2 * 100) + '%';
    elO2.textContent = o2 > 0.7 ? 'کافی' : o2 > 0.4 ? 'در حال کم شدن' : 'بسیار کم';
    const stage = rt >= 0.76 ? 'فلش‌اور' : rt > 0.5 ? 'گرمای شدید' : rt > 0.2 ? 'کمبود هوا' : 'رشد آتش';
    if (stage !== lastStage) { elStage.textContent = stage; lastStage = stage; }

    const P3 = clamp((b.P[3] || 0) / 0.64);
    hudBd.classList.toggle('on', T > 2.72 && T < 3.55 && P3 > 0.01);
    const step = P3 < 0.22 ? 0 : P3 < 0.44 ? 1 : P3 < 0.545 ? 2 : P3 < 0.9 ? 3 : 4;
    if (step !== lastStep) {
      lastStep = step;
      $$('li', hudBd).forEach((li, i) => { li.classList.toggle('on', i === step); li.classList.toggle('past', i < step); });
    }
  };
  // driven by the scene's own render loop so UI and 3D never drift apart
  (function hook() { BS().s ? (BS().onFrame = syncUI) : setTimeout(hook, 50); })();


  /* ───────────────────────── where am I: progress line, chapter chip, dots, nav ─────────────────────────
     Driven by scrolling itself (not by the 3D loop) so it keeps working even where WebGL is unavailable. */
  const CHAPTERS = ['شروع', 'آتش در اتاق', 'فلش‌اور', 'بک‌درفت', 'نشانه‌های خطر', 'یافته‌ها', 'نمونه کارها', 'تماس با ما', 'متن مقاله'];
  const chEls = [...$$('main > .chapter'), $('#article')].filter(Boolean);
  const dots = $$('.dots a'), navs = $$('.nav a'), tocs = $$('.toc a');
  const progress = $('#progress'), whereN = $('#where-n'), whereT = $('#where-t');
  let tops = [], artTop = 0, lastCh = -1, ticking = false;
  const measureUI = () => {
    const y = scrollY;
    tops = chEls.map((el) => el.getBoundingClientRect().top + y);
    artTop = tops[tops.length - 1] || 1;
  };
  const currentChapter = () => {
    const probe = scrollY + innerHeight * 0.5;
    let c = 0;
    for (let i = 0; i < tops.length; i++) if (probe >= tops[i]) c = i;
    return c;
  };
  function paintWhere() {
    ticking = false;
    const y = scrollY;
    progress.style.transform = 'scaleX(' + clamp(y / Math.max(1, artTop - innerHeight)).toFixed(4) + ')';
    if (!galleryOn && tops[6] && y + innerHeight * 3 > tops[6]) enableGallery();
    const ch = currentChapter();
    if (ch === lastCh) return;
    if (lastCh !== -1) play('section');
    lastCh = ch;
    whereN.textContent = fa(ch + 1);
    whereT.textContent = CHAPTERS[ch] || '';
    dots.forEach((a) => a.classList.toggle('on', +a.dataset.go === ch));
    navs.forEach((a) => {
      const g = a.dataset.go;
      a.classList.toggle('on', g != null && (+g === ch || (+g === 1 && (ch === 2 || ch === 3))));
    });
    tocs.forEach((a) => a.classList.toggle('on', +a.dataset.go === ch));
    onChapter(ch);
  }
  const queuePaint = () => { if (!ticking) { ticking = true; requestAnimationFrame(paintWhere); } };
  addEventListener('scroll', queuePaint, { passive: true });
  addEventListener('resize', () => { measureUI(); queuePaint(); });
  addEventListener('load', () => { measureUI(); queuePaint(); });
  measureUI();

  /* ───────────────────────── menu + how-to sheets ───────────────────────── */
  let openSheetEl = null, sheetOpener = null;
  const focusable = (el) => [...el.querySelectorAll('a[href], button:not([disabled])')].filter((x) => !x.closest('[hidden]'));
  function openSheet(el, opener) {
    closeSheet(true);
    sheetOpener = opener || document.activeElement;
    openSheetEl = el;
    el.hidden = false;
    root.classList.add('sheet-open');
    if (opener && opener.id === 'where') opener.setAttribute('aria-expanded', 'true');
    const f = $('.help-ok', el) || $('.toc a.on', el) || focusable(el)[0];
    f && f.focus({ preventScroll: true });
  }
  function closeSheet(silent) {
    if (!openSheetEl) return;
    openSheetEl.hidden = true;
    openSheetEl = null;
    root.classList.remove('sheet-open');
    $('#where').setAttribute('aria-expanded', 'false');
    if (!silent && sheetOpener && sheetOpener.focus) sheetOpener.focus({ preventScroll: true });
  }
  $('#where').addEventListener('click', (e) => { play('click'); openSheet($('#menu'), e.currentTarget); });
  $$('.sheet').forEach((sh) => sh.addEventListener('click', (e) => { if (e.target.closest('[data-close]')) closeSheet(); }));
  addEventListener('keydown', (e) => {
    if (!openSheetEl) return;
    if (e.key === 'Escape') { e.preventDefault(); closeSheet(); return; }
    if (e.key === 'Tab') { // keep focus inside the open sheet
      const f = focusable(openSheetEl);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* ───────────────────────── guidance for first-time visitors ───────────────────────── */
  // first visit: the help button wears its label and pulses until it is used (or for a while)
  const helpBtn = $('#help');
  function dismissTip() {
    if (!helpBtn.classList.contains('pulse-me')) return;
    helpBtn.classList.remove('pulse-me');
    store('bsma-tip', '1');
  }
  if (recall('bsma-tip') !== '1') {
    helpBtn.classList.add('pulse-me');
    setTimeout(dismissTip, 20000);
  }
  helpBtn.addEventListener('click', (e) => { dismissTip(); openSheet($('#help-dlg'), e.currentTarget); });

  /* a short hint the first time each interactive chapter comes on screen; it stops for good once the visitor has used it */
  const HINTS = {
    4: { key: 'signs', t: 'روی دایره‌های شماره‌دار روی پنجره بزنید' },
    5: { key: 'cards', t: touch ? 'روی هر کارت بزنید تا نقطه‌ی هم‌رنگش در صحنه روشن شود' : 'نشانگر را روی هر کارت ببرید تا نقطه‌ی هم‌رنگش روشن شود' },
    6: { key: 'products', t: touch ? 'محصول را با انگشت بکشید تا بچرخد؛ با فلش‌ها محصول بعدی را ببینید' : 'محصول را بکشید تا بچرخد؛ با فلش‌ها محصول بعدی را ببینید' },
  };
  const used = (() => { try { return JSON.parse(recall('bsma-used') || '{}'); } catch (e) { return {}; } })();
  const shown = {};
  const hintEl = $('#hint'), hintT = $('#hint-t'), hintGo = $('#hint-go');
  let hintTimer = 0, hintCh = -1, hintKey = null;
  function hideHint() {
    clearTimeout(hintTimer);
    hintEl.classList.remove('show');
    setTimeout(() => !hintEl.classList.contains('show') && (hintEl.hidden = true), 420);
    $$('.hs.nudge').forEach((h) => h.classList.remove('nudge'));
    hintCh = -1; hintKey = null;
  }
  function markUsed(key) {
    if (used[key]) return;
    used[key] = 1;
    store('bsma-used', JSON.stringify(used));
    if (hintKey === key) hideHint();
  }
  // o: { ch, key, sticky (stay until dismissed), go (label of a "continue" button) }
  function showHint(text, o = {}) {
    clearTimeout(hintTimer);
    hintCh = o.ch ?? -1; hintKey = o.key || null;
    hintT.textContent = text;
    hintGo.hidden = !o.go;
    if (o.go) hintGo.textContent = o.go;
    hintEl.hidden = false;
    requestAnimationFrame(() => requestAnimationFrame(() => hintEl.classList.add('show')));
    if (o.ch === 4) { const first = $('.hs'); first && first.classList.add('nudge'); }
    if (!o.sticky) hintTimer = setTimeout(hideHint, 9000);
  }
  function showChapterHint(ch) {
    const h = HINTS[ch];
    if (!h || used[h.key] || shown[ch] || openSheetEl || AP.on) return;
    shown[ch] = 1;
    showHint(h.t, { ch, key: h.key });
  }
  let hintDelay = 0;
  function onChapter(ch) {
    clearTimeout(hintDelay);
    if (hintCh !== -1 && hintCh !== ch) hideHint();
    if (HINTS[ch]) hintDelay = setTimeout(() => currentChapter() === ch && showChapterHint(ch), 900);
  }
  $('#hint-x').addEventListener('click', () => { hintKey ? markUsed(hintKey) : hideHint(); hideHint(); });
  $$('.hs, .sign-list button').forEach((b) => b.addEventListener('click', () => markUsed('signs')));
  $$('.card').forEach((c) => c.addEventListener('click', () => markUsed('cards')));
  ['#product-stage', '#p-next', '#p-prev', '#view-toggle', '#p-dots'].forEach((sel) => $(sel).addEventListener('pointerdown', () => markUsed('products')));

  /* ───────────────────────── story mode: the page plays itself like a film ─────────────────────────
     It scrolls to each card, waits as long as the card takes to read, and moves on. Any touch, wheel or key press hands control back.
     At the three interactive chapters it stops and asks the visitor to try things, then continues when they press "continue". */
  const playBtn = $('#play');
  const AP = { on: false, wp: [], i: 0, phase: 'idle', t0: 0, y0: 0, y1: 0, dur: 0, until: 0, lastY: 0, raf: 0 };
  const STOPS = [
    ['#features', 'نوبت شماست: روی دایره‌های شماره‌دار بزنید و نشانه‌ها را بخوانید. بعد «ادامه» را بزنید.', 4],
    ['#research', 'نوبت شماست: روی کارت‌ها بزنید تا نقطه‌ی هم‌رنگشان در صحنه روشن شود. بعد «ادامه» را بزنید.', 5],
    ['#portfolio', 'نوبت شماست: محصولات را ببینید و بچرخانید. بعد «ادامه» را بزنید.', 6],
  ];
  const words = (el) => (el.textContent.match(/\S+/g) || []).length;
  function buildWaypoints() {
    const vh = innerHeight, wp = [];
    const at = (el, f) => { const r = el.getBoundingClientRect(); return r.top + scrollY + f * Math.max(0, r.height - vh); };
    STORY.forEach(({ el, ranges, list, focus }) => list.forEach((b, i) => {
      const [s0, e0] = ranges[i];
      const f = focus[i] != null ? focus[i] : (s0 + e0) / 2;
      wp.push({ y: at(el, f), dwell: clamp(1800 + words(b) * 270, 4000, 12000) });
    }));
    STOPS.forEach(([sel, msg, ch]) => wp.push({ y: at($(sel), 0.5), stop: true, msg, ch, go: 'ادامه ▶' }));
    const c = $('#contact');
    wp.push({ y: c.getBoundingClientRect().top + scrollY + 40, stop: true, end: true, ch: 7, msg: 'پایان داستان. هر سؤالی دارید، از همین‌جا با ما تماس بگیرید.', go: 'از اول ▶' });
    return wp.sort((a, b) => a.y - b.y);
  }
  const setPlayUI = (on) => {
    playBtn.setAttribute('aria-pressed', String(on));
    playBtn.setAttribute('aria-label', on ? 'توقف پخش خودکار' : 'پخش خودکار داستان');
    root.classList.toggle('playing', on);
  };
  function apPause() {
    if (!AP.on) return;
    AP.on = false;
    cancelAnimationFrame(AP.raf);
    setPlayUI(false);
  }
  function apMoveTo(i, now) {
    AP.i = i;
    const w = AP.wp[i];
    AP.y0 = scrollY; AP.y1 = w.y;
    const dist = Math.abs(AP.y1 - AP.y0);
    AP.dur = clamp((dist / 430) * 1000, 1600, 7000);
    AP.t0 = now;
    AP.phase = dist < 6 ? 'dwell' : 'move';
    if (AP.phase === 'dwell') AP.until = now + (w.dwell || 0);
  }
  function apFrame(now) {
    if (!AP.on) return;
    AP.raf = requestAnimationFrame(apFrame);
    if (Math.abs(scrollY - AP.lastY) > 4) { apPause(); return; } // the visitor scrolled by themselves
    const w = AP.wp[AP.i];
    if (AP.phase === 'move') {
      const k = clamp((now - AP.t0) / AP.dur);
      const e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
      scrollTo({ top: AP.y0 + (AP.y1 - AP.y0) * e, behavior: 'instant' });
      AP.lastY = scrollY;
      if (k >= 1) {
        if (w.stop) { apPause(); showHint(w.msg, { ch: w.ch, sticky: true, go: w.go }); return; }
        AP.phase = 'dwell'; AP.until = now + w.dwell;
      }
    } else if (now >= AP.until) {
      if (AP.i + 1 >= AP.wp.length) { apPause(); return; }
      apMoveTo(AP.i + 1, now);
    }
  }
  function apPlay() {
    if (AP.on) return;
    AP.wp = buildWaypoints();
    hideHint();
    let i = AP.wp.findIndex((w) => w.y > scrollY + 30);
    if (i < 0) { scrollTo({ top: 0, behavior: 'instant' }); i = 0; } // finished before: start again
    AP.on = true;
    AP.lastY = scrollY;
    setPlayUI(true);
    const now = performance.now();
    apMoveTo(i, now);
    if (AP.phase === 'dwell') AP.until = now + 600;
    AP.raf = requestAnimationFrame(apFrame);
  }
  playBtn.addEventListener('click', () => (AP.on ? apPause() : apPlay()));
  $('#play-hero').addEventListener('click', apPlay);
  hintGo.addEventListener('click', apPlay);
  // any real input from the visitor takes over
  const takeOver = (e) => {
    if (!AP.on) return;
    if (e.target && e.target.closest && e.target.closest('#play, #play-hero, #hint-go')) return;
    if (e.type === 'keydown' && !['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(e.key)) return;
    apPause();
  };
  ['wheel', 'touchstart', 'keydown'].forEach((t) => addEventListener(t, takeOver, { passive: true }));

  /* ───────────────────────── anchors → cinematic scroll ───────────────────────── */
  const targetFor = (hash) => { const el = $(hash); return el ? el.getBoundingClientRect().top + scrollY : 0; };
  $$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
    const h = a.getAttribute('href');
    if (h.length < 2 || !$(h)) return;
    e.preventDefault();
    if (openSheetEl && openSheetEl.contains(a)) closeSheet(true);
    scrollTo({ top: targetFor(h), behavior: reduced ? 'auto' : 'smooth' });
    try { history.replaceState(null, '', h); } catch (err) { /* not allowed in some embedded views */ }
  }));

  /* ───────────────────────── features: the five signs ───────────────────────── */
  const SIGNS = [
    { t: 'دودِ غلیظ زرد یا قهوه‌ای', d: 'این رنگ یعنی آتش ناقص می‌سوزد و دود پر از سوخت و بخارِ نسوخته است. چنین دودی فقط حاصل آتش نیست، خودش هم می‌تواند آتش بگیرد.' },
    { t: 'دودی که نفس می‌کشد', d: 'دود با ریتم از شکاف‌ها بیرون می‌زند و دوباره به داخل برمی‌گردد، انگار ساختمان نفس می‌کشد. یعنی فشار داخل بالا و پایین می‌شود و آتش در مرز کمبود اکسیژن می‌سوزد.' },
    { t: 'شیشه‌ی سیاه‌شده و داغ', d: 'از بیرون، شیشه‌ها قهوه‌ای یا سیاه دیده می‌شوند، چون در اتاق اکسیژن کافی برای سوختن دوده نیست. اگر فشار تغییر کند، شیشه ممکن است بلرزد یا ترک بخورد.' },
    { t: 'شعله‌ی کم یا نامرئی، با گرمای زیاد', d: 'دیده نشدن شعله یعنی آتش خاموش است؟ نه. گاهی مخلوط آن‌قدر پر از سوخت است که شعله نمی‌گیرد، اما در و دیوار داغ‌اند. این همان آرامشِ پیش از طوفان است.' },
    { t: 'هوا به داخل مکیده می‌شود', d: 'اگر کنار در یا شکاف صدای مکش یا سوت می‌آید، اتاق دارد هوا می‌کشد. طبق منابع آموزشی، این از قوی‌ترین نشانه‌های خطرِ نزدیک است و باید فوراً دور شد.' },
  ];
  const signBtns = $$('[data-sign]'), hsBtns = $$('.hs');
  const signBox = $('#sign-detail');
  function selectSign(i) {
    const cur = BS().ui;
    const next = cur && cur.sign === i ? -1 : i;
    if (cur) cur.sign = next;
    signBtns.forEach((b) => { const on = +b.dataset.sign === next; b.classList.toggle('on', on); b.setAttribute('aria-selected', on); });
    hsBtns.forEach((b) => b.classList.toggle('on', +b.dataset.hs === next));
    const s = SIGNS[next];
    $('#sign-title').textContent = s ? s.t : 'یکی از نشانه‌ها را انتخاب کنید';
    $('#sign-text').textContent = s ? s.d : 'روی دایره‌های شماره‌دار در تصویر بزنید، یا از فهرست بالا انتخاب کنید.';
    signBox.classList.remove('swap'); void signBox.offsetWidth; signBox.classList.add('swap');
  }
  signBtns.forEach((b) => b.addEventListener('click', () => selectSign(+b.dataset.sign)));
  hsBtns.forEach((b) => b.addEventListener('click', () => selectSign(+b.dataset.hs)));

  /* ───────────────────────── research cards ↔ 3D nodes ───────────────────────── */
  $$('.card').forEach((c) => {
    const i = +c.dataset.node;
    const on = () => { BS().ui.node = i; $$('.card').forEach((x) => x.classList.toggle('on', x === c)); };
    const off = () => { BS().ui.node = -1; c.classList.remove('on'); };
    c.addEventListener('pointerenter', on); c.addEventListener('focus', on);
    c.addEventListener('pointerleave', off); c.addEventListener('blur', off);
    c.addEventListener('click', on);
  });

  /* ───────────────────────── portfolio ───────────────────────── */
  const PRODUCTS = [
    { t: 'جعبه آتش‌نشانی بهسازان', d: 'ساخت کارخانه‌ی خودمان از ۱۳۸۵؛ مدل‌های فلزی و استیل، با تأییدیه‌ی سازمان آتش‌نشانی اصفهان.', u: 'https://bsma.ir/product-category/fire-box/' },
    { t: 'کپسول آتش‌نشانی', d: 'پودر و گاز و CO₂؛ برای مهار آتش در همان دقیقه‌های اولی که هنوز کوچک است.', u: 'https://bsma.ir/product-category/انواع-کپسول-آتش-نشانی/' },
    { t: 'فن تخلیه دود صنعتی ARIS', d: 'فن آکسیال قابل‌حمل با بدنه‌ی آلیاژ آلومینیوم برای تخلیه‌ی دود در فضاهای بسته. تهویه در صحنه‌ی آتش فقط با نیروی آموزش‌دیده و هماهنگ انجام می‌شود.', u: 'https://bsma.ir/product/فن-تخلیه-دود-صنعتی-بک-درفت-قابل-حمل/' },
    { t: 'درب دودبند', d: 'برای جلوگیری از عبور دود و شعله در مسیرهای خروج و راه‌پله‌ها.', u: 'https://bsma.ir/product/درب-دودبند/' },
    { t: 'سیستم اعلام حریق', d: 'دتکتور، کنترل پنل و تجهیزات تست و نگهداری؛ برندهای GFE و تکنیم، متعارف و آدرس‌پذیر.', u: 'https://bsma.ir/product-category/سیستم-اعلام-حریق/' },
  ];
  let pi = 0;
  const pDots = $('#p-dots'), pInfo = $('#product-info'), photoStage = $('#photo-stage');
  PRODUCTS.forEach((p, i) => {
    const li = document.createElement('li');
    const b = document.createElement('button');
    b.type = 'button'; b.setAttribute('aria-label', p.t); b.dataset.sfx = '';
    b.addEventListener('click', () => showProduct(i));
    li.appendChild(b); pDots.appendChild(li);
  });
  function showProduct(i) {
    pi = (i + PRODUCTS.length) % PRODUCTS.length;
    const p = PRODUCTS[pi];
    BS().ui.product = pi;
    $('#p-title').textContent = p.t;
    $('#p-text').textContent = p.d;
    $('#p-link').href = p.u;
    $$('button', pDots).forEach((b, k) => b.classList.toggle('on', k === pi));
    pInfo.classList.remove('swap'); void pInfo.offsetWidth; pInfo.classList.add('swap');
    showProductView(pi);
  }
  $('#p-next').addEventListener('click', () => showProduct(pi - 1)); // RTL: "next" points left
  $('#p-prev').addEventListener('click', () => showProduct(pi + 1));

  /* real photographs from bsma.ir product pages (optimised WebP in /img) */
  const W = { fit: 'contain', bg: '#fff' };
  const GALLERY = [
    [ // جعبه آتش‌نشانی
      { src: 'img/firebox-black.webp', cap: 'جعبه آتش‌نشانی دو کابین، فلزی مشکی', alt: 'جعبه آتش‌نشانی دو کابین فلزی مشکی بهسازان' },
      { src: 'img/firebox-steel.webp', cap: 'جعبه آتش‌نشانی دو کابین، استیل مات', alt: 'جعبه آتش‌نشانی دو کابین با درب استیل مات بهسازان' },
      { src: 'img/firebox-open.webp', cap: 'داخل جعبه: کپسول و قرقره‌ی شیلنگ', alt: 'جعبه آتش‌نشانی باز با کپسول و قرقره‌ی شیلنگ' },
    ],
    [ // کپسول
      { src: 'img/ext-6kg.webp', cap: 'کپسول پودر و گاز ۶ کیلوگرمی', alt: 'کپسول آتش‌نشانی پودر و گاز ۶ کیلوگرمی', ...W },
      { src: 'img/ext-12kg.webp', cap: 'کپسول ۱۲ کیلوگرمی', alt: 'کپسول آتش‌نشانی ۱۲ کیلوگرمی با نشان BSMA', ...W },
      { src: 'img/ext-25kg.webp', cap: 'کپسول چرخ‌دار ۲۵ کیلوگرمی', alt: 'کپسول آتش‌نشانی چرخ‌دار ۲۵ کیلوگرمی', ...W },
    ],
    [ // فن
      { src: 'img/fan-aris.webp', cap: 'فن تخلیه‌ی دود صنعتی ARIS', alt: 'فن تخلیه دود صنعتی قابل‌حمل ARIS' },
    ],
    [ // درب
      { src: 'img/door-cream.webp', cap: 'درب با بار پانیک، رنگ کرم', alt: 'درب آتش‌نشانی با بار پانیک و تابلوی FIRE EXIT، رنگ کرم', pos: '50% 36%' },
      { src: 'img/door-brown.webp', cap: 'درب با بار پانیک، رنگ قهوه‌ای', alt: 'درب آتش‌نشانی با بار پانیک و تابلوی FIRE EXIT، رنگ قهوه‌ای', pos: '50% 36%' },
      { src: 'img/door-white.webp', cap: 'درب با بار پانیک، رنگ سفید', alt: 'درب آتش‌نشانی با بار پانیک و تابلوی FIRE EXIT، رنگ سفید', pos: '50% 36%' },
    ],
    [ // اعلام حریق
      { src: 'img/alarm-detector.webp', cap: 'دتکتور دود اپتیکال', alt: 'دتکتور دود اپتیکال', ...W },
      { src: 'img/alarm-panel.webp', cap: 'کنترل پنل اعلام حریق تکنیم', alt: 'کنترل پنل اعلام حریق تکنیم', ...W },
    ],
  ];
  const photoImg = $('#photo-main'), photoCap = $('#photo-cap'), photoCard = $('#photo-card'), thumbs = $('#photo-thumbs');
  let view = 'photo';
  // photos are fetched only when the portfolio is about to come on screen (paintWhere calls enableGallery while scrolling)
  let galleryOn = false;
  const setSrc = (img, url) => { img.dataset.src = url; if (galleryOn) img.src = url; };
  function enableGallery() {
    if (galleryOn) return;
    galleryOn = true;
    $$('img[data-src]', $('#product-stage')).forEach((im) => { im.src = im.dataset.src; });
  }
  function paint(ph) {
    setSrc(photoImg, BASE + ph.src); photoImg.alt = ph.alt; photoCap.textContent = ph.cap;
    photoImg.style.objectFit = ph.fit || 'cover';
    photoImg.style.objectPosition = ph.pos || '50% 50%';
    photoImg.style.background = ph.bg || '#fff';
  }
  function renderGallery(i) {
    const list = GALLERY[i] || [];
    thumbs.replaceChildren();
    list.forEach((ph, k) => {
      const b = document.createElement('button');
      b.type = 'button'; b.dataset.sfx = ''; b.setAttribute('aria-label', ph.cap); b.classList.toggle('on', k === 0);
      const im = document.createElement('img');
      setSrc(im, BASE + ph.src); im.alt = ''; im.width = 60; im.height = 60;
      im.style.objectFit = ph.fit || 'cover'; im.style.objectPosition = ph.pos || '50% 50%'; im.style.background = ph.bg || '#fff';
      b.appendChild(im);
      b.addEventListener('click', () => {
        $$('button', thumbs).forEach((x) => x.classList.toggle('on', x === b));
        gsap.to(photoImg, { opacity: 0, duration: 0.15, onComplete: () => { paint(ph); gsap.to(photoImg, { opacity: 1, duration: 0.3 }); } });
      });
      thumbs.appendChild(b);
    });
    thumbs.hidden = list.length < 2;
    if (list[0]) paint(list[0]);
  }
  function applyView() {
    const photo = view === 'photo';
    BS().ui.photoMode = photo;
    photoStage.hidden = !photo;
    $('#drag-hint').hidden = photo;
    $('#view-toggle').textContent = photo ? 'مشاهده‌ی مدل سه‌بعدی' : 'مشاهده‌ی عکس واقعی';
    if (photo && !reduced) gsap.fromTo('#photo-card', { autoAlpha: 0, y: 34, scale: 0.94 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.75, ease: 'power3.out' });
  }
  $('#view-toggle').addEventListener('click', () => { view = view === 'photo' ? '3d' : 'photo'; applyView(); });
  const showProductView = (i) => { renderGallery(i); applyView(); };
  showProduct(0); // everything it needs is defined above
  // the card leans toward the pointer (hover) and follows a drag, like turning it on a turntable
  let tilt = { x: 0, y: 0 };
  const setTilt = () => { photoCard.style.setProperty('--rx', tilt.x.toFixed(1) + 'deg'); photoCard.style.setProperty('--ry', tilt.y.toFixed(1) + 'deg'); };

  const stage = $('#product-stage');
  stage.addEventListener('pointermove', (e) => {
    if (photoStage.hidden || dragging) return;
    const r = stage.getBoundingClientRect();
    tilt.y = ((e.clientX - r.left) / r.width - 0.5) * 18;
    tilt.x = -((e.clientY - r.top) / r.height - 0.5) * 10;
    setTilt();
  });
  stage.addEventListener('pointerleave', () => { tilt = { x: 0, y: 0 }; setTilt(); });
  let dragging = false, lastX = 0, lastT = 0;
  stage.addEventListener('pointerdown', (e) => { if (e.target.closest('.photo-thumbs')) return; dragging = true; lastX = e.clientX; lastT = performance.now(); BS().ui.dragging = true; stage.setPointerCapture(e.pointerId); });
  stage.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    const now = performance.now(), dt = Math.max(8, now - lastT) / 1000;
    BS().ui.dragVel = ((e.clientX - lastX) * 0.012) / dt;
    if (!photoStage.hidden) { tilt.y = Math.max(-28, Math.min(28, tilt.y + (e.clientX - lastX) * 0.35)); setTilt(); }
    lastX = e.clientX; lastT = now;
  });
  const endDrag = () => { dragging = false; if (BS().ui) BS().ui.dragging = false; };
  stage.addEventListener('pointerup', endDrag); stage.addEventListener('pointercancel', endDrag);
  stage.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') showProduct(pi - 1);
    if (e.key === 'ArrowRight') showProduct(pi + 1);
  });

  /* ───────────────────────── contact form → WhatsApp (no backend) ───────────────────────── */
  $('#quick').addEventListener('submit', (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    const name = (f.get('name') || '').toString().trim();
    const msg = (f.get('msg') || '').toString().trim();
    const text = `سلام، ${name ? name + ' هستم. ' : ''}درباره‌ی «${f.get('need')}» مشاوره می‌خواهم.${msg ? '\n' + msg : ''}`;
    const url = 'https://wa.me/989306016798?text=' + encodeURIComponent(text);
    const link = $('#wa-link');
    link.href = url;
    link.hidden = false; // a real link works everywhere, even where pop-ups are blocked
    try { window.open(url, '_blank', 'noopener'); } catch (err) { /* the visible link remains */ }
  });

  /* after fonts settle the layout height may shift: re-measure for the 3D scene */
  addEventListener('load', () => { BS().measure && BS().measure(); ScrollTrigger.refresh(); });
})();
