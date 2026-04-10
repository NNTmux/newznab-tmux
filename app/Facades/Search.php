<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\Search\SearchService;
use Illuminate\Support\Facades\Facade;

/**
 * Search Facade - provides static access to the SearchService.
 *
 * @method static bool isAutocompleteEnabled()
 * @method static bool isSuggestEnabled()
 * @method static string getReleasesIndex()
 * @method static string getPredbIndex()
 * @method static void insertRelease(array $parameters)
 * @method static void updateRelease(int|string $releaseID)
 * @method static void deleteRelease(int $id)
 * @method static void insertPredb(array $parameters)
 * @method static void updatePreDb(array $parameters)
 * @method static void deletePreDb(int $id)
 * @method static array searchReleases(array|string $phrases, int $limit = 1000)
 * @method static array searchReleasesWithFuzzy(array|string $phrases, int $limit = 1000, bool $forceFuzzy = false)
 * @method static array fuzzySearchReleases(array|string $phrases, int $limit = 1000)
 * @method static bool isFuzzyEnabled()
 * @method static array getFuzzyConfig()
 * @method static array searchPredb(array|string $searchTerm)
 * @method static array autocomplete(string $query, ?string $index = null)
 * @method static array suggest(string $query, ?string $index = null)
 * @method static bool isAvailable()
 * @method static string getCurrentDriver()
 * @method static string escapeString(string $string)
 * @method static void truncateIndex(array|string $indexes)
 * @method static void optimizeIndex()
 * @method static array bulkInsertReleases(array $releases)
 * @method static array searchReleasesByExternalId(array $externalIds, int $limit = 1000)
 * @method static array searchReleasesByCategory(array $categoryIds, int $limit = 1000)
 * @method static array searchReleasesWithCategoryFilter(string $searchTerm, array $categoryIds = [], int $limit = 1000)
 * @method static array searchReleasesFiltered(array $criteria, int $limit, int $offset = 0)
 * @method static void insertSecondary(\App\Enums\SecondarySearchIndex $index, int $id, array $document)
 * @method static void updateSecondary(\App\Enums\SecondarySearchIndex $index, int $id)
 * @method static void deleteSecondary(\App\Enums\SecondarySearchIndex $index, int $id)
 * @method static array bulkInsertSecondary(\App\Enums\SecondarySearchIndex $index, array $documents)
 * @method static array searchSecondary(\App\Enums\SecondarySearchIndex $index, string $query, int $limit = 100)
 * @method static array searchAnimeTitle(string $query, int $limit = 100)
 * @method static array searchMoviesByFields(array $fieldTerms, int $limit = 5000)
 *
 * @see SearchService
 */
class Search extends Facade // @phpstan-ignore missingType.iterableValue
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return SearchService::class;
    }
}
