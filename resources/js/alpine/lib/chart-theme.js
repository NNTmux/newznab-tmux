/**
 * Chart.js theming from the app's CSS tokens, so charts follow light/dark mode and the
 * user's color scheme. Tailwind palettes are oklch(), which Chart.js cannot parse, so every
 * color is converted to rgba() through a 1×1 canvas.
 */

/** Series tones → CSS variable, with a hex fallback when the variable is not emitted. */
export const SERIES_TONES = {
    primary: ['--color-primary-500', '#3b82f6'],
    success: ['--color-success-500', '#10b981'],
    warning: ['--color-warning-500', '#f59e0b'],
    danger: ['--color-danger-500', '#ef4444'],
    info: ['--color-info-500', '#0ea5e9'],
    violet: ['--color-violet-500', '#8b5cf6'],
    orange: ['--color-orange-500', '#f97316'],
    cyan: ['--color-cyan-500', '#06b6d4'],
};

let probe = null;

export function readToken(name, fallback = '') {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return value || fallback;
}

/** Convert any CSS color to rgba(r, g, b, alpha); returns null when the browser cannot parse it. */
export function toRgba(color, alpha = 1) {
    const hex = /^#([0-9a-f]{6})$/i.exec(color);
    if (hex) {
        const n = parseInt(hex[1], 16);
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    }
    try {
        probe ||= document.createElement('canvas').getContext('2d', { willReadFrequently: true });
        const sentinel = '#010203';
        probe.fillStyle = sentinel;
        probe.fillStyle = color;
        if (String(probe.fillStyle).toLowerCase() === sentinel) return null;
        probe.clearRect(0, 0, 1, 1);
        probe.fillRect(0, 0, 1, 1);
        const [r, g, b] = probe.getImageData(0, 0, 1, 1).data;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    } catch (_error) {
        return null;
    }
}

/** Line/fill colors for a series tone. */
export function seriesColors(tone, fillAlpha = 0.2) {
    const [variable, fallback] = SERIES_TONES[tone] ?? SERIES_TONES.primary;
    const base = readToken(variable, fallback);
    return {
        borderColor: toRgba(base, 1) ?? toRgba(fallback, 1),
        backgroundColor: toRgba(base, fillAlpha) ?? toRgba(fallback, fillAlpha),
    };
}

/** Axis text, grid lines and font from the theme tokens. */
export function applyChartDefaults(Chart) {
    Chart.defaults.color = toRgba(readToken('--ui-fg-muted', '#64748b'), 1) ?? '#64748b';
    Chart.defaults.borderColor = toRgba(readToken('--ui-border', '#e2e8f0'), 0.8) ?? 'rgba(148, 163, 184, 0.3)';
    Chart.defaults.font.family = readToken('--font-sans', 'system-ui, sans-serif');
}

/** Re-theme registered charts whenever the theme or color scheme changes. Returns a disconnect function. */
export function observeTheme(Chart, getCharts) {
    const observer = new MutationObserver(() => {
        applyChartDefaults(Chart);
        getCharts().forEach((chart) => {
            const tone = chart.$themeTone;
            if (tone) {
                Object.assign(chart.data.datasets[0], seriesColors(tone.name, tone.fillAlpha));
            }
            chart.update('none');
        });
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-color-scheme'] });
    return () => observer.disconnect();
}
