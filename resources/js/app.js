import './bootstrap';

// Import CSP-safe styles
import '../css/csp-safe.css';

// Import Alpine.js CSP-safe components (replaces old csp-safe.js modules)
import './alpine/index.js';

// Theme initialization - optimized to prevent flash of unstyled content
(function() {
    'use strict';
    const applyTheme = function() {
        const metaTheme = document.querySelector('meta[name="theme-preference"]');
        const themePreference = metaTheme?.content || 'light';

        if (themePreference === 'system') {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            if (mediaQuery.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        } else if (themePreference === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    // Apply theme immediately if DOM is ready, otherwise wait
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTheme);
    } else {
        applyTheme();
    }
})();

// FontAwesome icons loaded via CSS webfonts in app.css
// Do NOT import the JS bundle (all.min.js) — it adds ~1.4MB to the build
// and duplicates what the CSS webfont approach already provides.
