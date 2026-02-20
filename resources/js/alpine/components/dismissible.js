/**
 * Alpine.data('dismissible') - Dismissible alert/flash message
 * Alpine.data('commentDeleteModal') - Comment delete confirmation modal
 * Alpine.data('categoryDeleteModal') - Category delete confirmation modal
 * Alpine.data('periodFilter') - Date period filter with custom range toggle
 */
import Alpine from '@alpinejs/csp';

// Simple dismissible alert (flash messages, banners)
Alpine.data('dismissible', () => ({
    show: true,

    dismiss() {
        this.show = false;
    }
}));

// Comment delete confirmation modal
Alpine.data('commentDeleteModal', () => ({
    open: false,
    commentId: null,
    commentText: '',

    init() {
        this._baseUrl = this.$el.dataset.deleteUrl || '';
        // Listen for delete button clicks via delegation (CSP-safe, no $dispatch with inline objects)
        var self = this;
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.comment-delete-btn');
            if (btn) {
                self.openModal(btn.dataset.commentId, btn.dataset.commentText);
            }
        });
    },

    openModal(id, text) {
        this.commentId = id;
        this.commentText = text;
        this.open = true;
        var form = this.$refs.deleteForm;
        if (form) {
            form.action = this._baseUrl + '?id=' + id;
        }
    },

    close() {
        this.open = false;
        this.commentId = null;
        this.commentText = '';
    }
}));

// Category delete confirmation modal
Alpine.data('categoryDeleteModal', () => ({
    open: false,
    categoryId: null,

    init() {
        this._baseUrl = this.$el.dataset.deleteUrl || '';
        // Listen for delete button clicks via delegation (CSP-safe, no $dispatch with inline objects)
        var self = this;
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.category-delete-btn');
            if (btn) {
                self.openModal(btn.dataset.categoryId);
            }
        });
    },

    openModal(id) {
        this.categoryId = id;
        this.open = true;
    },

    close() {
        this.open = false;
        this.categoryId = null;
    },

    deleteUrl() {
        return this._baseUrl + '?id=' + this.categoryId;
    }
}));

// Period filter with custom date range toggle
Alpine.data('periodFilter', (initialShowCustom) => ({
    showCustom: initialShowCustom || false,

    onPeriodChange(e) {
        if (e.target.value === 'custom') {
            this.showCustom = true;
        } else {
            this.showCustom = false;
            this.$refs.periodForm.submit();
        }
    }
}));

