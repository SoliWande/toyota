const originalWardOptions = new WeakMap();
document.querySelectorAll('[data-inline-editor] [name="ward_code"]').forEach((select) => {
    originalWardOptions.set(select, { html: select.innerHTML, disabled: select.disabled });
});

document.querySelectorAll('[data-cancel-inline]').forEach((button) => {
    button.addEventListener('click', () => {
        const panel = button.closest('[data-inline-confirmation], [data-inline-editor]');
        if (panel?.hasAttribute('data-inline-editor')) {
            panel.querySelector('[data-customer-address]')?.dispatchEvent(new Event('address:reset'));
            panel.querySelector('form')?.reset();
            const ward = panel.querySelector('[name="ward_code"]');
            const original = originalWardOptions.get(ward);
            if (original) {
                ward.innerHTML = original.html;
                ward.disabled = original.disabled;
            }
        }
        if (panel) {
            panel.open = false;
            panel.querySelector('summary')?.focus();
        }
    });
});

document.querySelectorAll('form[data-prevent-double-submit]').forEach((form) => {
    let submitting = false;
    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }
        if (!form.checkValidity()) return;
        submitting = true;
        if (event.submitter?.hasAttribute('formaction')) form.action = event.submitter.formAction;
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        const status = form.querySelector('[data-submitting-status]');
        if (status) status.textContent = 'Đang xử lý, vui lòng chờ…';
    });
});

// Restore interactive controls when returning via the browser back/forward cache.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});
