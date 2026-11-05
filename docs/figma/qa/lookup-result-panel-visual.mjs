import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const phase = process.argv[2] || 'after';
assert.ok(['before', 'after'].includes(phase));
const root = qaOutput('result-panel-sidebox');
const output = `${root}/${phase}`;
await mkdir(output, { recursive: true });
const cases = [
    { name:'D07', path:'/D07', badge:'Cần bổ sung', cta:'Gửi bổ sung', tone:'red', steps:['completed','completed','warning'] },
    { name:'D08', path:'/D08', steps:[] },
    { name:'D09', path:'/D09', steps:[] },
    { name:'D10', path:'/D10', cta:'Đăng ký tham gia', tone:'red', steps:[] },
    { name:'D11', path:'/D11', badge:'Chưa hoàn tất', title:'Còn một bước nữa', cta:'Tiếp tục đăng ký', tone:'red', steps:['current','pending','pending'] },
    { name:'D12', path:'/D12', badge:'Chờ vào group', title:'Bước tiếp theo: vào group', cta:'Vào group Facebook', tone:'red', steps:['completed','current','pending'] },
    { name:'D13', path:'/D13', badge:'Chờ duyệt', steps:['completed','completed','current'] },
    { name:'D14', path:'/D14', badge:'Thành viên', title:'Chào mừng bạn vào nhóm', cta:'Mở group Facebook', tone:'dark', steps:['completed','completed','completed'] },
    { name:'D15', path:'/D15', badge:'Chưa xác nhận', title:'Chưa thể xác nhận đăng ký của bạn', cta:'Gọi đại lý 0900000000', tone:'outline', steps:['completed','completed','blocked'] },
    { name:'FOUND', path:'/FOUND', badge:'Chưa xác nhận', steps:['completed','pending','pending'] },
    { name:'D07-unknown', path:'/D07?group=unknown', badge:'Cần bổ sung', cta:'Gửi bổ sung', tone:'red', steps:['completed','pending','warning'] },
    { name:'D07-contact', path:'/D07?group=unknown&ownership=none', badge:'Cần bổ sung', cta:'Gọi đại lý 0900000000', tone:'outline', steps:['completed','pending','warning'] },
    { name:'D15-unknown', path:'/D15?group=unknown', badge:'Chưa xác nhận', cta:'Gọi đại lý 0900000000', tone:'outline', steps:['completed','pending','blocked'] },
    { name:'D15-long', path:'/D15?group=unknown&text=long', badge:'Chưa xác nhận', cta:'Gọi đại lý 0900000000', tone:'outline', steps:['completed','pending','blocked'] },
    { name:'D15-no-phone', path:'/D15?phone=none', badge:'Chưa xác nhận', steps:['completed','completed','blocked'] },
    { name:'D12-no-group', path:'/D12?group_url=none', badge:'Chờ vào group', cta:'Vào group Facebook', disabled:true, steps:['completed','current','pending'] },
    { name:'D14-no-group', path:'/D14?group_url=none', badge:'Thành viên', cta:'Mở group Facebook', disabled:true, steps:['completed','completed','completed'] },
    { name:'D10-inactive', path:'/D10?dealer=inactive', steps:[] },
    { name:'D12-long-name', path:'/D12?name=long', badge:'Chờ vào group', cta:'Vào group Facebook', tone:'red', steps:['completed','current','pending'] },
];
const widths = phase === 'before' ? [390, 1440] : [320, 390, 768, 1024, 1440];
const selected = phase === 'before' ? cases.slice(0, 10) : cases;
const evidence = [];
const browser = await chromium.launch({ executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true, args:['--hide-scrollbars'] });
const near = (value, expected, label) => assert.ok(Math.abs(value - expected) <= .6, `${label}: ${value} != ${expected}`);
try {
    for (const item of selected) for (const width of widths) {
        const page = await browser.newPage({ viewport:{ width, height:1024 }, deviceScaleFactor:1 });
        await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ status:200, contentType:'text/css', body:'' }));
        const errors = [], failures = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('requestfailed', request => failures.push(request.url()));
        page.on('response', response => { if(response.status() >= 400) failures.push(`${response.status()} ${response.url()}`); });
        await page.goto(`http://127.0.0.1:8766${item.path}`, { waitUntil:'load' });
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(() => { document.activeElement?.blur(); scrollTo(0,0); });
        const result = await page.evaluate(() => {
            const box = element => { if(!element) return null; const r=element.getBoundingClientRect(), c=getComputedStyle(element); return {x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom,display:c.display,bg:c.backgroundColor,color:c.color,font:c.font,padding:c.padding,gap:c.gap,radius:c.borderRadius,shadow:c.boxShadow,border:c.borderLeftColor}; };
            const card = document.querySelector('.dr-lookup-result,.dr-d12,.dr-lookup-empty,.dr-supplement-success');
            const person = document.querySelector('[data-lookup-person],.dr-lookup-person,.dr-d12-person');
            const progress = document.querySelector('[data-lookup-progress]');
            const side = document.querySelector('[data-lookup-sidebox],.dr-lookup-next,.dr-d12-action');
            const badge = document.querySelector('[data-lookup-badge],.dr-status-chip,.dr-d12-status');
            const ctas = [...document.querySelectorAll('.dr-lookup-results .dr-button,.dr-supplement-actions .dr-button')].map(e=>({text:e.textContent.replace(e.querySelector('.sr-only')?.textContent || '', '').trim(),href:e.getAttribute('href'),disabled:e.getAttribute('aria-disabled'),...box(e)}));
            return { overflow:document.documentElement.scrollWidth>innerWidth, fonts:document.fonts.check('600 16px Inter'), images:[...document.images].every(i=>i.complete&&i.naturalWidth>0),
                card:box(card),person:box(person),badge:badge?{text:badge.textContent.trim(),...box(badge)}:null,
                personInfo:box(person?.querySelector('.dr-lookup-person-info,div')), avatar:box(person?.querySelector('.dr-lookup-avatar,.dr-d12-avatar')),
                progress:box(progress), side:box(side), message:box(document.querySelector('.dr-lookup-blocked-message')), ctas, body:document.body.innerText,
                steps:[...document.querySelectorAll('[data-lookup-progress]>li')].map(e=>({state:e.dataset.state,node:box(e.querySelector('.dr-lookup-progress-node')),text:box(e.querySelector('.dr-lookup-progress-text'))})) };
        });
        if (phase === 'after') {
            const label = `${item.name}/${width}`;
            assert.equal(result.overflow,false,`${label}: overflow`);
            assert.ok(result.fonts && result.images,`${label}: fonts/images`);
            assert.deepEqual(errors,[],`${label}: JS`); assert.deepEqual(failures,[],`${label}: resources`);
            if(item.name !== 'D09') {
                assert.ok(!result.body.includes('Đã tìm thấy đăng ký') && !result.body.includes('Thông tin tra cứu khớp với một đăng ký.'),`${label}: removed generic result copy`);
                assert.equal(await page.locator('.dr-lookup-next h3').filter({hasText:'Cộng đồng Toyota Veloz & Hilux'}).count(),0);
                assert.ok(!result.body.includes('48 giờ'),`${label}: no invented SLA`);
            }
            assert.deepEqual(result.steps.map(s=>s.state),item.steps,`${label}: backend presentation`);
            if (item.badge) {
                assert.equal(result.badge?.text,item.badge,`${label}: badge`);
                assert.ok(result.badge.x>=result.personInfo.right-1 || result.badge.y>=result.personInfo.bottom-1,`${label}: badge must not overlap identity`);
                if (width===390 && !item.name.includes('long-name')) near(result.badge.y+result.badge.height/2,result.avatar.y+result.avatar.height/2,`${label}: badge stays on header row`);
            } else assert.equal(result.badge,null,`${label}: no registration badge`);
            const mobileBlock = item.name.startsWith('D15') && width < 768;
            if(mobileBlock) near(result.message.y-result.person.bottom,20,`${label}: message follows divider`);
            if(item.steps.length) {
                if(mobileBlock) assert.equal(result.progress.display,'none',`${label}: M15 uses message instead of progress`);
                else {
                    near(result.progress.y - result.person.bottom,width<768?20:28,`${label}: person/progress gap`);
                    for(const step of result.steps) near(step.node.width,width<768?24:28,`${label}: marker`);
                }
            } else assert.equal(result.progress,null,`${label}: no progress`);
            if(['D13','FOUND','D08','D09','D10','D10-inactive'].includes(item.name)) assert.equal(result.side,null,`${label}: no sidebox`);
            if(item.title && width>=768) assert.ok(await page.locator('[data-lookup-sidebox]').innerText().then(t=>t.includes(item.title)),`${label}: sidebox title`);
            if(item.cta) {
                assert.equal(result.ctas.length,1,`${label}: exactly one CTA`);
                assert.equal(result.ctas[0].text,item.cta,`${label}: CTA copy`);
                if(width<768) near(result.ctas[0].height,52,`${label}: mobile CTA height`);
                if(item.disabled) { assert.equal(result.ctas[0].disabled,'true'); assert.equal(result.ctas[0].href,null); }
                else {
                    const expected = {red:'rgb(235, 10, 30)',dark:'rgb(47, 46, 44)',outline:'rgb(255, 255, 255)'}[item.tone];
                    assert.equal(result.ctas[0].bg,expected,`${label}: CTA background`);
                    const cta = page.locator('.dr-lookup-results .dr-button');
                    await cta.hover(); await page.waitForTimeout(180);
                    const hover = await cta.evaluate(e=>getComputedStyle(e).backgroundColor);
                    assert.equal(hover,item.tone==='red'?'rgb(201, 8, 27)':expected,`${label}: hover keeps variant`);
                    await page.mouse.move(0,0); await page.waitForTimeout(180);
                }
            } else if(!['D08','D09'].includes(item.name)) assert.equal(result.ctas.length,0,`${label}: no unsolicited CTA`);
            if(width===1440 && /^D(07|11|12|14|15)$/.test(item.name)) {
                near(result.card.width,1200,`${label}: card width`); near(result.side.width,420,`${label}: side width`);
                near(result.side.height,result.card.height,`${label}: side/card height`);
                assert.equal(result.side.bg,'rgb(250, 250, 251)'); assert.equal(result.side.border,'rgb(237, 237, 240)');
            }
            if(width<768 && item.name==='D10') {
                near(await page.locator('.dr-lookup-empty-icon').evaluate(e=>e.getBoundingClientRect().width),56,'M10 icon');
                near(await page.locator('.dr-lookup-empty-icon').evaluate(e=>{const r=e.getBoundingClientRect();return r.x+r.width/2;}),result.card.x+result.card.width/2,'M10 icon centered');
            }
        }
        await page.evaluate(()=>{document.activeElement?.blur();scrollTo(0,0);});
        await page.screenshot({path:`${output}/${item.name}-${width}.png`,fullPage:true});
        await page.waitForFunction(()=>[...document.images].every(image=>image.complete));
        if(phase==='after') {
            assert.deepEqual(errors,[],`${item.name}/${width}: JS after capture`);
            assert.deepEqual(failures,[],`${item.name}/${width}: resources after capture`);
        }
        // Snapshot active-page evidence; closing a context can abort obsolete responsive sources.
        evidence.push({name:item.name,path:item.path,width,...result,errors:[...errors],failures:[...failures],body:undefined});
        await page.close();
    }
} finally {
    await browser.close();
    await writeFile(`${output}/evidence.json`,JSON.stringify(evidence,null,2)+'\n');
}
if(phase==='after') {
    const gallery = cases.slice(0,10).map(c=>`<section><h2>${c.name}</h2><p>${c.name==='FOUND'?'Không có frame Figma riêng':`<a href="figma/${c.name}.png">Figma desktop</a>${c.name==='D09'?'':` · <a href="figma/M${c.name.slice(1)}.png">Figma mobile</a>`}`} · <a href="before/${c.name}-1440.png">Trước sửa</a></p><div class="row"><a href="after/${c.name}-1440.png"><img src="after/${c.name}-1440.png" loading="lazy" alt="${c.name} desktop"></a><a href="after/${c.name}-390.png"><img src="after/${c.name}-390.png" loading="lazy" alt="${c.name} mobile"></a></div></section>`).join('');
    const linkedGallery = gallery.replace(/(href|src)="((?:figma|before)\/[^"\s]+)"/g, (_, attr, relative) => `${attr}="${qaReferenceLink(root, `result-panel-sidebox/${relative}`)}"`);
    await writeFile(`${root}/index.html`,`<!doctype html><html lang="vi"><meta charset="utf-8"><title>Result Panel + Sidebox QA</title><style>body{font:16px sans-serif;margin:24px;background:#eee}.row{display:flex;gap:20px;align-items:flex-start}.row a:first-child{width:70%}.row a:last-child{width:25%}img{width:100%;display:block}section{margin-bottom:36px}</style><h1>Result Panel + Sidebox — Figma / trước / sau</h1><p>Fixture loopback, database disabled. Sai khác nghiệp vụ/privacy ghi trong tracking.</p>${linkedGallery}</html>`);
}
console.log(`PASS ${phase}: ${evidence.length} screenshots (${selected.length} cases x ${widths.length} widths).`);
