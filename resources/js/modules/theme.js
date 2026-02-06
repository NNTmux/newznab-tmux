// Shared theme management utilities
export const themeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

/**
 * Apply theme preference to the document
 * @param {string} themePreference - 'light', 'dark', or 'system'
 */
export function applyTheme(themePreference) {
    const html = document.documentElement;

    if (themePreference === 'system') {
        if (themeMediaQuery.matches) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
    } else if (themePreference === 'dark') {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
}

/**
 * Update all theme UI elements to reflect the current theme
 * @param {string} themePreference - 'light', 'dark', or 'system'
 */
export function updateAllThemeUI(themePreference) {
    // Update ALL theme selector radio buttons (user area profile edit page)
    // Use a flag to prevent event loops when programmatically updating
    window._updatingThemeUI = true;

    const allThemeRadios = document.querySelectorAll('input[name="theme_preference"]');
    let updatedCount = 0;
    allThemeRadios.forEach(radio => {
        const wasChecked = radio.checked;
        if (radio.value === themePreference) {
            radio.checked = true;
            if (!wasChecked) updatedCount++;
        } else {
            radio.checked = false;
        }
    });

    if (updatedCount > 0) {
        console.log(`Updated ${updatedCount} theme radio button(s) to ${themePreference}`);
    }

    // Update theme toggle button title and icon
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        const titles = {
            'light': 'Theme: Light',
            'dark': 'Theme: Dark',
            'system': 'Theme: System (Auto)'
        };
        themeToggle.setAttribute('title', titles[themePreference] || 'Toggle theme');

        // Update icon if it exists
        const themeIcon = document.getElementById('theme-icon');
        if (themeIcon) {
            const icons = {
                'light': 'fa-sun',
                'dark': 'fa-moon',
                'system': 'fa-desktop'
            };
            themeIcon.classList.remove('fa-sun', 'fa-moon', 'fa-desktop');
            themeIcon.classList.add(icons[themePreference] || 'fa-sun');
        }

        // Update label if it exists
        const themeLabel = document.getElementById('theme-label');
        if (themeLabel) {
            const labels = {
                'light': 'Light',
                'dark': 'Dark',
                'system': 'System'
            };
            themeLabel.textContent = labels[themePreference] || 'Light';
        }
    }

    // Update visual state of theme options
    const themeOptions = document.querySelectorAll('.theme-option');
    themeOptions.forEach(option => {
        const radio = option.querySelector('.theme-radio') || option.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            option.classList.add('theme-option-active');
        } else {
            option.classList.remove('theme-option-active');
        }
    });

    // Update meta tag
    const metaTheme = document.querySelector('meta[name="theme-preference"]');
    if (metaTheme) {
        metaTheme.content = themePreference;
    }

    // Clear the flag after a short delay to allow all updates to complete
    setTimeout(() => {
        window._updatingThemeUI = false;
    }, 100);
}

/**
 * Save theme preference to backend or localStorage
 * @param {string} themePreference - 'light', 'dark', or 'system'
 * @returns {Promise} Promise that resolves when theme is saved
 */
export function saveThemePreference(themePreference) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const updateThemeUrl = document.querySelector('meta[name="update-theme-url"]')?.content;
    const isAuthenticated = document.querySelector('meta[name="user-authenticated"]');

    if (isAuthenticated && isAuthenticated.content === 'true' && updateThemeUrl && csrfToken) {
        return fetch(updateThemeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ theme_preference: themePreference })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  console.log('Theme saved successfully:', themePreference);
                  // Update meta tag immediately
                  const metaTheme = document.querySelector('meta[name="theme-preference"]');
                  if (metaTheme) {
                      metaTheme.content = themePreference;
                      console.log('Meta tag updated to:', themePreference);
                  }
                  updateAllThemeUI(themePreference);
                  // Dispatch custom event for theme change
                  document.dispatchEvent(new CustomEvent('themeChanged', {
                      detail: { theme: themePreference }
                  }));
                  return data;
              } else {
                  console.error('Theme save failed:', data);
              }
          })
          .catch(error => {
              console.error('Error updating theme:', error);
              throw error;
          });
    } else {
        // Save to localStorage for guests
        localStorage.setItem('theme', themePreference);
        updateAllThemeUI(themePreference);
        document.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: themePreference }
        }));
        return Promise.resolve({ success: true });
    }
}

/**
 * Initialize theme system - runs once on page load
 */
export function initThemeSystem() {
    // Get initial theme preference
    const metaTheme = document.querySelector('meta[name="theme-preference"]');
    const isAuthenticated = document.querySelector('meta[name="user-authenticated"]');
    let currentTheme = metaTheme ? metaTheme.content : 'light';

    if (!isAuthenticated || isAuthenticated.content !== 'true') {
        currentTheme = localStorage.getItem('theme') || 'light';
    }

    // Apply initial theme
    applyTheme(currentTheme);
    updateAllThemeUI(currentTheme);

    // Listen for OS theme changes if 'system' is selected
    themeMediaQuery.addEventListener('change', () => {
        const selectedTheme = metaTheme ? metaTheme.content : localStorage.getItem('theme') || 'light';
        if (selectedTheme === 'system') {
            applyTheme('system');
        }
    });

    // Listen for theme changes from any source
    document.addEventListener('themeChanged', function(e) {
        if (e.detail && e.detail.theme) {
            applyTheme(e.detail.theme);
            updateAllThemeUI(e.detail.theme);
        }
    });
}
