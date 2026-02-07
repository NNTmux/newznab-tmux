/**
 * Alpine.data('contentToggle') - Admin content enable/disable toggle
 * Alpine.data('contentDelete') - Admin content delete with confirmation
 */
import Alpine from '@alpinejs/csp';

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

Alpine.data('contentToggle', () => ({
    toggleStatus(contentId, currentStatus, el) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!contentId || !csrf) { showToast('Error: Missing required data', 'error'); return; }

        el.disabled = true;
        el.style.opacity = '0.6';

        fetch('/admin/content-toggle-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id: contentId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = el.closest('tr');
                const statusCell = row.cells[5];
                const ns = data.status;

                if (ns === 1) {
                    statusCell.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100"><i class="fa fa-check mr-1"></i>Enabled</span>';
                    el.className = 'content-toggle-status text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300';
                    el.innerHTML = '<i class="fa fa-toggle-on"></i>';
                } else {
                    statusCell.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100"><i class="fa fa-times mr-1"></i>Disabled</span>';
                    el.className = 'content-toggle-status text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300';
                    el.innerHTML = '<i class="fa fa-toggle-off"></i>';
                }
                el.setAttribute('data-current-status', ns);
                el.title = ns === 1 ? 'Disable' : 'Enable';
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Failed to toggle content status', 'error');
            }
            el.disabled = false;
            el.style.opacity = '1';
        })
        .catch(() => { showToast('An error occurred while toggling content status', 'error'); el.disabled = false; el.style.opacity = '1'; });
    },

    deleteContent(contentId, contentTitle, el) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!contentId || !csrf) { showToast('Error: Missing required data', 'error'); return; }

        showConfirm({
            title: 'Delete Content',
            message: 'Are you sure you want to delete "' + contentTitle + '"?',
            details: 'This action cannot be undone.',
            type: 'danger',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            onConfirm: function() {
                el.disabled = true;
                el.style.opacity = '0.6';
                fetch('/admin/content-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id: contentId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const row = el.closest('tr');
                        if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => { row.remove(); const tbody = document.querySelector('tbody'); if (tbody && tbody.children.length === 0) location.reload(); }, 300); }
                        showToast(data.message, 'success');
                    } else { showToast(data.message || 'Failed to delete content', 'error'); el.disabled = false; el.style.opacity = '1'; }
                })
                .catch(() => { showToast('An error occurred while deleting content', 'error'); el.disabled = false; el.style.opacity = '1'; });
            }
        });
    }
}));
