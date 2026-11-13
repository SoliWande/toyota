import './dealer-profile';

document.querySelectorAll('[data-account-password]').forEach(button => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    button.hidden = false;
    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.textContent = reveal ? 'Ẩn' : 'Hiện';
        button.setAttribute('aria-label', reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        button.setAttribute('aria-pressed', String(reveal));
    });
    window.addEventListener('pageshow', () => { input.type = 'password'; button.textContent = 'Hiện'; button.setAttribute('aria-pressed', 'false'); });
});
document.querySelectorAll('[data-account-form]').forEach(form => {
    const submit = form.querySelector('[data-account-submit]');
    if (!submit) return;
    const label = submit.textContent;
    const disabled = submit.disabled;
    form.addEventListener('submit', event => {
        if (event.defaultPrevented || !form.checkValidity()) return;
        if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        form.setAttribute('aria-busy', 'true'); submit.disabled = true; submit.textContent = 'Đang gửi…';
    });
    window.addEventListener('pageshow', () => { form.removeAttribute('aria-busy'); submit.disabled = disabled; submit.textContent = label; });
});
const search = document.querySelector('[data-dealer-search]');
if (search) {
    search.hidden = false;
    const select = document.getElementById('dealer_id');
    const options = Array.from(select.options).map(option => option.cloneNode(true));
    const form = select.form;
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').toLocaleLowerCase('vi');
    const refresh = () => {
        const current = select.value;
        const manager = form.querySelector('[name=role]:checked')?.value === 'dealer';
        select.replaceChildren(...options.filter(option => !option.value || option.value === current || normalize(option.textContent).includes(normalize(search.value))).map(option => {
            const copy = option.cloneNode(true);
            copy.disabled = manager && copy.dataset.hasManager === '1';
            return copy;
        }));
        select.value = current;
        const occupied = manager && select.selectedOptions[0]?.dataset.hasManager === '1';
        select.setCustomValidity(occupied ? 'Đại lý đã có tài khoản quản lý. Vui lòng chọn đại lý khác.' : '');
        document.querySelector('[data-dealer-hint]').textContent = occupied ? 'Đại lý đã có tài khoản quản lý (kể cả tài khoản đang chờ duyệt).' : (manager ? 'Mỗi đại lý chỉ có một tài khoản quản lý. Admin Ô tô Sài Gòn xét duyệt.' : 'Quản lý của chính đại lý này xét duyệt tài khoản nhân viên.');
    };
    search.addEventListener('input', refresh);
    select.addEventListener('change', refresh);
    form.querySelectorAll('[name=role]').forEach(input => input.addEventListener('change', refresh));
    refresh();
}
