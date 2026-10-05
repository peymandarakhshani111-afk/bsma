import * as THREE from 'three';
import { C, hdr, clamp, smooth, glowPlane, glowTexture, collectTicks, easeOutCubic } from '../util.js';

/* Station 5 — portfolio. Five products rebuilt from primitives on a lit turntable.
   Order matches the cards in index.html (data-product="0..4"). */

const std = (color, metalness = 0.4, roughness = 0.4, extra = {}) => new THREE.MeshStandardMaterial({ color, metalness, roughness, ...extra });
const RED = () => std(0xc9141b, 0.35, 0.34);
const STEEL = () => std(0xc7cedb, 0.95, 0.28);
const DARK = () => std(0x14171f, 0.6, 0.5);
const GLASS = () => new THREE.MeshStandardMaterial({ color: 0x7fc4ff, metalness: 0.0, roughness: 0.2, transparent: true, opacity: 0.1, depthWrite: false });
const neon = (c, k = 3) => new THREE.MeshBasicMaterial({ color: hdr(c, k), toneMapped: false });

function mesh(geo, mat, x = 0, y = 0, z = 0) {
  const m = new THREE.Mesh(geo, mat);
  m.position.set(x, y, z);
  return m;
}

/* 0 — fire hose cabinet (the company's original product line) */
function fireBox() {
  const g = new THREE.Group();
  const red = RED();
  g.add(mesh(new THREE.BoxGeometry(1.5, 1.95, 0.52), red, 0, 1.02, 0));
  // door frame around a glass window
  const fr = (w, h, x, y) => g.add(mesh(new THREE.BoxGeometry(w, h, 0.06), red, x, y, 0.28));
  fr(1.5, 0.16, 0, 1.94);
  fr(1.5, 0.16, 0, 0.1);
  fr(0.16, 1.95, -0.67, 1.02);
  fr(0.16, 1.95, 0.67, 1.02);
  g.add(mesh(new THREE.PlaneGeometry(1.2, 1.64), GLASS(), 0, 1.02, 0.315));
  // inside
  g.add(mesh(new THREE.BoxGeometry(1.24, 1.68, 0.02), std(0x1b1f2a, 0.3, 0.8), 0, 1.02, -0.22));
  const reel = new THREE.Group();
  reel.position.set(-0.12, 1.1, 0.0);
  for (let i = 0; i < 4; i++) reel.add(mesh(new THREE.TorusGeometry(0.2 + i * 0.075, 0.042, 12, 48), std(0xe9edf5, 0.1, 0.7)));
  reel.add(mesh(new THREE.CylinderGeometry(0.1, 0.1, 0.36, 24), STEEL()).rotateX(Math.PI / 2));
  reel.add(mesh(new THREE.CylinderGeometry(0.46, 0.46, 0.03, 40), DARK()).rotateX(Math.PI / 2));
  g.add(reel);
  const noz = mesh(new THREE.CylinderGeometry(0.03, 0.055, 0.34, 16), std(0xb2791b, 0.9, 0.3), 0.5, 0.52, 0.05);
  noz.rotation.z = 0.35;
  g.add(noz);
  g.add(mesh(new THREE.CylinderGeometry(0.018, 0.018, 0.34, 12), STEEL(), 0.55, 1.02, 0.35));
  // label strip with a cyan edge light
  g.add(mesh(new THREE.BoxGeometry(1.2, 0.05, 0.02), neon(C.cyan, 2.4), 0, 1.86, 0.33));
  g.userData.scale = 1.0;
  return g;
}

/* 1 — powder / CO₂ extinguisher */
function extinguisher() {
  const g = new THREE.Group();
  const red = RED();
  g.add(mesh(new THREE.CylinderGeometry(0.3, 0.3, 1.3, 48), red, 0, 0.72, 0));
  const dome = mesh(new THREE.SphereGeometry(0.3, 48, 20, 0, Math.PI * 2, 0, Math.PI / 2), red, 0, 1.37, 0);
  g.add(dome);
  g.add(mesh(new THREE.SphereGeometry(0.3, 48, 12, 0, Math.PI * 2, Math.PI / 2, Math.PI / 2).scale(1, 0.35, 1), red, 0, 0.07, 0));
  g.add(mesh(new THREE.CylinderGeometry(0.12, 0.14, 0.14, 24), STEEL(), 0, 1.6, 0));
  // valve head, lever, gauge
  g.add(mesh(new THREE.BoxGeometry(0.36, 0.12, 0.14), STEEL(), 0.02, 1.7, 0));
  const lever = mesh(new THREE.BoxGeometry(0.4, 0.035, 0.1), DARK(), 0.05, 1.78, 0);
  lever.rotation.z = 0.12;
  g.add(lever);
  const gauge = mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.04, 32), std(0xf2f4f8, 0.2, 0.4), -0.16, 1.73, 0.1);
  gauge.rotation.x = Math.PI / 2;
  g.add(gauge);
  g.add(mesh(new THREE.BoxGeometry(0.012, 0.06, 0.01), neon(C.fire, 3), -0.16, 1.745, 0.125));
  // hose
  const curve = new THREE.CatmullRomCurve3([new THREE.Vector3(0.18, 1.68, 0.05), new THREE.Vector3(0.38, 1.55, 0.12), new THREE.Vector3(0.42, 1.0, 0.28), new THREE.Vector3(0.32, 0.55, 0.36), new THREE.Vector3(0.2, 0.46, 0.34)]);
  g.add(mesh(new THREE.TubeGeometry(curve, 48, 0.028, 10), DARK()));
  const horn = mesh(new THREE.CylinderGeometry(0.02, 0.07, 0.2, 16), DARK(), 0.15, 0.44, 0.32);
  horn.rotation.z = Math.PI / 2 + 0.2;
  g.add(horn);
  // label
  g.add(mesh(new THREE.CylinderGeometry(0.306, 0.306, 0.42, 48, 1, true), std(0xf1ece0, 0.05, 0.8, { side: THREE.DoubleSide }), 0, 0.75, 0));
  g.add(mesh(new THREE.CylinderGeometry(0.31, 0.31, 0.05, 48, 1, true), neon(C.cyan, 2.2), 0, 0.97, 0));
  g.add(mesh(new THREE.CylinderGeometry(0.31, 0.31, 0.05, 48, 1, true), neon(C.fire, 2.2), 0, 0.54, 0));
  g.userData.scale = 1.05;
  return g;
}

/* 2 — portable axial smoke-extraction fan (ARIS) */
function fan() {
  const g = new THREE.Group();
  const alu = std(0xb9c2cf, 0.95, 0.32);
  const red = RED();
  const ring = mesh(new THREE.TorusGeometry(0.78, 0.085, 24, 80), alu, 0, 1.0, 0);
  g.add(ring);
  const shroud = mesh(new THREE.CylinderGeometry(0.78, 0.78, 0.42, 64, 1, true), std(0x9ea8b8, 0.9, 0.4, { side: THREE.DoubleSide }), 0, 1.0, 0);
  shroud.rotation.x = Math.PI / 2;
  g.add(shroud);
  const rot = new THREE.Group();
  rot.position.set(0, 1.0, 0);
  rot.add(mesh(new THREE.CylinderGeometry(0.15, 0.15, 0.3, 32), red).rotateX(Math.PI / 2));
  for (let i = 0; i < 7; i++) {
    const a = (i / 7) * Math.PI * 2;
    const b = mesh(new THREE.BoxGeometry(0.58, 0.19, 0.018), std(0x1b2030, 0.5, 0.5), Math.cos(a) * 0.43, Math.sin(a) * 0.43, 0);
    b.rotation.z = a;
    b.rotateX(0.5);
    rot.add(b);
  }
  g.add(rot);
  g.userData.spin = rot;
  // motor housing + protective guard
  g.add(mesh(new THREE.CylinderGeometry(0.2, 0.22, 0.34, 32), red, 0, 1.0, -0.3).rotateX(Math.PI / 2));
  const guardMat = std(0x2a2f3c, 0.8, 0.4);
  for (let i = 1; i <= 3; i++) g.add(mesh(new THREE.TorusGeometry(0.22 * i, 0.012, 8, 64), guardMat, 0, 1.0, 0.24));
  for (let i = 0; i < 12; i++) {
    const a = (i / 12) * Math.PI * 2;
    const sp = mesh(new THREE.CylinderGeometry(0.008, 0.008, 0.72, 6), guardMat, Math.cos(a) * 0.36, 1.0 + Math.sin(a) * 0.36, 0.24);
    sp.rotation.z = a + Math.PI / 2;
    g.add(sp);
  }
  // frame, handle, wheels
  const frame = mesh(new THREE.TorusGeometry(0.9, 0.03, 8, 40, Math.PI), STEEL(), 0, 1.0, -0.12);
  g.add(frame);
  g.add(mesh(new THREE.BoxGeometry(1.7, 0.06, 0.08), STEEL(), 0, 0.11, -0.12));
  for (const sx of [-1, 1]) {
    g.add(mesh(new THREE.CylinderGeometry(0.11, 0.11, 0.07, 24), DARK(), sx * 0.82, 0.12, -0.12).rotateZ(Math.PI / 2));
    g.add(mesh(new THREE.CylinderGeometry(0.02, 0.02, 0.92, 8), STEEL(), sx * 0.78, 0.58, -0.12));
  }
  g.add(mesh(new THREE.TorusGeometry(0.78, 0.014, 8, 80), neon(C.cyan, 2.6), 0, 1.0, 0.2));
  g.userData.scale = 0.95;
  return g;
}

/* 3 — smoke-stop / fire-rated door */
function fireDoor() {
  const g = new THREE.Group();
  const frameM = std(0x2b303c, 0.8, 0.4);
  const fp = (w, h, x, y) => g.add(mesh(new THREE.BoxGeometry(w, h, 0.2), frameM, x, y, 0));
  fp(0.12, 2.3, -0.62, 1.15);
  fp(0.12, 2.3, 0.62, 1.15);
  fp(1.36, 0.12, 0, 2.3);
  const slab = new THREE.Group();
  slab.position.set(-0.56, 0, 0);
  slab.add(mesh(new THREE.BoxGeometry(1.12, 2.2, 0.085), std(0x9aa3b3, 0.8, 0.38), 0.56, 1.1, 0));
  // vision panel
  slab.add(mesh(new THREE.BoxGeometry(0.42, 0.62, 0.095), DARK(), 0.56, 1.5, 0));
  slab.add(mesh(new THREE.PlaneGeometry(0.34, 0.54), GLASS(), 0.56, 1.5, 0.05));
  // handle
  const h = mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.24, 10), STEEL(), 0.97, 1.05, 0.08);
  h.rotation.z = Math.PI / 2;
  slab.add(h);
  slab.add(mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.03, 20), STEEL(), 1.02, 1.05, 0.05).rotateX(Math.PI / 2));
  // hinges
  [0.35, 1.1, 1.85].forEach((y) => slab.add(mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.2, 12), STEEL(), 0, y, 0.06)));
  // smoke seal (glows so the idea reads in the dark)
  const sealM = neon(C.cyan, 1.8);
  slab.add(mesh(new THREE.BoxGeometry(0.014, 2.2, 0.012), sealM, 1.12, 1.1, -0.052));
  slab.add(mesh(new THREE.BoxGeometry(1.12, 0.014, 0.012), sealM, 0.56, 2.2, -0.052));
  slab.add(mesh(new THREE.BoxGeometry(1.12, 0.014, 0.012), sealM, 0.56, 0.02, -0.052));
  g.add(slab);
  g.userData.slab = slab;
  g.userData.scale = 0.88;
  return g;
}

/* 4 — addressable detector + control panel */
function alarm() {
  const g = new THREE.Group();
  const white = std(0xf1f3f7, 0.1, 0.45);
  // control panel
  const panel = new THREE.Group();
  panel.position.set(-0.45, 0, -0.1);
  panel.add(mesh(new THREE.BoxGeometry(1.1, 1.4, 0.24), std(0x39404f, 0.55, 0.45), 0, 1.0, 0));
  panel.add(mesh(new THREE.BoxGeometry(0.96, 1.26, 0.02), DARK(), 0, 1.0, 0.125));
  panel.add(mesh(new THREE.PlaneGeometry(0.76, 0.34), new THREE.MeshBasicMaterial({ color: hdr(new THREE.Color(0.2, 1, 0.55), 1.6), toneMapped: false }), 0, 1.38, 0.14));
  for (let r = 0; r < 3; r++) for (let c = 0; c < 4; c++) panel.add(mesh(new THREE.BoxGeometry(0.15, 0.1, 0.03), std(0xcfd5e0, 0.2, 0.5), -0.3 + c * 0.2, 0.95 - r * 0.17, 0.14));
  [C.fire, C.amber, new THREE.Color(0.2, 1, 0.5)].forEach((c, i) => panel.add(mesh(new THREE.SphereGeometry(0.03, 12, 12), neon(c, 3), -0.3 + i * 0.12, 0.62, 0.14)));
  g.add(panel);
  // detector
  const det = new THREE.Group();
  det.position.set(0.62, 0.56, 0.3);
  det.add(mesh(new THREE.CylinderGeometry(0.42, 0.44, 0.07, 56), white, 0, 0, 0));
  det.add(mesh(new THREE.SphereGeometry(0.4, 56, 20, 0, Math.PI * 2, 0, Math.PI / 2).scale(1, 0.42, 1), white, 0, 0.03, 0));
  for (let i = 0; i < 18; i++) {
    const a = (i / 18) * Math.PI * 2;
    const s = mesh(new THREE.BoxGeometry(0.05, 0.02, 0.1), std(0x2a2e38, 0.2, 0.7), Math.cos(a) * 0.3, 0.09, Math.sin(a) * 0.3);
    s.rotation.y = -a;
    det.add(s);
  }
  const led = mesh(new THREE.SphereGeometry(0.035, 16, 16), neon(C.fire, 4), 0, 0.2, 0);
  det.add(led);
  g.userData.led = led;
  det.rotation.x = -0.9;
  g.add(det);
  g.userData.scale = 0.9;
  return g;
}

export function createProducts(env) {
  const group = new THREE.Group();

  /* turntable */
  const disc = mesh(new THREE.CylinderGeometry(2.4, 2.5, 0.14, 96), std(0x0b0e18, 0.85, 0.35), 0, 0, 0);
  group.add(disc);
  const rim = mesh(new THREE.TorusGeometry(2.42, 0.026, 12, 160), neon(C.cyan, 3), 0, 0.075, 0);
  rim.rotation.x = Math.PI / 2;
  const rim2 = mesh(new THREE.TorusGeometry(1.75, 0.012, 8, 140), neon(C.fire, 2.4), 0, 0.078, 0);
  rim2.rotation.x = Math.PI / 2;
  group.add(rim, rim2);
  // soft contact shadow
  const sh = new THREE.Mesh(
    new THREE.PlaneGeometry(3.2, 3.2),
    new THREE.MeshBasicMaterial({ map: glowTexture(128, [[0, 'rgba(0,0,0,.85)'], [0.6, 'rgba(0,0,0,.35)'], [1, 'rgba(0,0,0,0)']]), transparent: true, depthWrite: false, color: 0x000000 })
  );
  sh.rotation.x = -Math.PI / 2;
  sh.position.y = 0.08;
  group.add(sh);
  // light cone
  const cone = new THREE.Mesh(
    new THREE.CylinderGeometry(0.55, 2.3, 4.2, 48, 1, true),
    new THREE.ShaderMaterial({
      transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide,
      uniforms: { uC: { value: hdr(C.cyan, 0.9) } },
      vertexShader: `varying float vY; varying vec3 vN; void main(){ vY = uv.y; vN = normalize(normalMatrix * normal); gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.); }`,
      fragmentShader: `uniform vec3 uC; varying float vY; varying vec3 vN; void main(){ float f = pow(abs(vN.z), 1.6); gl_FragColor = vec4(uC, (1. - vY) * .06 * (.3 + f)); }`,
    })
  );
  cone.position.y = 2.3;
  group.add(cone);
  const backGlow = glowPlane(11, 6, C.cyan, 0.35);
  backGlow.position.set(0, 2.2, -3.4);
  group.add(backGlow);

  /* models */
  const builders = [fireBox, extinguisher, fan, fireDoor, alarm];
  const models = builders.map((b) => {
    const m = b();
    m.traverse((o) => {
      if (o.isMesh && o.material && o.material.isMeshStandardMaterial && env) {
        o.material.envMap = env;
        o.material.envMapIntensity = 0.45;
      }
    });
    const slot = new THREE.Group();
    slot.add(m);
    slot.position.y = 0.075;
    slot.visible = false;
    group.add(slot);
    return { slot, m, sc: m.userData.scale || 1 };
  });

  group.add(new THREE.HemisphereLight(0x405080, 0x05060a, 0.45));
  const key = new THREE.SpotLight(0xfff0e0, 38, 20, 0.55, 0.6, 1.4);
  key.position.set(2.5, 6, 3.5);
  key.target.position.set(0, 1, 0);
  const rimL = new THREE.PointLight(0x27e6ff, 18, 14, 1.5);
  rimL.position.set(-3.5, 2.5, -2.5);
  const warmL = new THREE.PointLight(0xff6a1a, 14, 14, 1.5);
  warmL.position.set(3.4, 1.2, -1.2);
  group.add(key, key.target, rimL, warmL);

  const ticks = collectTicks(group);
  let cur = -1;
  let want = 0;
  let phase = 'in';
  let ph = 1;
  let spin = 0.4;
  let drag = 0;
  let vel = 0;

  function show(i) {
    models.forEach((m, k) => (m.slot.visible = k === i));
    cur = i;
  }

  return {
    group,
    update(t, dt, s) {
      want = s.ui.product;
      if (cur === -1) {
        show(want);
        phase = 'in';
        ph = 0;
      }
      if (want !== cur && phase !== 'out') {
        phase = 'out';
        ph = 0;
      }
      const cm = models[cur];
      // in photo mode the real product photographs (HTML layer) are shown, so the primitive model stays hidden
      cm.slot.visible = !s.ui.photoMode;
      ph = Math.min(1, ph + dt / (phase === 'out' ? 0.28 : 0.6));
      if (phase === 'out') {
        const k = 1 - ph;
        cm.slot.scale.setScalar(Math.max(0.001, k * cm.sc));
        spin += dt * 9 * ph;
        if (ph >= 1) {
          show(want);
          phase = 'in';
          ph = 0;
        }
      } else {
        const e = easeOutCubic(ph);
        const back = 1 + 0.5 * Math.sin(Math.PI * Math.min(1, ph * 1.1)) * (1 - ph) * 0.9;
        cm.slot.scale.setScalar(Math.max(0.001, e * cm.sc * back));
      }

      /* turntable spin: idle spin + user drag with inertia */
      if (s.ui.dragging) {
        vel = s.ui.dragVel;
      } else {
        vel *= 0.94;
        spin += dt * 0.35;
      }
      drag += vel * dt;
      s.ui.dragVel = 0;
      cm.slot.rotation.y = spin + drag + s.ui.dragRot;

      /* per-product animation */
      const spinner = cm.m.userData.spin;
      if (spinner) spinner.rotation.z -= dt * 14;
      if (cm.m.userData.slab) cm.m.userData.slab.rotation.y = -0.35 - 0.2 * Math.sin(t * 0.8);
      if (cm.m.userData.led) cm.m.userData.led.visible = Math.sin(t * 5) > -0.2;

      rim.material.color.copy(hdr(C.cyan, 2.4 + 0.9 * Math.sin(t * 1.6)));
      cone.material.uniforms.uC.value.copy(hdr(C.cyan, 0.8 + 0.2 * Math.sin(t * 0.9)));
      ticks.forEach((f) => f(t));
    },
  };
}
