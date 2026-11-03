import { initCustomerAddressFields } from './customer-address';

export function normalizePhone(phone) {
    return phone.replace(/[\s().-]+/g, '').replace(/^(?:\+84|0084)/, '0');
}

export function isPersonalFacebook(value) {
    try {
        const url = new URL(/^https?:\/\//i.test(value) ? value : `https://${value}`);
        if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password || url.port) return false;
        if (!['facebook.com', 'www.facebook.com', 'm.facebook.com', 'mobile.facebook.com', 'mbasic.facebook.com', 'web.facebook.com'].includes(url.hostname.toLowerCase())) return false;
        if (url.pathname.toLowerCase() === '/profile.php') {
            const ids = [...url.searchParams].filter(([key]) => key.toLowerCase() === 'id');
            return ![...url.searchParams.keys()].some((key) => key.toLowerCase().startsWith('id['))
                && ids.length === 1 && /^[0-9]{1,30}$/.test(ids[0][1]) && /[1-9]/.test(ids[0][1]);
        }
        const path = url.pathname.replace(/^\/|\/$/g, '');
        return /^[a-zA-Z0-9][a-zA-Z0-9.]{0,99}$/.test(path) && !['groups', 'pages', 'people', 'watch', 'reel', 'reels', 'photo', 'photos', 'videos', 'events', 'marketplace', 'gaming', 'share', 'sharer.php', 'login', 'login.php', 'l.php', 'plugins', 'settings', 'help', 'home.php', 'search', 'stories', 'profile.php'].includes(path.toLowerCase());
    } catch { return false; }
}

// Display only: callers must provide genuine server validation errors, never duplicate guesses.
export function renderValidation(form, errors = {}, focus = false) {
    form.querySelectorAll('[data-error-for]').forEach((node) => {
        const value = errors[node.dataset.errorFor];
        const message = Array.isArray(value) ? value.join(' ') : value || '';
        if (node.textContent !== message) node.textContent = message;
        node.hidden = !message;
    });
    form.querySelectorAll('[aria-invalid]').forEach((node) => node.setAttribute('aria-invalid', errors[node.name] ? 'true' : 'false'));
    const fieldOrder = [...form.elements].map((control) => control.name);
    const entries = Object.entries(errors).filter(([, value]) => value?.length)
        .sort(([left], [right]) => fieldOrder.indexOf(left) - fieldOrder.indexOf(right));
    let first;
    entries.forEach(([name]) => {
        const control = form.elements.namedItem(name);
        const controls = control instanceof RadioNodeList ? [...control] : (control ? [control] : []);
        controls.forEach((node) => node.setAttribute('aria-invalid', 'true'));
        first ||= controls.find((node) => !node.disabled && node.type !== 'hidden');
    });
    if (form.matches?.('[data-step-two]')) {
        const carLink = form.querySelector('[data-validation-control="vehicle_description"]');
        carLink?.setAttribute('aria-invalid', errors.vehicle_description ? 'true' : 'false');
        // Hidden car value and disabled dependent selects need an actionable focus target.
        const firstName = entries[0]?.[0];
        if (firstName === 'vehicle_description') first = carLink;
        if (firstName === 'ward_code' && form.elements.namedItem('ward_code')?.disabled) first = form.elements.namedItem('province_code');
    }
    if (focus && entries.length) {
        first?.focus();
        if (form.matches?.('[data-step-two]')) first?.scrollIntoView?.({ block: 'nearest' });
    }
}

export function collectErrors(form) {
    return form.matches?.('[data-step-one]') ? collectStepOneErrors(form) : collectStepTwoErrors(form);
}

export function collectStepTwoErrors(form) {
    const errors = {};
    const required = {
        province_code: 'Vui lòng chọn Tỉnh/Thành phố.', ward_code: 'Vui lòng chọn Phường/Xã.',
        address_line: 'Vui lòng nhập địa chỉ chi tiết.', vehicle_description: 'Vui lòng chọn dòng xe.',
        license_plate: 'Vui lòng nhập biển số xe.', first_registration_year: 'Vui lòng chọn năm đăng ký lần đầu.',
        facebook_url: 'Vui lòng nhập liên kết Facebook cá nhân.', consent: 'Vui lòng đồng ý điều khoản để tiếp tục.',
    };
    for (const [name, message] of Object.entries(required)) {
        const control = form.elements.namedItem(name);
        if (control && (control.type === 'checkbox' ? !control.checked : !control.value.trim())) errors[name] = message;
    }
    const plate = form.elements.namedItem('license_plate');
    if (plate?.value.trim() && (!plate.validity.valid || !/^[A-Z0-9][A-Z0-9.\-]{2,19}$/.test(plate.value.trim().toUpperCase()))) errors.license_plate = 'Biển số xe không hợp lệ.';
    const year = form.elements.namedItem('first_registration_year');
    if (year?.value && (!/^\d{4}$/.test(year.value) || ![...year.options].some((option) => option.value === year.value && !option.disabled))) errors.first_registration_year = 'Năm đăng ký không hợp lệ.';
    const odo = form.elements.namedItem('odo');
    if (odo && (!odo.validity.valid || (odo.value !== '' && (!/^\d+$/.test(odo.value) || !Number.isSafeInteger(Number(odo.value)))))) errors.odo = 'Vui lòng nhập số km hợp lệ.';
    const facebook = form.elements.namedItem('facebook_url');
    if (facebook?.value.trim() && (!facebook.validity.valid || !isPersonalFacebook(facebook.value.trim()))) errors.facebook_url = 'Liên kết Facebook không hợp lệ.';
    const address = form.elements.namedItem('address_line');
    if (address?.value.trim() && !address.validity.valid) errors.address_line = 'Địa chỉ chi tiết không được vượt quá 500 ký tự.';
    const fieldOrder = [...form.elements].map((control) => control.name);
    return Object.fromEntries(Object.entries(errors).sort(([left], [right]) => fieldOrder.indexOf(left) - fieldOrder.indexOf(right)));
}

export function initStepTwoValidation(form) {
    const notice = form.querySelector('#dr-step-two-notice');
    const initialNotice = notice?.textContent;
    const touched = new Set();
    const errors = {};
    const serverErrors = new Map();
    form.querySelectorAll('[data-error-for]').forEach((node) => {
        if (!node.hidden && node.textContent.trim()) {
            const name = node.dataset.errorFor;
            errors[name] = node.textContent.trim();
            touched.add(name);
            serverErrors.set(name, { value: form.elements.namedItem(name)?.value, message: errors[name] });
        }
    });
    const validate = () => {
        const result = collectStepTwoErrors(form);
        serverErrors.forEach((error, name) => {
            if (form.elements.namedItem(name)?.value === error.value) result[name] = error.message;
            else serverErrors.delete(name);
        });
        return result;
    };
    if (Object.keys(errors).length) renderValidation(form, errors, !form.closest('[hidden]'));
    const update = (event) => {
        const name = event.target.name;
        if (!name || !form.elements.namedItem(name)) return;
        if (notice && notice.textContent !== initialNotice) notice.textContent = initialNotice;
        if (event.type === 'focusout' || event.type === 'change') touched.add(name);
        if (!touched.has(name)) return;
        const current = validate();
        if (current[name]) errors[name] = current[name]; else delete errors[name];
        // Province changes reset the dependent ward; never keep a stale ward error/value.
        if (name === 'province_code' && touched.has('ward_code')) {
            if (current.ward_code) errors.ward_code = current.ward_code; else delete errors.ward_code;
        }
        renderValidation(form, errors);
    };
    for (const type of ['input', 'change', 'focusout']) form.addEventListener(type, update);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const current = validate();
        Object.keys(errors).forEach((name) => delete errors[name]);
        Object.assign(errors, current);
        [...form.elements].forEach((control) => { if (control.name) touched.add(control.name); });
        renderValidation(form, errors, true);
        if (Object.keys(errors).length && notice) notice.textContent = initialNotice;
        if (!Object.keys(errors).length) {
            if (form.method?.toLowerCase() === 'post') {
                HTMLFormElement.prototype.submit.call(form);
                return;
            }
            notice.textContent = 'Thông tin hợp lệ trên giao diện. Chưa gửi đăng ký: biểu mẫu hai bước sẽ được kết nối ở giai đoạn tiếp theo.';
            notice.tabIndex = -1; notice.focus();
        }
    });
}

export function collectStepOneErrors(form) {
    const errors = {};
    const name = form.elements.namedItem('customer_name');
    const phone = form.elements.namedItem('phone');
    if (!name?.value.trim()) errors.customer_name = 'Vui lòng nhập họ tên.';
    else if (!name.validity.valid) errors.customer_name = 'Họ tên không được vượt quá 255 ký tự.';
    if (!phone?.value.trim()) errors.phone = 'Vui lòng nhập số điện thoại.';
    else if (!phone.validity.valid || !/^0[1-9][0-9]{8,9}$/.test(normalizePhone(phone.value))) errors.phone = 'Số điện thoại không hợp lệ.';
    if (!form.querySelector('input[name="vehicle_description"]:checked')) errors.vehicle_description = 'Vui lòng chọn dòng xe.';
    return errors;
}

export function collectLookupErrors(form) {
    const errors = {};
    const phone = form.elements.namedItem('phone')?.value.trim() || '';
    const plate = form.elements.namedItem('license_plate')?.value.trim() || '';
    if (!phone) errors.phone = 'Vui lòng nhập số điện thoại.';
    else if (phone.length > 30 || !/^0[1-9][0-9]{8,9}$/.test(normalizePhone(phone))) errors.phone = 'Số điện thoại không hợp lệ.';
    if (!plate) errors.license_plate = 'Vui lòng nhập biển số xe.';
    else if (plate.length > 30 || !/^[A-Z0-9]{3,20}$/.test(plate.toUpperCase().replace(/[\s.\-]+/g, ''))) errors.license_plate = 'Biển số xe không hợp lệ.';
    return errors;
}

export function initLookupValidation(form) {
    const errors = {};
    const touched = new Set();
    const button = form.querySelector('[data-lookup-submit]');
    const loading = form.querySelector('.dr-lookup-loading');
    form.querySelectorAll('[data-error-for]').forEach((node) => {
        if (!node.hidden && node.textContent.trim()) {
            errors[node.dataset.errorFor] = node.textContent.trim(); touched.add(node.dataset.errorFor);
        }
    });
    if (Object.keys(errors).length) renderValidation(form, errors, true);
    else form.closest?.('[data-dealer-registration]')?.querySelector('[data-lookup-result-focus]')?.focus();
    for (const type of ['input', 'change', 'focusout']) form.addEventListener(type, (event) => {
        const name = event.target.name;
        if (!['phone', 'license_plate'].includes(name)) return;
        if (type !== 'input') touched.add(name);
        if (!touched.has(name)) return;
        const current = collectLookupErrors(form);
        if (current[name]) errors[name] = current[name]; else delete errors[name];
        renderValidation(form, errors);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const current = collectLookupErrors(form);
        Object.keys(errors).forEach((name) => delete errors[name]); Object.assign(errors, current);
        ['phone', 'license_plate'].forEach((name) => touched.add(name));
        renderValidation(form, errors, true);
        if (Object.keys(errors).length || form.dataset.lookupSubmitting) return;
        form.dataset.lookupSubmitting = 'true'; form.setAttribute('aria-busy', 'true');
        if (button) button.disabled = true;
        if (loading) loading.hidden = false;
        HTMLFormElement.prototype.submit.call(form);
    });
    window.addEventListener('pageshow', () => {
        delete form.dataset.lookupSubmitting; form.removeAttribute('aria-busy');
        if (button) button.disabled = false;
        if (loading) loading.hidden = true;
    });
}

export function collectSupplementErrors(form) {
    const value = String(new FormData(form).get('facebook_profile_url') || '').trim();
    if (!value) return { facebook_profile_url: 'Vui lòng nhập liên kết Facebook cá nhân.' };
    return value.length <= 2048 && /^https?:\/\//i.test(value) && isPersonalFacebook(value) ? {} : { facebook_profile_url: 'Liên kết Facebook không hợp lệ.' };
}

export function initSupplementValidation(form) {
    let touched = false;
    const input = form.elements.namedItem('facebook_profile_url');
    const button = form.querySelector('[type=submit]');
    const loading = form.querySelector('[data-supplement-loading]');
    if (input?.getAttribute('aria-invalid') === 'true') { touched = true; input.focus(); }
    for (const type of ['input', 'focusout']) input?.addEventListener(type, () => {
        if (type === 'focusout') touched = true;
        if (touched) renderValidation(form, collectSupplementErrors(form));
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault(); touched = true;
        const errors = collectSupplementErrors(form); renderValidation(form, errors, true);
        if (Object.keys(errors).length || form.dataset.submitting) return;
        form.dataset.submitting = 'true'; form.setAttribute('aria-busy', 'true');
        if (button) button.disabled = true;
        if (loading) loading.hidden = false;
        HTMLFormElement.prototype.submit.call(form);
    });
    window.addEventListener('pageshow', () => {
        delete form.dataset.submitting; form.removeAttribute('aria-busy');
        if (button) button.disabled = false;
        if (loading) loading.hidden = true;
    });
}

export function initDealerRegistration(root = document) {
    const site = root.querySelector('[data-dealer-registration]');
    if (!site) return;
    site.querySelectorAll('[data-registration-lookup]').forEach(initLookupValidation);
    site.querySelectorAll('[data-registration-supplement]').forEach(initSupplementValidation);
    if (!site.querySelector('[data-registration-lookup]')) site.querySelector('[data-lookup-result-focus]')?.focus();
    initCustomerAddressFields(site);
    const first = site.querySelector('[data-step-one]');
    const second = site.querySelector('[data-step-two]');
    const landingScreen = site.querySelector('[data-landing-screen]');
    const secondScreen = site.querySelector('[data-step-two-screen]');
    // Server-rendered errors after a POST redirect use the same field order as client validation.
    first?.querySelector('[aria-invalid="true"]')?.focus();
    const showLanding = () => {
        if (!landingScreen || !secondScreen) return;
        landingScreen.hidden = false; secondScreen.hidden = true;
        site.classList.add('dr-landing'); site.classList.remove('dr-inner');
        first?.querySelector('input')?.focus();
    };
    site.querySelectorAll('[data-back-step]').forEach((link) => link.addEventListener('click', (event) => {
        if (!landingScreen) return;
        event.preventDefault(); showLanding();
    }));
    first?.addEventListener('submit', (event) => {
        event.preventDefault(); const errors = collectErrors(first); renderValidation(first, errors, true);
        if (Object.keys(errors).length) return;
        if (!site.hasAttribute('data-preview')) { HTMLFormElement.prototype.submit.call(first); return; }
        if (!secondScreen || !second) return;
        const values = new FormData(first);
        const put = (selector, value) => site.querySelectorAll(selector).forEach((node) => { node.textContent = value; });
        put('[data-summary-name]', values.get('customer_name')); put('[data-summary-phone]', values.get('phone')); put('[data-selected-car]', values.get('vehicle_description'));
        second.elements.namedItem('vehicle_description').value = values.get('vehicle_description');
        landingScreen.hidden = true; secondScreen.hidden = false;
        site.classList.remove('dr-landing'); site.classList.add('dr-inner');
        secondScreen.querySelector('h1')?.focus(); window.scrollTo(0, 0);
    });
    if (second) initStepTwoValidation(second);
    // Until initialization, these controls stay disabled; without JS no PII can be submitted as a GET.
    site.querySelectorAll('[data-ui-submit]').forEach((button) => { button.disabled = false; });
    site.querySelectorAll('[data-fallback-image]').forEach((image) => image.addEventListener('error', () => image.remove(), { once:true }));
    const viewport = window.visualViewport;
    viewport?.addEventListener('resize', () => site.classList.toggle('dr-keyboard-open', window.innerHeight - viewport.height > 120));
    window.addEventListener('pagehide', () => {
        site.querySelectorAll('[data-ui-form]').forEach((form) => form.reset());
        site.querySelectorAll('[data-summary-name],[data-summary-phone]').forEach((node) => { node.textContent = 'Chưa nhập'; });
    });
}

if (typeof document !== 'undefined') initDealerRegistration();
