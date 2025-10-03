{{-- Cart functionality JavaScript --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle add to cart button clicks
    document.addEventListener('click', function(e) {
        // Check if clicked element or its parent is a cart button
        const cartBtn = e.target.closest('.add-to-cart');

        if (cartBtn) {
            e.preventDefault();

            // Get the GUID from the button's data attribute
            const guid = cartBtn.dataset.guid;
            const iconElement = cartBtn.querySelector('.icon_cart');

            if (!guid) {
                console.error('No GUID found for cart item');
                return;
            }

            // Prevent double-clicking
            if (iconElement && iconElement.classList.contains('icon_cart_clicked')) {
                return;
            }

            // Send AJAX request to add item to cart
            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: guid })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Visual feedback
                    if (iconElement) {
                        iconElement.classList.remove('fa-shopping-basket');
                        iconElement.classList.add('fa-check', 'icon_cart_clicked');

                        // Reset icon after 2 seconds
                        setTimeout(() => {
                            iconElement.classList.remove('fa-check', 'icon_cart_clicked');
                            iconElement.classList.add('fa-shopping-basket');
                        }, 2000);
                    }

                    // Update cart count if element exists
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount && data.cartCount) {
                        cartCount.textContent = data.cartCount;
                    }

                    // Show success notification
                    window.showToast('Added to cart successfully!', 'success');
                } else {
                    window.showToast('Failed to add item to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                window.showToast('An error occurred', 'error');
            });
        }
    });
});
</script>

