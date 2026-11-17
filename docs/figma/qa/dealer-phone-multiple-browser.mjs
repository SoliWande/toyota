import assert from 'node:assert/strict';
import {readFile, writeFile, mkdir} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {chromium} from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';

const dir = '.laravel-tools/dealer-phone-multiple';
const root = resolve('public');
const out = `${dir}/screenshots`;
await mkdir(out, {recursive: true});
const mime = {'.css':'text/css', '.js':'application/javascript', '.svg':'image/svg+xml', '.png':'image/png', '.jpg':'image/jpeg', '.woff2':'font/woff2', '.webp':'image/webp'};
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
const checks = [];
try {
  for (const width of [1440, 390]) {
    for (const screen of ['landing', 'd02', 'd03', 'success', 'd05', 'detail', 'd06']) {
      for (const variant of screen === 'd06' ? [''] : ['one', 'two', 'none', 'invalid']) {
        const fixture = screen === 'd06' ? screen : `${screen}-${variant}`;
        const page = await browser.newPage({viewport:{width, height:1000}});
        const errors = [], missing = [], writes = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', async route => {
          const request = route.request(), url = new URL(request.url());
          if (request.method() !== 'GET') {writes.push(request.method()); return route.abort();}
          if (url.origin !== 'http://toyota.test') return route.abort();
          if (url.pathname === '/phone-fixture') return route.fulfill({contentType:'text/html; charset=utf-8', body:await readFile(`${dir}/html/${fixture}.html`)});
          const file = resolve(root, '.' + decodeURIComponent(url.pathname));
          if (!file.startsWith(root + sep) || !mime[extname(file)]) return route.abort();
          try {return route.fulfill({contentType:mime[extname(file)], body:await readFile(file)});}
          catch {missing.push(url.pathname); return route.abort();}
        });
        await page.goto('http://toyota.test/phone-fixture', {waitUntil:'networkidle'});
        await page.evaluate(() => document.fonts.ready);
        if (screen === 'd05') assert.equal(await page.locator('.dr-duplicate').count(), 1, 'Duplicate state fixture');
        if (screen === 'd06') assert.equal(await page.locator('.dr-invalid-panel').count(), 1, 'Inactive state fixture');
        const calls = page.locator('a[href^="tel:"]');
        const expected = screen === 'd06' || ['none','invalid'].includes(variant) ? [] : variant === 'one' ? ['02583891999'] : ['02583891999','0912909032'];
        assert.equal(await calls.count(), expected.length, `${fixture}/${width}: call count`);
        for (const [index, phone] of expected.entries()) {
          const call = calls.nth(index);
          await call.scrollIntoViewIfNeeded();
          assert.equal(await call.getAttribute('href'), `tel:${phone}`);
          assert.ok((await call.innerText()).includes(phone));
          const metrics = await call.evaluate(el => {
            const r = el.getBoundingClientRect(), style = getComputedStyle(el);
            const parent = el.closest('.dr-dealer-card,.dr-success-dealer,.wc-d-contact').getBoundingClientRect();
            return {x:r.x, right:r.right, width:r.width, height:r.height, background:style.backgroundColor, color:style.color, radius:style.borderRadius, parentLeft:parent.left, parentRight:parent.right};
          });
          assert.ok(metrics.height >= 44, `${fixture}: touch height`);
          assert.ok(metrics.x >= metrics.parentLeft && metrics.right <= metrics.parentRight + 1, `${fixture}: button contained`);
          assert.equal(metrics.radius, '999px');
          assert.equal(metrics.color, 'rgb(17, 17, 17)');
          checks.push({fixture,width,phone,...metrics});
        }
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${fixture}/${width}: overflow`);
        assert.deepEqual(errors, [], `${fixture}: JS errors`);
        assert.deepEqual(missing, [], `${fixture}: missing assets`);
        assert.deepEqual(writes, [], `${fixture}: writes`);
        if (variant === 'two' || screen === 'd06') {
          const card = page.locator(screen === 'detail' ? '.wc-d-contact' : screen === 'success' ? '.dr-success-dealer' : screen === 'd02' || screen === 'd03' ? '.dr-dealer-compact' : '.dr-referring-dealer');
          if (await card.count()) await card.screenshot({path:`${out}/${fixture}-${width}.png`});
          await page.screenshot({path:`${out}/${fixture}-${width}-page.png`,fullPage:true});
        }
        await page.close();
      }
    }
  }
  await writeFile(`${out}/checks.json`, JSON.stringify(checks, null, 2));
  console.log('PASS: 50 page/viewport checks; visible numbers, tel, touch size, containment, no overflow/JS errors/missing assets/writes.');
} finally { await browser.close(); }
