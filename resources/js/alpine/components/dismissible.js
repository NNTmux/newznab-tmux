/**
 * Alpine.data('dismissible') - Dismissible alert/flash message
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

// Period filter with custom date range toggle
Alpine.data('periodFilter', (initialShowCustom) => ({
    showCustom: initialShowCustom || false,

    onPeriodChange() {
        var select = this.$refs.periodForm ? this.$refs.periodForm.querySelector('select[name="period"]') : null;
        if (!select) return;
        if (select.value === 'custom') {
            this.showCustom = true;
        } else {
            this.showCustom = false;
            this.$refs.periodForm.submit();
        }
    }
}));

