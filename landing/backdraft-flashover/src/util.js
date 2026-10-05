import * as THREE from 'three';

/* ───────── palette (HDR multipliers feed the bloom pass) ───────── */
export const C = {
  bg: 0x05060a,
  fire: new THREE.Color(1.0, 0.36, 0.08),
  amber: new THREE.Color(1.0, 0.7, 0.28),
  cyan: new THREE.Color(0.1, 0.9, 1.0),
  lilac: new THREE.Color(0.72, 0.65, 1.0),
  white: new THREE.Color(1, 1, 1),
};
export const hdr = (c, k) => c.clone().multiplyScalar(k);

export const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
export const lerp = (a, b, t) => a + (b - a) * t;
export const smooth = (a, b, x) => {
  const t = clamp((x - a) / (b - a));
  return t * t * (3 - 2 * t);
};
export const map01 = (x, a, b) => clamp((x - a) / (b - a));
export const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);
export const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

/* seeded random so scenes look identical on every load */
export function rng(seed = 1) {
  let s = seed >>> 0;
  return () => {
    s = (s + 0x6d2b79f5) >>> 0;
    let t = s;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/* ───────── canvas textures ───────── */
function canvas(size) {
  const c = document.createElement('canvas');
  c.width = c.height = size;
  return c;
}

export function glowTexture(size = 128, stops) {
  const c = canvas(size);
  const g = c.getContext('2d');
  const grd = g.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
  (stops || [[0, 'rgba(255,255,255,1)'], [0.25, 'rgba(255,255,255,.45)'], [0.6, 'rgba(255,255,255,.08)'], [1, 'rgba(255,255,255,0)']]).forEach(([o, col]) => grd.addColorStop(o, col));
  g.fillStyle = grd;
  g.fillRect(0, 0, size, size);
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

/* soft cloudy puff used by the smoke points */
export function puffTexture(size = 128, seed = 3) {
  const c = canvas(size);
  const g = c.getContext('2d');
  const r = rng(seed);
  g.clearRect(0, 0, size, size);
  for (let i = 0; i < 46; i++) {
    const a = r() * Math.PI * 2;
    const d = Math.pow(r(), 0.8) * size * 0.26;
    const x = size / 2 + Math.cos(a) * d;
    const y = size / 2 + Math.sin(a) * d;
    const rad = size * (0.1 + r() * 0.2);
    const grd = g.createRadialGradient(x, y, 0, x, y, rad);
    grd.addColorStop(0, 'rgba(255,255,255,.2)');
    grd.addColorStop(1, 'rgba(255,255,255,0)');
    g.fillStyle = grd;
    g.fillRect(0, 0, size, size);
  }
  // fade the border so the sprite never shows a hard square edge
  const m = g.createRadialGradient(size / 2, size / 2, size * 0.18, size / 2, size / 2, size / 2);
  m.addColorStop(0, 'rgba(0,0,0,0)');
  m.addColorStop(1, 'rgba(0,0,0,1)');
  g.globalCompositeOperation = 'destination-out';
  g.fillStyle = m;
  g.fillRect(0, 0, size, size);
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

export function sootTexture(size = 256) {
  const c = canvas(size);
  const g = c.getContext('2d');
  const r = rng(11);
  g.fillStyle = 'rgb(38,26,18)';
  g.fillRect(0, 0, size, size);
  for (let i = 0; i < 260; i++) {
    const x = r() * size;
    const y = r() * size;
    const rad = 8 + r() * 40;
    const grd = g.createRadialGradient(x, y, 0, x, y, rad);
    const dark = r() > 0.5;
    grd.addColorStop(0, dark ? 'rgba(10,6,4,.35)' : 'rgba(120,80,40,.18)');
    grd.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = grd;
    g.fillRect(x - rad, y - rad, rad * 2, rad * 2);
  }
  // vertical drip streaks
  for (let i = 0; i < 40; i++) {
    const x = r() * size;
    const grd = g.createLinearGradient(0, 0, 0, size);
    grd.addColorStop(0, 'rgba(0,0,0,0)');
    grd.addColorStop(0.2 + r() * 0.5, 'rgba(0,0,0,.22)');
    grd.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = grd;
    g.fillRect(x, 0, 1 + r() * 2.5, size);
  }
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace;
  t.wrapS = t.wrapT = THREE.RepeatWrapping;
  return t;
}

/* ───────── GLSL snippets ───────── */
export const GLSL_NOISE = /* glsl */ `
float hash31(vec3 p){ p = fract(p * .1031); p += dot(p, p.zyx + 31.32); return fract((p.x + p.y) * p.z); }
float vnoise(vec3 p){
  vec3 i = floor(p), f = fract(p); f = f*f*(3.-2.*f);
  float a = hash31(i), b = hash31(i+vec3(1,0,0)), c = hash31(i+vec3(0,1,0)), d = hash31(i+vec3(1,1,0));
  float e = hash31(i+vec3(0,0,1)), g = hash31(i+vec3(1,0,1)), h = hash31(i+vec3(0,1,1)), k = hash31(i+vec3(1,1,1));
  return mix(mix(mix(a,b,f.x), mix(c,d,f.x), f.y), mix(mix(e,g,f.x), mix(h,k,f.x), f.y), f.z);
}
float fbm(vec3 p){ float s = 0., a = .5; for(int i=0;i<4;i++){ s += a*vnoise(p); p = p*2.03 + 7.1; a *= .5; } return s; }
`;

/* ───────── quality registry: every Points object registers here so the
   quality manager can thin particle counts on weak GPUs ───────── */
export const registry = [];
export function registerPoints(obj, full) {
  registry.push({ obj, full });
  return obj;
}
export function applyDensity(f) {
  registry.forEach(({ obj, full }) => obj.geometry.setDrawRange(0, Math.max(8, Math.floor(full * f))));
}

/* ───────── SmokeField: GPU-animated cloud of soft puffs, lit by a few
   coloured point sources, fully looping (no CPU update) ───────── */
export function smokeField({ count = 300, box = [8, 4, 6], center = [0, 1.5, 0], size = 3.2, drift = [0.02, 0.05, 0], cool = 0x2a3550, warm = C.fire, opacity = 0.22, lights = [], seed = 5, texture }) {
  const r = rng(seed);
  const pos = new Float32Array(count * 3);
  const sd = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) {
    pos.set([r() - 0.5, r() - 0.5, r() - 0.5], i * 3);
    sd.set([r(), r(), r()], i * 3);
  }
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  geo.setAttribute('aSeed', new THREE.BufferAttribute(sd, 3));
  const lp = [], lc = [], lr = [];
  for (let i = 0; i < 3; i++) {
    const L = lights[i];
    lp.push(L ? new THREE.Vector3(...L.pos) : new THREE.Vector3(0, -999, 0));
    lc.push(L ? L.color.clone() : new THREE.Color(0, 0, 0));
    lr.push(L ? L.radius : 1);
  }
  const mat = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    uniforms: {
      uTime: { value: 0 },
      uBox: { value: new THREE.Vector3(...box) },
      uCenter: { value: new THREE.Vector3(...center) },
      uDrift: { value: new THREE.Vector3(...drift) },
      uSize: { value: size },
      uScale: { value: 600 },
      uCool: { value: new THREE.Color(cool) },
      uWarm: { value: new THREE.Color(warm) },
      uOpacity: { value: opacity },
      uMap: { value: texture || puffTexture(128, seed) },
      uLP: { value: lp },
      uLC: { value: lc },
      uLR: { value: lr },
      uBoost: { value: 1 },
    },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed;
      uniform float uTime, uSize, uScale, uBoost;
      uniform vec3 uBox, uCenter, uDrift, uLP[3], uLC[3];
      uniform float uLR[3];
      uniform vec3 uCool, uWarm;
      varying float vA; varying vec3 vCol; varying float vRot;
      void main(){
        vec3 q = position + uDrift * uTime * (0.4 + aSeed.x);
        q = fract(q + .5) - .5;
        q.x += .035 * sin(uTime * .25 + aSeed.y * 6.283);
        q.z += .035 * cos(uTime * .21 + aSeed.z * 6.283);
        vec3 w = uCenter + q * uBox;
        vec4 mv = modelViewMatrix * vec4(w, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = uSize * (.55 + aSeed.z) * uScale / max(.1, -mv.z);
        vec3 lit = uCool;
        for (int i = 0; i < 3; i++) {
          float d = distance(w, uLP[i]);
          float f = pow(clamp(1. - d / uLR[i], 0., 1.), 1.6);
          lit += uLC[i] * f * 1.4;
        }
        vCol = lit;
        float edge = (1. - smoothstep(.34, .5, abs(q.y))) * (1. - smoothstep(.34, .5, abs(q.x))) * (1. - smoothstep(.34, .5, abs(q.z)));
        vA = edge * (.5 + .5 * aSeed.y) * uBoost;
        vRot = aSeed.x * 6.283 + uTime * (aSeed.y - .5) * .15;
      }`,
    fragmentShader: /* glsl */ `
      uniform sampler2D uMap; uniform float uOpacity;
      varying float vA; varying vec3 vCol; varying float vRot;
      void main(){
        vec2 p = gl_PointCoord - .5;
        float c = cos(vRot), s = sin(vRot);
        p = mat2(c, -s, s, c) * p + .5;
        float a = texture2D(uMap, p).a;
        gl_FragColor = vec4(vCol, a * vA * uOpacity);
      }`,
  });
  const pts = new THREE.Points(geo, mat);
  pts.frustumCulled = false;
  registerPoints(pts, count);
  pts.userData.tick = (t) => (mat.uniforms.uTime.value = t);
  return pts;
}

/* ───────── EmberField: rising sparks (additive, HDR) ───────── */
export function emberField({ count = 120, box = [3, 3, 3], center = [0, 1, 0], color = C.amber, size = 0.1, rise = 0.25, seed = 9, hdrK = 3 }) {
  const r = rng(seed);
  const pos = new Float32Array(count * 3);
  const sd = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) {
    pos.set([r() - 0.5, r() - 0.5, r() - 0.5], i * 3);
    sd.set([r(), r(), r()], i * 3);
  }
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  geo.setAttribute('aSeed', new THREE.BufferAttribute(sd, 3));
  const mat = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    uniforms: {
      uTime: { value: 0 },
      uBox: { value: new THREE.Vector3(...box) },
      uCenter: { value: new THREE.Vector3(...center) },
      uColor: { value: color.clone().multiplyScalar(hdrK) },
      uSize: { value: size },
      uScale: { value: 600 },
      uRise: { value: rise },
      uAmt: { value: 1 },
      uTex: { value: glowTexture(64) },
    },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed;
      uniform float uTime, uSize, uScale, uRise, uAmt;
      uniform vec3 uBox, uCenter;
      varying float vA;
      void main(){
        vec3 q = position;
        q.y += uTime * uRise * (.4 + aSeed.x);
        q.x += .08 * sin(uTime * (.6 + aSeed.y) + aSeed.z * 20.);
        q = fract(q + .5) - .5;
        vec3 w = uCenter + q * uBox;
        vec4 mv = modelViewMatrix * vec4(w, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = uSize * (.4 + aSeed.z) * uScale / max(.1, -mv.z);
        float tw = .55 + .45 * sin(uTime * (3. + aSeed.x * 6.) + aSeed.y * 40.);
        vA = (1. - smoothstep(.3, .5, abs(q.y))) * tw * step(aSeed.y, uAmt);
      }`,
    fragmentShader: /* glsl */ `
      uniform sampler2D uTex; uniform vec3 uColor; varying float vA;
      void main(){ float a = texture2D(uTex, gl_PointCoord).a; gl_FragColor = vec4(uColor, a * vA); }`,
  });
  const pts = new THREE.Points(geo, mat);
  pts.frustumCulled = false;
  registerPoints(pts, count);
  pts.userData.tick = (t) => (mat.uniforms.uTime.value = t);
  return pts;
}

/* ───────── neon grid floor ───────── */
export function gridFloor({ size = [40, 60], cell = 1, color = C.cyan, glowColor = C.fire, glowPos = [0, 0], glowRadius = 6, fadeRadius = 22, k = 0.8 }) {
  const mat = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    uniforms: {
      uCell: { value: cell },
      uColor: { value: color.clone().multiplyScalar(k) },
      uGlow: { value: glowColor.clone() },
      uGlowPos: { value: new THREE.Vector2(...glowPos) },
      uGlowR: { value: glowRadius },
      uFade: { value: fadeRadius },
      uGlowK: { value: 1 },
      uAlpha: { value: 1 },
      uTime: { value: 0 },
    },
    vertexShader: `varying vec2 vP; void main(){ vP = position.xy; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.); }`,
    fragmentShader: /* glsl */ `
      uniform float uCell, uGlowR, uFade, uGlowK, uAlpha, uTime; uniform vec3 uColor, uGlow; uniform vec2 uGlowPos;
      varying vec2 vP;
      void main(){
        vec2 g = abs(fract(vP / uCell - .5) - .5) / fwidth(vP / uCell);
        float line = 1. - min(min(g.x, g.y), 1.);
        float fade = 1. - smoothstep(uFade * .25, uFade, length(vP));
        float gl = pow(clamp(1. - distance(vP, uGlowPos) / uGlowR, 0., 1.), 2.);
        vec3 col = uColor * line * .55 * fade + uGlow * gl * uGlowK * (.5 + .5 * line);
        float a = max(max(col.r, col.g), col.b);
        gl_FragColor = vec4(col, clamp(a, 0., 1.) * uAlpha);
      }`,
  });
  const m = new THREE.Mesh(new THREE.PlaneGeometry(size[0], size[1]), mat);
  m.rotation.x = -Math.PI / 2;
  m.userData.tick = (t) => (mat.uniforms.uTime.value = t);
  return m;
}

/* a thin glowing box used for neon bars/frames */
export function neonBar(w, h, d, color, k = 2.5) {
  const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), new THREE.MeshBasicMaterial({ color: color.clone().multiplyScalar(k), toneMapped: false }));
  return m;
}

export function edges(geometry, color, k = 1.6, opacity = 1) {
  const l = new THREE.LineSegments(new THREE.EdgesGeometry(geometry), new THREE.LineBasicMaterial({ color: color.clone().multiplyScalar(k), transparent: opacity < 1, opacity, toneMapped: false }));
  return l;
}

/* billboard-less additive glow quad (plane facing +z) */
export function glowPlane(w, h, color, k = 1.5, tex) {
  const mat = new THREE.MeshBasicMaterial({ map: tex || glowTexture(128), color: color.clone().multiplyScalar(k), transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, toneMapped: false });
  return new THREE.Mesh(new THREE.PlaneGeometry(w, h), mat);
}

/* gradient light shaft (vertical gradient along its length, soft across) */
export function shaft(len, width, color, k = 1.2) {
  const mat = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    side: THREE.DoubleSide,
    uniforms: { uColor: { value: color.clone().multiplyScalar(k) }, uA: { value: 1 }, uTime: { value: 0 }, uSeed: { value: Math.random() * 10 } },
    vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.); }`,
    fragmentShader: /* glsl */ `
      uniform vec3 uColor; uniform float uA, uTime, uSeed; varying vec2 vUv;
      void main(){
        float across = 1. - abs(vUv.x - .5) * 2.;
        float along = 1. - vUv.y;
        float fl = .75 + .25 * sin(uTime * 6. + uSeed) * sin(uTime * 2.3 + uSeed * 2.);
        float a = pow(across, 1.6) * pow(along, 1.4) * fl * uA;
        gl_FragColor = vec4(uColor, a);
      }`,
  });
  const g = new THREE.PlaneGeometry(width, len);
  g.translate(0, -len / 2, 0); // pivot at the top
  const m = new THREE.Mesh(g, mat);
  m.userData.tick = (t) => (mat.uniforms.uTime.value = t);
  return m;
}

/* gather every object carrying userData.tick so a station can drive them in one loop */
export function collectTicks(root) {
  const list = [];
  root.traverse((o) => o.userData && o.userData.tick && list.push(o.userData.tick));
  return list;
}
