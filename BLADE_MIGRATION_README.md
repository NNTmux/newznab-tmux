# Blade + TailwindCSS Migration - Quick Start

## Migration Status

✅ **Completed:**
- BasePageController updated to use Blade views
- Main and Admin layouts created with TailwindCSS
- Blade partials created (header, sidebar, footer, admin menu)
- BrowseController migrated to Blade
- DetailsController migrated to Blade
- Browse, Details, Search, and Admin Dashboard views created
- Migration helper script created

⏳ **Remaining Work:**
- Migrate remaining 40+ controllers
- Convert 100+ Smarty templates to Blade
- Update all AJAX endpoints
- Test thoroughly

## Quick Start

### 1. Install Dependencies (if needed)
```bash
npm install
```

### 2. Compile Assets
```bash
npm run build
# or for development with hot reload:
npm run dev
```

### 3. Clear Caches
```bash
php artisan view:clear
php artisan cache:clear
php artisan config:clear
```

### 4. Test the Migrated Pages
Visit these URLs to see the new Blade templates in action:
- Browse: `/browse/All`
- Details: `/details/{guid}`
- Admin Dashboard: `/admin`
- Search: `/search`

## File Structure

```
resources/views/
├── layouts/
│   ├── main.blade.php          # Main user-facing layout
│   ├── admin.blade.php         # Admin panel layout
│   └── app.blade.php           # Auth layout (existing)
├── partials/
│   ├── header-menu.blade.php   # Top navigation
│   ├── sidebar.blade.php       # Left sidebar
│   ├── footer.blade.php        # Footer
│   └── admin-menu.blade.php    # Admin sidebar
├── browse/
│   └── index.blade.php         # Browse releases page
├── details/
│   └── index.blade.php         # Release details page
├── search/
│   └── index.blade.php         # Search page
├── home/
│   └── index.blade.php         # Homepage
└── admin/
    └── dashboard.blade.php     # Admin dashboard
```

## Migration Helper Tool

Use the included migration script to help convert Smarty templates:

```bash
php migrate-templates.php resources/views/themes/Gentele/browse.tpl
```

This will:
- Convert Smarty syntax to Blade syntax
- Suggest the output filename
- Show a preview before saving
- Create necessary directories

## Controller Migration Pattern

**Before (Smarty):**
```php
public function index(Request $request)
{
    $this->setPreferences();
    $data = SomeModel::all();
    
    $this->smarty->assign('data', $data);
    $this->smarty->assign('meta_title', 'Page Title');
    
    $content = $this->smarty->fetch('template.tpl');
    $this->smarty->assign(compact('content', 'meta_title'));
    $this->pagerender();
}
```

**After (Blade):**
```php
public function index(Request $request)
{
    $this->setPreferences();
    $data = SomeModel::all();
    
    $this->viewData = array_merge($this->viewData, [
        'data' => $data,
        'meta_title' => 'Page Title',
    ]);
    
    return view('module.index', $this->viewData);
}
```

## TailwindCSS Classes

Common Bootstrap to Tailwind conversions:

| Bootstrap | TailwindCSS |
|-----------|-------------|
| `container` | `container mx-auto px-4` |
| `row` | `grid grid-cols-12` or `flex` |
| `col-md-6` | `md:col-span-6` or `md:w-1/2` |
| `btn btn-primary` | `px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700` |
| `card` | `bg-white rounded-lg shadow-sm` |
| `d-flex justify-content-between` | `flex justify-between` |
| `mb-3` | `mb-3` (same) |
| `text-center` | `text-center` (same) |

## Next Controllers to Migrate

Priority order for remaining migrations:

1. **SearchController** - High traffic page
2. **MovieController** - Popular feature
3. **SeriesController** - Popular feature
4. **ProfileController** - User management
5. **Admin Controllers** - Admin functionality

For each controller:
1. Update the controller method to use `$this->viewData` and `return view()`
2. Create corresponding `.blade.php` file
3. Convert Smarty syntax to Blade
4. Update CSS from Bootstrap to TailwindCSS
5. Test thoroughly

## Testing Checklist

- [ ] Browse pages load correctly
- [ ] Release details show all information
- [ ] Search functionality works
- [ ] User authentication works
- [ ] Admin panel is accessible
- [ ] Forms submit properly
- [ ] AJAX endpoints work
- [ ] Responsive design on mobile
- [ ] No console errors
- [ ] All images/assets load

## Common Issues & Solutions

**Issue:** Views not updating
```bash
php artisan view:clear
```

**Issue:** CSS not applying
```bash
npm run build
php artisan cache:clear
```

**Issue:** Routes not working
```bash
php artisan route:clear
php artisan config:clear
```

## Resources

- [Laravel Blade Docs](https://laravel.com/docs/blade)
- [TailwindCSS Docs](https://tailwindcss.com/docs)
- [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) - Detailed migration guide

## Notes

- All new views extend either `layouts.main` or `layouts.admin`
- Use `@auth` / `@guest` for authentication checks
- Use `@can()` for permission checks
- TailwindCSS is configured to scan all blade files
- Vite handles asset compilation

