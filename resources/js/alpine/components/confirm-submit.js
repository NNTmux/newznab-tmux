/**
 * Submit a confirmed element while preserving button-specific form attributes.
 */
export function submitConfirmedElement(element) {
    if (element.tagName === 'A') {
        if (element.href) window.location.href = element.href;
        return;
    }

    const form = element.form ?? element.closest('form');
    if (form) form.requestSubmit(element);
}
