import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const phase=process.argv[2];assert.ok(['before','after'].includes(phase));
const origin='http://127.0.0.1:8771',output=qaOutput(`awards-sticky-footer/${phase}`);
await mkdir(output,{recursive:true});
const before=phase==='after'?JSON.parse(await readFile(qaInput('awards-sticky-footer/before/evidence.json'),'utf8')):[];
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
async function capture(page,state,width,height,source) {
 const data=await page.evaluate(()=>{
  const rect=el=>el?.getBoundingClientRect().toJSON();
  const style=el=>{const s=getComputedStyle(el);return Object.fromEntries(['display','minHeight','height','flexDirection','flexGrow','flexShrink','flexBasis','position','marginTop','marginBottom','paddingTop','paddingBottom'].map(k=>[k,s[k]]));};
  const footer=document.querySelector('.wc-footer'),main=document.querySelector('#noi-dung');
  return {documentHeight:document.documentElement.scrollHeight,overflow:document.documentElement.scrollWidth>innerWidth,
   html:style(document.documentElement),body:style(document.body),main:main?style(main):null,footer:footer?style(footer):null,
   footerRect:rect(footer),mainRect:rect(main),
   sections:Object.fromEntries(['.wc-header','.wc-aw-intro','.wc-aw-chips','.wc-aw-panel','.wc-aw-history','.wc-aw-snapshot-note'].map(s=>[s,rect(document.querySelector(s))])),
   images:[...document.images].filter(el=>el.getBoundingClientRect().width>0).every(el=>el.complete&&el.naturalWidth>0),
   ctas:[...document.querySelectorAll('#noi-dung a')].filter(el=>el.getBoundingClientRect().width>0).map(rect)};
 });
 assert.equal(data.overflow,false,`${state}/${width}/${height} overflow`);assert.ok(data.images);
 const screenshot=await page.screenshot({path:`${output}/${state}-${width}-${height}.png`,fullPage:true});
 if(phase==='after') {
  const old=before.find(row=>row.state===state&&row.width===width&&row.height===height);assert.ok(old);
  if(state.startsWith('fixture-')||state==='live-awards') {
   assert.equal(data.body.display,'flex');assert.equal(data.body.flexDirection,'column');assert.equal(data.main.flexGrow,'1');
   assert.ok(Math.abs(data.footerRect.bottom-data.documentHeight)<=1,`${state}/${width}/${height} whitespace under footer`);
   assert.ok(data.footerRect.bottom>=height-1);assert.ok(data.footerRect.top>=data.mainRect.bottom-1);
   assert.ok(!['fixed','absolute'].includes(data.footer.position));
   for(const cta of data.ctas)assert.ok(cta.bottom<=data.footerRect.top+1);
   for(const [selector,rect] of Object.entries(data.sections))if(rect&&old.sections[selector])for(const key of ['x','y','width','height'])
    assert.ok(Math.abs(rect[key]-old.sections[selector][key])<=1,`${state}/${width} ${selector} changed ${key}`);
  }else assert.ok(screenshot.equals(await readFile(qaInput(`awards-sticky-footer/before/${state}-${width}-${height}.png`))),`${state}/${width} regression screenshot changed`);
 }
 evidence.push({state,width,height,source,...data});
}
try {
 for(const state of ['empty','design','one','long','many','detail'])for(const width of [320,390,768,1024,1440,1920])for(const height of [900,1200]) {
  const page=await browser.newPage({viewport:{width,height}}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('requestfailed',r=>errors.push(r.url()));page.on('response',r=>{if(r.status()>=400)errors.push(`${r.status()} ${r.url()}`);});
  const html=await readFile(`.laravel-tools/competition/awards-states/${state}.html`,'utf8');
  await page.route(`${origin}/__qa-awards-footer`,r=>r.fulfill({contentType:'text/html',body:html}));
  await page.goto(`${origin}/__qa-awards-footer`,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);
  await capture(page,`fixture-${state}`,width,height,'private DB-disabled Blade fixture; real assets/CSS');assert.deepEqual(errors,[]);await page.close();
 }
 const discovery=await browser.newPage();await discovery.goto(origin,{waitUntil:'networkidle'});
 const dealer=await discovery.locator('.wc-ranking-name a').first().getAttribute('href');await discovery.goto(dealer,{waitUntil:'domcontentloaded'});
 const lookup=await discovery.locator('.dr-lookup').getAttribute('href');await discovery.close();
 for(const [state,path] of [['live-awards','/awards'],['home','/'],['leaderboard','/leaderboard'],['lookup',lookup]])for(const width of [320,390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://fonts.googleapis.com/**',r=>r.fulfill({contentType:'text/css',body:''}));
  assert.equal((await page.goto(path.startsWith('http')?path:origin+path,{waitUntil:'networkidle'})).status(),200);await page.evaluate(()=>document.fonts.ready);
  await capture(page,state,width,900,'read-only live Laravel GET');assert.deepEqual(errors,[]);await page.close();
 }
 await writeFile(`${output}/evidence.json`,JSON.stringify(evidence,null,2));
 console.log(`PASS ${phase}: ${evidence.length} cases; ${phase==='after'?'Awards sticky footer and unchanged section geometry;18 shared-page screenshots byte-identical':'fresh baseline captured'}`);
 console.log(JSON.stringify(evidence.filter(r=>r.state==='fixture-empty'&&r.height===900&&[390,1440].includes(r.width)).map(r=>({width:r.width,body:r.body,main:r.main,footer:r.footer,footerRect:r.footerRect,documentHeight:r.documentHeight})),null,2));
} finally {await browser.close();}
