import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const phase = process.argv[2] || 'after';
const folder = `docs/figma/qa/screenshots/lookup-sync/${phase}`;
await mkdir(folder, { recursive: true });
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const heights = { D07:883, D08:765, D09:1578, D10:809, D11:883, D12:883, D13:863, D14:883, D15:883 };
const evidence = [];
try {
    for (const [frame, height] of Object.entries(heights)) {
        const page = await browser.newPage({ viewport: { width:1440, height }, deviceScaleFactor:1 });
        const errors = [];
        const failedLocalRequests = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('requestfailed', request => { if(request.url().startsWith('http://127.0.0.1:8766')) failedLocalRequests.push(request.url()); });
        page.on('response', response => { if(response.url().startsWith('http://127.0.0.1:8766') && response.status()>=400) failedLocalRequests.push(`${response.status()} ${response.url()}`); });
        await page.goto(`http://127.0.0.1:8766/${frame}`, { waitUntil:'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path:`${folder}/${frame}.png`, fullPage:true });
        const geometry = await page.evaluate(() => {
            const selectors = ['.dr-header', '.dr-lookup-heading', '.dr-lookup-form', '.dr-lookup-results>h2', '.dr-lookup-result', '.dr-d12', '.dr-lookup-summary', '.dr-lookup-person', '.dr-lookup-draft-steps', '.dr-lookup-next', '.dr-lookup-empty', '.dr-supplement-success', '.dr-full-rules', '.dr-footer'];
            return { overflow:document.documentElement.scrollWidth > innerWidth, fonts:document.fonts.check('800 40px Inter'),
                loadedFonts:[...document.fonts].filter(f=>f.status==='loaded').map(f=>({family:f.family,weight:f.weight})),
                elements: Object.fromEntries(selectors.map(s => { const e=document.querySelector(s); if(!e) return [s,null]; const r=e.getBoundingClientRect(), c=getComputedStyle(e); return [s,{x:r.x,y:r.y,width:r.width,height:r.height,font:c.font,bg:c.backgroundColor}]; })),
                images:[...document.images].map(i=>({src:i.currentSrc,loaded:i.complete&&i.naturalWidth>0,width:i.width,height:i.height})),
                markers:[...document.querySelectorAll('.dr-lookup-draft-steps li,.dr-d12-progress li')].map(e=>{
                    const c=getComputedStyle(e,'::before'), rail=getComputedStyle(e,'::after');
                    return {state:e.className,width:c.width,height:c.height,asset:c.backgroundImage,content:c.content,rail:rail.backgroundColor};
                }) };
        });
        evidence.push({frame,viewport:{width:1440,height},errors,failedLocalRequests,...geometry});
        // Positions and dimensions taken directly from the Figma frame metadata.
        const near = (actual, expected, label) => assert.ok(Math.abs(actual-expected)<=0.5, `${frame} ${label}: ${actual}, expected ${expected}`);
        if (phase !== 'before') {
            near(geometry.elements['.dr-header'].height,72,'header height');
            near(geometry.elements['.dr-footer'].y,height-101,'footer Y');
            if(geometry.elements['.dr-lookup-form']) {
                const f=geometry.elements['.dr-lookup-form'];
                near(f.x,120,'form X'); near(f.y,224,'form Y'); near(f.width,1200,'form width'); near(f.height,140,'form height');
            }
            const card=geometry.elements['.dr-lookup-result']||geometry.elements['.dr-d12']||geometry.elements['.dr-lookup-empty'];
            if(card) { near(card.y,424,'result Y'); near(card.width,1200,'result width'); near(card.height,frame==='D13'?238:frame==='D10'?184:258,'result height'); }
            assert.ok(geometry.loadedFonts.some(f=>f.family==='Inter'),`${frame}: Inter must actually load`);
            assert.ok(geometry.images.every(i=>i.loaded),`${frame}: all static images must load`);
        }
        await page.setViewportSize({width:390,height:844});
        await page.screenshot({ path:`${folder}/${frame}-390.png`, fullPage:true });
        evidence[evidence.length-1].mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
        await page.setViewportSize({width:768,height:1024});
        await page.screenshot({ path:`${folder}/${frame}-768.png`, fullPage:true });
        evidence[evidence.length-1].tabletOverflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
        assert.ok(!geometry.overflow && !evidence.at(-1).mobileOverflow && !evidence.at(-1).tabletOverflow,`${frame}: horizontal overflow`);
        assert.deepEqual(errors,[],`${frame}: browser errors`);
        assert.deepEqual(failedLocalRequests,[],`${frame}: local resources`);
        await page.close();
    }
} finally { await browser.close(); }
await writeFile(`${folder}/evidence.json`, JSON.stringify(evidence,null,2)+'\n');
console.log(JSON.stringify(evidence.map(({frame,overflow,mobileOverflow,tabletOverflow,fonts,errors})=>({frame,overflow,mobileOverflow,tabletOverflow,fonts,errors})),null,2));
