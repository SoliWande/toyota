const root = document.querySelector('[data-call-audit]');
if (root) {
    const base = root.dataset.api;
    const purpose = root.dataset.purpose || 'general_audit';
    const form = root.querySelector('[data-workspace]');
    const toast = document.querySelector('[data-call-toast]');
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const warning = form.querySelector('[data-warning]');
    const noAnswerWarning = warning.textContent;
    const updateWarning = () => { warning.textContent = form.querySelector('[name=outcome]:checked')?.value === 'wrong' ? warning.dataset.wrongWarning : noAnswerWarning; };
    let batch = null, rows = [], selected = null, lease = null, busy = false, pendingSave = null, pendingClaim = null, expiryTimer;
    let tab = 'needs_call';
    const node = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = text;
        return element;
    };
    const set = (selector, text) => { root.querySelector(selector).textContent = text ?? ''; };
    const key = () => typeof crypto.randomUUID === 'function' ? crypto.randomUUID()
        : [...crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
    const itemUrl = (item, action) => `${base}/${batch.id}/items/${item.id}/${action}`;
    async function request(url, body) {
        const response = await fetch(url, {method: body ? 'POST' : 'GET', credentials: 'same-origin', redirect: 'error',
            headers: {'Accept': 'application/json', ...(body ? {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf} : {})},
            ...(body ? {body: JSON.stringify(body)} : {})});
        const data = await response.json();
        if (!response.ok) {
            const error = new Error('audit_request_failed');
            error.status = response.status;
            throw error;
        }
        return {data, date: response.headers.get('Date')};
    }
    const input = (item, extra = {}) => ({purpose, row_version: item.row_version, ...extra});
    function conceal() {
        clearTimeout(expiryTimer);
        form.hidden = true;
        set('[data-phone]', '');
        form.reset();
        updateWarning();
        form.querySelectorAll('fieldset').forEach(fieldset => { fieldset.disabled = true; });
        form.querySelector('.ca-save').disabled = true;
    }
    function status(item) {
        if (lease && lease.item.id === item.id) return ['info', 'Đang gọi'];
        if (item.state === 'finding_pending') return ['rejected', 'Sai thông tin'];
        if (item.outcome === 'correct') return ['approved', 'Đúng'];
        if (item.state === 'recall') return ['pending', 'Gọi lại lần 2'];
        if (item.state === 'needs_call') return ['neutral', 'Chưa gọi'];
        return null; // No Figma badge for second no-answer: do not call it Correct.
    }
    function paintRows() {
        const list = root.querySelector('[data-list]');
        list.replaceChildren();
        rows.forEach(item => {
            const button = node('button', 'ca-row');
            button.type = 'button'; button.dataset.item = item.id;
            button.disabled = busy;
            if (selected?.id === item.id) button.setAttribute('aria-current', 'true');
            const copy = node('span');
            copy.append(node('strong', '', item.name), node('small', '', `${item.dealer.name} · ${item.phone_masked ?? ''}`));
            button.append(copy);
            const badge = status(item);
            if (badge) { const element = node('span', `ad-badge ad-badge-${badge[0]}`); element.append(node('i'), document.createTextNode(badge[1])); button.append(element); }
            button.addEventListener('click', () => run(async () => { if (pendingSave || pendingClaim) return; await release(); await open(item); }));
            list.append(button);
        });
    }
    function paintDealers(dealers) {
        const list = root.querySelector('[data-dealers]'); list.replaceChildren();
        root.querySelector('.ca-dealers').hidden = dealers.length === 0;
        dealers.forEach(dealer => {
            const row = node('div', 'ca-dealer'), title = node('div');
            title.append(node('strong', '', dealer.name), node('small', '', `${dealer.progress.called_count}/${dealer.actual_count} đã gọi`));
            if (dealer.wrong_ratio !== null) {
                const wrong = dealer.progress.finding_pending_count;
                const value = node('b', '', `${wrong} sai${wrong ? ` · ${dealer.wrong_ratio}%` : ''}`);
                if (!wrong) value.dataset.zero = '';
                title.append(value);
            }
            const track = node('div', 'ca-track'), bar = node('i');
            bar.style.width = `${dealer.wrong_ratio ?? 0}%`;
            if (dealer.wrong_ratio === 0) bar.dataset.zero = '';
            track.append(bar); row.append(title, track); list.append(row);
        });
    }
    async function refresh() {
        const detail = (await request(`${base}/${batch.id}?purpose=${purpose}`)).data.data;
        batch = detail;
        const result = (await request(`${base}/${batch.id}/items?purpose=${purpose}&tab=${tab}&per_page=50`)).data;
        rows = result.data;
        for (let page = 2; page <= result.meta.last_page; page++) {
            rows.push(...(await request(`${base}/${batch.id}/items?purpose=${purpose}&tab=${tab}&per_page=50&page=${page}`)).data.data);
        }
        const [year, month] = batch.month.split('-');
        set('[data-subtitle]', `Tháng ${Number(month)}/${year} · ${batch.actual_count} khách được chọn ngẫu nhiên từ các đại lý`);
        root.querySelector('.ca-progress').hidden = false;
        set('[data-progress]', `${batch.progress.called_count} / ${batch.actual_count}`);
        root.querySelector('[data-progress-bar]').style.width = `${batch.actual_count ? 100 * batch.progress.called_count / batch.actual_count : 0}%`;
        result.tabs.forEach(value => { set(`[data-tab-count="${value.key}"]`, value.count); });
        root.querySelectorAll('[data-tab]').forEach(button => {
            button.toggleAttribute('aria-current', button.dataset.tab === tab);
        });
        paintDealers(detail.dealers); paintRows();
    }
    async function open(item) {
        conceal(); selected = item; paintRows();
        Object.assign(item, (await request(`${base}/${batch.id}/items/${item.id}?purpose=${purpose}`)).data.data);
        const workspace = (await request(itemUrl(item, 'workspace'), input(item))).data.data;
        set('[data-name]', workspace.name);
        set('[data-description]', [workspace.dealer, workspace.vehicle, workspace.plate, workspace.verified_at ? `Đã xác minh ${workspace.verified_at}` : null].filter(Boolean).join(' · '));
        set('[data-phone]', workspace.phone_masked);
        set('[data-script]', workspace.script);
        workspace.questions.forEach((question, i) => set(`[data-question="${i + 1}"]`, question));
        if (workspace.outcome) form.querySelector(`[name=outcome][value="${workspace.outcome}"]`).checked = true;
        workspace.answers.forEach((answer, i) => {
            if (answer !== null) form.querySelector(`[name=answer_${i + 1}][value="${answer}"]`).checked = true;
        });
        updateWarning();
        form.querySelector('[data-start]').disabled = !['needs_call', 'recall'].includes(item.state) || (item.state === 'recall' && !item.is_due);
        form.hidden = false;
    }
    async function release() {
        if (!lease) return;
        const current = lease;
        try {
            const result = await request(itemUrl(current.item, 'release'), input(current.item, {claim_token: current.token, idempotency_key: current.releaseKey}));
            current.item.row_version = result.data.data.row_version;
        } catch (error) {
            if (![403, 409].includes(error.status)) throw error;
        }
        lease = null; conceal();
    }
    async function run(action) {
        if (busy) return;
        busy = true;
        root.setAttribute('aria-busy', 'true');
        root.querySelectorAll('[data-tab],.ca-row').forEach(button => { button.disabled = true; });
        root.removeAttribute('data-error');
        try { await action(); } catch {
            root.dataset.error = 'audit_unavailable';
            if (!pendingSave && !pendingClaim) { conceal(); }
        } finally {
            busy = false; root.removeAttribute('aria-busy');
            root.querySelectorAll('[data-tab],.ca-row').forEach(button => { button.disabled = false; });
        }
    }
    function validate() {
        if (!lease || busy || pendingSave) return;
        const outcome = form.querySelector('[name=outcome]:checked')?.value;
        const answers = [1, 2, 3].map(i => form.querySelector(`[name=answer_${i}]:checked`)?.value);
        const valid = outcome === 'no_answer' || (answers.every(Boolean) && (outcome === 'correct' ? answers.every(v => v === 'true') : outcome === 'wrong' && answers.includes('false')));
        form.querySelector('.ca-save').disabled = !valid;
    }
    root.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => run(async () => {
        if (pendingSave || pendingClaim) return;
        toast.hidden = true; await release(); conceal(); selected = null; tab = button.dataset.tab;
        if (batch) { await refresh(); if (rows[0]) await open(rows[0]); }
    })));
    form.querySelector('[data-start]').addEventListener('click', () => run(async () => {
        if (!selected || lease) return;
        const item = selected;
        pendingClaim ??= input(item, {idempotency_key: key()});
        let result;
        try { result = await request(itemUrl(item, 'claim'), pendingClaim); }
        catch (error) { if ([403, 409, 419, 422].includes(error.status)) pendingClaim = null; throw error; }
        pendingClaim = null;
        const claim = result.data.data;
        item.row_version = claim.row_version;
        lease = {item, token: claim.claim_token, releaseKey: key()};
        const workspace = (await request(itemUrl(item, 'workspace'), input(item, {claim_token: lease.token}))).data.data;
        form.reset(); // A new call never inherits answers or the previous no-answer outcome.
        updateWarning();
        set('[data-phone]', workspace.phone.length === 10 ? workspace.phone.replace(/^(\d{4})(\d{3})(\d{3})$/, '$1 $2 $3') : workspace.phone);
        form.querySelectorAll('fieldset').forEach(fieldset => { fieldset.disabled = false; });
        form.querySelector('[data-start]').disabled = true;
        paintRows();
        const serverDate = Date.parse(result.date) || Date.now();
        expiryTimer = setTimeout(() => { conceal(); lease = null; root.dataset.error = 'lease_expired'; paintRows(); }, Math.max(0, Date.parse(claim.claim_expires_at) - serverDate));
    }));
    form.addEventListener('change', event => {
        updateWarning();
        if (event.target.name === 'outcome') {
            const noAnswer = event.target.value === 'no_answer';
            form.querySelector('.ca-questions').disabled = noAnswer;
            if (noAnswer) form.querySelectorAll('.ca-questions input').forEach(input => { input.checked = false; });
        }
        validate();
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (busy || !lease) return;
        if (!pendingSave) {
            const outcome = form.querySelector('[name=outcome]:checked')?.value;
            const answers = [1, 2, 3].map(i => form.querySelector(`[name=answer_${i}]:checked`)?.value);
            if (!(outcome === 'no_answer' || (answers.every(Boolean) && (outcome === 'correct' ? answers.every(v => v === 'true') : outcome === 'wrong' && answers.includes('false'))))) return;
        }
        run(async () => {
            toast.hidden = true;
            if (!pendingSave) {
                const outcome = form.querySelector('[name=outcome]:checked')?.value;
                const payload = input(lease.item, {claim_token: lease.token, idempotency_key: key(), outcome, note: form.elements.note.value});
                for (let i = 1; i <= 3; i++) payload[`answer_${i}`] = outcome === 'no_answer' ? null : form.querySelector(`[name=answer_${i}]:checked`).value === 'true';
                pendingSave = {item: lease.item, payload};
            }
            const pending = pendingSave;
            form.querySelectorAll('fieldset').forEach(fieldset => { fieldset.disabled = true; });
            form.querySelector('.ca-save').disabled = true;
            try {
                await request(itemUrl(pending.item, 'record'), pending.payload);
            } catch (error) {
                if ([403, 409, 419, 422].includes(error.status)) {
                    pendingSave = null; await release(); conceal();
                } else { form.querySelector('.ca-save').disabled = false; }
                throw error;
            }
            pendingSave = null; lease = null; selected = null; conceal();
            await refresh();
            const next = rows.find(item => item.id !== pending.item.id && (item.state === 'needs_call' || (item.state === 'recall' && item.is_due)));
            if (next) {
                await open(next);
                if (pending.payload.outcome === 'correct') { toast.hidden = false; setTimeout(() => { toast.hidden = true; }, 5000); }
            }
        });
    });
    window.addEventListener('pagehide', () => { set('[data-phone]', ''); });
    run(async () => {
        const params = new URLSearchParams({purpose, per_page: '1'});
        if (root.dataset.month) params.set('month', root.dataset.month);
        batch = root.dataset.batch ? (await request(`${base}/${root.dataset.batch}?purpose=${purpose}`)).data.data : (await request(`${base}?${params}`)).data.data[0] ?? null;
        if (batch) { await refresh(); if (rows[0]) await open(rows[0]); }
        else root.querySelectorAll('[data-tab-count]').forEach(count => { count.textContent = '0'; });
    });
}
