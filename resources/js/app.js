import './bootstrap';

// Import CSP-safe styles and scripts
import '../css/csp-safe.css';
import './csp-safe.js';

// Theme initialization - must run early to prevent flash
(function() {
    const themePreference = document.querySelector('meta[name="theme-preference"]')?.content || 'light';

    if (themePreference === 'system') {
        // Use OS preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    } else if (themePreference === 'dark') {
        document.documentElement.classList.add('dark');
    }
})();

// Import FontAwesome
import '@fortawesome/fontawesome-free/js/all.min.js';

