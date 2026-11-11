import {showToast} from './admin.js';
const search = document.querySelector('[data-group-search] input[name=phone]');
if (search?.dataset.lookupError) {
    search.setCustomValidity(search.dataset.lookupError);
    search.reportValidity();
}
search?.addEventListener('input', () => search.setCustomValidity(''));
document.querySelector('[data-group-verify]')?.addEventListener('submit', event => {
    const form = event.currentTarget;
    if (form.dataset.submitting) { event.preventDefault(); return; }
    form.dataset.submitting = 'true';
    form.querySelector('button[type=submit]').disabled = true;
});
if (document.querySelector('[data-group-success]')) showToast('group-lookup-success');
