const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const post = async (url, payload) => {
    const response = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {
        Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf,
    }, body: JSON.stringify(payload)});
    if (!response.ok) throw new Error('Command unavailable');
    return response.json();
};
for (const button of document.querySelectorAll('.ac-call, .ac-publish')) {
    let busy = false;
    const key = typeof crypto.randomUUID === 'function' ? crypto.randomUUID()
        : Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
    button.addEventListener('click', async () => {
        if (busy || button.disabled) return;
        busy = true;
        button.disabled = true;
        try {
            if (button.classList.contains('ac-publish')) {
                const result = await post(button.dataset.publishUrl, {idempotency_key: key, preview_fingerprint: button.dataset.fingerprint});
                if (result.data?.status === 'published') window.location.reload();
            } else if (button.dataset.callCommand) {
                const payload = {idempotency_key: key};
                if (button.dataset.callCommand.endsWith('/freeze')) Object.assign(payload, {
                    purpose: 'award_top3', month: button.dataset.month, award_id: Number(button.dataset.awardId),
                });
                const result = await post(button.dataset.callCommand, payload);
                if (result.data?.status === 'FROZEN') {
                    const target = new URL(button.dataset.callUrl, window.location.origin);
                    target.searchParams.set('batch', result.data.batch_id);
                    window.location.assign(target.href);
                }
            } else {
                window.location.assign(button.dataset.callUrl);
            }
        } catch {
            // Missing error-state design: keep the current screen; never fabricate a success toast.
            button.disabled = false;
            busy = false;
        }
    });
}
