/**
 * Profile page module
 * Extracted from csp-safe.js
 */
import { applyTheme, saveThemePreference, themeMediaQuery } from './theme.js';

let downloadsChart = null;
let apiRequestsChart = null;

export function initProfilePage() {
    // Animate progress bars
    animateProgressBars();
}

function animateProgressBars() {
    document.querySelectorAll('.progress-bar').forEach(bar => {
        const width = bar.dataset.width;
        if (width) {
            setTimeout(() => {
                bar.style.width = width + '%';
            }, 100);
        }
    });
}

// Profile edit page functionality
export function initProfileEdit() {
    // 2FA Disable Form Toggle
    const toggleBtn = document.getElementById('toggle-disable-2fa-btn');
    const cancelBtn = document.getElementById('cancel-disable-2fa-btn');
    const formContainer = document.getElementById('disable-2fa-form-container');

    if (toggleBtn && formContainer) {
        toggleBtn.addEventListener('click', function() {
            formContainer.style.display = formContainer.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (cancelBtn && formContainer) {
        cancelBtn.addEventListener('click', function() {
            formContainer.style.display = 'none';
            // Clear password field
            const passwordInput = document.getElementById('disable_2fa_password');
            if (passwordInput) {
                passwordInput.value = '';
            }
        });
    }

    // Theme preference instant preview (user area - profile edit page)
    const allThemeRadios = document.querySelectorAll('input[name="theme_preference"]');

    allThemeRadios.forEach(radio => {
        // Skip if event listener already attached (prevent duplicates)
        if (radio.dataset.themeListenerAttached === 'true') {
            return;
        }
        radio.dataset.themeListenerAttached = 'true';

        radio.addEventListener('change', function() {
            // Prevent event loop if we're programmatically updating
            if (window._updatingThemeUI) {
                return;
            }
            const selectedTheme = this.value;
            applyTheme(selectedTheme);
            saveThemePreference(selectedTheme);
        });
    });
}

// Theme Management (moved from main.blade.php)
export function initThemeManagement() {

    function updateThemeButton(theme) {
        const themeIcon = document.getElementById('theme-icon');
        const themeLabel = document.getElementById('theme-label');
        const themeToggle = document.getElementById('theme-toggle');

        const icons = {
            'light': 'fa-sun',
            'dark': 'fa-moon',
            'system': 'fa-desktop'
        };
        const labels = {
            'light': 'Light',
            'dark': 'Dark',
            'system': 'System'
        };
        const titles = {
            'light': 'Light Mode',
            'dark': 'Dark Mode',
            'system': 'System Mode'
        };

        if (themeIcon) {
            themeIcon.classList.remove('fa-sun', 'fa-moon', 'fa-desktop');
            themeIcon.classList.add(icons[theme]);
        }
        if (themeLabel) {
            themeLabel.textContent = labels[theme];
        }
        if (themeToggle) {
            themeToggle.setAttribute('title', titles[theme]);
        }
    }

    // Get current theme from data attribute or localStorage
    const currentThemeElement = document.getElementById('current-theme-data');
    let currentTheme = currentThemeElement ? currentThemeElement.dataset.theme : 'light';
    const isAuthenticated = currentThemeElement ? currentThemeElement.dataset.authenticated === 'true' : false;

    if (!isAuthenticated) {
        currentTheme = localStorage.getItem('theme') || 'light';
    }

    // Listen for OS theme changes
    themeMediaQuery.addEventListener('change', () => {
        if (currentTheme === 'system') {
            applyTheme('system');
        }
    });

    // Listen for custom theme change events
    document.addEventListener('themeChanged', function(e) {
        if (e.detail && e.detail.theme) {
            updateThemeButton(e.detail.theme);
        }
    });

    // Dark mode toggle (user area - only if not already handled by admin)
    const themeToggle = document.getElementById('theme-toggle');
    // Check if this is admin area by looking for admin-specific elements
    const isAdminArea = document.querySelector('aside.bg-gray-900.dark\\:bg-gray-950') &&
                        document.querySelector('a[href*="admin"]');

    if (themeToggle && !isAdminArea) {
        // Only handle if not in admin area (admin has its own handler in initAdminMenu)
        themeToggle.addEventListener('click', function() {
            const metaTheme = document.querySelector('meta[name="theme-preference"]');
            const currentTheme = metaTheme ? metaTheme.content : localStorage.getItem('theme') || 'light';
            let nextTheme;

            if (currentTheme === 'light') {
                nextTheme = 'dark';
            } else if (currentTheme === 'dark') {
                nextTheme = 'system';
            } else {
                nextTheme = 'light';
            }

            applyTheme(nextTheme);
            saveThemePreference(nextTheme);
        });
    }

    // Mobile sidebar toggle
    document.getElementById('mobile-sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar')?.classList.toggle('hidden');
    });
}

// Copy to Clipboard functionality - consolidated and optimized
export function initCopyToClipboard() {
    // Unified copy function with visual feedback
    function copyToClipboard(text, button) {
        // Try modern Clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback(button);
            }).catch(function() {
                // Fallback for older browsers
                fallbackCopy(text, button);
            });
        } else {
            fallbackCopy(text, button);
        }
    }

    function fallbackCopy(text, button) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        try {
            document.execCommand('copy');
            showCopyFeedback(button);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
        document.body.removeChild(textarea);
    }

    function showCopyFeedback(button) {
        if (!button) return;
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-copy');
            icon.classList.add('fa-check');
        }
        if (button.classList) {
            button.classList.add('text-green-600');
        }
        setTimeout(function() {
            if (!button) return;
            if (icon) {
                icon.classList.remove('fa-check');
                icon.classList.add('fa-copy');
            }
            if (button.classList) {
                button.classList.remove('text-green-600');
            }
        }, 2000);
    }

    // Event delegation for copy buttons
    document.addEventListener('click', function(e) {
        const copyBtn = e.target.closest('.copy-btn');
        if (copyBtn) {
            const targetId = copyBtn.getAttribute('data-copy-target');
            const input = document.getElementById(targetId);
            if (input) {
                e.preventDefault();
                input.select();
                input.setSelectionRange(0, 99999);
                copyToClipboard(input.value, copyBtn);
            }
        }

        // API Token copy button (specific ID)
        const apiTokenBtn = e.target.id === 'copyApiToken' ? e.target : e.target.closest('#copyApiToken');
        if (apiTokenBtn) {
            const input = document.getElementById('apiTokenInput');
            if (input) {
                e.preventDefault();
                input.select();
                input.setSelectionRange(0, 99999);
                copyToClipboard(input.value, apiTokenBtn);
            }
        }
    });
}
