import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const phase=process.argv[2]||'before';assert.ok(['before','after'].includes(phase));
const probe=process.argv.includes('--probe');
const origin='http://127.0.0.1:8771';
const root='docs/figma/qa/screenshots/lookup-sticky-footer';
const output=`${root}/${phase}`;await mkdir(output,{recursive:true});
const before=phase==='after'?JSON.parse(await readFile(`${root}/before/evidence.json`,'utf8')):null;
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
const local=html=>html.replaceAll('http://localhost',origin).replaceAll('http://toyota.test',origin);
const measure=page=>page.evaluate(()=>{
 const rect=el=>el?el.getBoundingClientRect().toJSON():null;
 const style=el=>{const s=getComputedStyle(el);return Object.fromEntries(['display','minHeight','height','flexDirection','flexGrow','flexShrink','flexBasis','marginTop','marginBottom','paddingTop','paddingBottom','position','overflow','boxSizing'].map(k=>[k,s[k]]));};
 const footer=document.querySelector('.dr-footer'),main=document.querySelector('#dr-main');
 const selectors=['.dr-header','.dr-lookup-content','.dr-lookup-heading','.dr-lookup-form','.dr-lookup-result','.dr-d12','.dr-lookup-empty','.dr-supplement-success','.dr-lookup-next','.dr-footer'];
 return {viewport:{width:innerWidth,height:innerHeight},documentHeight:document.documentElement.scrollHeight,overflow:document.documentElement.scrollWidth>innerWidth,
  html:style(document.documentElement),body:style(document.body),main:style(main),footer:style(footer),
  bodyRect:rect(document.body),mainRect:rect(main),footerRect:rect(footer),footerVisible:getComputedStyle(footer).display!=='none',
  elements:Object.fromEntries(selectors.map(selector=>[selector,rect(document.querySelector(selector))])),
  stylesheets:[...document.styleSheets].map(s=>s.href).filter(Boolean),
  images:[...document.images].filter(el=>el.getBoundingClientRect().width>0).map(el=>({src:el.currentSrc,loaded:el.complete&&el.naturalWidth>0})),
  ctas:[...main.querySelectorAll('.dr-button')].filter(el=>el.getBoundingClientRect().width>0).map(el=>({text:el.textContent.trim(),rect:rect(el)})),
 };
});
try {
 const states=probe?['default']:['default','validation','D07','D08','D10','D11','D12','D13','D14','D15','long','found','rules'];
 for(const state of states) for(const width of probe?[390,1440]:[320,390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const failures=[];page.on('requestfailed',r=>{if(r.url().startsWith(origin))failures.push(`${r.url()} ${r.failure()?.errorText}`);});
  page.on('response',r=>{if(r.url().startsWith(origin)&&r.status()>=400)failures.push(`${r.status()} ${r.url()}`);});
  // Lookup/rules explicitly bundle local Inter/Montserrat. Make fixture rendering
  // independent of the layout's unrelated legacy Google stylesheet request.
  await page.route('https://fonts.googleapis.com/**',route=>route.fulfill({contentType:'text/css',body:''}));
  const html=local(await readFile(`.laravel-tools/lookup-sticky-footer/${state}.html`,'utf8'));
  await page.route(`${origin}/__qa-footer`,route=>route.fulfill({contentType:'text/html',body:html}));
  await page.goto(`${origin}/__qa-footer`,{waitUntil:'networkidle'});await page.evaluate(()=>document.fonts.ready);
  const data=await measure(page);const label=`${state}/${width}`;
  assert.equal(data.overflow,false,`${label} horizontal overflow`);
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);
  assert.ok(data.images.every(img=>img.loaded),`${label} images`);
  if(phase==='after'&&state!=='rules') {
   assert.equal(data.body.display,'flex');assert.equal(data.body.flexDirection,'column');
   assert.ok(Number(data.main.flexGrow)>0);
   if(data.footerVisible) {
    assert.ok(Math.abs(data.footerRect.bottom-data.documentHeight)<=1,`${label} space after footer`);
    assert.ok(data.footerRect.top>=data.mainRect.bottom-1,`${label} footer overlaps main`);
    assert.ok(data.footerRect.bottom>=900-1,`${label} footer above viewport bottom`);
    assert.ok(!['fixed','absolute'].includes(data.footer.position));
    for(const cta of data.ctas)assert.ok(cta.rect.bottom<=data.footerRect.top+1,`${label} footer overlays CTA`);
   }else assert.ok(state==='D08'&&width<768,`${label} unexpected hidden footer`);
   const old=before.find(row=>row.state===state&&row.width===width);
   // Keep original section geometry. Only free main space and footer Y may grow.
   for(const selector of ['.dr-header','.dr-lookup-heading','.dr-lookup-form','.dr-lookup-result','.dr-d12','.dr-lookup-empty','.dr-lookup-next','.dr-supplement-success']) {
    if(!data.elements[selector]||!old.elements[selector])continue;
    for(const key of ['x','y','width','height'])assert.ok(Math.abs(data.elements[selector][key]-old.elements[selector][key])<=1,`${label} ${selector} changed ${key}`);
   }
  }
  await page.screenshot({path:`${output}/${state}-${width}.png`,fullPage:true});
  evidence.push({state,width,source:'DB-disabled private Blade fixture; local production build',errors,failures,...data});
  if(!probe&&['default','long'].includes(state)) {
   await page.setViewportSize({width,height:1200});
   const tall=await measure(page);
   if(phase==='after'&&tall.footerVisible) {
    assert.ok(Math.abs(tall.footerRect.bottom-tall.documentHeight)<=1,`${label}/tall space after footer`);
    assert.ok(tall.footerRect.bottom>=1199,`${label}/tall viewport`);
    assert.ok(tall.footerRect.top>=tall.mainRect.bottom-1);
   }
   evidence.push({state:`${state}-tall`,width,source:'Same fixture at1200px height',...tall});
  }
  await page.close();
 }
 if(!probe) {
  // Shared-layout and competition regressions use read-only live routes.
  const discovery=await browser.newPage();await discovery.goto(origin,{waitUntil:'networkidle'});
  const dealerHref=await discovery.locator('.wc-ranking-name a').first().getAttribute('href');
  await discovery.goto(dealerHref,{waitUntil:'networkidle'});
  const lookupHref=await discovery.locator('.dr-lookup').getAttribute('href');
  await discovery.close();
  const paths=[['home','/'],['leaderboard','/leaderboard'],['lookup-live',lookupHref],['landing',dealerHref],['step-two','/_preview/dealer-registration/step-two'],['success','/_preview/dealer-registration/success'],['inactive','/_preview/dealer-registration/inactive']];
  for(const [name,path] of paths)for(const width of [320,390,768,1024,1440,1920]) {
   const page=await browser.newPage({viewport:{width,height:900}});
   const fontCss=(await readFile('public/dealer-registration/lookup-fonts.css','utf8')).replaceAll('url(',`url(${origin}/dealer-registration/`);
   await page.route('https://fonts.googleapis.com/**',route=>route.fulfill({contentType:'text/css',body:fontCss}));
   const response=await page.goto(path.startsWith('http')?path:origin+path,{waitUntil:'networkidle'});
   assert.equal(response.status(),200,`${name}/${width} HTTP`);await page.evaluate(()=>document.fonts.ready);
   if(name==='lookup-live') {
    const data=await measure(page);
    if(phase==='after'){assert.ok(data.footerRect.bottom>=899);assert.ok(Math.abs(data.footerRect.bottom-data.documentHeight)<=1);}
    evidence.push({state:name,width,source:'Actual Laravel GET, real local Dealer; no writes',...data});
   }
   await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
   if(phase==='after'&&name!=='lookup-live') {
    assert.ok((await readFile(`${output}/${name}-${width}.png`)).equals(await readFile(`${root}/before/${name}-${width}.png`)),`${name}/${width} shared page changed`);
   }
   await page.close();
  }
 }
 await writeFile(`${output}/${probe?'probe':'evidence'}.json`,JSON.stringify(evidence,null,2));
 console.log(JSON.stringify(probe?evidence.map(({width,html,body,main,footer,footerRect,documentHeight,stylesheets})=>({width,html,body,main,footer,footerRect,documentHeight,stylesheets})):{phase,measurements:evidence.length,footerChecks:phase==='after'?'PASS':'baseline captured',sharedPages:probe?0:42},null,2));
}finally {await browser.close();}
