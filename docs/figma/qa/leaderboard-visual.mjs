import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8771';
const output='docs/figma/qa/screenshots/competition-leaderboard/after';
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
try {
 for(const state of ['live-month','live-all','design','long','empty','page-two','one','two']) {
  const html=state.startsWith('live')?null:await readFile(`.laravel-tools/competition/leaderboard-states/${state}.html`,'utf8');
  for(const width of [320,390,768,1024,1440,1920]) {
   const page=await browser.newPage({viewport:{width,height:844}});
   const errors=[];
   page.on('pageerror',error=>errors.push(error.message));
   page.on('requestfailed',request=>errors.push(`${request.url()} ${request.failure()?.errorText}`));
   page.on('response',response=>{if(response.status()>=400)errors.push(`${response.status()} ${response.url()}`);});
   if(html) await page.route(`${origin}/__qa-leaderboard`,route=>route.fulfill({contentType:'text/html',body:html}));
   assert.equal((await page.goto(html?`${origin}/__qa-leaderboard`:`${origin}/leaderboard?period=${state==='live-all'?'all':'month'}`,{waitUntil:'networkidle'})).status(),200);
   await page.evaluate(()=>document.fonts.ready);
   const data=await page.evaluate(()=>({
    overflow:document.documentElement.scrollWidth>innerWidth,
    heading:getComputedStyle(document.querySelector('.wc-lb-heading h1')).font,
    headingTop:document.querySelector('.wc-lb-heading').getBoundingClientRect().top,
    podium:document.querySelector('.wc-lb-podium')?.getBoundingClientRect().toJSON(),
    cards:[...document.querySelectorAll('.wc-lb-card')].map(el=>({text:el.innerText,rect:el.getBoundingClientRect().toJSON()})),
    rows:[...document.querySelectorAll('.wc-lb-row')].map(el=>el.innerText),
    textBoxes:[...document.querySelectorAll('.wc-lb-dealer-name,.wc-lb-name,.wc-lb-score,.wc-lb-points,.wc-lb-dealer-code,.wc-lb-code')].map(el=>({text:el.innerText,width:el.clientWidth,scroll:el.scrollWidth})),
    images:[...document.images].filter(el=>el.getBoundingClientRect().width>0).map(el=>({src:new URL(el.currentSrc).pathname,loaded:el.complete&&el.naturalWidth>0,width:el.getBoundingClientRect().width,height:el.getBoundingClientRect().height,declaredWidth:el.getAttribute('width'),declaredHeight:el.getAttribute('height')})),
    badLinks:[...document.querySelectorAll('a')].filter(el=>!el.getAttribute('href')||el.getAttribute('href')==='#').length,
    activeNav:[...document.querySelectorAll('.wc-desktop-nav a[aria-current]')].map(el=>el.textContent),
   }));
   assert.equal(data.overflow,false,`${state}/${width} horizontal overflow`);
   assert.ok(data.textBoxes.every(el=>el.scroll<=el.width+1),`${state}/${width} text overflow ${JSON.stringify(data.textBoxes.filter(el=>el.scroll>el.width+1))}`);
   assert.ok(data.images.every(img=>img.loaded),`${state}/${width} asset failure`);
   for(const img of data.images.filter(img=>img.src.includes('/leaderboard/'))) {
    assert.equal(img.width,Number(img.declaredWidth),`${img.src} width`);
    assert.equal(img.height,Number(img.declaredHeight),`${img.src} height`);
   }
   assert.equal(data.badLinks,0);assert.deepEqual(data.activeNav,['Bảng xếp hạng']);
   assert.equal(await page.locator('body').innerText().then(text=>/@(?:else|endif|if)\b/.test(text)),false,'Uncompiled Blade directive');
   if(state==='empty') {assert.equal(data.cards.length,0);assert.equal(await page.locator('.wc-lb-empty').count(),1);}
   if(state==='design') {
    assert.equal(data.cards.length,3);assert.equal(data.rows.length,7);
    if(width>=1200) {
     assert.ok(Math.abs(data.podium.width-1200)<=1);
     const rects=data.cards.map(card=>card.rect);
     assert.ok(rects[1].x<rects[0].x&&rects[0].x<rects[2].x,'Desktop2–1–3');
     assert.ok(Math.abs(rects[0].height-302)<=1,'First card302px');
    } else if(width===390) {
     assert.equal(data.podium.width,358);
     assert.ok(data.cards[0].rect.y<data.cards[1].rect.y&&data.cards[1].rect.y<data.cards[2].rect.y);
    }
   }
   if(state==='page-two') {assert.equal(data.rows.length,3);assert.match(data.rows[0],/21/);}
   if(['live-month','live-all'].includes(state)) {
    await page.locator(`.wc-lb-periods a[href$="period=${state==='live-month'?'all':'month'}"]`).click();
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.wc-lb-periods a[aria-current]').innerText(),state==='live-month'?'Toàn chương trình':'Tháng này');
    // Return to the state named in the screenshot.
    await page.goto(`${origin}/leaderboard?period=${state==='live-all'?'all':'month'}`,{waitUntil:'networkidle'});
   }
   if(width<1200) {
    const trigger=page.locator('[data-competition-menu]>summary');await trigger.click();
    await page.waitForFunction(()=>document.querySelector('[data-competition-menu]').open);
    await page.evaluate(()=>document.fonts.ready);
    assert.equal(await page.locator('.wc-mobile-panel a[aria-current]').innerText(),'Bảng xếp hạng');
    await page.keyboard.press('Escape');
    await page.waitForFunction(()=>!document.querySelector('[data-competition-menu]').open);
   }
   assert.deepEqual(errors,[],`${state}/${width}`);
   await page.evaluate(()=>document.activeElement?.blur());
   await page.screenshot({path:`${output}/${state}-${width}.png`,fullPage:true});
   if(state==='design'&&width===390) await page.locator('.wc-lb-podium').screenshot({path:`${output}/design-podium-390.png`});
   evidence.push({state,source:html?'isolated Blade fixture, DB disabled':'live Laravel GET, real local data',width,...data,errors});
   await page.close();
  }
 }
 // Shared navigation change must preserve the homepage.
 for(const width of [390,1440]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  await page.goto(origin,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);
  assert.equal(await page.locator('.wc-desktop-nav a[aria-current]').innerText(),'Trang chủ');
  const screenshot=await page.screenshot({path:`${output}/home-${width}.png`,fullPage:true});
  assert.ok(screenshot.equals(await readFile(`docs/figma/qa/screenshots/competition-leaderboard/before/home-${width}.png`)),`Home screenshot changed ${width}`);
  await page.close();
 }
 await writeFile(`${output}/evidence.json`,JSON.stringify(evidence,null,2));
 console.log(`PASS ${evidence.length} state/viewport combinations; period navigation, mobile menu/Escape, original SVG sizes, no overflow/errors; home screenshots unchanged.`);
}finally {await browser.close();}
