import assert from 'node:assert/strict';
import test from 'node:test';

import { summarizeSelection } from '../../resources/js/alpine/components/admin/group-selection.js';

const rows = (...checked) => checked.map((isChecked, index) => ({
    id: String(index + 1),
    name: 'alt.binaries.group' + (index + 1),
    checked: isChecked,
}));

/** Mirrors what the Alpine component does to the row checkboxes on a header click. */
const applyToAll = (pageRows, checked) => pageRows.map(row => ({ ...row, checked }));

test('an empty page has nothing selected and an unchecked header', () => {
    const summary = summarizeSelection([]);

    assert.equal(summary.total, 0);
    assert.equal(summary.count, 0);
    assert.deepEqual(summary.ids, []);
    assert.deepEqual(summary.names, []);
    assert.equal(summary.hasSelection, false);
    assert.equal(summary.allChecked, false);
    assert.equal(summary.indeterminate, false);
});

test('no selected rows leaves the header unchecked and determinate', () => {
    const summary = summarizeSelection(rows(false, false, false));

    assert.equal(summary.count, 0);
    assert.equal(summary.hasSelection, false);
    assert.equal(summary.allChecked, false);
    assert.equal(summary.indeterminate, false);
});

test('a partial selection makes the header indeterminate', () => {
    const summary = summarizeSelection(rows(true, false, true));

    assert.equal(summary.total, 3);
    assert.equal(summary.count, 2);
    assert.deepEqual(summary.ids, ['1', '3']);
    assert.deepEqual(summary.names, ['alt.binaries.group1', 'alt.binaries.group3']);
    assert.equal(summary.hasSelection, true);
    assert.equal(summary.allChecked, false);
    assert.equal(summary.indeterminate, true);
});

test('selecting every row checks the header without indeterminate', () => {
    const summary = summarizeSelection(rows(true, true));

    assert.equal(summary.count, 2);
    assert.equal(summary.allChecked, true);
    assert.equal(summary.indeterminate, false);
});

test('clearing one row of a full selection returns the header to indeterminate', () => {
    const full = rows(true, true, true);
    full[1].checked = false;

    const summary = summarizeSelection(full);

    assert.equal(summary.allChecked, false);
    assert.equal(summary.indeterminate, true);
});

test('select all reports every row selected and a checked header', () => {
    const summary = summarizeSelection(applyToAll(rows(false, true, false), true));

    assert.equal(summary.count, 3);
    assert.deepEqual(summary.ids, ['1', '2', '3']);
    assert.equal(summary.allChecked, true);
    assert.equal(summary.indeterminate, false);
});

test('clear all reports nothing selected and an unchecked header', () => {
    const summary = summarizeSelection(applyToAll(rows(true, true, true), false));

    assert.equal(summary.count, 0);
    assert.deepEqual(summary.ids, []);
    assert.equal(summary.hasSelection, false);
    assert.equal(summary.allChecked, false);
    assert.equal(summary.indeterminate, false);
});

test('summaries tolerate a missing row list', () => {
    const summary = summarizeSelection(undefined);

    assert.equal(summary.total, 0);
    assert.equal(summary.hasSelection, false);
});
