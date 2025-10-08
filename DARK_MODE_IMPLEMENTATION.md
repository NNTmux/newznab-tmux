# Dark Mode Implementation Guide

## Overview
This document outlines the comprehensive dark/light mode implementation added to the NNTmux application.

## What Was Implemented

### 1. Core Dark Mode Setup

#### Tailwind Configuration (`tailwind.config.js`)
- Enabled class-based dark mode strategy
- This allows toggling dark mode by adding/removing the `dark` class from the `<html>` element

#### Main Layout (`resources/views/layouts/main.blade.php`)
- Added inline script to prevent flash of unstyled content (FOUC)
- Theme preference is stored in localStorage
- Dark mode toggle button added to bottom-left corner (sun/moon icons)
- Updated all base colors to support dark variants

### 2. Dark Mode Toggle Features
- **Toggle Button**: Fixed position button in bottom-left corner
- **Icons**: Sun icon for light mode, moon icon for dark mode
- **Persistence**: Theme preference saved to localStorage
- **Auto-load**: Theme applied before page render to prevent flashing

### 3. Components Updated

#### Admin Area (`resources/views/layouts/admin.blade.php`)
- Admin layout with dark mode initialization
- Admin sidebar navigation
- Top header bar with logout and "Back to Site" links
- Success/error alert messages
- Dark mode toggle button (shared with main site)

#### Admin Menu (`resources/views/partials/admin-menu.blade.php`)
- All menu items and submenu items
- Consistent hover states
- Accordion-style menu sections

#### Admin Dashboard (`resources/views/admin/dashboard.blade.php`)
- Statistics cards with icons
- System status section
- All badges and indicators

#### Admin User List (`resources/views/admin/user-list.blade.php`)
- Page header with action buttons
- Search/filter form with inputs and selects
- Data table (headers and rows)
- User status badges (verified, role)
- Action buttons (edit, verify, delete)

#### Browse Index Page (`resources/views/browse/index.blade.php`)
- Breadcrumb navigation
- Toolbar and action buttons
- Data table (headers and rows)
- Badges and status indicators
- Modals (Preview, Media Info, File List)
- Dynamically generated content in JavaScript functions

#### Header Menu (`resources/views/partials/header-menu.blade.php`)
- Navigation bar
- Dropdown menus
- Menu items and hover states

#### Footer (`resources/views/partials/footer.blade.php`)
- Background and borders
- Text colors and links
- Social media icons

#### Main Layout Components
- Sidebar
- Main content area
- Success/error alert messages
- Mobile sidebar toggle

### 4. CSS Updates (`resources/css/app.css`)
All button classes now support dark mode:
- `.btn-primary` - Blue buttons
- `.btn-secondary` - Gray buttons
- `.btn-success` - Green buttons
- `.btn-danger` - Red buttons
- `.btn-warning` - Yellow buttons
- `.btn-info` - Cyan buttons

### 5. Color Scheme

#### Light Mode
- Background: Gray-50
- Cards: White
- Text: Gray-700 to Gray-900
- Borders: Gray-200

#### Dark Mode
- Background: Gray-900
- Cards: Gray-800
- Text: Gray-100 to Gray-300
- Borders: Gray-700

## How to Use

### For End Users
1. Look for the theme toggle button in the bottom-left corner of the screen
2. Click to switch between light and dark modes
3. Your preference will be saved automatically

### For Developers

#### Adding Dark Mode to New Templates
When creating new templates, use Tailwind's dark mode variant:

```blade
<!-- Basic example -->
<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
    Content here
</div>

<!-- Buttons -->
<button class="bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800">
    Click me
</button>

<!-- Borders -->
<div class="border-gray-200 dark:border-gray-700">
    Content
</div>
```

#### Common Dark Mode Patterns

**Cards/Containers:**
```blade
<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
```

**Text:**
```blade
<p class="text-gray-700 dark:text-gray-300">
<h1 class="text-gray-900 dark:text-gray-100">
```

**Links:**
```blade
<a href="#" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
```

**Backgrounds (subtle):**
```blade
<div class="bg-gray-50 dark:bg-gray-900">
<div class="bg-gray-100 dark:bg-gray-800">
```

#### JavaScript Dynamic Content
When generating HTML dynamically in JavaScript, include dark mode classes:

```javascript
html += `<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
    ${content}
</div>`;
```

## Browser Compatibility
- All modern browsers (Chrome, Firefox, Safari, Edge)
- Internet Explorer: Not supported (uses Tailwind CSS v3)

## Testing Checklist
- [ ] Toggle button works on all pages
- [ ] Theme preference persists after page reload
- [ ] No flash of unstyled content on page load
- [ ] All modals display correctly in both modes
- [ ] Dynamically loaded content respects current theme
- [ ] Forms and inputs are readable in both modes
- [ ] All badges and status indicators are visible

## Remaining Templates to Update

The following templates may need dark mode support if they haven't been updated yet:
- `resources/views/home/*`
- `resources/views/search/*`
- `resources/views/details/*`
- `resources/views/series/*`
- `resources/views/movies/*`
- `resources/views/profile/*`
- `resources/views/auth/*`

**Admin templates** have been fully updated with dark mode support. Additional admin pages will inherit the dark mode styles from the admin layout and common patterns.

To update remaining admin pages (like release-list, group-list, etc.), follow the same pattern used in user-list.blade.php.

## Performance Considerations
- The inline script in the head prevents FOUC with minimal overhead
- localStorage is fast and doesn't require server requests
- CSS transitions are smooth (200ms duration)

## Future Enhancements
- [ ] Add system preference detection (prefers-color-scheme)
- [ ] Add auto-switch based on time of day
- [ ] Add custom color themes beyond light/dark
- [ ] Add user preference storage in database for logged-in users

## Support
If you encounter any issues with dark mode, check:
1. Browser console for JavaScript errors
2. localStorage is enabled in browser
3. Tailwind CSS is properly compiled
4. The `dark` class is being toggled on the `<html>` element

