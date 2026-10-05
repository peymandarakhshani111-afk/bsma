import * as THREE from 'three';
import { C, hdr, lerp, smooth, glowTexture, sootTexture, rng, registerPoints, collectTicks, GLSL_NOISE, smokeField, glowPlane } from '../util.js';

/* Station 3 — "features": the window you are told to read before you open anything.
   Five interactive hot-spots are anchored to real positions on this model. */
export const SIGN_ANCHORS = [
  [0.15, 1.85, -0.25], // 0  dense brown / yellow smoke behind the glass
  [-0.75, 0.9, 0.3], //   1  smoke pulsing in and out of a gap ("breathing")
  [0.95, 2.3, 0.08], //   2  soot-blackened, hot glass
  [-0.25, 1.25, -0.8], // 3  almost no visible flame
  [1.3, 0.88, 0.12], //   4  air being drawn in through a crack
];

export function createSigns() {
  const group = new THREE.Group();
  const WIN = { x: 0, y: 1.65, w: 2.6, h: 1.7 };

  /* wall around the opening */
  const wallMat = new THREE.MeshStandardMaterial({ color: 0x0d111c, roughness: 0.85, metalness: 0.2 });
  const piece = (w, h, x, y) => {
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, 0.3), wallMat);
    m.position.set(x, y, -0.15);
    group.add(m);
  };
  piece(8, WIN.y - WIN.h / 2, 0, (WIN.y - WIN.h / 2) / 2);
  piece(8, 5.2 - (WIN.y + WIN.h / 2), 0, (WIN.y + WIN.h / 2) + (5.2 - (WIN.y + WIN.h / 2)) / 2);
  piece(4 - WIN.w / 2, WIN.h, -(WIN.w / 2 + (4 - WIN.w / 2) / 2), WIN.y);
  piece(4 - WIN.w / 2, WIN.h, WIN.w / 2 + (4 - WIN.w / 2) / 2, WIN.y);

  /* neon frame + mullions */
  const bar = (w, h, x, y, k = 1.5) => {
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, 0.06), new THREE.MeshBasicMaterial({ color: hdr(C.cyan, k), toneMapped: false }));
    m.position.set(x, y, 0.04);
    group.add(m);
  };
  bar(WIN.w + 0.1, 0.03, 0, WIN.y + WIN.h / 2);
  bar(WIN.w + 0.1, 0.03, 0, WIN.y - WIN.h / 2);
  bar(0.03, WIN.h, -WIN.w / 2, WIN.y);
  bar(0.03, WIN.h, WIN.w / 2, WIN.y);
  bar(0.018, WIN.h, 0, WIN.y, 1.0);
  bar(WIN.w, 0.018, 0, WIN.y, 1.0);

  /* what is behind the glass: a fire-lit, brown, roiling mass */
  const behindMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uHot: { value: 0.5 } },
    vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.); }`,
    fragmentShader: /* glsl */ `
      ${GLSL_NOISE}
      uniform float uTime, uHot; varying vec2 vUv;
      void main(){
        float n = fbm(vec3(vUv * vec2(2.4, 1.6) * 1.8, uTime * .18));
        float m = smoothstep(.28, .8, n);
        vec3 c = mix(vec3(.18, .07, .02), vec3(.95, .38, .08), m) * (.8 + 1.5 * uHot);
        gl_FragColor = vec4(c, .85);
      }`,
  });
  const behind = new THREE.Mesh(new THREE.PlaneGeometry(WIN.w, WIN.h), behindMat);
  behind.position.set(0, WIN.y, -0.12);
  group.add(behind);

  const haze = smokeField({
    count: 110, box: [2.6, 1.7, 2.4], center: [0, WIN.y, -1.3], size: 1.7, drift: [0.01, 0.02, 0],
    lights: [{ pos: [0, WIN.y - 0.3, -2.2], color: C.fire, radius: 4.5 }], cool: 0x4a2c18, opacity: 0.5, seed: 77,
  });
  group.add(haze);

  /* sooty glass */
  const soot = sootTexture(256);
  soot.repeat.set(1.4, 1);
  const glass = new THREE.Mesh(
    new THREE.PlaneGeometry(WIN.w - 0.04, WIN.h - 0.04),
    new THREE.MeshBasicMaterial({ map: soot, color: 0xffffff, transparent: true, opacity: 0.74, depthWrite: false })
  );
  glass.position.set(0, WIN.y, 0.015);
  group.add(glass);
  // hot glass glow bleeding through the soot at the top
  const hotGlass = glowPlane(WIN.w, 0.8, C.fire, 1.1);
  hotGlass.position.set(0, WIN.y + 0.45, 0.03);
  group.add(hotGlass);

  /* smoke that "breathes" through a gap at the bottom of the frame */
  const N = 160;
  const rand = rng(61);
  const sd = new Float32Array(N * 3);
  for (let i = 0; i < N; i++) sd.set([rand(), rand(), rand()], i * 3);
  const bg = new THREE.BufferGeometry();
  bg.setAttribute('position', new THREE.BufferAttribute(new Float32Array(N * 3), 3));
  bg.setAttribute('aSeed', new THREE.BufferAttribute(sd, 3));
  const breathMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uScale: { value: 600 }, uAmt: { value: 1 }, uTex: { value: glowTexture(64) }, uHi: { value: 0 } },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed; uniform float uTime, uScale, uAmt, uHi; varying float vA;
      void main(){
        float b = sin(uTime * 1.55);
        float push = max(b, 0.) - .65 * max(-b, 0.);
        float x = ${(-1.2).toFixed(2)} + aSeed.x * 2.4;
        float reach = .12 + aSeed.z * .85;
        vec3 p = vec3(x + sin(uTime * .8 + aSeed.y * 9.) * .08 * push, ${(WIN.y - WIN.h / 2 + 0.04).toFixed(2)} + push * aSeed.y * .55, .06 + push * reach);
        vec4 mv = modelViewMatrix * vec4(p, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = (.2 + aSeed.y * .22) * (.55 + abs(push)) * uScale / max(.1, -mv.z);
        vA = uAmt * (.15 + abs(push) * .7) * step(aSeed.y, .9) * (1. + uHi);
      }`,
    fragmentShader: `uniform sampler2D uTex; varying float vA; void main(){ gl_FragColor = vec4(vec3(.7, .38, .17) * 1.5, texture2D(uTex, gl_PointCoord).a * vA * .5); }`,
  });
  const breath = new THREE.Points(bg, breathMat);
  breath.frustumCulled = false;
  registerPoints(breath, N);
  group.add(breath);

  /* thin cyan streaks drawn in through a crack on the right (air being pulled inside) */
  const AN = 40;
  const as = new Float32Array(AN * 3);
  for (let i = 0; i < AN; i++) as.set([rand(), rand(), rand()], i * 3);
  const ag = new THREE.BufferGeometry();
  ag.setAttribute('position', new THREE.BufferAttribute(new Float32Array(AN * 3), 3));
  ag.setAttribute('aSeed', new THREE.BufferAttribute(as, 3));
  const airMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uScale: { value: 600 }, uHi: { value: 0 }, uTex: { value: glowTexture(32) } },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed; uniform float uTime, uScale, uHi; varying float vA;
      void main(){
        float f = fract(aSeed.x + uTime * (.35 + aSeed.y * .2));
        vec3 p = vec3(${(1.3).toFixed(2)} + (1. - f) * (.4 + aSeed.z * .5), ${(0.88).toFixed(2)} + (aSeed.y - .5) * .18, .12 + (1. - f) * (.7 + aSeed.z * 1.2));
        vec4 mv = modelViewMatrix * vec4(p, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = (.07 + aSeed.y * .05) * uScale / max(.1, -mv.z);
        vA = (.35 + .65 * uHi) * smoothstep(0., .15, f) * (1. - smoothstep(.85, 1., f));
      }`,
    fragmentShader: `uniform sampler2D uTex; varying float vA; void main(){ gl_FragColor = vec4(vec3(.1, .9, 1.) * 2.6, texture2D(uTex, gl_PointCoord).a * vA); }`,
  });
  const airIn = new THREE.Points(ag, airMat);
  airIn.frustumCulled = false;
  registerPoints(airIn, AN);
  group.add(airIn);

  /* hot-spot rings in 3D */
  const rings = SIGN_ANCHORS.map((a) => {
    const m = new THREE.Mesh(
      new THREE.RingGeometry(0.16, 0.185, 48),
      new THREE.MeshBasicMaterial({ color: hdr(C.amber, 2.6), transparent: true, opacity: 0, depthWrite: false, depthTest: false, blending: THREE.AdditiveBlending, toneMapped: false })
    );
    m.position.set(...a);
    m.renderOrder = 10;
    group.add(m);
    return m;
  });

  group.add(new THREE.HemisphereLight(0x2a3a66, 0x05060a, 0.5));
  const warm = new THREE.PointLight(0xff6a1a, 22, 12, 1.5);
  warm.position.set(0, 1.5, 1.5);
  group.add(warm);

  const ticks = collectTicks(group);
  const tmp = new THREE.Vector3();
  const els = [];
  let cached = false;

  return {
    group,
    update(t, dt, s) {
      const act = s.ui.sign;
      behindMat.uniforms.uTime.value = t;
      behindMat.uniforms.uHot.value = 0.55 + 0.25 * Math.sin(t * 1.55);
      breathMat.uniforms.uTime.value = t;
      breathMat.uniforms.uHi.value = act === 1 ? 1 : 0;
      airMat.uniforms.uTime.value = t;
      airMat.uniforms.uHi.value = act === 4 ? 1 : 0;
      haze.material.uniforms.uBoost.value = act === 0 ? 1.7 : 1;
      glass.material.opacity = act === 2 ? 0.55 : 0.74;
      hotGlass.material.opacity = (act === 2 ? 0.85 : 0.35) + 0.1 * Math.sin(t * 3);
      ticks.forEach((f) => f(t));

      // pointer parallax: the whole wall tilts a little
      group.rotation.y += (s.mouse.x * 0.1 - group.rotation.y) * 0.06;
      group.rotation.x += (-s.mouse.y * 0.04 - group.rotation.x) * 0.06;

      rings.forEach((r, i) => {
        const on = i === act;
        r.material.opacity += ((on ? 0.95 : 0) - r.material.opacity) * 0.15;
        const k = on ? 1 + 0.18 * Math.sin(t * 6) : 0.8;
        r.scale.setScalar(k * (on ? 1.6 : 1));
        r.quaternion.copy(s.camera.quaternion);
      });

      /* project anchors → DOM hot-spot buttons */
      if (s.ch === 4 || s.ch === 3 || s.ch === 5) {
        if (!cached) {
          document.querySelectorAll('[data-hs]').forEach((el) => (els[+el.dataset.hs] = el));
          cached = els.length > 0;
        }
        group.updateMatrixWorld();
        SIGN_ANCHORS.forEach((a, i) => {
          const el = els[i];
          if (!el) return;
          tmp.set(...a);
          group.localToWorld(tmp);
          tmp.project(s.camera);
          const x = (tmp.x * 0.5 + 0.5) * s.width;
          const y = (-tmp.y * 0.5 + 0.5) * s.height;
          const vis = tmp.z < 1 && s.ch === 4;
          el.style.transform = `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0) translate(-50%, -50%)`;
          el.style.opacity = vis ? '1' : '0';
          el.style.pointerEvents = vis ? 'auto' : 'none';
        });
      }

      // gently lean the camera toward the selected sign
      if (act >= 0 && s.ch === 4) {
        const a = SIGN_ANCHORS[act];
        s.fx.look.x += a[0] * 0.18;
        s.fx.look.y += (a[1] - 1.65) * 0.18;
        s.fx.cam.z -= 0.35;
      }
    },
  };
}
