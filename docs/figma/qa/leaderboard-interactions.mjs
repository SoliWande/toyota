import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8771';
const output='docs/figma/qa/screenshots/competition-leaderboard/after';
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const results=[];
try {
 for(const width of [390,1440]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  await page.goto(`${origin}/leaderboard`,{waitUntil:'networkidle'});
  assert.match(await page.title(),/Bảng xếp hạng/);
  const dealerLink=page.locator('.wc-lb-card').first();
  if(await dealerLink.count()) {
   const href=await dealerLink.getAttribute('href');
   const [response]=await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),dealerLink.click()]);
   assert.equal(response.status(),200);
   assert.ok(!page.url().includes('/leaderboard'));
   assert.equal(await page.locator('h1').count(),1);
   results.push({width,interaction:'Actual dealer detail click, legacy URL follows redirect',href,finalUrl:page.url()});
  }
  await page.goto(`${origin}/leaderboard`,{waitUntil:'networkidle'});
  const all=page.locator('.wc-lb-periods a[href$="period=all"]');
  await all.focus();await page.keyboard.press('Enter');await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('.wc-lb-periods [aria-current]').innerText(),'Toàn chương trình');
  if(width===390) {
   await page.locator('[data-competition-menu]>summary').click();
   await page.locator('.wc-mobile-panel a').filter({hasText:'Trang chủ'}).click();
  }else await page.locator('.wc-desktop-nav a').filter({hasText:'Trang chủ'}).click();
  await page.waitForLoadState('networkidle');assert.equal(await page.locator('.wc-hero').count(),1);
  results.push({width,interaction:'Keyboard period change and actual home navigation',pass:true});
  await page.goto(`${origin}/leaderboard`,{waitUntil:'networkidle'});
  // Probe actual Vietnamese glyphs at both weights, beyond numeric scoreboard use.
  await page.evaluate(async()=>{
   for(const weight of [600,700]) {
    const probe=document.createElement('span');probe.id=`lb-font-${weight}`;
    probe.textContent='MỖI KẾT NỐI MỘT HÀNH TRÌNH MỚI CỘNG ĐỒNG';
    probe.style.cssText=`position:absolute;left:-2000px;font: ${weight} 24px/32px Montserrat, Inter`;
    document.body.append(probe);
   }
   await document.fonts.ready;
  });
  const cdp=await page.context().newCDPSession(page);
  await cdp.send('DOM.enable');await cdp.send('CSS.enable');
  const {root}=await cdp.send('DOM.getDocument');
  for(const weight of [600,700]) {
   const {nodeId}=await cdp.send('DOM.querySelector',{nodeId:root.nodeId,selector:`#lb-font-${weight}`});
   const {fonts}=await cdp.send('CSS.getPlatformFontsForNode',{nodeId});
   assert.ok(fonts.length&&fonts.every(font=>font.isCustomFont&&font.familyName.includes('Montserrat')));
   assert.ok(fonts.every(font=>font.postScriptName.includes(weight===600?'SemiBold':'Bold')));
   results.push({width,weight,fonts});
  }
  await page.close();
 }
 // Final template fix only affects note-only lists; keep new screenshots separate.
 for(const state of ['one','two']) for(const width of [320,390,768,1024,1440,1920]) {
  const html=await readFile(`.laravel-tools/competition/leaderboard-states/${state}.html`,'utf8');
  const page=await browser.newPage({viewport:{width,height:844}});
  await page.route(`${origin}/__qa-final`,route=>route.fulfill({contentType:'text/html',body:html}));
  await page.goto(`${origin}/__qa-final`,{waitUntil:'networkidle'});
  assert.equal(await page.locator('.wc-lb-rows').count(),0);
  assert.equal(await page.locator('.wc-lb-table-head').count(),0);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  assert.equal((await page.locator('body').innerText()).includes('@else'),false);
  await page.screenshot({path:`${output}/${state}-${width}.png`,fullPage:true});
  results.push({state,width,source:'DB-disabled Blade fixture',noteWithoutEmptyList:true});
  await page.close();
 }
 await writeFile(`${output}/interaction-evidence.json`,JSON.stringify(results,null,2));
 console.log('PASS actual Dealer/home clicks, keyboard filters, Montserrat Vietnamese600/700 CDP, and final note-only lists12 state/viewports.');
}finally {await browser.close();}
