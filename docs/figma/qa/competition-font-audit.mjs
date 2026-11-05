import { qaInput, qaOutput, qaReferenceLink } from './artifact-paths.mjs';
import { chromium } from '../../../.laravel-tools/visual-qa/node_modules/playwright-core/index.mjs';
import { mkdir, writeFile } from 'node:fs/promises';

const output = process.env.COMPETITION_QA_OUTPUT || qaOutput('website-competition/final-qa');
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true });
try {
    const page = await browser.newPage({ viewport:{width:1440,height:1000} });
    await page.goto('http://127.0.0.1:8771/', {waitUntil:'networkidle'});
    await page.evaluate(() => document.fonts.ready);
    const unrestricted = process.argv.includes('--probe-unrestricted');
    if (unrestricted) await page.evaluate(async () => {
        const font = new FontFace('MontserratUnrestricted', 'url(/dealer-registration/montserrat-latin.woff2)', {weight:'700'});
        document.fonts.add(await font.load());
    });
    const glyphs = await page.evaluate(unrestricted => {
        const elements = ['.wc-hero h1','.wc-hero-label','.wc-award h2','.wc-join h2'];
        const rows=[];
        for (const selector of elements) {
            const source=document.querySelector(selector), style=getComputedStyle(source);
            const text=style.textTransform==='uppercase'?source.innerText.toUpperCase():source.innerText;
            for (const glyph of [...new Set([...text].filter(c=>c.trim()))]) {
                const span=document.createElement('span');
                span.id=`font-probe-${rows.length}`;span.textContent=glyph;
                Object.assign(span.style,{position:'fixed',top:'0',left:'-2000px',fontFamily:unrestricted?'MontserratUnrestricted, Inter':style.fontFamily,fontWeight:style.fontWeight,fontSize:style.fontSize});
                document.body.appendChild(span);
                rows.push({selector,glyph,codepoint:`U+${glyph.codePointAt(0).toString(16).toUpperCase()}`,id:span.id,requested:style.fontFamily,weight:style.fontWeight});
            }
        }
        return rows;
    }, unrestricted);
    const cdp=await page.context().newCDPSession(page);
    await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
    const {root}=await cdp.send('DOM.getDocument');
    for (const row of glyphs) {
        const {nodeId}=await cdp.send('DOM.querySelector',{nodeId:root.nodeId,selector:`#${row.id}`});
        row.fonts=(await cdp.send('CSS.getPlatformFontsForNode',{nodeId})).fonts;
    }
    const fallback=glyphs.filter(row=>row.fonts.some(font=>!font.familyName.includes('Montserrat')));
    await writeFile(`${output}/font-audit${unrestricted?'-probe':''}.json`,JSON.stringify({method:'Chrome CDP CSS.getPlatformFontsForNode, individual rendered glyphs with actual computed family/weight',glyphs,fallback},null,2));
    console.log(JSON.stringify({glyphs:glyphs.length,fallback:fallback.map(row=>({selector:row.selector,glyph:row.glyph,codepoint:row.codepoint,fonts:row.fonts.map(font=>font.familyName)}))},null,2));
} finally { await browser.close(); }
