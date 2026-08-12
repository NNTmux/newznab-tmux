import assert from 'node:assert/strict';
import test from 'node:test';

import { submitConfirmedElement } from '../../resources/js/alpine/components/confirm-submit.js';

test('confirmed submit buttons preserve their submitter-specific form action', () => {
    const form = {
        submittedWith: null,
        requestSubmit(submitter) {
            this.submittedWith = submitter;
        },
    };
    const button = {
        tagName: 'BUTTON',
        form,
        closest: () => null,
    };

    submitConfirmedElement(button);

    assert.equal(form.submittedWith, button);
});
