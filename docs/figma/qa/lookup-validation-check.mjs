import assert from 'node:assert/strict';
import { collectLookupErrors, initLookupValidation } from '../../../resources/js/dealer-registration.js';

globalThis.RadioNodeList = class extends Array {};
let focused;
const events = {};
const pageEvents = {};
globalThis.window = { addEventListener(type, callback) { pageEvents[type] = callback; } };
let submits = 0;
globalThis.HTMLFormElement = class { submit() { submits++; } };
const input = (name) => ({ name, value: '', disabled: false, type: 'text', attrs: {},
    setAttribute(key, value) { this.attrs[key] = value; }, focus() { focused = name; } });
const phone = input('phone'); const plate = input('license_plate');
const elements = [phone, plate];
elements.namedItem = (name) => elements.find((node) => node.name === name);
const outputs = elements.map((node) => ({ dataset: { errorFor: node.name }, textContent: '', hidden: true }));
const button = { disabled: false }; const loading = { hidden: true };
const form = {
    elements, dataset: {}, attrs: {},
    matches() { return false; },
    querySelector(selector) { return selector === '[data-lookup-submit]' ? button : loading; },
    querySelectorAll(selector) { return selector === '[data-error-for]' ? outputs : elements; },
    setAttribute(name, value) { this.attrs[name] = value; }, removeAttribute(name) { delete this.attrs[name]; },
    addEventListener(type, callback) { events[type] = callback; },
};
initLookupValidation(form);
assert.equal(outputs.every((node) => node.hidden), true, 'Untouched fields remain quiet');
events.submit({ preventDefault() {} });
assert.equal(focused, 'phone');
assert.deepEqual(collectLookupErrors(form), { phone: 'Vui lòng nhập số điện thoại.', license_plate: 'Vui lòng nhập biển số xe.' });
assert.equal(button.disabled, false);
phone.value = 'invalid'; plate.value = '51k - 900.91';
events.input({ target: phone });
assert.equal(outputs[0].textContent, 'Số điện thoại không hợp lệ.');
assert.equal(plate.value, '51k - 900.91', 'Other editable values remain intact');
phone.value = '0084 (990) 000-091';
events.input({ target: phone });
assert.equal(outputs[0].hidden, true);
assert.equal(phone.attrs['aria-invalid'], 'false');
assert.deepEqual(collectLookupErrors(form), {});
plate.value = '<invalid>';
assert.equal(collectLookupErrors(form).license_plate, 'Biển số xe không hợp lệ.');
plate.value = '51K90091';
events.submit({ preventDefault() {} }); events.submit({ preventDefault() {} });
assert.equal(submits, 1, 'Double click has one native POST');
assert.equal(button.disabled, true); assert.equal(loading.hidden, false);
assert.equal(form.attrs['aria-busy'], 'true');
pageEvents.pageshow();
assert.equal(button.disabled, false); assert.equal(loading.hidden, true);
assert.equal(form.dataset.lookupSubmitting, undefined);
console.log('PASS lookup inline validation, focus/aria, normalization, retention, loading and duplicate-submit guard');
