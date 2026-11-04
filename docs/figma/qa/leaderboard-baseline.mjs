import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
const output='docs/figma/qa/screenshots/competition-leaderboard/before';
await mkdir(output,{recursive:true});
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const evidence=[];
try {
 for(const width of [390,1440]) {
  const page=await browser.newPage({viewport:{width,height:900}});
  for(const [name,path] of [['leaderboard','/leaderboard'],['home','/']]) {
   const response=await page.goto('http://127.0.0.1:8771'+path,{waitUntil:'networkidle'});
   await page.evaluate(()=>document.fonts.ready);
   await page.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
   evidence.push({name,width,status:response.status(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)});
  }
  await page.close();
 }
 await writeFile(`${output}/evidence.json`,JSON.stringify(evidence,null,2));
 console.log(evidence);
}finally {await browser.close();}
