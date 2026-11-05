import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const phase=process.argv[2]||'before',origin='http://127.0.0.1:8771';
const output=qaOutput(`competition-awards/${phase}`);await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const results=[];
try {
 const discovery=await browser.newPage();await discovery.goto(origin,{waitUntil:'networkidle'});
 const dealer=await discovery.locator('.wc-ranking-name a').first().getAttribute('href');
 await discovery.goto(dealer,{waitUntil:'domcontentloaded'});const lookup=await discovery.locator('.dr-lookup').getAttribute('href');await discovery.close();
 for(const [name,path] of [['home','/'],['leaderboard','/leaderboard'],['awards','/awards'],['lookup',lookup]])for(const width of [320,390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  await page.route('https://fonts.googleapis.com/**',route=>route.fulfill({contentType:'text/css',body:''}));
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  assert.equal((await page.goto(path.startsWith('http')?path:origin+path,{waitUntil:'networkidle'})).status(),200);
  await page.evaluate(()=>document.fonts.ready);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  const screenshot=await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
  if(phase==='after'&&name!=='awards')assert.ok(screenshot.equals(await readFile(qaInput(`competition-awards/before/${name}-${width}.png`))),`${name}/${width} changed`);
  assert.deepEqual(errors,[]);results.push({name,width,status:200,errors,source:'live GET real local data'});await page.close();
 }
 await writeFile(`${output}/regression.json`,JSON.stringify(results,null,2));console.log(`PASS ${phase}:24 live page/viewports${phase==='after'?';18 home/leaderboard/lookup screenshots unchanged':''}`);
}finally{await browser.close();}
