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
import { formatGroupFileSize, parseGroupFileSize } from './group-file-size.js';

export function adminGroups() {
    return {
        resetAllOpen: false,
        purgeAllOpen: false,
        resetSelectedOpen: false,
        editSelectedOpen: false,
        editSelectedEditing: true,
        editSelectedConfirming: false,
        maintenanceOpen: false,
        selectedGroupNames: [],
        selectedCount: 0,
        hasSelection: false,
        editBackfillTarget: '',
        editMinFiles: '',
        editMinSize: '',
        editActive: '',
        editBackfill: '',
        editBackfillTargetError: '',
        editMinFilesError: '',
        editMinSizeError: '',
        editMinSizeReadout: '',
        editSaveDisabled: true,
        editConfirmationChanges: [],
        editConfirmationGroupNames: [],

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
            window.showEditSelectedModal = function() { self.openEditSelected(); };
            window.hideEditSelectedModal = function() { self.closeEditSelected(); };
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
                case 'show-edit-selected-modal': this.openEditSelected(); break;
                case 'hide-edit-selected-modal': this.closeEditSelected(); break;
                case 'confirm-edit-selected': this.confirmEditSelected(); break;
                case 'back-to-edit-selected': this.backToEditSelected(); break;
                case 'save-edit-selected': this.saveEditSelected(); break;
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
                    if (data.row) { this._replaceReturnedRows({ [id]: data.row }, false); }
                    showToast(data.message || 'Group status updated', 'success');
                } else showToast(data.message || 'Error', 'error');
            }).catch(() => showToast('Error updating group status', 'error'));
        },

        _toggleBackfill(id, status) {
            this._post({ action: 'toggle_group_backfill', group_id: id, backfill: status }).then(data => {
                if (data.success) {
                    if (data.row) { this._replaceReturnedRows({ [id]: data.row }, false); }
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
                backfillTarget: cb.dataset.backfillTarget,
                minFiles: cb.dataset.minFiles,
                minSize: cb.dataset.minSize,
                active: cb.dataset.active,
                backfill: cb.dataset.backfill,
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
        },

        openEditSelected() {
            const selectedRows = this._readRows().filter(row => row.checked);
            if (selectedRows.length === 0) { showToast('No groups selected', 'warning'); return; }

            this._editSelectedTrigger = document.activeElement;
            this.editBackfillTarget = this._uniformValue(selectedRows, 'backfillTarget');
            this.editMinFiles = this._uniformValue(selectedRows, 'minFiles');
            const uniformSize = this._uniformValue(selectedRows, 'minSize');
            this.editMinSize = uniformSize === '' ? '' : formatGroupFileSize(uniformSize);
            this.editActive = this._uniformValue(selectedRows, 'active');
            this.editBackfill = this._uniformValue(selectedRows, 'backfill');
            this._editSelectedOriginal = this._editSelectedValues();
            this._editSelectedOriginalNormalized = this._normalizedEditSelectedValues(this._editSelectedOriginal);
            this.editSelectedEditing = true;
            this.editSelectedConfirming = false;
            this.editSelectedOpen = true;
            this.validateEditSelected();
            setTimeout(() => this._root.querySelector('#edit-selected-backfill-target')?.focus(), 0);
        },

        closeEditSelected() {
            if (! this.editSelectedOpen) { return; }
            this.editSelectedOpen = false;
            this.editSelectedEditing = true;
            this.editSelectedConfirming = false;
            this._editSelectedTrigger?.focus?.();
        },

        validateEditSelected() {
            this.editBackfillTargetError = this._integerError(this.editBackfillTarget, 1, 7300, 'Backfill Days must be a whole number between 1 and 7300.');
            this.editMinFilesError = this._integerError(this.editMinFiles, 0, 2147483647, 'Minimum Files must be a whole number between 0 and 2,147,483,647.');

            if (this.editMinSize === '') {
                this.editMinSizeError = '';
                this.editMinSizeReadout = '';
            } else {
                const parsed = parseGroupFileSize(this.editMinSize);
                this.editMinSizeError = parsed.error;
                this.editMinSizeReadout = parsed.error ? '' : `${parsed.bytes.toLocaleString()} bytes`;
            }

            this.editSaveDisabled = ! this.canSaveEditSelected();
        },

        canSaveEditSelected() {
            return ! this.editBackfillTargetError
                && ! this.editMinFilesError
                && ! this.editMinSizeError
                && Object.keys(this.editSelectedChanges()).length > 0;
        },

        editSelectedChanges() {
            const current = this._editSelectedValues();
            const original = this._editSelectedOriginal ?? current;
            const currentNormalized = this._normalizedEditSelectedValues(current);
            const originalNormalized = this._editSelectedOriginalNormalized ?? this._normalizedEditSelectedValues(original);
            const changes = {};

            if (currentNormalized.backfillTarget !== originalNormalized.backfillTarget && current.backfillTarget !== '') {
                changes.backfill_target = Number(current.backfillTarget);
            }
            if (currentNormalized.minFiles !== originalNormalized.minFiles && current.minFiles !== '') {
                changes.minfilestoformrelease = Number(current.minFiles);
            }
            if (currentNormalized.minSize !== originalNormalized.minSize && current.minSize !== '') {
                changes.minsizetoformrelease = current.minSize;
            }
            if (currentNormalized.active !== originalNormalized.active && current.active !== '') {
                changes.active = Number(current.active);
            }
            if (currentNormalized.backfill !== originalNormalized.backfill && current.backfill !== '') {
                changes.backfill = Number(current.backfill);
            }

            return changes;
        },

        confirmEditSelected() {
            this.validateEditSelected();
            if (this.editSaveDisabled) { return; }

            const changes = this.editSelectedChanges();
            const labels = {
                backfill_target: 'Backfill Days',
                minfilestoformrelease: 'Minimum Files to Form Release',
                minsizetoformrelease: 'Minimum File Size',
                active: 'Active',
                backfill: 'Backfill',
            };

            this.editConfirmationChanges = Object.entries(changes).map(([key, value]) => {
                let display = value;
                if (key === 'active' || key === 'backfill') { display = value === 1 ? 'Enabled' : 'Disabled'; }
                if (key === 'minsizetoformrelease') {
                    const parsed = parseGroupFileSize(value);
                    display = `${value} (${parsed.bytes.toLocaleString()} bytes)`;
                }
                return { key, label: labels[key], value: display };
            });
            this.editConfirmationGroupNames = this.selectedGroupNames.slice(0, 5);
            this.editSelectedEditing = false;
            this.editSelectedConfirming = true;
        },

        backToEditSelected() {
            this.editSelectedEditing = true;
            this.editSelectedConfirming = false;
        },

        saveEditSelected() {
            const summary = this._syncSelection();
            if (! summary.hasSelection) { this.closeEditSelected(); showToast('No groups selected', 'warning'); return; }

            this._post({
                action: 'edit_selected_groups',
                group_ids: JSON.stringify(summary.ids),
                changes: JSON.stringify(this.editSelectedChanges()),
            }).then(data => {
                if (! data.success) { showToast(data.message || 'Error updating selected groups', 'error'); return; }

                this._replaceReturnedRows(data.rows ?? {}, true);
                this.closeEditSelected();
                showToast(data.message || 'Selected groups updated', 'success');
            }).catch(() => showToast('Error updating selected groups', 'error'));
        },

        _uniformValue(rows, key) {
            const first = rows[0][key] ?? '';
            return rows.every(row => (row[key] ?? '') === first) ? first : '';
        },

        _editSelectedValues() {
            return {
                backfillTarget: String(this.editBackfillTarget).trim(),
                minFiles: String(this.editMinFiles).trim(),
                minSize: String(this.editMinSize).trim(),
                active: String(this.editActive),
                backfill: String(this.editBackfill),
            };
        },

        _normalizedEditSelectedValues(values) {
            const parsedSize = values.minSize === '' ? null : parseGroupFileSize(values.minSize);

            return {
                backfillTarget: values.backfillTarget === '' ? '' : String(Number(values.backfillTarget)),
                minFiles: values.minFiles === '' ? '' : String(Number(values.minFiles)),
                minSize: values.minSize === '' || parsedSize.error ? values.minSize : String(parsedSize.bytes),
                active: values.active,
                backfill: values.backfill,
            };
        },

        _integerError(value, minimum, maximum, message) {
            const input = String(value).trim();
            if (input === '') { return ''; }
            if (! /^\d+$/.test(input)) { return message; }
            const parsed = Number(input);
            return Number.isSafeInteger(parsed) && parsed >= minimum && parsed <= maximum ? '' : message;
        },

        _replaceReturnedRows(rows, keepSelected) {
            for (const [id, html] of Object.entries(rows)) {
                const currentRow = this._root.querySelector(`#grouprow-${id}`);
                if (! currentRow || ! document.createElement) { continue; }
                const wasSelected = Boolean(currentRow.querySelector?.('.group-checkbox')?.checked);

                const template = document.createElement('template');
                template.innerHTML = String(html).trim();
                const replacement = template.content.firstElementChild;
                if (! replacement) { continue; }

                currentRow.replaceWith(replacement);
                window.Alpine?.initTree?.(replacement);
                const checkbox = replacement.querySelector('.group-checkbox');
                if (checkbox) { checkbox.checked = Boolean(keepSelected || wasSelected); }
                replacement.classList.add('group-row-updated');
                setTimeout(() => replacement.classList.remove('group-row-updated'), 1600);
            }

            this._syncSelection();
        }
    };
}
