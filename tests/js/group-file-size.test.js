import assert from 'node:assert/strict';
import test from 'node:test';

import { formatGroupFileSize, parseGroupFileSize } from '../../resources/js/alpine/components/admin/group-file-size.js';

const validCases = [
    ['12345', 12345],
    ['100M', 104857600],
    ['2.5G', 2684354560],
    ['100MB', 104857600],
    [' 100mB ', 104857600],
    ['0', 0],
];

for (const [input, expected] of validCases) {
    test(`parses ${input} as ${expected} bytes`, () => {
        assert.deepEqual(parseGroupFileSize(input), { bytes: expected, error: '' });
    });
}

for (const input of ['', '10K', '2.5', '-1M', 'large']) {
    test(`rejects unsupported size ${input || '(empty)'}`, () => {
        assert.match(parseGroupFileSize(input).error, /whole byte count or a number followed by M, MB, G, or GB/);
    });
}

test('formats byte values for editing', () => {
    assert.equal(formatGroupFileSize(104857600), '100MB');
    assert.equal(formatGroupFileSize(2684354560), '2.5GB');
    assert.equal(formatGroupFileSize(12345), '12345');
    assert.equal(formatGroupFileSize(null), '0');
});
