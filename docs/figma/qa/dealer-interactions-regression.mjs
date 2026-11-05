import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { readFile, writeFile } from 'node:fs/promises';
import { qaOutput } from './artifact-paths.mjs';
import assert from 'node:assert/strict';

const origin = 'http://127.0.0.1:8771', output = qaOutput('competition-dealer/regression');
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const evidence = [];
try {
  // Exercise native GET search/select/navigation using real Blade fixture responses.
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  const fixture = state => readFile(`.laravel-tools/competition/dealer-states/${state}.html`, 'utf8');
  await page.route(url => url.origin === origin && url.pathname.startsWith('/dealers'), async r => r.fulfill({ contentType: 'text/html', body: await fixture(r.request().url().includes('/1/profile') ? 'profile' : new URL(r.request().url()).searchParams.has('province') ? 'filtered' : 'directory') }));
  await page.goto(`${origin}/dealers`, { waitUntil: 'networkidle' });
  await page.locator('[data-dealer-province]').selectOption('Thái Nguyên');
  await page.waitForURL(u => u.searchParams.get('province') === 'Thái Nguyên');
  await page.locator('#wc-d-search').fill('Toyota');
  await page.locator('.wc-d-search button').click();
  await page.waitForURL(u => u.searchParams.get('q') === 'Toyota' && u.searchParams.get('province') === 'Thái Nguyên');
  await page.locator('.wc-d-row').first().click();
  await page.waitForURL(`${origin}/dealers/1/profile`);
  assert.match(await page.locator('h1').innerText(), /Toyota/);
  assert.equal(await page.locator('.wc-d-join:visible').count(), 1);
  assert.match(await page.locator('.wc-d-join:visible').getAttribute('href'), /\/qa-thai-nguyen#registration$/);
  await page.close();
  evidence.push({ source: 'Blade fixtures + real production JavaScript, no backend data simulation claim', nativeSearch: true, provinceAutosubmit: true, profileLink: true, registrationHref: true });

  // Live read-only Laravel GETs; no create/approve/seed operations through browser.
  const probe = await browser.newPage();
  assert.equal((await probe.goto(`${origin}/dealers`)).status(), 200);
  const profileHref = await probe.locator('.wc-d-row').first().getAttribute('href');
  await probe.goto(profileHref);
  const registrationHref = await probe.locator('.wc-d-join').first().getAttribute('href');
  const landing = new URL(registrationHref).pathname;
  await probe.close();
  for (const [name, path] of [['home', '/'], ['leaderboard', '/leaderboard'], ['awards', '/awards'], ['directory-live', '/dealers'], ['profile-live', new URL(profileHref).pathname], ['landing', landing], ['lookup', `${landing.replace(/\/$/, '')}/registrations/lookup`]]) {
    for (const width of [320, 390, 768, 1024, 1440, 1920]) {
      const p = await browser.newPage({ viewport: { width, height: 844 } });
      const errors = []; p.on('pageerror', e => errors.push(e.message));
      const response = await p.goto(`${origin}${path}`, { waitUntil: 'networkidle' });
      assert.equal(response.status(), 200, `${name}/${width}`);
      await p.evaluate(() => document.fonts.ready);
      const data = await p.evaluate(() => ({ overflow: document.documentElement.scrollWidth > innerWidth,
        brokenImages: [...document.images].filter(i => i.getBoundingClientRect().width > 0 && (!i.complete || i.naturalWidth === 0)).map(i => i.src),
        title: document.title, forms: [...document.querySelectorAll('form')].map(f => ({ method: f.method, action: f.action })) }));
      assert.equal(data.overflow, false, `${name}/${width} overflow`); assert.deepEqual(data.brokenImages, []); assert.deepEqual(errors, []);
      await p.screenshot({ path: `${output}/${name}-${width}.png`, fullPage: true });
      evidence.push({ name, width, source: 'Live read-only local Laravel GET, separate from DB-disabled fixtures', status: response.status(), ...data, errors });
      await p.close();
    }
  }
  await writeFile(`${output}/evidence.json`, JSON.stringify(evidence, null, 2));
  console.log('PASS fixture native interactions and42 live Laravel page/viewport regression screenshots.');
} finally { await browser.close(); }
