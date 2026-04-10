<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SecondarySearchIndex;
use App\Facades\Elasticsearch;
use App\Facades\Search;
use App\Models\BookInfo;
use App\Models\ConsoleInfo;
use App\Models\GamesInfo;
use App\Models\MovieInfo;
use App\Models\MusicInfo;
use App\Models\Predb;
use App\Models\Release;
use App\Models\SteamApp;
use App\Models\Video;
use App\Support\ReleaseSearchIndexDocument;
use App\Support\SecondaryIndexDocuments;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NntmuxPopulateSearchIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:populate
                                       {--manticore : Use ManticoreSearch}
                                       {--elastic : Use ElasticSearch}
                                       {--releases : Populates the releases index}
                                       {--predb : Populates the predb index}
                                       {--movies : Populates the movies index}
                                       {--tvshows : Populates the TV shows index}
                                       {--music : Populates the music metadata index}
                                       {--books : Populates the books metadata index}
                                       {--games : Populates the games metadata index}
                                       {--console : Populates the console metadata index}
                                       {--steam : Populates the Steam apps index}
                                       {--anime : Populates the anime titles index}
                                       {--all : Populate all supported indexes for the selected engine}
                                       {--count=50000 : Sets the chunk size}
                                       {--parallel=4 : Number of parallel processes}
                                       {--batch-size=5000 : Batch size for bulk operations}
                                       {--disable-keys : Disable database keys during population}
                                       {--optimize : Optimize ManticoreSearch indexes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate Manticore/Elasticsearch indexes (releases, predb, movies, tvshows, music, books, games, console, steam, anime)';

    private const SUPPORTED_ENGINES = ['manticore', 'elastic'];

    private const SUPPORTED_INDEXES = [
        'releases', 'predb', 'movies', 'tvshows',
        'music', 'books', 'games', 'console', 'steam', 'anime',
    ];

    private const GROUP_CONCAT_MAX_LEN = 16384;

    private const DEFAULT_CHUNK_SIZE = 50000;

    /** @phpstan-ignore classConstant.unused */
    private const DEFAULT_PARALLEL_PROCESSES = 4;

    private const DEFAULT_BATCH_SIZE = 5000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            if ($this->option('optimize')) {
                return $this->handleOptimize();
            }

            $engine = $this->getSelectedEngine();
            $indexes = $this->getSelectedIndexes();

            if (! $engine || $indexes === []) {
                $this->error('You must specify an engine (--manticore or --elastic) and at least one index (or --all).');
                $this->info('Use --help to see all available options.');

                return Command::FAILURE;
            }

            $exit = Command::SUCCESS;
            foreach ($indexes as $index) {
                $result = $this->populateIndex($engine, $index);
                if ($result !== Command::SUCCESS) {
                    $exit = $result;
                }
            }

            return $exit;

        } catch (Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get the selected search engine from options
     */
    private function getSelectedEngine(): ?string
    {
        foreach (self::SUPPORTED_ENGINES as $engine) {
            if ($this->option($engine)) {
                return $engine;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getSelectedIndexes(): array
    {
        if ($this->option('all')) {
            return self::SUPPORTED_INDEXES;
        }

        $selected = [];
        foreach (self::SUPPORTED_INDEXES as $index) {
            if ($this->option($index)) {
                $selected[] = $index;
            }
        }

        return $selected;
    }

    /**
     * Handle the optimize command
     */
    private function handleOptimize(): int
    {
        $this->info('Optimizing search indexes...');

        try {
            Search::optimizeIndex();
            $this->info('Optimization completed successfully!');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Optimization failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Populate the specified index with the specified engine
     */
    private function populateIndex(string $engine, string $index): int
    {
        $methodName = "{$engine}".ucfirst($index);

        if (! method_exists($this, $methodName)) {
            $this->error("Method {$methodName} not implemented.");

            return Command::FAILURE;
        }

        $this->info("Starting {$engine} {$index} population...");

        $startTime = microtime(true);
        $result = $this->{$methodName}();
        $executionTime = round(microtime(true) - $startTime, 2);

        if ($result === Command::SUCCESS) {
            $this->info("Population completed in {$executionTime} seconds.");
        }

        return $result;
    }

    private function manticoreReleases(): int
    {
        $indexName = 'releases_rt';

        Search::truncateIndex([$indexName]);

        $total = Release::count();
        if (! $total) {
            $this->warn('Releases table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        // Optimized query: avoid GROUP_CONCAT and complex joins for faster population
        // External media IDs can be populated separately if needed
        $query = Release::query()
            ->orderByDesc('releases.id')
            ->select([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
                'releases.size',
                'releases.postdate',
                'releases.adddate',
                'releases.totalpart',
                'releases.grabs',
                'releases.passwordstatus',
                'releases.groups_id',
                'releases.nzbstatus',
                'releases.haspreview',
                'releases.videos_id',
                'releases.movieinfo_id',
                'releases.imdbid',
            ]);

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            function ($item) {
                return ReleaseSearchIndexDocument::normalize(array_merge(
                    (array) $item->getAttributes(),
                    ['filename' => '']
                ));
            }
        );
    }

    /**
     * Populate ManticoreSearch predb index
     */
    private function manticorePredb(): int
    {
        $indexName = 'predb_rt';

        Search::truncateIndex([$indexName]);

        $total = Predb::count();
        if (! $total) {
            $this->warn('PreDB table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Predb::query()
            ->select(['id', 'title', 'filename', 'source'])
            ->orderBy('id');

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            function ($item) {
                return [
                    'id' => $item->id,
                    'title' => (string) ($item->title ?? ''),
                    'filename' => (string) ($item->filename ?? ''),
                    'source' => (string) ($item->source ?? ''),
                ];
            }
        );
    }

    /**
     * Populate ManticoreSearch movies index
     */
    private function manticoreMovies(): int
    {
        $indexName = 'movies_rt';

        Search::truncateIndex([$indexName]);

        $total = MovieInfo::count();
        if (! $total) {
            $this->warn('MovieInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = MovieInfo::query()
            ->select([
                'id',
                'imdbid',
                'tmdbid',
                'traktid',
                'title',
                'year',
                'genre',
                'actors',
                'director',
                'rating',
                'plot',
            ])
            ->orderBy('id');

        return $this->processManticoreMoviesData(
            $indexName,
            $total,
            $query,
            function ($item) {
                return [
                    'id' => $item->id,
                    'imdbid' => (string) ($item->imdbid ?? ''),
                    'tmdbid' => (int) ($item->tmdbid ?? 0),
                    'traktid' => (int) ($item->traktid ?? 0),
                    'title' => (string) ($item->title ?? ''),
                    'year' => (string) ($item->year ?? ''),
                    'genre' => (string) ($item->genre ?? ''),
                    'actors' => (string) ($item->actors ?? ''),
                    'director' => (string) ($item->director ?? ''),
                    'rating' => (string) ($item->rating ?? ''),
                    'plot' => (string) ($item->plot ?? ''),
                ];
            }
        );
    }

    /**
     * Populate ManticoreSearch TV shows index
     */
    private function manticoreTvshows(): int
    {
        $indexName = 'tvshows_rt';

        Search::truncateIndex([$indexName]);

        $total = Video::count();
        if (! $total) {
            $this->warn('Videos table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Video::query()
            ->select([
                'id',
                'title',
                'tvdb',
                'trakt',
                'tvmaze',
                'tvrage',
                'imdb',
                'tmdb',
                'started',
                'type',
            ])
            ->orderBy('id');

        return $this->processManticoreTvShowsData(
            $indexName,
            $total,
            $query,
            function ($item) {
                return [
                    'id' => $item->id,
                    'title' => (string) ($item->title ?? ''),
                    'tvdb' => (int) ($item->tvdb ?? 0),
                    'trakt' => (int) ($item->trakt ?? 0),
                    'tvmaze' => (int) ($item->tvmaze ?? 0),
                    'tvrage' => (int) ($item->tvrage ?? 0),
                    'imdb' => (string) ($item->imdb ?? ''),
                    'tmdb' => (int) ($item->tmdb ?? 0),
                    'started' => (string) ($item->started ?? ''),
                    'type' => (int) ($item->type ?? 0),
                ];
            }
        );
    }

    private function manticoreMusic(): int
    {
        $indexName = 'music_rt';
        Search::truncateIndex([$indexName]);
        $total = MusicInfo::count();
        if (! $total) {
            $this->warn('MusicInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = MusicInfo::query()->orderBy('id');

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            fn (MusicInfo $m): array => array_merge(['id' => $m->id], SecondaryIndexDocuments::music($m))
        );
    }

    private function manticoreBooks(): int
    {
        $indexName = 'books_rt';
        Search::truncateIndex([$indexName]);
        $total = BookInfo::count();
        if (! $total) {
            $this->warn('BookInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = BookInfo::query()->orderBy('id');

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            fn (BookInfo $b): array => array_merge(['id' => $b->id], SecondaryIndexDocuments::book($b))
        );
    }

    private function manticoreGames(): int
    {
        $indexName = 'games_rt';
        Search::truncateIndex([$indexName]);
        $total = GamesInfo::count();
        if (! $total) {
            $this->warn('GamesInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = GamesInfo::query()->orderBy('id');

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            fn (GamesInfo $g): array => array_merge(['id' => $g->id], SecondaryIndexDocuments::games($g))
        );
    }

    private function manticoreConsole(): int
    {
        $indexName = 'console_rt';
        Search::truncateIndex([$indexName]);
        $total = ConsoleInfo::count();
        if (! $total) {
            $this->warn('ConsoleInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = ConsoleInfo::query()->orderBy('id');

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            fn (ConsoleInfo $c): array => array_merge(['id' => $c->id], SecondaryIndexDocuments::console($c))
        );
    }

    private function manticoreSteam(): int
    {
        $indexName = 'steam_rt';
        Search::truncateIndex([$indexName]);
        $total = SteamApp::count();
        if (! $total) {
            $this->warn('Steam apps table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = SteamApp::query()->orderBy('id');

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            fn (SteamApp $s): array => array_merge(['id' => (int) $s->id], SecondaryIndexDocuments::steam($s))
        );
    }

    private function manticoreAnime(): int
    {
        $indexName = 'anime_rt';
        Search::truncateIndex([$indexName]);
        $total = (int) DB::table('anidb_titles')->count();
        if (! $total) {
            $this->warn('anidb_titles table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = DB::table('anidb_titles as t')
            ->leftJoin('anidb_info as i', 't.anidbid', '=', 'i.anidbid')
            ->orderBy('t.anidbid')
            ->select([
                't.anidbid', 't.type', 't.lang', 't.title',
                'i.anilist_id', 'i.mal_id', 'i.media_type', 'i.status',
            ]);

        return $this->processManticoreData(
            $indexName,
            $total,
            $query,
            function ($row) {
                $docId = SecondarySearchIndex::animeTitleDocumentId(
                    (int) $row->anidbid,
                    (string) $row->type,
                    (string) $row->lang,
                    (string) $row->title
                );

                return array_merge(['id' => $docId], [
                    'title' => (string) $row->title,
                    'anidbid' => (int) $row->anidbid,
                    'anilist_id' => (int) ($row->anilist_id ?? 0),
                    'mal_id' => (int) ($row->mal_id ?? 0),
                    'lang' => (string) $row->lang,
                    'title_type' => (string) $row->type,
                    'media_type' => (string) ($row->media_type ?? ''),
                    'status' => (string) ($row->status ?? ''),
                ]);
            }
        );
    }

    /**
     * Process data for ManticoreSearch with optimizations
     */
    private function processManticoreData(string $indexName, int $total, mixed $query, callable $transformer): int
    {
        $chunkSize = $this->getChunkSize();
        $batchSize = $this->getBatchSize();

        $this->optimizeDatabase();
        $this->setGroupConcatMaxLen();

        $this->info(sprintf(
            "Populating search index '%s' with %s rows using chunks of %s and batch size of %s.",
            $indexName,
            number_format($total),
            number_format($chunkSize),
            number_format($batchSize)
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;
        $batchData = [];

        try {
            $query->chunk($chunkSize, function ($items) use ($indexName, $transformer, $bar, &$processedCount, &$errorCount, $batchSize, &$batchData) {
                foreach ($items as $item) {
                    try {
                        $batchData[] = $transformer($item);
                        $processedCount++;

                        // Process in optimized batch sizes
                        if (count($batchData) >= $batchSize) {
                            $this->processBatch($indexName, $batchData); // @phpstan-ignore argument.type
                            $batchData = [];
                        }
                    } catch (Exception $e) {
                        $errorCount++;
                        if ($this->output->isVerbose()) {
                            $this->error("Error processing item {$item->id}: {$e->getMessage()}");
                        }
                    }
                    $bar->advance();
                }
            });

            // Process remaining items
            if (! empty($batchData)) {
                $this->processBatch($indexName, $batchData); // @phpstan-ignore argument.type
            }

            $bar->finish();
            $this->newLine();

            if ($errorCount > 0) {
                $this->warn("Completed with {$errorCount} errors out of {$processedCount} processed items.");
            } else {
                $this->info('Search index population completed successfully!');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("Failed to populate ManticoreSearch: {$e->getMessage()}");

            return Command::FAILURE;
        } finally {
            $this->restoreDatabase();
        }
    }

    /**
     * Process data for ManticoreSearch movies index
     */
    private function processManticoreMoviesData(string $indexName, int $total, mixed $query, callable $transformer): int
    {
        $chunkSize = $this->getChunkSize();
        $batchSize = $this->getBatchSize();

        $this->optimizeDatabase();

        $this->info(sprintf(
            "Populating search index '%s' with %s rows using chunks of %s and batch size of %s.",
            $indexName,
            number_format($total),
            number_format($chunkSize),
            number_format($batchSize)
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;
        $batchData = [];

        try {
            $query->chunk($chunkSize, function ($items) use ($transformer, $bar, &$processedCount, &$errorCount, $batchSize, &$batchData) {
                foreach ($items as $item) {
                    try {
                        $batchData[] = $transformer($item);
                        $processedCount++;

                        // Process in optimized batch sizes
                        if (count($batchData) >= $batchSize) {
                            $this->processMoviesBatch($batchData); // @phpstan-ignore argument.type
                            $batchData = [];
                        }
                    } catch (Exception $e) {
                        $errorCount++;
                        if ($this->output->isVerbose()) {
                            $this->error("Error processing item {$item->id}: {$e->getMessage()}");
                        }
                    }
                    $bar->advance();
                }
            });

            // Process remaining items
            if (! empty($batchData)) {
                $this->processMoviesBatch($batchData); // @phpstan-ignore argument.type
            }

            $bar->finish();
            $this->newLine();

            if ($errorCount > 0) {
                $this->warn("Completed with {$errorCount} errors out of {$processedCount} processed items.");
            } else {
                $this->info('Movies index population completed successfully!');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("Failed to populate movies index: {$e->getMessage()}");

            return Command::FAILURE;
        } finally {
            $this->restoreDatabase();
        }
    }

    /**
     * Process data for ManticoreSearch TV shows index
     */
    private function processManticoreTvShowsData(string $indexName, int $total, mixed $query, callable $transformer): int
    {
        $chunkSize = $this->getChunkSize();
        $batchSize = $this->getBatchSize();

        $this->optimizeDatabase();

        $this->info(sprintf(
            "Populating search index '%s' with %s rows using chunks of %s and batch size of %s.",
            $indexName,
            number_format($total),
            number_format($chunkSize),
            number_format($batchSize)
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;
        $batchData = [];

        try {
            $query->chunk($chunkSize, function ($items) use ($transformer, $bar, &$processedCount, &$errorCount, $batchSize, &$batchData) {
                foreach ($items as $item) {
                    try {
                        $batchData[] = $transformer($item);
                        $processedCount++;

                        // Process in optimized batch sizes
                        if (count($batchData) >= $batchSize) {
                            $this->processTvShowsBatch($batchData); // @phpstan-ignore argument.type
                            $batchData = [];
                        }
                    } catch (Exception $e) {
                        $errorCount++;
                        if ($this->output->isVerbose()) {
                            $this->error("Error processing item {$item->id}: {$e->getMessage()}");
                        }
                    }
                    $bar->advance();
                }
            });

            // Process remaining items
            if (! empty($batchData)) {
                $this->processTvShowsBatch($batchData); // @phpstan-ignore argument.type
            }

            $bar->finish();
            $this->newLine();

            if ($errorCount > 0) {
                $this->warn("Completed with {$errorCount} errors out of {$processedCount} processed items.");
            } else {
                $this->info('TV shows index population completed successfully!');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("Failed to populate TV shows index: {$e->getMessage()}");

            return Command::FAILURE;
        } finally {
            $this->restoreDatabase();
        }
    }

    /**
     * Populate ElasticSearch releases index
     */
    private function elasticReleases(): int
    {
        $total = Release::count();
        if (! $total) {
            $this->warn('Releases table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Release::query()
            ->orderByDesc('releases.id')
            ->leftJoin('release_files', 'releases.id', '=', 'release_files.releases_id')
            ->select([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
                'releases.postdate',
            ])
            ->selectRaw('IFNULL(GROUP_CONCAT(release_files.name SEPARATOR " "),"") AS filename')
            ->groupBy([
                'releases.id',
                'releases.name',
                'releases.searchname',
                'releases.fromname',
                'releases.categories_id',
                'releases.postdate',
            ]);

        return $this->processElasticData(
            'releases',
            $total,
            $query,
            function ($item) {
                $searchName = str_replace(['.', '-'], ' ', $item->searchname ?? '');

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'searchname' => $item->searchname,
                    'plainsearchname' => $searchName,
                    'fromname' => $item->fromname,
                    'categories_id' => $item->categories_id,
                    'filename' => $item->filename ?? '',
                    'postdate' => $item->postdate,
                ];
            }
        );
    }

    /**
     * Populate ElasticSearch predb index
     */
    private function elasticPredb(): int
    {
        $total = Predb::count();
        if (! $total) {
            $this->warn('PreDB table is empty. Nothing to do.');

            return Command::SUCCESS;
        }

        $query = Predb::query()
            ->select(['id', 'title', 'filename', 'source'])
            ->orderBy('id');

        return $this->processElasticData(
            'predb',
            $total,
            $query,
            function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'filename' => $item->filename,
                    'source' => $item->source,
                ];
            }
        );
    }

    private function elasticMusic(): int
    {
        $ix = (string) config('search.drivers.elasticsearch.indexes.music', 'music');
        Search::truncateIndex([$ix]);
        $total = MusicInfo::count();
        if (! $total) {
            $this->warn('MusicInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = MusicInfo::query()->orderBy('id');

        return $this->processSecondaryElasticChunks(
            SecondarySearchIndex::Music,
            $total,
            $query,
            fn (MusicInfo $m): array => array_merge(['id' => $m->id], SecondaryIndexDocuments::music($m))
        );
    }

    private function elasticBooks(): int
    {
        $ix = (string) config('search.drivers.elasticsearch.indexes.books', 'books');
        Search::truncateIndex([$ix]);
        $total = BookInfo::count();
        if (! $total) {
            $this->warn('BookInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = BookInfo::query()->orderBy('id');

        return $this->processSecondaryElasticChunks(
            SecondarySearchIndex::Books,
            $total,
            $query,
            fn (BookInfo $b): array => array_merge(['id' => $b->id], SecondaryIndexDocuments::book($b))
        );
    }

    private function elasticGames(): int
    {
        $ix = (string) config('search.drivers.elasticsearch.indexes.games', 'games');
        Search::truncateIndex([$ix]);
        $total = GamesInfo::count();
        if (! $total) {
            $this->warn('GamesInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = GamesInfo::query()->orderBy('id');

        return $this->processSecondaryElasticChunks(
            SecondarySearchIndex::Games,
            $total,
            $query,
            fn (GamesInfo $g): array => array_merge(['id' => $g->id], SecondaryIndexDocuments::games($g))
        );
    }

    private function elasticConsole(): int
    {
        $ix = (string) config('search.drivers.elasticsearch.indexes.console', 'console');
        Search::truncateIndex([$ix]);
        $total = ConsoleInfo::count();
        if (! $total) {
            $this->warn('ConsoleInfo table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = ConsoleInfo::query()->orderBy('id');

        return $this->processSecondaryElasticChunks(
            SecondarySearchIndex::Console,
            $total,
            $query,
            fn (ConsoleInfo $c): array => array_merge(['id' => $c->id], SecondaryIndexDocuments::console($c))
        );
    }

    private function elasticSteam(): int
    {
        $ix = (string) config('search.drivers.elasticsearch.indexes.steam', 'steam');
        Search::truncateIndex([$ix]);
        $total = SteamApp::count();
        if (! $total) {
            $this->warn('Steam apps table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = SteamApp::query()->orderBy('id');

        return $this->processSecondaryElasticChunks(
            SecondarySearchIndex::Steam,
            $total,
            $query,
            fn (SteamApp $s): array => array_merge(['id' => (int) $s->id], SecondaryIndexDocuments::steam($s))
        );
    }

    private function elasticAnime(): int
    {
        $ix = (string) config('search.drivers.elasticsearch.indexes.anime', 'anime');
        Search::truncateIndex([$ix]);
        $total = (int) DB::table('anidb_titles')->count();
        if (! $total) {
            $this->warn('anidb_titles table is empty. Nothing to do.');

            return Command::SUCCESS;
        }
        $query = DB::table('anidb_titles as t')
            ->leftJoin('anidb_info as i', 't.anidbid', '=', 'i.anidbid')
            ->orderBy('t.anidbid')
            ->select([
                't.anidbid', 't.type', 't.lang', 't.title',
                'i.anilist_id', 'i.mal_id', 'i.media_type', 'i.status',
            ]);

        return $this->processSecondaryElasticChunks(
            SecondarySearchIndex::Anime,
            $total,
            $query,
            function ($row) {
                $docId = SecondarySearchIndex::animeTitleDocumentId(
                    (int) $row->anidbid,
                    (string) $row->type,
                    (string) $row->lang,
                    (string) $row->title
                );

                return array_merge(['id' => $docId], [
                    'title' => (string) $row->title,
                    'anidbid' => (int) $row->anidbid,
                    'anilist_id' => (int) ($row->anilist_id ?? 0),
                    'mal_id' => (int) ($row->mal_id ?? 0),
                    'lang' => (string) $row->lang,
                    'title_type' => (string) $row->type,
                    'media_type' => (string) ($row->media_type ?? ''),
                    'status' => (string) ($row->status ?? ''),
                ]);
            }
        );
    }

    /**
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function processSecondaryElasticChunks(SecondarySearchIndex $enum, int $total, mixed $query, callable $transformer): int
    {
        $chunkSize = $this->getChunkSize();
        $batchSize = $this->getBatchSize();

        $this->optimizeDatabase();
        $this->setGroupConcatMaxLen();

        $this->info(sprintf(
            'Populating Elasticsearch secondary index %s with %s rows.',
            $enum->value,
            number_format($total)
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;
        $batchData = [];

        try {
            $query->chunk($chunkSize, function ($items) use ($enum, $transformer, $bar, &$processedCount, &$errorCount, $batchSize, &$batchData) {
                foreach ($items as $item) {
                    try {
                        $batchData[] = $transformer($item);
                        $processedCount++;

                        if (count($batchData) >= $batchSize) {
                            Search::bulkInsertSecondary($enum, $batchData);
                            $batchData = [];
                        }
                    } catch (Exception $e) {
                        $errorCount++;
                        if ($this->output->isVerbose()) {
                            $this->error('Error processing secondary index row: '.$e->getMessage());
                        }
                    }
                    $bar->advance();
                }
            });

            if ($batchData !== []) {
                Search::bulkInsertSecondary($enum, $batchData);
            }

            $bar->finish();
            $this->newLine();

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error('Failed to populate secondary Elasticsearch index: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->restoreDatabase();
        }
    }

    /**
     * Process data for ElasticSearch with optimizations
     */
    private function processElasticData(string $indexName, int $total, mixed $query, callable $transformer): int
    {
        $chunkSize = $this->getChunkSize();
        $batchSize = $this->getBatchSize();

        $this->optimizeDatabase();
        $this->setGroupConcatMaxLen();

        $this->info(sprintf(
            "Populating ElasticSearch index '%s' with %s rows using chunks of %s and batch size of %s.",
            $indexName,
            number_format($total),
            number_format($chunkSize),
            number_format($batchSize)
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('verbose');
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        try {
            $query->chunk($chunkSize, function ($items) use ($indexName, $transformer, $bar, &$processedCount, &$errorCount, $batchSize) {
                // Process in optimized batches for ElasticSearch
                foreach ($items->chunk($batchSize) as $batch) {
                    $data = ['body' => []];

                    foreach ($batch as $item) {
                        try {
                            $transformedData = $transformer($item);

                            $data['body'][] = [
                                'index' => [
                                    '_index' => $indexName,
                                    '_id' => $item->id,
                                ],
                            ];
                            $data['body'][] = $transformedData;

                            $processedCount++;
                        } catch (Exception $e) {
                            $errorCount++;
                            if ($this->output->isVerbose()) {
                                $this->error("Error processing item {$item->id}: {$e->getMessage()}");
                            }
                        }

                        $bar->advance();
                    }

                    if (! empty($data['body'])) {
                        $this->processElasticBatch($data, $errorCount);
                    }
                }
            });

            $bar->finish();
            $this->newLine();

            if ($errorCount > 0) {
                $this->warn("Completed with {$errorCount} errors out of {$processedCount} processed items.");
            } else {
                $this->info('ElasticSearch population completed successfully!');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("Failed to populate ElasticSearch: {$e->getMessage()}");

            return Command::FAILURE;
        } finally {
            $this->restoreDatabase();
        }
    }

    /**
     * Process search index batch with retry logic
     *
     * @param  array<string, mixed>  $data
     */
    private function processBatch(string $indexName, array $data): void
    {
        $retries = 3;
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                // Use the correct bulk insert method based on index name
                if ($indexName === 'releases_rt') {
                    Search::bulkInsertReleases($data);
                } elseif ($indexName === 'predb_rt') {
                    Search::bulkInsertPredb($data);
                } elseif ($indexName === 'music_rt') {
                    Search::bulkInsertSecondary(SecondarySearchIndex::Music, $data);
                } elseif ($indexName === 'books_rt') {
                    Search::bulkInsertSecondary(SecondarySearchIndex::Books, $data);
                } elseif ($indexName === 'games_rt') {
                    Search::bulkInsertSecondary(SecondarySearchIndex::Games, $data);
                } elseif ($indexName === 'console_rt') {
                    Search::bulkInsertSecondary(SecondarySearchIndex::Console, $data);
                } elseif ($indexName === 'steam_rt') {
                    Search::bulkInsertSecondary(SecondarySearchIndex::Steam, $data);
                } elseif ($indexName === 'anime_rt') {
                    Search::bulkInsertSecondary(SecondarySearchIndex::Anime, $data);
                } else {
                    throw new Exception("Unknown index: {$indexName}");
                }
                break;
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw $e;
                }
                usleep(100000); // 100ms delay before retry
            }
        }
    }

    /**
     * Process movies batch with retry logic
     *
     * @param  array<string, mixed>  $data
     */
    private function processMoviesBatch(array $data): void
    {
        $retries = 3;
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                Search::bulkInsertMovies($data);
                break;
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw $e;
                }
                usleep(100000); // 100ms delay before retry
            }
        }
    }

    /**
     * Process TV shows batch with retry logic
     *
     * @param  array<string, mixed>  $data
     */
    private function processTvShowsBatch(array $data): void
    {
        $retries = 3;
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                Search::bulkInsertTvShows($data);
                break;
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw $e;
                }
                usleep(100000); // 100ms delay before retry
            }
        }
    }

    /**
     * Process ElasticSearch batch with retry logic
     *
     * @param  array<string, mixed>  $data
     */
    private function processElasticBatch(array $data, int &$errorCount): void
    {
        $retries = 3;
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                $response = Elasticsearch::bulk($data);

                // Check for errors in bulk response
                if (isset($response['errors']) && $response['errors']) {
                    foreach ($response['items'] as $item) {
                        if (isset($item['index']['error'])) {
                            $errorCount++;
                            if ($this->output->isVerbose()) {
                                $this->error('ElasticSearch error: '.json_encode($item['index']['error']));
                            }
                        }
                    }
                }
                break;
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw $e;
                }
                usleep(100000); // 100ms delay before retry
            }
        }
    }

    /**
     * Optimize database settings for bulk operations
     */
    private function optimizeDatabase(): void
    {
        if ($this->option('disable-keys')) {
            $this->info('Disabling database keys for faster bulk operations...');

            try {
                // Disable foreign key checks
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
                DB::statement('SET UNIQUE_CHECKS = 0');
                DB::statement('SET AUTOCOMMIT = 0');

                // Increase buffer sizes
                DB::statement('SET SESSION innodb_buffer_pool_size = 1073741824'); // 1GB
                DB::statement('SET SESSION bulk_insert_buffer_size = 268435456'); // 256MB
                DB::statement('SET SESSION read_buffer_size = 2097152'); // 2MB
                DB::statement('SET SESSION sort_buffer_size = 16777216'); // 16MB

            } catch (Exception $e) {
                $this->warn("Could not optimize database settings: {$e->getMessage()}");
            }
        }
    }

    /**
     * Restore database settings after bulk operations
     */
    private function restoreDatabase(): void
    {
        if ($this->option('disable-keys')) {
            $this->info('Restoring database settings...');

            try {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                DB::statement('SET UNIQUE_CHECKS = 1');
                DB::statement('SET AUTOCOMMIT = 1');
                DB::statement('COMMIT');
            } catch (Exception $e) {
                $this->warn("Could not restore database settings: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get the chunk size from options
     */
    private function getChunkSize(): int
    {
        $chunkSize = (int) $this->option('count');

        return $chunkSize > 0 ? $chunkSize : self::DEFAULT_CHUNK_SIZE;
    }

    /**
     * Get the batch size from options
     */
    private function getBatchSize(): int
    {
        $batchSize = (int) $this->option('batch-size');

        return $batchSize > 0 ? $batchSize : self::DEFAULT_BATCH_SIZE;
    }

    /**
     * Set the GROUP_CONCAT max length for the session
     */
    private function setGroupConcatMaxLen(): void
    {
        DB::statement('SET SESSION group_concat_max_len = ?', [self::GROUP_CONCAT_MAX_LEN]);
    }
}
