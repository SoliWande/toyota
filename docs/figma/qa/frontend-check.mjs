import assert from 'node:assert/strict';
import { normalizePhone, isPersonalFacebook, collectErrors, renderValidation } from '../../../resources/js/dealer-registration.js';

for (const phone of ['0901.234.567', '+84 901 234 567', '0084 (901) 234-567']) assert.equal(normalizePhone(phone), '0901234567');
for (const url of ['facebook.com/example.customer', 'https://m.facebook.com/example.customer', 'https://www.facebook.com/profile.php?id=12345']) assert.equal(isPersonalFacebook(url), true, url);
for (const url of ['https://facebook.com.evil.test/customer', 'https://facebook.com/groups/group', 'https://facebook.com/login.php', 'https://evil.test@facebook.com/customer', 'https://facebook.com/profile.php?id=1&id=2', 'https://facebook.com/profile.php?id=0', 'javascript:alert(1)', 'https://facebook.com/people/customer/123']) assert.equal(isPersonalFacebook(url), false, url);
const field = (name, value, extra = {}) => ({ name, value, required:true, type:'text', disabled:false, validity:{ valid:true }, ...extra });
const elements = [field('address_line', ''), field('ward_code', '', { disabled:true }), field('facebook_url', 'not-a-url'), field('consent', '1', { type:'checkbox', checked:false })];
elements.namedItem = (name) => elements.find((item) => item.name === name);
const errors = collectErrors({ elements });
assert.deepEqual(Object.keys(errors), ['address_line', 'ward_code', 'facebook_url', 'consent']);
assert.equal(errors.facebook_url, 'Liên kết Facebook không hợp lệ.');
assert.match(errors.consent, /đồng ý/);
assert.equal(Object.hasOwn(errors, 'license_plate'), false, 'No fabricated duplicate plate result');
// Step One has specific inline messages and no summary; Step Two above retains its own validation.
globalThis.RadioNodeList = class extends Array {};
let focused;
const control = (name, value, extra = {}) => ({ ...field(name, value, extra),
    attrs: {}, setAttribute(key, value) { this.attrs[key] = value; }, focus() { focused = this.name; } });
const name = control('customer_name', '');
const phone = control('phone', '');
const car = control('vehicle_description', 'FJ', { type: 'radio', checked: false });
const firstElements = [name, phone, car];
const radios = new RadioNodeList(car);
firstElements.namedItem = (key) => key === 'vehicle_description' ? radios : firstElements.find((item) => item.name === key);
const outputs = firstElements.map((item) => ({ dataset: { errorFor: item.name }, textContent: '', hidden: true }));
const firstForm = {
    elements: firstElements,
    matches: (selector) => selector === '[data-step-one]',
    querySelector: (selector) => selector.includes(':checked') && car.checked ? car : null,
    querySelectorAll: (selector) => selector === '[data-error-for]' ? outputs : firstElements,
};
assert.deepEqual(collectErrors(firstForm), { customer_name: 'Vui lòng nhập họ tên.', phone: 'Vui lòng nhập số điện thoại.', vehicle_description: 'Vui lòng chọn dòng xe.' });
renderValidation(firstForm, collectErrors(firstForm), true);
assert.equal(focused, 'customer_name');
assert.equal(name.attrs['aria-invalid'], 'true');
assert.equal(car.attrs['aria-invalid'], 'true');
name.value = 'Synthetic Customer'; phone.value = 'invalid'; car.checked = true;
assert.deepEqual(collectErrors(firstForm), { phone: 'Số điện thoại không hợp lệ.' });
renderValidation(firstForm, collectErrors(firstForm), true);
assert.equal(focused, 'phone');
assert.equal(phone.value, 'invalid');
assert.equal(name.value, 'Synthetic Customer');
assert.equal(name.attrs['aria-invalid'], 'false');
assert.equal(outputs[0].hidden, true);
phone.value = '+84 901 234 567';
assert.deepEqual(collectErrors(firstForm), {});
console.log('PASS Step One inline messages, first-error focus, retained values and aria state');
