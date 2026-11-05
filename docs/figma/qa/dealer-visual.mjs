import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { qaOutput } from './artifact-paths.mjs';
import { readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin = 'http://127.0.0.1:8771';
const output = qaOutput('competition-dealer/after');
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const evidence = [];
try {
  for (const state of ['directory', 'empty', 'one', 'long-directory', 'many', 'page-two', 'profile', 'zero', 'long-profile', 'photo', 'inactive', 'unapproved']) {
    const html = await readFile(`.laravel-tools/competition/dealer-states/${state}.html`, 'utf8');
    for (const width of [320, 390, 768, 1024, 1440, 1920]) {
      const page = await browser.newPage({ viewport: { width, height: 844 } });
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      page.on('requestfailed', r => errors.push(`${r.url()}: ${r.failure()?.errorText}`));
      page.on('response', r => { if (r.status() >= 400) errors.push(`${r.status()} ${r.url()}`); });
      await page.route(`${origin}/__qa-dealer`, r => r.fulfill({ contentType: 'text/html', body: html }));
      await page.goto(`${origin}/__qa-dealer`, { waitUntil: 'networkidle' });
      await page.evaluate(async () => { await Promise.all([600, 700].map(w => document.fonts.load(`${w} 28px Montserrat`, 'Đại lý KẾT NỐI HÀNH TRÌNH'))); await document.fonts.ready; });
      const data = await page.evaluate(() => {
        const rect = el => el?.getBoundingClientRect().toJSON();
        const main = document.querySelector('main'), footer = document.querySelector('.wc-footer');
        return { overflow: document.documentElement.scrollWidth > innerWidth, main: rect(main), footer: rect(footer),
          title: rect(document.querySelector('h1')), banner: rect(document.querySelector('.wc-d-banner')), list: rect(document.querySelector('.wc-d-list')),
          stats: rect(document.querySelector('.wc-d-stats')), chart: rect(document.querySelector('.wc-d-chart')),
          rows: document.querySelectorAll('.wc-d-row').length,
          assets: [...document.images].filter(el => el.getBoundingClientRect().width > 0).map(el => ({ src: new URL(el.currentSrc).pathname, loaded: el.complete && el.naturalWidth > 0, width: el.getBoundingClientRect().width, height: el.getBoundingClientRect().height, declaredWidth: el.getAttribute('width'), declaredHeight: el.getAttribute('height') })),
          text: [...document.querySelectorAll('.wc-d-name strong,.wc-d-stat strong,.wc-d-contact-row p,h1')].map(el => ({ text: el.textContent, client: el.clientWidth, scroll: el.scrollWidth })),
          font: getComputedStyle(document.querySelector('h1')).font,
          montserrat: [600, 700].every(w => document.fonts.check(`${w} 28px Montserrat`, 'Đại lý KẾT NỐI HÀNH TRÌNH')),
          activeNav: [...document.querySelectorAll('.wc-desktop-nav [aria-current]')].map(el => el.textContent.trim()),
          badLinks: [...document.querySelectorAll('a')].filter(el => !el.getAttribute('href') || el.getAttribute('href') === '#').length,
          tel: document.querySelectorAll('a[href^="tel:"]').length,
          registration: [...document.querySelectorAll('.wc-d-join')].filter(el => el.getBoundingClientRect().width > 0).length };
      });
      await page.screenshot({ path: `${output}/${state}-${width}.png`, fullPage: true });
      evidence.push({ state, width, source: 'DB-disabled Blade DTO fixture, production build', ...data, errors });
      await writeFile(`${output}/visual.json`, JSON.stringify(evidence, null, 2));
      assert.equal(data.overflow, false, `${state}/${width} horizontal overflow`);
      assert.ok(data.assets.every(a => a.loaded), `${state}/${width} assets`);
      for (const asset of data.assets.filter(a => a.src.includes('/competition/dealer/'))) {
        assert.equal(asset.width, +asset.declaredWidth, `${state}/${width} ${asset.src} width`);
        assert.equal(asset.height, +asset.declaredHeight, `${asset.src} height`);
      }
      assert.ok(data.text.every(t => t.scroll <= t.client + 1), `${state}/${width} text clipping`);
      assert.ok(data.montserrat); assert.equal(data.badLinks, 0); assert.deepEqual(data.activeNav, ['Đại lý']);
      assert.ok(data.footer.y >= data.main.bottom - 1, `${state}/${width} footer overlaps content`);
      assert.ok(data.footer.bottom >= 843, `${state}/${width} sticky footer`);
      if (['inactive', 'unapproved'].includes(state)) assert.equal(data.tel, 0);
      if (state === 'inactive') assert.equal(data.registration, 0);
      if (['profile', 'zero', 'long-profile', 'photo', 'unapproved'].includes(state)) assert.equal(data.registration, 1);
      if (width < 1200) {
        await page.locator('[data-competition-menu]>summary').click();
        await page.waitForFunction(() => document.querySelector('[data-competition-menu]').open);
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => !document.querySelector('[data-competition-menu]').open);
      }
      assert.deepEqual(errors, []); await page.close();
    }
  }
  console.log(`PASS ${evidence.length} Dealer state/viewports; assets, Vietnamese fonts, overflow, menu and footer.`);
} finally { await browser.close(); }
