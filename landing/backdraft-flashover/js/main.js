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

  const BS = () => window.BSMA || {};
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ───────────────────────── sound (Howler.js) ───────────────────────── */
  const html5 = location.protocol === 'file:'; // Web Audio cannot XHR files from file://
  if (html5) Howler.html5PoolSize = 24;
  const mk = (name, o = {}) => new Howl(Object.assign({ src: ['audio/' + name + '.wav'], html5, preload: true }, o));
  const SFX = {
    hover: mk('hover', { volume: 0.2 }),
    click: mk('click', { volume: 0.32 }),
    section: mk('section', { volume: 0.28 }),
    whoosh: mk('whoosh', { volume: 0.4 }),
    boom: mk('boom', { volume: 0.5 }),
    ambient: mk('ambient', { volume: 0, loop: true }),
  };
  let soundOn = false;
  let lastHover = 0;
  const soundBtn = $('#sound');

  function play(name, vol) {
    if (!soundOn || !SFX[name]) return;
    const id = SFX[name].play();
    if (vol != null) SFX[name].volume(vol, id);
  }
  function setSound(on) {
    soundOn = on;
    soundBtn.setAttribute('aria-pressed', String(on));
    soundBtn.setAttribute('aria-label', on ? 'صدا: روشن' : 'صدا: خاموش');
    try { localStorage.setItem('bsma-sound', on ? '1' : '0'); } catch (e) { /* storage may be blocked */ }
    if (on) {
      if (!SFX.ambient.playing()) SFX.ambient.play();
      SFX.ambient.fade(SFX.ambient.volume(), 0.16, 1600);
    } else {
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

  /* ───────────────────────── loader ───────────────────────── */
  const loader = $('#loader');
  const fill = $('#loader-fill');
  const actions = $('#loader-actions');
  const note = $('#loader-note');
  document.body.classList.add('locked');
  let loaded = 0;
  const total = 3;
  const tick = () => { loaded++; fill.style.width = Math.round((loaded / total) * 100) + '%'; if (loaded >= total) ready(); };
  let readied = false;
  function ready() {
    if (readied) return;
    readied = true;
    fill.style.width = '100%';
    note.textContent = BS().failed ? 'مرورگر شما WebGL را پشتیبانی نمی‌کند؛ نسخه‌ی ساده نمایش داده می‌شود.' : 'صحنه آماده است.';
    actions.hidden = false;
    $('#start-sound').focus({ preventScroll: true });
  }
  document.fonts && document.fonts.ready ? document.fonts.ready.then(tick) : tick();
  (function waitScene() { BS().ready ? requestAnimationFrame(() => requestAnimationFrame(tick)) : setTimeout(waitScene, 60); })();
  let aLoaded = 0;
  const aTotal = Object.keys(SFX).length;
  Object.values(SFX).forEach((h) => { const f = () => ++aLoaded === aTotal && tick(); h.once('load', f); h.once('loaderror', f); });
  setTimeout(ready, 7000); // never block the page on slow audio

  function begin(withSound) {
    loader.classList.add('done');
    document.body.classList.remove('locked');
    bindCues();
    if (withSound) setSound(true);
    BS().measure && BS().measure();
    ScrollTrigger.refresh();
    intro();
  }
  $('#start-sound').addEventListener('click', () => begin(true));
  $('#start-silent').addEventListener('click', () => begin(false));

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

  // the hero copy lifts away as the camera starts to move
  gsap.timeline({ scrollTrigger: { trigger: '#hero', start: 'top top', end: '+=70%', scrub: 0.4 } })
    .to('.hero-top', { autoAlpha: 0, y: -70, ease: 'none' }, 0)
    .to('.hero-bottom', { autoAlpha: 0, y: 50, ease: 'none' }, 0)
    .to('.scroll-cue', { autoAlpha: 0, ease: 'none' }, 0);

  /* beats: each text card fades in/out over a fraction of the chapter's scroll */
  function beats(section, ranges, extra) {
    const el = $(section);
    const list = $$('.beat', el);
    const tl = gsap.timeline({ defaults: { ease: 'none' }, scrollTrigger: { trigger: el, start: 'top top', end: 'bottom bottom', scrub: 0.5 } });
    const f = 0.035;
    list.forEach((b, i) => {
      const [s, e] = ranges[i];
      tl.fromTo(b, { autoAlpha: 0, y: 46, filter: 'blur(10px)' }, { autoAlpha: 1, y: 0, filter: 'blur(0px)', duration: f }, s);
      if (i < list.length - 1) tl.to(b, { autoAlpha: 0, y: -46, filter: 'blur(10px)', duration: f }, e - f);
    });
    extra && extra(tl);
    tl.set({}, {}, 1); // pin the timeline length to exactly 1 so positions are scroll fractions
    return tl;
  }
  beats('#story', [[0, 0.34], [0.34, 0.67], [0.67, 1]]);
  beats('#flashover', [[0, 0.46], [0.52, 0.78], [0.8, 1]], (tl) => {
    $$('#flashover .fill').forEach((f, i) => tl.fromTo(f, { width: '0%' }, { width: f.dataset.w + '%', duration: 0.1 }, 0.58 + i * 0.03));
    $$('#flashover .thermo i').forEach((f, i) => tl.fromTo(f, { height: '0px' }, { height: (f.dataset.h / 100) * (innerWidth < 820 ? 100 : 130) + 'px', duration: 0.1 }, 0.84 + i * 0.03));
  });
  beats('#backdraft', [[0, 0.13], [0.14, 0.26], [0.27, 0.34], [0.35, 0.52], [0.58, 0.78], [0.8, 1]]);

  // a card or panel that simply holds its place (interactive chapters)
  ['#features', '#research', '#portfolio'].forEach((sel) => {
    const p = $(sel + ' .panel');
    gsap.fromTo(p, { autoAlpha: 0, y: 50 }, { autoAlpha: 1, y: 0, ease: 'power2.out', scrollTrigger: { trigger: sel, start: 'top 55%', end: 'top 15%', scrub: 0.4 } });
  });
  gsap.from('.contact-panel, .sources', { autoAlpha: 0, y: 60, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '#contact', start: 'top 70%' } });

  /* ───────────────────────── per-frame UI sync (HUD, nav, dots, sounds) ───────────────────────── */
  const hudRoom = $('#hud-room'), hudBd = $('#hud-bd');
  const elStage = $('#hud-stage'), elTemp = $('#hud-temp'), elTempBar = $('#hud-temp-bar'), elO2 = $('#hud-o2'), elO2Bar = $('#hud-o2-bar');
  const dots = $$('.dots a'), navs = $$('.nav a');
  const started = () => loader.classList.contains('done');
  let lastCh = -1, lastStage = '', lastStep = -1;

  const syncUI = () => {
    const b = BS();
    if (!b.s) return;
    const T = b.T || 0, ch = b.s.ch;

    if (ch !== lastCh) {
      if (lastCh !== -1 && started()) play('section');
      lastCh = ch;
      dots.forEach((a) => a.classList.toggle('on', +a.dataset.go === ch));
      navs.forEach((a) => {
        const g = +a.dataset.go;
        a.classList.toggle('on', g === ch || (g === 1 && (ch === 2 || ch === 3)));
      });
    }

    hudRoom.classList.toggle('on', T > 0.72 && T < 2.72 && started());
    const rt = b.s.state.roomT || 0, heat = b.s.state.roomHeat || 0;
    const temp = 20 + 580 * heat;
    elTemp.textContent = fa(Math.round(temp / 10) * 10) + '°C';
    elTempBar.style.width = Math.round(heat * 100) + '%';
    const o2 = 1 - 0.78 * clamp((rt - 0.04) / 0.5);
    elO2Bar.style.width = Math.round(o2 * 100) + '%';
    elO2.textContent = o2 > 0.7 ? 'بالا' : o2 > 0.4 ? 'در حال افت' : 'بسیار کم';
    const stage = rt >= 0.76 ? 'فلش‌اور' : rt > 0.5 ? 'تابش و گرمایش' : rt > 0.2 ? 'تهویه‌محور' : 'رشد';
    if (stage !== lastStage) { elStage.textContent = stage; lastStage = stage; }

    const P3 = clamp((b.P[3] || 0) / 0.64);
    hudBd.classList.toggle('on', T > 2.72 && T < 3.55 && P3 > 0.01 && started());
    const step = P3 < 0.22 ? 0 : P3 < 0.44 ? 1 : P3 < 0.545 ? 2 : P3 < 0.9 ? 3 : 4;
    if (step !== lastStep) {
      lastStep = step;
      $$('li', hudBd).forEach((li, i) => { li.classList.toggle('on', i === step); li.classList.toggle('past', i < step); });
    }
  };
  // driven by the scene's own render loop so UI and 3D never drift apart
  (function hook() { BS().s ? (BS().onFrame = syncUI) : setTimeout(hook, 50); })();

  /* ───────────────────────── anchors → cinematic scroll ───────────────────────── */
  const targetFor = (hash) => { const el = $(hash); return el ? el.getBoundingClientRect().top + scrollY : 0; };
  $$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
    const h = a.getAttribute('href');
    if (h.length < 2 || !$(h)) return;
    e.preventDefault();
    scrollTo({ top: targetFor(h), behavior: reduced ? 'auto' : 'smooth' });
    try { history.replaceState(null, '', h); } catch (err) { /* not allowed in some embedded views */ }
  }));

  /* ───────────────────────── features: the five signs ───────────────────────── */
  const SIGNS = [
    { t: 'دود غلیظ زرد یا قهوه‌ای', d: 'نشانه‌ی احتراق ناقص است: دود پر از سوخت نسوخته و بخارات داغ می‌شود. چنین دودی فقط محصول آتش نیست؛ خودش سوخت است و در تماس با هوا می‌تواند مشتعل شود.' },
    { t: 'دود پالسی؛ ساختمان نفس می‌کشد', d: 'دود با ضرباهنگ از شکاف‌ها بیرون می‌زند و دوباره به داخل برمی‌گردد. یعنی فشار داخل محفظه در نوسان است و آتش در مرز کمبود اکسیژن می‌سوزد؛ نام «بک‌درفت» از همین بازگشتِ دود می‌آید.' },
    { t: 'شیشه‌ی دوده‌گرفته و داغ', d: 'از بیرون، شیشه‌ها قهوه‌ای یا سیاه دیده می‌شوند چون اکسیژنِ کافی برای اکسید شدن ذرات دوده در اتاق نیست. شیشه ممکن است بر اثر تغییر فشار کمی بلرزد یا ترک بخورد.' },
    { t: 'شعله‌ی کم یا نامرئی با گرمای زیاد', d: 'نبودنِ شعله به معنای خاموش بودن آتش نیست. گاهی مخلوط آن‌قدر سوخت‌غنی است که شعله نمی‌گیرد، درحالی‌که در و دیوار داغ‌اند. این همان «آرامشِ پیش از طوفان» است.' },
    { t: 'هوا به داخل مکیده می‌شود', d: 'اگر کنار در یا شکاف، صدای مکش یا سوت می‌آید، اتاق هوا می‌کشد. طبق منابع آموزشی این از قوی‌ترین نشانه‌های خطر قریب‌الوقوع است و نیروها باید فوراً تخلیه شوند.' },
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
    $('#sign-text').textContent = s ? s.d : 'روی شماره‌های روی پنجره بزنید یا از فهرست انتخاب کنید.';
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
    { t: 'کپسول آتش‌نشانی', d: 'پودر و گاز و CO₂؛ برای مهار اولیه، در همان دقایقی که آتش هنوز کوچک است.', u: 'https://bsma.ir/product-category/انواع-کپسول-آتش-نشانی/' },
    { t: 'فن تخلیه دود صنعتی ARIS', d: 'فن آکسیال قابل‌حمل با بدنه‌ی آلیاژ آلومینیوم برای تهویه‌ی فضاهای بسته. تهویه در صحنه‌ی آتش فقط با نیروی آموزش‌دیده و به‌صورت هماهنگ انجام می‌شود.', u: 'https://bsma.ir/product/فن-تخلیه-دود-صنعتی-بک-درفت-قابل-حمل/' },
    { t: 'درب دودبند', d: 'برای محدود کردن عبور دود و شعله در مسیرهای تخلیه و راه‌پله‌ها.', u: 'https://bsma.ir/product/درب-دودبند/' },
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
  function paint(ph) {
    photoImg.src = ph.src; photoImg.alt = ph.alt; photoCap.textContent = ph.cap;
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
      im.src = ph.src; im.alt = ''; im.width = 60; im.height = 60; im.loading = 'lazy';
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
