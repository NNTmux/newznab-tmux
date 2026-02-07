/**
 * Alpine.store('cart') - Global cart count state
 */
import Alpine from '@alpinejs/csp';

Alpine.store('cart', {
    count: 0,

    init() {
        const el = document.querySelector('.cart-count');
        if (el) this.count = parseInt(el.textContent) || 0;
    },

    setCount(n) {
        this.count = n;
    }
});
