/**
 * Event Bridge - Document-level event delegation for existing Blade templates
 *
 * The Blade templates use CSS class hooks (.add-to-cart, .report-trigger, etc.)
 * and data attributes (data-confirm, data-open-nfo, etc.) without x-data wrappers.
 * This bridge catches those events at the document level and routes them to
 * the appropriate Alpine store/global functions.
 *
 * This file can be removed once all Blade templates are updated with x-data
 * directives and Alpine event bindings.
 */
import Alpine from '@alpinejs/csp';

function findWithAttr(e, attr) {
    if (!e || !e.target) return null;
    if (e.target.hasAttribute && e.target.hasAttribute(attr)) return e.target;
    return e.target.closest ? e.target.closest('[' + attr + ']') : null;
}

function findWithClass(e, cls) {
    if (!e || !e.target) return null;
    if (e.target.classList && e.target.classList.contains(cls)) return e.target;
    return e.target.closest ? e.target.closest('.' + cls) : null;
}

document.addEventListener('click', function(e) {

    // --- Add to Cart (individual buttons) ---
    const cartBtn = findWithClass(e, 'add-to-cart');
    if (cartBtn) {
        e.preventDefault();
        const guid = cartBtn.dataset.guid;
        const iconEl = cartBtn.querySelector('.icon_cart');
        if (!guid) return;
        if (iconEl && iconEl.classList.contains('icon_cart_clicked')) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id: guid })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (iconEl) {
                    iconEl.classList.remove('fa-shopping-basket', 'fa-shopping-cart');
                    iconEl.classList.add('fa-check', 'icon_cart_clicked');
                    setTimeout(() => {
                        iconEl.classList.remove('fa-check', 'icon_cart_clicked');
                        iconEl.classList.add('fa-shopping-basket');
                    }, 2000);
                }
                if (data.cartCount) {
                    const countEl = document.querySelector('.cart-count');
                    if (countEl) countEl.textContent = data.cartCount;
                    Alpine.store('cart').setCount(data.cartCount);
                }
                showToast('Added to cart successfully!', 'success');
            } else {
                showToast('Failed to add item to cart', 'error');
            }
        })
        .catch(() => showToast('An error occurred', 'error'));
        return;
    }

    // --- Download NZB toast ---
    if (findWithClass(e, 'download-nzb')) {
        showToast('Downloading NZB...', 'success');
        // Don't prevent default - let the link navigate
    }

    // --- NFO modal open ---
    const nfoOpen = findWithAttr(e, 'data-open-nfo');
    if (nfoOpen) {
        e.preventDefault();
        const guid = nfoOpen.getAttribute('data-open-nfo');
        if (guid && typeof openNfoModal === 'function') openNfoModal(guid);
        return;
    }

    // --- NFO modal close ---
    if (findWithAttr(e, 'data-close-nfo-modal')) {
        e.preventDefault();
        if (typeof closeNfoModal === 'function') closeNfoModal();
        return;
    }

    // --- NFO badge ---
    const nfoBadge = findWithClass(e, 'nfo-badge');
    if (nfoBadge) {
        e.preventDefault();
        const guid = nfoBadge.getAttribute('data-guid');
        if (guid && typeof openNfoModal === 'function') openNfoModal(guid);
        return;
    }

    // --- Preview badge ---
    const previewBadge = findWithClass(e, 'preview-badge');
    if (previewBadge) {
        e.preventDefault();
        const guid = previewBadge.dataset.guid;
        if (guid && typeof showPreviewImage === 'function') showPreviewImage(guid, 'preview');
        return;
    }

    // --- Sample badge ---
    const sampleBadge = findWithClass(e, 'sample-badge');
    if (sampleBadge) {
        e.preventDefault();
        const guid = sampleBadge.dataset.guid;
        if (guid && typeof showPreviewImage === 'function') showPreviewImage(guid, 'sample');
        return;
    }

    // --- Mediainfo badge ---
    const mediainfoBadge = findWithClass(e, 'mediainfo-badge');
    if (mediainfoBadge) {
        e.preventDefault();
        const releaseId = mediainfoBadge.dataset.releaseId;
        if (releaseId && typeof showMediainfo === 'function') showMediainfo(releaseId);
        return;
    }

    // --- Filelist badge ---
    const filelistBadge = findWithClass(e, 'filelist-badge');
    if (filelistBadge) {
        e.preventDefault();
        const guid = filelistBadge.dataset.guid;
        if (guid && typeof showFilelist === 'function') showFilelist(guid);
        return;
    }

    // --- Admin Release Reports: Comment/Description Modal ---
    const reportDescBtn = findWithClass(e, 'report-description-btn');
    if (reportDescBtn) {
        e.preventDefault();
        const modal = document.getElementById('reportDescriptionModal');
        const desc = reportDescBtn.dataset.description || '';
        const reason = reportDescBtn.dataset.reason || '';
        const reporter = reportDescBtn.dataset.reporter || '';
        if (modal) {
            document.getElementById('reportDescReason').textContent = reason;
            document.getElementById('reportDescReporter').textContent = reporter;
            document.getElementById('reportDescContent').textContent = desc;
            modal.classList.remove('hidden');
        }
        return;
    }

    // --- Admin Release Reports: Close description modal ---
    if (findWithClass(e, 'report-desc-modal-close') || findWithClass(e, 'report-desc-modal-backdrop')) {
        e.preventDefault();
        const modal = document.getElementById('reportDescriptionModal');
        if (modal) modal.classList.add('hidden');
        return;
    }

    // --- Admin Release Reports: Revert button ---
    const revertBtn = findWithClass(e, 'revert-report-btn');
    if (revertBtn) {
        e.preventDefault();
        const modal = document.getElementById('revertConfirmModal');
        const form = document.getElementById('revertConfirmForm');
        const statusSpan = document.getElementById('revertReportStatus');
        const actionUrl = revertBtn.dataset.actionUrl || '';
        const status = revertBtn.dataset.reportStatus || '';
        if (modal && form) {
            form.action = actionUrl;
            if (statusSpan) statusSpan.textContent = status;
            modal.classList.remove('hidden');
        }
        return;
    }

    // --- Admin Release Reports: Close revert modal ---
    if (findWithClass(e, 'revert-modal-close') || findWithClass(e, 'revert-modal-backdrop')) {
        e.preventDefault();
        const modal = document.getElementById('revertConfirmModal');
        if (modal) modal.classList.add('hidden');
        return;
    }

    // --- Modal close buttons (data attributes) ---
    if (findWithAttr(e, 'data-close-preview-modal')) { e.preventDefault(); if (typeof closePreviewModal === 'function') closePreviewModal(); return; }
    if (findWithAttr(e, 'data-close-mediainfo-modal')) { e.preventDefault(); if (typeof closeMediainfoModal === 'function') closeMediainfoModal(); return; }
    if (findWithAttr(e, 'data-close-filelist-modal')) { e.preventDefault(); if (typeof closeFilelistModal === 'function') closeFilelistModal(); return; }
    if (findWithAttr(e, 'data-close-image-modal')) { e.preventDefault(); if (typeof closeImageModal === 'function') closeImageModal(); return; }

    // --- Confirmation modal buttons ---
    if (findWithAttr(e, 'data-close-confirmation-modal')) { e.preventDefault(); if (typeof closeConfirmationModal === 'function') closeConfirmationModal(); return; }
    if (findWithAttr(e, 'data-confirm-confirmation-modal')) { e.preventDefault(); if (typeof confirmConfirmationModal === 'function') confirmConfirmationModal(); return; }

    // --- Logout ---
    if (findWithAttr(e, 'data-logout')) {
        e.preventDefault();
        const form = document.getElementById('logout-form') || document.getElementById('sidebar-logout-form');
        if (form) form.submit();
        return;
    }

    // --- Confirm delete (data-confirm-delete) ---
    const confirmDelete = findWithAttr(e, 'data-confirm-delete');
    if (confirmDelete) {
        e.preventDefault();
        e.stopPropagation();
        const form = confirmDelete.closest('form');
        showConfirm({
            message: 'Are you sure you want to delete this item?',
            type: 'danger',
            confirmText: 'Delete',
            onConfirm: function() { if (form) form.submit(); else if (confirmDelete.href) window.location.href = confirmDelete.href; }
        });
        return;
    }

    // --- Confirm action (data-confirm) ---
    const confirmAction = findWithAttr(e, 'data-confirm');
    if (confirmAction) {
        e.preventDefault();
        e.stopPropagation();
        const message = confirmAction.getAttribute('data-confirm');
        const form = confirmAction.closest('form');
        showConfirm({
            message: message,
            type: 'danger',
            confirmText: 'Confirm',
            onConfirm: function() { if (form) form.submit(); else if (confirmAction.href) window.location.href = confirmAction.href; }
        });
        return;
    }

    // --- Release report trigger ---
    const reportTrigger = findWithClass(e, 'report-trigger');
    if (reportTrigger) {
        e.preventDefault();
        e.stopPropagation();
        const container = reportTrigger.closest('.report-button-container');
        if (!container) return;
        const modal = container.querySelector('.report-modal');
        if (!modal) return;

        // Skip if already initialized by Alpine - check if modal has x-data
        if (modal.hasAttribute('x-data')) return;

        const releaseId = reportTrigger.getAttribute('data-report-release-id');
        const reasonSelect = modal.querySelector('.report-reason');
        const descriptionTextarea = modal.querySelector('.report-description');
        const charCount = modal.querySelector('.report-char-count');
        const submitButton = modal.querySelector('.report-submit');
        const errorDiv = modal.querySelector('.report-error');
        const successDiv = modal.querySelector('.report-success');
        const closeButtons = modal.querySelectorAll('.report-modal-close');
        const backdrop = modal.querySelector('.report-modal-backdrop');
        const reportLabel = reportTrigger.querySelector('.report-label');
        const flagIcon = reportTrigger.querySelector('i');

        // Reset and show
        if (errorDiv) errorDiv.classList.add('hidden');
        if (successDiv) successDiv.classList.add('hidden');
        if (reasonSelect) reasonSelect.value = '';
        if (descriptionTextarea) descriptionTextarea.value = '';
        if (charCount) charCount.textContent = '0/1000 characters';
        if (submitButton) submitButton.disabled = true;
        modal.classList.remove('hidden');

        let hasReported = false;
        let isSubmitting = false;

        // Close function
        function closeReportModal() {
            modal.classList.add('hidden');
            if (hasReported) {
                reportTrigger.disabled = true;
                reportTrigger.classList.add('opacity-50', 'cursor-not-allowed');
                if (flagIcon) flagIcon.classList.add('text-red-500');
                if (reportLabel) reportLabel.textContent = 'Reported';
            }
        }

        // Wire up close buttons (one-time, checks for duplicate)
        if (!modal._bridgeWired) {
            modal._bridgeWired = true;
            closeButtons.forEach(btn => btn.addEventListener('click', function(ev) { ev.preventDefault(); closeReportModal(); }));
            if (backdrop) backdrop.addEventListener('click', closeReportModal);
            if (descriptionTextarea && charCount) {
                descriptionTextarea.addEventListener('input', function() { charCount.textContent = this.value.length + '/1000 characters'; });
            }
            if (reasonSelect && submitButton) {
                reasonSelect.addEventListener('change', function() { submitButton.disabled = !this.value; });
            }
            if (submitButton) {
                submitButton.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    if (isSubmitting || !reasonSelect || !reasonSelect.value) return;
                    isSubmitting = true;
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
                    if (errorDiv) errorDiv.classList.add('hidden');
                    if (successDiv) successDiv.classList.add('hidden');

                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    fetch('/release-report', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ release_id: releaseId, reason: reasonSelect.value, description: descriptionTextarea ? descriptionTextarea.value : '' })
                    })
                    .then(r => r.json().then(data => ({ ok: r.ok, data })))
                    .then(result => {
                        if (result.ok && result.data.success) {
                            if (successDiv) { successDiv.querySelector('p').textContent = result.data.message; successDiv.classList.remove('hidden'); }
                            hasReported = true;
                            setTimeout(closeReportModal, 2000);
                        } else {
                            if (errorDiv) { errorDiv.querySelector('p').textContent = result.data.message || 'An error occurred.'; errorDiv.classList.remove('hidden'); }
                        }
                    })
                    .catch(() => { if (errorDiv) { errorDiv.querySelector('p').textContent = 'Network error.'; errorDiv.classList.remove('hidden'); } })
                    .finally(() => { isSubmitting = false; if (submitButton) { submitButton.disabled = !reasonSelect.value; submitButton.innerHTML = 'Submit Report'; } });
                });
            }
        }
        return;
    }

    // --- Admin menu submenu toggle ---
    const menuToggle = findWithAttr(e, 'data-toggle-submenu');
    if (menuToggle) {
        const menuId = menuToggle.getAttribute('data-toggle-submenu');
        if (menuId) {
            const submenu = document.getElementById(menuId);
            const icon = document.getElementById(menuId + '-icon');
            if (submenu) submenu.classList.toggle('hidden');
            if (icon) icon.classList.toggle('rotate-180');
        }
        return;
    }

    // --- Admin groups data-action delegation ---
    const actionTarget = e.target.closest('[data-action]');
    if (actionTarget) {
        const action = actionTarget.dataset.action;
        const groupId = actionTarget.dataset.groupId;
        const status = actionTarget.dataset.status;

        switch (action) {
            case 'show-reset-modal': if (typeof showResetAllModal === 'function') showResetAllModal(); break;
            case 'hide-reset-modal': if (typeof hideResetAllModal === 'function') hideResetAllModal(); break;
            case 'show-purge-modal': if (typeof showPurgeAllModal === 'function') showPurgeAllModal(); break;
            case 'hide-purge-modal': if (typeof hidePurgeAllModal === 'function') hidePurgeAllModal(); break;
            case 'show-reset-selected-modal': if (typeof showResetSelectedModal === 'function') showResetSelectedModal(); break;
            case 'hide-reset-selected-modal': if (typeof hideResetSelectedModal === 'function') hideResetSelectedModal(); break;
            case 'select-all-groups': if (typeof toggleSelectAllGroups === 'function') toggleSelectAllGroups(actionTarget); break;
            case 'toggle-group-status': if (typeof ajax_group_status === 'function') ajax_group_status(groupId, status); break;
            case 'toggle-backfill': if (typeof ajax_backfill_status === 'function') ajax_backfill_status(groupId, status); break;
            case 'reset-group': if (typeof ajax_group_reset === 'function') ajax_group_reset(groupId); break;
            case 'delete-group': if (typeof confirmGroupDelete === 'function') confirmGroupDelete(groupId); break;
            case 'purge-group': if (typeof confirmGroupPurge === 'function') confirmGroupPurge(groupId); break;
            case 'reset-all': if (typeof ajax_group_reset_all === 'function') ajax_group_reset_all(); break;
            case 'purge-all': if (typeof ajax_group_purge_all === 'function') ajax_group_purge_all(); break;
            case 'reset-selected': if (typeof ajax_group_reset_selected === 'function') ajax_group_reset_selected(); break;
        }
        return;
    }

    // --- Multi-operations: Download selected ---
    if (findWithClass(e, 'nzb_multi_operations_download')) {
        e.preventDefault();
        const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
        if (selected.length === 0) { showToast('Please select at least one release', 'error'); return; }
        if (selected.length === 1) window.location.href = '/getnzb/' + selected[0];
        else window.location.href = '/getnzb?id=' + encodeURIComponent(selected.join(',')) + '&zip=1';
        showToast('Downloading ' + selected.length + ' NZB(s)...', 'success');
        return;
    }

    // --- Multi-operations: Cart selected ---
    if (findWithClass(e, 'nzb_multi_operations_cart')) {
        e.preventDefault();
        const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
        if (selected.length === 0) { showToast('Please select at least one release', 'error'); return; }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id: selected.join(',') })
        }).then(r => r.json()).then(data => {
            if (data.success) showToast(data.message || 'Added ' + selected.length + ' item(s) to cart', 'success');
            else showToast('Failed to add items to cart', 'error');
        }).catch(() => showToast('An error occurred', 'error'));
        return;
    }

    // --- Multi-operations: Delete selected (admin) ---
    if (findWithClass(e, 'nzb_multi_operations_delete')) {
        e.preventDefault();
        const checkboxes = document.querySelectorAll('.chkRelease:checked');
        const selected = Array.from(checkboxes);
        if (selected.length === 0) { showToast('Please select at least one release to delete', 'error'); return; }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        showConfirm({
            title: 'Delete Releases',
            message: 'Are you sure you want to delete ' + selected.length + ' release(s)? This action cannot be undone.',
            type: 'danger', confirmText: 'Delete',
            onConfirm: function() {
                showToast('Deleting...', 'info');
                let ok = 0, fail = 0;
                Promise.all(selected.map(cb => {
                    const row = cb.closest('tr');
                    return fetch('/admin/release-delete/' + cb.value, {
                        method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' }
                    }).then(r => { if (r.ok) { ok++; if (row) { row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(()=>row.remove(),300); } } else fail++; }).catch(() => fail++);
                })).then(() => {
                    const sa = document.getElementById('chkSelectAll'); if (sa) sa.checked = false;
                    if (ok > 0 && fail === 0) showToast('Deleted ' + ok + ' release(s)', 'success');
                    else if (ok > 0) showToast('Deleted ' + ok + ', ' + fail + ' failed', 'error');
                    else showToast('Failed to delete releases', 'error');
                    if (!document.querySelectorAll('.chkRelease').length) setTimeout(() => window.location.reload(), 1500);
                });
            }
        });
        return;
    }

    // --- Cart page: Download selected ---
    if (findWithClass(e, 'nzb_multi_operations_download_cart')) {
        e.preventDefault();
        const selected = Array.from(document.querySelectorAll('.cart-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) { showToast('Please select at least one item', 'error'); return; }
        if (selected.length === 1) window.location.href = '/getnzb/' + selected[0];
        else window.location.href = '/getnzb?id=' + encodeURIComponent(selected.join(',')) + '&zip=1';
        showToast('Downloading...', 'success');
        return;
    }

    // --- Cart page: Delete selected ---
    if (findWithClass(e, 'nzb_multi_operations_cartdelete')) {
        e.preventDefault();
        const selected = Array.from(document.querySelectorAll('.cart-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) { showToast('Please select at least one item', 'error'); return; }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        showConfirm({
            title: 'Delete from Cart',
            message: 'Are you sure you want to delete ' + selected.length + ' item(s) from your cart?',
            type: 'danger', confirmText: 'Delete',
            onConfirm: function() {
                fetch('/cart/delete/' + selected.join(','), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' } })
                .then(r => { if (r.ok) { showToast('Items deleted', 'success'); setTimeout(() => window.location.reload(), 1000); } else showToast('Failed', 'error'); })
                .catch(() => showToast('Failed', 'error'));
            }
        });
        return;
    }

    // --- Cart page: Individual delete ---
    const cartDeleteLink = findWithClass(e, 'cart-delete-link');
    if (cartDeleteLink) {
        e.preventDefault();
        e.stopPropagation();
        const name = cartDeleteLink.getAttribute('data-release-name');
        const url = cartDeleteLink.getAttribute('data-delete-url');
        showConfirm({
            title: 'Remove from Cart',
            message: 'Are you sure you want to remove "' + (name || 'this item') + '" from your cart?',
            type: 'warning', confirmText: 'Remove',
            onConfirm: function() { showToast('Removing...', 'info'); setTimeout(() => { window.location.href = url; }, 500); }
        });
        return;
    }

    // --- Image modal triggers ---
    const imgTrigger = findWithClass(e, 'image-modal-trigger');
    if (imgTrigger) {
        e.preventDefault();
        const url = imgTrigger.getAttribute('data-image-url');
        const title = imgTrigger.getAttribute('data-image-title') || 'Image Preview';
        if (typeof openImageModal === 'function') openImageModal(url, title);
        return;
    }

    // --- Verify user modal ---
    const verifyBtn = findWithAttr(e, 'data-show-verify-modal');
    if (verifyBtn) {
        const form = verifyBtn.closest('form');
        if (form && typeof showVerifyModal === 'function') showVerifyModal(e, form);
        return;
    }
    if (findWithAttr(e, 'data-close-verify-modal')) { e.preventDefault(); if (typeof hideVerifyModal === 'function') hideVerifyModal(); return; }
    if (findWithAttr(e, 'data-submit-verify-form')) { e.preventDefault(); if (typeof submitVerifyForm === 'function') submitVerifyForm(); return; }

    // --- Restore/delete user buttons ---
    const restoreBtn = findWithClass(e, 'restore-user-btn');
    if (restoreBtn) {
        e.preventDefault();
        const userId = restoreBtn.getAttribute('data-user-id');
        const username = restoreBtn.getAttribute('data-username') || 'this user';
        showConfirm({
            title: 'Restore User', message: 'Are you sure you want to restore user "' + username + '"?',
            type: 'success', confirmText: 'Restore',
            onConfirm: function() {
                const form = document.getElementById('individualActionForm');
                if (form) { form.action = '/admin/deleted-users/restore/' + userId; form.method = 'POST'; form.submit(); }
            }
        });
        return;
    }

    const deleteUserBtn = findWithClass(e, 'delete-user-btn');
    if (deleteUserBtn) {
        e.preventDefault();
        const userId = deleteUserBtn.getAttribute('data-user-id');
        const username = deleteUserBtn.getAttribute('data-username') || 'this user';
        showConfirm({
            title: 'Permanently Delete User',
            message: 'Are you sure you want to permanently delete user "' + username + '"? This action cannot be undone.',
            type: 'danger', confirmText: 'Delete Permanently',
            onConfirm: function() {
                const form = document.getElementById('individualActionForm');
                if (form) { form.action = '/admin/deleted-users/permanent-delete/' + userId; form.method = 'POST'; form.submit(); }
            }
        });
        return;
    }

    // --- Promotion toggle/delete ---
    const promoToggle = findWithClass(e, 'promotion-toggle-btn');
    if (promoToggle) {
        e.preventDefault();
        const name = promoToggle.getAttribute('data-promotion-name');
        const active = promoToggle.getAttribute('data-promotion-active') === '1';
        const action = active ? 'deactivate' : 'activate';
        showConfirm({
            title: action.charAt(0).toUpperCase() + action.slice(1) + ' Promotion',
            message: 'Are you sure you want to ' + action + ' the promotion "' + name + '"?',
            type: active ? 'warning' : 'success',
            confirmText: action.charAt(0).toUpperCase() + action.slice(1),
            onConfirm: function() { window.location.href = promoToggle.href; }
        });
        return;
    }

    const promoDelete = findWithClass(e, 'promotion-delete-btn');
    if (promoDelete) {
        e.preventDefault();
        const name = promoDelete.getAttribute('data-promotion-name');
        const form = promoDelete.closest('form');
        showConfirm({
            title: 'Delete Promotion', message: 'Are you sure you want to delete the promotion "' + name + '"?',
            details: 'This action cannot be undone.', type: 'danger', confirmText: 'Delete',
            onConfirm: function() { if (form) form.submit(); }
        });
        return;
    }

    // --- Content toggle/delete ---
    const contentToggle = findWithClass(e, 'content-toggle-status');
    if (contentToggle) {
        e.preventDefault();
        const id = contentToggle.getAttribute('data-content-id');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!id || !csrf) return;
        contentToggle.disabled = true; contentToggle.style.opacity = '0.6';
        fetch('/admin/content-toggle-status', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                const row = contentToggle.closest('tr'), sc = row.cells[5], ns = data.status;
                if (ns === 1) { sc.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100"><i class="fa fa-check mr-1"></i>Enabled</span>'; contentToggle.className = 'content-toggle-status text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300'; contentToggle.innerHTML = '<i class="fa fa-toggle-on"></i>'; }
                else { sc.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100"><i class="fa fa-times mr-1"></i>Disabled</span>'; contentToggle.className = 'content-toggle-status text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300'; contentToggle.innerHTML = '<i class="fa fa-toggle-off"></i>'; }
                contentToggle.setAttribute('data-current-status', ns); contentToggle.title = ns === 1 ? 'Disable' : 'Enable';
                showToast(data.message, 'success');
            } else showToast(data.message || 'Failed', 'error');
            contentToggle.disabled = false; contentToggle.style.opacity = '1';
        }).catch(() => { showToast('Error', 'error'); contentToggle.disabled = false; contentToggle.style.opacity = '1'; });
        return;
    }

    const contentDelete = findWithClass(e, 'content-delete');
    if (contentDelete) {
        e.preventDefault();
        const id = contentDelete.getAttribute('data-content-id');
        const title = contentDelete.getAttribute('data-content-title');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!id || !csrf) return;
        showConfirm({ title: 'Delete Content', message: 'Are you sure you want to delete "' + title + '"?', details: 'This action cannot be undone.', type: 'danger', confirmText: 'Delete',
            onConfirm: function() {
                contentDelete.disabled = true; contentDelete.style.opacity = '0.6';
                fetch('/admin/content-delete', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ id }) })
                .then(r => r.json()).then(data => {
                    if (data.success) { const row = contentDelete.closest('tr'); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => { row.remove(); if (document.querySelector('tbody')?.children.length === 0) location.reload(); }, 300); } showToast(data.message, 'success'); }
                    else { showToast(data.message || 'Failed', 'error'); contentDelete.disabled = false; contentDelete.style.opacity = '1'; }
                }).catch(() => { showToast('Error', 'error'); contentDelete.disabled = false; contentDelete.style.opacity = '1'; });
            }
        });
        return;
    }

    // --- Regex delete ---
    const regexDel = findWithAttr(e, 'data-delete-regex');
    if (regexDel) {
        e.preventDefault();
        const id = regexDel.getAttribute('data-delete-regex');
        const url = regexDel.getAttribute('data-delete-url');
        if (confirm('Are you sure you want to delete this regex?')) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(url + '?id=' + id, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } })
            .then(r => r.json()).then(d => {
                if (d.success) { const row = document.getElementById('row-' + id); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); } showToast('Regex deleted', 'success'); }
                else showToast('Error', 'error');
            }).catch(() => showToast('Error', 'error'));
        }
        return;
    }

    // --- Release delete ---
    const relDel = findWithAttr(e, 'data-delete-release');
    if (relDel) {
        e.preventDefault();
        const id = relDel.getAttribute('data-delete-release');
        const url = relDel.getAttribute('data-delete-url');
        showConfirm({ title: 'Delete Release', message: 'Are you sure? This cannot be undone.', type: 'danger', confirmText: 'Delete',
            onConfirm: function() {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                showToast('Deleting...', 'info');
                fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' } })
                .then(r => { if (r.ok) { const row = relDel.closest('tr'); if (row) { row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(()=>row.remove(),300); } showToast('Deleted', 'success'); } else showToast('Error: ' + r.status, 'error'); })
                .catch(err => showToast('Error: ' + err.message, 'error'));
            }
        });
        return;
    }

    // --- Binary blacklist delete ---
    const blDel = findWithAttr(e, 'data-delete-blacklist');
    if (blDel) {
        e.preventDefault();
        const id = blDel.getAttribute('data-delete-blacklist');
        if (confirm('Are you sure? This will delete the blacklist.')) {
            if (typeof ajax_binaryblacklist_delete === 'function') ajax_binaryblacklist_delete(id);
        }
        return;
    }

    // --- Season tab ---
    const seasonTab = findWithClass(e, 'season-tab');
    if (seasonTab) {
        e.preventDefault();
        const num = seasonTab.getAttribute('data-season');
        if (num && typeof switchSeason === 'function') switchSeason(num);
        return;
    }
});

// --- Change event delegation ---
document.addEventListener('change', function(e) {
    // Select redirects
    if (e.target.hasAttribute('data-redirect-on-change')) {
        const url = e.target.value;
        if (url && url !== '#') window.location.href = url;
    }

    // Group checkbox
    if (e.target.classList.contains('group-checkbox')) {
        if (typeof updateSelectionUI === 'function') updateSelectionUI();
    }
});

// --- Cart page: Check-all checkbox ---
(function() {
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.cart-checkbox');
    if (checkAll && checkboxes.length > 0) {
        function updateState() {
            const checked = Array.from(checkboxes).filter(cb => cb.checked).length;
            checkAll.checked = checked === checkboxes.length;
            checkAll.indeterminate = checked > 0 && checked < checkboxes.length;
        }
        checkAll.addEventListener('change', function() { checkboxes.forEach(cb => { cb.checked = this.checked; }); });
        checkboxes.forEach(cb => cb.addEventListener('change', updateState));
        updateState();
    }

    // Select-all for chkRelease (browse pages)
    const selectAll = document.getElementById('chkSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.chkRelease').forEach(cb => { cb.checked = this.checked; });
        });
    }
})();

// --- Sidebar toggles ---
document.querySelectorAll('.sidebar-toggle').forEach(toggle => {
    toggle.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const submenu = document.getElementById(targetId);
        const chevron = this.querySelector('.fa-chevron-down');
        if (submenu) submenu.classList.toggle('hidden');
        if (chevron) chevron.classList.toggle('rotate-180');
    });
});

// --- Password visibility toggle ---
document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const fieldId = this.getAttribute('data-field-id');
        if (!fieldId) return;
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '-eye');
        if (!field) return;
        if (field.type === 'password') { field.type = 'text'; if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } }
        else { field.type = 'password'; if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } }
    });
});
window.togglePasswordVisibility = function(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-eye');
    if (!field) return;
    if (field.type === 'password') { field.type = 'text'; if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } }
    else { field.type = 'password'; if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } }
};

// --- Image fallbacks ---
document.querySelectorAll('img[data-fallback-src]').forEach(img => {
    img.addEventListener('error', function() {
        const fb = this.getAttribute('data-fallback-src');
        if (fb && this.src !== fb) this.src = fb;
    });
});

// --- Theme toggle button ---
(function() {
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            Alpine.store('theme').cycle();
        });
    }
})();

// --- Search autocomplete initialization ---
(function() {
    function initAutocomplete(inputId, dropdownId, formId, itemClass, maxItems) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        const form = document.getElementById(formId);
        if (!input || !dropdown) return;

        let debounceTimer, currentIndex = -1, suggestions = [];

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const q = this.value.trim();
            if (q.length < 2) { hideDropdown(); return; }
            debounceTimer = setTimeout(() => {
                fetch('/api/search/autocomplete?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.suggestions && data.suggestions.length > 0) {
                            suggestions = data.suggestions;
                            currentIndex = -1;
                            renderDropdown(suggestions, q);
                        } else hideDropdown();
                    }).catch(() => hideDropdown());
            }, 200);
        });

        input.addEventListener('keydown', function(ev) {
            if (dropdown.classList.contains('hidden')) return;
            const items = dropdown.querySelectorAll('.' + itemClass);
            if (ev.key === 'ArrowDown') { ev.preventDefault(); currentIndex = Math.min(currentIndex + 1, items.length - 1); updateSelection(items); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); currentIndex = Math.max(currentIndex - 1, 0); updateSelection(items); }
            else if (ev.key === 'Enter' && currentIndex >= 0 && items[currentIndex]) { ev.preventDefault(); input.value = suggestions[currentIndex]; hideDropdown(); if (form) form.submit(); }
            else if (ev.key === 'Escape') hideDropdown();
        });

        document.addEventListener('click', function(ev) {
            if (!input.contains(ev.target) && !dropdown.contains(ev.target)) hideDropdown();
        });

        function renderDropdown(items, query) {
            currentIndex = -1;
            const escapeRegex = str => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
            const limit = maxItems || 10;
            const sizeClass = itemClass === 'header-autocomplete-item' ? 'px-3 py-2 text-sm' : 'px-4 py-2';
            const iconSize = itemClass === 'header-autocomplete-item' ? 'text-xs' : '';
            dropdown.innerHTML = items.slice(0, limit).map((item, i) => {
                const hl = item.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-700 px-0.5 rounded">$1</mark>');
                return '<div class="' + itemClass + ' ' + sizeClass + ' cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-100" data-index="' + i + '"><i class="fas fa-search text-gray-400 mr-2 ' + iconSize + '"></i>' + hl + '</div>';
            }).join('');
            dropdown.querySelectorAll('.' + itemClass).forEach((el, i) => {
                el.addEventListener('click', () => { input.value = suggestions[i]; hideDropdown(); if (form) form.submit(); });
                el.addEventListener('mouseenter', () => { currentIndex = i; updateSelection(dropdown.querySelectorAll('.' + itemClass)); });
            });
            dropdown.classList.remove('hidden');
        }

        function hideDropdown() { dropdown.classList.add('hidden'); dropdown.innerHTML = ''; suggestions = []; currentIndex = -1; }
        function updateSelection(items) { items.forEach((el, i) => { if (i === currentIndex) el.classList.add('bg-blue-100', 'dark:bg-blue-900'); else el.classList.remove('bg-blue-100', 'dark:bg-blue-900'); }); }
    }

    // Header desktop search
    initAutocomplete('header-search-input', 'header-autocomplete-dropdown', 'header-search-form', 'header-autocomplete-item', 8);
    // Header mobile search
    initAutocomplete('mobile-search-input', 'mobile-autocomplete-dropdown', 'mobile-search-form-el', 'header-autocomplete-item', 8);
    // Main search page
    initAutocomplete('search', 'autocomplete-dropdown', 'searchForm', 'autocomplete-item', 10);
    // Dynamic autocomplete inputs
    document.querySelectorAll('[data-autocomplete-input]').forEach(function(el) {
        const ddId = el.getAttribute('data-autocomplete-input');
        const formId = el.getAttribute('data-autocomplete-form');
        if (el.id && ddId) initAutocomplete(el.id, ddId, formId, 'autocomplete-suggestion', 10);
    });
})();

// --- Sort dropdowns ---
(function() {
    document.querySelectorAll('.sort-dropdown').forEach(function(dd) {
        const toggle = dd.querySelector('.sort-dropdown-toggle');
        const menu = dd.querySelector('.sort-dropdown-menu');
        const chevron = dd.querySelector('.sort-dropdown-chevron');
        if (!toggle || !menu) return;
        if (toggle.hasAttribute('data-sort-initialized')) return;
        toggle.setAttribute('data-sort-initialized', 'true');
        toggle.addEventListener('click', function(ev) {
            ev.preventDefault(); ev.stopPropagation();
            const isOpen = !menu.classList.contains('hidden');
            document.querySelectorAll('.sort-dropdown-menu').forEach(m => m.classList.add('hidden'));
            document.querySelectorAll('.sort-dropdown-chevron').forEach(c => c.classList.remove('rotate-180'));
            if (!isOpen) { menu.classList.remove('hidden'); if (chevron) chevron.classList.add('rotate-180'); }
        });
    });
    if (!window._sortDropdownOutsideListenerAdded) {
        window._sortDropdownOutsideListenerAdded = true;
        document.addEventListener('click', function(ev) {
            if (!ev.target.closest('.sort-dropdown')) {
                document.querySelectorAll('.sort-dropdown-menu').forEach(m => m.classList.add('hidden'));
                document.querySelectorAll('.sort-dropdown-chevron').forEach(c => c.classList.remove('rotate-180'));
            }
        });
    }
})();

// --- Header dropdown menus ---
(function() {
    const containers = document.querySelectorAll('.dropdown-container');
    containers.forEach(function(container) {
        const toggle = container.querySelector('.dropdown-toggle');
        const menu = container.querySelector('.dropdown-menu');
        if (!toggle || !menu) return;
        let closeTimeout;
        menu.style.display = 'none';
        toggle.addEventListener('click', function(ev) {
            ev.preventDefault(); ev.stopPropagation();
            const open = menu.style.display === 'block';
            containers.forEach(c => { const m = c.querySelector('.dropdown-menu'); if (m && c !== container) m.style.display = 'none'; });
            menu.style.display = open ? 'none' : 'block';
        });
        container.addEventListener('mouseenter', () => clearTimeout(closeTimeout));
        container.addEventListener('mouseleave', () => { closeTimeout = setTimeout(() => { menu.style.display = 'none'; }, 300); });
        menu.addEventListener('mouseenter', () => clearTimeout(closeTimeout));
    });
    document.addEventListener('click', function(ev) {
        if (!ev.target.closest('.dropdown-container')) containers.forEach(c => { const m = c.querySelector('.dropdown-menu'); if (m) m.style.display = 'none'; });
    });

    // Nested submenus
    document.querySelectorAll('.submenu-container').forEach(function(container) {
        const sub = container.querySelector('.submenu');
        if (!sub) return;
        let t;
        container.addEventListener('mouseenter', () => { clearTimeout(t); sub.style.display = 'block'; });
        container.addEventListener('mouseleave', () => { t = setTimeout(() => { sub.style.display = 'none'; }, 200); });
        sub.addEventListener('mouseenter', () => clearTimeout(t));
    });
})();

// --- Mobile menu toggle ---
(function() {
    const toggle = document.getElementById('mobile-menu-toggle');
    const panel = document.getElementById('mobile-nav-panel');
    const iconOpen = document.getElementById('mobile-menu-icon-open');
    const iconClose = document.getElementById('mobile-menu-icon-close');
    const searchToggle = document.getElementById('mobile-search-toggle');
    const searchForm = document.getElementById('mobile-search-form');

    if (toggle && panel) {
        toggle.addEventListener('click', function() {
            const isOpen = !panel.classList.contains('hidden');
            if (isOpen) { panel.classList.add('hidden'); toggle.setAttribute('aria-expanded', 'false'); }
            else { panel.classList.remove('hidden'); toggle.setAttribute('aria-expanded', 'true'); if (searchForm) searchForm.classList.add('hidden'); }
            if (iconOpen && iconClose) { iconOpen.classList.toggle('hidden'); iconClose.classList.toggle('hidden'); }
        });
    }

    // Mobile nav section accordion toggles
    document.querySelectorAll('.mobile-nav-toggle').forEach(function(t) {
        t.addEventListener('click', function() {
            const section = this.closest('.mobile-nav-section');
            if (!section) return;
            const submenu = section.querySelector('.mobile-nav-submenu');
            const chevron = this.querySelector('.mobile-nav-chevron');
            if (submenu) submenu.classList.toggle('hidden');
            if (chevron) chevron.classList.toggle('rotate-180');
        });
    });

    if (searchToggle && searchForm) {
        searchToggle.addEventListener('click', function(ev) {
            ev.preventDefault();
            searchForm.classList.toggle('hidden');
            if (panel && !searchForm.classList.contains('hidden')) {
                panel.classList.add('hidden');
                if (iconOpen && iconClose) { iconOpen.classList.remove('hidden'); iconClose.classList.add('hidden'); }
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) { sidebar.classList.toggle('hidden'); sidebar.classList.toggle('flex'); }
        });
    }

    // Close on resize past lg
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            if (panel) panel.classList.add('hidden');
            if (searchForm) searchForm.classList.add('hidden');
            if (iconOpen && iconClose) { iconOpen.classList.remove('hidden'); iconClose.classList.add('hidden'); }
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }
    });
})();

// --- Tab switcher (data-tab-trigger) ---
document.querySelectorAll('[data-tab-trigger]').forEach(function(trigger) {
    trigger.addEventListener('click', function(ev) {
        ev.preventDefault();
        const tabId = this.getAttribute('data-tab-trigger');
        document.querySelectorAll('.tab-content').forEach(t => { t.style.display = 'none'; });
        const sel = document.getElementById(tabId);
        if (sel) sel.style.display = 'block';
        document.querySelectorAll('[data-tab-trigger]').forEach(t => { t.classList.remove('active', 'border-blue-500', 'text-blue-600'); t.classList.add('border-transparent', 'text-gray-500'); });
        this.classList.remove('border-transparent', 'text-gray-500');
        this.classList.add('active', 'border-blue-500', 'text-blue-600');
    });
});

// --- Profile tabs (tab-link) ---
(function() {
    const links = document.querySelectorAll('.tab-link');
    const contents = document.querySelectorAll('.tab-content');
    if (!links.length || !contents.length) return;
    let chartsInited = false;
    links.forEach(link => {
        link.addEventListener('click', function(ev) {
            ev.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            links.forEach(l => { l.classList.remove('bg-blue-50', 'text-blue-700', 'font-medium'); l.classList.add('text-gray-700', 'dark:text-gray-300'); });
            this.classList.add('bg-blue-50', 'text-blue-700', 'font-medium');
            this.classList.remove('text-gray-700', 'dark:text-gray-300');
            contents.forEach(c => { c.style.display = 'none'; });
            const target = document.getElementById(targetId);
            if (target) { target.style.display = 'block'; if (targetId === 'api' && !chartsInited) { chartsInited = true; let att = 0; const check = setInterval(() => { att++; if (typeof Chart !== 'undefined') { clearInterval(check); if (typeof initializeProfileCharts === 'function') initializeProfileCharts(); } else if (att >= 20) clearInterval(check); }, 100); } }
            history.pushState(null, null, '#' + targetId);
        });
    });
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        const link = document.querySelector('a[href="#' + hash + '"]');
        if (link) link.click();
    } else { contents.forEach((c, i) => { if (i !== 0) c.style.display = 'none'; }); }
})();

// --- Profile Edit: 2FA form toggle ---
(function() {
    const toggleBtn = document.getElementById('toggle-disable-2fa-btn');
    const cancelBtn = document.getElementById('cancel-disable-2fa-btn');
    const formContainer = document.getElementById('disable-2fa-form-container');
    if (toggleBtn && formContainer) toggleBtn.addEventListener('click', () => { formContainer.style.display = formContainer.style.display === 'none' ? 'block' : 'none'; });
    if (cancelBtn && formContainer) cancelBtn.addEventListener('click', () => { formContainer.style.display = 'none'; const p = document.getElementById('disable_2fa_password'); if (p) p.value = ''; });

    // Theme radio buttons on profile edit
    document.querySelectorAll('input[name="theme_preference"]').forEach(radio => {
        if (radio.dataset.themeListenerAttached === 'true') return;
        radio.dataset.themeListenerAttached = 'true';
        radio.addEventListener('change', function() {
            if (window._updatingThemeUI) return;
            Alpine.store('theme').set(this.value);
        });
    });
})();

// --- Auth pages: auto-hide alerts, 2FA auto-submit ---
(function() {
    if (window.location.pathname.includes('/login')) {
        setTimeout(() => {
            document.querySelectorAll('.bg-green-50, .bg-blue-50').forEach(el => {
                el.style.transition = 'opacity 0.5s ease-out'; el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    }
    const otp = document.getElementById('one_time_password');
    if (otp) {
        otp.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); if (this.value.length === 6) setTimeout(() => this.form.submit(), 300); });
        otp.focus();
    }
})();

// --- Progress bar animation ---
document.querySelectorAll('.progress-bar').forEach(bar => {
    const w = bar.dataset.width;
    if (w) setTimeout(() => { bar.style.width = w + '%'; }, 100);
});

// --- Copy to Clipboard ---
(function() {
    function copyToClipboard(text, button) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => showCopyFeedback(button)).catch(() => fallbackCopy(text, button));
        } else fallbackCopy(text, button);
    }
    function fallbackCopy(text, button) {
        const ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); ta.setSelectionRange(0, 99999);
        try { document.execCommand('copy'); showCopyFeedback(button); } catch(e) {} document.body.removeChild(ta);
    }
    function showCopyFeedback(button) {
        if (!button) return; const icon = button.querySelector('i');
        if (icon) { icon.classList.remove('fa-copy'); icon.classList.add('fa-check'); }
        button.classList.add('text-green-600');
        setTimeout(() => { if (icon) { icon.classList.remove('fa-check'); icon.classList.add('fa-copy'); } button.classList.remove('text-green-600'); }, 2000);
    }
    document.addEventListener('click', function(ev) {
        const copyBtn = ev.target.closest('.copy-btn');
        if (copyBtn) { const tid = copyBtn.getAttribute('data-copy-target'); const input = document.getElementById(tid); if (input) { ev.preventDefault(); input.select(); input.setSelectionRange(0, 99999); copyToClipboard(input.value, copyBtn); } }
        const apiBtn = ev.target.id === 'copyApiToken' ? ev.target : ev.target.closest('#copyApiToken');
        if (apiBtn) { const input = document.getElementById('apiTokenInput'); if (input) { ev.preventDefault(); input.select(); input.setSelectionRange(0, 99999); copyToClipboard(input.value, apiBtn); } }
    });
})();

// --- Admin dashboard charts and recent activity ---
(function() {
    function initCharts() {
        if (typeof Chart === 'undefined') { let a = 0; const c = setInterval(() => { a++; if (typeof Chart !== 'undefined') { clearInterval(c); doCharts(); } else if (a >= 30) clearInterval(c); }, 100); return; }
        doCharts();
    }
    function doCharts() {
        function makeChart(id, type, label, bg, border, fill) {
            const canvas = document.getElementById(id); if (!canvas) return;
            const data = JSON.parse(canvas.dataset.chartData || canvas.dataset.history || '[]'); if (!data.length) return;
            new Chart(canvas, { type, data: { labels: data.map(i => i.time), datasets: [{ label, data: data.map(i => i.count || i.value), backgroundColor: bg, borderColor: border, borderWidth: type === 'bar' ? 1 : 2, fill, tension: 0.4 }] }, options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } } });
        }
        function makePctChart(id, label, bg, border) {
            const canvas = document.getElementById(id); if (!canvas) return;
            const data = JSON.parse(canvas.dataset.history || '[]'); if (!data.length) return;
            new Chart(canvas, { type: 'line', data: { labels: data.map(i => i.time), datasets: [{ label, data: data.map(i => i.value), backgroundColor: bg, borderColor: border, borderWidth: 2, fill: true, tension: 0.4 }] }, options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }, plugins: { legend: { display: false } } } });
        }
        makeChart('downloadsChart','bar','Downloads','rgba(34,197,94,0.7)','rgba(34,197,94,1)',false);
        makeChart('apiHitsChart','line','API Hits','rgba(168,85,247,0.2)','rgba(168,85,247,1)',true);
        makeChart('downloadsMinuteChart','line','Downloads','rgba(34,197,94,0.2)','rgba(34,197,94,1)',true);
        makeChart('apiHitsMinuteChart','line','API Hits','rgba(168,85,247,0.2)','rgba(168,85,247,1)',true);
        makePctChart('cpuHistory24hChart','CPU %','rgba(249,115,22,0.2)','rgba(249,115,22,1)');
        makePctChart('ramHistory24hChart','RAM %','rgba(6,182,212,0.2)','rgba(6,182,212,1)');
        makePctChart('cpuHistory30dChart','CPU %','rgba(249,115,22,0.2)','rgba(249,115,22,1)');
        makePctChart('ramHistory30dChart','RAM %','rgba(6,182,212,0.2)','rgba(6,182,212,1)');
    }
    const hasCharts = document.getElementById('downloadsChart') || document.getElementById('cpuHistory24hChart');
    if (hasCharts) initCharts();

    // Recent activity refresh
    const activityContainer = document.getElementById('recent-activity-container');
    if (activityContainer) {
        const refreshUrl = activityContainer.getAttribute('data-refresh-url');
        if (refreshUrl) {
            function escHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
            function refreshActivity() {
                fetch(refreshUrl, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json()).then(data => {
                    if (!data.success || !data.activities) return;
                    activityContainer.innerHTML = '';
                    if (!data.activities.length) { activityContainer.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">No recent activity</p>'; return; }
                    data.activities.forEach(a => {
                        const div = document.createElement('div'); div.className = 'flex items-start activity-item rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors';
                        div.innerHTML = '<div class="w-8 h-8 '+a.icon_bg+' rounded-full flex items-center justify-center mr-3 flex-shrink-0"><i class="fas fa-'+a.icon+' '+a.icon_color+' text-sm"></i></div><div class="flex-1"><p class="text-sm text-gray-800 dark:text-gray-200">'+escHtml(a.message)+'</p><p class="text-xs text-gray-500 dark:text-gray-400">'+escHtml(a.created_at)+'</p></div>';
                        activityContainer.appendChild(div);
                    });
                    const ts = document.getElementById('activity-last-updated');
                    if (ts) ts.innerHTML = '<i class="fas fa-sync-alt"></i> Last updated: ' + new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
                }).catch(() => {});
            }
            setInterval(refreshActivity, 20 * 60 * 1000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshActivity(); });
        }
    }
})();

// --- Admin user edit datetime picker ---
(function() {
    if (!document.getElementById('rolechangedate')) return;
    const hidden = document.getElementById('rolechangedate');
    if (hidden && hidden.value) {
        const d = new Date(hidden.value);
        if (!isNaN(d.getTime())) {
            const el = id => document.getElementById(id);
            if (el('expiry_year')) el('expiry_year').value = d.getFullYear().toString();
            if (el('expiry_month')) el('expiry_month').value = String(d.getMonth()+1).padStart(2,'0');
            if (el('expiry_day')) el('expiry_day').value = String(d.getDate()).padStart(2,'0');
            if (el('expiry_hour')) el('expiry_hour').value = String(d.getHours()).padStart(2,'0');
            if (el('expiry_minute')) el('expiry_minute').value = String(d.getMinutes()).padStart(2,'0');
        }
    }
    function updateDateTime() {
        const y = document.getElementById('expiry_year')?.value, mo = document.getElementById('expiry_month')?.value, d = document.getElementById('expiry_day')?.value, h = document.getElementById('expiry_hour')?.value, mi = document.getElementById('expiry_minute')?.value;
        if (y && mo && d && h && mi) { document.getElementById('rolechangedate').value = y+'-'+mo+'-'+d+'T'+h+':'+mi+':00'; updatePreview(); }
    }
    function updatePreview() {
        const h = document.getElementById('rolechangedate'), p = document.getElementById('datetime_preview'), disp = document.getElementById('datetime_display');
        if (!h?.value || !p || !disp) return;
        const d = new Date(h.value);
        disp.textContent = d.toLocaleDateString('en-US',{weekday:'short',year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'});
        p.classList.remove('hidden');
    }
    ['expiry_year','expiry_month','expiry_day','expiry_hour','expiry_minute'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', updateDateTime);
    });

    // Expiry quick actions
    document.addEventListener('click', function(ev) {
        const target = ev.target.closest('[data-expiry-action]');
        if (!target) return;
        ev.preventDefault();
        const action = target.getAttribute('data-expiry-action');
        const days = parseInt(target.getAttribute('data-days') || '0');
        const hours = parseInt(target.getAttribute('data-hours') || '0');
        if (action === 'set') {
            let base; const y = document.getElementById('expiry_year')?.value, mo = document.getElementById('expiry_month')?.value, d = document.getElementById('expiry_day')?.value, h = document.getElementById('expiry_hour')?.value, mi = document.getElementById('expiry_minute')?.value;
            const orig = document.getElementById('original_user_expiry')?.value;
            if (y && mo && d && h && mi) base = new Date(y, parseInt(mo)-1, d, h, mi);
            else if (orig) base = new Date(orig);
            else base = new Date();
            base.setDate(base.getDate() + days); base.setHours(base.getHours() + hours);
            document.getElementById('expiry_year').value = base.getFullYear().toString();
            document.getElementById('expiry_month').value = String(base.getMonth()+1).padStart(2,'0');
            document.getElementById('expiry_day').value = String(base.getDate()).padStart(2,'0');
            document.getElementById('expiry_hour').value = String(base.getHours()).padStart(2,'0');
            document.getElementById('expiry_minute').value = String(base.getMinutes()).padStart(2,'0');
            updateDateTime();
        } else if (action === 'end-of-day') {
            const eod = new Date(); eod.setHours(23,59,0,0);
            document.getElementById('expiry_year').value = eod.getFullYear().toString();
            document.getElementById('expiry_month').value = String(eod.getMonth()+1).padStart(2,'0');
            document.getElementById('expiry_day').value = String(eod.getDate()).padStart(2,'0');
            document.getElementById('expiry_hour').value = '23'; document.getElementById('expiry_minute').value = '59';
            updateDateTime();
        } else if (action === 'clear') {
            ['expiry_year','expiry_month','expiry_day','expiry_hour','expiry_minute'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            document.getElementById('rolechangedate').value = '';
            const p = document.getElementById('datetime_preview'); if (p) p.classList.add('hidden');
        }
    });
})();

// --- TinyMCE initialization ---
// NOTE: TinyMCE is now loaded directly in blade templates with proper CSP nonce
// See admin/content/add.blade.php for example. This avoids CSP blocking of dynamically loaded scripts.
// Keeping this code commented for reference:
/*
(function() {
    function initTinyMCE() {
        const body = document.getElementById('body');
        const editors = document.querySelectorAll('.tinymce-editor');
        if (!body && !editors.length) return;

        function loadAndInit() {
            if (typeof tinymce !== 'undefined') { doInit(); return; }
            let apiKey = 'no-api-key';
            const meta = document.querySelector('meta[name="tinymce-api-key"]');
            if (meta && meta.content) apiKey = meta.content;
            else if (window.NNTmuxConfig?.tinymceApiKey) apiKey = window.NNTmuxConfig.tinymceApiKey;
            else if (body?.dataset.tinymceApiKey) apiKey = body.dataset.tinymceApiKey;
            else { for (const e of editors) { if (e.dataset.tinymceApiKey) { apiKey = e.dataset.tinymceApiKey; break; } } }

            const script = document.createElement('script');
            script.src = 'https://cdn.tiny.cloud/1/' + apiKey + '/tinymce/7/tinymce.min.js';
            script.referrerPolicy = 'origin';
            script.onload = doInit;
            script.onerror = function() { console.error('Failed to load TinyMCE'); };
            document.head.appendChild(script);
        }

        function doInit() {
            const dark = document.documentElement.classList.contains('dark');
            tinymce.init({
                selector: '#body, .tinymce-editor',
                height: 500,
                menubar: true,
                skin: dark ? 'oxide-dark' : 'oxide',
                content_css: dark ? 'dark' : 'default',
                plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'],
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table emoticons | removeformat code fullscreen | help',
                toolbar_mode: 'sliding',
                content_style: 'body{font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6}',
                branding: false,
                promotion: false,
                resize: true,
                valid_elements: '*[*]',
                extended_valid_elements: '*[*]',
                setup: function(editor) {
                    editor.on('change blur keyup submit', function() { editor.save(); });
                }
            });
        }

        loadAndInit();
    }

    // Run on DOMContentLoaded or immediately if already loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTinyMCE);
    } else {
        initTinyMCE();
    }
})();
*/

// --- Admin: Invitations select-all, deleted users, regex validation ---
(function() {
    // Release reports select-all
    const selectAllReports = document.getElementById('select-all');
    if (selectAllReports) {
        const boxes = document.querySelectorAll('.report-checkbox');
        if (boxes.length > 0) {
            selectAllReports.addEventListener('change', function() { boxes.forEach(cb => { cb.checked = this.checked; }); });
            boxes.forEach(cb => cb.addEventListener('change', () => {
                const all = Array.from(boxes).every(c => c.checked);
                const some = Array.from(boxes).some(c => c.checked);
                selectAllReports.checked = all; selectAllReports.indeterminate = some && !all;
            }));
        }
    }

    // Invitations
    const selectAll = document.getElementById('select_all');
    if (selectAll) {
        const boxes = document.querySelectorAll('.invitation-checkbox');
        selectAll.addEventListener('change', function() { boxes.forEach(cb => { cb.checked = this.checked; }); });
        boxes.forEach(cb => cb.addEventListener('change', () => {
            const all = Array.from(boxes).every(c => c.checked);
            const some = Array.from(boxes).some(c => c.checked);
            selectAll.checked = all; selectAll.indeterminate = some && !all;
        }));
    }

    // Deleted users select-all
    const selectAllUsers = document.getElementById('selectAll');
    if (selectAllUsers) {
        const boxes = document.querySelectorAll('.user-checkbox');
        selectAllUsers.addEventListener('change', function() { boxes.forEach(cb => { cb.checked = this.checked; }); });
        boxes.forEach(cb => cb.addEventListener('change', () => {
            const all = Array.from(boxes).every(c => c.checked);
            const some = Array.from(boxes).some(c => c.checked);
            selectAllUsers.checked = all; selectAllUsers.indeterminate = some && !all;
        }));
    }

    // Bulk action form
    const bulkForm = document.getElementById('bulkActionForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(ev) {
            ev.preventDefault();
            const action = document.getElementById('bulkAction')?.value;
            const checked = document.querySelectorAll('.user-checkbox:checked');
            const errEl = document.getElementById('validationError'), errMsg = document.getElementById('validationErrorMessage');
            if (errEl) errEl.classList.add('hidden');
            if (!action) { if (errEl && errMsg) { errMsg.textContent = 'Please select an action.'; errEl.classList.remove('hidden'); } return; }
            if (!checked.length) { if (errEl && errMsg) { errMsg.textContent = 'Please select at least one user.'; errEl.classList.remove('hidden'); } return; }
            const text = action === 'restore' ? 'restore' : 'permanently delete';
            showConfirm({ title: action === 'restore' ? 'Restore Users' : 'Delete Users', message: 'Are you sure you want to ' + text + ' ' + checked.length + ' user(s)?', type: action === 'restore' ? 'success' : 'danger', confirmText: action === 'restore' ? 'Restore' : 'Delete', onConfirm: () => bulkForm.submit() });
        });
    }

    // Tmux crap types toggle
    const checkedRadio = document.querySelector('input[name="fix_crap_opt"]:checked');
    const crapContainer = document.getElementById('crap_types_container');
    if (crapContainer) {
        if (checkedRadio) crapContainer.style.display = checkedRadio.value === 'Custom' ? 'block' : 'none';
        document.querySelectorAll('input[name="fix_crap_opt"]').forEach(r => r.addEventListener('change', function() { crapContainer.style.display = this.value === 'Custom' ? 'block' : 'none'; }));
    }
})();

// --- User list scroll sync ---
(function() {
    const top = document.getElementById('topScroll'), bot = document.getElementById('bottomScroll'), content = document.getElementById('topScrollContent'), table = bot?.querySelector('table');
    if (!top || !bot || !content || !table) return;
    const sync = () => { content.style.width = table.scrollWidth + 'px'; }; sync();
    window.addEventListener('resize', sync);
    top.addEventListener('scroll', function() { if (!top._s) { bot._s = true; bot.scrollLeft = top.scrollLeft; bot._s = false; } });
    bot.addEventListener('scroll', function() { if (!bot._s) { top._s = true; top.scrollLeft = bot.scrollLeft; top._s = false; } });
})();

// --- My Movies confirm ---
document.querySelectorAll('.confirm_action').forEach(el => { el.addEventListener('click', function(ev) { if (!confirm('Are you sure you want to remove this movie from your watchlist?')) ev.preventDefault(); }); });

// --- Escape key for modals ---
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    if (typeof closeNfoModal === 'function') closeNfoModal();
    if (typeof closePreviewModal === 'function') closePreviewModal();
    if (typeof closeMediainfoModal === 'function') closeMediainfoModal();
    if (typeof closeFilelistModal === 'function') closeFilelistModal();
    if (typeof closeImageModal === 'function') closeImageModal();
    if (typeof hideVerifyModal === 'function') hideVerifyModal();
    // Close admin release reports modals
    const descModal = document.getElementById('reportDescriptionModal');
    if (descModal && !descModal.classList.contains('hidden')) descModal.classList.add('hidden');
    const revertModal = document.getElementById('revertConfirmModal');
    if (revertModal && !revertModal.classList.contains('hidden')) revertModal.classList.add('hidden');
});
