import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const html = await readFile('.laravel-tools/dealer-call/cards.html', 'utf8');
const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
const css = await readFile(`public/build/${manifest['resources/css/dealer-registration.css'].file}`, 'utf8');
const output = 'docs/figma/qa/screenshots/dealer-call';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const evidence = [];
try {
    for (const width of [320, 390, 768, 1024, 1440]) {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        // No network and no tel navigation. This is a Blade fixture, not a live DB flow.
        await page.route('**/*', async route => {
            const path = new URL(route.request().url()).pathname;
            if (path.startsWith('/dealer-registration/')) {
                try { return await route.fulfill({ body: await readFile(`public${path}`) }); } catch {}
            }
            await route.abort();
        });
        await page.setContent(html);
        await page.addStyleTag({ content: css });
        await page.addStyleTag({ content: '* { transition:none!important; }' });
        const result = await page.evaluate(() => ({
            overflow: document.documentElement.scrollWidth > innerWidth,
            cards: [...document.querySelectorAll('.dr-dealer-card')].map(card => {
                const button = card.querySelector('.dr-dealer-call');
                const b = button.getBoundingClientRect(), c = card.getBoundingClientRect();
                const copy = card.querySelector('.dr-dealer-copy').getBoundingClientRect();
                const style = getComputedStyle(button);
                return { height: b.height, contained: b.left >= c.left && b.right <= c.right,
                    overlap: b.left < copy.right && b.right > copy.left && b.top < copy.bottom && b.bottom > copy.top,
                    centered: Math.abs((b.top + b.bottom - c.top - c.bottom) / 2) < 1,
                    background: style.backgroundColor, color: style.color, radius: style.borderRadius };
            })
        }));
        assert.equal(result.overflow, false, `Overflow at ${width}`);
        for (const card of result.cards) {
            assert.ok(card.height >= 44 && card.contained && !card.overlap, `Hit area/layout at ${width}: ${JSON.stringify(card)}`);
            assert.equal(card.color, 'rgb(17, 17, 17)');
            assert.equal(card.background, 'rgb(242, 242, 245)');
            assert.equal(card.radius, '999px');
            if (width >= 1024) assert.ok(card.centered, `Vertical center at ${width}`);
        }
        const call = page.getByRole('link', { name: 'Gọi đại lý', exact: true }).first();
        await call.hover();
        assert.equal(await call.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(227, 227, 232)');
        await page.mouse.move(0, 0);
        await page.screenshot({ path: `${output}/${width}.png`, fullPage: true });
        evidence.push({ width, ...result });
        await page.close();
    }
    await writeFile(`${output}/evidence.json`, JSON.stringify(evidence, null, 2));
    console.log('PASS: 320/390/768/1024/1440px, full and compact cards, geometry and gray pill style.');
} finally {
    await browser.close();
}
