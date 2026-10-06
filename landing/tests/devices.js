const { chromium, devices } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const fs = require('fs');
const SP = (process.env.OUT_DIR || '.') + '/';
const PAGE = process.env.PAGE || 'http://localhost:8766/preview.html?q=min&nosmooth&msaa=0';
const only = (process.env.ONLY || '').split(',').filter(Boolean);
const list = [
  ['iPhone SE', 'iPhone SE'], ['iPhone SE 3', 'iPhone SE (3rd gen)'], ['iPhone 12', 'iPhone 12'], ['iPhone 15 Pro Max', 'iPhone 15 Pro Max'],
  ['Pixel 5', 'Pixel 5'], ['Pixel 7', 'Pixel 7'], ['Galaxy S8', 'Galaxy S8'], ['Galaxy S9+', 'Galaxy S9+'],
  ['Galaxy Tab S9', 'Galaxy Tab S9'], ['iPad Mini', 'iPad Mini'], ['iPad Pro 11', 'iPad Pro 11'],
  ['iPhone 12 landscape', 'iPhone 12 landscape'], ['Pixel 7 landscape', 'Pixel 7 landscape'], ['iPad Mini landscape', 'iPad Mini landscape'],
  ['iPad Pro 12.9', null, { width: 1024, height: 1366, isMobile: true, hasTouch: true }], ['Desktop 1024x768', null, { width: 1024, height: 768 }], ['Desktop 1280x720', null, { width: 1280, height: 720 }], ['Desktop 1366x768', null, { width: 1366, height: 768 }],
  ['Desktop 1920x1080', null, { width: 1920, height: 1080 }], ['Desktop 2560x1440', null, { width: 2560, height: 1440 }],
].filter(([n]) => !only.length || only.some((o) => n.includes(o)));
const SECTIONS = [
  ['hero', 0.02], ['story', 0.17], ['story', 0.5], ['story', 0.87], ['alarm', 0.18], ['alarm', 0.445], ['alarm', 0.7], ['alarm', 0.97], ['flashover', 0.17], ['flashover', 0.75], ['flashover', 0.95],
  ['backdraft', 0.065], ['backdraft', 0.2], ['backdraft', 0.435], ['backdraft', 0.68], ['backdraft', 0.95],
  ['response', 0.13], ['response', 0.36], ['response', 0.585], ['response', 0.78], ['response', 0.97],
  ['features', 0.5], ['research', 0.5], ['portfolio', 0.5], ['contact', 0.3],
];
const MEASURE = () => {
  const vw = innerWidth, vh = innerHeight, out = { problems: [], info: [] };
  const R = (el) => el.getBoundingClientRect();
  const vis = (el) => { const cs = getComputedStyle(el); return cs.visibility !== 'hidden' && cs.display !== 'none' && +cs.opacity > 0.55; };
  const inter = (a, b) => Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
  const topbar = document.querySelector('.topbar'), tb = R(topbar);
  const player = document.getElementById('player'); const pvis = player && getComputedStyle(player).visibility !== 'hidden' && +getComputedStyle(player).opacity > 0.5;
  const pr = pvis ? R(player) : null;
  if (document.documentElement.scrollWidth > vw + 1) out.problems.push('horizontal overflow ' + (document.documentElement.scrollWidth - vw) + 'px');
  // top bar: children must not overlap and must stay inside the viewport
  const kids = [...topbar.children].filter((c) => getComputedStyle(c).display !== 'none' && R(c).width > 0);
  for (let i = 0; i < kids.length; i++) { const a = R(kids[i]); if (a.left < -1 || a.right > vw + 1) out.problems.push('topbar child outside viewport: ' + (kids[i].id || kids[i].className)); for (let j = i + 1; j < kids.length; j++) if (inter(a, R(kids[j])) > 20) out.problems.push('topbar overlap: ' + (kids[i].id || kids[i].className) + ' × ' + (kids[j].id || kids[j].className)); }
  if (pr) { if (pr.left < -1 || pr.right > vw + 1 || pr.bottom > vh + 1) out.problems.push('player outside viewport');
    const pk = [...player.querySelectorAll('button')].filter((c) => getComputedStyle(c).display !== 'none' && R(c).width > 0); pk.forEach((c) => { const m = Math.min(R(c).width, R(c).height); if (m < 34) out.problems.push('player control too small: ' + c.id + ' ' + Math.round(R(c).width) + '×' + Math.round(R(c).height)); }); }
  const cards = [...document.querySelectorAll('.beat, .panel, .contact-panel')].filter(vis).filter((c) => { const r = R(c); return r.bottom > 0 && r.top < vh && r.height > 40; });
  const huds = [...document.querySelectorAll('.hud.on')];
  cards.forEach((c) => {
    const r = R(c), name = c.className.split(' ').slice(0, 2).join('.') + ':' + (c.querySelector('h2,h3') ? c.querySelector('h2,h3').textContent.trim().slice(0, 18) : '');
    const isFlow = c.classList.contains('contact-panel');
    if (!isFlow) {
      if (r.top < tb.bottom - 6) out.problems.push('card under top bar: ' + name + ' top=' + Math.round(r.top) + ' bar=' + Math.round(tb.bottom));
      const limit = pr ? pr.top : vh;
      if (r.bottom > limit + 4) out.problems.push('card under player/bottom edge: ' + name + ' bottom=' + Math.round(r.bottom) + ' limit=' + Math.round(limit));
      if (r.left < -1 || r.right > vw + 1) out.problems.push('card outside viewport: ' + name);
      const sh = c.scrollHeight - c.clientHeight; if (sh > 4) out.info.push('card scrolls inside: ' + name + ' (+' + sh + 'px)');
    }
    huds.forEach((h) => { const a = inter(r, R(h)); if (a > 400) out.problems.push('card overlaps HUD ' + (h.id) + ' by ' + Math.round(a) + 'px²: ' + name); });
    if (pr && !isFlow) { const a = inter(r, pr); if (a > 400) out.problems.push('card overlaps player: ' + name); }
  });
  huds.forEach((h) => { const r = R(h); if (r.left < -1 || r.right > vw + 1) out.problems.push('HUD outside viewport ' + h.id); if (pr && inter(r, pr) > 200) out.problems.push('HUD overlaps player ' + h.id); if (inter(r, tb) > 300) out.problems.push('HUD overlaps top bar ' + h.id); });
  // small tap targets (visible buttons / links that are not inline text)
  const small = [...document.querySelectorAll('button:not(.p-dots button), .btn, .icon-btn, .hs, .card, .toc a, .nav a')].filter((e) => e.offsetParent !== null && vis(e) && R(e).top > -50 && R(e).top < vh).filter((e) => { const r = R(e); return r.width > 0 && Math.min(r.width, r.height) < 30; }).map((e) => (e.tagName.toLowerCase() + '#' + e.id + '.' + e.className.toString().slice(0, 30)) + ' ' + Math.round(R(e).width) + '×' + Math.round(R(e).height));
  if (small.length) out.problems.push('small tap targets: ' + [...new Set(small)].slice(0, 5).join(', '));
  // the two illustrated acts: the scene must be big enough, clear of the bars and of the text card
  document.querySelectorAll('.act-stage').forEach((st) => {
    const sr = R(st); if (sr.bottom < 0 || sr.top > vh) return;
    const pl = st.querySelector('.plan'), stp = st.querySelector('.steps'); if (!pl) return;
    const r = R(pl), tag = st.id;
    const minW = vh < 620 ? 190 : 240, minH = vh < 620 ? 160 : 190;
    if (r.width < minW || r.height < minH) out.problems.push('scene too small ' + tag + ' ' + Math.round(r.width) + '×' + Math.round(r.height));
    if (stp && R(stp).top < tb.bottom - 4) out.problems.push('scene steps under top bar ' + tag);
    if (pr && r.bottom > pr.top + 2) out.problems.push('scene under player ' + tag);
    if (r.left < -1 || r.right > vw + 1) out.problems.push('scene outside viewport ' + tag);
    cards.forEach((c) => { if (c.closest('.act') && inter(r, R(c)) > 600) out.problems.push('scene overlaps card ' + tag + ' by ' + Math.round(inter(r, R(c))) + 'px²'); });
    const bad = [...pl.querySelectorAll('.dev, .cab, .fan, .gauge, .lcd')].filter((e) => { const b = R(e); return b.width > 0 && getComputedStyle(e).opacity !== '0' && (b.left < r.left - 6 || b.right > r.right + 6 || b.top < r.top - 6 || b.bottom > r.bottom + 6); });
    if (bad.length) out.problems.push('scene item outside the plan: ' + bad.map((e) => e.className.toString().slice(0, 14)).join(','));
  });
  const h1 = document.querySelector('.hero-title'); if (h1 && vis(h1) && R(h1).bottom > vh) out.problems.push('hero title cut off');
  // the things a visitor must be able to reach without hunting
  const within = (el, box, tol = 2) => { const r = R(el), b = R(box); return r.top >= b.top - tol && r.bottom <= b.bottom + tol; };
  const need = (sel, box, label) => { const el = document.querySelector(sel); if (!el || !vis(el)) return; const b = box || el.closest('.panel, .beat'); if (!b) return; const r = R(el), bb = R(b); if (bb.bottom < 0 || bb.top > vh) return; const lim = Math.min(bb.bottom, pr ? pr.top : vh, vh); if (r.top < bb.top - 2 || r.bottom > lim + 2) out.warns.push(label + ' needs scrolling'); };
  out.warns = [];
  need('.sign-list li:last-child', null, 'last of five signs');
  need('#sign-detail', null, 'sign detail');
  need('#p-link', null, 'shop button');
  need('.p-controls', null, 'product arrows');
  need('.research-panel .card:nth-child(2)', null, 'second research card');
  const hs = document.querySelector('.hero-stick');
  if (hs && R(hs).top > -5) {
    const act = document.querySelector('.hero-actions'), tit = document.querySelector('.hero-title');
    if (act && R(act).bottom > (pr ? pr.top : vh) - 2) out.problems.push('hero buttons under the player');
    const st = document.querySelector('.stats'); if (st && getComputedStyle(st).display !== 'none' && R(st).bottom > (pr ? pr.top : vh) - 2) out.problems.push('hero stats under the player');
    const lead = document.querySelector('.lead'); if (lead && tit && getComputedStyle(lead).display !== 'none' && inter(R(lead), R(tit)) > 100) out.problems.push('hero text overlaps the title');
    if (act && lead && getComputedStyle(lead).display !== 'none' && inter(R(act), R(lead)) > 100) out.problems.push('hero buttons overlap the text');
  }
  return out;
};
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--use-angle=swiftshader', '--use-gl=angle', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });
  const report = {};
  for (const [name, dev, vp] of list) {
    const opts = dev ? Object.assign({}, devices[dev], { deviceScaleFactor: 1 }) : (vp.isMobile ? { viewport: { width: vp.width, height: vp.height }, isMobile: true, hasTouch: true, deviceScaleFactor: 1 } : { viewport: vp, deviceScaleFactor: 1 });
    const ctx = await browser.newContext(opts);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(e.message.slice(0, 150)));
    await page.goto(PAGE, { waitUntil: 'load' });
    await page.waitForTimeout(2500);
    const res = { vp: await page.evaluate(() => innerWidth + '×' + innerHeight), samples: {}, errors: errs };
    for (const [id, f] of SECTIONS) {
      await page.evaluate(([id, f]) => { const r = document.getElementById(id).getBoundingClientRect(); scrollTo(0, r.top + scrollY + f * Math.max(0, r.height - innerHeight)); }, [id, f]);
      await page.waitForTimeout(1900);
      const m = await page.evaluate(MEASURE);
      res.samples[id + '@' + f] = m;
      if (process.env.SHOTS && ['hero@0.02', 'backdraft@0.68', 'alarm@0.7', 'response@0.585', 'features@0.5', 'portfolio@0.5'].includes(id + '@' + f)) await page.screenshot({ path: SP + `dev-${name.replace(/[^\w]+/g, '_')}-${id}-${f}.png` });
    }
    report[name] = res;
    const probs = Object.entries(res.samples).flatMap(([k, v]) => v.problems.map((p) => k + ': ' + p));
    const warns = Object.entries(res.samples).flatMap(([k, v]) => (v.warns || []).map((p) => k + ': ' + p));
    console.log(`\n## ${name} (${res.vp}) — ${probs.length} problem(s)${errs.length ? ' | JS errors: ' + errs.join('|') : ''}`);
    probs.forEach((p) => console.log('  - ' + p));
    warns.forEach((p) => console.log('  ~ ' + p));
    await ctx.close();
  }
  fs.writeFileSync(SP + 'devs-report.json', JSON.stringify(report, null, 1));
  await browser.close();
})().catch((e) => { console.error('ERR', e); process.exit(1); });
