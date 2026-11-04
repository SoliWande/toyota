const menu = document.querySelector('[data-competition-menu]');
if (menu) {
    const trigger = menu.querySelector('summary');
    const panel = menu.querySelector('nav');
    const sync = () => {
        trigger.setAttribute('aria-expanded', String(menu.open));
        trigger.setAttribute('aria-label', menu.open ? 'Đóng menu điều hướng' : 'Mở menu điều hướng');
        document.body.classList.toggle('wc-menu-open', menu.open);
    };
    menu.addEventListener('toggle', sync);
    panel.addEventListener('click', event => {
        if (event.target.closest('a')) menu.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            trigger.focus();
        }
        if (event.key === 'Tab' && menu.open) {
            const links = [trigger, ...panel.querySelectorAll('a, button')];
            const first = links[0], last = links[links.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    window.matchMedia('(min-width: 1200px)').addEventListener('change', event => {
        if (event.matches) menu.open = false;
    });
    sync();
}
