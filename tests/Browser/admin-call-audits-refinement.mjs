import {chromium} from '../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import {mkdir,writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin='http://127.0.0.1:8776', directory='.laravel-tools/p09m-edges';
const due=process.env.P09M_DUE_ITEM, future=process.env.P09M_FUTURE_ITEM;
assert.match(due,/^\d+$/);assert.match(future,/^\d+$/);
await mkdir(directory,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
try {
 const context=await browser.newContext({viewport:{width:1440,height:1046}}), page=await context.newPage();
 const errors=[];let claims=0, records=0;
 page.on('pageerror',error=>errors.push(error.message));
 page.on('request',request=>{
   if(request.method()==='POST'&&new URL(request.url()).pathname.endsWith('/claim'))claims++;
   if(request.method()==='POST'&&new URL(request.url()).pathname.endsWith('/record'))records++;
 });
 const idle=()=>page.waitForFunction(()=>!document.querySelector('[data-call-audit]').hasAttribute('aria-busy'));
 const shot=name=>page.screenshot({path:directory+'/'+name+'-1440.png',fullPage:true});
 await page.goto(origin+'/admin/call-audits/workspace?month=2026-08',{waitUntil:'networkidle'});await idle();
 assert.equal(await page.locator('.ca-row').count(),0);assert.equal(await page.locator('.ca-progress').isVisible(),false);
 assert.equal(await page.locator('.ca-workspace').isVisible(),false);assert.equal(claims,0);assert.equal(records,0);
 assert.deepEqual(await page.locator('[data-tab-count]').allTextContents(),['0','0','0']);
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);await shot('no-batch');
 await page.goto(origin+'/admin/call-audits/workspace?month=2026-09',{waitUntil:'networkidle'});await idle();
 assert.equal(await page.locator('[data-progress]').innerText(),'0 / 0');
 assert.equal(await page.locator('.ca-workspace').isVisible(),false);assert.equal(await page.locator('.ca-row').count(),0);
 assert.equal(claims,0);assert.equal(records,0);await shot('zero-batch');
 await page.goto(origin+'/admin/call-audits/workspace?month=2026-10',{waitUntil:'networkidle'});await idle();
 assert.equal(await page.locator('[data-progress]').innerText(),'2 / 5');
 assert.deepEqual(await page.locator('[data-tab-count]').allTextContents(),['3','2','0']);await shot('underfill');
 const leasedItem=await page.locator('.ca-row[aria-current]').getAttribute('data-item');
 await page.clock.install();
 await page.locator('[data-start]').click();await idle();
 assert.equal(claims,1);assert.equal(await page.locator('.ca-result').evaluate(element=>element.disabled),false);
 await page.clock.fastForward(601000);
 assert.equal(await page.locator('.ca-workspace').isVisible(),false);
 assert.equal(await page.locator('[data-phone]').innerText(),'');
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);
 assert.equal(claims,1);assert.equal(records,0);await shot('lease-expired-ui-clock');
 // Choose an actual sibling by ID, without touching the expired-display lease.
 const available=await page.locator('.ca-row').evaluateAll((elements,id)=>elements.map(e=>e.dataset.item).find(value=>value!==id),leasedItem);
 await page.locator(`[data-item="${available}"]`).click();await idle();
 await page.locator('[data-start]').click();await idle();
 for(let i=1;i<=3;i++)await page.locator(`[name=answer_${i}][value=true]`).check({force:true});
 await page.locator('[name=outcome][value=correct]').check({force:true});
 await page.route('**/items/*/record',route=>route.fulfill({status:403,contentType:'application/json',body:'{"error":"injected_denial"}'}));
 await page.locator('.ca-save').click();await idle();await page.unroute('**/items/*/record');
 assert.equal(await page.locator('.ca-workspace').isVisible(),false);
 assert.equal(await page.locator('[data-phone]').innerText(),'');
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);await shot('post-denied');
 await page.locator('[data-tab=recall]').click();await idle();
 await page.locator(`[data-item="${future}"]`).click();await idle();
 assert.equal(await page.locator('[data-start]').isDisabled(),true);
 assert.ok((await page.locator('[data-phone]').innerText()).includes('****'));
 assert.equal(await page.locator('.ca-result').evaluate(element=>element.disabled),true);
 assert.equal(await page.locator('.ca-save').isDisabled(),true);await shot('recall-not-due');
 await page.locator(`[data-item="${due}"]`).click();await idle();
 assert.equal(await page.locator('[data-start]').isEnabled(),true);await shot('recall-due');
 await page.locator('[data-start]').click();await idle();
 assert.equal(await page.locator('[name=outcome]:checked').count(),0);
 assert.equal(await page.locator('.ca-questions input:checked').count(),0);
 await page.locator('[name=outcome][value=no_answer]').check({force:true});
 await page.locator('.ca-save').click();await idle();
 assert.equal(await page.locator('[data-progress]').innerText(),'2 / 5');
 assert.equal(await page.locator('[data-call-toast]').isVisible(),false);
 assert.deepEqual(await page.locator('[data-tab-count]').allTextContents(),['3','1','1']);
 await page.locator('[data-tab=completed]').click();await idle();
 assert.equal(await page.locator('.ca-row').count(),1);
 assert.equal(await page.locator('.ca-row .ad-badge-approved').count(),0);
 assert.equal(await page.locator('[name=outcome][value=no_answer]').isChecked(),true);
 assert.equal(await page.locator('[data-start]').isDisabled(),true);
 assert.equal(await page.locator('.ca-save').isDisabled(),true);await shot('completed-no-answer2');
 const controls=await page.evaluate(()=>({
   overflow:document.documentElement.scrollWidth>innerWidth,
   tabs:[...document.querySelectorAll('[data-tab]')].map(e=>({active:e.hasAttribute('aria-current'),font:getComputedStyle(e).fontSize,weight:getComputedStyle(e).fontWeight,background:getComputedStyle(e).backgroundColor})),
   countFont:getComputedStyle(document.querySelector('[data-tab-count]')).fontSize,
   dialogs:document.querySelectorAll('dialog').length,storage:[localStorage.length,sessionStorage.length],
 }));
 assert.equal(controls.overflow,false);assert.equal(controls.dialogs,0);assert.deepEqual(controls.storage,[0,0]);
 assert.deepEqual(controls.tabs.map(tab=>tab.weight),['500','500','600']);assert.equal(controls.countFont,'12px');
 assert.ok(controls.tabs.every(tab=>tab.font==='13px'));assert.deepEqual(errors,[]);
 await writeFile(directory+'/checks.json',JSON.stringify({controls,claims,records,errors,
   mode:'Live C1/C2 persisted fixtures; historical/current no-answer prepared through C1; client-only clock expiry and injected403 are UI fault tests'},null,2));
 process.stdout.write('PASS Desktop1440 no-batch/zero/underfill/recall-due/recall-future/expiry/denial/no-answer2\n');
} finally {await browser.close();}
