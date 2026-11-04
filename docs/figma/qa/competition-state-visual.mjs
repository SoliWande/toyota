import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin='http://127.0.0.1:8771';
const output='docs/figma/qa/screenshots/website-competition/final-qa';
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const results=[];
try {
    for (const state of ['empty','published','sales','dealer','admin','pending']) {
        const html=(await readFile(`.laravel-tools/competition/final-qa-states/${state}.html`,'utf8'))
            .replaceAll('http://localhost',origin).replaceAll('http://toyota.test',origin);
        for (const width of [320,390,768,1024,1440,1920]) {
            const page=await browser.newPage({viewport:{width,height:844}});
            const errors=[]; page.on('pageerror',e=>errors.push(e.message));
            await page.route(`${origin}/__qa-state`,route=>route.fulfill({contentType:'text/html',body:html}));
            await page.goto(`${origin}/__qa-state`,{waitUntil:'networkidle'});
            const geometry=await page.evaluate(()=>({
                overflow:document.documentElement.scrollWidth>innerWidth,
                columns:[...document.querySelectorAll('.wc-ranking-name,.wc-award-published li')].map(el=>({
                    width:el.clientWidth,scroll:el.scrollWidth
                }))
            }));
            assert.equal(geometry.overflow,false,`${state}/${width} overflow`);
            assert.ok(geometry.columns.every(el=>el.scroll<=el.width+1),`${state}/${width} text clipping`);
            if (['sales','dealer','admin','pending'].includes(state)) {
                assert.equal(await page.locator('.wc-hero-actions a[href$="/register"]').count(),0);
                assert.equal(await page.locator('form[action$="/logout"][method="POST"]').count(),1);
                if(width<1200) {
                    const trigger=page.locator('[data-competition-menu]>summary'); await trigger.click();
                    await page.waitForFunction(()=>document.querySelector('[data-competition-menu]').open);
                    const last=page.locator('.wc-logout'); await last.focus(); await page.keyboard.press('Tab');
                    assert.ok(await trigger.evaluate(el=>el===document.activeElement));
                    await page.keyboard.press('Escape');
                    assert.equal(await page.locator('[data-competition-menu]').getAttribute('open'),null);
                }
            }
            if (state==='published') assert.equal(await page.locator('.wc-award-published li').count(),3);
            if (state==='empty') assert.equal(await page.locator('.wc-empty').count(),1);
            if ([390,1440].includes(width)) await page.screenshot({path:`${output}/${state}-${width}.png`,fullPage:true});
            assert.deepEqual(errors,[]);
            results.push({state,width,geometry,errors}); await page.close();
        }
    }
    await writeFile(`${output}/state-evidence.json`,JSON.stringify({source:'Private isolated Blade/feature-test fixtures, no live authentication or production writes',results},null,2));
    console.log(`PASS ${results.length} isolated state/viewports: guest empty/published and Sales/Dealer/Admin/pending auth; long names, large scores, focus trap and logout markup.`);
} finally {await browser.close();}
