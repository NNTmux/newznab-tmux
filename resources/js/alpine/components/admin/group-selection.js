/**
 * Pure selection-state helpers for the admin Group List page.
 *
 * The row checkboxes remain the authoritative selection state; these helpers
 * turn a snapshot of them into the summary the page renders, so the header
 * checkbox is always derived from the rows instead of being tracked
 * separately and drifting out of sync.
 */

/**
 * @typedef {object} GroupSelectionRow
 * @property {string} id
 * @property {string} name
 * @property {boolean} checked
 */

/**
 * @typedef {object} GroupSelectionSummary
 * @property {number} total Selectable rows on the current page.
 * @property {number} count Selected rows on the current page.
 * @property {string[]} ids Selected group ids, in row order.
 * @property {string[]} names Selected group names, in row order.
 * @property {boolean} hasSelection
 * @property {boolean} allChecked
 * @property {boolean} indeterminate
 */

/**
 * Derive every piece of selection UI state from the current rows.
 *
 * @param {GroupSelectionRow[]|undefined|null} rows
 * @returns {GroupSelectionSummary}
 */
export function summarizeSelection(rows) {
    const all = Array.isArray(rows) ? rows : [];
    const selected = all.filter(row => row.checked);

    return {
        total: all.length,
        count: selected.length,
        ids: selected.map(row => row.id),
        names: selected.map(row => row.name),
        hasSelection: selected.length > 0,
        allChecked: all.length > 0 && selected.length === all.length,
        indeterminate: selected.length > 0 && selected.length < all.length,
    };
}
