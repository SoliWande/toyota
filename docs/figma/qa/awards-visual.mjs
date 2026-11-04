import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8771',output='docs/figma/qa/screenshots/competition-awards/after';
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
const local=html=>html.replaceAll('http://localhost',origin).replaceAll('http://toyota.test',origin);
try {
 for(const state of ['design','empty','long','many','page-two','weekly','missing','one','detail','selected-2','selected-3'])for(const width of [320,390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  page.on('requestfailed',r=>errors.push(`${r.url()} ${r.failure()?.errorText}`));
  page.on('response',r=>{if(r.status()>=400)errors.push(`${r.status()} ${r.url()}`);});
  const html=local(await readFile(`.laravel-tools/competition/awards-states/${state}.html`,'utf8'));
  await page.route(`${origin}/__qa-awards`,route=>route.fulfill({contentType:'text/html',body:html}));
  await page.goto(`${origin}/__qa-awards`,{waitUntil:'networkidle'});
  // FontFaceSet.check only reports already loaded faces. Explicitly load both
  // subsets/weights: the monthly panel itself does not use Montserrat 600.
  await page.evaluate(async()=>{
   await Promise.all([600,700].map(weight=>document.fonts.load(`${weight} 28px Montserrat`,'Vinh danh Đại lý MỖI KỲ thi đua')));
   await document.fonts.ready;
  });
  const data=await page.evaluate(()=>{
   const rect=el=>el?.getBoundingClientRect().toJSON();
   return {overflow:document.documentElement.scrollWidth>innerWidth,
    intro:rect(document.querySelector('.wc-aw-intro')),panel:rect(document.querySelector('.wc-aw-panel')),
    history:rect(document.querySelector('.wc-aw-history')),chips:rect(document.querySelector('.wc-aw-chips')),
    titleFont:getComputedStyle(document.querySelector('.wc-aw-intro h1')).font,
    periodFont:document.querySelector('.wc-aw-panel-heading h2')?getComputedStyle(document.querySelector('.wc-aw-panel-heading h2')).font:null,
    vietnameseFonts:[600,700].map(weight=>({weight,loaded:document.fonts.check(`${weight} 28px Montserrat`,'Vinh danh Đại lý MỖI KỲ thi đua')})),
    winners:[...document.querySelectorAll('.wc-aw-winner')].map(el=>({rank:el.dataset.awardRank,rect:rect(el)})),
    text:[...document.querySelectorAll('.wc-aw-winner h3,.wc-aw-score,.wc-aw-period-copy,.wc-aw-panel-heading h2')].map(el=>({text:el.textContent.trim(),width:el.clientWidth,scroll:el.scrollWidth})),
    assets:[...document.images].filter(el=>el.getBoundingClientRect().width>0).map(el=>({src:new URL(el.currentSrc).pathname,loaded:el.complete&&el.naturalWidth>0,width:el.getBoundingClientRect().width,height:el.getBoundingClientRect().height,declaredWidth:el.getAttribute('width'),declaredHeight:el.getAttribute('height')})),
    activeNav:[...document.querySelectorAll('.wc-desktop-nav [aria-current]')].map(el=>el.textContent.trim()),
    activePeriod:[...document.querySelectorAll('.wc-aw-chips [aria-current]')].map(el=>el.textContent.trim()),
    badLinks:[...document.querySelectorAll('a')].filter(el=>!el.getAttribute('href')||el.getAttribute('href')==='#').length,
    uncompiled:/@(?:if|else|endif)\b/.test(document.body.innerText)
   };
  });
  await page.screenshot({path:`${output}/${state}-${width}.png`,fullPage:true});
  assert.equal(data.overflow,false,`${state}/${width} overflow`);
  assert.ok(data.text.every(el=>el.scroll<=el.width+1),`${state}/${width} clipping ${JSON.stringify(data.text.filter(el=>el.scroll>el.width+1))}`);
  assert.ok(data.assets.every(asset=>asset.loaded));
  assert.ok(data.vietnameseFonts.every(font=>font.loaded),`${state}/${width} Vietnamese Montserrat 600/700`);
  for(const asset of data.assets.filter(asset=>asset.src.includes('/awards/'))) {
   assert.equal(asset.width,Number(asset.declaredWidth),`${asset.src} width`);assert.equal(asset.height,Number(asset.declaredHeight));
  }
  assert.deepEqual(data.activeNav,['Vinh danh']);assert.equal(data.badLinks,0);assert.equal(data.uncompiled,false);
  if(state==='empty')assert.equal(data.winners.length,0);else assert.equal(data.winners.length,state==='one'?1:3);
  if(state==='design'&&width===1440) {
   assert.equal(data.panel.x,444);assert.equal(data.panel.y,252);assert.equal(data.panel.width,876);
   assert.ok(Math.abs(data.panel.height-426)<=1,`Panel height ${data.panel.height}`);
   for(const winner of data.winners)assert.ok(Math.abs(winner.rect.height-222)<=1);
  }
  if(state==='design'&&width===390) {
   assert.equal(data.panel.width,358);assert.equal(data.panel.y,262,`Mobile panel Y ${data.panel.y}`);
   assert.equal(data.panel.height,446,`Mobile panel height ${data.panel.height}`);
  }
  if(width<1200) {
   await page.locator('[data-competition-menu]>summary').click();await page.waitForFunction(()=>document.querySelector('[data-competition-menu]').open);
   await page.evaluate(()=>document.fonts.ready);assert.equal(await page.locator('.wc-mobile-panel [aria-current]').innerText(),'Vinh danh');
   await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('[data-competition-menu]').open);
  }
  if(state==='design'&&width===390) {
   const selected=local(await readFile('.laravel-tools/competition/awards-states/selected-2.html','utf8'));
   await page.route(`${origin}/awards/2`,route=>route.fulfill({contentType:'text/html',body:selected}));
   await page.locator('.wc-aw-chips a').nth(1).click();await page.waitForLoadState('networkidle');
   assert.match(await page.locator('.wc-aw-panel-heading h2').innerText(),/9\/2026/);
   await page.locator('.wc-aw-chips a[aria-current]').evaluate(el=>el.scrollIntoView({inline:'center'}));
   assert.match(await page.locator('.wc-aw-chips [aria-current]').innerText(),/9\/2026/);
  }
  assert.deepEqual(errors,[]);
  evidence.push({state,width,source:'DB-disabled Blade DTO fixture; production build/assets/JS',...data,errors});
  await page.close();
 }
 await writeFile(`${output}/visual.json`,JSON.stringify(evidence,null,2));
 console.log(`PASS ${evidence.length} Awards state/viewports; design geometry, assets, overflow, menu/Escape and selected-period click.`);
}finally{await browser.close();}
