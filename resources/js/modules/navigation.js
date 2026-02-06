/**
 * Navigation-related functions extracted from csp-safe.js
 */

import { applyTheme, saveThemePreference } from './theme.js';

// Admin Menu
export function initAdminMenu() {
    window.toggleAdminSubmenu = function(id) {
        const submenu = document.getElementById(id);
        const icon = document.getElementById(id + '-icon');
        if (submenu) {
            submenu.classList.toggle('hidden');
        }
        if (icon) {
            icon.classList.toggle('rotate-180');
        }
    };

    // Add click handlers for admin menu buttons
    document.addEventListener('click', function(e) {
        const menuButton = e.target.closest('[data-toggle-submenu]');
        if (menuButton) {
            const menuId = menuButton.getAttribute('data-toggle-submenu');
            if (menuId) {
                toggleAdminSubmenu(menuId);
            }
        }
    });

    // Theme toggle button handler - cycles through light -> dark -> system
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const metaTheme = document.querySelector('meta[name="theme-preference"]');
            const currentTheme = metaTheme ? metaTheme.content : localStorage.getItem('theme') || 'light';
            let nextTheme;

            // Cycle through: light -> dark -> system -> light
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

}

// Sidebar Toggle functionality for regular user sidebar
export function initSidebarToggle() {
    const sidebarToggles = document.querySelectorAll('.sidebar-toggle');

    sidebarToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const submenu = document.getElementById(targetId);
            const chevron = this.querySelector('.fa-chevron-down');

            if (submenu) {
                submenu.classList.toggle('hidden');
                if (chevron) {
                    chevron.classList.toggle('rotate-180');
                }
            }
        });
    });

    // Handle logout link in sidebar
    const sidebarLogoutLink = document.querySelector('[data-logout]');
    const sidebarLogoutForm = document.getElementById('sidebar-logout-form');

    if (sidebarLogoutLink && sidebarLogoutForm) {
        sidebarLogoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            sidebarLogoutForm.submit();
        });
    }
}

// Dropdown Menus for Header Navigation
export function initDropdownMenus() {
    const dropdownContainers = document.querySelectorAll('.dropdown-container');

    dropdownContainers.forEach(function(container) {
        const toggle = container.querySelector('.dropdown-toggle');
        const menu = container.querySelector('.dropdown-menu');

        if (!toggle || !menu) return;

        let closeTimeout;

        // Ensure menu is hidden initially
        menu.style.display = 'none';

        // Toggle on click
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const isCurrentlyOpen = menu.style.display === 'block';

            // Close all other dropdowns
            dropdownContainers.forEach(function(otherContainer) {
                if (otherContainer !== container) {
                    const otherMenu = otherContainer.querySelector('.dropdown-menu');
                    if (otherMenu) {
                        otherMenu.style.display = 'none';
                    }
                }
            });

            // Toggle this dropdown
            if (isCurrentlyOpen) {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'block';
            }
        });

        // Keep open on hover over the container
        container.addEventListener('mouseenter', function() {
            clearTimeout(closeTimeout);
        });

        // Close after delay when leaving the container
        container.addEventListener('mouseleave', function() {
            closeTimeout = setTimeout(function() {
                menu.style.display = 'none';
            }, 300);
        });

        // Prevent closing when hovering over the menu itself
        menu.addEventListener('mouseenter', function() {
            clearTimeout(closeTimeout);
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-container')) {
            dropdownContainers.forEach(function(container) {
                const menu = container.querySelector('.dropdown-menu');
                if (menu) {
                    menu.style.display = 'none';
                }
            });
        }
    });

    // Handle nested submenus (e.g., Foreign languages dropdown)
    const submenuContainers = document.querySelectorAll('.submenu-container');
    submenuContainers.forEach(function(container) {
        const submenu = container.querySelector('.submenu');
        if (!submenu) return;

        let submenuCloseTimeout;

        container.addEventListener('mouseenter', function() {
            clearTimeout(submenuCloseTimeout);
            submenu.style.display = 'block';
        });

        container.addEventListener('mouseleave', function() {
            submenuCloseTimeout = setTimeout(function() {
                submenu.style.display = 'none';
            }, 200);
        });

        submenu.addEventListener('mouseenter', function() {
            clearTimeout(submenuCloseTimeout);
        });
    });

    // Mobile menu toggle - opens/closes the mobile nav panel
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mobileNavPanel = document.getElementById('mobile-nav-panel');
    const mobileMenuIconOpen = document.getElementById('mobile-menu-icon-open');
    const mobileMenuIconClose = document.getElementById('mobile-menu-icon-close');

    if (mobileMenuToggle && mobileNavPanel) {
        mobileMenuToggle.addEventListener('click', function() {
            const isOpen = !mobileNavPanel.classList.contains('hidden');

            if (isOpen) {
                mobileNavPanel.classList.add('hidden');
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
            } else {
                mobileNavPanel.classList.remove('hidden');
                mobileMenuToggle.setAttribute('aria-expanded', 'true');
                // Close mobile search when opening nav
                const mobileSearchForm = document.getElementById('mobile-search-form');
                if (mobileSearchForm) mobileSearchForm.classList.add('hidden');
            }

            // Toggle hamburger / X icons
            if (mobileMenuIconOpen && mobileMenuIconClose) {
                mobileMenuIconOpen.classList.toggle('hidden');
                mobileMenuIconClose.classList.toggle('hidden');
            }
        });
    }

    // Mobile nav accordion toggles (category expand/collapse)
    document.querySelectorAll('.mobile-nav-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const section = this.closest('.mobile-nav-section');
            if (!section) return;

            const submenu = section.querySelector('.mobile-nav-submenu');
            const chevron = this.querySelector('.mobile-nav-chevron');

            if (submenu) {
                submenu.classList.toggle('hidden');
            }
            if (chevron) {
                chevron.classList.toggle('rotate-180');
            }
        });
    });

    // Mobile search toggle
    const mobileSearchToggle = document.getElementById('mobile-search-toggle');
    const mobileSearchForm = document.getElementById('mobile-search-form');
    if (mobileSearchToggle && mobileSearchForm) {
        mobileSearchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            mobileSearchForm.classList.toggle('hidden');
            // Close mobile nav when opening search
            if (mobileNavPanel && !mobileSearchForm.classList.contains('hidden')) {
                mobileNavPanel.classList.add('hidden');
                if (mobileMenuIconOpen && mobileMenuIconClose) {
                    mobileMenuIconOpen.classList.remove('hidden');
                    mobileMenuIconClose.classList.add('hidden');
                }
                if (mobileMenuToggle) mobileMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Close mobile panels on window resize past lg breakpoint
    function closeMobilePanelsOnResize() {
        if (window.innerWidth >= 1024) {
            if (mobileNavPanel) mobileNavPanel.classList.add('hidden');
            if (mobileSearchForm) mobileSearchForm.classList.add('hidden');
            if (mobileMenuIconOpen && mobileMenuIconClose) {
                mobileMenuIconOpen.classList.remove('hidden');
                mobileMenuIconClose.classList.add('hidden');
            }
            if (mobileMenuToggle) mobileMenuToggle.setAttribute('aria-expanded', 'false');
        }
    }
    window.addEventListener('resize', closeMobilePanelsOnResize);
}

// Mobile enhancements
export function initMobileEnhancements() {
    // Handle mobile-specific enhancements
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('flex');
            }
        });
    }
}
