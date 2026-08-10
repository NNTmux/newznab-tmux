/**
 * The `adminGroups` component body, kept free of any Alpine import so it can
 * be exercised headlessly. `groups.js` is the thin registration shim.
 *
 * Alpine's `$el` magic resolves to the element the *expression* was evaluated
 * on, not the component root (`magic("el", (el) => el)`), so a method called
 * from a row's `@change` sees that row's input. Every DOM query below goes
 * through the root captured once during `init()` instead.
 */

import { summarizeSelection } from './group-selection.js';

export function adminGroups() {
    return {
        resetAllOpen: false,
        purgeAllOpen: false,
        resetSelectedOpen: false,
        maintenanceOpen: false,
        selectedGroupNames: [],
        selectedCount: 0,
        hasSelection: false,

        init() {
            // `init()` evaluates against the x-data element, but resolve upwards
            // anyway so the root never depends on which element Alpine passed.
            this._root = this.$el.closest?.('[x-data]') ?? this.$el;

            const container = this._root.querySelector('[data-ajax-url]') || this._root;
            this._ajaxUrl = container.dataset.ajaxUrl || '/admin/ajax';
            this._csrf = container.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;

            // Selection is per page: never inherit checkboxes a browser restored
            // across a reload or a back navigation.
            this._applySelectAll(false);

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
            window.toggleSelectAllGroups = function(cb) { self._applySelectAll(cb?.checked); };
            window.getSelectedGroups = function() { return self._getSelected(); };
            window.updateSelectionUI = function() { self._syncSelection(); };
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
                case 'show-reset-modal': this.closeMaintenance(); this.resetAllOpen = true; break;
                case 'hide-reset-modal': this.resetAllOpen = false; break;
                case 'show-purge-modal': this.closeMaintenance(); this.purgeAllOpen = true; break;
                case 'hide-purge-modal': this.purgeAllOpen = false; break;
                case 'show-reset-selected-modal': this._showResetSelected(); break;
                case 'hide-reset-selected-modal': this.resetSelectedOpen = false; break;
            }
        },

        toggleMaintenance() {
            this.maintenanceOpen = ! this.maintenanceOpen;
        },

        closeMaintenance() {
            this.maintenanceOpen = false;
        },

        /**
         * Escape closes the menu and hands focus back to the control that opened it.
         */
        dismissMaintenance() {
            if (! this.maintenanceOpen) { return; }
            this.maintenanceOpen = false;
            this._root.querySelector('#group-maintenance-toggle')?.focus();
        },

        /**
         * Header checkbox: apply its *new* checked value to every row on this page.
         */
        toggleAllCheckboxes() {
            this._applySelectAll(this._selectAllCheckbox()?.checked);
        },

        onGroupCheckboxChange() {
            this._syncSelection();
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
                    if (d.success) { const row = document.getElementById('grouprow-' + id); if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => { row.remove(); this._syncSelection(); }, 300); } }
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

        _rowCheckboxes() {
            return Array.from(this._root.querySelectorAll('.group-checkbox'));
        },

        _selectAllCheckbox() {
            return this._root.querySelector('#select-all-groups');
        },

        /**
         * Snapshot the row checkboxes, which are the authoritative selection state.
         */
        _readRows() {
            return this._rowCheckboxes().map(cb => ({
                id: cb.dataset.groupId,
                name: cb.dataset.groupName,
                checked: cb.checked,
            }));
        },

        _getSelected() {
            return this._readRows().filter(row => row.checked).map(({ id, name }) => ({ id, name }));
        },

        _applySelectAll(checked) {
            const value = Boolean(checked);
            this._rowCheckboxes().forEach(cb => { cb.checked = value; });

            return this._syncSelection();
        },

        /**
         * Derive every piece of selection UI — counter, contextual action, header
         * checked/indeterminate — from the rows that are actually checked.
         */
        _syncSelection() {
            const summary = summarizeSelection(this._readRows());

            this.selectedCount = summary.count;
            this.hasSelection = summary.hasSelection;
            this.selectedGroupNames = summary.names;

            const selectAll = this._selectAllCheckbox();
            if (selectAll) {
                selectAll.checked = summary.allChecked;
                selectAll.indeterminate = summary.indeterminate;
            }

            return summary;
        },

        _showResetSelected() {
            if (! this._syncSelection().hasSelection) { showToast('No groups selected', 'warning'); return; }
            this.resetSelectedOpen = true;
        },

        _resetSelected() {
            this.resetSelectedOpen = false;
            const summary = this._syncSelection();
            if (! summary.hasSelection) { showToast('No groups selected', 'warning'); return; }
            this._post({ action: 'reset_selected_groups', group_ids: JSON.stringify(summary.ids) }).then(d => {
                if (d.success) { this._applySelectAll(false); }
                showToast(d.message || 'Done', d.success ? 'success' : 'error');
            }).catch(() => showToast('Error', 'error'));
        }
    };
}
