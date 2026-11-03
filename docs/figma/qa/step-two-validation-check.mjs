import assert from 'node:assert/strict';
import { collectErrors, renderValidation, initStepTwoValidation } from '../../../resources/js/dealer-registration.js';

globalThis.RadioNodeList = class extends Array {};
const messages = {
    province_code: 'Vui lòng chọn Tỉnh/Thành phố.', ward_code: 'Vui lòng chọn Phường/Xã.',
    address_line: 'Vui lòng nhập địa chỉ chi tiết.', vehicle_description: 'Vui lòng chọn dòng xe.',
    license_plate: 'Vui lòng nhập biển số xe.', first_registration_year: 'Vui lòng chọn năm đăng ký lần đầu.',
    facebook_url: 'Vui lòng nhập liên kết Facebook cá nhân.', consent: 'Vui lòng đồng ý điều khoản để tiếp tục.',
};
function fixture(valid = false) {
    let focused;
    let scrolled;
    const defaults = { province_code: '01', ward_code: '00001', address_line: 'Synthetic address', vehicle_description: 'FJ', license_plate: '51K-123.45', first_registration_year: '2024', odo: '', facebook_url: 'facebook.com/synthetic.customer', consent: '1' };
    const elements = Object.keys(defaults).map((name) => ({
        name, value: valid ? defaults[name] : '', disabled: false, checked: valid,
        type: name === 'consent' ? 'checkbox' : name === 'vehicle_description' ? 'hidden' : 'text',
        validity: { valid: true }, options: [{ value: '' }, { value: '2024' }], attrs: {},
        setAttribute(key, value) { this.attrs[key] = value; },
        focus() { focused = name; }, scrollIntoView() { scrolled = name; },
    }));
    elements.namedItem = (name) => elements.find((control) => control.name === name);
    const outputs = elements.map((control) => ({ dataset: { errorFor: control.name }, textContent: '', hidden: true }));
    const carLink = { attrs: {}, setAttribute(key, value) { this.attrs[key] = value; }, focus() { focused = 'car-link'; }, scrollIntoView() { scrolled = 'car-link'; } };
    const notice = { textContent: '', focus() { focused = 'notice'; } };
    const listeners = {};
    const form = {
        elements, matches: (selector) => selector === '[data-step-two]', closest: () => null,
        querySelector: (selector) => selector.includes('data-validation-control') ? carLink : selector === '#dr-step-two-notice' ? notice : null,
        querySelectorAll: (selector) => selector === '[data-error-for]' ? outputs : elements,
        addEventListener: (type, handler) => { listeners[type] = handler; },
    };
    const fire = (type, name) => listeners[type]({ type, target: elements.namedItem(name), preventDefault() {} });
    return { form, outputs, fire, field: (name) => elements.namedItem(name), focus: () => focused, scroll: () => scrolled };
}

const empty = fixture();
assert.deepEqual(collectErrors(empty.form), messages);
empty.field('ward_code').disabled = true;
renderValidation(empty.form, collectErrors(empty.form), true);
assert.equal(empty.focus(), 'province_code');
assert.equal(empty.scroll(), 'province_code');
assert.equal(empty.outputs.filter((node) => !node.hidden).length, 8);
assert.equal(empty.field('odo').attrs['aria-invalid'], 'false');

for (const [name, message] of Object.entries(messages)) {
    const test = fixture(true);
    if (name === 'consent') test.field(name).checked = false;
    else test.field(name).value = '';
    assert.deepEqual(collectErrors(test.form), { [name]: message });
    renderValidation(test.form, collectErrors(test.form), true);
    assert.equal(test.focus(), name === 'vehicle_description' ? 'car-link' : name);
}
for (const [name, value, message] of [
    ['license_plate', '!bad', 'Biển số xe không hợp lệ.'],
    ['first_registration_year', 'invalid', 'Năm đăng ký không hợp lệ.'],
    ['first_registration_year', '9999', 'Năm đăng ký không hợp lệ.'],
    ['odo', '-1', 'Vui lòng nhập số km hợp lệ.'],
    ['odo', '1.5', 'Vui lòng nhập số km hợp lệ.'],
    ['odo', 'abc', 'Vui lòng nhập số km hợp lệ.'],
    ['facebook_url', 'facebook.com/groups/synthetic', 'Liên kết Facebook không hợp lệ.'],
]) {
    const test = fixture(true);
    test.field(name).value = value;
    assert.deepEqual(collectErrors(test.form), { [name]: message });
}
const badNumber = fixture(true);
badNumber.field('odo').validity.valid = false; // Browsers expose bad numeric text as value='' plus badInput.
assert.deepEqual(collectErrors(badNumber.form), { odo: 'Vui lòng nhập số km hợp lệ.' });

const editing = fixture(true);
initStepTwoValidation(editing.form);
assert.equal(editing.outputs.some((node) => !node.hidden), false, 'No initial errors');
editing.field('facebook_url').value = 'invalid';
editing.fire('input', 'facebook_url');
assert.equal(editing.outputs.some((node) => !node.hidden), false, 'No errors before interaction completes');
editing.fire('focusout', 'facebook_url');
assert.equal(editing.outputs.find((node) => node.dataset.errorFor === 'facebook_url').hidden, false);
editing.field('facebook_url').value = 'facebook.com/synthetic.customer';
editing.fire('input', 'facebook_url');
assert.equal(editing.field('facebook_url').attrs['aria-invalid'], 'false');
assert.equal(editing.field('address_line').value, 'Synthetic address');
editing.fire('submit');
assert.equal(editing.focus(), 'notice');

const server = fixture(true);
const plateOutput = server.outputs.find((node) => node.dataset.errorFor === 'license_plate');
plateOutput.textContent = 'Biển số xe này đã được đăng ký.'; plateOutput.hidden = false;
initStepTwoValidation(server.form);
assert.equal(server.focus(), 'license_plate');
server.fire('change', 'consent');
assert.equal(plateOutput.textContent, 'Biển số xe này đã được đăng ký.', 'Unrelated edits retain backend errors');
server.fire('submit');
assert.equal(plateOutput.hidden, false, 'Unchanged backend duplicate is not dismissed by client validation');
server.field('license_plate').value = '51K-543.21'; server.fire('input', 'license_plate');
assert.equal(plateOutput.hidden, true, 'Editing invalidates stale backend result; no new duplicate claim');
assert.equal(collectErrors(server.form).license_plate, undefined, 'No client duplicate inference');

const disabled = fixture(true);
disabled.field('ward_code').value = ''; disabled.field('ward_code').disabled = true;
renderValidation(disabled.form, collectErrors(disabled.form), true);
assert.equal(disabled.focus(), 'province_code', 'Disabled ward focuses actionable parent');
const production = fixture(true);
production.form.method = 'post';
let posted = 0;
globalThis.HTMLFormElement = { prototype: { submit() { posted++; } } };
initStepTwoValidation(production.form);
production.fire('submit');
assert.equal(posted, 1, 'Valid production Step Two submits its actual POST form');
production.field('consent').checked = false;
production.fire('submit');
assert.equal(posted, 1, 'Invalid Step Two never submits to the backend');
console.log('PASS Step Two empty/individual/multiple errors, focus/scroll, correction, retained values, backend duplicate preservation and no fabricated duplicates');
