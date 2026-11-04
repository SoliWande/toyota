import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const output='docs/figma/qa/screenshots/hero-heading/font-restored';
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
try {
 for(const width of [390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  const failures=[];
  page.on('pageerror',error=>failures.push(error.message));
  page.on('requestfailed',request=>failures.push(request.url()));
  page.on('response',response=>{if(response.status()>=400) failures.push(`${response.status()} ${response.url()}`);});
  assert.equal((await page.goto('http://127.0.0.1:8771/',{waitUntil:'networkidle'})).status(),200);
  await page.evaluate(()=>document.fonts.ready);
  const data=await page.evaluate(()=>{
   const heading=document.querySelector('.wc-hero h1'),s=getComputedStyle(heading);
   const marker=document.createElement('span');marker.style.cssText='display:inline-block;width:0;height:0;vertical-align:baseline';
   heading.prepend(marker);const baseline=marker.getBoundingClientRect().y;marker.remove();
   const ctx=document.createElement('canvas').getContext('2d');ctx.font=`${s.fontWeight} ${s.fontSize} Montserrat`;
   const inkTop=baseline-ctx.measureText('MỖI KẾT NỐI.').actualBoundingBoxAscent;
   const inkBottom=baseline+parseFloat(s.lineHeight)+ctx.measureText('MỘT HÀNH TRÌNH MỚI.').actualBoundingBoxDescent;
   const widths={};for(const weight of [600,700]) {ctx.font=`${weight} 48px Montserrat`;widths[weight]=ctx.measureText('CỘNG ĐỒNG MỖI KẾT NỐI MỘT HÀNH TRÌNH MỚI').width;}
   return {weight:s.fontWeight,labelWeight:getComputedStyle(document.querySelector('.wc-hero-label')).fontWeight,lineHeight:s.lineHeight,heroHeight:document.querySelector('.wc-hero').getBoundingClientRect().height,inkAboveBox:Math.max(0,heading.getBoundingClientRect().top-inkTop),inkBelowBox:Math.max(0,inkBottom-heading.getBoundingClientRect().bottom),overflow:document.documentElement.scrollWidth>innerWidth,widths,fonts:[...document.fonts].filter(f=>f.family.includes('Montserrat')).map(f=>({weight:f.weight,status:f.status})),fontRequests:performance.getEntriesByType('resource').filter(r=>/woff/.test(r.name)).map(r=>r.name)};
  });
  const cdp=await page.context().newCDPSession(page);
  await cdp.send('DOM.enable');await cdp.send('CSS.enable');
  const {root}=await cdp.send('DOM.getDocument');
  data.actualFonts={};
  for(const selector of ['.wc-hero h1','.wc-hero-label']) {
   const {nodeId}=await cdp.send('DOM.querySelector',{nodeId:root.nodeId,selector});
   const {fonts}=await cdp.send('CSS.getPlatformFontsForNode',{nodeId});
   data.actualFonts[selector]=fonts;
   assert.ok(fonts.length>0 && fonts.every(f=>f.isCustomFont&&f.familyName.includes('Montserrat')),`Fallback ${selector} ${width}`);
  }
  assert.equal(data.weight,'700');assert.equal(data.labelWeight,'600');
  assert.notEqual(data.widths[600],data.widths[700],'600/700 must render different metrics');
  assert.equal(data.lineHeight,width===390?'32px':'56px');
  assert.equal(data.heroHeight,width===390?780:680);
  assert.equal(data.overflow,false);assert.equal(data.inkAboveBox,0);assert.equal(data.inkBelowBox,0);
  assert.deepEqual(failures,[]);
  await page.screenshot({path:`${output}/home-${width}.png`,fullPage:true});
  await page.locator('.wc-hero').screenshot({path:`${output}/hero-${width}.png`});
  await page.locator('.wc-hero h1').screenshot({path:`${output}/heading-${width}.png`});
  evidence.push({width,...data,failures});await page.close();
 }
 await writeFile(`${output}/font-verification.json`,JSON.stringify(evidence,null,2));
 console.log(JSON.stringify(evidence.map(({width,weight,labelWeight,inkAboveBox,inkBelowBox,widths,actualFonts})=>({width,weight,labelWeight,inkAboveBox,inkBelowBox,widths,actualFonts})),null,2));
}finally {await browser.close();}
