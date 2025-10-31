import './bootstrap';

// Import CSP-safe styles and scripts
import '../css/csp-safe.css';
import './csp-safe.js';

// Theme initialization - optimized to prevent flash of unstyled content
(function() {
    'use strict';
    // Use requestAnimationFrame for better performance
    const applyTheme = function() {
        const metaTheme = document.querySelector('meta[name="theme-preference"]');
        const themePreference = metaTheme?.content || 'light';

        if (themePreference === 'system') {
            // Use OS preference with proper media query listener
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

// Import FontAwesome
import '@fortawesome/fontawesome-free/js/all.min.js';

