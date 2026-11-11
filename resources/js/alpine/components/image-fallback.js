/**
 * Broken-image handling for every page.
 *
 * <img data-fallback-src="..."> swaps to the fallback once, and <img data-hide-on-error> is hidden,
 * when the image fails to load. `error` does not bubble, so this listens in the capture phase,
 * which also covers images added later (season tabs, modals). Images that failed before this
 * module ran are picked up by the initial sweep.
 */
function handleBrokenImage(img) {
    const fallback = img.getAttribute('data-fallback-src');
    if (fallback && img.dataset.fallbackApplied !== '1') {
        img.dataset.fallbackApplied = '1';
        img.src = fallback;
        return;
    }
    if (img.hasAttribute('data-hide-on-error')) {
        img.style.display = 'none';
    }
}

document.addEventListener('error', (event) => {
    const img = event.target;
    if (img instanceof HTMLImageElement && (img.hasAttribute('data-fallback-src') || img.hasAttribute('data-hide-on-error'))) {
        handleBrokenImage(img);
    }
}, true);

document.querySelectorAll('img[data-fallback-src], img[data-hide-on-error]').forEach((img) => {
    if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
        handleBrokenImage(img);
    }
});
