import * as THREE from 'three';
import { C, hdr, clamp, smooth, map01, easeOutCubic, easeInOut, glowTexture, rng, registerPoints, collectTicks, GLSL_NOISE, smokeField, glowPlane } from '../util.js';
import { buildDoor } from '../door.js';

/* Station 2 — backdraft. One door, one second of physics, stretched across ~5 screens of scroll.
   phase A  0.00–0.22  tension: the door "breathes", smoke pulses at the gaps
   phase B  0.22–0.42  the door opens: cool air in at the bottom, hot fuel-rich smoke out at the top
   phase C  0.42–0.54  the inhale: a spark meets the mixture
   phase D  0.54–0.86  deflagration: fireball + pressure ring out of the opening
   phase E  0.86–1.00  aftermath */

/* generic "flow" points: the path is supplied as a GLSL snippet f(progress, seed) → position */
function flow({ count, path, color, size, seed }) {
  const rand = rng(seed);
  const sd = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) sd.set([rand(), rand(), rand()], i * 3);
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(count * 3), 3));
  geo.setAttribute('aSeed', new THREE.BufferAttribute(sd, 3));
  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uScale: { value: 600 }, uAmt: { value: 0 }, uColor: { value: color }, uSize: { value: size }, uTex: { value: glowTexture(64) } },
    vertexShader: /* glsl */ `
      attribute vec3 aSeed; uniform float uTime, uScale, uAmt, uSize; varying float vA; varying float vF;
      vec3 pathAt(float f, vec3 s){ ${path} }
      void main(){
        float f = fract(aSeed.x + uTime * (.16 + aSeed.y * .12));
        vec3 p = pathAt(f, aSeed);
        vec4 mv = modelViewMatrix * vec4(p, 1.);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = uSize * (.5 + aSeed.z) * uScale / max(.1, -mv.z);
        vF = f;
        vA = step(aSeed.y, uAmt) * smoothstep(0., .1, f) * (1. - smoothstep(.65, 1., f));
      }`,
    fragmentShader: `uniform sampler2D uTex; uniform vec3 uColor; varying float vA; void main(){ gl_FragColor = vec4(uColor, texture2D(uTex, gl_PointCoord).a * vA); }`,
  });
  const p = new THREE.Points(geo, mat);
  p.frustumCulled = false;
  registerPoints(p, count);
  return p;
}

export function createBackdraft() {
  const group = new THREE.Group();
  const door = buildDoor({ accent: C.fire });
  group.add(door.group);
  const floorU = door.group.userData.floor.material.uniforms;

  /* interior: fire-lit smoke seen through the doorway */
  const interiorMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uA: { value: 0 }, uHot: { value: 0 } },
    vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.); }`,
    fragmentShader: /* glsl */ `
      ${GLSL_NOISE}
      uniform float uTime, uA, uHot; varying vec2 vUv;
      void main(){
        vec3 p = vec3(vUv * vec2(1.6, 3.2), uTime * .25);
        float n = fbm(p * 1.8 + vec3(0., -uTime * .35, 0.));
        float rollers = smoothstep(.25, .85, n);
        vec3 c = mix(vec3(.25, .04, .01), vec3(1., .3, .05), rollers) * (.9 + 1.5 * uHot);
        c = mix(c, vec3(1., .7, .3) * 1.8, uHot * rollers * .45);
        float edge = smoothstep(0., .08, vUv.x) * smoothstep(0., .08, 1. - vUv.x) * smoothstep(0., .04, 1. - vUv.y);
        gl_FragColor = vec4(c, uA * edge * (.55 + .45 * rollers));
      }`,
  });
  door.interior.material = interiorMat;
  door.interior.position.z = -0.1;

  /* smoke that pulses at the gaps while the door is still shut */
  const gapSmoke = smokeField({
    count: 90, box: [1.8, 2.8, 1.6], center: [0, 1.3, 0.55], size: 1.8, drift: [0, 0.05, 0.02],
    lights: [{ pos: [0, 1.2, 0.2], color: C.fire, radius: 4 }], cool: 0x3a2a22, opacity: 0.16, seed: 31,
  });
  group.add(gapSmoke);

  /* smoke that rolls out once the door is open, aftermath smoke */
  const roll = smokeField({
    count: 220, box: [9, 4.2, 9], center: [0, 2.0, 3.2], size: 3.6, drift: [0, 0.05, 0.04],
    lights: [{ pos: [0, 1.1, 0.6], color: C.fire, radius: 9 }], cool: 0x1c2234, opacity: 0.22, seed: 14,
  });
  group.add(roll);

  /* cool air in (cyan), hot gas out (orange) */
  const airIn = flow({
    count: 120, color: new THREE.Color(0.1, 0.9, 1).multiplyScalar(1.7), size: 0.045, seed: 5,
    path: `return vec3((s.y - .5) * 1.7 * (1. - f * .55), .06 + s.z * .5 * (.2 + f) + sin(f * 10. + s.x * 9.) * .03, 7. - f * 7.6);`,
  });
  const hotOut = flow({
    count: 140, color: new THREE.Color(1, .4, .1).multiplyScalar(1.5), size: 0.07, seed: 6,
    path: `return vec3((s.y - .5) * 1.0 * (1. + f * 2.2), 1.7 + s.z * .55 + f * (.9 + s.x), -.1 + f * 5.2);`,
  });
  group.add(airIn, hotOut);

  /* the spark: an ember glowing inside the doorway */
  const spark = glowPlane(0.5, 0.5, C.amber, 3.2);
  spark.position.set(0.2, 0.95, -0.05);
  group.add(spark);

  /* fireball (displaced icosphere with a fire ramp) + core + pressure ring + flash */
  const fbMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uLife: { value: 0 } },
    vertexShader: /* glsl */ `
      ${GLSL_NOISE}
      uniform float uTime, uLife; varying vec3 vN, vP;
      void main(){
        vec3 p = position;
        float n = fbm(p * 1.7 + vec3(0., -uTime * .9, 0.));
        p += normal * (n - .5) * (.45 + uLife * .6);
        vP = p; vN = normalize(normalMatrix * normal);
        gl_Position = projectionMatrix * modelViewMatrix * vec4(p, 1.);
      }`,
    fragmentShader: /* glsl */ `
      ${GLSL_NOISE}
      uniform float uTime, uLife; varying vec3 vN, vP;
      void main(){
        float fres = pow(1. - abs(normalize(vN).z), 1.4);
        float n = fbm(vP * 2.3 + vec3(0., -uTime * 1.3, 0.));
        float heat = clamp(.95 - uLife * .95 + (n - .5) * 1.25 + (1. - fres) * .12, 0., 1.);
        vec3 c = mix(vec3(.2, .02, .0), vec3(1., .24, .03), smoothstep(0., .5, heat));
        c = mix(c, vec3(1., .6, .16), smoothstep(.55, .92, heat));
        float a = (.35 + .65 * (1. - fres)) * smoothstep(0., .08, uLife) * (1. - smoothstep(.6, 1., uLife));
        gl_FragColor = vec4(c * (.5 + .75 * heat), a * .9);
      }`,
  });
  const fireball = new THREE.Mesh(new THREE.IcosahedronGeometry(1, 28), fbMat);
  fireball.visible = false;
  group.add(fireball);

  const ringMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide,
    uniforms: { uA: { value: 0 } },
    vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.); }`,
    fragmentShader: `uniform float uA; varying vec2 vUv; void main(){ float r = length(vUv - .5) * 2.; float ring = smoothstep(.78, .93, r) * (1. - smoothstep(.93, 1., r)); gl_FragColor = vec4(vec3(1., .75, .45) * 2.6, ring * uA); }`,
  });
  const ring = new THREE.Mesh(new THREE.PlaneGeometry(2, 2), ringMat);
  ring.position.set(0, 1.2, 0.4);
  ring.visible = false;
  group.add(ring);

  const flash = glowPlane(8, 8, C.amber, 2.6);
  flash.position.set(0, 1.2, 0.7);
  flash.visible = false;
  group.add(flash);

  const fbLight = new THREE.PointLight(0xff7a22, 0, 30, 1.3);
  fbLight.position.set(0, 1.3, 1.6);
  group.add(fbLight);

  const ticks = collectTicks(group);
  let lastP = 0;
  let boom = 0;

  return {
    group,
    update(t, dt, s) {
      // the cinematic part occupies the first 64% of the chapter; the rest is the aftermath text
      const P = Math.min(1, s.P[3] / 0.64);

      /* cues (forward crossings only) */
      if (lastP < 0.22 && P >= 0.22 && P < 0.5 && P - lastP < 0.1) s.cue && s.cue('doorOpen');
      if (lastP < 0.545 && P >= 0.545 && P < 0.7 && P - lastP < 0.1) {
        s.cue && s.cue('fireball');
        boom = 1;
      }
      lastP = P;
      boom = Math.max(0, boom - dt * 1.2);

      /* choreography */
      const open = easeInOut(map01(P, 0.22, 0.44)) * 1.38;
      const inhale = smooth(0.24, 0.34, P) * (1 - smooth(0.5, 0.555, P));
      const exhaust = smooth(0.26, 0.38, P) * (1 - smooth(0.52, 0.6, P)) + 0.25 * smooth(0.6, 0.9, P);
      const sparkA = smooth(0.4, 0.5, P) * (1 - smooth(0.545, 0.575, P));
      const life = map01(P, 0.545, 0.88);
      const fe = easeOutCubic(map01(P, 0.545, 0.82));
      const ringT = map01(P, 0.55, 0.75);
      const tension = smooth(0, 0.22, P) * (1 - smooth(0.22, 0.3, P));
      const pulse = 0.5 + 0.5 * Math.sin(t * (2.2 + tension * 5));

      const baseGlow = 0.5 + 0.3 * smooth(0, 0.22, P) + 0.5 * smooth(0.3, 0.5, P);
      const bump = boom > 0 ? boom : 0;
      door.set({ open, glow: baseGlow * (1 + 0.7 * bump) * (0.85 + 0.15 * pulse), leak: 0.6 + 0.4 * pulse, rot: t * 0.05 });
      door.pivot.rotation.y = -open - (P < 0.22 ? 0.004 * Math.sin(t * 11) * tension : 0);

      interiorMat.uniforms.uTime.value = t;
      interiorMat.uniforms.uA.value = smooth(0.2, 0.44, P) * (1 - smooth(0.62, 0.8, P) * 0.6);
      interiorMat.uniforms.uHot.value = smooth(0.42, 0.55, P) * (1 - smooth(0.6, 0.9, P));

      gapSmoke.material.uniforms.uBoost.value = (1 - smooth(0.26, 0.4, P)) * (0.35 + 1.1 * pulse * tension + 0.3 * smooth(0, 0.2, P));
      roll.material.uniforms.uBoost.value = 0.25 + 0.6 * smooth(0.3, 0.6, P) + 0.5 * smooth(0.6, 0.9, P);

      airIn.material.uniforms.uAmt.value = inhale;
      hotOut.material.uniforms.uAmt.value = exhaust;

      spark.visible = sparkA > 0.01;
      const sp = 0.4 + 1.8 * sparkA * (0.7 + 0.3 * Math.sin(t * 40));
      spark.scale.setScalar(sp);
      spark.material.opacity = sparkA;

      /* fireball */
      fireball.visible = life > 0 && life < 1;
      if (fireball.visible) {
        const sc = 0.25 + 2.2 * fe;
        fireball.scale.set(sc * 0.95, sc * 0.85, sc);
        fireball.position.set(0, 1.15 + 0.3 * fe, -0.25 + 2.5 * fe);
        fbMat.uniforms.uLife.value = life;
        fbMat.uniforms.uTime.value = t;
      }
      ring.visible = ringT > 0 && ringT < 1;
      if (ring.visible) {
        ring.scale.setScalar(0.6 + 15 * easeOutCubic(ringT));
        ringMat.uniforms.uA.value = (1 - ringT) * (1 - ringT) * 0.9;
      }
      flash.visible = bump > 0.02;
      flash.scale.setScalar(0.4 + 1.1 * (1 - bump));
      flash.material.opacity = bump * 0.5;
      fbLight.intensity = 130 * Math.pow(Math.max(0, 1 - life * 1.15), 1.5) * smooth(0.545, 0.57, P);

      floorU.uGlowK.value = 0.7 + 0.8 * smooth(0.3, 0.5, P) + 3.2 * Math.max(0, 1 - life) * smooth(0.545, 0.57, P);

      ticks.forEach((f) => f(t));

      /* camera + post */
      s.fx.cam.z += -2.0 * smooth(0, 0.5, P) + 2.6 * smooth(0.55, 0.64, P);
      s.fx.cam.y += 0.08 * smooth(0.5, 0.56, P);
      s.fx.shake = Math.max(s.fx.shake, 0.12 * bump);
      s.fx.bloom += 0.45 * bump + 0.15 * smooth(0.4, 0.545, P);
      s.fx.flash = Math.max(s.fx.flash, bump > 0.88 ? (bump - 0.88) / 0.12 * 0.45 : 0);
      s.fx.fov += 8 * bump;
    },
  };
}
