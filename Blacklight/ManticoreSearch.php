<?php

namespace Blacklight;

use App\Models\Release;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Manticoresearch\Client;
use Manticoresearch\Exceptions\ResponseException;
use Manticoresearch\Exceptions\RuntimeException;
use Manticoresearch\Search;

/**
 * Class ManticoreSearch.
 */
class ManticoreSearch
{
    /**
     * @var \Illuminate\Config\Repository|mixed
     */
    protected mixed $config;

    protected array $connection;

    public Client $manticoresearch;

    public Search $search;

    /**
     * @var \Blacklight\ColorCLI
     */
    private ColorCLI $cli;

    /**
     * Establishes a connection to ManticoreSearch HTTP port.
     */
    public function __construct()
    {
        $this->config = config('sphinxsearch');
        $this->connection = ['host' => $this->config['host'], 'port' => $this->config['port']];
        $this->manticoresearch = new Client($this->connection);
        $this->search = new Search($this->manticoresearch);
        $this->cli = new ColorCLI();
    }

    /**
     * Insert release into ManticoreSearch releases_rt realtime index
     */
    public function insertRelease(array $parameters): void
    {
        if ($parameters['id']) {
            try {
                $this->manticoresearch->index($this->config['indexes']['releases'])
                    ->replaceDocument(
                        [
                            'name' => $parameters['name'],
                            'searchname' => $parameters['searchname'],
                            'fromname' => $parameters['fromname'],
                            'categories_id' => (string) $parameters['categories_id'],
                            'filename' => empty($parameters['filename']) ? "''" : $parameters['filename'],
                        ], $parameters['id']);
            } catch (ResponseException $re) {
                Log::error($re->getMessage());
            } catch (\RuntimeException $ru) {
                Log::error($ru->getMessage());
            } catch (\Throwable $exception) {
                Log::error($exception->getMessage());
            }

        }
    }

    /**
     * Insert release into Manticore RT table.
     */
    public function insertPredb(array $parameters): void
    {
        try {
            if ($parameters['id']) {
                $this->manticoresearch->index($this->config['indexes']['predb'])
                    ->replaceDocument(['title' => $parameters['title'], 'filename' => empty($parameters['filename']) ? "''" : $parameters['filename'], 'source' => $parameters['source']], $parameters['id']);
            }
        } catch (ResponseException $re) {
            Log::error($re->getMessage());
        } catch (\RuntimeException $ru) {
            Log::error($ru->getMessage());
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage());
        }

    }

    /**
     * Delete release from Manticore RT tables.
     *
     * @param  array  $identifiers  ['g' => Release GUID(mandatory), 'id' => ReleaseID(optional, pass false)]
     */
    public function deleteRelease(array $identifiers): void
    {
        if ($identifiers['i'] === false) {
            $identifiers['i'] = Release::query()->where('guid', $identifiers['g'])->first(['id']);
            if ($identifiers['i'] !== null) {
                $identifiers['i'] = $identifiers['i']['id'];
            }
        }
        if ($identifiers['i'] !== false) {
            $this->manticoresearch->index($this->config['indexes']['releases'])->deleteDocument($identifiers['i']);
        }
    }

    /**
     * Escapes characters that are treated as special operators by the query language parser.
     *
     * @param  string  $string  unescaped string
     * @return string Escaped string.
     */
    public static function escapeString(string $string): string
    {
        if ($string === '*') {
            return '';
        }

        $from = ['\\', '(', ')', '@', '~', '"', '&', '/', '$', '=', "'", '--', '[', ']'];
        $to = ['\\\\', '\(', '\)', '\@', '\~', '\"', '\&', '\/', '\$', '\=', "\', '\--", '\[', '\]'];

        $string = str_replace($from, $to, $string);
        // Remove these characaters if they are the last chars in $string
        $string = Str::of($string)->rtrim('-');
        $string = Str::of($string)->rtrim('!');

        return $string;
    }

    public function updateRelease(int|string $releaseID): void
    {
        $new = Release::query()
                ->where('releases.id', $releaseID)
                ->leftJoin('release_files as rf', 'releases.id', '=', 'rf.releases_id')
                ->select(['releases.id', 'releases.name', 'releases.searchname', 'releases.fromname', 'releases.categories_id', DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename')])
                ->groupBy('releases.id')
                ->first();

        if ($new !== null) {
            $release = $new->toArray();
            $this->insertRelease($release);
        }
    }

    /**
     * Update Manticore Predb index for given predb_id.
     *
     *
     * @throws \Exception
     */
    public function updatePreDb(array $parameters): void
    {
        if (! empty($parameters)) {
            $this->insertPredb($parameters);
        }
    }

    public function truncateRTIndex(array $indexes = []): bool
    {
        if (empty($indexes)) {
            $this->cli->error('You need to provide index name to truncate');

            return false;
        }
        foreach ($indexes as $index) {
            if (\in_array($index, $this->config['indexes'], true)) {
                try {
                    $this->manticoresearch->index($index)->truncate();
                    $this->cli->info('Truncating index '.$index.' finished.');
                } catch (ResponseException $e) {
                    if ($e->getMessage() === 'Invalid index') {
                        if ($index === 'releases_rt') {
                            $this->manticoresearch->index($index)->create([
                                'name' => ['type' => 'string'],
                                'searchname' => ['type' => 'string'],
                                'fromname' => ['type' => 'string'],
                                'filename' => ['type' => 'string'],
                                'categories_id' => ['type' => 'integer'],
                            ]);
                        } elseif ($index === 'predb_rt') {
                            $this->manticoresearch->index($index)->create([
                                'title' => ['type' => 'string'],
                                'filename' => ['type' => 'string'],
                                'source' => ['type' => 'string'],
                            ]);
                        }
                    }
                }
            } else {
                $this->cli->error('Unsupported index: '.$index);
            }
        }

        return true;
    }

    /**
     * Optimize the RT index.
     */
    public function optimizeRTIndex(): void
    {
        foreach ($this->config['indexes'] as $index) {
            $this->manticoresearch->index($index)->flush();
            $this->manticoresearch->index($index)->optimize();
        }
    }

    public function searchIndexes(string $rt_index, string $searchString = '', array $column = [], array $searchArray = []): array
    {
        $resultId = [];
        $resultData = [];
        try {
            $query = $this->search->setIndex($rt_index)->option('ranker', 'sph04')->option('sort_method', 'pq')->maxMatches(10000)->limit(10000)->sort('id', 'desc')->stripBadUtf8(true)->trackScores(true);
            if (! empty($searchArray)) {
                foreach ($searchArray as $key => $value) {
                    $query->search('@@relaxed @'.$key.' '.self::escapeString($value));
                }
            } elseif (! empty($searchString)) {
                $searchColumns = ! empty($column) ? '@'.implode('', $column) : '';
                $query->search('@@relaxed'.$searchColumns.' '.self::escapeString($searchString));
            } else {
                return [];
            }
            $results = $query->get();
            foreach ($results as $doc) {
                $resultId[] = [
                    'id' => $doc->getId(),
                ];
                $resultData[] = [
                    'data' => $doc->getData(),
                ];
            }

            return [
                'id' => Arr::pluck($resultId, 'id'),
                'data' => Arr::pluck($resultData, 'data'),
            ];
        } catch (ResponseException $exception) {
            Log::error($exception->getMessage());

            return [];
        } catch (RuntimeException $exception) {
            Log::error($exception->getMessage());

            return [];
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage());

            return [];
        }
    }
}
