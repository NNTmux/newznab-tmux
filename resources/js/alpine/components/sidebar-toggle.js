/**
 * Alpine.data('sidebarToggle') - Sidebar section collapse/expand
 */
import Alpine from '@alpinejs/csp';

Alpine.data('sidebarToggle', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    }
}));

// Document-level delegation for .sidebar-toggle elements without x-data
document.querySelectorAll('.sidebar-toggle').forEach(function(toggle) {
    if (toggle.closest('[x-data]')) return;
    toggle.addEventListener('click', function() {
        var targetId = this.getAttribute('data-target');
        var submenu = document.getElementById(targetId);
        var chevron = this.querySelector('.fa-chevron-down');
        if (submenu) submenu.classList.toggle('hidden');
        if (chevron) chevron.classList.toggle('rotate-180');
    });
});
