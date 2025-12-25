<?php

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
 * @method static array searchPredb(array|string $searchTerm)
 * @method static array autocomplete(string $query, ?string $index = null)
 * @method static array suggest(string $query, ?string $index = null)
 * @method static bool isAvailable()
 * @method static string getCurrentDriver()
 * @method static string escapeString(string $string)
 * @method static void truncateIndex(array|string $indexes)
 * @method static void optimizeIndex()
 * @method static array bulkInsertReleases(array $releases)
 *
 * @see \App\Services\Search\SearchService
 */
class Search extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return SearchService::class;
    }
}
