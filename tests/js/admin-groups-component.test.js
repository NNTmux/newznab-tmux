import assert from 'node:assert/strict';
import test from 'node:test';

import { adminGroups } from '../../resources/js/alpine/components/admin/groups-component.js';

/**
 * A checkbox stand-in. Only the surface the component touches is modelled:
 * class/id matching, `dataset`, `checked`, `indeterminate`.
 */
class FakeElement {
    constructor({ id = '', classes = [], dataset = {} } = {}) {
        this.id = id;
        this.classes = classes;
        this.dataset = dataset;
        this.checked = false;
        this.indeterminate = false;
        this.focused = false;
        this.classList = { add() {}, remove() {} };
    }

    matches(selector) {
        if (selector.startsWith('#')) { return this.id === selector.slice(1); }
        if (selector.startsWith('.')) { return this.classes.includes(selector.slice(1)); }
        if (selector.startsWith('[') && selector.endsWith(']')) {
            return selector.slice(1, -1) === 'data-ajax-url' && this.dataset.ajaxUrl !== undefined;
        }

        return false;
    }

    closest() {
        return null;
    }

    focus() {
        this.focused = true;
    }
}

class FakeRoot {
    constructor(descendants) {
        this.descendants = descendants;
        this.dataset = { ajaxUrl: '/admin/ajax', csrfToken: 'test-token' };
    }

    querySelectorAll(selector) {
        return this.descendants.filter(node => node.matches(selector));
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null;
    }

    closest(selector) {
        return selector === '[x-data]' ? this : null;
    }
}

/**
 * Build a page and a component wired the way Alpine wires it: `$el` resolves
 * to whichever element the current expression sits on, NOT the component root.
 */
function mountPage(rowCount = 3) {
    const header = new FakeElement({ id: 'select-all-groups' });
    const rows = Array.from({ length: rowCount }, (unused, index) => new FakeElement({
        classes: ['group-checkbox'],
        dataset: {
            groupId: String(index + 1),
            groupName: 'alt.binaries.group' + (index + 1),
            backfillTarget: index === 2 ? '30' : '7',
            minFiles: index === 2 ? '' : '4',
            minSize: index === 2 ? '1073741824' : '104857600',
            active: index === 2 ? '0' : '1',
            backfill: '0',
        },
    }));
    const maintenanceToggle = new FakeElement({ id: 'group-maintenance-toggle' });
    const root = new FakeRoot([header, ...rows, maintenanceToggle]);

    const component = adminGroups();
    let currentElement = root;
    Object.defineProperty(component, '$el', { get: () => currentElement });

    globalThis.window = {};
    globalThis.document = { querySelector: () => null, getElementById: () => null };

    component.init();

    return {
        component,
        root,
        header,
        rows,
        maintenanceToggle,
        /** Simulate Alpine evaluating an expression declared on `element`. */
        firingFrom(element, run) {
            currentElement = element;
            try {
                return run();
            } finally {
                currentElement = root;
            }
        },
    };
}

test('a header click checks every row on the page', () => {
    const page = mountPage();

    page.header.checked = true; // the browser flips this before @change fires
    page.firingFrom(page.header, () => page.component.toggleAllCheckboxes());

    assert.deepEqual(page.rows.map(row => row.checked), [true, true, true]);
    assert.equal(page.header.checked, true);
    assert.equal(page.header.indeterminate, false);
    assert.equal(page.component.selectedCount, 3);
    assert.equal(page.component.hasSelection, true);
});

test('a second header click clears every row and the header', () => {
    const page = mountPage();

    page.header.checked = true;
    page.firingFrom(page.header, () => page.component.toggleAllCheckboxes());

    page.header.checked = false;
    page.firingFrom(page.header, () => page.component.toggleAllCheckboxes());

    assert.deepEqual(page.rows.map(row => row.checked), [false, false, false]);
    assert.equal(page.header.checked, false);
    assert.equal(page.header.indeterminate, false);
    assert.equal(page.component.selectedCount, 0);
    assert.equal(page.component.hasSelection, false);
});

test('selecting one row from that row makes the header indeterminate', () => {
    const page = mountPage();

    page.rows[1].checked = true;
    page.firingFrom(page.rows[1], () => page.component.onGroupCheckboxChange());

    assert.equal(page.header.checked, false);
    assert.equal(page.header.indeterminate, true);
    assert.equal(page.component.selectedCount, 1);
    assert.deepEqual(page.component.selectedGroupNames, ['alt.binaries.group2']);
});

test('selecting every row individually checks the header', () => {
    const page = mountPage();

    page.rows.forEach(row => {
        row.checked = true;
        page.firingFrom(row, () => page.component.onGroupCheckboxChange());
    });

    assert.equal(page.header.checked, true);
    assert.equal(page.header.indeterminate, false);
});

test('clearing one row of a full selection returns the header to indeterminate', () => {
    const page = mountPage();

    page.header.checked = true;
    page.firingFrom(page.header, () => page.component.toggleAllCheckboxes());

    page.rows[0].checked = false;
    page.firingFrom(page.rows[0], () => page.component.onGroupCheckboxChange());

    assert.equal(page.header.checked, false);
    assert.equal(page.header.indeterminate, true);
    assert.equal(page.component.selectedCount, 2);
});

test('the submitted ids match the visibly checked rows', () => {
    const page = mountPage();

    page.rows[0].checked = true;
    page.rows[2].checked = true;
    page.firingFrom(page.rows[2], () => page.component.onGroupCheckboxChange());

    assert.deepEqual(page.component._getSelected(), [
        { id: '1', name: 'alt.binaries.group1' },
        { id: '3', name: 'alt.binaries.group3' },
    ]);
});

test('removing a selected row cannot leave a stale count', () => {
    const page = mountPage();

    page.header.checked = true;
    page.firingFrom(page.header, () => page.component.toggleAllCheckboxes());
    assert.equal(page.component.selectedCount, 3);

    page.root.descendants = page.root.descendants.filter(node => node !== page.rows[0]);
    page.component._syncSelection();

    assert.equal(page.component.selectedCount, 2);
    assert.equal(page.header.checked, true);
});

test('init clears checkboxes a browser restored across a reload', () => {
    const header = new FakeElement({ id: 'select-all-groups' });
    header.checked = true;
    const row = new FakeElement({ classes: ['group-checkbox'], dataset: { groupId: '1', groupName: 'a.b.one' } });
    row.checked = true;
    const root = new FakeRoot([header, row]);

    const component = adminGroups();
    Object.defineProperty(component, '$el', { get: () => root });
    globalThis.window = {};
    globalThis.document = { querySelector: () => null, getElementById: () => null };

    component.init();

    assert.equal(row.checked, false);
    assert.equal(header.checked, false);
    assert.equal(component.hasSelection, false);
});

test('escape returns focus to the maintenance trigger only when the menu is open', () => {
    const page = mountPage();

    page.firingFrom(page.maintenanceToggle, () => page.component.dismissMaintenance());
    assert.equal(page.maintenanceToggle.focused, false, 'a closed menu must not steal focus');

    page.component.toggleMaintenance();
    page.firingFrom(page.maintenanceToggle, () => page.component.dismissMaintenance());

    assert.equal(page.component.maintenanceOpen, false);
    assert.equal(page.maintenanceToggle.focused, true);
});

test('edit selected prefills uniform values and leaves mixed values empty', () => {
    const page = mountPage();
    page.rows.forEach(row => { row.checked = true; });
    page.component._syncSelection();

    page.component.openEditSelected();

    assert.equal(page.component.editBackfillTarget, '');
    assert.equal(page.component.editMinFiles, '');
    assert.equal(page.component.editMinSize, '');
    assert.equal(page.component.editActive, '');
    assert.equal(page.component.editBackfill, '0');
});

test('edit selected sends only values changed since the dialog opened', () => {
    const page = mountPage(2);
    page.rows.forEach(row => { row.checked = true; });
    page.component._syncSelection();
    page.component.openEditSelected();

    assert.deepEqual(page.component.editSelectedChanges(), {});

    page.component.editBackfillTarget = '30';
    page.component.editMinSize = '2.5G';
    page.component.editActive = '0';
    page.component.validateEditSelected();

    assert.deepEqual(page.component.editSelectedChanges(), {
        backfill_target: 30,
        minsizetoformrelease: '2.5G',
        active: 0,
    });
    assert.equal(page.component.canSaveEditSelected(), true);
});

test('edit selected save stays disabled for invalid values or no changes', () => {
    const page = mountPage(2);
    page.rows.forEach(row => { row.checked = true; });
    page.component._syncSelection();
    page.component.openEditSelected();

    assert.equal(page.component.canSaveEditSelected(), false);

    page.component.editBackfillTarget = '07';
    page.component.editMinSize = '100M';
    page.component.validateEditSelected();
    assert.deepEqual(page.component.editSelectedChanges(), {});
    assert.equal(page.component.canSaveEditSelected(), false, 'Equivalent numeric and size spellings are not changes.');

    page.component.editMinFiles = '3000000000';
    page.component.validateEditSelected();
    assert.match(page.component.editMinFilesError, /2,147,483,647/);
    assert.equal(page.component.canSaveEditSelected(), false);

    page.component.editBackfillTarget = '0';
    page.component.validateEditSelected();
    assert.match(page.component.editBackfillTargetError, /between 1 and 7300/);
    assert.equal(page.component.canSaveEditSelected(), false);

    page.component.editBackfillTarget = '30';
    page.component.editMinSize = '10K';
    page.component.validateEditSelected();
    assert.match(page.component.editMinSizeError, /M, MB, G, or GB/);
    assert.equal(page.component.canSaveEditSelected(), false);
});

test('returned rows replace the originals and remain selected', () => {
    const page = mountPage(1);
    page.rows[0].checked = true;
    page.component._syncSelection();

    const replacementCheckbox = new FakeElement({
        classes: ['group-checkbox'],
        dataset: page.rows[0].dataset,
    });
    const replacementRow = {
        classList: { add() {}, remove() {} },
        querySelector: () => replacementCheckbox,
    };
    const currentRow = {
        querySelector: () => page.rows[0],
        replaceWith() {
            page.root.descendants = page.root.descendants.map(node => node === page.rows[0] ? replacementCheckbox : node);
        },
    };
    const originalQuerySelector = page.root.querySelector.bind(page.root);
    page.root.querySelector = selector => selector === '#grouprow-1' ? currentRow : originalQuerySelector(selector);
    globalThis.document.createElement = () => ({
        content: { firstElementChild: replacementRow },
        set innerHTML(value) { this.html = value; },
    });

    page.component._replaceReturnedRows({ 1: '<tr id="grouprow-1"></tr>' }, true);

    assert.equal(replacementCheckbox.checked, true);
    assert.equal(page.component.selectedCount, 1);
});
