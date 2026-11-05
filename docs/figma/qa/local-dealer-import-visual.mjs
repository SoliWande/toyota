import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { qaOutput } from './artifact-paths.mjs';

const origin = 'http://127.0.0.1:8771';
const output = qaOutput('local-dealer-import');
const evidence = [];
const post = JSON.parse(await readFile('.laravel-tools/backups/toyota-local/20261105-072148-3a57bf8d8b33/postcheck.json','utf8'));
const snapshot = JSON.parse(await readFile('database/data/toyota_dealers.json','utf8'));
const sourceById = new Map(post.created.map(record => [record.id, snapshot.dealers.find(row => row.toyota_source_id === record.toyota_source_id)]));
const browser = await chromium.launch({ executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true });
try {
  const page = await browser.newPage({viewport:{width:1440,height:1000}});
  const getRows = async () => page.locator('.wc-d-row').evaluateAll(rows => rows.map(row => ({
    id:Number(new URL(row.href).pathname.split('/')[2]), name:row.querySelector('.wc-d-name strong').textContent.trim(),
    province:row.querySelector('.wc-d-province-copy').textContent.trim(), members:row.querySelector('.wc-d-score strong').textContent.trim(),
    rank:row.querySelector('.wc-d-rank').getAttribute('aria-label'),
  })));
  const allRows=[];
  await page.goto(`${origin}/dealers`,{waitUntil:'networkidle'});
  assert.match(await page.locator('.wc-d-intro p').innerText(),/88 đại lý/);
  const options=await page.locator('[data-dealer-province] option').evaluateAll(items=>items.map(o=>o.value));
  assert.deepEqual(new Set(options.filter(Boolean)),new Set(post.province_labels));
  for (let n=1;n<=5;n++) {
    const rows=await getRows();
    assert.equal(rows.length,n===5?8:20);
    allRows.push(...rows);
    assert.match(await page.locator('.wc-d-pagination p').innerText(),new RegExp(`Trang ${n} / 5`));
    if(n<5) await page.goto(await page.locator('.wc-d-pagination a[rel=next]').getAttribute('href'),{waitUntil:'networkidle'});
  }
  assert.equal(allRows.length,88); assert.equal(new Set(allRows.map(r=>r.id)).size,88);
  assert.deepEqual(new Set(allRows.map(r=>r.id)),new Set([1,...post.new_dealer_ids]));
  for(const row of allRows.filter(r=>sourceById.has(r.id))) {
    assert.equal(row.name,sourceById.get(row.id).name); assert.equal(row.province,sourceById.get(row.id).province);
    assert.equal(row.members,'0'); assert.match(row.rank,/chưa xếp hạng/);
  }
  const ranks=new Map(allRows.map(r=>[r.id,r.rank]));
  for(const sort of ['members','name','rank']) for(const direction of ['asc','desc']) {
    await page.goto(`${origin}/dealers?sort=${sort}&direction=${direction}`,{waitUntil:'networkidle'});
    assert.equal(await page.locator('[data-dealer-sort]').inputValue(),sort);
    assert.equal(await page.locator('[name=direction]').inputValue(),direction);
    for(const row of await getRows()) assert.equal(row.rank,ranks.get(row.id));
    const next=new URL(await page.locator('a[rel=next]').getAttribute('href'));
    assert.equal(next.searchParams.get('sort'),sort); assert.equal(next.searchParams.get('direction'),direction);
  }
  await page.goto(`${origin}/dealers`,{waitUntil:'networkidle'});
  await page.locator('[data-dealer-province]').selectOption('Hồ Chí Minh');
  await page.waitForURL(u=>u.searchParams.get('province')==='Hồ Chí Minh');
  assert.equal((await getRows()).length,16);
  await page.locator('#wc-d-search').fill('Toyota');
  await page.locator('.wc-d-search button').click();
  await page.waitForURL(u=>u.searchParams.get('q')==='Toyota' && u.searchParams.get('province')==='Hồ Chí Minh');
  assert.equal((await getRows()).length,16);
  await page.locator('[data-dealer-sort]').selectOption('name');
  await page.waitForURL(u=>u.searchParams.get('sort')==='name');
  const oldDirection=await page.locator('[name=direction]').inputValue();
  await page.locator('[data-dealer-direction]').click();
  await page.waitForURL(u=>u.searchParams.get('direction')!==oldDirection);
  assert.equal(await page.locator('#wc-d-search').inputValue(),'Toyota');
  assert.equal(await page.locator('[data-dealer-province]').inputValue(),'Hồ Chí Minh');
  await page.goto(`${origin}/dealers?q=Toyota&sort=name&direction=asc`,{waitUntil:'networkidle'});
  await page.locator('a[rel=next]').click();
  assert.equal(new URL(page.url()).searchParams.get('q'),'Toyota');
  assert.equal(new URL(page.url()).searchParams.get('sort'),'name');
  assert.equal(new URL(page.url()).searchParams.get('direction'),'asc');
  evidence.push({source:'Live Laravel GET local toyota, no fixtures/interception',allActiveIds:allRows.map(r=>r.id),pagination:5,provinceOptions:options,sortDirections:6,nativeInteractions:'PASS'});

  const longRecord=[...sourceById.entries()].sort((a,b)=>b[1].name.length-a[1].name.length)[0];
  const profilePath=`/dealers/${longRecord[0]}/profile`;
  for (const record of post.created) {
    const response=await page.goto(`${origin}/dealers/${record.id}/profile`,{waitUntil:'domcontentloaded'});
    assert.equal(response.status(),200);
    const row=sourceById.get(record.id);
    assert.equal(await page.locator('h1').innerText(),row.name);
    assert.match(await page.locator('.wc-d-profile-heading p').first().innerText(),new RegExp(row.code));
    assert.equal(await page.locator('.wc-d-contact-row p').first().innerText(),row.address);
    assert.equal(await page.locator('.wc-d-call').count(),0);
    assert.equal(await page.locator('.wc-d-contact a[href^="tel:"]').count(),0);
    assert.equal(await page.locator('.wc-d-photo').count(),0);
    assert.equal(await page.locator('.wc-d-stat strong').first().innerText(),'0');
    assert.equal(await page.locator('.wc-d-stat strong').nth(1).innerText(),'—');
  }
  evidence.push({profiles:87,rawSnapshotNamesAddresses:true,unapprovedPhonesHidden:true,zeroScoreAndNoRank:true,photoFallback:true});
  await page.goto(`${origin}${profilePath}`,{waitUntil:'networkidle'});
  const landingHref=await page.locator('.wc-d-join').first().getAttribute('href');
  const landingPath=new URL(landingHref).pathname;
  assert.equal((await page.goto(landingHref,{waitUntil:'networkidle'})).status(),200);
  assert.equal((await page.goto(`${origin}/dealers/${longRecord[0]}/qr`)).status(),200);
  assert.equal((await page.goto(`${origin}/dealers/99999999/profile`)).status(),404);
  evidence.push({landingPath,qr:200,missingProfile:404});
  await page.close();

  for(const [name,path] of [['directory','/dealers'],['directory-last','/dealers?page=5'],['legacy-province','/dealers?province=Bình%20Dương'],['empty','/dealers?q=no-such-dealer-import-qa'],['profile-long',profilePath],['landing',landingPath]]) {
    for(const width of [320,390,768,1024,1440,1920]) {
      const p=await browser.newPage({viewport:{width,height:844}});
      const errors=[],assetErrors=[];
      p.on('pageerror',e=>errors.push(e.message));
      p.on('response',r=>{if(r.status()>=400) assetErrors.push({url:r.url(),status:r.status()});});
      const r=await p.goto(`${origin}${path}`,{waitUntil:'networkidle'}); assert.equal(r.status(),200);
      await p.evaluate(()=>document.fonts.ready);
      // Dealer typography uses Inter; explicitly probe the shared Montserrat faces
      // rather than treating an unused, lazily unloaded font as a load failure.
      const fontProbe=await p.evaluate(async()=>{
        const faces=await Promise.all([600,700].map(w=>document.fonts.load(`${w} 20px Montserrat`,'ĐẠI LÝ THÀNH PHỐ')));
        return {requestedFaces:faces.map(f=>f.length),headingFamily:getComputedStyle(document.querySelector('h1')).fontFamily};
      });
      const geometry=await p.evaluate(()=>({
        overflow:document.documentElement.scrollWidth>innerWidth,
        brokenImages:[...document.images].filter(i=>i.getBoundingClientRect().width>0&&(!i.complete||!i.naturalWidth)).map(i=>i.src),
        clipping:[...document.querySelectorAll('.wc-d-name strong,.wc-d-profile-heading h1,.wc-d-contact-row p')].filter(e=>e.clientWidth>0&&e.scrollWidth>e.clientWidth+1).map(e=>e.textContent),
        fonts:[600,700].map(w=>({weight:w,loaded:document.fonts.check(`${w} 20px Montserrat`,'ĐẠI LÝ THÀNH PHỐ')})),
        footerAfterMain:document.querySelector('footer').getBoundingClientRect().top>=document.querySelector('main').getBoundingClientRect().bottom-1,
      }));
      assert.equal(geometry.overflow,false,`${name}/${width} overflow`);
      assert.deepEqual(geometry.brokenImages,[]); assert.deepEqual(geometry.clipping,[]);
      assert.deepEqual(errors,[]); assert.deepEqual(assetErrors,[]); assert(geometry.footerAfterMain);
      assert(geometry.fonts.every(f=>f.loaded),`${name}/${width} Vietnamese font probe`);
      await p.screenshot({path:`${output}/${name}-${width}.png`,fullPage:true});
      evidence.push({name,width,status:200,...geometry,fontProbe,errors,assetErrors,source:'actual imported local toyota'});
      await p.close();
    }
  }
  await writeFile(`${output}/evidence.json`,JSON.stringify(evidence,null,2));
  console.log('PASS live local: 88 active IDs/5 pages, 6 sorts, native filters, 87 profiles, D01/QR/404 and 36 responsive screenshots.');
} finally {await browser.close();}
