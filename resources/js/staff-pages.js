import { initCustomerAddressFields } from './customer-address';
initCustomerAddressFields();

const statuses = () => document.querySelectorAll('[data-share-status]');
const announce = text => statuses().forEach(node => { node.textContent = text; });
async function copyText(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }
    const field = document.createElement('textarea');
    field.value = text; field.style.cssText = 'position:fixed;top:-9999px';
    document.body.append(field); field.select();
    const success = document.execCommand('copy'); field.remove();
    if (!success) throw new Error('Clipboard unavailable');
}
document.querySelectorAll('[data-copy-text],[data-copy-target],[data-zalo-text],[data-zalo-target]').forEach(button => {
    button.addEventListener('click', async () => {
        const target = button.dataset.copyTarget || button.dataset.zaloTarget;
        const text = target ? document.getElementById(target)?.value : (button.dataset.copyText || button.dataset.zaloText);
        if (!text) return;
        const zalo = button.hasAttribute('data-zalo-text') || button.hasAttribute('data-zalo-target');
        // Open the real Zalo web client; never invent a prefilled share URL or mark a message as sent.
        if (zalo) window.open('https://chat.zalo.me/', '_blank', 'noopener,noreferrer');
        try {
            await copyText(text);
            announce(zalo ? 'Đã sao chép nội dung. Dán vào cuộc trò chuyện Zalo để gửi; hệ thống chưa xác nhận đã gửi.' : 'Đã sao chép.');
        } catch {
            announce('Chưa sao chép được. Vui lòng chọn nội dung và sao chép thủ công.');
        }
    });
});
document.querySelectorAll('[data-delete-customer]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('Bạn có chắc muốn xóa khai báo này?')) event.preventDefault();
}));
document.querySelectorAll('[data-staff-form]').forEach(form => {
    const submit = form.querySelector('[data-staff-submit]');
    const label = submit.textContent;
    form.addEventListener('submit', event => {
        if (event.defaultPrevented || !form.checkValidity()) return;
        if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        form.setAttribute('aria-busy', 'true'); submit.disabled = true; submit.textContent = 'Đang lưu…';
    });
    window.addEventListener('pageshow', () => { form.removeAttribute('aria-busy'); submit.disabled = false; submit.textContent = label; });
});
document.querySelectorAll('[data-evidence-input]').forEach(input => input.addEventListener('change', () => {
    const output = document.querySelector('[data-evidence-name]');
    if (output) output.textContent = input.files?.[0]?.name || '';
}));
document.querySelectorAll('[data-customer-message]').forEach(field => {
    const resize = () => { field.style.height = 'auto'; field.style.height = `${Math.max(150, field.scrollHeight) + 2}px`; };
    field.addEventListener('input', resize);
    window.addEventListener('resize', resize);
    resize();
});
