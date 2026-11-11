import {showToast} from './admin.js';

const filters = document.querySelector('[data-sales-filters]');
filters?.querySelectorAll('select').forEach(select => select.addEventListener('change', () => filters.requestSubmit()));
document.querySelector('[data-sales-lock]')?.addEventListener('submit', event => {
    const form = event.currentTarget;
    if (form.dataset.submitting) { event.preventDefault(); return; }
    form.dataset.submitting = 'true';
    form.querySelector('button[type=submit]').disabled = true;
});
// This marker exists only after the Laravel action succeeds and redirects back.
if (document.querySelector('[data-sales-success]')) showToast('sales-lock-success');
