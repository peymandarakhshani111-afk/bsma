import * as THREE from 'three';
import { C, hdr, lerp, smooth, edges, gridFloor, glowTexture, rng, registerPoints, collectTicks, GLSL_NOISE, smokeField, neonBar } from '../util.js';

/* Station 1 — one compartment, two stories: growth → flashover.
   Everything is a pure function of the scroll-driven "room time" rt ∈ [0,1]:
   rt 0–0.5  = chapter 1 (fire grows, hot layer descends, air runs out)
   rt 0.5–1  = chapter 2 (radiation, simultaneous ignition) */
const W = 9, H = 3.6, D = 6.5;

function flameCluster({ n = 40, r = 0.4, h = 1.2, size = 1.1, seed = 1 }) {
  const rand = rng(seed);
  const pos = new Float32Array(n * 3);
  const sd = new Float32Array(n * 3);
  for (let i = 0; i < n; i++) {
    sd.set([rand(), rand(), rand()], i * 3);
  }
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  geo.setAttribute('aSeed', new THREE.BufferAttribute(sd, 3));
  const mat = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    uniforms: {
      uTime: { value: 0 }, uScale: { value: 600 }, uSize: { value: size }, uInt: { value: 0 },
      uR: { value: r }, uH: { value: h }, uTex: { value: glowTexture(64) },
    },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed; uniform float uTime, uScale, uSize, uInt, uR, uH; varying float vL;
      void main(){
        float life = fract(aSeed.x + uTime * (.55 + aSeed.y * .9));
        float sp = uR * (1. - life * .65);
        vec3 p = vec3((aSeed.y - .5) * 2. * sp + sin(uTime * 3. + aSeed.z * 30.) * .07 * life,
                      life * uH * (.25 + uInt * .85),
                      (aSeed.z - .5) * 2. * sp);
        vec4 mv = modelViewMatrix * vec4(p, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = uSize * uInt * (1. - life * .72) * (.6 + aSeed.y) * uScale / max(.1, -mv.z);
        vL = life;
      }`,
    fragmentShader: /* glsl */ `
      uniform sampler2D uTex; uniform float uInt; varying float vL;
      void main(){
        float a = texture2D(uTex, gl_PointCoord).a;
        vec3 hot = vec3(1.0, .93, .6), mid = vec3(1.0, .42, .08), cold = vec3(.55, .08, .02);
        vec3 c = vL < .35 ? mix(hot, mid, vL / .35) : mix(mid, cold, (vL - .35) / .65);
        gl_FragColor = vec4(c * 2.4, a * pow(1. - vL, .8) * clamp(uInt, 0., 1.3));
      }`,
  });
  const p = new THREE.Points(geo, mat);
  p.frustumCulled = false;
  registerPoints(p, n);
  return p;
}

function layerMaterial() {
  return new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    side: THREE.BackSide, // draw only the far faces: one pass of haze instead of two stacked ones
    blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uHeat: { value: 0 }, uFlash: { value: 0 }, uThick: { value: 1 } },
    vertexShader: `varying vec3 vW; varying float vY; void main(){ vec4 w = modelMatrix * vec4(position, 1.); vW = w.xyz; vY = position.y + .5; gl_Position = projectionMatrix * viewMatrix * w; }`,
    fragmentShader: /* glsl */ `
      ${GLSL_NOISE}
      uniform float uTime, uHeat, uFlash, uThick; varying vec3 vW; varying float vY;
      void main(){
        // vY: 0 at the lower boundary of the layer, 1 at the ceiling
        float n = fbm(vW * .55 + vec3(0., -uTime * .12, uTime * .05));
        float edge = smoothstep(0., .32, vY);
        vec3 smokeC = vec3(.16, .13, .12);
        vec3 hotC = vec3(1., .2, .03);
        vec3 col = mix(smokeC, hotC, uHeat) * (.5 + .8 * uHeat + .5 * uFlash);
        col = mix(col, vec3(1., .42, .12) * 1.1, uFlash * .2);
        float a = edge * (.05 + .11 * uHeat + .07 * uFlash) * (.55 + .8 * n);
        // glowing lower boundary (the "rolling" front)
        a += exp(-pow((vY - .02) * 11., 2.)) * (.08 + .22 * uHeat);
        gl_FragColor = vec4(col, a);
      }`,
  });
}

export function createRoom() {
  const group = new THREE.Group();
  const rand = rng(33);

  /* shell */
  const wire = edges(new THREE.BoxGeometry(W, H, D), C.cyan, 1.5);
  wire.position.y = H / 2;
  group.add(wire);

  const dark = new THREE.MeshStandardMaterial({ color: 0x0c101c, roughness: 0.9, metalness: 0.1, side: THREE.DoubleSide });
  const ghost = new THREE.MeshBasicMaterial({ color: 0x0a1428, transparent: true, opacity: 0.35, depthWrite: false, side: THREE.DoubleSide });
  const back = new THREE.Mesh(new THREE.PlaneGeometry(W, H), dark);
  back.position.set(0, H / 2, -D / 2);
  const left = new THREE.Mesh(new THREE.PlaneGeometry(D, H), dark);
  left.rotation.y = Math.PI / 2;
  left.position.set(-W / 2, H / 2, 0);
  const right = new THREE.Mesh(new THREE.PlaneGeometry(D, H), ghost);
  right.rotation.y = -Math.PI / 2;
  right.position.set(W / 2, H / 2, 0);
  const ceil = new THREE.Mesh(new THREE.PlaneGeometry(W, D), ghost);
  ceil.rotation.x = Math.PI / 2;
  ceil.position.set(0, H, 0);
  const floorBase = new THREE.Mesh(new THREE.PlaneGeometry(W, D), new THREE.MeshStandardMaterial({ color: 0x080b14, roughness: 0.6, metalness: 0.5 }));
  floorBase.rotation.x = -Math.PI / 2;
  group.add(back, left, right, ceil, floorBase);

  const grid = gridFloor({ size: [W, D], cell: 0.5, color: C.cyan, glowColor: C.fire, glowPos: [-1.4, 1.4], glowRadius: 4.5, fadeRadius: 12, k: 1.1 });
  grid.position.y = 0.004;
  group.add(grid);

  /* door (left wall, near the front) and window (back wall) — both closed */
  const door = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(0.06, 2.1, 0.95)), new THREE.LineBasicMaterial({ color: hdr(C.cyan, 2.2), toneMapped: false }));
  door.position.set(-W / 2 + 0.03, 1.05, 1.5);
  const doorGap = neonBar(0.02, 2.1, 0.02, C.fire, 3.5);
  doorGap.position.set(-W / 2 + 0.05, 1.05, 1.0);
  const win = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(1.9, 1.3, 0.06)), new THREE.LineBasicMaterial({ color: hdr(C.cyan, 2.2), toneMapped: false }));
  win.position.set(2.2, 1.9, -D / 2 + 0.3);
  group.add(door, doorGap, win);

  /* furniture: each piece ignites at (nearly) the same instant at flashover */
  const furn = [];
  const addF = (name, w, h, d, x, y, z, color, jitter) => {
    const mat = new THREE.MeshStandardMaterial({ color, roughness: 0.75, metalness: 0.05, emissive: new THREE.Color(1.0, 0.2, 0.03), emissiveIntensity: 0 });
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
    m.position.set(x, y, z);
    group.add(m);
    furn.push({ name, mat, jitter, m });
    return m;
  };
  addF('sofa-base', 2.5, 0.45, 1.0, -1.8, 0.23, -1.9, 0x1b2236, 0.0);
  addF('sofa-back', 2.5, 0.6, 0.26, -1.8, 0.75, -2.3, 0x1b2236, 0.004);
  addF('sofa-arm-l', 0.3, 0.55, 1.0, -3.0, 0.5, -1.9, 0x1b2236, 0.008);
  addF('sofa-arm-r', 0.3, 0.55, 1.0, -0.6, 0.5, -1.9, 0x1b2236, 0.003);
  addF('table', 1.15, 0.38, 0.65, 0.0, 0.2, -0.85, 0x241d1a, 0.012);
  addF('tv-stand', 2.1, 0.5, 0.5, 2.3, 0.26, -2.75, 0x1d2233, 0.006);
  addF('tv', 1.5, 0.85, 0.08, 2.3, 1.05, -2.85, 0x0a0c12, 0.01);
  addF('shelf', 0.42, 2.1, 1.5, -4.2, 1.05, -0.6, 0x231d1b, 0.014);
  addF('curtain', 1.9, 2.3, 0.05, 2.2, 1.35, -D / 2 + 0.2, 0x1b2236, 0.002);
  addF('rug', 3.0, 0.02, 2.2, -0.4, 0.015, -1.2, 0x1a1e2e, 0.016);
  addF('chair', 0.7, 0.9, 0.7, 2.8, 0.45, 0.2, 0x1b2236, 0.009);

  /* flames: origin = sofa; at flashover every object carries its own fire */
  const clusters = [
    { at: [-1.5, 0.3, -1.5], r: 0.55, h: 1.5, f: furn[0], base: true },
    { at: [-1.8, 0.5, -2.2], r: 0.9, h: 1.7, f: furn[1] },
    { at: [0.0, 0.3, -0.85], r: 0.45, h: 1.1, f: furn[4] },
    { at: [2.3, 0.5, -2.7], r: 0.8, h: 1.6, f: furn[5] },
    { at: [-4.2, 0.6, -0.6], r: 0.4, h: 2.2, f: furn[7] },
    { at: [2.2, 0.8, -D / 2 + 0.3], r: 0.7, h: 2.0, f: furn[8] },
    { at: [-0.4, 0.1, -1.2], r: 1.3, h: 0.8, f: furn[9] },
    { at: [2.8, 0.4, 0.2], r: 0.4, h: 1.2, f: furn[10] },
  ].map((c, i) => {
    const p = flameCluster({ n: c.base ? 60 : 34, r: c.r, h: c.h, seed: 40 + i, size: c.base ? 1.3 : 1.1 });
    p.position.set(...c.at);
    group.add(p);
    return { ...c, p };
  });

  /* hot gas layer under the ceiling */
  const layerMat = layerMaterial();
  const layer = new THREE.Mesh(new THREE.BoxGeometry(W - 0.06, 1, D - 0.06), layerMat);
  layer.renderOrder = 4;
  group.add(layer);

  /* radiant heat "rain" falling from the layer */
  const rainN = 180;
  const rp = new Float32Array(rainN * 3), rs = new Float32Array(rainN * 3);
  for (let i = 0; i < rainN; i++) {
    rp.set([rand() - 0.5, rand(), rand() - 0.5], i * 3);
    rs.set([rand(), rand(), rand()], i * 3);
  }
  const rg = new THREE.BufferGeometry();
  rg.setAttribute('position', new THREE.BufferAttribute(rp, 3));
  rg.setAttribute('aSeed', new THREE.BufferAttribute(rs, 3));
  const rainMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uScale: { value: 600 }, uAmt: { value: 0 }, uTop: { value: H }, uTex: { value: glowTexture(32) } },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed; uniform float uTime, uScale, uAmt, uTop; varying float vA;
      void main(){
        float f = fract(aSeed.x + uTime * (.28 + aSeed.y * .35));
        vec3 p = vec3(position.x * ${(W - 0.6).toFixed(2)}, uTop - f * uTop, position.z * ${(D - 0.6).toFixed(2)});
        vec4 mv = modelViewMatrix * vec4(p, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = .09 * (.5 + aSeed.z) * uScale / max(.1, -mv.z);
        vA = uAmt * step(aSeed.y, uAmt + .05) * (1. - f * .4);
      }`,
    fragmentShader: `uniform sampler2D uTex; varying float vA; void main(){ gl_FragColor = vec4(vec3(1., .5, .15) * 3., texture2D(uTex, gl_PointCoord).a * vA * .8); }`,
  });
  const rain = new THREE.Points(rg, rainMat);
  rain.frustumCulled = false;
  registerPoints(rain, rainN);
  group.add(rain);

  /* oxygen stream (cyan) trickling in under the door — it dies out as the fire eats the air */
  const oN = 90;
  const op = new Float32Array(oN * 3), os = new Float32Array(oN * 3);
  for (let i = 0; i < oN; i++) {
    op.set([0, 0, 0], i * 3);
    os.set([rand(), rand(), rand()], i * 3);
  }
  const og = new THREE.BufferGeometry();
  og.setAttribute('position', new THREE.BufferAttribute(op, 3));
  og.setAttribute('aSeed', new THREE.BufferAttribute(os, 3));
  const oMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uScale: { value: 600 }, uAmt: { value: 1 }, uTex: { value: glowTexture(32) } },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed; uniform float uTime, uScale, uAmt; varying float vA;
      void main(){
        float f = fract(aSeed.x + uTime * (.1 + aSeed.y * .08));
        vec3 p = vec3(${(-W / 2 + 0.1).toFixed(2)} + f * 5.2, .05 + (aSeed.y - .5) * .18 + f * f * .5 + sin(f * 9. + aSeed.z * 9.) * .05, ${(1.5 + 0.0).toFixed(2)} + (aSeed.z - .5) * .8 + f * -1.6);
        vec4 mv = modelViewMatrix * vec4(p, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = .11 * (.6 + aSeed.y) * uScale / max(.1, -mv.z);
        vA = step(aSeed.y, uAmt) * (1. - f) * smoothstep(0., .08, f);
      }`,
    fragmentShader: `uniform sampler2D uTex; varying float vA; void main(){ gl_FragColor = vec4(vec3(.1, .9, 1.) * 2.4, texture2D(uTex, gl_PointCoord).a * vA); }`,
  });
  const oxy = new THREE.Points(og, oMat);
  oxy.frustumCulled = false;
  registerPoints(oxy, oN);
  group.add(oxy);

  /* residual smoke inside, lit by the fire */
  const smoke = smokeField({
    count: 120, box: [W - 0.4, H - 0.2, D - 0.4], center: [0, H / 2, 0], size: 2.6, drift: [0.004, 0.02, 0],
    lights: [{ pos: [-1.5, 0.6, -1.5], color: C.fire, radius: 7 }], cool: 0x141a2a, opacity: 0.16, seed: 8,
  });
  group.add(smoke);

  /* lighting */
  group.add(new THREE.HemisphereLight(0x2a3a66, 0x05060a, 0.55));
  const fireLight = new THREE.PointLight(0xff5a14, 0, 14, 1.5);
  fireLight.position.set(-1.5, 0.8, -1.5);
  const roomLight = new THREE.PointLight(0xff7a2a, 0, 16, 1.4);
  roomLight.position.set(0, H - 0.6, -0.4);
  const rim = new THREE.PointLight(0x27e6ff, 8, 22, 1.6);
  rim.position.set(6.5, 3.2, 6);
  group.add(fireLight, roomLight, rim);

  const ticks = collectTicks(group);
  let lastRt = 0;
  let flashEnv = 0;

  return {
    group,
    layer,
    update(t, dt, s) {
      const rt = 0.5 * s.P[1] + 0.5 * s.P[2];
      const growth = smooth(0.02, 0.5, rt);
      const heat = smooth(0.08, 0.76, rt);
      const flashT = smooth(0.745, 0.79, rt);
      const post = smooth(0.79, 0.9, rt);

      /* cue + flash envelope when the room goes */
      if (lastRt < 0.765 && rt >= 0.765 && rt - lastRt < 0.1 && s.P[2] > 0) {
        s.cue && s.cue('flashover');
        flashEnv = 1;
      }
      lastRt = rt;
      flashEnv = Math.max(0, flashEnv - dt * 1.6);

      /* hot layer: thickness grows, then fills the whole room */
      const thick = Math.min(H, lerp(0.12, 1.45, smooth(0, 0.5, rt)) + lerp(0, 2.2, smooth(0.5, 0.79, rt)));
      layer.scale.set(1, thick, 1);
      layer.position.y = H - thick / 2;
      layerMat.uniforms.uTime.value = t;
      layerMat.uniforms.uHeat.value = heat;
      layerMat.uniforms.uFlash.value = Math.min(1, flashT * 0.55 + flashEnv * 0.6 + post * 0.25);
      layerMat.uniforms.uThick.value = thick;

      /* fire + furniture */
      const flick = 0.9 + 0.1 * Math.sin(t * 13) + 0.05 * Math.sin(t * 31);
      clusters.forEach((c, i) => {
        const ign = smooth(0.745 + c.f.jitter, 0.785 + c.f.jitter, rt);
        let inten = c.base ? Math.max(0.25 + 0.75 * growth, ign * 1.2) : ign * 1.15;
        inten *= flick * (0.9 + 0.1 * Math.sin(t * 9 + i));
        c.p.material.uniforms.uInt.value = inten;
        c.p.material.uniforms.uTime.value = t;
        c.f.mat.emissiveIntensity = (c.base ? 0.35 * growth : 0) + ign * 1.2 * flick;
      });
      furn.forEach((f) => {
        if (!clusters.some((c) => c.f === f)) f.mat.emissiveIntensity = smooth(0.745 + f.jitter, 0.785 + f.jitter, rt) * 1.0;
      });

      fireLight.intensity = (14 + 30 * growth + 26 * flashT) * flick;
      roomLight.intensity = (4 * heat + 34 * flashT) * (0.9 + 0.1 * Math.sin(t * 21));
      grid.material.uniforms.uGlowK.value = 0.5 + 1.0 * growth + 0.6 * flashT;

      /* radiation rain & oxygen */
      rainMat.uniforms.uAmt.value = smooth(0.34, 0.78, rt) * (1 - post * 0.35);
      rainMat.uniforms.uTop.value = H - 0.05;
      rainMat.uniforms.uTime.value = t;
      oMat.uniforms.uAmt.value = 1 - smooth(0.04, 0.5, rt);
      oMat.uniforms.uTime.value = t;

      smoke.material.uniforms.uBoost.value = 0.5 + heat;
      ticks.forEach((f) => f(t));

      /* effects for the post pass / camera */
      s.fx.bloom += flashT * 0.2 + flashEnv * 0.35;
      s.fx.flash = Math.max(s.fx.flash, flashEnv * 0.55);
      s.fx.shake = Math.max(s.fx.shake, flashEnv * 0.05);
      s.state.roomHeat = heat;
      s.state.roomT = rt;
    },
  };
}
