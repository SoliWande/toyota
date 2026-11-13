import {chromium} from '../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import {mkdir,writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const base='http://127.0.0.1:8777';
const award=process.env.P09FINAL_AWARD_ID;
assert.match(award,/^[0-9]+$/);
const directory=process.env.P09FINAL_OUTPUT || '.laravel-tools/p09-final-publication-browser';
await mkdir(directory,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
try {
 const page=await browser.newPage({viewport:{width:1440,height:956}});
 const errors=[]; page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>new URL(route.request().url()).origin===base ? route.continue() : route.abort());
 await page.goto(base+'/admin/awards?selected='+award,{waitUntil:'networkidle'});
 await page.evaluate(()=>document.fonts.ready);
 if(process.env.P09FINAL_MODE==='supplement') {
  assert.equal(await page.locator('.ac-publish').isDisabled(),true);
  assert.ok((await page.locator('.ac-call').getAttribute('data-call-command')).endsWith('/supplemental'));
  await page.screenshot({path:directory+'/c07-needs-supplement-1440.png',fullPage:true});
  let commands=0;let batch;
  await page.route('**/supplemental',async route=>{
   commands++;const response=await route.fetch();assert.equal(response.status(),200);
   batch=(await response.json()).data.batch_id;await route.fulfill({response});
  });
  await page.locator('.ac-call').click();
  await page.locator('.ac-call').dispatchEvent('click');
  await page.waitForURL('**/admin/call-audits/workspace?**');
  await page.waitForLoadState('networkidle');
  assert.equal(commands,1);
  assert.equal(new URL(page.url()).searchParams.get('purpose'),'award_top3');
  assert.equal(new URL(page.url()).searchParams.get('batch'),String(batch));
  assert.equal(await page.locator('[data-purpose="award_top3"]').getAttribute('data-batch'),String(batch));
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:directory+'/c08-supplement-1440.png',fullPage:true});
  assert.deepEqual(errors,[]);
  await writeFile(directory+'/supplement-checks.json',JSON.stringify({viewport:1440,liveLaravel:true,commands,batch,errors},null,2));
 } else {
 assert.equal(await page.locator('.ac-publish').isEnabled(),true);
 assert.equal(await page.locator('.ac-step[aria-current="step"] strong').textContent(),'Công bố');
 assert.equal(await page.locator('.ac-steps .ac-complete').count(),2);
 assert.equal(await page.locator('.ac-result-badge .ad-badge-approved').count(),3);
 assert.equal(await page.locator('.ac-period-selected .ad-badge-pending').textContent(),'Đang kiểm tra');
 assert.equal(await page.locator('.ac-threshold').textContent().then(t=>t.includes('[x]%')),false);
 assert.equal(await page.locator('[data-admin-toast]').count(),0);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await page.screenshot({path:directory+'/c07-ready-1440.png',fullPage:true});
 // Failed POST retains draft and never fabricates success; use the same button again for a real commit.
 await page.route('**/publish',route=>route.fulfill({status:409,contentType:'application/json',body:'{"error":"publication_not_ready"}'}));
 await page.locator('.ac-publish').click();
 await page.waitForFunction(()=>!document.querySelector('.ac-publish').disabled);
 assert.equal(await page.locator('.ac-period-selected').innerText().then(t=>t.includes('\u0110\u00e3 c\u00f4ng b\u1ed1')),false);
 await page.unroute('**/publish');
 let release;const gate=new Promise(r=>release=r);let arrived;const waiting=new Promise(r=>arrived=r);
 let posts=0;let savedBody;let savedStatus;
 await page.route('**/publish',async route=>{posts++;arrived();await gate;const result=await route.fetch();savedStatus=result.status();savedBody=await result.json();await route.fulfill({response:result});});
 await page.locator('.ac-publish').click();
 await waiting;
 assert.equal(await page.locator('.ac-publish').isDisabled(),true);
 await page.locator('.ac-publish').dispatchEvent('click');
 assert.equal(posts,1);
 assert.equal(await page.locator('.ac-period-selected').innerText().then(t=>t.includes('\u0110\u00e3 c\u00f4ng b\u1ed1')),false);
 const committed=page.waitForResponse(r=>r.url().endsWith('/publish')&&r.request().method()==='POST');
 release();
 const response=await committed;
 assert.equal(response.status(),200);
 assert.equal(savedStatus,200);
 assert.equal(savedBody.data.status,'published');
 await page.waitForFunction(()=>document.querySelector('.ac-period-selected')?.textContent.includes('\u0110\u00e3 c\u00f4ng b\u1ed1'));
 assert.equal(await page.locator('.ac-publish').isDisabled(),true);
 assert.equal(await page.locator('[data-admin-toast]').count(),0);
 assert.equal(await page.locator('.ac-step[aria-current="step"]').count(),0);
 assert.equal(await page.locator('.ac-steps .ac-complete').count(),3);
 await page.screenshot({path:directory+'/c07-published-1440.png',fullPage:true});
 assert.deepEqual(errors,[]);
 await writeFile(directory+'/checks.json',JSON.stringify({viewport:1440,liveLaravel:true,postOnlyAfterClick:true,commitBeforePublished:true,doubleClickPosts:posts,errors},null,2));
 }
} finally {await browser.close();}
