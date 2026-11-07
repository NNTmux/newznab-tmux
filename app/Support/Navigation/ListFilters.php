<?php

declare(strict_types=1);

namespace App\Support\Navigation;

/**
 * Builds the items for the category and sort dropdowns on release list pages
 * (browse and the cover pages). Category links keep the current sort order.
 */
final class ListFilters
{
    /**
     * Label pair (ascending, descending) and icon per sort field.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const array SORT_FIELDS = [
        'posted' => ['Posted (Oldest)', 'Posted (Newest)', 'fa-calendar-days'],
        'added' => ['Added (Oldest)', 'Added (Newest)', 'fa-clock'],
        'releasedate' => ['Release date (Oldest)', 'Release date (Newest)', 'fa-calendar-check'],
        'year' => ['Year (Oldest)', 'Year (Newest)', 'fa-calendar'],
        'name' => ['Name (A-Z)', 'Name (Z-A)', 'fa-font'],
        'title' => ['Title (A-Z)', 'Title (Z-A)', 'fa-font'],
        'artist' => ['Artist (A-Z)', 'Artist (Z-A)', 'fa-microphone'],
        'author' => ['Author (A-Z)', 'Author (Z-A)', 'fa-user-pen'],
        'cat' => ['Category (A-Z)', 'Category (Z-A)', 'fa-folder'],
        'genre' => ['Genre (A-Z)', 'Genre (Z-A)', 'fa-masks-theater'],
        'platform' => ['Platform (A-Z)', 'Platform (Z-A)', 'fa-gamepad'],
        'rating' => ['Rating (Lowest)', 'Rating (Highest)', 'fa-star'],
        'size' => ['Size (Smallest)', 'Size (Largest)', 'fa-hard-drive'],
        'files' => ['Files (Least)', 'Files (Most)', 'fa-file'],
        'stats' => ['Grabs (Least)', 'Grabs (Most)', 'fa-download'],
    ];

    /**
     * Sort options for a controller's ordering list, keyed by the `ob` value.
     *
     * @param  list<string>  $ordering
     * @return array<string, array{label: string, icon: string}>
     */
    public static function sortOptions(array $ordering): array
    {
        $options = [];

        foreach ($ordering as $key) {
            [$field, $direction] = array_pad(explode('_', $key, 2), 2, 'asc');
            [$ascending, $descending, $icon] = self::SORT_FIELDS[$field] ?? [ucfirst($field).' (ascending)', ucfirst($field).' (descending)', 'fa-sort'];
            $options[$key] = ['label' => $direction === 'desc' ? $descending : $ascending, 'icon' => $icon];
        }

        return $options;
    }

    /**
     * Subcategory items for a cover page: "All …" plus each category.
     *
     * @param  iterable<array{id: int|string, title: string}>  $categories
     * @param  bool  $titleInPath  true for routes like /Movies/{title}, false for ?t= only (Games)
     * @return list<array{label: string, url: string, active: bool, icon?: string}>
     */
    public static function categoryItems(iterable $categories, int|string|null $current, string $baseUrl, string $allLabel, bool $titleInPath = true): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $items = [];
        $anyActive = false;

        foreach ($categories as $category) {
            $active = (string) $category['id'] === (string) $current;
            $anyActive = $anyActive || $active;
            $path = $titleInPath ? $baseUrl.'/'.$category['title'] : $baseUrl;

            $items[] = [
                'label' => (string) $category['title'],
                'url' => self::withQuery($path, ['t' => $category['id']]),
                'active' => $active,
            ];
        }

        array_unshift($items, ['label' => $allLabel, 'url' => self::withQuery($baseUrl), 'active' => ! $anyActive, 'icon' => 'fa-layer-group']);

        return $items;
    }

    /**
     * Main (root) category items for browse.
     *
     * @param  iterable<array{id: int|string, title: string}>  $parents
     * @return list<array{label: string, url: string, active: bool, icon?: string}>
     */
    public static function mainCategoryItems(iterable $parents, ?string $currentParent): array
    {
        $items = [['label' => __('All categories'), 'url' => self::withQuery(url('/browse/All')), 'active' => true, 'icon' => 'fa-layer-group']];

        foreach ($parents as $parent) {
            $active = $currentParent !== null && strcasecmp((string) $parent['title'], $currentParent) === 0;
            if ($active) {
                $items[0]['active'] = false;
            }

            $items[] = ['label' => (string) $parent['title'], 'url' => self::withQuery(url('/browse/'.$parent['title'])), 'active' => $active];
        }

        return $items;
    }

    /**
     * Subcategory items for browse within one main category.
     *
     * @param  array{title: string, categories?: iterable<array{id: int|string, title: string}>}  $parent
     * @return list<array{label: string, url: string, active: bool, icon?: string}>
     */
    public static function browseSubcategoryItems(array $parent, ?string $currentCategory): array
    {
        $base = url('/browse/'.$parent['title']);
        $items = [];
        $anyActive = false;

        foreach ($parent['categories'] ?? [] as $category) {
            $active = $currentCategory !== null && strcasecmp((string) $category['title'], $currentCategory) === 0;
            $anyActive = $anyActive || $active;
            $items[] = ['label' => (string) $category['title'], 'url' => self::withQuery($base.'/'.$category['title']), 'active' => $active];
        }

        array_unshift($items, ['label' => __('All :category', ['category' => $parent['title']]), 'url' => self::withQuery($base), 'active' => ! $anyActive, 'icon' => 'fa-layer-group']);

        return $items;
    }

    /**
     * Append extra query parameters plus the current sort order, so switching category keeps it.
     *
     * @param  array<string, int|string>  $query
     */
    private static function withQuery(string $url, array $query = []): string
    {
        $order = request()->query('ob');
        if (is_string($order) && preg_match('/^[a-z]+_(asc|desc)$/', $order) === 1) {
            $query['ob'] = $order;
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
