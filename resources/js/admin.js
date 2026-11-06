// Infrastructure only. No route interception, business mutation or automatic success feedback.
const previousFocus = new WeakMap();
export function openModal(id) {
    const dialog = document.getElementById(id);
    if (!(dialog instanceof HTMLDialogElement) || !dialog.matches('[data-admin-modal]') || dialog.open) return;
    previousFocus.set(dialog, document.activeElement);
    dialog.showModal();
}
export function closeModal(id) {
    const dialog = document.getElementById(id);
    if (!(dialog instanceof HTMLDialogElement) || !dialog.matches('[data-admin-modal]')) return;
    dialog.close();
    previousFocus.get(dialog)?.focus();
}
export function showToast(id) {
    const toast = document.getElementById(id);
    if (toast?.matches('[data-admin-toast]')) toast.hidden = false;
}
export function hideToast(id) {
    const toast = document.getElementById(id);
    if (toast?.matches('[data-admin-toast]')) toast.hidden = true;
}
document.querySelectorAll('[data-admin-modal]').forEach(dialog => {
    // Escape/backdrop behavior is not specified by the audited prototype.
    dialog.addEventListener('cancel', event => event.preventDefault());
    dialog.querySelectorAll('[data-admin-modal-close]').forEach(button => {
        button.addEventListener('click', () => closeModal(dialog.id));
    });
});
window.ToyotaAdminUI = Object.freeze({openModal, closeModal, showToast, hideToast});
