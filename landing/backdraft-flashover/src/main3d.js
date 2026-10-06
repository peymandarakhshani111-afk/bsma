import * as THREE from 'three';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/examples/jsm/postprocessing/OutputPass.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { C, clamp, lerp, smooth, applyDensity } from './util.js';
import { createHero } from './stations/hero.js';
import { createRoom } from './stations/room.js';
import { createBackdraft } from './stations/backdraft.js';
import { createSigns } from './stations/signs.js';
import { createScience } from './stations/science.js';
import { createProducts } from './stations/products.js';
import { createContact } from './stations/contact.js';

/* One fixed WebGL canvas. The page is a camera ride through seven "stations",
   each station owns the 3D set-piece of one or two chapters.
   Scroll → (P[i], T) → camera + station state. GSAP only animates the HTML. */

const STZ = 80; // distance between stations along -z
const CH_ST = [0, 1, 1, 2, 3, 4, 5, 6]; // chapter → station
const CAMS = [
  { p: [0, 1.6, 13], l: [0, 1.55, 0], fov: 40, shift: 0, mob: 1.0 }, //          0 hero
  { p: [0.3, 2.5, 16.5], l: [-0.2, 1.7, -0.2], fov: 38, shift: 0.19, mob: 2.0, my: 0.09 }, //  1 growth
  { p: [-1.2, 1.9, 7.8], l: [0.2, 1.7, -1.4], fov: 50, shift: 0.19, mob: 1.8, my: 0.09 }, // 2 flashover
  { p: [0, 1.5, 9.8], l: [0, 1.25, 0], fov: 38, shift: 0.13, mob: 1.5 }, //       3 backdraft
  { p: [0, 1.8, 6.8], l: [0, 1.6, 0], fov: 36, shift: 0.15, mob: 1.9 }, //        4 signs
  { p: [0, 0.6, 11], l: [0, 0, 0], fov: 40, shift: 0.15, mob: 1.5 }, //           5 research
  { p: [0, 1.9, 7.2], l: [0, 0.95, 0], fov: 36, shift: 0.13, mob: 1.9 }, //       6 portfolio
  { p: [0, 1.6, 8.4], l: [0, 1.4, 0], fov: 40, shift: 0.15, mob: 1.3 }, //        7 contact
];
const LEVELS = [
  { pr: 2, dens: 1, bloom: true, name: 'high' },
  { pr: 1.5, dens: 0.7, bloom: true, name: 'mid' },
  { pr: 1.25, dens: 0.55, bloom: true, name: 'low' },
  { pr: 1, dens: 0.4, bloom: false, name: 'min' }, // also renders at 30 fps
];

/* js/main.js (the UI layer) may already have created window.BSMA with its `ui` and `cue`; adopt those instead of replacing them */
const pre = window.BSMA || {};
const BSMA = (window.BSMA = Object.assign(pre, {
  P: new Array(CAMS.length).fill(0),
  ui: Object.assign({ sign: -1, node: -1, product: 0, dragging: false, dragVel: 0, dragRot: 0 }, pre.ui),
  cue: pre.cue || null,
  ready: false,
  failed: false,
  level: 0,
  marks: {},
}));
const mark = (n) => (BSMA.marks[n] = Math.round(performance.now()));

async function start() {
  mark('start');
  const canvas = document.getElementById('gl');
  const flashEl = document.getElementById('flash');
  const params = new URLSearchParams(location.search);
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const coarse = matchMedia('(pointer: coarse)').matches;

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: false, powerPreference: 'high-performance', alpha: false });
  } catch (e) {
    BSMA.failed = true;
    BSMA.ready = true;
    document.documentElement.classList.add('no-webgl');
    return;
  }
  renderer.setClearColor(C.bg, 1);
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.85;

  const scene = new THREE.Scene();
  scene.background = new THREE.Color(C.bg);
  scene.fog = new THREE.FogExp2(C.bg, 0.014);

  const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 400);

  mark('renderer');
  /* image-based light only for the product models: built the first time that station is created */
  let envTex = null;
  const getEnv = () => {
    if (!envTex) {
      const pmrem = new THREE.PMREMGenerator(renderer);
      envTex = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
      pmrem.dispose();
    }
    return envTex;
  };

  const composer = new EffectComposer(
    renderer,
    new THREE.WebGLRenderTarget(4, 4, { type: THREE.HalfFloatType, samples: coarse || params.get('msaa') === '0' ? 0 : 4 })
  );
  composer.addPass(new RenderPass(scene, camera));
  const bloom = new UnrealBloomPass(new THREE.Vector2(512, 512), 0.42, 0.55, 1.0);
  composer.addPass(bloom);
  composer.addPass(new OutputPass());

  /* stations are built on demand: the hero first so the page can show something at once, the rest while the browser is idle
     (or immediately, if the visitor scrolls to one that is not built yet) */
  const makers = [createHero, createRoom, createBackdraft, createSigns, createScience, () => createProducts(getEnv()), createContact];
  const stations = new Array(makers.length).fill(null);
  let pxScale = 600;
  const setScale = (root) => root.traverse((o) => {
    const u = o.material && o.material.uniforms;
    if (u && u.uScale) u.uScale.value = pxScale;
  });
  function build(k) {
    if (stations[k]) return stations[k];
    const st = makers[k]();
    st.group.position.z = -STZ * k;
    st.group.visible = false;
    scene.add(st.group);
    stations[k] = st;
    setScale(st.group);
    applyDensity(LEVELS[level].dens);
    mark('st' + k);
    return st;
  }

  /* shared state handed to every station each frame */
  const S = (BSMA.s = {
    P: BSMA.P,
    T: 0,
    ch: 0,
    t: 0,
    ui: BSMA.ui,
    mouse: { x: 0, y: 0 },
    camera,
    width: 1,
    height: 1,
    fx: { bloom: 0, flash: 0, shake: 0, fov: 0, cam: new THREE.Vector3(), look: new THREE.Vector3() },
    state: {},
    cue: (n) => BSMA.cue && BSMA.cue(n),
  });

  /* layout metrics (sections are tall; their sticky child holds the viewport) */
  let L = [];
  let artTop = Infinity; // the opaque article section covers the canvas from here down: stop drawing
  let vh = innerHeight;
  BSMA.measure = () => {
    const y = scrollY;
    vh = innerHeight;
    L = [...document.querySelectorAll('main > .chapter')].map((el) => {
      const r = el.getBoundingClientRect();
      return { top: r.top + y, h: r.height };
    });
    const art = document.getElementById('article');
    artTop = art ? art.getBoundingClientRect().top + y : Infinity;
  };

  /* quality */
  const forced = params.get('q');
  const weak = (navigator.deviceMemory && navigator.deviceMemory <= 4) || (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4);
  const phone = coarse || innerWidth < 820;
  let level = forced ? { high: 0, mid: 1, low: 2, min: 3 }[forced] ?? 0 : phone ? (weak ? 2 : 1) : 0;
  if (reduced && !forced) level = Math.max(level, 1);
  const MAXL = LEVELS.length - 1;
  function setLevel(n) {
    level = clamp(n, 0, MAXL);
    BSMA.level = level;
    const Lv = LEVELS[level];
    renderer.setPixelRatio(Math.min(devicePixelRatio || 1, Lv.pr));
    composer.setPixelRatio(Math.min(devicePixelRatio || 1, Lv.pr));
    bloom.enabled = Lv.bloom;
    applyDensity(Lv.dens);
    resize();
  }
  BSMA.setLevel = setLevel;

  function resize() {
    const w = innerWidth, h = innerHeight;
    S.width = w;
    S.height = h;
    renderer.setSize(w, h, false);
    composer.setSize(w, h);
    bloom.resolution.set(w / 2, h / 2);
    camera.aspect = w / h;
    const pr = renderer.getPixelRatio();
    pxScale = (h * pr) / (2 * Math.tan(THREE.MathUtils.degToRad(20)));
    setScale(scene);
    BSMA.measure();
  }
  addEventListener('resize', () => {
    clearTimeout(resize._t);
    resize._t = setTimeout(resize, 120);
  });
  if ('ResizeObserver' in window) new ResizeObserver(() => BSMA.measure()).observe(document.body);

  addEventListener('pointermove', (e) => {
    mTarget.x = (e.clientX / innerWidth) * 2 - 1;
    mTarget.y = (e.clientY / innerHeight) * 2 - 1;
  }, { passive: true });
  const mTarget = { x: 0, y: 0 };

  setLevel(level);

  /* camera rig */
  const cA = new THREE.Vector3(), cB = new THREE.Vector3(), lA = new THREE.Vector3(), lB = new THREE.Vector3();
  const pos = new THREE.Vector3(), look = new THREE.Vector3();
  const w = (k, a, out) => out.set(a[0], a[1], a[2] + -STZ * CH_ST[k]);

  let sy = scrollY;
  let last = performance.now();
  let t = 0;
  let frames = 0, acc = 0;
  let flashSm = 0;
  const smoothScroll = !params.has('nosmooth') && !reduced;

  let tick = 0;
  function frame(now) {
    requestAnimationFrame(frame);
    if (level >= MAXL && tick++ % 2) return; // the lightest level draws at half rate
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;
    t += dt;
    S.t = t;

    /* scroll → progress */
    const y = scrollY;
    sy = smoothScroll ? sy + (y - sy) * (1 - Math.exp(-dt * 8)) : y;
    if (Math.abs(sy - y) < 0.3) sy = y;
    let T = 0;
    for (let i = 0; i < L.length; i++) {
      const { top, h } = L[i];
      BSMA.P[i] = clamp((sy - top) / Math.max(1, h - vh));
      if (i < L.length - 1) T += smooth(0, 1, clamp((sy - (top + h - vh)) / vh));
    }
    S.T = T;
    S.ch = Math.round(T);
    BSMA.T = T;
    if (sy > artTop + 4 && !(S.fx.flash > 0.01)) { BSMA.onFrame && BSMA.onFrame(S); return; }

    /* pointer easing */
    S.mouse.x += (mTarget.x - S.mouse.x) * 0.05;
    S.mouse.y += (mTarget.y - S.mouse.y) * 0.05;

    /* per-frame effect accumulators */
    const fx = S.fx;
    fx.bloom = 0; fx.flash = 0; fx.shake = 0; fx.fov = 0; fx.cam.set(0, 0, 0); fx.look.set(0, 0, 0);

    /* base camera pose along the ride */
    const i = Math.min(CAMS.length - 2, Math.floor(clamp(T, 0, CAMS.length - 1 - 1e-4)));
    const e = clamp(T - i, 0, 1);
    w(i, CAMS[i].p, cA); w(i + 1, CAMS[i + 1].p, cB);
    w(i, CAMS[i].l, lA); w(i + 1, CAMS[i + 1].l, lB);
    pos.lerpVectors(cA, cB, e);
    look.lerpVectors(lA, lB, e);
    /* portrait screens: pull the camera back so wide sets still fit the narrow width */
    const asp = innerWidth / innerHeight;
    const portrait = clamp((1.25 - asp) / 0.7);
    if (portrait > 0) {
      const mf = lerp(CAMS[i].mob, CAMS[i + 1].mob, e);
      const k = 1 + (mf - 1) * portrait;
      pos.sub(look).multiplyScalar(k).add(look);
    }
    const cross = CH_ST[i] !== CH_ST[i + 1];
    if (cross) pos.y += Math.sin(Math.PI * e) * 0.9;
    let fov = lerp(CAMS[i].fov, CAMS[i + 1].fov, e) + (cross ? 10 * Math.sin(Math.PI * e) : 0);
    const shift = lerp(CAMS[i].shift, CAMS[i + 1].shift, e);
    const my = lerp(CAMS[i].my ?? 0.2, CAMS[i + 1].my ?? 0.2, e); // phones: how far the picture is lifted above the text sheet

    const parallax = innerWidth < 820 ? 0.25 : 1;
    camera.position.set(pos.x + S.mouse.x * 0.55 * parallax, pos.y - S.mouse.y * 0.3 * parallax, pos.z);
    camera.lookAt(look);
    camera.updateMatrixWorld();

    /* stations in range */
    for (let k = 0; k < stations.length; k++) {
      const near = Math.abs(camera.position.z + STZ * k) < 62;
      const st = stations[k] || (near ? build(k) : null);
      if (!st) continue;
      st.group.visible = near;
      if (near) st.update(t, dt, S);
    }

    /* apply what the stations asked for */
    camera.position.add(fx.cam);
    if (fx.shake > 0) camera.position.add(new THREE.Vector3((Math.random() - 0.5) * fx.shake, (Math.random() - 0.5) * fx.shake, (Math.random() - 0.5) * fx.shake));
    camera.lookAt(look.x + fx.look.x, look.y + fx.look.y, look.z);
    camera.fov = fov + fx.fov + ((innerWidth < 820 && innerWidth / innerHeight < 1.15) || (innerWidth < 1100 && innerWidth / innerHeight < 1) ? 8 : 0);
    const W = innerWidth, H = innerHeight;
    // portrait phones: the text sheet sits at the bottom, so lift the picture; wide windows and phones on their side: the card sits at the side, so shift it sideways
    const sheet = (W < 820 && W / H < 1.15) || (W < 1100 && W / H < 1); // phones, and tablets standing up
    if (sheet) camera.setViewOffset(W, H, 0, H * my * (S.ch === 0 ? 0.4 : 1), W, H);
    else if (shift > 0.001) camera.setViewOffset(W, H, W * shift, 0, W, H);
    else camera.clearViewOffset();
    camera.updateProjectionMatrix();

    bloom.strength = 0.42 + fx.bloom * 0.8;
    flashSm += (fx.flash - flashSm) * 0.35;
    flashEl.style.opacity = flashSm > 0.004 ? flashSm.toFixed(3) : '0';

    composer.render();
    BSMA.onFrame && BSMA.onFrame(S);

    /* adaptive quality (skip warm-up frames; ignore when forced) */
    frames++;
    if (!forced && frames > 40 && level < MAXL - 1) {
      acc += dt;
      if (frames % 30 === 0) {
        if (acc / 30 > 0.036) setLevel(level + 1);
        acc = 0;
      }
    }
  }

  const hero = build(0);
  /* compile the hero's shaders in parallel (off the main thread where the GPU driver allows it) before the first frame */
  hero.group.visible = true;
  if (renderer.extensions.has('KHR_parallel_shader_compile')) {
    try {
      await Promise.race([renderer.compileAsync(hero.group, camera, scene), new Promise((r) => setTimeout(r, 1500))]);
    } catch (e) { /* the first frame compiles them instead */ }
  }
  mark('hero');
  BSMA.ready = true;
  requestAnimationFrame((n) => {
    last = n;
    frame(n);
  });

  /* the other stations (and the product environment map) are built one per idle slice, hero first */
  const idle = window.requestIdleCallback ? (f) => requestIdleCallback(f, { timeout: 900 }) : (f) => setTimeout(f, 140);
  let nextSt = 1;
  const pump = () => {
    while (nextSt < stations.length && stations[nextSt]) nextSt++;
    if (nextSt >= stations.length) { mark('all'); return; }
    build(nextSt++);
    idle(pump);
  };
  setTimeout(() => idle(pump), 1200);
}

/* let the browser paint the page first, then boot the 3D scene */
const boot = () => requestAnimationFrame(() => setTimeout(start, 30));
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
