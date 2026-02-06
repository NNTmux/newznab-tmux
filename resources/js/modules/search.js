/**
 * Search and autocomplete module
 * Extracted from csp-safe.js
 */

export function initSearchAutocomplete() {
    // Initialize header autocomplete (desktop and mobile)
    initAutocompleteInput('header-search-input', 'header-autocomplete-dropdown', 'header-search-form', 'header-autocomplete-item');
    initAutocompleteInput('mobile-search-input', 'mobile-autocomplete-dropdown', 'mobile-search-form-el', 'header-autocomplete-item');

    // Initialize main search page autocomplete
    initAutocompleteInput('search', 'autocomplete-dropdown', 'searchForm', 'autocomplete-item');

    // Initialize any dynamic autocomplete inputs (from Blade components)
    document.querySelectorAll('[data-autocomplete-input]').forEach(function(input) {
        const dropdownId = input.getAttribute('data-autocomplete-input');
        const formId = input.getAttribute('data-autocomplete-form');
        const inputId = input.id;
        if (inputId && dropdownId) {
            initAutocompleteInput(inputId, dropdownId, formId, 'autocomplete-suggestion');
        }
    });

    // Apply CSP-safe styles to modals (replaces inline styles)
    initModalStyles();
}

/**
 * Initialize autocomplete for a specific input element
 * @param {string} inputId - ID of the search input
 * @param {string} dropdownId - ID of the dropdown container
 * @param {string} formId - ID of the form to submit
 * @param {string} itemClass - CSS class for dropdown items
 */
export function initAutocompleteInput(inputId, dropdownId, formId, itemClass) {
    const searchInput = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const form = document.getElementById(formId);

    if (!searchInput || !dropdown) return;

    let debounceTimer;
    let currentIndex = -1;
    let suggestions = [];

    // Input handler with debounce
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();

        if (query.length < 2) {
            hideDropdown();
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(`/api/search/autocomplete?q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success && data.suggestions && data.suggestions.length > 0) {
                    suggestions = data.suggestions;
                    renderDropdown(suggestions, query);
                } else {
                    hideDropdown();
                }
            } catch (error) {
                console.error('Autocomplete error:', error);
                hideDropdown();
            }
        }, 200);
    });

    // Keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        if (!dropdown.classList.contains('hidden')) {
            const items = dropdown.querySelectorAll('.' + itemClass);

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    currentIndex = Math.min(currentIndex + 1, items.length - 1);
                    updateSelection(items);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    currentIndex = Math.max(currentIndex - 1, 0);
                    updateSelection(items);
                    break;
                case 'Enter':
                    if (currentIndex >= 0 && items[currentIndex]) {
                        e.preventDefault();
                        searchInput.value = suggestions[currentIndex];
                        hideDropdown();
                        if (form) form.submit();
                    }
                    break;
                case 'Escape':
                    hideDropdown();
                    break;
            }
        }
    });

    // Click outside to close
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            hideDropdown();
        }
    });

    function renderDropdown(items, query) {
        currentIndex = -1;
        const escapeRegex = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');

        const maxItems = itemClass === 'header-autocomplete-item' ? 8 : 10;
        const sizeClass = itemClass === 'header-autocomplete-item' ? 'px-3 py-2 text-sm' : 'px-4 py-2';
        const iconSizeClass = itemClass === 'header-autocomplete-item' ? 'text-xs' : '';

        dropdown.innerHTML = items.slice(0, maxItems).map((item, index) => {
            const highlighted = item.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-700 px-0.5 rounded">$1</mark>');
            return `
                <div class="${itemClass} ${sizeClass} cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-100"
                     data-index="${index}">
                    <i class="fas fa-search text-gray-400 mr-2 ${iconSizeClass}"></i>
                    ${highlighted}
                </div>
            `;
        }).join('');

        // Add click handlers
        dropdown.querySelectorAll('.' + itemClass).forEach((item, index) => {
            item.addEventListener('click', function() {
                searchInput.value = suggestions[index];
                hideDropdown();
                if (form) form.submit();
            });
            item.addEventListener('mouseenter', function() {
                currentIndex = index;
                updateSelection(dropdown.querySelectorAll('.' + itemClass));
            });
        });

        dropdown.classList.remove('hidden');
    }

    function hideDropdown() {
        dropdown.classList.add('hidden');
        dropdown.innerHTML = '';
        suggestions = [];
        currentIndex = -1;
    }

    function updateSelection(items) {
        items.forEach((item, index) => {
            if (index === currentIndex) {
                item.classList.add('bg-blue-100', 'dark:bg-blue-900');
            } else {
                item.classList.remove('bg-blue-100', 'dark:bg-blue-900');
            }
        });
    }
}

/**
 * Initialize sort dropdown functionality
 * Handles the custom dropdown for sorting releases on browse/search pages
 */
export function initSortDropdowns() {
    document.querySelectorAll('.sort-dropdown').forEach(function(dropdown) {
        var toggle = dropdown.querySelector('.sort-dropdown-toggle');
        var menu = dropdown.querySelector('.sort-dropdown-menu');
        var chevron = dropdown.querySelector('.sort-dropdown-chevron');

        if (!toggle || !menu) return;

        // Skip if already initialized
        if (toggle.hasAttribute('data-sort-initialized')) return;
        toggle.setAttribute('data-sort-initialized', 'true');

        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = !menu.classList.contains('hidden');

            // Close all other dropdowns first
            document.querySelectorAll('.sort-dropdown-menu').forEach(function(m) {
                m.classList.add('hidden');
            });
            document.querySelectorAll('.sort-dropdown-chevron').forEach(function(c) {
                c.classList.remove('rotate-180');
            });

            if (!isOpen) {
                menu.classList.remove('hidden');
                if (chevron) chevron.classList.add('rotate-180');
            }
        });
    });

    // Close dropdowns when clicking outside (single global listener)
    if (!window._sortDropdownOutsideListenerAdded) {
        window._sortDropdownOutsideListenerAdded = true;
        document.addEventListener('click', function(e) {
            var isInsideDropdown = e.target.closest('.sort-dropdown');
            if (!isInsideDropdown) {
                document.querySelectorAll('.sort-dropdown-menu').forEach(function(m) {
                    m.classList.add('hidden');
                });
                document.querySelectorAll('.sort-dropdown-chevron').forEach(function(c) {
                    c.classList.remove('rotate-180');
                });
            }
        });
    }
}

// Initialize sort dropdowns on DOMContentLoaded
document.addEventListener('DOMContentLoaded', initSortDropdowns);
