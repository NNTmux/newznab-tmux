/**
 * Alpine.data('cartPage') - Cart page with select-all, download, delete
 */
import Alpine from '@alpinejs/csp';

Alpine.data('cartPage', () => ({
    allChecked: false,

    init() {
        this._updateCheckAll();
    },

    toggleAll() {
        const boxes = this.$el.querySelectorAll('.cart-checkbox');
        boxes.forEach(cb => { cb.checked = this.allChecked; });
    },

    onCheckboxChange() {
        this._updateCheckAll();
    },

    _updateCheckAll() {
        const boxes = this.$el.querySelectorAll('.cart-checkbox');
        const checked = this.$el.querySelectorAll('.cart-checkbox:checked');
        this.allChecked = boxes.length > 0 && checked.length === boxes.length;
    },

    _getSelected() {
        return Array.from(this.$el.querySelectorAll('.cart-checkbox:checked')).map(cb => cb.value);
    },

    downloadSelected() {
        const selected = this._getSelected();
        if (selected.length === 0) { showToast('Please select at least one item', 'error'); return; }
        if (selected.length === 1) {
            window.location.href = '/getnzb/' + selected[0];
        } else {
            window.location.href = '/getnzb?id=' + encodeURIComponent(selected.join(',')) + '&zip=1';
        }
        showToast('Downloading ' + selected.length + ' NZB' + (selected.length > 1 ? 's' : '') + '...', 'success');
    },

    deleteSelected() {
        const selected = this._getSelected();
        if (selected.length === 0) { showToast('Please select at least one item', 'error'); return; }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        showConfirm({
            title: 'Delete from Cart',
            message: 'Are you sure you want to delete ' + selected.length + ' item' + (selected.length > 1 ? 's' : '') + ' from your cart?',
            type: 'danger',
            confirmText: 'Delete',
            onConfirm: function() {
                fetch('/cart/delete/' + selected.join(','), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }
                })
                .then(r => {
                    if (r.ok) { showToast('Items deleted successfully', 'success'); setTimeout(() => window.location.reload(), 1000); }
                    else showToast('Failed to delete items', 'error');
                })
                .catch(() => showToast('Failed to delete items', 'error'));
            }
        });
    },

    deleteItem(url, name) {
        showConfirm({
            title: 'Remove from Cart',
            message: 'Are you sure you want to remove "' + name + '" from your cart?',
            type: 'warning',
            confirmText: 'Remove',
            onConfirm: function() {
                showToast('Removing item from cart...', 'info');
                setTimeout(() => { window.location.href = url; }, 500);
            }
        });
    }
}));

// Multi-operations for browse pages (select all releases, download, cart, delete)
Alpine.data('releaseMultiOps', () => ({
    allChecked: false,

    toggleAll() {
        const boxes = document.querySelectorAll('.chkRelease');
        boxes.forEach(cb => { cb.checked = this.allChecked; });
    },

    _getSelected() {
        return Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
    },

    downloadSelected() {
        const selected = this._getSelected();
        if (selected.length === 0) { showToast('Please select at least one release', 'error'); return; }
        if (selected.length === 1) {
            window.location.href = '/getnzb/' + selected[0];
        } else {
            window.location.href = '/getnzb?id=' + encodeURIComponent(selected.join(',')) + '&zip=1';
        }
        showToast('Downloading ' + selected.length + ' NZB' + (selected.length > 1 ? 's' : '') + '...', 'success');
    },

    addSelectedToCart() {
        const selected = this._getSelected();
        if (selected.length === 0) { showToast('Please select at least one release', 'error'); return; }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id: selected.join(',') })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) showToast(data.message || 'Added ' + selected.length + ' item(s) to cart', 'success');
            else showToast('Failed to add items to cart', 'error');
        })
        .catch(() => showToast('An error occurred', 'error'));
    },

    deleteSelected() {
        const selected = this._getSelected();
        const selectedEls = Array.from(document.querySelectorAll('.chkRelease:checked'));
        if (selected.length === 0) { showToast('Please select at least one release to delete', 'error'); return; }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        showConfirm({
            title: 'Delete Releases',
            message: 'Are you sure you want to delete ' + selected.length + ' release' + (selected.length > 1 ? 's' : '') + '? This action cannot be undone.',
            type: 'danger',
            confirmText: 'Delete',
            onConfirm: function() {
                showToast('Deleting ' + selected.length + ' release(s)...', 'info');
                let deletedCount = 0, errorCount = 0;

                const promises = selectedEls.map(checkbox => {
                    const guid = checkbox.value;
                    const row = checkbox.closest('tr');
                    return fetch('/admin/release-delete/' + guid, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' }
                    }).then(r => {
                        if (r.ok) { deletedCount++; if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); } }
                        else errorCount++;
                    }).catch(() => errorCount++);
                });

                Promise.all(promises).then(() => {
                    const selAll = document.getElementById('chkSelectAll');
                    if (selAll) selAll.checked = false;
                    if (deletedCount > 0 && errorCount === 0) showToast('Successfully deleted ' + deletedCount + ' release(s)', 'success');
                    else if (deletedCount > 0) showToast('Deleted ' + deletedCount + ', ' + errorCount + ' failed', 'error');
                    else showToast('Failed to delete releases', 'error');
                    if (document.querySelectorAll('.chkRelease').length === 0) setTimeout(() => window.location.reload(), 1500);
                });
            }
        });
    }
}));

// Document-level delegation for multi-ops and cart page buttons
(function() {
    document.addEventListener('click', function(e) {
        // Multi-operations: Download selected releases
        if (e.target.closest('.nzb_multi_operations_download')) {
            e.preventDefault();
            var selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(function(cb) { return cb.value; });
            if (selected.length === 0) { showToast('Please select at least one release', 'error'); return; }
            if (selected.length === 1) window.location.href = '/getnzb/' + selected[0];
            else window.location.href = '/getnzb?id=' + encodeURIComponent(selected.join(',')) + '&zip=1';
            showToast('Downloading ' + selected.length + ' NZB(s)...', 'success');
            return;
        }

        // Multi-operations: Cart selected releases
        if (e.target.closest('.nzb_multi_operations_cart')) {
            e.preventDefault();
            var selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(function(cb) { return cb.value; });
            if (selected.length === 0) { showToast('Please select at least one release', 'error'); return; }
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch('/cart/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id: selected.join(',') })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) showToast(data.message || 'Added ' + selected.length + ' item(s) to cart', 'success');
                else showToast('Failed to add items to cart', 'error');
            }).catch(function() { showToast('An error occurred', 'error'); });
            return;
        }

        // Multi-operations: Delete selected releases (admin)
        if (e.target.closest('.nzb_multi_operations_delete')) {
            e.preventDefault();
            var checkboxes = document.querySelectorAll('.chkRelease:checked');
            var selected = Array.from(checkboxes);
            if (selected.length === 0) { showToast('Please select at least one release to delete', 'error'); return; }
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            showConfirm({
                title: 'Delete Releases',
                message: 'Are you sure you want to delete ' + selected.length + ' release(s)? This action cannot be undone.',
                type: 'danger', confirmText: 'Delete',
                onConfirm: function() {
                    showToast('Deleting...', 'info');
                    var ok = 0, fail = 0;
                    Promise.all(selected.map(function(cb) {
                        var row = cb.closest('tr');
                        return fetch('/admin/release-delete/' + cb.value, {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' }
                        }).then(function(r) { if (r.ok) { ok++; if (row) { row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(function() { row.remove(); },300); } } else fail++; }).catch(function() { fail++; });
                    })).then(function() {
                        var sa = document.getElementById('chkSelectAll'); if (sa) sa.checked = false;
                        if (ok > 0 && fail === 0) showToast('Deleted ' + ok + ' release(s)', 'success');
                        else if (ok > 0) showToast('Deleted ' + ok + ', ' + fail + ' failed', 'error');
                        else showToast('Failed to delete releases', 'error');
                        if (!document.querySelectorAll('.chkRelease').length) setTimeout(function() { window.location.reload(); }, 1500);
                    });
                }
            });
            return;
        }

        // Cart page: Download selected
        if (e.target.closest('.nzb_multi_operations_download_cart')) {
            e.preventDefault();
            var selected = Array.from(document.querySelectorAll('.cart-checkbox:checked')).map(function(cb) { return cb.value; });
            if (selected.length === 0) { showToast('Please select at least one item', 'error'); return; }
            if (selected.length === 1) window.location.href = '/getnzb/' + selected[0];
            else window.location.href = '/getnzb?id=' + encodeURIComponent(selected.join(',')) + '&zip=1';
            showToast('Downloading...', 'success');
            return;
        }

        // Cart page: Delete selected
        if (e.target.closest('.nzb_multi_operations_cartdelete')) {
            e.preventDefault();
            var selected = Array.from(document.querySelectorAll('.cart-checkbox:checked')).map(function(cb) { return cb.value; });
            if (selected.length === 0) { showToast('Please select at least one item', 'error'); return; }
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            showConfirm({
                title: 'Delete from Cart',
                message: 'Are you sure you want to delete ' + selected.length + ' item(s) from your cart?',
                type: 'danger', confirmText: 'Delete',
                onConfirm: function() {
                    fetch('/cart/delete/' + selected.join(','), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' } })
                    .then(function(r) { if (r.ok) { showToast('Items deleted', 'success'); setTimeout(function() { window.location.reload(); }, 1000); } else showToast('Failed', 'error'); })
                    .catch(function() { showToast('Failed', 'error'); });
                }
            });
            return;
        }

        // Cart page: Individual delete
        var cartDeleteLink = e.target.closest('.cart-delete-link');
        if (cartDeleteLink) {
            e.preventDefault();
            e.stopPropagation();
            var name = cartDeleteLink.getAttribute('data-release-name');
            var url = cartDeleteLink.getAttribute('data-delete-url');
            showConfirm({
                title: 'Remove from Cart',
                message: 'Are you sure you want to remove "' + (name || 'this item') + '" from your cart?',
                type: 'warning', confirmText: 'Remove',
                onConfirm: function() { showToast('Removing...', 'info'); setTimeout(function() { window.location.href = url; }, 500); }
            });
            return;
        }
    });

    // Cart page: Check-all checkbox
    var checkAll = document.getElementById('check-all');
    var cartCheckboxes = document.querySelectorAll('.cart-checkbox');
    if (checkAll && cartCheckboxes.length > 0) {
        function updateState() {
            var checked = Array.from(cartCheckboxes).filter(function(cb) { return cb.checked; }).length;
            checkAll.checked = checked === cartCheckboxes.length;
            checkAll.indeterminate = checked > 0 && checked < cartCheckboxes.length;
        }
        checkAll.addEventListener('change', function() { cartCheckboxes.forEach(function(cb) { cb.checked = checkAll.checked; }); });
        cartCheckboxes.forEach(function(cb) { cb.addEventListener('change', updateState); });
        updateState();
    }

    // Select-all for chkRelease (browse pages)
    var selectAll = document.getElementById('chkSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.chkRelease').forEach(function(cb) { cb.checked = selectAll.checked; });
        });
    }
})();
