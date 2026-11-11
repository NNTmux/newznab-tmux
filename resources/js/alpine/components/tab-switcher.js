/**
 * Alpine.data('tabSwitcher') - Generic tab switching
 * Alpine.data('seasonSwitcher') - TV series season tabs
 * Alpine.data('profileTabs') - Profile page tabs with Chart.js support
 */
import Alpine from '@alpinejs/csp';

/**
 * Document-level delegation for tab triggers and profile tabs without x-data.
 */
(function() {
    // Generic tab triggers (data-tab-trigger)
    document.querySelectorAll('[data-tab-trigger]').forEach(function(trigger) {
        if (trigger.closest('[x-data]')) return;
        trigger.addEventListener('click', function(ev) {
            ev.preventDefault();
            var tabId = this.getAttribute('data-tab-trigger');
            document.querySelectorAll('.tab-content').forEach(function(t) { t.style.display = 'none'; });
            var sel = document.getElementById(tabId);
            if (sel) sel.style.display = 'block';
            document.querySelectorAll('[data-tab-trigger]').forEach(function(t) { t.classList.remove('active', 'border-primary-500', 'text-link'); t.classList.add('border-transparent', 'text-gray-500'); });
            this.classList.remove('border-transparent', 'text-gray-500');
            this.classList.add('active', 'border-primary-500', 'text-link');
        });
    });

    // Profile tabs (.tab-link)
    var links = document.querySelectorAll('.tab-link');
    var contents = document.querySelectorAll('.tab-content');
    if (links.length && contents.length && !links[0].closest('[x-data]')) {
        var chartsInited = false;
        links.forEach(function(link) {
            link.addEventListener('click', function(ev) {
                ev.preventDefault();
                var targetId = this.getAttribute('href').substring(1);
                links.forEach(function(l) { l.classList.remove('bg-primary-50', 'text-link', 'font-medium'); l.classList.add('text-gray-700', 'dark:text-gray-300'); });
                this.classList.add('bg-primary-50', 'text-link', 'font-medium');
                this.classList.remove('text-gray-700', 'dark:text-gray-300');
                contents.forEach(function(c) { c.style.display = 'none'; });
                var target = document.getElementById(targetId);
                if (target) { target.style.display = 'block'; if (targetId === 'api' && !chartsInited) { chartsInited = true; var att = 0; var check = setInterval(function() { att++; if (typeof Chart !== 'undefined') { clearInterval(check); if (typeof initializeProfileCharts === 'function') initializeProfileCharts(); } else if (att >= 20) clearInterval(check); }, 100); } }
                history.pushState(null, null, '#' + targetId);
            });
        });
        var hash = window.location.hash.substring(1);
        if (hash && document.getElementById(hash)) {
            var link = document.querySelector('a[href="#' + hash + '"]');
            if (link) link.click();
        } else { contents.forEach(function(c, i) { if (i !== 0) c.style.display = 'none'; }); }
    }
})();
