import * as THREE from 'three';
import { C, smokeField, emberField, collectTicks, smooth } from '../util.js';
import { buildDoor } from '../door.js';

/* Station 0 — the closed door that breathes */
export function createHero() {
  const group = new THREE.Group();
  const door = buildDoor({ accent: C.fire });
  group.add(door.group);

  const smoke = smokeField({
    count: 280, box: [14, 5, 12], center: [0, 2.2, 4.5], size: 3.8, drift: [0.012, 0.03, 0.008],
    lights: [{ pos: [0, 1.3, 0.8], color: C.fire.clone().multiplyScalar(0.9), radius: 10 }],
    cool: 0x1b2438, opacity: 0.12, seed: 12,
  });
  const embers = emberField({ count: 150, box: [5, 3.6, 4], center: [0, 1.7, 1.6], size: 0.085, seed: 4 });
  group.add(smoke, embers);

  const ticks = collectTicks(group);

  return {
    group,
    update(t, dt, s) {
      // the building "breathes": slow swell + quick flutter, a hint of what is coming
      const heat = 1 + 0.45 * s.P[0];
      const breath = 0.82 + 0.2 * Math.sin(t * 1.25) + 0.06 * Math.sin(t * 7.3);
      door.set({ open: 0, glow: breath * heat, leak: breath, rot: t * 0.03 });
      door.pivot.rotation.y = -0.0035 * Math.sin(t * 8.5) * (0.4 + 0.6 * s.P[0]);
      door.light.intensity *= 0.95 + 0.1 * Math.sin(t * 17);
      smoke.material.uniforms.uBoost.value = 0.7 + 0.5 * s.P[0];
      ticks.forEach((f) => f(t));
    },
  };
}
