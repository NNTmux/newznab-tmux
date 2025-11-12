<?php

namespace Blacklight;

use App\Models\Release;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;
use Manticoresearch\Exceptions\RuntimeException;
use Manticoresearch\Search;

/**
 * Class ManticoreSearch.
 */
class ManticoreSearch
{
    protected mixed $config;

    protected array $connection;

    public Client $manticoreSearch;

    public Search $search;

    private ColorCLI $cli;

    /**
     * Establishes a connection to ManticoreSearch HTTP port.
     */
    public function __construct()
    {
        $this->config = config('sphinxsearch');
        $this->connection = ['host' => $this->config['host'], 'port' => $this->config['port']];
        $this->manticoreSearch = new Client($this->connection);
        $this->search = new Search($this->manticoreSearch);
        $this->cli = new ColorCLI;
    }

    /**
     * Insert release into ManticoreSearch releases_rt realtime index
     */
    public function insertRelease(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert release without ID');

            return;
        }

        try {
            $document = [
                'name' => $parameters['name'] ?? '',
                'searchname' => $parameters['searchname'] ?? '',
                'fromname' => $parameters['fromname'] ?? '',
                'categories_id' => (string) ($parameters['categories_id'] ?? ''),
                'filename' => $parameters['filename'] ?? '',
            ];

            $this->manticoreSearch->table($this->config['indexes']['releases'])
                ->replaceDocument($document, $parameters['id']);

        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertRelease ResponseException: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
                'index' => $this->config['indexes']['releases'],
            ]);
        } catch (RuntimeException $e) {
            Log::error('ManticoreSearch insertRelease RuntimeException: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertRelease unexpected error: '.$e->getMessage(), [
                'release_id' => $parameters['id'],
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Insert release into Manticore RT table.
     */
    public function insertPredb(array $parameters): void
    {
        if (empty($parameters['id'])) {
            Log::warning('ManticoreSearch: Cannot insert predb without ID');

            return;
        }

        try {
            $document = [
                'title' => $parameters['title'] ?? '',
                'filename' => $parameters['filename'] ?? '',
                'source' => $parameters['source'] ?? '',
            ];

            $this->manticoreSearch->table($this->config['indexes']['predb'])
                ->replaceDocument($document, $parameters['id']);

        } catch (ResponseException $e) {
            Log::error('ManticoreSearch insertPredb ResponseException: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        } catch (RuntimeException $e) {
            Log::error('ManticoreSearch insertPredb RuntimeException: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch insertPredb unexpected error: '.$e->getMessage(), [
                'predb_id' => $parameters['id'],
            ]);
        }
    }

    /**
     * Delete release from Manticore RT tables.
     *
     * @param  array  $identifiers  ['g' => Release GUID(mandatory), 'id' => ReleaseID(optional, pass false)]
     */
    public function deleteRelease(array $identifiers): void
    {
        if (empty($identifiers['g'])) {
            Log::warning('ManticoreSearch: Cannot delete release without GUID');

            return;
        }

        try {
            if ($identifiers['i'] === false || empty($identifiers['i'])) {
                $release = Release::query()->where('guid', $identifiers['g'])->first(['id']);
                $identifiers['i'] = $release?->id;
            }

            if (! empty($identifiers['i'])) {
                $this->manticoreSearch->table($this->config['indexes']['releases'])
                    ->deleteDocument($identifiers['i']);
            } else {
                Log::warning('ManticoreSearch: Could not find release ID for deletion', [
                    'guid' => $identifiers['g'],
                ]);
            }
        } catch (ResponseException $e) {
            Log::error('ManticoreSearch deleteRelease error: '.$e->getMessage(), [
                'guid' => $identifiers['g'],
                'id' => $identifiers['i'] ?? null,
            ]);
        }
    }

    /**
     * Escapes characters that are treated as special operators by the query language parser.
     */
    public static function escapeString(string $string): string
    {
        if ($string === '*' || empty($string)) {
            return '';
        }

        $from = ['\\', '(', ')', '@', '~', '"', '&', '/', '$', '=', "'", '--', '[', ']', '!', '-'];
        $to = ['\\\\', '\(', '\)', '\@', '\~', '\"', '\&', '\/', '\$', '\=', "\'", '\--', '\[', '\]', '\!', '\-'];

        $string = str_replace($from, $to, $string);

        // Clean up trailing special characters
        $string = rtrim($string, '-!');

        return trim($string);
    }

    public function updateRelease(int|string $releaseID): void
    {
        if (empty($releaseID)) {
            Log::warning('ManticoreSearch: Cannot update release without ID');

            return;
        }

        try {
            $release = Release::query()
                ->where('releases.id', $releaseID)
                ->leftJoin('release_files as rf', 'releases.id', '=', 'rf.releases_id')
                ->select([
                    'releases.id',
                    'releases.name',
                    'releases.searchname',
                    'releases.fromname',
                    'releases.categories_id',
                    DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename'),
                ])
                ->groupBy('releases.id')
                ->first();

            if ($release !== null) {
                $this->insertRelease($release->toArray());
            } else {
                Log::warning('ManticoreSearch: Release not found for update', ['id' => $releaseID]);
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch updateRelease error: '.$e->getMessage(), [
                'release_id' => $releaseID,
            ]);
        }
    }

    /**
     * Update Manticore Predb index for given predb_id.
     */
    public function updatePreDb(array $parameters): void
    {
        if (empty($parameters)) {
            Log::warning('ManticoreSearch: Cannot update predb with empty parameters');

            return;
        }

        $this->insertPredb($parameters);
    }

    public function truncateRTIndex(array $indexes = []): bool
    {
        if (empty($indexes)) {
            $this->cli->error('You need to provide index name to truncate');

            return false;
        }

        $success = true;
        foreach ($indexes as $index) {
            if (! \in_array($index, $this->config['indexes'], true)) {
                $this->cli->error('Unsupported index: '.$index);
                $success = false;

                continue;
            }

            try {
                $this->manticoreSearch->table($index)->truncate();
                $this->cli->info('Truncating index '.$index.' finished.');
            } catch (ResponseException $e) {
                if ($e->getMessage() === 'Invalid index') {
                    $this->createIndexIfNotExists($index);
                } else {
                    $this->cli->error('Error truncating index '.$index.': '.$e->getMessage());
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->cli->error('Unexpected error truncating index '.$index.': '.$e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Create index if it doesn't exist
     */
    private function createIndexIfNotExists(string $index): void
    {
        try {
            if ($index === 'releases_rt') {
                $this->manticoreSearch->table($index)->create([
                    'name' => ['type' => 'string'],
                    'searchname' => ['type' => 'string'],
                    'fromname' => ['type' => 'string'],
                    'filename' => ['type' => 'string'],
                    'categories_id' => ['type' => 'integer'],
                ]);
                $this->cli->info('Created releases_rt index');
            } elseif ($index === 'predb_rt') {
                $this->manticoreSearch->table($index)->create([
                    'title' => ['type' => 'string'],
                    'filename' => ['type' => 'string'],
                    'source' => ['type' => 'string'],
                ]);
                $this->cli->info('Created predb_rt index');
            }
        } catch (\Throwable $e) {
            $this->cli->error('Error creating index '.$index.': '.$e->getMessage());
        }
    }

    /**
     * Optimize the RT indices.
     */
    public function optimizeRTIndex(): bool
    {
        $success = true;

        foreach ($this->config['indexes'] as $index) {
            try {
                $this->manticoreSearch->table($index)->flush();
                $this->manticoreSearch->table($index)->optimize();
                Log::info("Successfully optimized index: {$index}");
            } catch (ResponseException $e) {
                Log::error('Failed to optimize index '.$index.': '.$e->getMessage());
                $success = false;
            } catch (\Throwable $e) {
                Log::error('Unexpected error optimizing index '.$index.': '.$e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    public function searchIndexes(string $rt_index, ?string $searchString, array $column = [], array $searchArray = []): array
    {
        if (empty($rt_index)) {
            Log::warning('ManticoreSearch: Index name is required for search');

            return [];
        }

        // Create cache key for search results
        $cacheKey = md5(serialize([
            'index' => $rt_index,
            'search' => $searchString,
            'columns' => $column,
            'array' => $searchArray,
        ]));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Build query string once so we can retry if needed
        $searchExpr = null;
        if (! empty($searchArray)) {
            $terms = [];
            foreach ($searchArray as $key => $value) {
                if (! empty($value)) {
                    $escapedValue = self::escapeString($value);
                    if (! empty($escapedValue)) {
                        $terms[] = '@@relaxed @'.$key.' '.$escapedValue;
                    }
                }
            }
            if (! empty($terms)) {
                $searchExpr = implode(' ', $terms);
            } else {
                return [];
            }
        } elseif (! empty($searchString)) {
            $escapedSearch = self::escapeString($searchString);
            if (empty($escapedSearch)) {
                return [];
            }

            $searchColumns = '';
            if (! empty($column)) {
                if (count($column) > 1) {
                    $searchColumns = '@('.implode(',', $column).')';
                } else {
                    $searchColumns = '@'.$column[0];
                }
            }

            $searchExpr = '@@relaxed '.$searchColumns.' '.$escapedSearch;
        } else {
            return [];
        }

        // Avoid explicit sort for predb_rt to prevent Manticore's "too many sort-by attributes" error
        $avoidSortForIndex = ($rt_index === 'predb_rt');

        try {
            // Use a fresh Search instance for every query to avoid parameter accumulation across calls
            $query = (new Search($this->manticoreSearch))
                ->setTable($rt_index)
                ->option('ranker', 'sph04')
                ->maxMatches(10000)
                ->limit(10000)
                ->stripBadUtf8(true)
                ->search($searchExpr);

            if (! $avoidSortForIndex) {
                $query->sort('id', 'desc');
            }

            $results = $query->get();
        } catch (ResponseException $e) {
            // If we hit Manticore's "too many sort-by attributes" limit, retry once without explicit sorting
            if (stripos($e->getMessage(), 'too many sort-by attributes') !== false) {
                try {
                    $query = (new Search($this->manticoreSearch))
                        ->setTable($rt_index)
                        ->option('ranker', 'sph04')
                        ->maxMatches(10000)
                        ->limit(10000)
                        ->stripBadUtf8(true)
                        ->search($searchExpr);

                    $results = $query->get();

                    Log::warning('ManticoreSearch: Retried search without sorting due to sort-by attributes limit', [
                        'index' => $rt_index,
                    ]);
                } catch (ResponseException $e2) {
                    Log::error('ManticoreSearch searchIndexes ResponseException after retry: '.$e2->getMessage(), [
                        'index' => $rt_index,
                        'search' => $searchString,
                    ]);

                    return [];
                }
            } else {
                Log::error('ManticoreSearch searchIndexes ResponseException: '.$e->getMessage(), [
                    'index' => $rt_index,
                    'search' => $searchString,
                ]);

                return [];
            }
        } catch (RuntimeException $e) {
            Log::error('ManticoreSearch searchIndexes RuntimeException: '.$e->getMessage(), [
                'index' => $rt_index,
                'search' => $searchString,
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch searchIndexes unexpected error: '.$e->getMessage(), [
                'index' => $rt_index,
                'search' => $searchString,
            ]);

            return [];
        }

        // Parse results and cache
        $resultIds = [];
        $resultData = [];
        foreach ($results as $doc) {
            $resultIds[] = $doc->getId();
            $resultData[] = $doc->getData();
        }

        $result = [
            'id' => $resultIds,
            'data' => $resultData,
        ];

        // Cache results for 5 minutes
        Cache::put($cacheKey, $result, now()->addMinutes(5));

        return $result;
    }

    /**
     * Fast exact match search - returns only first matching ID or null.
     * Much faster than searchIndexes for exact matches.
     */
    public function exactMatch(string $rt_index, string $searchString, string $field): ?int
    {
        if (empty($rt_index) || empty($searchString) || empty($field)) {
            return null;
        }

        try {
            // Use exact phrase matching with field filter
            // The @field syntax with quotes ensures exact matching
            $escapedSearch = self::escapeString($searchString);
            if (empty($escapedSearch)) {
                return null;
            }

            $searchExpr = '@'.$field.' "'.str_replace('"', '\\"', $searchString).'"';

            $query = (new Search($this->manticoreSearch))
                ->setTable($rt_index)
                ->option('ranker', 'none') // Faster - we don't need ranking for exact match
                ->limit(1) // Only need first match
                ->stripBadUtf8(true)
                ->search($searchExpr);

            $results = $query->get();

            if (! empty($results)) {
                foreach ($results as $doc) {
                    return $doc->getId();
                }
            }
        } catch (\Throwable $e) {
            Log::error('ManticoreSearch exactMatch error: '.$e->getMessage(), [
                'index' => $rt_index,
                'field' => $field,
                'search' => $searchString,
            ]);
        }

        return null;
    }
}
