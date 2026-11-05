import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin='http://127.0.0.1:8771',output=qaOutput('competition-awards/interactions');
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
try {
 for(const width of [320,390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const many=await readFile('.laravel-tools/competition/awards-states/many.html','utf8');
  const second=await readFile('.laravel-tools/competition/awards-states/page-two.html','utf8');
  await page.route(`${origin}/__qa-awards-many`,r=>r.fulfill({contentType:'text/html',body:many}));
  await page.route(`${origin}/awards?page=2`,r=>r.fulfill({contentType:'text/html',body:second}));
  await page.goto(`${origin}/__qa-awards-many`,{waitUntil:'networkidle'});
  await page.locator('.wc-aw-pagination [rel=next]').click();await page.waitForLoadState('networkidle');
  assert.match(await page.locator('.wc-aw-pagination').innerText(),/Trang 2 \/ 2/);
  assert.equal(await page.locator('.wc-aw-panel').getAttribute('data-award-id'),'13');
  assert.equal(await page.locator('.wc-aw-period-list a').count(),2);
  assert.equal(await page.locator('.wc-aw-pagination [rel=prev]').getAttribute('href'),`${origin}/awards?page=1`);
  await page.unroute(`${origin}/awards?page=2`);
  // These filter links perform real Laravel GET requests. No database writes.
  for(const [label,type] of [['Tuần','weekly'],['Tháng','monthly'],['Tất cả','']]) {
   const response=page.waitForResponse(r=>r.request().isNavigationRequest()&&r.url().startsWith(`${origin}/awards`));
   await page.locator('.wc-aw-filter a').filter({hasText:label}).click();
   assert.equal((await response).status(),200);await page.waitForLoadState('networkidle');
   assert.equal(new URL(page.url()).searchParams.get('period_type')||'',type);
   assert.equal(await page.locator('.wc-aw-filter [aria-current]').innerText(),label);
  }
  assert.deepEqual(errors,[]);evidence.push({width,pagination:'private Blade fixture click PASS',filters:'three real Laravel GET links PASS',errors});
  await page.close();
 }
 for(const state of ['default','validation','D07','D08','D10','D11','D12','D13','D14','D15','long'])for(const width of [320,390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://fonts.googleapis.com/**',r=>r.fulfill({contentType:'text/css',body:''}));
  const html=(await readFile(`.laravel-tools/lookup-sticky-footer/${state}.html`,'utf8')).replaceAll('http://localhost',origin).replaceAll('http://toyota.test',origin);
  await page.route(`${origin}/__qa-lookup-regression`,r=>r.fulfill({contentType:'text/html',body:html}));
  await page.goto(`${origin}/__qa-lookup-regression`,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);
  const data=await page.evaluate(()=>{
   const footer=document.querySelector('.dr-footer'),main=document.querySelector('#dr-main');
   return {overflow:document.documentElement.scrollWidth>innerWidth,footerVisible:getComputedStyle(footer).display!=='none',
    footer:footer.getBoundingClientRect().toJSON(),main:main.getBoundingClientRect().toJSON(),height:document.documentElement.scrollHeight,
    assets:[...document.images].filter(el=>el.getBoundingClientRect().width>0).every(el=>el.complete&&el.naturalWidth>0)};
  });
  assert.equal(data.overflow,false);assert.ok(data.assets);assert.deepEqual(errors,[]);
  if(data.footerVisible) {
   assert.ok(Math.abs(data.footer.bottom-data.height)<=1,`${state}/${width} space after footer`);
   assert.ok(data.footer.top>=data.main.bottom-1);assert.ok(data.footer.bottom>=899);
  }else assert.ok(state==='D08'&&width<768);
  await page.screenshot({path:`${output}/lookup-${state}-${width}.png`,fullPage:true});
  evidence.push({state,width,source:'fresh DB-disabled Blade fixture, current production build',...data,errors});await page.close();
 }
 await writeFile(`${output}/evidence.json`,JSON.stringify(evidence,null,2));
 console.log('PASS pagination and three live filters at six widths;66 fresh lookup state/viewports, footer/overflow/assets/JS.');
}finally{await browser.close();}
