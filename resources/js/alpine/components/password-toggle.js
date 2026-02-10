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

// Document-level delegation for .password-toggle-btn without x-data
document.querySelectorAll('.password-toggle-btn').forEach(function(btn) {
    if (btn.closest('[x-data]')) return;
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var fieldId = this.getAttribute('data-field-id');
        if (!fieldId) return;
        var field = document.getElementById(fieldId);
        var icon = document.getElementById(fieldId + '-eye');
        if (!field) return;
        if (field.type === 'password') { field.type = 'text'; if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } }
        else { field.type = 'password'; if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } }
    });
});
window.togglePasswordVisibility = function(fieldId) {
    var field = document.getElementById(fieldId);
    var icon = document.getElementById(fieldId + '-eye');
    if (!field) return;
    if (field.type === 'password') { field.type = 'text'; if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } }
    else { field.type = 'password'; if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } }
};
