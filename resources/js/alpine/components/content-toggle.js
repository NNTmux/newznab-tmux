/**
 * Alpine.data('contentToggle') - Admin content enable/disable toggle
 * Alpine.data('contentDelete') - Admin content delete with confirmation
 */
import Alpine from '@alpinejs/csp';

Alpine.data('contentToggle', () => ({
    dragSourceRow: null,
    dragSourceBody: null,
    dragSourceContentType: null,
    originalOrder: [],
    dropCompleted: false,
    isSavingOrder: false,

    init() {
        this.$nextTick(() => this.initializeDragAndDrop());
    },

    getContentGroups() {
        return Array.from(this.$root.querySelectorAll('[data-content-group]'));
    },

    initializeDragAndDrop() {
        this.getContentGroups().forEach(group => {
            const tableBody = group.querySelector('tbody');

            if (!tableBody || tableBody.dataset.dragInitialized === 'true') {
                return;
            }

            tableBody.dataset.dragInitialized = 'true';
            tableBody.addEventListener('dragstart', event => this.onDragStart(event));
            tableBody.addEventListener('dragover', event => this.onDragOver(event));
            tableBody.addEventListener('dragenter', event => {
                if (this.dragSourceRow && event.currentTarget === this.dragSourceBody) {
                    event.preventDefault();
                }
            });
            tableBody.addEventListener('drop', event => this.onDrop(event));
            tableBody.addEventListener('dragend', () => this.onDragEnd());
        });
    },

    onDragStart(event) {
        const handle = event.target.closest('[data-drag-handle]');
        const row = handle?.closest('tr[data-content-id]');

        if (!handle || !row || this.isSavingOrder) {
            event.preventDefault();
            return;
        }

        const tableBody = row.closest('tbody');

        this.dragSourceRow = row;
        this.dragSourceBody = tableBody;
        this.dragSourceContentType = row.dataset.contentType || null;
        this.originalOrder = this.getOrderedIds(tableBody);
        this.dropCompleted = false;

        row.classList.add('opacity-60', 'is-dragging');
        handle.classList.remove('cursor-grab');
        handle.classList.add('cursor-grabbing');

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.contentId || '');
        }
    },

    onDragOver(event) {
        const tableBody = event.currentTarget;

        if (!this.dragSourceRow || tableBody !== this.dragSourceBody) {
            return;
        }

        event.preventDefault();

        const nextRow = this.getRowAfterCursor(tableBody, event.clientY);

        if (nextRow === null) {
            tableBody.appendChild(this.dragSourceRow);

            return;
        }

        tableBody.insertBefore(this.dragSourceRow, nextRow);
    },

    onDrop(event) {
        const tableBody = event.currentTarget;

        if (!this.dragSourceRow || tableBody !== this.dragSourceBody || !this.dragSourceContentType) {
            return;
        }

        event.preventDefault();
        this.dropCompleted = true;
        this.persistOrder(tableBody, this.dragSourceContentType, [...this.originalOrder]);
    },

    onDragEnd() {
        if (this.dragSourceRow && this.dragSourceBody && !this.dropCompleted) {
            this.restoreOrder(this.dragSourceBody, this.originalOrder);
            this.originalOrder = [];
        }

        const handle = this.dragSourceRow?.querySelector('[data-drag-handle]');
        this.dragSourceRow?.classList.remove('opacity-60', 'is-dragging');
        handle?.classList.remove('cursor-grabbing');
        handle?.classList.add('cursor-grab');

        this.dragSourceRow = null;
        this.dragSourceBody = null;
        this.dragSourceContentType = null;
        this.dropCompleted = false;
    },

    getRowAfterCursor(tableBody, clientY) {
        const rows = Array.from(tableBody?.querySelectorAll('tr[data-content-id]:not(.is-dragging)') || []);

        return rows.reduce((closestRow, row) => {
            const box = row.getBoundingClientRect();
            const offset = clientY - box.top - (box.height / 2);

            if (offset < 0 && offset > closestRow.offset) {
                return { offset, element: row };
            }

            return closestRow;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    },

    getOrderedIds(tableBody) {
        return Array.from(tableBody?.querySelectorAll('tr[data-content-id]') || [])
            .map(row => row.dataset.contentId)
            .filter(Boolean);
    },

    restoreOrder(tableBody, orderedIds) {
        if (!tableBody || orderedIds.length === 0) {
            return;
        }

        const rowsById = new Map(
            Array.from(tableBody.querySelectorAll('tr[data-content-id]')).map(row => [row.dataset.contentId, row])
        );

        orderedIds.forEach(contentId => {
            const row = rowsById.get(contentId);

            if (row) {
                tableBody.appendChild(row);
            }
        });
    },

    persistOrder(tableBody, contentType, previousOrder) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const orderedIds = this.getOrderedIds(tableBody).map(Number);
        const submittedOrder = orderedIds.map(String);

        if (!csrf || orderedIds.length === 0) {
            this.restoreOrder(tableBody, previousOrder);
            showToast('Error: Missing required data', 'error');
            return;
        }

        this.isSavingOrder = true;

        fetch('/admin/content-reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ contenttype: Number(contentType), ordered_ids: orderedIds })
        })
        .then(async response => {
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to reorder content');
            }

            this.updateOrdinalCells(tableBody, data.ordinals || {});
            this.originalOrder = submittedOrder;
            showToast(data.message || 'Content order updated successfully', 'success');
        })
        .catch(error => {
            this.restoreOrder(tableBody, previousOrder);
            showToast(error.message || 'An error occurred while updating content order', 'error');
        })
        .finally(() => {
            this.isSavingOrder = false;
        });
    },

    updateOrdinalCells(tableBody, ordinals) {
        if (!tableBody) {
            return;
        }

        Array.from(tableBody.querySelectorAll('tr[data-content-id]')).forEach(row => {
            const ordinalCell = row.querySelector('[data-ordinal-cell]');
            const ordinal = ordinals[row.dataset.contentId];

            if (ordinalCell && ordinal !== undefined) {
                ordinalCell.textContent = ordinal;
                row.dataset.ordinal = ordinal;
            }
        });
    },

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
                const statusCell = row.querySelector('[data-status-cell]');
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
            onConfirm: () => {
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
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                const tbody = row.closest('tbody');
                                if (tbody && tbody.children.length === 0) {
                                    location.reload();
                                }
                            }, 300);
                        }
                        showToast(data.message, 'success');
                    } else { showToast(data.message || 'Failed to delete content', 'error'); el.disabled = false; el.style.opacity = '1'; }
                })
                .catch(() => { showToast('An error occurred while deleting content', 'error'); el.disabled = false; el.style.opacity = '1'; });
            }
        });
    }
}));
