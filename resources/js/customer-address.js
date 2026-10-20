export function initCustomerAddressFields(root = document) {
    root.querySelectorAll('[data-customer-address]').forEach((container) => {
        if (container.dataset.addressInitialized) return;
        container.dataset.addressInitialized = 'true';

        const province = container.querySelector('[name="province_code"]');
        const ward = container.querySelector('[name="ward_code"]');
        const status = container.querySelector('[data-address-status]');
        const retry = container.querySelector('[data-address-retry]');
        const cache = new Map();
        let controller;
        let requestId = 0;
        container.addEventListener('address:reset', () => {
            controller?.abort();
            requestId++;
            status.textContent = '';
            retry.hidden = true;
        });

        if (province.value && ward.options.length > 1) {
            cache.set(province.value, Array.from(ward.options).filter((option) => option.value)
                .map((option) => ({ code: option.value, name: option.textContent })));
        }

        const loadWards = async () => {
            controller?.abort();
            const currentRequest = ++requestId;
            const provinceCode = province.value;
            retry.hidden = true;
            ward.disabled = true;
            ward.replaceChildren(new Option(provinceCode ? 'Đang tải Phường/Xã...' : 'Chọn Tỉnh/Thành trước', ''));
            status.textContent = provinceCode ? 'Đang tải danh sách Phường/Xã...' : '';
            if (!provinceCode) return;

            controller = new AbortController();
            try {
                if (!cache.has(provinceCode)) {
                    const response = await fetch(container.dataset.wardsUrl.replace('__PROVINCE__', encodeURIComponent(provinceCode)), {
                        credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('Cannot load wards');
                    const data = await response.json();
                    if (!Array.isArray(data.wards) || !data.wards.every((item) => typeof item.code === 'string'
                        && /^[0-9]{5}$/.test(item.code) && typeof item.name === 'string')) {
                        throw new Error('Invalid wards response');
                    }
                    cache.set(provinceCode, data.wards);
                }
                if (currentRequest !== requestId || province.value !== provinceCode) return;
                ward.replaceChildren(new Option('Chọn Phường/Xã', ''),
                    ...cache.get(provinceCode).map((item) => new Option(item.name, item.code)));
                ward.disabled = false;
                status.textContent = 'Vui lòng chọn Phường/Xã.';
            } catch (error) {
                if (error.name === 'AbortError' || currentRequest !== requestId) return;
                ward.replaceChildren(new Option('Chưa tải được Phường/Xã', ''));
                status.textContent = 'Không tải được danh sách Phường/Xã. Vui lòng thử lại.';
                retry.hidden = false;
            }
        };

        province.addEventListener('change', loadWards);
        retry.addEventListener('click', loadWards);
    });
}

if (typeof document !== 'undefined') initCustomerAddressFields();
