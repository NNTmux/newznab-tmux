<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\RootCategory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearanceMiddleware
{
    /**
     * Handle an incoming request.
     *
     *
     * @throws \Exception
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin/Moderator bypass only applies to Admin section pages
        // They should still respect their personal category permission settings
        if ($request->is(['Admin', 'Admin/*'])) {
            if (! $user->hasAnyRole(['Admin', 'Moderator'])) {
                abort(403, 'Unauthorized access to admin area');
            }

            return $next($request);
        }

        // Get the path for pattern matching
        $path = $request->path();

        // Check for browse routes with subcategory - e.g., browse/Movies/HD
        if (preg_match('#^browse/([^/]+)/([^/]+)$#i', $path, $matches)) {
            $parentCategoryName = $matches[1];
            $subcategoryName = $matches[2];

            // First check if the main category is blocked
            $blockedCategory = $this->checkMainCategoryPermission($user, $parentCategoryName);
            if ($blockedCategory) {
                return $this->abortCategoryDisabled($blockedCategory);
            }

            // Then check if the subcategory is excluded (unless it's "All")
            if (strtolower($subcategoryName) !== 'all') {
                $blockedSubcategory = $this->checkSubcategoryExclusion($user, $parentCategoryName, $subcategoryName);
                if ($blockedSubcategory) {
                    return $this->abortSubcategoryDisabled($parentCategoryName, $blockedSubcategory);
                }
            }

            return $next($request);
        }

        // Check for browse routes without subcategory - e.g., browse/Movies
        if (preg_match('#^browse/([^/]+)$#i', $path, $matches)) {
            $parentCategoryName = $matches[1];

            $blockedCategory = $this->checkMainCategoryPermission($user, $parentCategoryName);
            if ($blockedCategory) {
                return $this->abortCategoryDisabled($blockedCategory);
            }

            return $next($request);
        }

        // Movies category
        if ($this->matchesCategoryPath($path, 'Movies')
            || $this->matchesCategoryPath($path, 'movie')
            || $this->matchesCategoryPath($path, 'trending-movies')
            || $this->matchesCategoryPath($path, 'movietrailers')
            || $this->matchesCategoryPath($path, 'mymovies')) {
            if (! $user->hasDirectPermission('view movies')) {
                return $this->abortCategoryDisabled('Movies');
            }

            return $next($request);
        }

        // Console category
        if ($this->matchesCategoryPath($path, 'Console')) {
            if (! $user->hasDirectPermission('view console')) {
                return $this->abortCategoryDisabled('Console');
            }

            return $next($request);
        }

        // Books category
        if ($this->matchesCategoryPath($path, 'Books')) {
            if (! $user->hasDirectPermission('view books')) {
                return $this->abortCategoryDisabled('Books');
            }

            return $next($request);
        }

        // Audio category
        if ($this->matchesCategoryPath($path, 'Audio')) {
            if (! $user->hasDirectPermission('view audio')) {
                return $this->abortCategoryDisabled('Audio');
            }

            return $next($request);
        }

        // Adult (XXX) category
        if ($this->matchesCategoryPath($path, 'XXX')) {
            if (! $user->hasDirectPermission('view adult')) {
                return $this->abortCategoryDisabled('Adult');
            }

            return $next($request);
        }

        // PC/Games category
        if ($this->matchesCategoryPath($path, 'Games') || $this->matchesCategoryPath($path, 'PC')) {
            if (! $user->hasDirectPermission('view pc')) {
                return $this->abortCategoryDisabled('PC');
            }

            return $next($request);
        }

        // TV category
        if ($this->matchesCategoryPath($path, 'TV')
            || $this->matchesCategoryPath($path, 'series')
            || $this->matchesCategoryPath($path, 'trending-tv')
            || $this->matchesCategoryPath($path, 'myshows')) {
            if (! $user->hasDirectPermission('view tv')) {
                return $this->abortCategoryDisabled('TV');
            }

            return $next($request);
        }

        return $next($request);
    }

    /**
     * Check if the user has permission to view a main category.
     *
     * @return string|null The blocked category name, or null if allowed
     */
    protected function checkMainCategoryPermission($user, string $parentCategoryName): ?string
    {
        $categoryPermissions = [
            'movies' => 'view movies',
            'console' => 'view console',
            'books' => 'view books',
            'audio' => 'view audio',
            'xxx' => 'view adult',
            'games' => 'view pc',
            'pc' => 'view pc',
            'tv' => 'view tv',
        ];

        $categoryDisplayNames = [
            'movies' => 'Movies',
            'console' => 'Console',
            'books' => 'Books',
            'audio' => 'Audio',
            'xxx' => 'Adult',
            'games' => 'PC',
            'pc' => 'PC',
            'tv' => 'TV',
        ];

        $lowerName = strtolower($parentCategoryName);
        if (isset($categoryPermissions[$lowerName])) {
            if (! $user->hasDirectPermission($categoryPermissions[$lowerName])) {
                return $categoryDisplayNames[$lowerName];
            }
        }

        return null;
    }

    /**
     * Check if the user has excluded a specific subcategory.
     *
     * @return string|null The blocked subcategory name, or null if allowed
     */
    protected function checkSubcategoryExclusion($user, string $parentCategoryName, string $subcategoryName): ?string
    {
        // Get the root category ID
        $rootCategory = RootCategory::query()
            ->whereRaw('LOWER(title) = ?', [strtolower($parentCategoryName)])
            ->first();

        if (! $rootCategory) {
            return null;
        }

        // Get the subcategory
        $subcategory = Category::query()
            ->where('root_categories_id', $rootCategory->id)
            ->whereRaw('LOWER(title) = ?', [strtolower($subcategoryName)])
            ->first();

        if (! $subcategory) {
            return null;
        }

        // Check if this subcategory is in the user's exclusion list
        $isExcluded = $user->excludedCategories()
            ->where('categories_id', $subcategory->id)
            ->exists();

        if ($isExcluded) {
            return $subcategory->title;
        }

        return null;
    }

    /**
     * Check if the path matches a category pattern (case-insensitive).
     */
    protected function matchesCategoryPath(string $path, string $category): bool
    {
        $lowerPath = strtolower($path);
        $lowerCategory = strtolower($category);

        // Match: Category, Category/*, browse/Category, browse/Category/*
        return $lowerPath === $lowerCategory
            || str_starts_with($lowerPath, $lowerCategory.'/')
            || $lowerPath === 'browse/'.$lowerCategory
            || str_starts_with($lowerPath, 'browse/'.$lowerCategory.'/');
    }

    /**
     * Abort with a category disabled response.
     */
    protected function abortCategoryDisabled(string $category): Response
    {
        return response()->view('errors.category-disabled', [
            'category' => $category,
        ], 403);
    }

    /**
     * Abort with a subcategory disabled response.
     */
    protected function abortSubcategoryDisabled(string $parentCategory, string $subcategory): Response
    {
        return response()->view('errors.category-disabled', [
            'category' => $parentCategory.' - '.$subcategory,
            'isSubcategory' => true,
        ], 403);
    }
}
