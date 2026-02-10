/**
 * Alpine.data('searchAutocomplete') - Search input with autocomplete dropdown
 */
import Alpine from '@alpinejs/csp';

Alpine.data('searchAutocomplete', (inputId, dropdownId, formId, itemClass, maxItems) => ({
    query: '',
    suggestions: [],
    currentIndex: -1,
    open: false,
    _debounceTimer: null,

    init() {
        // If IDs passed as data attributes, use those
        this._inputId = inputId || this.$el.dataset.inputId;
        this._dropdownId = dropdownId || this.$el.dataset.dropdownId;
        this._formId = formId || this.$el.dataset.formId;
        this._itemClass = itemClass || this.$el.dataset.itemClass || 'autocomplete-item';
        this._maxItems = maxItems || parseInt(this.$el.dataset.maxItems) || 10;
    },

    onInput() {
        clearTimeout(this._debounceTimer);
        const q = this.query.trim();
        if (q.length < 2) { this.hide(); return; }

        this._debounceTimer = setTimeout(() => {
            fetch('/api/search/autocomplete?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.suggestions && data.suggestions.length > 0) {
                        this.suggestions = data.suggestions.slice(0, this._maxItems);
                        this.currentIndex = -1;
                        this.open = true;
                    } else {
                        this.hide();
                    }
                })
                .catch(() => this.hide());
        }, 200);
    },

    onKeydown(e) {
        if (!this.open) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.currentIndex = Math.min(this.currentIndex + 1, this.suggestions.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.currentIndex = Math.max(this.currentIndex - 1, 0);
        } else if (e.key === 'Enter' && this.currentIndex >= 0) {
            e.preventDefault();
            this.select(this.currentIndex);
        } else if (e.key === 'Escape') {
            this.hide();
        }
    },

    select(index) {
        this.query = this.suggestions[index];
        this.hide();
        // Submit form
        const form = this.$refs.form || document.getElementById(this._formId);
        if (form) form.submit();
    },

    hover(index) {
        this.currentIndex = index;
    },

    hide() {
        this.open = false;
        this.suggestions = [];
        this.currentIndex = -1;
    },

    isSelected(index) {
        return this.currentIndex === index;
    }
}));

// Sort dropdown
Alpine.data('sortDropdown', () => ({
    open: false,

    toggle() { this.open = !this.open; },
    close() { this.open = false; }
}));

/**
 * Document-level delegation for search autocomplete and sort dropdowns
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
            dropdown.innerHTML = items.slice(0, limit).map(function(item, i) {
                var hl = item.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-700 px-0.5 rounded">$1</mark>');
                return '<div class="' + itemClass + ' ' + sizeClass + ' cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-100" data-index="' + i + '"><i class="fas fa-search text-gray-400 mr-2 ' + iconSize + '"></i>' + hl + '</div>';
            }).join('');
            dropdown.querySelectorAll('.' + itemClass).forEach(function(el, i) {
                el.addEventListener('click', function() { input.value = suggestions[i]; hideDropdown(); if (form) form.submit(); });
                el.addEventListener('mouseenter', function() { currentIndex = i; updateSelection(dropdown.querySelectorAll('.' + itemClass)); });
            });
            dropdown.classList.remove('hidden');
        }

        function hideDropdown() { dropdown.classList.add('hidden'); dropdown.innerHTML = ''; suggestions = []; currentIndex = -1; }
        function updateSelection(items) { items.forEach(function(el, i) { if (i === currentIndex) el.classList.add('bg-blue-100', 'dark:bg-blue-900'); else el.classList.remove('bg-blue-100', 'dark:bg-blue-900'); }); }
    }

    initAutocomplete('header-search-input', 'header-autocomplete-dropdown', 'header-search-form', 'header-autocomplete-item', 8);
    initAutocomplete('mobile-search-input', 'mobile-autocomplete-dropdown', 'mobile-search-form-el', 'header-autocomplete-item', 8);
    initAutocomplete('search', 'autocomplete-dropdown', 'searchForm', 'autocomplete-item', 10);
    document.querySelectorAll('[data-autocomplete-input]').forEach(function(el) {
        var ddId = el.getAttribute('data-autocomplete-input');
        var formId = el.getAttribute('data-autocomplete-form');
        if (el.id && ddId) initAutocomplete(el.id, ddId, formId, 'autocomplete-suggestion', 10);
    });

    // Sort dropdowns delegation
    document.querySelectorAll('.sort-dropdown').forEach(function(dd) {
        if (dd.closest('[x-data]')) return;
        var toggle = dd.querySelector('.sort-dropdown-toggle');
        var menu = dd.querySelector('.sort-dropdown-menu');
        var chevron = dd.querySelector('.sort-dropdown-chevron');
        if (!toggle || !menu) return;
        if (toggle.hasAttribute('data-sort-initialized')) return;
        toggle.setAttribute('data-sort-initialized', 'true');
        toggle.addEventListener('click', function(ev) {
            ev.preventDefault(); ev.stopPropagation();
            var isOpen = !menu.classList.contains('hidden');
            document.querySelectorAll('.sort-dropdown-menu').forEach(function(m) { m.classList.add('hidden'); });
            document.querySelectorAll('.sort-dropdown-chevron').forEach(function(c) { c.classList.remove('rotate-180'); });
            if (!isOpen) { menu.classList.remove('hidden'); if (chevron) chevron.classList.add('rotate-180'); }
        });
    });
    if (!window._sortDropdownOutsideListenerAdded) {
        window._sortDropdownOutsideListenerAdded = true;
        document.addEventListener('click', function(ev) {
            if (!ev.target.closest('.sort-dropdown')) {
                document.querySelectorAll('.sort-dropdown-menu').forEach(function(m) { m.classList.add('hidden'); });
                document.querySelectorAll('.sort-dropdown-chevron').forEach(function(c) { c.classList.remove('rotate-180'); });
            }
        });
    }
})();
