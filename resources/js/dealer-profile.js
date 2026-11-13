document.querySelectorAll('.wc-dealer-avatar [data-fallback-image]').forEach(image => {
    const fallback = () => image.remove();
    image.addEventListener('error', fallback, { once: true });
    if (image.complete && !image.naturalWidth) fallback();
});

document.querySelectorAll('[data-dealer-profile]').forEach(form => {
    const input = form.querySelector('[name="avatar"]');
    const preview = form.querySelector('[data-avatar-preview]');
    const error = form.querySelector('[data-avatar-error]');
    let objectUrl;
    let selection = 0;
    const clear = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = undefined;
        preview.hidden = true;
        preview.removeAttribute('src');
    };
    input.addEventListener('change', () => {
        const version = ++selection;
        clear();
        error.hidden = true;
        error.textContent = '';
        input.setCustomValidity('');
        const file = input.files[0];
        if (!file) return;
        const reject = () => {
            clear();
            input.value = '';
            error.textContent = 'Vui lòng chọn ảnh JPG, PNG hoặc WebP, tối đa 5 MB và 4096 × 4096 px.';
            error.hidden = false;
        };
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            reject();
            return;
        }
        objectUrl = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            if (version !== selection) return;
            if (image.naturalWidth > 4096 || image.naturalHeight > 4096) { reject(); return; }
            preview.src = objectUrl;
            preview.hidden = false;
        };
        image.onerror = () => { if (version === selection) reject(); };
        image.src = objectUrl;
    });
    form.addEventListener('reset', () => { ++selection; clear(); error.hidden = true; });
    window.addEventListener('pagehide', clear);
});
