import assert from 'node:assert/strict';
import fs from 'node:fs';
import postcss from 'postcss';

// Validate the shipped cascade, including media queries and the later shared button rule.
// This does not measure browser geometry, font glyphs or accessibility rendering.
const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json', 'utf8'));
const css = postcss.parse(fs.readFileSync(`public/build/${manifest['resources/css/dealer-registration.css'].file}`, 'utf8'));
function active(rule, width) {
    for (let parent = rule.parent; parent; parent = parent.parent) {
        if (parent.type !== 'atrule' || parent.name !== 'media') continue;
        const constraints = [...parent.params.matchAll(/(min|max)-width\s*:\s*(\d+)px/g)];
        if (!constraints.length) return false;
        if (constraints.some(([, kind, limit]) => kind === 'min' ? width < Number(limit) : width > Number(limit))) return false;
    }
    return true;
}
function cascade(selectors, width) {
    const result = {}; const weights = {};
    css.walkRules((rule) => {
        if (!active(rule, width)) return;
        for (const selector of rule.selector.split(',').map((part) => part.trim())) {
            if (!selectors.includes(selector)) continue;
            const withoutAttributes = selector.replace(/\[[^\]]*\]/g, '');
            const weight = (withoutAttributes.match(/#[\w-]+/g) || []).length * 100
                + (withoutAttributes.match(/\./g) || []).length * 10
                + (selector.match(/\[[^\]]*\]/g) || []).length * 10
                + (selector.match(/(?:\s|>)\w+/g) || []).length;
            rule.walkDecls((declaration) => {
                const priority = weight + (declaration.important ? 1000 : 0);
                if (priority >= (weights[declaration.prop] ?? -1)) {
                    result[declaration.prop] = declaration.value; weights[declaration.prop] = priority;
                }
            });
        }
    });
    return result;
}
for (const width of [390, 768, 1024, 1440]) {
    const number = cascade(['.dr-site .dr-rules-number'], width);
    const tocNumber = cascade(['.dr-site .dr-rules-toc ol span'], width);
    const cta = cascade(['.dr-site .dr-button', '.dr-site .dr-rules-toc .dr-rules-join'], width);
    const split = cascade(['.dr-site .dr-rules-split'], width);
    const nav = cascade(['.dr-site .dr-rules-toc'], width);
    for (const styles of [number, tocNumber]) {
        assert.equal(styles['white-space'], 'nowrap');
        assert.equal(styles.flex, 'none');
        assert.equal(styles['overflow-wrap'], 'normal');
        assert.equal(styles['word-break'], 'normal');
        assert.equal(styles['min-width'], '2ch');
    }
    assert.equal(cta.display, 'flex', 'Shared button cascade must not hide or duplicate the single CTA');
    assert.equal(split.display, width < 768 ? 'block' : 'grid');
    if (width < 768) {
        assert.equal(nav.padding, '0'); assert.equal(cta.width, '100%');
        assert.equal(cascade(['.dr-site .dr-rules-toc nav'], width).display, 'none');
        assert.equal(cta['min-height'], '52px'); assert.equal(cta.margin, '0');
    } else {
        assert.equal(nav['grid-area'], 'toc');
        assert.notEqual(cascade(['.dr-site .dr-rules-toc nav'], width).display, 'none');
        assert.equal(cascade(['.dr-site .dr-rules-document'], width)['grid-area'], 'document');
    }
    console.log(`PASS shipped rules CSS cascade at ${width}px: atomic numbering, single CTA placement; browser geometry NOT VERIFIED`);
}
const baseLink = '.dr-site .dr-rules-toc ol a';
const hover = cascade([baseLink, `${baseLink}:hover`], 1440);
assert.equal(hover.background, undefined, 'Unselected hover must not acquire the selected pink fill');
assert.equal(hover.color, 'var(--dr-red)');
assert.equal(hover.transition, undefined);
const document = fs.readFileSync('resources/content/campaign-rules.php', 'utf8');
const ids = [...document.matchAll(/'id' => '([^']+)'/g)].map((match) => match[1]);
assert.equal(ids.length, 8);
for (const id of ids) {
    const selector = `.dr-site .dr-rules-split:has(#rules-${id}:target) a[href="#rules-${id}"]`;
    const selected = cascade([baseLink, selector], 1440);
    assert.equal(selected.background, '#fff1f2'); assert.equal(selected['font-weight'], '600');
    assert.equal(selected.color, '#111');
    assert.equal(selected['--dr-rule-number-color'], 'var(--dr-red)');
}
console.log('PASS separate minimal hover and Figma selected styling for all eight anchor targets; no menu animation');
