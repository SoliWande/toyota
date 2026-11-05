import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const phase=process.argv[2]||'before';
const output=qaOutput(`hero-heading/${phase}`);
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
const before=phase==='after'?JSON.parse(await readFile(qaInput('hero-heading/before/evidence.json'),'utf8')):null;
try {
 for(const width of [390,768,1024,1440,1920]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  await page.goto('http://127.0.0.1:8771/',{waitUntil:'networkidle'});
  await page.evaluate(()=>document.fonts.ready);
  const data=await page.evaluate(()=>{
   const heading=document.querySelector('.wc-hero h1'), style=getComputedStyle(heading);
   const rect=el=>{const r=el.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height};};
   const marker=document.createElement('span');Object.assign(marker.style,{display:'inline-block',width:'0',height:'0',padding:'0',margin:'0',verticalAlign:'baseline'});
   heading.prepend(marker);const baseline=marker.getBoundingClientRect().y;marker.remove();
   const ctx=document.createElement('canvas').getContext('2d');ctx.font=`${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
   const metrics=ctx.measureText('MỖI KẾT NỐI.');
   const second=ctx.measureText('MỘT HÀNH TRÌNH MỚI.');
   const inkTop=baseline-metrics.actualBoundingBoxAscent;
   const inkBottom=baseline+parseFloat(style.lineHeight)+second.actualBoundingBoxDescent;
   const ancestors=[];for(let el=heading;el;el=el.parentElement){const s=getComputedStyle(el);ancestors.push({tag:el.tagName,class:el.className,overflow:s.overflow,height:s.height,maxHeight:s.maxHeight,transform:s.transform});}
   return {heading:rect(heading),hero:rect(document.querySelector('.wc-hero')),cta:rect(document.querySelector('.wc-hero-actions')),font:style.font,fontFamily:style.fontFamily,weight:style.fontWeight,lineHeight:style.lineHeight,paddingTop:style.paddingTop,backgroundClip:style.backgroundClip,baseline,ascent:metrics.actualBoundingBoxAscent,inkTop,inkBottom,inkAboveBox:Math.max(0,heading.getBoundingClientRect().top-inkTop),inkBelowBox:Math.max(0,inkBottom-heading.getBoundingClientRect().bottom),overflow:document.documentElement.scrollWidth>innerWidth,ancestors};
  });
  const cdp=await page.context().newCDPSession(page);
  await cdp.send('DOM.enable');await cdp.send('CSS.enable');
  const {root}=await cdp.send('DOM.getDocument');
  const {nodeId}=await cdp.send('DOM.querySelector',{nodeId:root.nodeId,selector:'.wc-hero h1'});
  data.actualFonts=(await cdp.send('CSS.getPlatformFontsForNode',{nodeId})).fonts;
  const label=await cdp.send('DOM.querySelector',{nodeId:root.nodeId,selector:'.wc-hero-label'});
  data.labelFonts=(await cdp.send('CSS.getPlatformFontsForNode',{nodeId:label.nodeId})).fonts;
  data.fontFaces=await page.evaluate(()=>[...document.fonts].filter(face=>face.family.includes('Montserrat')).map(face=>({family:face.family,weight:face.weight,status:face.status})));
  data.labelWeight=await page.locator('.wc-hero-label').evaluate(el=>getComputedStyle(el).fontWeight);
  if(phase==='after') {
   const old=before.find(row=>row.width===width);
   assert.equal(data.overflow,false,`Overflow ${width}`);
   assert.equal(data.inkAboveBox,0,`Accent outside gradient paint box ${width}`);
   assert.equal(data.inkBelowBox,0,`Lower accents outside gradient paint box ${width}`);
   assert.equal(data.weight,'700');
   assert.equal(data.lineHeight,width===390?'32px':'56px');
   assert.equal(data.hero.height,width===390?780:680);
   for(const key of ['x','y','height']) assert.ok(Math.abs(data.cta[key]-old.cta[key])<=.5,`CTA ${key} changed at ${width}`);
   assert.ok(Math.abs(data.baseline-old.baseline)<=.5,`Text baseline moved ${width}`);
  }
  await page.locator('.wc-hero').screenshot({path:`${output}/hero-${width}.png`});
  await page.locator('.wc-hero h1').screenshot({path:`${output}/heading-${width}.png`});
  if(phase==='before'){
   await page.addStyleTag({content:'.competition-site .wc-hero h1 {background:none;color:white;filter:none;}'});
   await page.locator('.wc-hero').screenshot({path:`${output}/solid-color-${width}.png`});
  }
  evidence.push({width,...data});await page.close();
 }
 await writeFile(`${output}/evidence.json`,JSON.stringify(evidence,null,2));
 console.log(JSON.stringify(evidence.map(({width,font,lineHeight,inkAboveBox,heading,cta})=>({width,font,lineHeight,inkAboveBox,heading,cta})),null,2));
}finally{await browser.close();}
