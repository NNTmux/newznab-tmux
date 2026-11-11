/**
 * Alpine.data('searchAutocomplete') - Search input with autocomplete dropdown
 */
import Alpine from '@alpinejs/csp';

// Sort dropdown
Alpine.data('sortDropdown', () => ({
    open: false,

    toggle() { this.open = !this.open; },
    close() { this.open = false; },
    chevronClass() { return this.open ? 'rotate-180' : ''; }
}));

/**
 * Document-level delegation for search autocomplete
 * without x-data attributes.
 */
(function() {
    function initAutocomplete(inputId, dropdownId, formId, itemClass, maxItems) {
        var input = document.getElementById(inputId);
        var dropdown = document.getElementById(dropdownId);
        var form = document.getElementById(formId);
        if (!input || !dropdown) return;
        if (input.closest('[x-data]')) return;

        var debounceTimer, currentIndex = -1, suggestions = [];

        // Combobox semantics so screen readers announce the suggestion list and the highlighted option.
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', dropdownId);
        dropdown.setAttribute('role', 'listbox');
        if (!dropdown.hasAttribute('aria-label')) dropdown.setAttribute('aria-label', 'Search suggestions');

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var q = this.value.trim();
            if (q.length < 2) { hideDropdown(); return; }
            debounceTimer = setTimeout(function() {
                fetch('/api/search/autocomplete?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.suggestions && data.suggestions.length > 0) {
                            suggestions = data.suggestions;
                            currentIndex = -1;
                            renderDropdown(suggestions, q);
                        } else hideDropdown();
                    }).catch(function() { hideDropdown(); });
            }, 200);
        });

        input.addEventListener('keydown', function(ev) {
            if (dropdown.classList.contains('hidden')) return;
            var items = dropdown.querySelectorAll('.' + itemClass);
            if (ev.key === 'ArrowDown') { ev.preventDefault(); currentIndex = Math.min(currentIndex + 1, items.length - 1); updateSelection(items); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); currentIndex = Math.max(currentIndex - 1, 0); updateSelection(items); }
            else if (ev.key === 'Enter' && currentIndex >= 0 && items[currentIndex]) { ev.preventDefault(); input.value = suggestions[currentIndex]; hideDropdown(); if (form) form.submit(); }
            else if (ev.key === 'Escape') hideDropdown();
        });

        document.addEventListener('click', function(ev) {
            if (!input.contains(ev.target) && !dropdown.contains(ev.target)) hideDropdown();
        });

        function renderDropdown(items, query) {
            currentIndex = -1;
            var escapeRegex = function(str) { return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); };
            var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
            var limit = maxItems || 10;
            var sizeClass = itemClass === 'header-autocomplete-item' ? 'px-3 py-2 text-sm' : 'px-4 py-2';
            var iconSize = itemClass === 'header-autocomplete-item' ? 'text-xs' : '';
            // Suggestions are Usenet release names: build nodes with textContent so they never become markup.
            dropdown.replaceChildren();
            items.slice(0, limit).forEach(function(item, i) {
                var row = document.createElement('div');
                row.className = itemClass + ' ' + sizeClass + ' cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-100';
                row.dataset.index = String(i);
                row.id = dropdownId + '-option-' + i;
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', 'false');
                var icon = document.createElement('i');
                icon.setAttribute('aria-hidden', 'true');
                icon.className = ('fas fa-search text-gray-400 mr-2 ' + iconSize).trim();
                row.appendChild(icon);
                String(item).split(regex).forEach(function(part, partIndex) {
                    if (part === '') return;
                    if (partIndex % 2 === 1) {
                        var mark = document.createElement('mark');
                        mark.className = 'bg-warning-200 dark:bg-warning-700 px-0.5 rounded';
                        mark.textContent = part;
                        row.appendChild(mark);
                    } else {
                        row.appendChild(document.createTextNode(part));
                    }
                });
                dropdown.appendChild(row);
            });
            dropdown.querySelectorAll('.' + itemClass).forEach(function(el, i) {
                el.addEventListener('click', function() { input.value = suggestions[i]; hideDropdown(); if (form) form.submit(); });
                el.addEventListener('mouseenter', function() { currentIndex = i; updateSelection(dropdown.querySelectorAll('.' + itemClass)); });
            });
            dropdown.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
            input.removeAttribute('aria-activedescendant');
        }

        function hideDropdown() {
            dropdown.classList.add('hidden'); dropdown.innerHTML = ''; suggestions = []; currentIndex = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }
        function updateSelection(items) {
            items.forEach(function(el, i) {
                var selected = i === currentIndex;
                el.classList.toggle('bg-primary-100', selected);
                el.classList.toggle('dark:bg-primary-900', selected);
                el.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            if (currentIndex >= 0 && items[currentIndex]) input.setAttribute('aria-activedescendant', items[currentIndex].id);
            else input.removeAttribute('aria-activedescendant');
        }
    }

    initAutocomplete('header-search-input', 'header-autocomplete-dropdown', 'header-search-form', 'header-autocomplete-item', 8);
    initAutocomplete('mobile-search-input', 'mobile-autocomplete-dropdown', 'mobile-search-form-el', 'header-autocomplete-item', 8);
    initAutocomplete('search', 'autocomplete-dropdown', 'searchForm', 'autocomplete-item', 10);
    document.querySelectorAll('[data-autocomplete-input]').forEach(function(el) {
        var ddId = el.getAttribute('data-autocomplete-input');
        var formId = el.getAttribute('data-autocomplete-form');
        if (el.id && ddId) initAutocomplete(el.id, ddId, formId, 'autocomplete-suggestion', 10);
    });
})();
