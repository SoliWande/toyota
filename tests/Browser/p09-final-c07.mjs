import {chromium} from '../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import {readFile,mkdir,stat,writeFile} from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

// Replay an actual authorized Laravel fixture response, with all network requests intercepted locally.
const directory='.laravel-tools/p09-final-browser';
const html=await readFile(path.join(directory,'c07-render.html'),'utf8');
const root=path.resolve('public');
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:956}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const url=new URL(route.request().url());
  if(url.pathname==='/admin/awards')return route.fulfill({status:200,contentType:'text/html; charset=utf-8',body:html});
  const file=path.resolve(root,decodeURIComponent(url.pathname).replace(/^\//,''));
  if(!file.startsWith(root+path.sep))return route.abort();
  try{
   if(!(await stat(file)).isFile())return route.abort();
   const types={'.css':'text/css','.js':'application/javascript','.png':'image/png','.svg':'image/svg+xml','.woff2':'font/woff2'};
   return route.fulfill({status:200,contentType:types[path.extname(file)]||'application/octet-stream',body:await readFile(file)});
  }catch{return route.abort();}
 });
 await page.goto('http://toyota.test/admin/awards',{waitUntil:'networkidle'});
 await page.evaluate(()=>document.fonts.ready);
 assert.equal(await page.locator('.ac-call').isDisabled(),false);
 assert.equal(await page.locator('.ac-publish').isDisabled(),true);
 const url=await page.locator('.ac-call').getAttribute('data-call-url');
 assert.equal(new URL(url).searchParams.get('purpose'),'award_top3');
 assert.ok(new URL(url).searchParams.get('batch'));
 assert.ok((await page.locator('.ac-table').innerText()).includes('1/3 đã gọi, 0 đúng'));
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 assert.deepEqual(errors,[]);
 await mkdir(directory,{recursive:true});
 await page.screenshot({path:path.join(directory,'c07-call-audit-1440.png'),fullPage:true});
 await writeFile(path.join(directory,'c07-checks.json'),JSON.stringify({viewport:1440,callEnabled:true,publishDisabled:true,errors},null,2));
}finally{await browser.close();}
