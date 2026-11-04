import assert from 'node:assert/strict';
import { collectSupplementErrors, initSupplementValidation } from '../../../resources/js/dealer-registration.js';

globalThis.RadioNodeList = class extends Array {};
globalThis.FormData = class { constructor(form) { this.form = form; } get(name) { return this.form.elements.namedItem(name)?.value; } };
let focus;
let submits = 0;
const events = {}; const inputEvents = {}; const pageEvents = {};
globalThis.window = { addEventListener(type, callback) { pageEvents[type] = callback; } };
globalThis.HTMLFormElement = class { submit() { submits++; } };
const input = { name:'facebook_profile_url', value:'', type:'url', disabled:false, attrs:{},
    setAttribute(name, value) { this.attrs[name] = value; }, getAttribute(name) { return this.attrs[name]; },
    addEventListener(type, callback) { inputEvents[type] = callback; }, focus() { focus = this.name; } };
const elements = [input]; elements.namedItem = (name) => elements.find((node) => node.name === name);
const output = { dataset:{ errorFor:'facebook_profile_url' }, textContent:'', hidden:true };
const button = { disabled:false }; const loading = { hidden:true };
const form = { elements, dataset:{}, attrs:{},
    querySelector(selector) { return selector === '[type=submit]' ? button : loading; },
    querySelectorAll(selector) { return selector === '[data-error-for]' ? [output] : elements; },
    addEventListener(type, callback) { events[type] = callback; },
    setAttribute(name, value) { this.attrs[name] = value; }, removeAttribute(name) { delete this.attrs[name]; } };
initSupplementValidation(form);
assert.equal(output.hidden, true);
inputEvents.focusout({ relatedTarget: button });
assert.equal(output.hidden, true, 'Submit-target blur must leave button geometry stable until submit');
inputEvents.focusout({ relatedTarget: null });
assert.equal(output.hidden, false, 'Normal blur continues showing the field error');
events.submit({ preventDefault() {} });
assert.equal(output.textContent, 'Vui lòng nhập liên kết Facebook cá nhân.');
assert.equal(focus, 'facebook_profile_url');
assert.equal(input.attrs['aria-invalid'], 'true');
for (const value of ['javascript:alert(1)', 'facebook.com/synthetic.customer', 'https://facebook.com/groups/synthetic', 'https://example.invalid/synthetic']) {
    input.value = value;
    assert.equal(collectSupplementErrors(form).facebook_profile_url, 'Liên kết Facebook không hợp lệ.');
}
input.value = 'https://facebook.com/synthetic.customer'; inputEvents.input();
assert.equal(output.hidden, true); assert.equal(input.attrs['aria-invalid'], 'false');
events.submit({ preventDefault() {} }); events.submit({ preventDefault() {} });
assert.equal(submits, 1); assert.equal(button.disabled, true); assert.equal(loading.hidden, false);
assert.equal(form.attrs['aria-busy'], 'true');
assert.equal(input.value, 'https://facebook.com/synthetic.customer');
pageEvents.pageshow(); assert.equal(button.disabled, false); assert.equal(loading.hidden, true);
assert.equal(form.dataset.submitting, undefined);
console.log('PASS supplementation specific inline errors, correction, focus/aria, retention, loading and one native POST; no client D08 transition');
