import {openModal, closeModal, showToast, hideToast} from './admin';

let busy = false;
function bindPage() {
    const root = document.querySelector('[data-cq-page]');
    if (!root) return;
    root.querySelectorAll('[data-cq-filters]').forEach(form => {
        form.querySelectorAll('select, input[type=date]').forEach(input => input.addEventListener('change', () => form.requestSubmit()));
        form.querySelectorAll('input[type=date]').forEach(input => input.addEventListener('click', () => {
            try {input.showPicker?.();} catch { /* Native date input remains available. */ }
        }));
    });
    const dialog = root.querySelector('#cq-edit');
    const resetEdit = () => {
        if (busy) return;
        dialog?.querySelector('form')?.reset();
        clearErrors(dialog);
        closeModal('cq-edit');
    };
    root.querySelectorAll('[data-cq-edit]').forEach(button => button.addEventListener('click', () => openModal('cq-edit')));
    dialog?.addEventListener('cancel', event => event.preventDefault());
    dialog?.querySelectorAll('[data-admin-modal-close], [data-cq-modal-cancel]').forEach(button => button.addEventListener('click', resetEdit));
    const supplement = root.querySelector('#cq-supplement');
    const toggle = root.querySelector('[data-cq-supplement-toggle]');
    const setSupplement = visible => {
        if (!supplement || busy) return;
        supplement.hidden = !visible;
        toggle?.setAttribute('aria-expanded', String(visible));
    };
    toggle?.addEventListener('click', () => setSupplement(supplement?.hidden));
    root.querySelector('[data-cq-supplement-cancel]')?.addEventListener('click', () => {
        if (busy) return;
        supplement?.reset();
        clearErrors(root);
        setSupplement(false);
    });
    root.querySelectorAll('[data-cq-action]').forEach(form => form.addEventListener('submit', submitAction));
    if (root.dataset.flashToast) toast(root.dataset.flashToast);
    delete root.dataset.flashToast;
}
function clearErrors(root) {
    root?.querySelectorAll('[data-cq-error], [data-cq-form-error], [data-cq-field-error]').forEach(node => {
        node.textContent = '';
        node.hidden = true;
    });
}
function toast(message) {
    const node = document.querySelector('[data-cq-toast-message]');
    if (!node || !message) return;
    node.textContent = message;
    showToast('cq-toast');
}
async function submitAction(event) {
    event.preventDefault();
    if (busy) return;
    const form = event.currentTarget;
    const root = form.closest('[data-cq-page]');
    const payload = new FormData(form);
    clearErrors(root);
    hideToast('cq-toast');
    const buttons = [...root.querySelectorAll('button')].map(button => [button, button.disabled]);
    buttons.forEach(([button]) => {button.disabled = true;});
    busy = true;
    let committed = false;
    try {
        const response = await fetch(form.getAttribute('action'), {method: 'POST', body: payload, headers: {Accept: 'application/json'}, credentials: 'same-origin'});
        const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
        if (!response.ok) {
            const errors = Object.values(data.errors ?? {}).flat();
            form.querySelectorAll('[data-cq-field-error]').forEach(node => {
                const messages = data.errors?.[node.dataset.cqFieldError];
                if (messages) {node.textContent = messages.join(' '); node.hidden = false;}
            });
            const error = form.querySelector('[data-cq-form-error]') ?? root.querySelector('[data-cq-error]');
            error.textContent = errors.join(' ') || (response.status === 419 ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.' : 'Không thể lưu thay đổi. Vui lòng thử lại.');
            error.hidden = false;
            return;
        }
        committed = true;
        const refreshed = await fetch(location.href, {headers: {Accept: 'text/html'}, credentials: 'same-origin'});
        if (!refreshed.ok) throw new Error('refresh');
        const documentNext = new DOMParser().parseFromString(await refreshed.text(), 'text/html');
        const next = documentNext.querySelector('[data-cq-page]');
        if (!next) throw new Error('refresh');
        closeModal('cq-edit');
        const badge = document.querySelector('.ad-nav-item[aria-current] b');
        const freshBadge = documentNext.querySelector('.ad-nav-item[aria-current] b');
        if (badge && freshBadge) badge.textContent = freshBadge.textContent;
        root.replaceWith(next);
        const url = new URL(refreshed.url);
        if (next.dataset.selected) url.searchParams.set('selected', next.dataset.selected);
        else url.searchParams.delete('selected');
        if (url.searchParams.has('page')) url.searchParams.set('page', next.dataset.page);
        history.replaceState(null, '', url);
        bindPage();
        toast(data.message);
    } catch {
        if (committed) {location.reload(); return;}
        const error = form.querySelector('[data-cq-form-error]') ?? root.querySelector('[data-cq-error]');
        error.textContent = 'Không thể kết nối. Vui lòng kiểm tra lại trước khi thử lại.';
        error.hidden = false;
    } finally {
        busy = false;
        buttons.forEach(([button, disabled]) => {button.disabled = disabled;});
    }
}
bindPage();
