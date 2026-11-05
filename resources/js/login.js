const form = document.querySelector('[data-login-form]');
if (form) {
    const password = form.querySelector('[name=password]');
    const toggle = form.querySelector('[data-password-toggle]');
    const submit = form.querySelector('[data-login-submit]');
    const label = submit.textContent;
    const conceal = () => {
        password.type = 'password';
        toggle.textContent = 'Hiện';
        toggle.setAttribute('aria-label', 'Hiện mật khẩu');
        toggle.setAttribute('aria-pressed', 'false');
    };
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const reveal = password.type === 'password';
        password.type = reveal ? 'text' : 'password';
        toggle.textContent = reveal ? 'Ẩn' : 'Hiện';
        toggle.setAttribute('aria-label', reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        toggle.setAttribute('aria-pressed', String(reveal));
        password.focus({ preventScroll: true });
    });
    form.addEventListener('submit', event => {
        if (event.defaultPrevented || !form.checkValidity()) return;
        if (form.getAttribute('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }
        form.setAttribute('aria-busy', 'true');
        submit.disabled = true;
        submit.textContent = 'Đang đăng nhập…';
    });
    window.addEventListener('pageshow', () => {
        form.removeAttribute('aria-busy');
        submit.disabled = false;
        submit.textContent = label;
        conceal();
    });
}
