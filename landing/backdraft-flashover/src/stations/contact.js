import * as THREE from 'three';
import { C, hdr, smooth, smokeField, emberField, collectTicks } from '../util.js';
import { buildDoor } from '../door.js';

/* Station 6 — the same door, now open onto light: the safe ending */
export function createContact() {
  const group = new THREE.Group();
  const door = buildDoor({ accent: C.cyan, slabOpen: 1.32, floorGlow: true });
  group.add(door.group);
  const haze = smokeField({
    count: 160, box: [12, 4.5, 12], center: [0, 2, 4], size: 3.4, drift: [0.01, 0.02, 0.012],
    lights: [{ pos: [0, 1.3, 0.8], color: C.cyan.clone().multiplyScalar(0.8), radius: 10 }],
    cool: 0x0d1830, warm: C.cyan, opacity: 0.16, seed: 52,
  });
  const motes = emberField({ count: 150, box: [6, 4, 8], center: [0, 1.8, 3], color: C.cyan, size: 0.075, rise: 0.12, seed: 18, hdrK: 2.6 });
  group.add(haze, motes);
  const ticks = collectTicks(group);

  return {
    group,
    update(t, dt, s) {
      const breath = 1 + 0.1 * Math.sin(t * 1.1);
      door.set({ open: 1.32, glow: (0.95 + 0.25 * smooth(0, 1, s.P[7])) * breath, leak: 1, rot: t * 0.02 });
      ticks.forEach((f) => f(t));
    },
  };
}
