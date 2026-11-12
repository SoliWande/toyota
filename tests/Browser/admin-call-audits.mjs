import {chromium} from '../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import {mkdir,writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin = 'http://127.0.0.1:8776';
const directory = process.env.CALL_AUDIT_ARTIFACT_DIR || '.laravel-tools/p09l-browser';
await mkdir(directory,{recursive:true});
const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
try {
 const context = await browser.newContext({viewport:{width:1440,height:1046}});
 await context.addInitScript(()=>Object.defineProperty(window.crypto,'randomUUID',{value:undefined}));
 const page = await context.newPage();
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 let claims=0,records=0;
 page.on('request',request=>{
   const url=new URL(request.url());
   assert.ok(!url.searchParams.has('phone')&&!url.searchParams.has('claim_token'));
   if(url.pathname.endsWith('/claim')&&request.method()==='POST')claims++;
   if(url.pathname.endsWith('/record')&&request.method()==='POST')records++;
 });
 await page.goto(origin+'/admin/call-audits/workspace',{waitUntil:'networkidle'});
 await page.locator('.ca-workspace:visible').waitFor();
 await page.evaluate(()=>document.fonts.ready);
 assert.equal(await page.locator('.ca-row').count(),5);
 assert.equal(claims,0);assert.equal(records,0);
 assert.equal(await page.locator('.ca-save').isDisabled(),true);
 assert.ok((await page.locator('[data-phone]').innerText()).includes('****'));
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);
 await page.screenshot({path:directory+'/c08-preview-1440.png',fullPage:true});
 await page.locator('[data-start]').click();
 await page.waitForFunction(()=>!document.querySelector('[data-phone]').textContent.includes('*')&&!document.querySelector('.ca-result').disabled);
 assert.equal(claims,1);
 for(let i=1;i<=2;i++)await page.locator(`[name=answer_${i}][value=true]`).check({force:true});
 await page.locator('[name=outcome][value=correct]').check({force:true});
 assert.ok(!(await page.locator('[data-warning]').innerText()).includes('chưa khớp'));
 const callingGeometry=await page.locator('.ca-workspace').boundingBox();
 assert.deepEqual([callingGeometry.x,callingGeometry.y,callingGeometry.width],[716,236,692]);
 assert.ok(Math.abs(callingGeometry.height-762)<=1);
 await page.screenshot({path:directory+'/c08-calling-1440.png',fullPage:true});
 assert.equal(records,0);
 await page.locator('[name=answer_3][value=true]').check({force:true});
 assert.equal(await page.locator('.ca-save').isEnabled(),true);
 let unblock;
 const held=new Promise(resolve=>{unblock=resolve;});
 let committed;
 const saved=new Promise(resolve=>{committed=resolve;});
 await page.route('**/items/*/record',async route=>{
   const response=await route.fetch();
   assert.equal(response.status(),200);committed();await held;await route.fulfill({response});
 });
 const original=await page.locator('.ca-row[aria-current]').getAttribute('data-item');
 await page.locator('.ca-save').click();await saved;
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);
 assert.equal(await page.locator('.ca-save').isDisabled(),true);
 await page.locator('.ca-save').dispatchEvent('click');
 assert.equal(records,1);unblock();
 await page.locator('[data-call-toast]:visible').waitFor();
 await page.waitForFunction(id=>document.querySelector('.ca-row[aria-current]')?.dataset.item!==id,original);
 assert.equal(await page.locator('.ca-row').count(),4);
 assert.equal(claims,1); // Next real item is previewed, never auto-claimed.
 const zeroMarkers=page.locator('.ca-dealer .ca-track i[data-zero]');
 assert.ok(await zeroMarkers.count()>0);
 for(const marker of await zeroMarkers.all()) {
   const geometry=await marker.boundingBox();assert.equal(geometry.width,6);assert.equal(geometry.height,6);
 }
 await page.screenshot({path:directory+'/c08-success-1440.png',fullPage:true});
 await page.unroute('**/items/*/record');
 await page.locator('[data-start]').click();
 await page.waitForFunction(()=>!document.querySelector('.ca-result').disabled);
 const releasedItem=await page.locator('.ca-row[aria-current]').getAttribute('data-item');
 await page.locator('[data-tab=recall]').click();await page.waitForFunction(()=>!document.querySelector('[data-call-audit]').hasAttribute('data-error')&&document.querySelector('[data-tab=recall]').hasAttribute('aria-current'));
 assert.equal(await page.locator('.ca-row').count(),0);
 assert.equal(await page.locator('.ca-workspace').isVisible(),false);
 await page.locator('[data-tab=needs_call]').click();await page.locator('.ca-workspace:visible').waitFor();
 await page.locator(`[data-item="${releasedItem}"]`).click();
 await page.waitForLoadState('networkidle');
 await page.locator('[data-start]').click();await page.waitForFunction(()=>!document.querySelector('.ca-result').disabled);
 await page.locator('[name=answer_1][value=false]').check({force:true});
 await page.locator('[name=answer_2][value=true]').check({force:true});
 await page.locator('[name=answer_3][value=true]').check({force:true});
 await page.locator('[name=outcome][value=wrong]').check({force:true});
 assert.equal(await page.locator('[data-warning]').innerText(),'Thông tin khách hàng chưa khớp. Kết quả sẽ được ghi nhận để kiểm tra và xử lý.');
 await page.screenshot({path:directory+'/c08-wrong-1440.png',fullPage:true});
 await page.locator('.ca-save').click();await page.waitForFunction(()=>document.querySelectorAll('.ca-row').length===3);
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);
 await page.locator('[data-start]').click();await page.waitForFunction(()=>!document.querySelector('.ca-result').disabled);
 await page.locator('[name=outcome][value=no_answer]').check({force:true});
 assert.ok((await page.locator('[data-warning]').innerText()).startsWith('Không nghe máy:'));
 await page.screenshot({path:directory+'/c08-no-answer-1440.png',fullPage:true});
 assert.equal(await page.locator('.ca-questions input:checked').count(),0);
 await page.locator('.ca-save').click();await page.waitForFunction(()=>document.querySelectorAll('.ca-row').length===2);
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);
 await page.locator('[data-tab=recall]').click();await page.waitForFunction(()=>document.querySelector('[data-tab=recall]').hasAttribute('aria-current'));
 await page.locator('.ca-workspace:visible').waitFor();
 assert.equal(await page.locator('.ca-row').count(),1);
 assert.equal(await page.locator('[data-start]').isDisabled(),true);
 await page.screenshot({path:directory+'/c08-recall-1440.png',fullPage:true});
 await page.locator('[data-tab=completed]').click();await page.waitForFunction(()=>document.querySelector('[data-tab=completed]').hasAttribute('aria-current'));
 assert.equal(await page.locator('.ca-row').count(),2);
 await page.screenshot({path:directory+'/c08-completed-1440.png',fullPage:true});
 const pendingRow=page.locator('.ca-row').filter({hasText:'Sai thông tin'});
 await pendingRow.click();await page.waitForFunction(()=>!document.querySelector('[data-call-audit]').hasAttribute('aria-busy'));
 assert.equal(await page.locator('[data-warning]').innerText(),'Thông tin khách hàng chưa khớp. Kết quả sẽ được ghi nhận để kiểm tra và xử lý.');
 assert.equal(await page.locator('.ca-save').isDisabled(),true);
 await page.screenshot({path:directory+'/c08-finding-pending-1440.png',fullPage:true});
 await page.locator('[data-tab=needs_call]').click();await page.locator('.ca-workspace:visible').waitFor();
 for(let remaining=2;remaining>=1;remaining--){
   await page.locator('[data-start]').click();await page.waitForFunction(()=>!document.querySelector('.ca-result').disabled);
   for(let i=1;i<=3;i++)await page.locator(`[name=answer_${i}][value=true]`).check({force:true});
   await page.locator('[name=outcome][value=correct]').check({force:true});
   if(remaining===2){
     let firstPayload;let failOnce=true;
     await page.route('**/items/*/record',async route=>{
       const payload=route.request().postDataJSON();
       if(failOnce){firstPayload=payload;failOnce=false;return route.fulfill({status:503,contentType:'application/json',body:'{"error":"test_failure"}'});}
       assert.deepEqual(payload,firstPayload);return route.continue();
     });
     await page.locator('.ca-save').click();await page.waitForFunction(()=>document.querySelector('[data-call-audit]').hasAttribute('data-error'));
     assert.equal(await page.locator('[data-call-toast]').isVisible(),false);assert.equal(await page.locator('.ca-save').isEnabled(),true);
   }
   await page.locator('.ca-save').click();await page.waitForFunction(n=>document.querySelectorAll('.ca-row').length===n,remaining-1);
   if(remaining===2){await page.unroute('**/items/*/record');await page.locator('[data-call-toast]:visible').waitFor();}
 }
 assert.equal(await page.locator('.ca-workspace').isVisible(),false);assert.equal(await page.locator('[data-call-toast]').isVisible(),false);
 await page.screenshot({path:directory+'/c08-last-item-1440.png',fullPage:true});
 await page.locator('[data-tab=completed]').click();await page.locator('.ca-workspace:visible').waitFor();
 const checks=await page.evaluate(()=>({
   viewport:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,
   split:[...document.querySelectorAll('.ca-left,.ca-workspace')].map(e=>({x:e.getBoundingClientRect().x,width:e.getBoundingClientRect().width})),
   assets:[...document.querySelectorAll('.ca-page img,.ca-toast img')].map(e=>({src:e.getAttribute('src'),loaded:e.complete&&e.naturalWidth>0,width:e.width,height:e.height,visible:!!e.getClientRects().length,renderedWidth:e.getBoundingClientRect().width,renderedHeight:e.getBoundingClientRect().height})),
   storage:[localStorage.length,sessionStorage.length],
 }));
 assert.equal(checks.overflow,false);assert.ok(checks.assets.every(asset=>asset.loaded));
 assert.ok(checks.assets.filter(asset=>asset.visible).every(asset=>asset.renderedWidth===asset.width&&asset.renderedHeight===asset.height));
 assert.deepEqual(checks.split.map(e=>e.width),[420,692]);
 assert.deepEqual(checks.storage,[0,0]);assert.deepEqual(errors,[]);
 await writeFile(directory+'/checks.json',JSON.stringify({checks,callingGeometry,claims,records,errors,mode:'live Laravel on guarded toyota_testing; one injected503 fault, no mocked success responses'},null,2));
 process.stdout.write('PASS live Desktop1440 claim/release/Correct/Wrong/no-answer/tabs/commit-toast/privacy/assets\n');
} finally {await browser.close();}
