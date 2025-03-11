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
        if ($parameters['id']) {
            try {
                $this->manticoreSearch->table($this->config['indexes']['releases'])
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
                $this->manticoreSearch->table($this->config['indexes']['predb'])
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
            $this->manticoreSearch->table($this->config['indexes']['releases'])->deleteDocument($identifiers['i']);
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
        $to = ['\\\\', '\(', '\)', '\@', '\~', '\"', '\&', '\/', '\$', '\=', '\'', '\--', '\[', '\]'];

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
                    $this->manticoreSearch->table($index)->truncate();
                    $this->cli->info('Truncating index '.$index.' finished.');
                } catch (ResponseException $e) {
                    if ($e->getMessage() === 'Invalid index') {
                        if ($index === 'releases_rt') {
                            $this->manticoreSearch->table($index)->create([
                                'name' => ['type' => 'string'],
                                'searchname' => ['type' => 'string'],
                                'fromname' => ['type' => 'string'],
                                'filename' => ['type' => 'string'],
                                'categories_id' => ['type' => 'integer'],
                            ]);
                        } elseif ($index === 'predb_rt') {
                            $this->manticoreSearch->table($index)->create([
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
     * Optimize the RT indices.
     *
     * @return bool Returns true if optimization was successful, false otherwise
     */
    public function optimizeRTIndex(): bool
    {
        try {
            foreach ($this->config['indexes'] as $index) {
                $this->manticoreSearch->table($index)->flush();
                $this->manticoreSearch->table($index)->optimize();
                Log::info("Successfully optimized index: {$index}");
            }

            return true;
        } catch (ResponseException $e) {
            Log::error('Failed to optimize RT indices: '.$e->getMessage());

            return false;
        } catch (\Throwable $e) {
            Log::error('Unexpected error while optimizing RT indices: '.$e->getMessage());

            return false;
        }
    }

    public function searchIndexes(string $rt_index, ?string $searchString, array $column = [], array $searchArray = []): array
    {
        $resultId = [];
        $resultData = [];
        try {
            $query = $this->search->setTable($rt_index)
                ->option('ranker', 'sph04')
                ->option('sort_method', 'pq')
                ->maxMatches(10000)
                ->limit(10000)
                ->sort('id', 'desc')
                ->stripBadUtf8(true)
                ->trackScores(true);

            // Process search string to handle apostrophes
            $processSearchString = function ($text) {
                if (empty($text)) {
                    return '';
                }

                // For words with apostrophes, add a version without apostrophes
                $processedText = preg_replace_callback('/\b(\w+\'(?:\w+)?)\b/', function ($matches) {
                    $withApostrophe = self::escapeString($matches[1]);
                    $withoutApostrophe = self::escapeString(str_replace("'", '', $matches[1]));

                    return "($withApostrophe | $withoutApostrophe)";
                }, $text);

                // For common contractions without apostrophes, add versions with apostrophes
                $contractions = [
                    '/\bim\b/i' => "i'm",
                    '/\bdont\b/i' => "don't",
                    '/\bcant\b/i' => "can't",
                    '/\bwont\b/i' => "won't",
                    '/\bdidnt\b/i' => "didn't",
                    '/\bisnt\b/i' => "isn't",
                    '/\bwasnt\b/i' => "wasn't",
                    '/\bwouldnt\b/i' => "wouldn't",
                    '/\bcouldnt\b/i' => "couldn't",
                    '/\bshouldnt\b/i' => "shouldn't",
                    '/\barent\b/i' => "aren't",
                    '/\bwerent\b/i' => "weren't",
                    '/\byoure\b/i' => "you're",
                    '/\btheyre\b/i' => "they're",
                    '/\bive\b/i' => "i've",
                    '/\btheyve\b/i' => "they've",
                    '/\bweve\b/i' => "we've",
                    '/\byouve\b/i' => "you've",
                    '/\byoull\b/i' => "you'll",
                    '/\btheyll\b/i' => "they'll",
                    '/\bhes\b/i' => "he's",
                    '/\bshes\b/i' => "she's",
                    '/\bthats\b/i' => "that's",
                    '/\bwhats\b/i' => "what's",
                    '/\bwhos\b/i' => "who's",
                ];

                foreach ($contractions as $pattern => $replacement) {
                    $processedText = preg_replace_callback($pattern, function ($matches) use ($replacement) {
                        $withoutApostrophe = self::escapeString($matches[0]);
                        $withApostrophe = self::escapeString($replacement);

                        return "($withoutApostrophe | $withApostrophe)";
                    }, $processedText);
                }

                // Escape any remaining words
                $words = preg_split('/\s+/', $processedText);
                $finalWords = [];

                foreach ($words as $word) {
                    // Skip if it's already a combined condition
                    if (strpos($word, ' | ') !== false) {
                        $finalWords[] = $word;
                    } else {
                        $finalWords[] = self::escapeString($word);
                    }
                }

                return implode(' ', $finalWords);
            };

            if (! empty($searchArray)) {
                foreach ($searchArray as $key => $value) {
                    $processedValue = $processSearchString($value);
                    $query->search('@@relaxed @'.$key.' '.$processedValue);
                }
            } elseif (! empty($searchString)) {
                // If $column is an array and has more than one item, implode it and wrap in parentheses.
                if (! empty($column) && \count($column) > 1) {
                    $searchColumns = '@('.implode(',', $column).')';
                } elseif (! empty($column) && \count($column) === 1) { // If $column is an array and has only one item, use as is.
                    $searchColumns = '@'.$column[0];
                } else {
                    $searchColumns = ''; // Careful, this will search all columns.
                }

                $processedSearchString = $processSearchString($searchString);
                $query->search('@@relaxed '.$searchColumns.' '.$processedSearchString);
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
