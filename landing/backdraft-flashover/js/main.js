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
  window.BSMA_LOAD && BSMA_LOAD.set(50); // the loading screen: the UI layer is in place
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
  else if (window.BSMA_LOAD) BSMA_LOAD.whenDone(() => requestAnimationFrame(intro)); // play the hero intro once the loading screen has left
  else requestAnimationFrame(intro);

  // the hero copy lifts away as the camera starts to move
  gsap.timeline({ scrollTrigger: { trigger: '#hero', start: 'top top', end: '+=70%', scrub: 0.4 } })
    .to('.hero-top', { autoAlpha: 0, y: -70, ease: 'none' }, 0)
    .to('.hero-bottom', { autoAlpha: 0, y: 50, ease: 'none' }, 0);

  /* beats: each text card fades in/out over a fraction of the chapter's scroll */
  const STORY = []; // every scrolled chapter, in page order: used by story mode (the self-playing film)
  function beats(section, ranges, extra, focus) {
    const el = $(section);
    const list = $$('.beat', el);
    STORY.push({ el, ranges, list, focus: focus || [] });
    const tl = gsap.timeline({ defaults: { ease: 'none' }, scrollTrigger: { trigger: el, start: 'top top', end: 'bottom bottom', scrub: 0.5 } });
    const f = 0.035;
    list.forEach((b, i) => {
      const [s, e] = ranges[i];
      tl.fromTo(b, { autoAlpha: 0, y: 46 }, { autoAlpha: 1, y: 0, duration: f }, s);
      if (i < list.length - 1) tl.to(b, { autoAlpha: 0, y: -46, duration: f }, e - f);
    });
    extra && extra(tl);
    tl.set({}, {}, 1); // pin the timeline length to exactly 1 so positions are scroll fractions
    return tl;
  }
  beats('#story', [[0, 0.34], [0.34, 0.67], [0.67, 1]], null, [null, null, 0.97]);
  beats('#alarm', [[0, 0.24], [0.26, 0.5], [0.52, 0.76], [0.78, 1]], null, [0.18, 0.445, 0.7, 0.97]);
  beats('#flashover', [[0, 0.46], [0.52, 0.78], [0.8, 1]], (tl) => {
    $$('#flashover .fill').forEach((f, i) => tl.fromTo(f, { width: '0%' }, { width: f.dataset.w + '%', duration: 0.06 }, 0.57 + i * 0.015));
    $$('#flashover .thermo i').forEach((f, i) => tl.fromTo(f, { height: '0px' }, { height: (f.dataset.h / 100) * (innerWidth < 820 ? 100 : 130) + 'px', duration: 0.1 }, 0.84 + i * 0.03));
  }, [null, 0.7, 0.97]);
  beats('#backdraft', [[0, 0.13], [0.14, 0.26], [0.27, 0.34], [0.35, 0.52], [0.58, 0.78], [0.8, 1]], null, [null, null, null, null, null, 0.97]);
  beats('#response', [[0, 0.2], [0.22, 0.42], [0.44, 0.64], [0.66, 0.84], [0.86, 1]], null, [0.13, 0.36, 0.585, 0.78, 0.97]);

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

  /* ───────────────────────── acts: two illustrated scenes that play with the scroll ─────────────────────────
     #alarm    detectors (hall + kitchen) → cable → control panel → sirens   (Teknim addressable system)
     #response hose cabinet: cool with water first → ARIS fan: then exhaust the smoke
     Each scene is a pure function of the section's scroll progress p (0..1), so scrubbing, jumping and the self-playing film all agree.
     Everything that loops by itself (pulses on the cable, flashing sirens, water bursts) runs only while the section is on screen. */
  const lerp = (a, b, t) => a + (b - a) * t;
  const ramp = (p, a, b) => clamp((p - a) / (b - a));
  const ease = (t) => t * t * (3 - 2 * t);
  const SVGNS = 'http://www.w3.org/2000/svg';
  const mk = (name, attrs) => { const e = document.createElementNS(SVGNS, name); for (const k in attrs) e.setAttribute(k, attrs[k]); return e; };
  const mix = (c1, c2, t) => '#' + c1.map((v, i) => Math.round(lerp(v, c2[i], t)).toString(16).padStart(2, '0')).join('');

  // a two-tone alarm tone, only when the visitor has switched sound on
  let siren = null;
  function sirenSound(on) {
    const ctx = window.Howler && Howler.ctx;
    if (on && soundOn && ctx && !siren) {
      try {
        if (ctx.state === 'suspended') ctx.resume();
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.type = 'triangle'; g.gain.value = 0;
        o.connect(g); g.connect(ctx.destination); o.start();
        g.gain.linearRampToValueAtTime(0.04, ctx.currentTime + 0.3);
        let hi = false;
        const timer = setInterval(() => { hi = !hi; o.frequency.setValueAtTime(hi ? 960 : 770, ctx.currentTime); }, 480);
        siren = { o, g, timer, ctx };
      } catch (e) { siren = null; }
    } else if (!on && siren) {
      const { o, g, timer, ctx: c } = siren;
      siren = null;
      clearInterval(timer);
      try { g.gain.linearRampToValueAtTime(0, c.currentTime + 0.15); o.stop(c.currentTime + 0.2); } catch (e) { /* already stopped */ }
    }
  }

  /* flames: soft tongues of different height that sway at their own pace, a glow behind them and a few rising embers (the group is scaled by the scene) */
  function buildFlames(g, uid) {
    const svg = g.ownerSVGElement, defs = $('defs', svg);
    const grad = (id, tag, stops) => {
      const e = mk(tag, tag === 'linearGradient' ? { id: uid + '-' + id, x1: 0, y1: 1, x2: 0, y2: 0 } : { id: uid + '-' + id });
      stops.forEach(([o, c, a]) => e.append(mk('stop', { offset: o, 'stop-color': c, 'stop-opacity': a })));
      defs.append(e);
    };
    grad('gF', 'linearGradient', [[0, '#ffe7a0', 1], [0.28, '#ffb12f', 1], [0.62, '#ff6a1a', 0.95], [1, '#d4300c', 0.25]]);
    grad('gC', 'linearGradient', [[0, '#fffbe9', 1], [0.55, '#ffe488', 0.95], [1, '#ffb42f', 0]]);
    grad('gG', 'radialGradient', [[0, '#ff9a3a', 0.55], [1, '#ff6b1f', 0]]);
    g.append(mk('ellipse', { class: 'fglow', cx: 0, cy: -9, rx: 17, ry: 14, fill: 'url(#' + uid + '-gG)' }));
    const f = (n) => n.toFixed(2);
    const tongue = (w, h, l) => 'M' + f(-w / 2) + ' 0C' + f(-w * 0.62) + ' ' + f(-h * 0.28) + ' ' + f(-w * 0.3 + l * 0.2) + ' ' + f(-h * 0.5) + ' ' + f(l * 0.55) + ' ' + f(-h * 0.74)
      + 'C' + f(l * 0.8) + ' ' + f(-h * 0.86) + ' ' + f(l * 0.98) + ' ' + f(-h * 0.93) + ' ' + f(l) + ' ' + f(-h)
      + 'C' + f(l + w * 0.12) + ' ' + f(-h * 0.78) + ' ' + f(w * 0.62) + ' ' + f(-h * 0.5) + ' ' + f(w * 0.5) + ' ' + f(-h * 0.2)
      + 'C' + f(w * 0.46) + ' ' + f(-h * 0.08) + ' ' + f(w * 0.3) + ' 0 0 0C' + f(-w * 0.2) + ' 0 ' + f(-w * 0.4) + ' 0 ' + f(-w / 2) + ' 0Z';
    // [width, height, lean, x, seconds, delay, fill]
    [[6, 13, -2, -9.5, 0.7, -0.2, 'gF'], [6, 14, 2.5, 10, 0.75, -0.4, 'gF'], [9, 19, -3, -6, 0.8, -0.3, 'gF'], [9, 21, 3.5, 6.5, 0.9, -0.55, 'gF'], [13, 27, 1.5, 0, 1.0, 0, 'gF'],
      [7, 15, 1, 0, 0.85, -0.15, 'gC'], [5, 10, -2, -6, 0.65, -0.5, 'gC'], [5, 11, 2, 6.5, 0.72, -0.1, 'gC']].forEach(([w, h, l, x, d, dl, fl]) => {
      const wrap = mk('g', { transform: 'translate(' + x + ' 0)' });
      wrap.append(mk('path', { class: 'tg', d: tongue(w, h, l), fill: 'url(#' + uid + '-' + fl + ')', style: 'animation-duration:' + d + 's;animation-delay:' + dl + 's' }));
      g.append(wrap);
    });
    for (let i = 0; i < 7; i++) g.append(mk('circle', { class: 'ember', cx: -7 + i * 2.3, cy: -8 - (i % 3) * 3, r: 0.42 + (i % 2) * 0.2, fill: '#ffd27a', style: '--dx:' + ((i % 2 ? 1 : -1) * (2 + (i % 3))) + 'px;animation-delay:' + (i * 0.37).toFixed(2) + 's;animation-duration:' + (1.8 + (i % 3) * 0.5) + 's' }));
  }

  function act(sel, scene) {
    const el = $(sel);
    if (!el) return;
    let p = 0, live = false;
    const set = (self) => { p = self.progress; scene.set(p); };
    ScrollTrigger.create({ trigger: el, start: 'top top', end: 'bottom bottom', onUpdate: set, onRefresh: set });
    const loop = () => scene.tick(performance.now() / 1000, p);
    ScrollTrigger.create({
      trigger: el, start: 'top bottom', end: 'bottom top',
      onToggle: (self) => {
        el.classList.toggle('live', self.isActive);
        if (self.isActive && !live) { live = true; gsap.ticker.add(loop); }
        else if (!self.isActive && live) { live = false; gsap.ticker.remove(loop); scene.idle && scene.idle(); }
      },
    });
    scene.set(0);
  }

  /* — 1 · detectors → cable → panel → sirens — */
  act('#alarm', (() => {
    const stage = $('#alarm-stage'), plan = $('#alarm-plan');
    if (!stage) return { set() {}, tick() {} };
    const devs = $$('.dev[data-i]', plan);
    const cable = $('#al-cable'), pulseG = $('.a-pulses', plan);
    const sH = $('.a-sh', plan), hH = $('.a-hh', plan), sK = $('.a-sk', plan), hK = $('.a-hk', plan);
    const fire = $('.a-fire', plan), flash = $('.a-flash', plan);
    buildFlames(fire, 'al');
    const tempEl = $('#al-temp'), tempBar = $('#al-temp-bar');
    const lcd = $('#al-lcd'), lcdSt = $('#al-lcd-st'), lcdM = $('#al-lcd-m'), panel = $('#al-panel');
    const steps = $$('.steps li', stage);
    const PT = [[99, 16], [81, 16], [63, 16], [37, 16], [21, 16], [13, 34]]; // device centres on the plan
    const TRIP = [0.15, 0.33, 0.8, 0.385, 0.43, 0.8]; // scroll progress at which each device acts
    const LOG = [
      [0.64, 'آدرس ۰۰۱ · دتکتور دود، سالن'],
      [0.66, 'آدرس ۰۰۲ · دتکتور حرارتی، سالن'],
      [0.68, 'آدرس ۰۰۴ · دتکتور دود، آشپزخانه'],
      [0.7, 'آدرس ۰۰۵ · دتکتور حرارتی، آشپزخانه'],
    ];
    let len = 0, anchor = [];
    const pulses = []; // [halo, core] for each of the six devices
    function measure() {
      const total = cable.getTotalLength();
      anchor = PT.map(([x, y]) => { // where along the cable each device sits
        let best = 0, bd = 1e9;
        for (let i = 0; i <= 480; i++) { const q = cable.getPointAtLength((total * i) / 480), d = (q.x - x) ** 2 + (q.y - y) ** 2; if (d < bd) { bd = d; best = (total * i) / 480; } }
        return best;
      });
      PT.forEach(() => {
        const halo = mk('circle', { r: 2.6, fill: '#27e6ff', opacity: 0 }), core = mk('circle', { r: 1.1, fill: '#e8fbff', opacity: 0 });
        pulseG.append(halo, core);
        pulses.push([halo, core]);
      });
      len = total;
    }
    const state = { p: 0 };
    return {
      set(p) {
        state.p = p;
        if (!len) { try { measure(); } catch (e) { return; } }
        // smoke and heat gather under the ceiling; the kitchen follows through the doorway
        const hall = 3 + 33 * ease(ramp(p, 0, 0.42)), kit = 2 + 28 * ease(ramp(p, 0.24, 0.52));
        sH.setAttribute('height', hall.toFixed(2)); hH.setAttribute('height', hall.toFixed(2));
        sK.setAttribute('height', kit.toFixed(2)); hK.setAttribute('height', kit.toFixed(2));
        hH.setAttribute('opacity', (0.9 * ramp(p, 0.14, 0.5)).toFixed(2));
        hK.setAttribute('opacity', (0.8 * ramp(p, 0.3, 0.54)).toFixed(2));
        fire.setAttribute('transform', 'translate(101 69) scale(' + lerp(0.35, 1.12, ease(ramp(p, 0, 0.5))).toFixed(3) + ')');
        const T = 24 + 50 * ramp(p, 0.04, 0.46);
        tempEl.textContent = fa(T) + '°C';
        tempBar.style.width = (((T - 20) / 80) * 100).toFixed(1) + '%';
        // the six devices
        devs.forEach((d, i) => {
          const on = p >= TRIP[i];
          if (d.classList.contains('on') !== on) {
            d.classList.toggle('on', on);
            const st = $('.dev-st', d);
            if (st) st.textContent = on ? (d.dataset.t === 'a' ? 'روشن شد' : 'فعال شد') : (d.dataset.t === 'a' ? 'آماده' : 'عادی');
          }
        });
        // the cable is drawn after the detectors have gone off
        const draw = ramp(p, 0.53, 0.61);
        cable.style.strokeDashoffset = (1 - ease(draw)).toFixed(4);
        cable.style.opacity = draw > 0 ? 1 : 0;
        // the panel reads the loop
        const arrived = p >= 0.64, alarm = arrived;
        panel.classList.toggle('on', alarm);
        lcd.classList.toggle('alarm', alarm);
        let msg = 'یک لوپ، تا ۲۴۰ دستگاه آدرس‌پذیر', st = 'آماده به‌کار';
        if (p >= 0.53 && !alarm) msg = 'لوپ ۱ · ۶ دستگاه، هرکدام یک آدرس';
        if (alarm) {
          const seen = LOG.filter(([t]) => p >= t);
          st = p >= 0.8 ? 'حریق · آژیرها روشن' : 'حریق';
          msg = p >= 0.8 ? 'آژیرِ ۰۰۳ و ۰۰۶ روشن شد' : (seen.length ? seen[seen.length - 1][1] : 'سیگنال از لوپ رسید…');
        }
        if (lcdSt.textContent !== st) lcdSt.textContent = st;
        if (lcdM.textContent !== msg) lcdM.textContent = msg;
        stage.classList.toggle('sirens', p >= 0.8);
        sirenSound(p >= 0.8);
        // the row of steps above the plan
        const cur = p < 0.24 ? 0 : p < 0.5 ? 1 : p < 0.78 ? 2 : 3;
        steps.forEach((li, i) => { li.classList.toggle('on', i === cur); li.classList.toggle('done', i < cur); });
      },
      tick(now) {
        if (!len) return;
        const p = state.p;
        // signals travel from each tripped detector to the nearest end of the loop (the panel); later the panel answers the sirens
        pulses.forEach(([halo, core], i) => {
          const isSiren = i === 2 || i === 5;
          let u = -1, from = anchor[i], to = 0;
          if (!isSiren && p >= 0.6 && p >= TRIP[i]) { to = i < 3 ? 0 : len; u = (now * 0.5 + i * 0.37) % 1; }
          else if (isSiren && p >= 0.74) { from = i === 2 ? 0 : len; to = anchor[i]; u = (now * 0.6 + i * 0.3) % 1; }
          if (u < 0) { halo.setAttribute('opacity', 0); core.setAttribute('opacity', 0); return; }
          const pt = cable.getPointAtLength(from + (to - from) * u), a = Math.sin(Math.PI * u);
          halo.setAttribute('cx', pt.x.toFixed(2)); halo.setAttribute('cy', pt.y.toFixed(2)); halo.setAttribute('opacity', (0.3 * a).toFixed(2));
          core.setAttribute('cx', pt.x.toFixed(2)); core.setAttribute('cy', pt.y.toFixed(2)); core.setAttribute('opacity', a.toFixed(2));
        });
      },
      idle() {},
    };
  })());

  /* — 2 · hose cabinet (water first) → ARIS fan (then the common area is cleared of smoke) — */
  act('#response', (() => {
    const stage = $('#response-stage'), plan = $('#resp-plan');
    if (!stage) return { set() {}, tick() {} };
    const sm = $('.r-sm', plan), ht = $('.r-ht', plan), gasG = $('.r-gas', plan), cs = $('.r-cs', plan);
    const door = $('.r-door', plan), doorBody = $('.door-body', plan), doorGlow = $('.door-glow', plan);
    const fire = $('.r-fire', plan), hoses = $$('.r-hose-edge, .r-hose, .r-hose-hl', plan), nozzle = $('.r-nozzle', plan), water = $('.r-water', plan);
    const steam = $('.r-steam', plan), outG = $('.r-out', plan), smokeOut = $('.r-smoke-out', plan);
    const cab = $('#rs-cab'), cabClosed = $('.cab-closed', cab), cabOpen = $('.cab-open', cab), cabT = $('#rs-cab-t');
    const fan = $('#rs-fan');
    const tempEl = $('#rs-temp'), tempBar = $('#rs-temp-bar'), gasEl = $('#rs-gas'), gasBar = $('#rs-gas-bar');
    const steps = $$('.steps li', stage);
    buildFlames(fire, 'rs');
    // unburnt gas in the hot layer of the room: little amber dots
    let seed = 7;
    const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
    const dots = Array.from({ length: 26 }, (_, i) => {
      const c = mk('circle', { cx: (5 + rnd() * 43).toFixed(1), cy: (10 + rnd() * 24).toFixed(1), r: (0.35 + rnd() * 0.4).toFixed(2), fill: '#ffb454', opacity: 0 });
      gasG.append(c);
      return { c, k: (i + 0.5) / 26 };
    });
    for (let i = 0; i < 7; i++) steam.append(mk('circle', { class: 'puff', cx: 56 + (i % 3) * 1.6, cy: 46 - (i % 4) * 3.2, r: 1.5, fill: '#dff6ff', style: 'animation-delay:' + (i * 0.27).toFixed(2) + 's' }));
    for (let i = 0; i < 8; i++) smokeOut.append(mk('circle', { class: 'drift', cx: 118 + (i % 4) * 1.6, cy: 13 + (i % 3) * 3.6, r: 1.8 + (i % 3) * 0.5, fill: '#aeb4c2', style: 'animation-delay:' + (i * 0.4).toFixed(2) + 's' }));
    // smoke in the common area: soft grey puffs that drift, and stream toward the fan once it runs
    const cp = $('.r-cp', plan);
    const puffs = Array.from({ length: 16 }, (_, i) => {
      const c = mk('circle', { class: 'cpuff', cx: (57 + rnd() * 44).toFixed(1), cy: (10 + rnd() * 15).toFixed(1), r: (3 + rnd() * 3).toFixed(1), fill: '#b9bfcc', opacity: 0, style: 'animation-delay:' + (-rnd() * 4).toFixed(2) + 's' });
      cp.append(c);
      return { c, k: (i + 0.5) / 16 };
    });
    const state = { p: 0, jet: 0 };
    return {
      set(p) {
        state.p = p;
        const cool = ease(ramp(p, 0.46, 0.62)), clear = ease(ramp(p, 0.72, 0.94));
        const hot = 1 - 0.88 * cool - 0.04 * clear;
        // the room's hot layer stays deep and orange until water arrives, then turns grey and thins once a flow path to the outside exists
        const depth = lerp(lerp(34, 27, cool), 15, clear);
        sm.setAttribute('height', depth.toFixed(2)); ht.setAttribute('height', depth.toFixed(2));
        ht.setAttribute('opacity', clamp(hot * 1.05).toFixed(2));
        fire.setAttribute('transform', 'translate(23 69) scale(' + (1.1 - 0.62 * cool - 0.14 * clear).toFixed(3) + ')');
        const T = 60 + 390 * hot;
        tempEl.textContent = fa(Math.round(T / 10) * 10) + '°C';
        tempBar.style.width = (hot * 100).toFixed(1) + '%';
        dots.forEach((d) => d.c.setAttribute('opacity', d.k < 1 - 0.5 * clear ? 0.85 : 0));
        // the common area: a little smoke seeps around the door, a lot when it is opened a hand's width; the fan then clears it
        const leak = 3 + 5 * ramp(p, 0, 0.4) + 17 * ease(ramp(p, 0.6, 0.72));
        const cdepth = lerp(leak, 2, clear);
        cs.setAttribute('height', cdepth.toFixed(2));
        const sf = clamp((cdepth - 2) / 22);
        gasEl.textContent = sf > 0.62 ? 'زیاد' : sf > 0.22 ? 'متوسط' : 'کم';
        gasBar.style.width = (sf * 100).toFixed(1) + '%';
        puffs.forEach((q) => q.c.setAttribute('opacity', q.k < sf ? 0.34 : 0));
        stage.classList.toggle('fan-run', p >= 0.69 && p < 0.96);
        doorBody.style.fill = mix([122, 42, 20], [26, 34, 54], 1 - hot);
        doorGlow.style.opacity = (hot * 0.85).toFixed(2);
        door.setAttribute('transform', 'translate(' + (2.4 * ease(ramp(p, 0.6, 0.66))).toFixed(2) + ' 0)');
        // the cabinet on the corridor wall opens; the hose is pulled off the reel
        const open = ease(ramp(p, 0.22, 0.3));
        cabOpen.style.opacity = open.toFixed(2); cabClosed.style.opacity = (1 - open).toFixed(2);
        const t = p < 0.26 ? 'روی دیوار، پشتِ دربِ خودش' : 'درِ جعبه باز شد ✓';
        if (cabT.textContent !== t) cabT.textContent = t;
        const pull = (1 - ease(ramp(p, 0.28, 0.38))).toFixed(3);
        hoses.forEach((h) => { h.style.strokeDashoffset = pull; h.style.opacity = p > 0.27 ? 1 : 0; });
        nozzle.style.opacity = ramp(p, 0.34, 0.38).toFixed(2);
        state.jet = ramp(p, 0.44, 0.46) * (1 - ramp(p, 0.62, 0.64));
        steam.style.opacity = (state.jet * 0.9).toFixed(2);
        // the fan at the end of the corridor pulls the smoke out through the opening
        const fanOn = ease(ramp(p, 0.66, 0.72));
        fan.style.opacity = fanOn.toFixed(2);
        fan.style.transform = 'translate(' + ((1 - fanOn) * 10).toFixed(1) + 'px,0)';
        outG.style.opacity = (ease(ramp(p, 0.7, 0.76)) * (1 - ramp(p, 0.95, 1))).toFixed(2);
        smokeOut.style.opacity = (fanOn * (1 - ease(ramp(p, 0.9, 0.97)))).toFixed(2);
        const cur = p < 0.22 ? 0 : p < 0.62 ? 1 : p < 0.7 ? 2 : 3;
        steps.forEach((li, i) => { li.classList.toggle('on', i === cur && p < 0.97); li.classList.toggle('done', i < cur || p >= 0.97); });
      },
      tick(now) {
        // water goes on in short bursts, not as one long stream
        water.style.opacity = (state.jet * (Math.floor(now * 2.2) % 3 < 2 ? 1 : 0.18)).toFixed(2);
      },
      idle() { water.style.opacity = 0; },
    };
  })());

  /* ───────────────────────── per-frame UI sync (HUD, nav, dots, sounds) ───────────────────────── */
  const hudRoom = $('#hud-room'), hudBd = $('#hud-bd');
  const elStage = $('#hud-stage'), elTemp = $('#hud-temp'), elTempBar = $('#hud-temp-bar'), elO2 = $('#hud-o2'), elO2Bar = $('#hud-o2-bar');
  let lastStage = '', lastStep = -1;

  const syncUI = () => {
    const b = BS();
    if (!b.s) return;
    const T = b.T || 0;

    const inAct = root.classList.contains('in-act');
    hudRoom.classList.toggle('on', !inAct && T > 0.72 && T < 2.72);
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
    hudBd.classList.toggle('on', !inAct && T > 2.72 && T < 3.55 && P3 > 0.01);
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
  const CHAPTERS = ['شروع', 'آتش در اتاق', 'دتکتور و آژیر', 'فلش‌اور', 'بک‌درفت', 'اول آب، بعد در', 'نشانه‌های خطر', 'یافته‌ها', 'نمونه کارها', 'تماس با ما', 'متن مقاله'];
  const chEls = [...$$('main > .chapter, main > .act'), $('#article')].filter(Boolean);
  const chIndex = (id) => chEls.findIndex((el) => el.id === id);
  const CH = { features: chIndex('features'), research: chIndex('research'), portfolio: chIndex('portfolio'), contact: chIndex('contact') }; // the "story" chapters are everything before `features`
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
    paintPlayer();
    if (!galleryOn && tops[CH.portfolio] && y + innerHeight * 3 > tops[CH.portfolio]) enableGallery();
    const ch = currentChapter();
    if (ch === lastCh) return;
    if (lastCh !== -1) play('section');
    lastCh = ch;
    root.classList.toggle('has-player', ch <= CH.portfolio);
    root.classList.toggle('in-act', !!chEls[ch] && chEls[ch].classList.contains('act'));
    paintPlayer();
    whereN.textContent = fa(ch + 1);
    whereT.textContent = CHAPTERS[ch] || '';
    dots.forEach((a) => a.classList.toggle('on', +a.dataset.go === ch));
    navs.forEach((a) => {
      const g = a.dataset.go;
      a.classList.toggle('on', g != null && (+g === ch || (+g === 1 && ch >= 1 && ch < CH.features)));
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
    apPause();
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
    [CH.features]: { key: 'signs', t: 'روی دایره‌های شماره‌دار روی پنجره بزنید' },
    [CH.research]: { key: 'cards', t: touch ? 'روی کارت بزنید تا نقطه‌اش در صحنه روشن شود؛ کارت‌ها را به چپ بکشید' : 'نشانگر را روی هر کارت ببرید تا نقطه‌ی هم‌رنگش روشن شود' },
    [CH.portfolio]: { key: 'products', t: touch ? 'محصول را با انگشت بکشید تا بچرخد؛ با فلش‌ها محصول بعدی را ببینید' : 'محصول را بکشید تا بچرخد؛ با فلش‌ها محصول بعدی را ببینید' },
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
    unframe();
    hintCh = -1; hintKey = null;
  }
  function unframe() { $$('.focus-frame').forEach((el) => el.classList.remove('focus-frame')); }
  function markUsed(key) {
    if (used[key]) return;
    used[key] = 1;
    store('bsma-used', JSON.stringify(used));
    if (hintKey === key) hideHint();
  }
  const tried = () => unframe();
  // o: { ch, key, sticky (stay until dismissed), go (label of a "continue" button) }
  function showHint(text, o = {}) {
    clearTimeout(hintTimer);
    hintCh = o.ch ?? -1; hintKey = o.key || null;
    unframe();
    (o.frame || []).forEach((sel) => $$(sel).forEach((el) => el.classList.add('focus-frame')));
    hintT.textContent = text;
    hintGo.hidden = !o.go;
    if (o.go) hintGo.textContent = o.go;
    hintEl.hidden = false;
    requestAnimationFrame(() => requestAnimationFrame(() => hintEl.classList.add('show')));
    if (o.ch === CH.features) { const first = $('.hs'); first && first.classList.add('nudge'); }
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
  $$('.hs, .sign-list button').forEach((b) => b.addEventListener('click', () => { markUsed('signs'); tried(); }));
  $$('.card').forEach((c) => c.addEventListener('click', () => { markUsed('cards'); tried(); }));
  ['#product-stage', '#p-next', '#p-prev', '#view-toggle', '#p-dots'].forEach((sel) => $(sel).addEventListener('pointerdown', () => { markUsed('products'); tried(); }));

  /* ───────────────────────── story mode: the page plays itself like a film ─────────────────────────
     It scrolls to each card, then lights up the words one by one at reading pace (like karaoke) so the visitor can see where to look
     and that the film is running; when the card is done it moves on. A swipe, the wheel or a scroll key hands control back.
     At the three interactive chapters it stops and asks the visitor to try things, then continues when they press "continue". */
  const playBtn = $('#play');
  const AP = { on: false, wp: [], i: 0, phase: 'idle', t0: 0, y0: 0, y1: 0, dur: 0, from: 0, until: 0, raf: 0, ver: 0, cur: null, speed: 1 };
  const SPEEDS = [0.75, 1, 1.5, 2];
  { const sv = parseFloat(recall('bsma-speed')); if (SPEEDS.includes(sv)) AP.speed = sv; }
  const STOPS = [
    ['#features', 'نوبت شماست: روی دایره‌های شماره‌دار بزنید و نشانه‌ها را بخوانید. بعد «ادامه» را بزنید.', CH.features, ['#signs .sign-list']],
    ['#research', touch ? 'نوبت شماست: کارت‌ها را به چپ بکشید و روی هر کدام بزنید تا نقطه‌اش در صحنه روشن شود. بعد «ادامه» را بزنید.' : 'نوبت شماست: روی کارت‌ها بزنید تا نقطه‌ی هم‌رنگشان در صحنه روشن شود. بعد «ادامه» را بزنید.', CH.research, ['.research-panel .cards']],
    ['#portfolio', 'نوبت شماست: محصولات را ببینید و بچرخانید. بعد «ادامه» را بزنید.', CH.portfolio, ['#photo-card', '.p-controls']],
  ];
  const words = (el) => (el.textContent.match(/\S+/g) || []).length;
  function buildWaypoints() {
    const wp = [];
    const at = (el, f, extra = 0) => () => { const r = el.getBoundingClientRect(); return r.top + scrollY + f * Math.max(0, r.height - innerHeight) + extra; };
    STORY.forEach(({ el, ranges, list, focus }) => list.forEach((b, i) => {
      const [s0, e0] = ranges[i];
      let f = focus[i] != null ? focus[i] : (s0 + e0) / 2;
      if (e0 - s0 >= 0.12 && i < list.length - 1) f = clamp(f, s0 + 0.055, e0 - 0.055); // inside the fully visible stretch (the fades take 0.035 at each end)
      wp.push({ at: at(el, f), beat: b, dwell: clamp(2500 + words(b) * 540, 6000, 30000) }); // ≈ 110 words a minute: a relaxed screen-reading pace
    }));
    STOPS.forEach(([sel, msg, ch, frame]) => wp.push({ at: at($(sel), 0.5), stop: true, msg, ch, frame, go: 'ادامه ▶' }));
    wp.push({ at: at($('#contact'), 0, 40), stop: true, end: true, ch: CH.contact, msg: 'پایان داستان. هر سؤالی دارید، از همین‌جا با ما تماس بگیرید.', go: 'از اول ▶' });
    wp.forEach((w) => { w._v = -1; });
    return wp.sort((a, b) => a.at() - b.at());
  }
  // positions are measured lazily and re-measured after any resize (phones resize whenever their toolbars slide in or out)
  const yOf = (w) => { if (w._v !== AP.ver) { w._y = w.at(); w._v = AP.ver; } return w._y; };
  addEventListener('resize', () => { AP.ver++; });

  /* reading highlight: every word of the card is wrapped once (a table card is lit row by row) */
  function wrapWords(card) {
    if (card._units) return card._units;
    const units = [];
    const walk = (node) => [...node.childNodes].forEach((c) => {
      if (c.nodeType === 3) {
        if (!c.nodeValue.trim()) return;
        const frag = document.createDocumentFragment();
        c.nodeValue.split(/(\s+)/).forEach((t) => {
          if (!t) return;
          if (/^\s+$/.test(t)) { frag.append(t); return; }
          const sp = document.createElement('span');
          sp.className = 'w'; sp.textContent = t;
          frag.append(sp); units.push(sp);
        });
        c.replaceWith(frag);
      } else if (c.nodeType === 1 && !c.matches('svg, script, style, .src, .nextline, .step')) walk(c);
    });
    const rows = $$('tbody tr', card);
    if (rows.length) units.push(...rows);
    else $$('p:not(.tag):not(.nextline):not(.src), li', card).forEach(walk);
    card._units = units;
    return units;
  }
  function apFocus(card) {
    if (!card) return;
    if (AP.cur && AP.cur.card === card) return;
    apUnfocus();
    if (!card._frame) {
      card._frame = document.createElement('i');
      card._frame.className = 'read-frame';
      card._frame.setAttribute('aria-hidden', 'true');
      card.append(card._frame);
    }
    AP.cur = { card, units: wrapWords(card), shown: -1, frame: card._frame, lineTop: -1e9 };
    card.classList.add('reading');
  }
  /* the frame hugs the line that is being read (or the table row) and glides to the next one */
  function moveFrame(c, idx) {
    const u = c.units[idx];
    if (!u) return;
    const first = u.getBoundingClientRect();
    if (Math.abs(first.top - c.lineTop) < 3) return; // still on the same line
    c.lineTop = first.top;
    let l = first.left, r = first.right;
    if (u.tagName !== 'TR') {
      const same = (q) => Math.abs(q.top - first.top) < Math.max(6, first.height * 0.45);
      for (let k = idx - 1; k >= 0; k--) { const q = c.units[k].getBoundingClientRect(); if (!same(q)) break; l = Math.min(l, q.left); r = Math.max(r, q.right); }
      for (let k = idx + 1; k < c.units.length; k++) { const q = c.units[k].getBoundingClientRect(); if (!same(q)) break; l = Math.min(l, q.left); r = Math.max(r, q.right); }
    }
    const base = c.card.getBoundingClientRect(), px = 8, py = 3;
    const f = c.frame;
    f.style.transform = 'translate(' + (l - base.left - c.card.clientLeft + c.card.scrollLeft - px) + 'px,' + (first.top - base.top - c.card.clientTop + c.card.scrollTop - py) + 'px)';
    f.style.width = (r - l + px * 2) + 'px';
    f.style.height = (first.height + py * 2) + 'px';
  }
  function apUnfocus() {
    if (!AP.cur) return;
    const { card, units } = AP.cur;
    card.classList.remove('reading');
    units.forEach((u) => u.classList.remove('r', 'cur'));
    card.scrollTop = 0;
    if (card._frame) { card._frame.style.width = card._frame.style.height = '0px'; }
    AP.cur = null;
  }
  function apRead(frac) {
    const c = AP.cur;
    if (!c || !c.units.length) return;
    const n = c.units.length;
    const idx = Math.min(n - 1, Math.floor(clamp(frac / 0.9) * n));
    if (idx !== c.shown) {
      for (let k = c.shown + 1; k <= idx; k++) c.units[k].classList.add('r');
      if (c.shown >= 0) c.units[c.shown].classList.remove('cur');
      c.units[idx].classList.add('cur');
      c.shown = idx;
      moveFrame(c, idx);
    }
    const over = c.card.scrollHeight - c.card.clientHeight; // a tall card on a small screen scrolls along with the reading
    if (over > 4) c.card.scrollTop = over * clamp((frac - 0.08) / 0.84); // (the frame lives inside the card, so it scrolls with the text)
  }

  const setPlayUI = (on) => {
    playBtn.setAttribute('aria-pressed', String(on));
    playBtn.setAttribute('aria-label', on ? 'توقف پخش خودکار' : 'پخش خودکار داستان');
    root.classList.toggle('playing', on);
    if (on) moved = true;
    paintPlayer();
  };
  function apPause() {
    if (!AP.on) return;
    AP.on = false;
    cancelAnimationFrame(AP.raf);
    apUnfocus();
    setPlayUI(false);
  }
  function apMoveTo(i, now) {
    AP.i = i;
    const w = AP.wp[i];
    AP.y0 = scrollY; AP.y1 = yOf(w);
    const dist = Math.abs(AP.y1 - AP.y0);
    AP.dur = clamp((dist / (340 * AP.speed)) * 1000, 1400, 9000);
    AP.t0 = now;
    apUnfocus();
    if (dist < 6) apArrive(now, w, w.stop ? 600 : null); // already there: read the card from its start
    else AP.phase = 'move';
  }
  function apArrive(now, w, dwell) {
    AP.phase = 'dwell';
    AP.from = now;
    AP.until = now + (dwell != null ? dwell : w.dwell / AP.speed);
    if (w.beat) apFocus(w.beat);
  }
  function apFrame(now) {
    if (!AP.on) return;
    AP.raf = requestAnimationFrame(apFrame);
    const w = AP.wp[AP.i];
    if (AP.phase === 'move') {
      const k = clamp((now - AP.t0) / AP.dur);
      const e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
      // the target is re-read every frame: if the layout moved (a phone's toolbar, a font arriving) the film simply follows it
      AP.y1 = yOf(w);
      scrollTo(0, AP.y0 + (AP.y1 - AP.y0) * e);
      if (k >= 1) {
        if (w.stop) { apPause(); showHint(w.msg, { ch: w.ch, sticky: true, go: w.go, frame: w.frame }); return; }
        apArrive(now, w);
      }
    } else if (now >= AP.until) {
      if (AP.i + 1 >= AP.wp.length) { apPause(); return; }
      apMoveTo(AP.i + 1, now);
    } else {
      apRead((now - AP.from) / (AP.until - AP.from));
    }
  }
  const ensureWp = () => (AP.wp.length ? AP.wp : (AP.wp = buildWaypoints()));
  function apPlay() {
    if (AP.on) return;
    ensureWp();
    AP.ver++;
    hideHint();
    // start with the card the visitor is looking at (not the next one); a "your turn" stop that was just handled is skipped
    let i = AP.wp.findIndex((w) => yOf(w) >= scrollY - 40);
    if (i >= 0 && AP.wp[i].stop && Math.abs(yOf(AP.wp[i]) - scrollY) < 80) i++;
    if (i < 0 || i >= AP.wp.length) { scrollTo(0, 0); i = 0; } // finished before: start again
    AP.on = true;
    setPlayUI(true);
    const now = performance.now();
    apMoveTo(i, now);
    AP.raf = requestAnimationFrame(apFrame);
  }
  playBtn.addEventListener('click', () => (AP.on ? apPause() : apPlay()));
  $('#play-hero').addEventListener('click', apPlay);
  hintGo.addEventListener('click', apPlay);

  /* The visitor takes over by scrolling (wheel, a swipe of more than a few pixels, a scroll key, the scrollbar) or by opening a menu.
     A plain tap does not stop the film, and the page's own position is never used as a signal: a phone can nudge it by itself. */
  let touchY = 0;
  const onTouchStart = (e) => { touchY = e.touches && e.touches[0] ? e.touches[0].clientY : 0; };
  const onTouchMove = (e) => {
    if (!AP.on || !e.touches || !e.touches[0]) return;
    if (e.target.closest && e.target.closest('.player')) return;
    if (Math.abs(e.touches[0].clientY - touchY) > 14) apPause();
  };
  addEventListener('touchstart', onTouchStart, { passive: true });
  addEventListener('touchmove', (e) => { if (!(e.target.closest && e.target.closest('.player'))) stopTween(); onTouchMove(e); }, { passive: true });
  addEventListener('wheel', () => { stopTween(); AP.on && apPause(); }, { passive: true });
  addEventListener('keydown', (e) => {
    if (!AP.on || !['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(e.key)) return;
    if (e.target.closest && e.target.closest('button, a, input, textarea, select, [role="slider"]')) return; // those keys belong to the control
    apPause();
  });
  addEventListener('mousedown', (e) => { if (AP.on && e.target === document.documentElement) apPause(); }); // the scrollbar itself
  document.addEventListener('click', (e) => { if (AP.on && e.target.closest && e.target.closest('a[href^="#"]:not(#play-hero)')) apPause(); });


  /* ───────────────────────── the player bar ─────────────────────────
     Play / pause, previous / next card, a timeline that can be dragged, and a speed button, like a video player. */
  const player = $('#player'), plText = $('#pl-text'), plTrack = $('#pl-track'), plFill = $('#pl-fill'), plKnob = $('#pl-knob'), plSpeed = $('#pl-speed');
  let moved = false, tw = 0;
  const fullY = () => Math.max(1, artTop - innerHeight);
  const fnum = (n) => n.toLocaleString('fa-IR');
  function stopTween() { cancelAnimationFrame(tw); }
  function tweenScroll(y1, ms, done) {
    stopTween();
    const y0 = scrollY, t0 = performance.now();
    const step = (now) => {
      const k = clamp((now - t0) / ms);
      scrollTo(0, y0 + (y1 - y0) * (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2));
      if (k < 1) tw = requestAnimationFrame(step); else done && done();
    };
    tw = requestAnimationFrame(step);
  }
  function paintPlayer() {
    const f = clamp(scrollY / fullY());
    if (scrollY > 30) moved = true;
    plFill.style.transform = 'scaleX(' + f.toFixed(4) + ')';
    plKnob.style.right = (f * 100).toFixed(2) + '%';
    plTrack.setAttribute('aria-valuenow', String(Math.round(f * 100)));
    const idle = !moved && !AP.on;
    player.classList.toggle('pl-idle', idle);
    if (idle) { plText.textContent = touch ? '▶ را بزنید، یا انگشتتان را به بالا بکشید' : '▶ را بزنید تا داستان خودش پخش شود، یا اسکرول کنید'; return; }
    const ch = Math.max(0, lastCh);
    const wp = ensureWp(), narrative = wp.filter((w) => w.beat).length;
    let k = -1;
    wp.forEach((w, i) => { if (yOf(w) <= scrollY + 40) k = i; });
    plText.textContent = CHAPTERS[ch] + (k >= 0 && wp[k].beat && ch >= 1 && ch < CH.features ? ' · کارت ' + fnum(k + 1) + ' از ' + fnum(narrative) : '');
  }
  function arrived(w) { if (w && w.stop) showHint(w.msg, { ch: w.ch, sticky: true, go: w.go, frame: w.frame }); }
  function seekCard(dir) {
    const wp = ensureWp(), y = scrollY;
    let target;
    if (dir > 0) { target = wp.findIndex((w) => yOf(w) > y + 40); if (target < 0) target = wp.length - 1; }
    else { target = -1; wp.forEach((w, i) => { if (yOf(w) < y - 40) target = i; }); if (target < 0) target = 0; }
    hideHint();
    if (AP.on) { apUnfocus(); apMoveTo(target, performance.now()); return; }
    tweenScroll(yOf(wp[target]), clamp(Math.abs(yOf(wp[target]) - y) / 1.6, 500, 1400), () => arrived(wp[target]));
  }
  $('#pl-prev').addEventListener('click', () => seekCard(-1));
  $('#pl-next').addEventListener('click', () => seekCard(1));

  // dragging the timeline
  let scrubbing = false, wasPlaying = false;
  const fracAt = (e) => { const r = plTrack.getBoundingClientRect(); return clamp((r.right - e.clientX) / Math.max(1, r.width)); }; // the page is right-to-left
  plTrack.addEventListener('pointerdown', (e) => {
    scrubbing = true; wasPlaying = AP.on;
    apPause(); stopTween(); hideHint();
    try { plTrack.setPointerCapture(e.pointerId); } catch (err) { /* older browsers */ }
    scrollTo(0, fracAt(e) * fullY());
    e.preventDefault();
  });
  plTrack.addEventListener('pointermove', (e) => { if (scrubbing) scrollTo(0, fracAt(e) * fullY()); });
  const endScrub = () => { if (!scrubbing) return; scrubbing = false; if (wasPlaying) apPlay(); };
  plTrack.addEventListener('pointerup', endScrub);
  plTrack.addEventListener('pointercancel', endScrub);
  plTrack.addEventListener('keydown', (e) => {
    const k = e.key;
    if (k === 'ArrowLeft' || k === 'ArrowUp') { e.preventDefault(); seekCard(1); }
    else if (k === 'ArrowRight' || k === 'ArrowDown') { e.preventDefault(); seekCard(-1); }
    else if (k === 'Home') { e.preventDefault(); apPause(); tweenScroll(0, 900); }
    else if (k === 'End') { e.preventDefault(); apPause(); tweenScroll(fullY(), 1400); }
  });

  // speed: 0.75× / 1× / 1.5× / 2× (1× is a relaxed reading pace)
  const paintSpeed = () => { plSpeed.textContent = fnum(AP.speed) + '×'; plSpeed.setAttribute('aria-label', 'سرعت پخش: ' + fnum(AP.speed) + ' برابر. برای تغییر بزنید'); };
  plSpeed.addEventListener('click', () => {
    const old = AP.speed;
    AP.speed = SPEEDS[(SPEEDS.indexOf(old) + 1) % SPEEDS.length];
    store('bsma-speed', String(AP.speed));
    if (AP.on && AP.phase === 'dwell') { // keep the same share of the card already read
      const now = performance.now(), w = AP.wp[AP.i], total = w.dwell / AP.speed;
      const done = clamp((now - AP.from) / Math.max(1, AP.until - AP.from));
      AP.from = now - total * done; AP.until = AP.from + total;
    }
    paintSpeed();
  });
  paintSpeed();

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
    $('#sign-text').textContent = s ? s.d : 'روی یکی از شماره‌ها بزنید؛ هم در تصویر، هم همین‌جا.';
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
