/**
 * Alpine.data('sidebarToggle') - Sidebar section collapse/expand
 */
import Alpine from '@alpinejs/csp';

// Document-level delegation for .sidebar-toggle elements without x-data
document.querySelectorAll('.sidebar-toggle').forEach(function(toggle) {
    if (toggle.closest('[x-data]')) return;
    var targetId = toggle.getAttribute('data-target');
    var submenu = document.getElementById(targetId);
    if (!submenu) return;
    toggle.setAttribute('aria-controls', targetId);
    toggle.setAttribute('aria-expanded', String(!submenu.classList.contains('hidden')));
    toggle.addEventListener('click', function() {
        var targetId = this.getAttribute('data-target');
        var submenu = document.getElementById(targetId);
        var chevron = this.querySelector('.fa-chevron-down');
        if (submenu) {
            submenu.classList.toggle('hidden');
            this.setAttribute('aria-expanded', String(!submenu.classList.contains('hidden')));
        }
        if (chevron) chevron.classList.toggle('rotate-180');
    });
});
