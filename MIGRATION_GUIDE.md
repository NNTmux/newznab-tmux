# Smarty 4 to Laravel Blade + TailwindCSS Migration Guide

## Overview
This document provides a comprehensive guide for migrating the NNTmux application from Smarty 4 templates to Laravel Blade with TailwindCSS.

## Changes Made

### 1. New Blade Layouts Created
- `resources/views/layouts/main.blade.php` - Main application layout
- `resources/views/layouts/admin.blade.php` - Admin panel layout
- `resources/views/layouts/app.blade.php` - Existing Laravel auth layout (retained)

### 2. New Blade Partials Created
- `resources/views/partials/header-menu.blade.php` - Top navigation menu
- `resources/views/partials/sidebar.blade.php` - Left sidebar navigation
- `resources/views/partials/footer.blade.php` - Footer content
- `resources/views/partials/admin-menu.blade.php` - Admin sidebar menu

### 3. New Blade Views Created
- `resources/views/browse/index.blade.php` - Browse releases page
- `resources/views/details/index.blade.php` - Release details page
- `resources/views/home/index.blade.php` - Home page template

### 4. Controllers Updated
The following controllers have been migrated from Smarty to Blade:
- `BasePageController.php` - Base controller (removed Smarty, added Blade support)
- `BrowseController.php` - Browse functionality
- `DetailsController.php` - Release details

## Migration Pattern

### Before (Smarty):
```php
public function index(Request $request)
{
    $this->setPreferences();
    
    // Business logic...
    
    $this->smarty->assign('data', $data);
    $this->smarty->assign('meta_title', 'Page Title');
    
    $content = $this->smarty->fetch('template.tpl');
    $this->smarty->assign(compact('content', 'meta_title'));
    $this->pagerender();
}
```

### After (Blade):
```php
public function index(Request $request)
{
    $this->setPreferences();
    
    // Business logic...
    
    $this->viewData = array_merge($this->viewData, [
        'data' => $data,
        'meta_title' => 'Page Title',
    ]);
    
    return view('module.index', $this->viewData);
}
```

## Template Syntax Migration

### Variables
**Smarty:**
```smarty
{$variable}
{$user.name}
```

**Blade:**
```blade
{{ $variable }}
{{ $user->name }}
```

### Conditionals
**Smarty:**
```smarty
{if $condition}
    Content
{elseif $other}
    Other
{else}
    Default
{/if}
```

**Blade:**
```blade
@if($condition)
    Content
@elseif($other)
    Other
@else
    Default
@endif
```

### Loops
**Smarty:**
```smarty
{foreach $items as $item}
    {$item.name}
{/foreach}
```

**Blade:**
```blade
@foreach($items as $item)
    {{ $item->name }}
@endforeach
```

### URLs and Routes
**Smarty:**
```smarty
{{url('/browse/All')}}
{{route('series')}}
```

**Blade:**
```blade
{{ url('/browse/All') }}
{{ route('series') }}
```

### Authentication
**Smarty:**
```smarty
{if Auth::check()}
    Logged in content
{/if}
```

**Blade:**
```blade
@auth
    Logged in content
@endauth

@guest
    Guest content
@endguest
```

### Including Partials
**Smarty:**
```smarty
{include file='sidebar.tpl'}
```

**Blade:**
```blade
@include('partials.sidebar')
```

## TailwindCSS Integration

### Styling Philosophy
- Replace Bootstrap classes with TailwindCSS utility classes
- Use responsive prefixes (sm:, md:, lg:, xl:)
- Utilize Tailwind's color palette and spacing system

### Common Class Conversions

| Bootstrap | TailwindCSS |
|-----------|-------------|
| `container` | `container mx-auto px-4` |
| `row` | `flex flex-wrap` or `grid` |
| `col-md-6` | `md:w-1/2` or `md:col-span-6` |
| `btn btn-primary` | `px-4 py-2 bg-blue-600 text-white rounded` |
| `text-center` | `text-center` |
| `mb-3` | `mb-3` (Tailwind uses same spacing scale) |
| `d-flex` | `flex` |
| `justify-content-between` | `justify-between` |
| `align-items-center` | `items-center` |

## Remaining Work

### Controllers to Update
All remaining controllers in `app/Http/Controllers/` need to be migrated:
- SearchController.php
- MovieController.php
- SeriesController.php
- ProfileController.php
- CartController.php
- Admin/* controllers
- And many more...

### Templates to Create
Convert all `.tpl` files from `resources/views/themes/` to `.blade.php`:
- Admin templates
- Search templates
- Movie/TV templates
- Profile templates
- Forum templates
- etc.

### Steps for Each Controller Migration

1. **Update the controller method:**
   - Remove `$this->smarty->assign()` calls
   - Replace with `$this->viewData = array_merge()`
   - Change `$this->pagerender()` to `return view('view.name', $this->viewData)`

2. **Create corresponding Blade view:**
   - Copy relevant HTML from `.tpl` file
   - Convert Smarty syntax to Blade syntax
   - Replace Bootstrap classes with TailwindCSS
   - Ensure proper use of `@extends` and `@section`

3. **Test thoroughly:**
   - Verify all data displays correctly
   - Check responsive design
   - Test user interactions
   - Validate forms and AJAX calls

## Configuration Changes

### Remove Smarty Package (After Migration Complete)
Once all templates are migrated, you can remove the Smarty dependency:

```bash
composer remove smarty/smarty ytake/laravel-smarty
```

Remove from `config/app.php`:
- Smarty service provider entries

Delete:
- `config/ytake-laravel-smarty.php`

## Benefits of This Migration

1. **Better Laravel Integration** - Blade is Laravel's native templating engine
2. **Performance** - Blade compiles to plain PHP and is cached
3. **Modern UI** - TailwindCSS provides utility-first CSS framework
4. **Maintainability** - Standard Laravel conventions
5. **Developer Experience** - Better IDE support and debugging
6. **Security** - Automatic XSS protection with Blade's `{{ }}` syntax

## Testing Checklist

- [ ] Authentication works (login/logout)
- [ ] Browse pages display correctly
- [ ] Release details show all information
- [ ] Search functionality works
- [ ] Admin panel is accessible
- [ ] Forms submit properly
- [ ] AJAX requests function
- [ ] Responsive design on mobile
- [ ] All routes resolve correctly
- [ ] No JavaScript errors in console

## Notes

- The `BasePageController` now uses `$viewData` array instead of Smarty assignments
- All views should extend `layouts.main` or `layouts.admin`
- Use `@stack` and `@push` for page-specific scripts/styles
- TailwindCSS config is in `tailwind.config.js`
- Vite is configured for asset compilation

## Support

For questions or issues during migration, refer to:
- [Laravel Blade Documentation](https://laravel.com/docs/blade)
- [TailwindCSS Documentation](https://tailwindcss.com/docs)

