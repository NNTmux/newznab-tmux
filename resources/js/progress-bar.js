/**
 * Animate .progress-bar elements on any page: width transitions from the
 * csp-safe.css starting width of 0 to the element's data-width percentage.
 * CSP-safe replacement for inline style="width: X%" attributes.
 */
function animateProgressBars() {
    document.querySelectorAll('.progress-bar').forEach(function (bar) {
        var w = bar.dataset.width;
        if (w) setTimeout(function () { bar.style.width = w + '%'; }, 100);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateProgressBars);
} else {
    animateProgressBars();
}
