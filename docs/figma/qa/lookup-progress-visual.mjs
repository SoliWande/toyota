import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

// Presentation fixtures are loopback-only and have no database connection.
const output = qaOutput('phase-3c-progress');
await mkdir(output, { recursive: true });
const cases = [
    ['D07', '/D07', ['completed', 'completed', 'warning'], 'Cần bổ sung'],
    ['D08', '/D08', [], 'Đã gửi bổ sung'],
    ['D09', '/D09', [], null],
    ['D10', '/D10', [], 'Không tìm thấy đăng ký'],
    ['D11', '/D11', ['current', 'pending', 'pending'], 'Chưa hoàn tất'],
    ['D12', '/D12', ['completed', 'current', 'pending'], 'Chờ vào group'],
    ['D13', '/D13', ['completed', 'completed', 'current'], 'Chờ duyệt'],
    ['D14', '/D14', ['completed', 'completed', 'completed'], 'Thành viên'],
    ['D15', '/D15', ['completed', 'completed', 'blocked'], 'Chưa xác nhận'],
    ['FOUND', '/FOUND', ['completed', 'pending', 'pending'], 'Đã tìm thấy đăng ký'],
    ['D07-unknown', '/D07?group=unknown', ['completed', 'pending', 'warning'], 'Cần bổ sung'],
    ['D07-contact', '/D07?group=unknown&ownership=none', ['completed', 'pending', 'warning'], 'Cần bổ sung'],
    ['D15-unknown', '/D15?group=unknown', ['completed', 'pending', 'blocked'], 'Chưa xác nhận'],
    ['D15-long', '/D15?group=unknown&text=long', ['completed', 'pending', 'blocked'], 'Chưa xác nhận'],
];
const widths = [320, 390, 768, 1024, 1440];
const evidence = [];
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
    for (const [name, path, expected, badge] of cases) {
        for (const width of widths) {
            const height = width < 768 ? 844 : 1024;
            const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 1 });
            // Validate the existing local font assets without a Google Fonts/network dependency.
            await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
            const errors = [];
            const failures = [];
            page.on('pageerror', error => errors.push(error.message));
            page.on('requestfailed', request => failures.push(request.url()));
            page.on('response', response => { if (response.status() >= 400) failures.push(`${response.status()} ${response.url()}`); });
            await page.goto(`http://127.0.0.1:8766${path}`, { waitUntil: 'networkidle' });
            await page.evaluate(() => document.fonts.ready);
            const progress = page.locator('[data-lookup-progress]');
            assert.equal(await progress.count(), expected.length ? 1 : 0, `${name}/${width}: progress count`);
            if (expected.length) {
                await progress.scrollIntoViewIfNeeded();
                assert.ok(await progress.isVisible(), `${name}/${width}: progress must be visible`);
                assert.deepEqual(await progress.locator('li').evaluateAll(items => items.map(item => item.dataset.state)), expected);
                assert.equal(await progress.locator('[aria-current="step"]').count(), expected.includes('current') || expected.includes('warning') || expected.includes('blocked') ? 1 : 0);
                assert.equal(await progress.locator('li').count(), 3);
                assert.equal(await progress.locator('.dr-lookup-progress-text p').count(), 3);
                assert.equal(await progress.locator('.dr-lookup-progress-text>span').count(), 3, 'Accessible status text for every step');
            }
            if (badge) assert.ok((await page.locator('body').innerText()).includes(badge), `${name}/${width}: badge/copy`);
            if (['D07', 'D07-unknown'].includes(name)) assert.equal(await page.locator('[data-registration-supplement]').count(), 1);
            if (name === 'D07-contact') assert.equal(await page.locator('[data-registration-supplement]').count(), 0);
            const measure = await page.evaluate(() => {
                const rect = el => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }; };
                const steps = [...document.querySelectorAll('[data-lookup-progress]>li')].map(el => {
                    const node = el.querySelector('.dr-lookup-progress-node');
                    const text = el.querySelector('.dr-lookup-progress-text');
                    const title = text.querySelector('strong');
                    const rail = el.querySelector('.dr-lookup-progress-rail');
                    const after = getComputedStyle(rail, '::after');
                    const paragraph = text.querySelector('p');
                    return { key: el.dataset.step, state: el.dataset.state, box: rect(el), node: rect(node), text: rect(text), title: rect(title),
                        titleFont: getComputedStyle(title).font, descriptionFont: getComputedStyle(paragraph).font,
                        description: paragraph.textContent.trim(), icon: node.querySelector('img')?.currentSrc || 'question-mark',
                        rail: { box: rect(rail), width: after.width, height: after.height, color: after.backgroundColor, content: after.content } };
                });
                return { overflow: document.documentElement.scrollWidth > innerWidth, fonts: document.fonts.check('600 16px Inter'),
                    imagesLoaded: [...document.images].every(img => img.complete && img.naturalWidth > 0),
                    progress: document.querySelector('[data-lookup-progress]') ? rect(document.querySelector('[data-lookup-progress]')) : null,
                    steps, badge: document.querySelector('.dr-status-chip,.dr-d12-status')?.textContent.trim() || null };
            });
            assert.equal(measure.overflow, false, `${name}/${width}: horizontal overflow`);
            assert.equal(measure.fonts, true, `${name}/${width}: Inter`);
            assert.equal(measure.imagesLoaded, true, `${name}/${width}: image loading`);
            for (const [index, step] of measure.steps.entries()) {
                const near = (a, b, label) => assert.ok(Math.abs(a - b) <= 0.6, `${name}/${width}/${step.key}: ${label} ${a} != ${b}`);
                near(step.node.width, width < 768 ? 24 : 28, 'node width');
                near(step.node.height, width < 768 ? 24 : 28, 'node height');
                assert.ok(step.title.height >= 20 && step.text.right <= step.box.right + 1, `${name}/${width}: title clipped`);
                if (width < 768) {
                    near(step.text.x - step.node.right, 14, 'mobile text gap');
                    if (index) assert.ok(step.node.y >= measure.steps[index - 1].text.bottom - 0.5, 'Vertical steps must not overlap');
                    if (index < 2) near(parseFloat(step.rail.width), 2, 'vertical rail width');
                } else {
                    near(step.text.y - step.node.bottom, 12, 'desktop text gap');
                    near(step.box.width, measure.progress.width / 3, 'equal columns');
                    if (index < 2) {
                        near(parseFloat(step.rail.height), 2, 'horizontal rail height');
                        near(parseFloat(step.rail.width), step.box.width - 36, 'rail length to next node');
                    }
                }
                if (index < 2) assert.equal(step.rail.color, step.state === 'completed' ? 'rgb(191, 230, 204)' : 'rgb(227, 227, 232)');
            }
            assert.deepEqual(errors, [], `${name}/${width}: JavaScript errors`);
            assert.deepEqual(failures, [], `${name}/${width}: failed resources`);
            assert.ok(!(await page.locator('body').innerText()).includes('48 giờ'), `${name}/${width}: no SLA`);
            // JS result focus is tested below; default-state comparison excludes its transient outline.
            await page.evaluate(() => { document.activeElement?.blur(); scrollTo(0, 0); });
            await page.screenshot({ path: `${output}/${name}-${width}.png`, fullPage: true });
            evidence.push({ name, path, viewport: { width, height }, errors, failures, ...measure });
            if (name === 'D10' && width === 390) {
                await page.locator('#lookup-phone').fill('');
                await page.locator('#lookup-license_plate').fill('');
                await page.locator('[data-lookup-submit]').click();
                assert.equal(await page.locator('#lookup-phone').getAttribute('aria-invalid'), 'true');
                assert.equal(await page.evaluate(() => document.activeElement.id), 'lookup-phone');
                await page.locator('#lookup-phone').press('Enter');
                assert.equal(await page.evaluate(() => document.activeElement.id), 'lookup-phone');
                assert.equal(new URL(page.url()).pathname, '/D10', 'Invalid lookup must not navigate');
                await page.screenshot({ path: `${output}/lookup-validation-390.png`, fullPage: true });
                evidence.at(-1).validationFocus = true;
            }
            if (name === 'D07' && width === 390) {
                await page.locator('#supplement-facebook_profile_url').fill('');
                await page.locator('[data-registration-supplement] button[type=submit]').click();
                assert.equal(await page.locator('#supplement-facebook_profile_url').getAttribute('aria-invalid'), 'true');
                assert.equal(await page.evaluate(() => document.activeElement.id), 'supplement-facebook_profile_url');
                await page.locator('#supplement-facebook_profile_url').press('Enter');
                assert.equal(await page.evaluate(() => document.activeElement.id), 'supplement-facebook_profile_url');
                assert.equal(new URL(page.url()).pathname, '/D07', 'Invalid supplement must not fake D08');
                await page.screenshot({ path: `${output}/supplement-validation-390.png`, fullPage: true });
                evidence.at(-1).validationFocus = true;
            }
            await page.close();
        }
    }
} finally {
    await browser.close();
    await writeFile(`${output}/evidence.json`, JSON.stringify(evidence, null, 2) + '\n');
}
const gallery = cases.map(([name]) => `<section><h2>${name}</h2>${['D12', 'D13', 'D14'].includes(name) ? `<p><a href="${name === 'D12' ? '../lookup-sync/figma/D12.png' : `figma/${name}.png`}">Figma desktop</a>${name === 'D12' ? ' · <a href="figma/M12.png">Figma mobile</a>' : ''}</p>` : ''}<div class="row">${[1440, 390].map(width => `<a href="${name}-${width}.png"><img loading="lazy" src="${name}-${width}.png" alt="${name} ${width}px"></a>`).join('')}</div></section>`).join('');
const linkedGallery = gallery.replace(/(href|src)="((?:figma|\.\.\/lookup-sync)\/[^"\s]+)"/g, (_, attr, relative) => {
    const source = relative.startsWith("../lookup-sync/") ? relative.slice(3) : `phase-3c-progress/${relative}`;
    return `${attr}="${qaReferenceLink(output, source)}"`;
});
await writeFile(`${output}/index.html`, `<!doctype html><html lang="vi"><meta charset="utf-8"><title>Phase 3C progress QA</title><style>body{font:16px sans-serif;background:#eee;margin:24px}.row{display:flex;gap:20px;align-items:flex-start}.row a:first-child{width:70%}.row a:last-child{width:25%}img{width:100%;display:block}section{margin-bottom:40px}</style><h1>Phase 3C — Progress QA</h1><p>Fixture loopback, database disabled; 5 widths. <a href="${qaReferenceLink(output, 'lookup-sync/index.html')}">Figma references and earlier comparison</a></p>${linkedGallery}</html>`);
console.log(`PASS: ${cases.length} cases x ${widths.length} widths = ${evidence.length} default screenshots + 2 validation/focus screenshots; semantic states, markers, rails, fonts, resources and overflow.`);
