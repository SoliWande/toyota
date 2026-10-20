import './bootstrap';
import './customer-address';
import './submission-inline';

document.querySelectorAll('[data-sales-avatar], [data-fallback-image]').forEach((image) => {
    const showPlaceholder = () => image.remove();
    image.addEventListener('error', showPlaceholder, { once: true });
    if (image.complete && image.naturalWidth === 0) showPlaceholder();
});
