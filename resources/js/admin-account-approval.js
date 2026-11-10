import {showToast, hideToast} from './admin';

function bind() {
    document.querySelectorAll('[data-ac-account]').forEach(card => {
        const rejection = card.querySelector('[data-ac-rejection]');
        const toggle = card.querySelector('[data-ac-reject]');
        const error = card.querySelector('[data-ac-error]');
        toggle.addEventListener('click', () => {
            rejection.hidden = !rejection.hidden;
            toggle.setAttribute('aria-expanded', String(!rejection.hidden));
            hideToast('ac-toast');
            if (!rejection.hidden) rejection.querySelector('select').focus();
        });
        card.querySelector('[data-ac-cancel]').addEventListener('click', () => {
            rejection.reset();
            rejection.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            error.hidden = true;
            toggle.focus();
        });
        card.querySelectorAll('[data-ac-review]').forEach(form => form.addEventListener('submit', async event => {
            event.preventDefault();
            if (card.dataset.busy) return;
            card.dataset.busy = 'true';
            card.querySelectorAll('button').forEach(button => button.disabled = true);
            hideToast('ac-toast');
            error.hidden = true;
            let committed = false;
            try {
                const response = await fetch(form.action, {
                    method: 'POST', headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: new FormData(form),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat().join(' ') || data.message || response.statusText);
                committed = true;
                const updated = await fetch(location.href, {headers: {Accept: 'text/html'}});
                if (!updated.ok || updated.redirected) throw new Error(updated.statusText);
                const next = new DOMParser().parseFromString(await updated.text(), 'text/html').querySelector('[data-account-approval]');
                if (!next) throw new Error(updated.statusText);
                document.querySelector('[data-account-approval]').replaceWith(next);
                bind();
                // Rejection email/reminder/override toasts remain blocked; only actual approval has a design-backed success toast.
                if (data.message) {
                    document.querySelector('[data-ac-toast-message]').textContent = data.message;
                    showToast('ac-toast');
                }
            } catch (exception) {
                if (committed) location.reload();
                else {error.textContent = exception.message; error.hidden = false;}
            } finally {
                if (!committed) {
                    delete card.dataset.busy;
                    card.querySelectorAll('button').forEach(button => button.disabled = false);
                }
            }
        }));
    });
}
if (document.querySelector('[data-account-approval]')) bind();
