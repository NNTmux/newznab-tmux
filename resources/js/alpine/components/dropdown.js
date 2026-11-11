/**
 * Alpine.data('dropdown') - Header navigation dropdown menus
 * Supports click toggle, hover keep-open, and click-outside close.
 */
import Alpine from '@alpinejs/csp';

/**
 * Document-level delegation for .dropdown-container and .submenu-container
 * elements that don't have x-data attributes yet.
 */
(function() {
    const disclosures = [];

    function register(container, toggleSelector, menuSelector, index, nested = false) {
        if (container.hasAttribute('x-data')) return;
        const toggle = container.querySelector(toggleSelector);
        const menu = container.querySelector(menuSelector);
        if (!toggle || !menu) return;
        let closeTimeout;
        menu.id ||= `navigation-disclosure-${index}`;
        toggle.setAttribute('aria-controls', menu.id);

        function setOpen(open) {
            clearTimeout(closeTimeout);
            menu.style.display = open ? 'block' : 'none';
            toggle.setAttribute('aria-expanded', String(open));
            if (!open) {
                disclosures.filter(item => menu.contains(item.container)).forEach(item => item.setOpen(false));
            }
        }

        setOpen(false);
        disclosures.push({ container, setOpen });
        toggle.addEventListener('click', event => {
            event.preventDefault();
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            disclosures.filter(item => !item.container.contains(container)).forEach(item => item.setOpen(false));
            setOpen(open);
        });
        container.addEventListener('keydown', event => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                event.preventDefault();
                event.stopPropagation();
                setOpen(false);
                toggle.focus();
            } else if (event.target === toggle && event.key === (nested ? 'ArrowRight' : 'ArrowDown')) {
                event.preventDefault();
                setOpen(true);
                menu.querySelector('a[href], button:not(:disabled)')?.focus();
            }
        });
        container.addEventListener('focusout', event => {
            if (!container.contains(event.relatedTarget)) setOpen(false);
        });
        container.addEventListener('mouseenter', () => {
            clearTimeout(closeTimeout);
            if (nested) setOpen(true);
        });
        container.addEventListener('mouseleave', () => {
            closeTimeout = setTimeout(() => {
                if (!container.contains(document.activeElement)) setOpen(false);
            }, 300);
        });
    }

    document.querySelectorAll('.dropdown-container').forEach((container, index) => {
        register(container, '.dropdown-toggle', '.dropdown-menu', `menu-${index}`);
    });
    document.querySelectorAll('.submenu-container').forEach((container, index) => {
        register(container, '.submenu-toggle', '.submenu', `submenu-${index}`, true);
    });
    document.addEventListener('click', event => {
        disclosures.filter(item => !item.container.contains(event.target)).forEach(item => item.setOpen(false));
    });
})();
