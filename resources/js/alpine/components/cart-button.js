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

// Document-level delegation for .add-to-cart and .download-nzb buttons
document.addEventListener('click', function(e) {
    // Add to cart
    var cartBtn = e.target.closest('.add-to-cart');
    if (cartBtn) {
        e.preventDefault();
        var guid = cartBtn.dataset.guid;
        var iconEl = cartBtn.querySelector('.icon_cart');
        if (!guid) return;
        if (iconEl && iconEl.classList.contains('icon_cart_clicked')) return;

        var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id: guid })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (iconEl) {
                    iconEl.classList.remove('fa-shopping-basket', 'fa-shopping-cart');
                    iconEl.classList.add('fa-check', 'icon_cart_clicked');
                    setTimeout(function() {
                        iconEl.classList.remove('fa-check', 'icon_cart_clicked');
                        iconEl.classList.add('fa-shopping-basket');
                    }, 2000);
                }
                if (data.cartCount) {
                    var countEl = document.querySelector('.cart-count');
                    if (countEl) countEl.textContent = data.cartCount;
                    Alpine.store('cart').setCount(data.cartCount);
                }
                showToast('Added to cart successfully!', 'success');
            } else {
                showToast('Failed to add item to cart', 'error');
            }
        })
        .catch(function() { showToast('An error occurred', 'error'); });
        return;
    }

    // Download NZB toast
    if (e.target.closest('.download-nzb')) {
        showToast('Downloading NZB...', 'success');
    }
});
