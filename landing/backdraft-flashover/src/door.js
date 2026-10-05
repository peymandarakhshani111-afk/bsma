import * as THREE from 'three';
import { C, gridFloor, neonBar, glowPlane, glowTexture, rng, hdr } from './util.js';

/* radial "god-ray" sprite texture */
function raysTexture(size = 512) {
  const c = document.createElement('canvas');
  c.width = c.height = size;
  const g = c.getContext('2d');
  const r = rng(21);
  g.translate(size / 2, size / 2);
  for (let i = 0; i < 46; i++) {
    const a = r() * Math.PI * 2;
    const w = 0.006 + r() * 0.03;
    const len = size * (0.28 + r() * 0.22);
    const grd = g.createLinearGradient(0, 0, Math.cos(a) * len, Math.sin(a) * len);
    grd.addColorStop(0, `rgba(255,255,255,${0.12 + r() * 0.22})`);
    grd.addColorStop(1, 'rgba(255,255,255,0)');
    g.fillStyle = grd;
    g.beginPath();
    g.moveTo(0, 0);
    g.lineTo(Math.cos(a - w) * len, Math.sin(a - w) * len);
    g.lineTo(Math.cos(a + w) * len, Math.sin(a + w) * len);
    g.closePath();
    g.fill();
  }
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

let _rays;
const rays = () => (_rays ||= raysTexture());

/**
 * A hallway wall with a single door. `accent` tints the door light
 * (fire = orange, safe = cyan). Everything is procedural.
 */
export function buildDoor({ accent = C.fire, hall = true, slabOpen = 0, floorGlow = true } = {}) {
  const g = new THREE.Group();
  const ticks = [];

  /* back wall */
  const wall = new THREE.Mesh(
    new THREE.BoxGeometry(16, 7, 0.4),
    new THREE.MeshStandardMaterial({ color: 0x0b0e18, roughness: 0.85, metalness: 0.2 })
  );
  wall.position.set(0, 3.5, -0.32);
  g.add(wall);

  /* architrave: dark frame + neon inner edge */
  const frameMat = new THREE.MeshStandardMaterial({ color: 0x141926, roughness: 0.5, metalness: 0.7 });
  const post = (x, y, w, h) => {
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, 0.22), frameMat);
    m.position.set(x, y, 0.0);
    g.add(m);
  };
  post(-0.7, 1.2, 0.2, 2.5);
  post(0.7, 1.2, 0.2, 2.5);
  post(0, 2.45, 1.6, 0.2);

  const neonL = neonBar(0.045, 2.4, 0.05, accent, 3.4);
  neonL.position.set(-0.58, 1.2, 0.1);
  const neonR = neonBar(0.045, 2.4, 0.05, accent, 3.4);
  neonR.position.set(0.58, 1.2, 0.1);
  const neonT = neonBar(1.2, 0.045, 0.05, accent, 3.4);
  neonT.position.set(0, 2.38, 0.1);
  g.add(neonL, neonR, neonT);

  /* interior light visible when the door opens */
  const interior = glowPlane(1.12, 2.3, accent, 2.2, glowTexture(64, [[0, 'rgba(255,255,255,1)'], [1, 'rgba(255,255,255,.55)']]));
  interior.position.set(0, 1.18, -0.12);
  g.add(interior);

  /* slab on a hinge (hinge at x = -0.55) */
  const pivot = new THREE.Group();
  pivot.position.set(-0.55, 0, 0.03);
  const slab = new THREE.Mesh(
    new THREE.BoxGeometry(1.1, 2.34, 0.07),
    new THREE.MeshStandardMaterial({ color: 0x161b29, roughness: 0.4, metalness: 0.75 })
  );
  slab.position.set(0.55, 1.18, 0);
  pivot.add(slab);
  const handle = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.018, 0.22, 12), new THREE.MeshStandardMaterial({ color: 0xc9d2e4, metalness: 1, roughness: 0.25 }));
  handle.rotation.z = Math.PI / 2;
  handle.position.set(0.95, 1.1, 0.09);
  pivot.add(handle);
  // light leaking around the closed slab
  const leakMat = hdr(accent, 5);
  const leak = (w, h, x, y) => {
    const b = new THREE.Mesh(new THREE.BoxGeometry(w, h, 0.012), new THREE.MeshBasicMaterial({ color: leakMat, toneMapped: false }));
    b.position.set(x, y, 0.042);
    pivot.add(b);
    return b;
  };
  const leaks = [leak(0.014, 2.34, 1.1, 1.18), leak(1.1, 0.014, 0.55, 2.35), leak(1.1, 0.012, 0.55, 0.02)];
  g.add(pivot);

  /* halo, rays and anamorphic streak (all additive sprites) */
  const halo = glowPlane(7.5, 7.5, accent, 1.0);
  halo.position.set(0, 1.3, 0.16);
  g.add(halo);

  const rayMat = new THREE.MeshBasicMaterial({ map: rays(), color: hdr(accent, 1.4), transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, toneMapped: false });
  const rayMesh = new THREE.Mesh(new THREE.PlaneGeometry(14, 14), rayMat);
  rayMesh.position.set(0, 1.3, 0.2);
  g.add(rayMesh);

  const streak = glowPlane(16, 0.9, accent, 1.0);
  streak.position.set(0, 1.25, 0.22);
  g.add(streak);

  /* hall: floor grid, pillars, ceiling beams */
  if (hall) {
    const floorLen = 52;
    const fl = gridFloor({ size: [44, floorLen], color: C.cyan, glowColor: accent, glowPos: [0, 8], glowRadius: 9, fadeRadius: 26 });
    fl.position.set(0, 0.002, 8);
    // the plane is rotated flat: local y maps to world -z, so a glow at world z=0 sits at local y = plane z
    if (!floorGlow) fl.material.uniforms.uGlowK.value = 0;
    g.add(fl);
    g.userData.floor = fl;
    ticks.push(fl);

    const pillarMat = new THREE.MeshStandardMaterial({ color: 0x0e121d, roughness: 0.6, metalness: 0.6 });
    for (let i = 0; i < 6; i++) {
      const z = 2.4 + i * 3.4;
      for (const sx of [-1, 1]) {
        const p = new THREE.Mesh(new THREE.BoxGeometry(0.45, 5.6, 0.45), pillarMat);
        p.position.set(sx * 3.6, 2.8, z);
        g.add(p);
        const n = neonBar(0.02, 5.4, 0.02, C.cyan, 0.9);
        n.position.set(sx * 3.36, 2.8, z);
        g.add(n);
      }
      const beam = new THREE.Mesh(new THREE.BoxGeometry(7.8, 0.22, 0.32), pillarMat);
      beam.position.set(0, 5.5, z);
      g.add(beam);
      const bn = neonBar(7.4, 0.02, 0.02, C.cyan, 0.8);
      bn.position.set(0, 5.37, z);
      g.add(bn);
    }
  }

  const light = new THREE.PointLight(accent.clone(), 30, 24, 1.6);
  light.position.set(0, 1.4, 1.4);
  g.add(light);

  pivot.rotation.y = -slabOpen;

  return {
    group: g,
    pivot,
    light,
    halo,
    rayMesh,
    streak,
    interior,
    leaks,
    ticks,
    /* open: radians, glow: 0..1.5 multiplier */
    set({ open = 0, glow = 1, leak = 1, rot = 0 }) {
      pivot.rotation.y = -open;
      const o = Math.min(1, open / 0.35);
      leaks.forEach((l) => (l.visible = open < 0.5));
      halo.material.opacity = 0.2 * glow;
      rayMat.opacity = 0.2 * glow * (0.6 + 0.4 * leak);
      streak.material.opacity = 0.16 * glow;
      interior.material.opacity = THREE.MathUtils.clamp(o * glow, 0, 1);
      interior.visible = o > 0.01;
      light.intensity = 14 * glow;
      rayMesh.rotation.z = rot;
    },
    tick(t) {
      ticks.forEach((m) => m.userData.tick && m.userData.tick(t));
    },
  };
}
