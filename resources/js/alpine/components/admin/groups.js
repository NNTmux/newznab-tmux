/**
 * Alpine.data('adminGroups') - Admin groups management
 */
import Alpine from '@alpinejs/csp';

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

Alpine.data('adminGroups', () => ({
    resetAllOpen: false,
    purgeAllOpen: false,
    resetSelectedOpen: false,
    selectedGroupNames: [],
    allChecked: false,

    init() {
        const container = this.$el.querySelector('[data-ajax-url]') || this.$el;
        this._ajaxUrl = container.dataset.ajaxUrl || '/admin/ajax';
        this._csrf = container.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;

        // Backward compat
        const self = this;
        window.ajax_group_status = function(id, s) { self._toggleStatus(id, s); };
        window.ajax_backfill_status = function(id, s) { self._toggleBackfill(id, s); };
        window.ajax_group_reset = function(id) { self._resetGroup(id); };
        window.confirmGroupDelete = function(id) { self._deleteGroup(id); };
        window.confirmGroupPurge = function(id) { self._purgeGroup(id); };
        window.ajax_group_reset_all = function() { self._resetAll(); };
        window.ajax_group_purge_all = function() { self._purgeAll(); };
        window.ajax_group_reset_selected = function() { self._resetSelected(); };
        window.showResetAllModal = function() { self.resetAllOpen = true; };
        window.hideResetAllModal = function() { self.resetAllOpen = false; };
        window.showPurgeAllModal = function() { self.purgeAllOpen = true; };
        window.hidePurgeAllModal = function() { self.purgeAllOpen = false; };
        window.showResetSelectedModal = function() { self._showResetSelected(); };
        window.hideResetSelectedModal = function() { self.resetSelectedOpen = false; };
        window.toggleSelectAllGroups = function(cb) { self._toggleSelectAll(cb); };
        window.getSelectedGroups = function() { return self._getSelected(); };
        window.updateSelectionUI = function() { self._updateSelectionUI(); };
    },

    handleAction(action, groupId, status) {
        switch (action) {
            case 'toggle-group-status': this._toggleStatus(groupId, status); break;
            case 'toggle-backfill': this._toggleBackfill(groupId, status); break;
            case 'reset-group': this._resetGroup(groupId); break;
            case 'delete-group': this._deleteGroup(groupId); break;
            case 'purge-group': this._purgeGroup(groupId); break;
            case 'reset-all': this._resetAll(); break;
            case 'purge-all': this._purgeAll(); break;
            case 'reset-selected': this._resetSelected(); break;
            case 'show-reset-modal': this.resetAllOpen = true; break;
            case 'hide-reset-modal': this.resetAllOpen = false; break;
            case 'show-purge-modal': this.purgeAllOpen = true; break;
            case 'hide-purge-modal': this.purgeAllOpen = false; break;
            case 'show-reset-selected-modal': this._showResetSelected(); break;
            case 'hide-reset-selected-modal': this.resetSelectedOpen = false; break;
            case 'select-all-groups': break; // handled via x-model
        }
    },

    toggleAllCheckboxes() {
        const boxes = this.$el.querySelectorAll('.group-checkbox');
        boxes.forEach(cb => { cb.checked = this.allChecked; });
        this._updateSelectionUI();
    },

    onGroupCheckboxChange() {
        this._updateSelectionUI();
    },

    _post(body) {
        return fetch(this._ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': this._csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(body)
        }).then(r => r.json());
    },

    _toggleStatus(id, status) {
        this._post({ action: 'toggle_group_active_status', group_id: id, group_status: status }).then(data => {
            if (data.success) {
                const cell = document.getElementById('group-' + id);
                if (cell && data.newStatus !== undefined) {
                    const active = data.newStatus == 1;
                    cell.innerHTML = active
                        ? '<button type="button" data-action="toggle-group-status" data-group-id="' + id + '" data-status="0" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 hover:bg-green-200"><i class="fa fa-check-circle mr-1"></i>Active</button>'
                        : '<button type="button" data-action="toggle-group-status" data-group-id="' + id + '" data-status="1" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200"><i class="fa fa-times-circle mr-1"></i>Inactive</button>';
                }
                showToast(data.message || 'Group status updated', 'success');
            } else showToast(data.message || 'Error', 'error');
        }).catch(() => showToast('Error updating group status', 'error'));
    },

    _toggleBackfill(id, status) {
        this._post({ action: 'toggle_group_backfill', group_id: id, backfill: status }).then(data => {
            if (data.success) {
                const cell = document.getElementById('backfill-' + id);
                if (cell && data.newStatus !== undefined) {
                    const en = data.newStatus == 1;
                    cell.innerHTML = en
                        ? '<button type="button" data-action="toggle-backfill" data-group-id="' + id + '" data-status="0" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200"><i class="fa fa-check-circle mr-1"></i>Enabled</button>'
                        : '<button type="button" data-action="toggle-backfill" data-group-id="' + id + '" data-status="1" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200"><i class="fa fa-times-circle mr-1"></i>Disabled</button>';
                }
                showToast(data.message || 'Backfill status updated', 'success');
            } else showToast(data.message || 'Error', 'error');
        }).catch(() => showToast('Error updating backfill status', 'error'));
    },

    _resetGroup(id) {
        showConfirm({ title: 'Reset Group', message: 'Are you sure you want to reset this group?', details: 'This will reset the article pointers back to the current state.', type: 'warning', confirmText: 'Reset', cancelText: 'Cancel', onConfirm: () => {
            this._post({ action: 'reset_group', group_id: id }).then(d => showToast(d.message || (d.success ? 'Group reset' : 'Error'), d.success ? 'success' : 'error')).catch(() => showToast('Error', 'error'));
        }});
    },

    _deleteGroup(id) {
        showConfirm({ title: 'Delete Group', message: 'Are you sure you want to delete this group?', details: 'This action cannot be undone.', type: 'danger', confirmText: 'Delete', cancelText: 'Cancel', onConfirm: () => {
            this._post({ action: 'delete_group', group_id: id }).then(d => {
                if (d.success) { const row = document.getElementById('grouprow-' + id); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); } }
                showToast(d.message || (d.success ? 'Deleted' : 'Error'), d.success ? 'success' : 'error');
            }).catch(() => showToast('Error', 'error'));
        }});
    },

    _purgeGroup(id) {
        showConfirm({ title: 'Purge Group', message: 'Are you sure you want to purge this group?', details: 'This will delete all releases and binaries. Cannot be undone!', type: 'danger', confirmText: 'Purge', cancelText: 'Cancel', onConfirm: () => {
            this._post({ action: 'purge_group', group_id: id }).then(d => showToast(d.message || (d.success ? 'Purged' : 'Error'), d.success ? 'success' : 'error')).catch(() => showToast('Error', 'error'));
        }});
    },

    _resetAll() { this.resetAllOpen = false; this._post({ action: 'reset_all_groups' }).then(d => showToast(d.message || 'Done', d.success ? 'success' : 'error')).catch(() => showToast('Error', 'error')); },
    _purgeAll() { this.purgeAllOpen = false; this._post({ action: 'purge_all_groups' }).then(d => showToast(d.message || 'Done', d.success ? 'success' : 'error')).catch(() => showToast('Error', 'error')); },

    _getSelected() {
        return Array.from(this.$el.querySelectorAll('.group-checkbox:checked')).map(cb => ({ id: cb.dataset.groupId, name: cb.dataset.groupName }));
    },

    _showResetSelected() {
        const selected = this._getSelected();
        if (selected.length === 0) { showToast('No groups selected', 'warning'); return; }
        this.selectedGroupNames = selected.map(g => g.name);
        this.resetSelectedOpen = true;
    },

    _resetSelected() {
        this.resetSelectedOpen = false;
        const selected = this._getSelected();
        if (selected.length === 0) { showToast('No groups selected', 'warning'); return; }
        this._post({ action: 'reset_selected_groups', group_ids: JSON.stringify(selected.map(g => g.id)) }).then(d => {
            if (d.success) { this.$el.querySelectorAll('.group-checkbox:checked').forEach(cb => { cb.checked = false; }); this.allChecked = false; this._updateSelectionUI(); }
            showToast(d.message || 'Done', d.success ? 'success' : 'error');
        }).catch(() => showToast('Error', 'error'));
    },

    _toggleSelectAll(cb) { this.allChecked = cb.checked; this.toggleAllCheckboxes(); },

    _updateSelectionUI() {
        const selected = this._getSelected();
        const counter = document.getElementById('selection-counter');
        const countSpan = document.getElementById('selected-count');
        const resetBtn = document.getElementById('reset-selected-btn');
        if (counter && countSpan) { if (selected.length > 0) { counter.classList.remove('hidden'); countSpan.textContent = selected.length; } else counter.classList.add('hidden'); }
        if (resetBtn) { if (selected.length > 0) resetBtn.classList.remove('hidden'); else resetBtn.classList.add('hidden'); }
        const all = this.$el.querySelectorAll('.group-checkbox');
        const allC = Array.from(all).every(cb => cb.checked);
        const someC = Array.from(all).some(cb => cb.checked);
        this.allChecked = allC;
        const selAll = document.getElementById('select-all-groups');
        if (selAll) { selAll.checked = allC; selAll.indeterminate = someC && !allC; }
    }
}));
