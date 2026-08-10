const errorMessage = 'Enter a whole byte count or a number followed by M, MB, G, or GB.';

export function parseGroupFileSize(value) {
    const input = String(value ?? '').trim();

    if (/^\d+$/.test(input)) {
        const bytes = Number(input);
        return Number.isSafeInteger(bytes) ? { bytes, error: '' } : { bytes: null, error: errorMessage };
    }

    const match = input.match(/^(\d+(?:\.\d+)?)\s*([MG])B?$/i);
    if (! match) {
        return { bytes: null, error: errorMessage };
    }

    const multiplier = match[2].toUpperCase() === 'G' ? 1024 ** 3 : 1024 ** 2;
    const bytes = Math.round(Number(match[1]) * multiplier);

    return Number.isSafeInteger(bytes) ? { bytes, error: '' } : { bytes: null, error: errorMessage };
}

export function formatGroupFileSize(bytes) {
    const value = Math.max(0, Number(bytes ?? 0));

    for (const [suffix, multiplier] of [['GB', 1024 ** 3], ['MB', 1024 ** 2]]) {
        if (value >= multiplier) {
            return `${Number((value / multiplier).toFixed(3))}${suffix}`;
        }
    }

    return String(value);
}
