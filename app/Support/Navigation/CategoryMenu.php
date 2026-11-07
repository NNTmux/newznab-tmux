<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Models\Category;
use App\Models\User;

/**
 * Builds the header category menu once so the desktop dropdowns and the mobile panel
 * render the same links. Users who enabled cover views (movieview, consoleview, gameview)
 * are sent to the cover pages instead of the plain browse lists.
 */
final class CategoryMenu
{
    /**
     * @var array<int, string>
     */
    private const array ICONS = [
        Category::TV_ROOT => 'fa-television',
        Category::MOVIE_ROOT => 'fa-film',
        Category::GAME_ROOT => 'fa-gamepad',
        Category::PC_ROOT => 'fa-desktop',
        Category::MUSIC_ROOT => 'fa-music',
        Category::BOOKS_ROOT => 'fa-book',
        Category::XXX_ROOT => 'fa-ban',
    ];

    /**
     * Quick searches offered under the "Foreign" subcategories.
     *
     * @var array<string, string>
     */
    private const array FOREIGN_LANGUAGES = [
        'German' => 'German|Deutsch|DE|GER',
        'French' => 'French|Francais|FR|FRENCH|VOSTFR|TRUEFRENCH',
        'Spanish' => 'Spanish|Espanol|ES|ESP|SPANISH|Latino',
        'Italian' => 'Italian|Italiano|IT|ITA',
        'Dutch' => 'Dutch|NL|Flemish|Nederlands|DUTCH',
        'Japanese' => 'Japanese|JP|JPN|JAP',
        'Korean' => 'Korean|KR|KOR|K-Drama',
        'Chinese' => 'Chinese|Mandarin|Cantonese|CH|CHN|C-Drama',
        'Russian' => 'Russian|RU|RUS',
        'Portuguese' => 'Portuguese|PT|POR|Portugues|Brazilian',
    ];

    /**
     * @param  iterable<array{id: int|string, title: string, categories?: iterable<array{id: int|string, title: string}>}>  $parentCategories
     * @return list<array{id: int, title: string, icon: string, links: list<array{label: string, url: string}>, children: list<array{label: string, url: string, languages: list<array{label: string, url: string}>}>}>
     */
    public static function build(iterable $parentCategories, ?User $user): array
    {
        $menu = [];

        foreach ($parentCategories as $parent) {
            $id = (int) $parent['id'];
            $title = (string) $parent['title'];
            $browse = url('/browse/'.$title);
            $coverBase = match ($id) {
                Category::MOVIE_ROOT => self::enabled($user?->movieview) ? url('/'.$title) : $browse,
                Category::GAME_ROOT => self::enabled($user?->consoleview) ? url('/'.$title) : $browse,
                default => $browse,
            };

            $links = match ($id) {
                Category::TV_ROOT => [
                    ['label' => __('All TV'), 'url' => $browse],
                    ['label' => __('TV Series'), 'url' => route('series')],
                ],
                Category::MOVIE_ROOT => [
                    ['label' => $title, 'url' => $coverBase],
                    ['label' => __('My Movies'), 'url' => route('mymovies')],
                ],
                default => [['label' => $title, 'url' => $coverBase]],
            };

            $children = [];
            foreach ($parent['categories'] ?? [] as $sub) {
                $subId = (int) $sub['id'];
                $subTitle = (string) $sub['title'];

                $url = match (true) {
                    $id === Category::TV_ROOT => url('/browse/TV/'.$subTitle),
                    $id === Category::PC_ROOT && $subId === Category::PC_GAMES && self::enabled($user?->gameview) => url('/'.$subTitle),
                    default => $coverBase.'/'.$subTitle,
                };

                $children[] = [
                    'label' => $subTitle,
                    'url' => $url,
                    'languages' => in_array($subId, [Category::TV_FOREIGN, Category::MOVIE_FOREIGN], true) ? self::languages($subId) : [],
                ];
            }

            $menu[] = [
                'id' => $id,
                'title' => $title,
                'icon' => self::ICONS[$id] ?? 'fa-folder',
                'links' => $links,
                'children' => $children,
            ];
        }

        return $menu;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function languages(int $categoryId): array
    {
        $links = [];
        foreach (self::FOREIGN_LANGUAGES as $language => $query) {
            $links[] = ['label' => __($language), 'url' => route('search', ['search' => $query, 't' => $categoryId])];
        }

        return $links;
    }

    private static function enabled(mixed $preference): bool
    {
        return (string) $preference === '1';
    }
}
