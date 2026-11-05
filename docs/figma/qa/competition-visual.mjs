import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin = process.env.COMPETITION_QA_ORIGIN || 'http://127.0.0.1:8771';
const output = process.env.COMPETITION_QA_OUTPUT || qaOutput('website-competition');
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const evidence = [];
const close = (actual, expected, label) => assert.ok(Math.abs(actual - expected) <= 1, `${label}: ${actual} != ${expected}`);
try {
    for (const width of [320, 390, 768, 1024, 1440, 1920]) {
        const page = await browser.newPage({ viewport: { width, height: width === 390 ? 844 : 1024 }, deviceScaleFactor: 1 });
        const errors = [], failed = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('requestfailed', request => failed.push(request.url()));
        page.on('response', response => { if (response.status() >= 400) failed.push(`${response.status()} ${response.url()}`); });
        const response = await page.goto(origin, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200);
        await page.evaluate(() => document.fonts.ready);
        const geometry = await page.evaluate(() => ({
            overflow: document.documentElement.scrollWidth > innerWidth,
            header: document.querySelector('.wc-header').getBoundingClientRect().height,
            container: document.querySelector('.wc-stats').getBoundingClientRect().width,
            sections: [...document.querySelectorAll('main>section')].map(el => ({ y: el.offsetTop, height: el.offsetHeight })),
            heroSource: document.querySelector('.wc-hero-art img').currentSrc,
            images: [...document.images].filter(img => img.getBoundingClientRect().width > 0).map(img => ({
                src: new URL(img.currentSrc).pathname, loaded: img.complete && img.naturalWidth > 0,
                width: img.getBoundingClientRect().width, height: img.getBoundingClientRect().height
            })),
            fonts: [...document.fonts].filter(font => font.status === 'loaded').map(font => `${font.family}:${font.weight}`),
            badLinks: [...document.querySelectorAll('a')].filter(a => !a.getAttribute('href') || ['#', 'tel:', 'tel:undefined'].includes(a.getAttribute('href'))).length
        }));
        assert.equal(geometry.overflow, false, `Horizontal overflow ${width}`);
        assert.equal(geometry.badLinks, 0);
        assert.ok(geometry.images.every(img => img.loaded), `Missing image ${width}`);
        assert.equal(await page.locator('.wc-step').count(), 4);
        assert.equal(await page.locator('.wc-accordion details').count(), 11);
        if (width === 1440) {
            close(geometry.container, 1200, 'Desktop container');
            close(geometry.header, 72, 'Desktop header');
            [72, 752, 996, 1375, 1859, 2403].forEach((y, i) => close(geometry.sections[i].y, y, `Desktop section ${i}`));
            close(geometry.sections[0].height, 680, 'Desktop hero');
        }
        if (width === 390) {
            close(geometry.container, 358, 'Mobile container');
            close(geometry.header, 60, 'Mobile header');
            [60, 840, 1216, 1765, 2475, 3265].forEach((y, i) => close(geometry.sections[i].y, y, `Mobile section ${i}`));
            close(geometry.sections[0].height, 780, 'Mobile hero');
            assert.ok(geometry.heroSource.endsWith('/competition/hero-mobile.png'));
        }
        await page.screenshot({ path: `${output}/home-${width}.png`, fullPage: true });
        if (width === 390) {
            await page.locator('.wc-hero').screenshot({ path: `${output}/hero-390.png` });
            await page.locator('.wc-stats').screenshot({ path: `${output}/stats-390.png` });
            for (const [name,selector] of [['steps','.wc-steps'],['results','.wc-results'],['rules','.wc-rules'],['cta','.wc-join-card'],['footer','.wc-footer']]) {
                await page.locator(selector).screenshot({path:`${output}/below-fold-${name}-390.png`});
            }
            const cta=await page.locator('.wc-join-card').evaluate(el=>({
                font:getComputedStyle(el.querySelector('h2')).fontSize,
                buttonColor:getComputedStyle(el.querySelector('a')).color,
                border:getComputedStyle(el.querySelector('a')).borderTopWidth,
                footerFont:getComputedStyle(document.querySelector('.wc-footer-row')).fontSize
            }));
            // Chrome snaps the declared 1.5px border to 1 device pixel at DPR 1.
            assert.deepEqual(cta,{font:'22px',buttonColor:'rgb(17, 17, 17)',border:'1px',footerFont:'12px'});
        }
        // Real native accordion and keyboard operation, with no submit or database writes.
        for (const question of await page.locator('.wc-accordion summary').all()) {
            await question.click();
            assert.ok(await question.locator('..').locator('.wc-accordion-answer').isVisible());
            await question.click();
        }
        const firstQuestion = page.locator('.wc-accordion summary').first();
        await firstQuestion.focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('.wc-accordion details').first().getAttribute('open'), '');
        assert.ok(await page.locator('.wc-accordion-answer').first().isVisible());
        await firstQuestion.click();
        assert.equal(await page.locator('.wc-accordion details').first().getAttribute('open'), null);
        const discover = page.locator('.wc-hero-actions a').last();
        await discover.click();
        assert.ok(page.url().endsWith('#hanh-trinh'));
        if (width < 1200) {
            const trigger = page.locator('[data-competition-menu]>summary');
            await trigger.click();
            await page.waitForFunction(() => document.querySelector('[data-competition-menu]>summary').getAttribute('aria-expanded') === 'true');
            assert.ok(await page.locator('#wc-mobile-menu').isVisible());
            assert.equal(await page.locator('#wc-mobile-menu>a').count(), 5);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('[data-competition-menu]').getAttribute('open'), null);
            await trigger.click();
            await page.locator('#wc-mobile-menu>a').last().click();
            assert.ok(page.url().endsWith('#the-le'));
            assert.equal(await page.locator('[data-competition-menu]').getAttribute('open'), null);
            if (width === 390) {
                await trigger.click();
                await page.screenshot({ path: `${output}/menu-390.png` });
                const menuImages = await page.locator('#wc-mobile-menu img').evaluateAll(imgs => imgs.every(img => img.complete && img.naturalWidth > 0));
                assert.ok(menuImages);
                await page.keyboard.press('Escape');
            }
        }
        assert.deepEqual(errors, []);
        assert.deepEqual(failed, []);
        evidence.push({ width, geometry, errors, failed, accordion: 'PASS', anchors: 'PASS', menu: width < 1200 ? 'PASS' : 'desktop navigation' });
        await page.close();
    }
    // Existing route navigation over HTTP: GET only, no authentication or form submission.
    const page = await browser.newPage();
    await page.goto(origin);
    const paths = await page.locator('a').evaluateAll(links => [...new Set(links.map(a => new URL(a.href).pathname))]);
    const routes = [];
    for (const path of paths) {
        const response = await page.request.get(`${origin}${path}`);
        assert.equal(response.status(), 200, `GET ${path}`);
        routes.push({ path, status: response.status() });
    }
    await page.close();
    await writeFile(`${output}/evidence.json`, JSON.stringify({ source: 'Live Laravel GET / with existing local database; no fixture numbers or writes', evidence, routes }, null, 2));
    console.log(`PASS ${evidence.length} viewports, exact B01 section positions at 390/1440, assets/fonts, native accordion, mobile menu/Escape/anchors and ${routes.length} existing GET routes.`);
} finally {
    await browser.close();
}
