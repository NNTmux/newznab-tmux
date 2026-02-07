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
