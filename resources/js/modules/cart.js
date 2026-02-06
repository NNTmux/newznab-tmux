/**
 * Cart functionality module
 * Extracted from csp-safe.js
 */

export function initCartFunctionality() {
    // Handle individual add to cart button clicks (for details page, browse page, etc.)
    document.addEventListener('click', function(e) {
        const cartBtn = e.target.closest('.add-to-cart');

        if (cartBtn) {
            e.preventDefault();

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
                    if (typeof showToast === 'function') {
                        showToast('Added to cart successfully!', 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast('Failed to add item to cart', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                if (typeof showToast === 'function') {
                    showToast('An error occurred', 'error');
                }
            });
        }
    });

    // Cart page specific functionality
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.cart-checkbox');

    if (checkAll && checkboxes.length > 0) {
        // Function to update the check-all checkbox state
        function updateCheckAllState() {
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            checkAll.checked = checkedCount === checkboxes.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }

        // Handle check-all checkbox change
        checkAll.addEventListener('change', function() {
            const isChecked = this.checked;
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        });

        // Handle individual checkbox changes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateCheckAllState);
        });

        // Initialize the check-all state on page load
        updateCheckAllState();

        // Download selected
        document.querySelectorAll('.nzb_multi_operations_download_cart').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                const selected = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                if (selected.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('Please select at least one item', 'error');
                    } else {
                        alert('Please select at least one item');
                    }
                    return;
                }

                // If only one release is selected, download it directly
                if (selected.length === 1) {
                    window.location.href = '/getnzb/' + selected[0];
                    if (typeof showToast === 'function') {
                        showToast('Downloading NZB...', 'success');
                    }
                } else {
                    // For multiple releases, download as zip
                    const guids = selected.join(',');
                    window.location.href = '/getnzb?id=' + encodeURIComponent(guids) + '&zip=1';
                    if (typeof showToast === 'function') {
                        showToast(`Downloading ${selected.length} NZBs as zip file...`, 'success');
                    }
                }
            });
        });

        // Delete selected
        document.querySelectorAll('.nzb_multi_operations_cartdelete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                const selected = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                if (selected.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('Please select at least one item', 'error');
                    } else {
                        alert('Please select at least one item');
                    }
                    return;
                }

                showConfirm({
                    title: 'Delete from Cart',
                    message: `Are you sure you want to delete ${selected.length} item${selected.length > 1 ? 's' : ''} from your cart?`,
                    type: 'danger',
                    confirmText: 'Delete',
                    onConfirm: function() {
                        // Delete via AJAX
                        fetch('/cart/delete/' + selected.join(','), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json',
                            }
                        })
                        .then(response => {
                            if (response.ok) {
                                if (typeof showToast === 'function') {
                                    showToast('Items deleted successfully', 'success');
                                }
                                setTimeout(() => window.location.reload(), 1000);
                            } else {
                                if (typeof showToast === 'function') {
                                    showToast('Failed to delete items', 'error');
                                } else {
                                    alert('Failed to delete items');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            if (typeof showToast === 'function') {
                                showToast('Failed to delete items', 'error');
                            } else {
                                alert('Failed to delete items');
                            }
                        });
                    }
                });
            });
        });

        // Individual delete confirmation
        document.querySelectorAll('.cart-delete-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const releaseName = this.getAttribute('data-release-name');
                const deleteUrl = this.getAttribute('data-delete-url');

                showConfirm({
                    title: 'Remove from Cart',
                    message: `Are you sure you want to remove "${releaseName}" from your cart?`,
                    type: 'warning',
                    confirmText: 'Remove',
                    onConfirm: function() {
                        if (typeof showToast === 'function') {
                            showToast('Removing item from cart...', 'info');
                        }

                        setTimeout(() => {
                            window.location.href = deleteUrl;
                        }, 500);
                    }
                });
            });
        });
    }

    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('chkSelectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.chkRelease');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    }

    // Multi-operations download
    const multiDownloadBtn = document.querySelector('.nzb_multi_operations_download');
    if (multiDownloadBtn) {
        multiDownloadBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one release', 'error');
                }
                return;
            }

            // If only one release is selected, download it directly
            if (selected.length === 1) {
                window.location.href = '/getnzb/' + selected[0];
                if (typeof showToast === 'function') {
                    showToast('Downloading NZB...', 'success');
                }
            } else {
                // For multiple releases, download as zip
                const guids = selected.join(',');
                window.location.href = '/getnzb?id=' + encodeURIComponent(guids) + '&zip=1';
                if (typeof showToast === 'function') {
                    showToast(`Downloading ${selected.length} NZBs as zip file...`, 'success');
                }
            }
        });
    }

    // Multi-operations cart
    const multiCartBtn = document.querySelector('.nzb_multi_operations_cart');
    if (multiCartBtn) {
        multiCartBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one release', 'error');
                }
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: selected.join(',') })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast(data.message || `Added ${selected.length} item${selected.length > 1 ? 's' : ''} to cart`, 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast('Failed to add items to cart', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                if (typeof showToast === 'function') {
                    showToast('An error occurred', 'error');
                }
            });
        });
    }

    // Multi-operations delete (Admin only)
    const multiDeleteBtn = document.querySelector('.nzb_multi_operations_delete');
    if (multiDeleteBtn) {
        console.log('Delete button found and event listener being attached');
        multiDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Delete button clicked');

            const checkboxes = document.querySelectorAll('.chkRelease:checked');
            const selected = Array.from(checkboxes);

            console.log('Selected releases:', selected.length);

            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one release to delete', 'error');
                } else {
                    alert('Please select at least one release to delete');
                }
                return;
            }

            const confirmMessage = `Are you sure you want to delete ${selected.length} release${selected.length > 1 ? 's' : ''}? This action cannot be undone.`;

            // Use styled confirmation modal
            showConfirm({
                title: 'Delete Releases',
                message: confirmMessage,
                type: 'danger',
                confirmText: 'Delete',
                onConfirm: function() {
                console.log('User confirmed deletion');

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (!csrfToken) {
                    console.error('CSRF token not found');
                    if (typeof showToast === 'function') {
                        showToast('Security token not found. Please refresh the page.', 'error');
                    } else {
                        alert('Security token not found. Please refresh the page.');
                    }
                    return;
                }

                let deletedCount = 0;
                let errorCount = 0;

                if (typeof showToast === 'function') {
                    showToast(`Deleting ${selected.length} release${selected.length > 1 ? 's' : ''}...`, 'info');
                }

                // Delete releases one by one
                const deletePromises = selected.map(checkbox => {
                    const guid = checkbox.value;
                    const row = checkbox.closest('tr');

                    console.log('Attempting to delete release:', guid);

                    return fetch('/admin/release-delete/' + guid, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => {
                        console.log('Delete response status:', response.status, 'for guid:', guid);
                        if (response.ok) {
                            deletedCount++;
                            // Fade out and remove the row
                            if (row) {
                                row.style.transition = 'opacity 0.3s';
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 300);
                            }
                            return true;
                        } else {
                            errorCount++;
                            console.error('Failed to delete release:', guid, 'Status:', response.status);
                            return response.text().then(text => {
                                console.error('Error response:', text);
                                return false;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting release:', guid, error);
                        errorCount++;
                        return false;
                    });
                });

                // Wait for all deletes to complete
                Promise.all(deletePromises).then(() => {
                    console.log('All delete operations completed. Deleted:', deletedCount, 'Errors:', errorCount);

                    // Uncheck the select all checkbox
                    const selectAllCheckbox = document.getElementById('chkSelectAll');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = false;
                    }

                    // Show final result
                    if (deletedCount > 0 && errorCount === 0) {
                        if (typeof showToast === 'function') {
                            showToast(`Successfully deleted ${deletedCount} release${deletedCount > 1 ? 's' : ''}`, 'success');
                        }
                    } else if (deletedCount > 0 && errorCount > 0) {
                        if (typeof showToast === 'function') {
                            showToast(`Deleted ${deletedCount} release${deletedCount > 1 ? 's' : ''}, ${errorCount} failed`, 'error');
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast('Failed to delete releases', 'error');
                        }
                    }

                    // Reload page if all items on current page were deleted
                    const remainingRows = document.querySelectorAll('.chkRelease');
                    if (remainingRows.length === 0) {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                });
                }
            });
        });
    }
}

// Add to Cart function (standalone for backward compatibility)
window.addToCart = function(guid) {
    if (!guid) {
        console.error('No GUID provided to addToCart');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: guid })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Added to cart successfully!', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Failed to add item to cart', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        if (typeof showToast === 'function') {
            showToast('An error occurred', 'error');
        }
    });
};
