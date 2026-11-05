import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin='http://127.0.0.1:8771';
const output=qaOutput('competition-leaderboard/final-qa');
const matrix=JSON.parse(await readFile(qaInput('competition-leaderboard/final-qa/matrix.json'),'utf8'));
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const results=[];
const localHtml=html=>html.replaceAll('http://localhost',origin).replaceAll('http://toyota.test',origin);
const geometry=page=>page.evaluate(()=>({
 overflow:document.documentElement.scrollWidth>innerWidth,
 titleFont:getComputedStyle(document.querySelector('.wc-lb-heading h1')).font,
 cards:[...document.querySelectorAll('.wc-lb-card')].map(el=>({href:el.getAttribute('href'),x:el.getBoundingClientRect().x,y:el.getBoundingClientRect().y,width:el.getBoundingClientRect().width})),
 text:[...document.querySelectorAll('.wc-lb-dealer-name,.wc-lb-dealer-code,.wc-lb-name,.wc-lb-code,.wc-lb-points,.wc-lb-score')].map(el=>({text:el.textContent,width:el.clientWidth,scroll:el.scrollWidth})),
 images:[...document.images].filter(el=>el.getBoundingClientRect().width>0).map(el=>({src:new URL(el.currentSrc).pathname,loaded:el.complete&&el.naturalWidth>0,width:el.getBoundingClientRect().width,height:el.getBoundingClientRect().height})),
 footer:!!document.querySelector('.wc-footer'),
 badLinks:[...document.querySelectorAll('a')].filter(el=>!el.getAttribute('href')||el.getAttribute('href')==='#').length,
}));
const check=(data,label)=>{
 assert.equal(data.overflow,false,`${label} horizontal overflow`);
 assert.ok(data.text.every(el=>el.scroll<=el.width+1),`${label} text clipping`);
 assert.ok(data.images.every(el=>el.loaded),`${label} missing asset`);
 assert.equal(data.badLinks,0);assert.equal(data.footer,true);
};
try {
 for(const scenario of matrix) for(const width of [320,390,1440]) {
  const page=await browser.newPage({viewport:{width,height:844}});
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('requestfailed',request=>errors.push(`${request.url()} ${request.failure()?.errorText}`));
  page.on('response',response=>{if(response.status()>=400)errors.push(`${response.status()} ${response.url()}`);});
  // All leaderboard navigations use freshly exported real Laravel test responses;
  // assets/JS run live, no runtime fixture route or production database writes.
  await page.route(url=>url.origin===origin&&url.pathname==='/leaderboard',async route=>{
   const url=new URL(route.request().url());
   const period=url.searchParams.get('period')||'month';
   const number=Number(url.searchParams.get('dealers_page')||1);
   const html=await readFile(`.laravel-tools/competition/leaderboard-final-qa/count-${scenario.count}-${period}-page-${number}.html`,'utf8');
   await route.fulfill({contentType:'text/html',body:localHtml(html)});
  });
  await page.goto(`${origin}/leaderboard?period=${scenario.period}`,{waitUntil:'networkidle'});
  for(const entry of scenario.pages) {
   await page.evaluate(()=>document.fonts.ready);
   assert.equal(await page.locator('.wc-lb-periods [aria-current]').innerText(),scenario.period==='month'?'Tháng này':'Toàn chương trình');
   assert.equal(await page.locator('.wc-lb-card').count(),Math.min(3,scenario.count));
   assert.equal(await page.locator('.wc-lb-row').count(),entry.listIds.length);
   const data=await geometry(page);
   const label=`${scenario.count}/${scenario.period}/page${entry.page}/${width}`;
   check(data,label);
   if(data.cards.length===3) {
    if(width===1440)assert.ok(data.cards[1].x<data.cards[0].x&&data.cards[0].x<data.cards[2].x,`${label} podium2–1–3`);
    else assert.ok(data.cards[0].y<data.cards[1].y&&data.cards[1].y<data.cards[2].y,`${label} mobile1–2–3`);
   }
   if(scenario.count===0)assert.equal(await page.locator('.wc-lb-empty').count(),1);
   assert.deepEqual(errors,[],label);
   await page.screenshot({path:`${output}/count-${scenario.count}-${scenario.period}-page-${entry.page}-${width}.png`,fullPage:true});
   results.push({count:scenario.count,period:scenario.period,page:entry.page,width,source:'Fresh Laravel HTTP test HTML from isolated toyota_testing, browser intercept only',...data,errors:[...errors]});
   if(entry.page<scenario.totalPages) {
    await page.locator('.wc-lb-next').click();
    await page.waitForLoadState('networkidle');
    assert.equal(new URL(page.url()).searchParams.get('dealers_page'),String(entry.page+1));
   }
  }
  if(scenario.totalPages>1) {
   await page.locator('.wc-lb-prev').click();await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('period'),scenario.period);
   assert.equal(new URL(page.url()).searchParams.get('dealers_page'),String(scenario.totalPages-1));
  }
  await page.close();
 }
 for(const state of ['design','long']) for(const width of [320,390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:844}});
  const html=localHtml(await readFile(`.laravel-tools/competition/leaderboard-states/${state}.html`,'utf8'));
  await page.route(`${origin}/__qa-final-state`,route=>route.fulfill({contentType:'text/html',body:html}));
  await page.goto(`${origin}/__qa-final-state`,{waitUntil:'networkidle'});
  await page.evaluate(()=>document.fonts.ready);
  const data=await geometry(page);check(data,`${state}/${width}`);
  await page.screenshot({path:`${output}/${state}-${width}.png`,fullPage:true});
  if(width===390) await page.locator('.wc-lb-podium').screenshot({path:`${output}/${state}-podium-390.png`});
  results.push({state,width,source:'Fresh DB-disabled Blade reference/stress fixture',...data});
  await page.close();
 }
 for(const width of [320,390,1440]) {
  const page=await browser.newPage({viewport:{width,height:844}});
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  assert.equal((await page.goto(`${origin}/leaderboard`,{waitUntil:'networkidle'})).status(),200);
  await page.evaluate(()=>document.fonts.ready);
  await page.screenshot({path:`${output}/live-${width}.png`,fullPage:true});
  const first=page.locator('.wc-lb-card').first();
  if(await first.count()) {
   const [response]=await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),first.click()]);
   assert.equal(response.status(),200);
   await page.goto(`${origin}/leaderboard`,{waitUntil:'networkidle'});
  }
  await page.locator('.wc-lb-periods a[href$="period=all"]').focus();await page.keyboard.press('Enter');
  await page.waitForLoadState('networkidle');assert.equal(await page.locator('.wc-lb-periods [aria-current]').innerText(),'Toàn chương trình');
  if(width<1200) {
   const trigger=page.locator('[data-competition-menu]>summary');await trigger.click();
   await page.waitForFunction(()=>document.querySelector('[data-competition-menu]').open);
   await page.evaluate(()=>document.fonts.ready);
   assert.equal(await page.locator('.wc-mobile-panel a[aria-current]').innerText(),'Bảng xếp hạng');
   await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('[data-competition-menu]').open);
   await trigger.click();await page.locator('.wc-mobile-panel a').filter({hasText:'Trang chủ'}).click();
  }else await page.locator('.wc-desktop-nav a').filter({hasText:'Trang chủ'}).click();
  await page.waitForLoadState('networkidle');assert.equal(await page.locator('.wc-hero').count(),1);
  await page.goto(`${origin}/leaderboard`,{waitUntil:'networkidle'});
  await page.evaluate(async()=>{
   for(const weight of [600,700]){const el=document.createElement('span');el.id=`qa-font-${weight}`;el.textContent='MỖI KẾT NỐI MỘT HÀNH TRÌNH MỚI CỘNG ĐỒNG';el.style.cssText=`position:absolute;left:-2000px;font:${weight} 24px/32px Montserrat,Inter`;document.body.append(el);}
   await document.fonts.ready;
  });
  const cdp=await page.context().newCDPSession(page);await cdp.send('DOM.enable');await cdp.send('CSS.enable');
  const {root}=await cdp.send('DOM.getDocument');const fonts=[];
  for(const weight of [600,700]){const {nodeId}=await cdp.send('DOM.querySelector',{nodeId:root.nodeId,selector:`#qa-font-${weight}`});const row=await cdp.send('CSS.getPlatformFontsForNode',{nodeId});assert.ok(row.fonts.length&&row.fonts.every(font=>font.isCustomFont&&font.familyName.includes('Montserrat')&&font.postScriptName.includes(weight===600?'SemiBold':'Bold')));fonts.push({weight,...row});}
  assert.deepEqual(errors,[]);
  results.push({state:'live-interactions',width,source:'Actual Laravel GET navigation, no interception',dealerDetail:200,keyboardPeriod:true,mobileMenu:width<1200,home:true,fonts,errors});
  await page.close();
 }
 await writeFile(`${output}/visual.json`,JSON.stringify(results,null,2));
 console.log(`PASS ${results.length} final UI checks:78 matrix page/viewports +12 reference/stress +3 live navigation/font.`);
}finally {await browser.close();}
