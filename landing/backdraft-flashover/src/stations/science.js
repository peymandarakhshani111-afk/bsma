import * as THREE from 'three';
import { C, hdr, lerp, edges, glowTexture, rng, collectTicks, glowPlane } from '../util.js';

/* Station 4 — research: a knowledge "gyroscope" whose four orbiting nodes map to the four findings */
const NODE_COLORS = [C.fire, C.cyan, C.amber, C.lilac];

export function createScience() {
  const group = new THREE.Group();
  const rand = rng(91);

  const core = new THREE.Group();
  const wire = edges(new THREE.IcosahedronGeometry(1.3, 1), C.cyan, 1.5);
  const inner = new THREE.Mesh(new THREE.IcosahedronGeometry(0.62, 1), new THREE.MeshBasicMaterial({ color: hdr(C.amber, 1.6), wireframe: true, toneMapped: false }));
  const heart = new THREE.Mesh(new THREE.SphereGeometry(0.3, 24, 24), new THREE.MeshBasicMaterial({ color: hdr(C.fire, 3), toneMapped: false }));
  const heartGlow = glowPlane(3, 3, C.fire, 1.1);
  core.add(wire, inner, heart);
  group.add(core, heartGlow);

  const ringDefs = [
    { r: 2.1, c: C.cyan, tilt: [0.5, 0, 0.2], sp: 0.18 },
    { r: 2.7, c: C.lilac, tilt: [-0.3, 0.4, 1.0], sp: -0.12 },
    { r: 3.3, c: C.amber, tilt: [1.2, 0.2, -0.4], sp: 0.08 },
  ];
  const rings = ringDefs.map((d) => {
    const m = new THREE.Mesh(new THREE.TorusGeometry(d.r, 0.011, 8, 160), new THREE.MeshBasicMaterial({ color: hdr(d.c, 2.2), toneMapped: false }));
    m.rotation.set(...d.tilt);
    m.userData = d;
    group.add(m);
    return m;
  });

  /* four finding nodes */
  const nodes = NODE_COLORS.map((c, i) => {
    const g = new THREE.Group();
    const body = new THREE.Mesh(new THREE.SphereGeometry(0.2, 24, 24), new THREE.MeshBasicMaterial({ color: hdr(c, 3), toneMapped: false }));
    const halo = glowPlane(1.6, 1.6, c, 1.4);
    g.add(body, halo);
    const lineGeo = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(), new THREE.Vector3()]);
    const line = new THREE.Line(lineGeo, new THREE.LineBasicMaterial({ color: hdr(c, 1.6), transparent: true, opacity: 0.55, toneMapped: false }));
    group.add(g, line);
    return { g, body, halo, line, a: (i / 4) * Math.PI * 2 + 0.4, r: 3.05 + (i % 2) * 0.35, inc: [0.35, -0.5, 0.15, -0.2][i], c, sc: 1 };
  });

  /* dust */
  const N = 520;
  const pos = new Float32Array(N * 3);
  for (let i = 0; i < N; i++) {
    const r = 5 + rand() * 11, a = rand() * Math.PI * 2, b = (rand() - 0.5) * 2.4;
    pos.set([Math.cos(a) * r, b * 3, Math.sin(a) * r - 2], i * 3);
  }
  const dg = new THREE.BufferGeometry();
  dg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  const dust = new THREE.Points(dg, new THREE.PointsMaterial({ color: hdr(C.cyan, 1.3), size: 0.045, sizeAttenuation: true, transparent: true, opacity: 0.55, depthWrite: false, blending: THREE.AdditiveBlending, toneMapped: false }));
  group.add(dust);

  const ticks = collectTicks(group);
  const tmp = new THREE.Vector3();

  return {
    group,
    update(t, dt, s) {
      core.rotation.y = t * 0.2;
      core.rotation.x = t * 0.08;
      inner.rotation.y = -t * 0.45;
      const hb = 1 + 0.12 * Math.sin(t * 2.4);
      heart.scale.setScalar(hb);
      heartGlow.material.opacity = 0.5 + 0.2 * Math.sin(t * 2.4);
      rings.forEach((m) => (m.rotation.z += m.userData.sp * dt));
      dust.rotation.y = t * 0.012;

      const act = s.ui.node;
      nodes.forEach((n, i) => {
        n.a += dt * (0.18 + i * 0.03) * (act === i ? 0.2 : 1);
        const x = Math.cos(n.a) * n.r;
        const z = Math.sin(n.a) * n.r;
        const y = Math.sin(n.a * 1.0 + i) * n.r * n.inc;
        n.g.position.set(x, y, z);
        const want = act === i ? 1.9 : act >= 0 ? 0.7 : 1;
        n.sc += (want - n.sc) * 0.12;
        n.g.scale.setScalar(n.sc * (1 + 0.08 * Math.sin(t * 3 + i)));
        n.halo.quaternion.copy(s.camera.quaternion);
        n.halo.material.opacity = act === i ? 0.95 : 0.5;
        const p = n.line.geometry.attributes.position;
        p.setXYZ(0, 0, 0, 0);
        p.setXYZ(1, x, y, z);
        p.needsUpdate = true;
        n.line.material.opacity = act === i ? 0.95 : 0.35;
      });
      heartGlow.quaternion.copy(s.camera.quaternion);

      group.rotation.y += (s.mouse.x * 0.35 - group.rotation.y) * 0.04;
      group.rotation.x += (-s.mouse.y * 0.12 - group.rotation.x) * 0.04;
      ticks.forEach((f) => f(t));
    },
  };
}
