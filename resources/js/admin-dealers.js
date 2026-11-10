import {openModal, closeModal, showToast, hideToast} from './admin';

const headers = {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
function toast(message) {
    document.querySelector('[data-dc-toast-message]').textContent = message;
    showToast('dc-toast');
}
function errorText(element, message) {
    element.textContent = message;
    element.hidden = !message;
}
async function refresh(id, message) {
    const url = new URL(location.href);
    url.searchParams.set('selected', id);
    const response = await fetch(url, {headers: {Accept: 'text/html'}});
    if (!response.ok || response.redirected) throw new Error(response.statusText);
    const documentNext = new DOMParser().parseFromString(await response.text(), 'text/html');
    const next = documentNext.querySelector('[data-dealer-page]');
    if (!next) throw new Error(response.statusText);
    document.querySelector('[data-dealer-page]').replaceWith(next);
    history.replaceState(null, '', url);
    bind();
    toast(message);
}
function bind() {
    const page = document.querySelector('[data-dealer-page]');
    page.querySelectorAll('[data-dc-open]').forEach(button => button.addEventListener('click', () => {
        hideToast('dc-toast');
        openModal(button.dataset.dcOpen);
    }));
    page.querySelectorAll('[data-dc-close], [data-admin-modal-close]').forEach(button => button.addEventListener('click', () => closeModal(button.closest('dialog').id)));
    page.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('cancel', event => event.preventDefault()));
    page.querySelectorAll('[data-dc-filters] select').forEach(select => select.addEventListener('change', () => select.form.requestSubmit()));
    page.querySelectorAll('[data-dc-form]').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        if (form.dataset.busy) return;
        form.dataset.busy = 'true';
        const dialog = form.closest('dialog');
        const submit = dialog.querySelector('[type=submit]');
        submit.disabled = true;
        form.querySelectorAll('[data-dc-field-error], [data-dc-form-error]').forEach(element => errorText(element, ''));
        form.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
        let committed = false;
        try {
            const response = await fetch(form.action, {method: 'POST', headers, body: new FormData(form)});
            const data = await response.json();
            if (!response.ok) {
                for (const [field, messages] of Object.entries(data.errors ?? {})) {
                    const label = form.querySelector(`[data-dc-field-error="${CSS.escape(field)}"]`);
                    if (label) {errorText(label, messages.join(' '));form.elements.namedItem(field)?.setAttribute('aria-invalid', 'true');}
                }
                throw new Error(data.message ?? response.statusText);
            }
            committed = true;
            closeModal(dialog.id);
            await refresh(data.id, data.message);
        } catch (error) {
            if (committed) location.reload();
            else errorText(form.querySelector('[data-dc-form-error]'), error.message);
        } finally {
            if (!committed) {delete form.dataset.busy; submit.disabled = false;}
        }
    }));
    page.querySelector('[data-dc-copy]')?.addEventListener('click', async event => {
        hideToast('dc-toast');
        errorText(page.querySelector('[data-dc-error]'), '');
        try {await navigator.clipboard.writeText(event.currentTarget.dataset.dcCopy);toast('Đã sao chép link đại lý');}
        catch (error) {errorText(page.querySelector('[data-dc-error]'), error.message);}
    });
    page.querySelectorAll('[data-dc-download]').forEach(link => link.addEventListener('click', async event => {
        event.preventDefault();
        if (link.dataset.busy) return;
        link.dataset.busy = 'true';
        hideToast('dc-toast');
        errorText(page.querySelector('[data-dc-error]'), '');
        try {
            const response = await fetch(link.href, {headers});
            const pdf = new URL(link.href).pathname.endsWith('/pdf');
            const type = pdf ? 'application/pdf' : 'image/png';
            if (!response.ok || !response.headers.get('content-type')?.startsWith(type)) throw new Error(response.statusText);
            const blob = await response.blob();
            const bytes = new Uint8Array(await blob.slice(0, 8).arrayBuffer());
            if (pdf ? String.fromCharCode(...bytes.slice(0, 5)) !== '%PDF-' : ![137,80,78,71,13,10,26,10].every((b,i)=>bytes[i]===b)) throw new Error(response.statusText);
            const url = URL.createObjectURL(blob), anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = response.headers.get('content-disposition')?.match(/filename="([^"]+)"/)?.[1] ?? (pdf ? 'standee.pdf' : 'qr.png');
            anchor.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            toast(link.dataset.dcToast);
        } catch (error) {errorText(page.querySelector('[data-dc-error]'), error.message);}
        finally {delete link.dataset.busy;}
    }));
}
if (document.querySelector('[data-dealer-page]')) bind();
