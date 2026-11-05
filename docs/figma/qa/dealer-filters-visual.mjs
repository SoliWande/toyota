import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { readFile, writeFile } from 'node:fs/promises';
import { qaOutput } from './artifact-paths.mjs';
import assert from 'node:assert/strict';

const origin = 'http://127.0.0.1:8771', output = qaOutput('dealer-filters');
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const evidence = [];
const html = (state, page = 1) => readFile(`.laravel-tools/competition/dealer-filter-states/${state}-${page}.html`, 'utf8');
const cases = ['default', 'no-provinces', 'empty', 'members-asc', 'members-desc', 'name-asc', 'name-desc', 'rank-asc', 'rank-desc'];
try {
  for (const state of cases) for (const width of [320, 390, 768, 1024, 1440, 1920]) {
    const page = await browser.newPage({ viewport: { width, height: 844 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('requestfailed', r => errors.push(`${r.url()} ${r.failure()?.errorText}`));
    page.on('response', r => { if (r.status() >= 400) errors.push(`${r.status()} ${r.url()}`); });
    await page.route(`${origin}/__qa-filters`, async r => r.fulfill({ contentType: 'text/html', body: await html(state) }));
    await page.goto(`${origin}/__qa-filters`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    const data = await page.evaluate(() => {
      const rect = s => document.querySelector(s)?.getBoundingClientRect().toJSON();
      return { overflow: document.documentElement.scrollWidth > innerWidth, sort: document.querySelector('[name=sort]').value,
        direction: document.querySelector('[name=direction]').value, province: document.querySelector('[name=province]').value,
        controls: { province: rect('.wc-d-province'), sort: rect('.wc-d-sort'), toggle: rect('.wc-d-direction') },
        provinces: [...document.querySelector('[name=province]').options].map(o => ({ value: o.value, label: o.text })),
        rankLabels: [...document.querySelectorAll('.wc-d-rank')].map(el => ({ aria: el.getAttribute('aria-label'), text: el.innerText })),
        assets: [...document.images].filter(i => i.getBoundingClientRect().width > 0).every(i => i.complete && i.naturalWidth > 0),
        note: document.querySelector('#wc-d-province-note')?.innerText ?? null,
        badLinks: [...document.querySelectorAll('a')].filter(a => !a.getAttribute('href') || a.getAttribute('href') === '#').length };
    });
    await page.screenshot({ path: `${output}/${state}-${width}.png`, fullPage: true });
    evidence.push({ state, width, source: 'DB-disabled Blade DTO fixture; SQL verified separately', ...data, errors });
    await writeFile(`${output}/visual.json`, JSON.stringify(evidence, null, 2));
    assert.equal(data.overflow, false, `${state}/${width} overflow`);
    assert.ok(data.assets); assert.equal(data.badLinks, 0); assert.deepEqual(errors, []);
    assert.ok(Object.values(data.controls).every(r => r.height >= 44 && r.width >= 44), `${state}/${width} hit area`);
    assert.ok(data.controls.sort.right <= data.controls.toggle.x + 1, `${state}/${width} overlap`);
    if (width < 768) assert.ok(data.rankLabels.every(r => r.text.includes('#Hạng')));
    assert.ok(data.rankLabels.every(r => r.aria.includes('toàn kỳ') && !r.aria.includes('tháng')));
    if (state === 'no-provinces') { assert.equal(data.provinces.length, 1); assert.ok(data.note); }
    else if (state !== 'empty') assert.equal(data.provinces.length, 4);
    await page.close();
  }
  // Native controls + production JS. Fixture responses retain state; no pretend SQL sort.
  for (const javaScriptEnabled of [true, false]) {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 }, javaScriptEnabled });
    await page.route(url => url.origin === origin && url.pathname === '/dealers', async r => {
      const q = new URL(r.request().url()).searchParams;
      const state = `${q.get('sort') || 'members'}-${q.get('direction') || 'desc'}`;
      await r.fulfill({ contentType: 'text/html', body: await html(state, q.get('page') === '2' ? 2 : 1) });
    });
    await page.goto(`${origin}/dealers?province=H%C3%A0%20N%E1%BB%99i&q=Toyota&sort=members&direction=desc`, { waitUntil: 'networkidle' });
    await page.locator('[name=sort]').selectOption('name');
    if (!javaScriptEnabled) await page.locator('.wc-d-search button').click();
    await page.waitForURL(u => u.searchParams.get('sort') === 'name' && u.searchParams.get('direction') === 'desc');
    await page.locator('.wc-d-direction').click();
    await page.waitForURL(u => u.searchParams.get('direction') === 'asc');
    assert.equal(new URL(page.url()).searchParams.get('q'), 'Toyota');
    assert.equal(new URL(page.url()).searchParams.get('province'), 'Hà Nội');
    await page.locator('.wc-d-pagination [rel=next]').click();
    await page.waitForURL(u => u.searchParams.get('page') === '2');
    assert.equal(await page.locator('[name=sort]').inputValue(), 'name');
    assert.equal(await page.locator('[name=direction]').inputValue(), 'asc');
    assert.equal(await page.locator('[name=province]').inputValue(), 'Hà Nội');
    evidence.push({ nativeInteractions: true, javaScriptEnabled, source: 'UI fixture responses + native form/real JS, SQL tests separate' });
    await page.close();
  }
  await writeFile(`${output}/visual.json`, JSON.stringify(evidence, null, 2));
  console.log('PASS54 filter state/viewports and native sort/direction/pagination with JavaScript on/off.');
} finally { await browser.close(); }
