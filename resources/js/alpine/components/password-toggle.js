/**
 * Alpine.data('passwordToggle') - Toggle password field visibility
 */
import Alpine from '@alpinejs/csp';

Alpine.data('passwordToggle', () => ({
    visible: false,

    toggle() {
        this.visible = !this.visible;
        const field = this.$refs.field;
        if (field) {
            field.type = this.visible ? 'text' : 'password';
        }
    },

    iconClass() {
        return this.visible ? 'fa-eye-slash' : 'fa-eye';
    }
}));
