import assert from 'node:assert/strict';
import {readFile, writeFile} from 'node:fs/promises';
import {resolve, sep, extname} from 'node:path';
import {chromium} from '../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';

// HTML comes from the real D01 controller test against the isolated test database.
// All browser requests are fulfilled locally or aborted; no production hosts or POSTs.
const directory = '.laravel-tools/d01-cta-browser';
const publicRoot = resolve('public');
const mime = {'.css':'text/css', '.js':'application/javascript', '.png':'image/png', '.svg':'image/svg+xml', '.woff2':'font/woff2', '.jpg':'image/jpeg', '.webp':'image/webp'};
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
const checks = [];
try {
    for (const count of [2, 0]) {
        for (const width of [1440, 390, 320]) {
            const page = await browser.newPage({viewport:{width, height:width === 1440 ? 1000 : 844}});
            const errors = [];
            const missing = [];
            const posts = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', async route => {
                const request = route.request();
                if (request.method() !== 'GET') {
                    posts.push(request.method());
                    return route.abort();
                }
                const pathname = decodeURIComponent(new URL(request.url()).pathname);
                if (pathname === '/d01-cta-fixture') {
                    return route.fulfill({contentType:'text/html; charset=utf-8', body:await readFile(`${directory}/owners-${count}.html`)});
                }
                const file = resolve(publicRoot, '.' + pathname);
                if (!file.startsWith(publicRoot + sep) || !mime[extname(file)]) return route.abort();
                try {
                    return await route.fulfill({contentType:mime[extname(file)], body:await readFile(file)});
                } catch {
                    missing.push(pathname);
                    return route.abort();
                }
            });
            await page.goto('http://toyota.test/d01-cta-fixture', {waitUntil:'networkidle'});
            await page.evaluate(() => document.fonts.ready);
            assert.equal(await page.locator('.dr-hero-count strong').textContent(), `${count} chủ xe`);
            assert.equal(await page.locator('.dr-hero-count small').textContent(), 'đã tham gia');
            const cta = page.locator('.dr-hero-capsule .dr-button');
            assert.equal((await cta.innerText()).trim(), 'Tham gia ngay');
            assert.equal(await cta.getAttribute('href'), '#registration');
            const geometry = await page.evaluate(() => {
                const capsule = document.querySelector('.dr-hero-capsule');
                const button = capsule.querySelector('.dr-button');
                const count = capsule.querySelector('.dr-hero-count');
                const box = element => {const {x,y,width,height}=element.getBoundingClientRect(); return {x,y,width,height};};
                const a = box(capsule), b = box(button), c = box(count);
                return {capsule:a, button:b, count:c, gap:c.x-(b.x+b.width), overflow:document.documentElement.scrollWidth > innerWidth,
                    fits:b.x >= a.x && c.x+c.width <= a.x+a.width && b.x+b.width <= c.x,
                    images:[...document.querySelectorAll('.dr-hero img')].filter(img => img.getBoundingClientRect().width > 0).map(img => ({src:new URL(img.currentSrc).pathname, loaded:img.complete && img.naturalWidth > 0, width:img.getBoundingClientRect().width, height:img.getBoundingClientRect().height}))};
            });
            assert.equal(geometry.overflow, false);
            assert.equal(geometry.fits, true);
            assert.ok(geometry.images.every(image => image.loaded));
            assert.equal(Math.round(geometry.gap), width === 1440 ? 16 : width === 390 ? 14 : 10);
            assert.equal(Math.round(geometry.button.height), width === 1440 ? 54 : 46);
            if (width === 390) assert.equal(Math.round(geometry.button.width), 204);
            await page.screenshot({path:`${directory}/d01-${width}-owners-${count}.png`, fullPage:true});
            await page.locator('.dr-hero').screenshot({path:`${directory}/hero-${width}-owners-${count}.png`});
            await cta.click();
            assert.equal(new URL(page.url()).hash, '#registration');
            assert.equal(posts.length, 0);
            assert.deepEqual(errors, []);
            assert.deepEqual(missing, []);
            checks.push({width,count,geometry,errors,missing,posts});
            await page.close();
        }
    }
    await writeFile(`${directory}/checks.json`, JSON.stringify(checks, null, 2));
    process.stdout.write(`PASS ${checks.length} D01 desktop/mobile fixture browser cases\n`);
} finally {
    await browser.close();
}
