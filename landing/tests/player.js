const { chromium, devices } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--use-angle=swiftshader','--use-gl=angle','--enable-unsafe-swiftshader','--ignore-gpu-blocklist'] });
  for (const [name, opts] of [['iPhone 12', Object.assign({}, devices['iPhone 12'], { deviceScaleFactor: 1 })], ['Desktop', { viewport: { width: 1280, height: 720 } }]]) {
    const ctx = await browser.newContext(opts);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message.slice(0, 150)));
    await page.goto('http://localhost:8766/preview.html?q=min&nosmooth&msaa=0', { waitUntil: 'load' });
    await page.waitForTimeout(3000);
    const S = () => page.evaluate(() => ({ y: Math.round(scrollY), vis: document.documentElement.classList.contains('has-player'), idle: document.getElementById('player').classList.contains('pl-idle'), text: document.getElementById('pl-text').textContent, playing: document.getElementById('play').getAttribute('aria-pressed'), speed: document.getElementById('pl-speed').textContent, now: document.getElementById('pl-track').getAttribute('aria-valuenow') }));
    console.log('\n##', name);
    console.log('start:', JSON.stringify(await S()));
    const tap = (sel) => (opts.hasTouch ? page.tap(sel) : page.click(sel));
    await page.evaluate(() => scrollTo(0, 120)); await page.waitForTimeout(800);
    console.log('after a small scroll (leaves the idle hint):', JSON.stringify(await S()));
    // next / previous card while paused
    await tap('#pl-next'); await page.waitForTimeout(2200);
    console.log('after next:', JSON.stringify(await S()));
    await tap('#pl-next'); await page.waitForTimeout(2200);
    const second = await S(); console.log('after next #2:', JSON.stringify(second));
    await tap('#pl-prev'); await page.waitForTimeout(2200);
    console.log('after prev:', JSON.stringify(await S()));
    // speed button cycles and persists
    const sp = []; for (let i = 0; i < 4; i++) { await tap('#pl-speed'); sp.push((await S()).speed); }
    console.log('speed cycle:', sp.join(' → '), '| stored:', await page.evaluate(() => localStorage.getItem('bsma-speed')));
    // scrub to the middle of the timeline
    const box = await page.evaluate(() => { const r = document.getElementById('pl-track').getBoundingClientRect(); return { x: r.left, y: r.top + r.height / 2, w: r.width }; });
    await page.mouse.move(box.x + box.w / 2, box.y); await page.mouse.down(); await page.waitForTimeout(150); await page.mouse.up(); await page.waitForTimeout(600);
    const mid = await S(); console.log('scrub to 50%:', JSON.stringify(mid));
    // play / pause with the button, and with Space on the focused button (must toggle exactly once)
    await tap('#play'); await page.waitForTimeout(800); const p1 = (await S()).playing;
    await tap('#play'); await page.waitForTimeout(400); const p2 = (await S()).playing;
    await page.focus('#play'); await page.keyboard.press('Space'); await page.waitForTimeout(500); const p3 = (await S()).playing;
    await page.keyboard.press('Space'); await page.waitForTimeout(500); const p4 = (await S()).playing;
    console.log('play states click,click,space,space:', [p1, p2, p3, p4].join(','), '(expected true,false,true,false)');
    // hidden once the contact chapter is reached
    await page.evaluate(() => { const r = document.getElementById('contact').getBoundingClientRect(); scrollTo(0, r.top + scrollY + 200); });
    await page.waitForTimeout(1800);
    console.log('at contact: player shown =', (await S()).vis, '(expected false)');
    console.log('errors:', errs.join('|') || 'none');
    await ctx.close();
  }
  await browser.close();
})();
