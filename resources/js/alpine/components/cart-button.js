/**
 * Alpine.data('cartButton') - Add to cart button (browse/details pages)
 */
import Alpine from '@alpinejs/csp';

Alpine.data('cartButton', () => ({
    clicked: false,

    addToCart(guid) {
        if (!guid || this.clicked) return;
        this.clicked = true;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: guid })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.cartCount) Alpine.store('cart').setCount(data.cartCount);
                showToast('Added to cart successfully!', 'success');
            } else {
                showToast('Failed to add item to cart', 'error');
            }
            setTimeout(() => { this.clicked = false; }, 2000);
        })
        .catch(() => {
            showToast('An error occurred', 'error');
            this.clicked = false;
        });
    }
}));

// Standalone addToCart for backward compat
window.addToCart = function(guid) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    fetch('/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ id: guid })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showToast('Added to cart successfully!', 'success');
        else showToast('Failed to add item to cart', 'error');
    })
    .catch(() => showToast('An error occurred', 'error'));
};
