import Alpine from '@alpinejs/csp';

Alpine.data('adminUserPasskeys', () => ({
    showDangerZone: false,
    wipeConfirm: '',

    toggleDangerZone() {
        this.showDangerZone = !this.showDangerZone;
    },

    canWipe() {
        return this.wipeConfirm === 'WIPE';
    },
}));
