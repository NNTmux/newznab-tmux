<?php

namespace App\Services\AdultProcessing;

use App\Models\Category;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Settings;
use App\Models\XxxInfo;
use App\Services\AdultProcessing\Pipes\AbstractAdultProviderPipe;
use App\Services\AdultProcessing\Pipes\AdePipe;
use App\Services\AdultProcessing\Pipes\AdmPipe;
use App\Services\AdultProcessing\Pipes\AebnPipe;
use App\Services\AdultProcessing\Pipes\Data18Pipe;
use App\Services\AdultProcessing\Pipes\HotmoviesPipe;
use App\Services\AdultProcessing\Pipes\IafdPipe;
use App\Services\AdultProcessing\Pipes\PoppornPipe;
use App\Services\ReleaseImageService;
use Blacklight\ColorCLI;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline-based adult movie processing service using Laravel Pipeline.
 *
 * This service uses Laravel's Pipeline to orchestrate multiple adult movie data providers
 * to process releases and match them against movie metadata from various sources.
 *
 * It also supports parallel processing of multiple releases using Laravel's native Concurrency facade.
 */
class AdultProcessingPipeline
{
    /**
     * @var Collection<AbstractAdultProviderPipe>
     */
    protected Collection $pipes;

    protected int $movieQty;
    protected bool $echoOutput;
    protected ColorCLI $colorCli;
    protected ReleaseImageService $releaseImage;
    protected string $imgSavePath;
    protected string $cookie;
    protected string $showPasswords;

    /**
     * Processing statistics.
     */
    protected array $stats = [
        'processed' => 0,
        'matched' => 0,
        'failed' => 0,
        'skipped' => 0,
        'duration' => 0.0,
        'providers' => [],
    ];

    /**
     * @param iterable<AbstractAdultProviderPipe> $pipes
     */
    public function __construct(iterable $pipes = [], bool $echoOutput = true)
    {
        $this->pipes = collect($pipes);

        if ($this->pipes->isEmpty()) {
            $this->pipes = $this->getDefaultPipes();
        }

        $this->pipes = $this->pipes->sortBy(fn (AbstractAdultProviderPipe $p) => $p->getPriority());

        // Try to get settings from database, but handle failures gracefully (e.g., in child processes)
        try {
            $this->movieQty = (int) (Settings::settingValue('maxxxxprocessed') ?? 100);
            $this->showPasswords = app(\App\Services\Releases\ReleaseBrowseService::class)->showPasswords();
        } catch (\Exception $e) {
            // Fallback values for child processes where DB might not be available
            $this->movieQty = 100;
            $this->showPasswords = '';
        }

        $this->echoOutput = $echoOutput;
        $this->colorCli = new ColorCLI();
        $this->releaseImage = new ReleaseImageService();
        $this->imgSavePath = storage_path('covers/xxx/');
        $this->cookie = resource_path('tmp/xxx.cookie');
    }

    /**
     * Create a lightweight instance for child process use.
     * This avoids database calls that may fail in forked processes.
     */
    protected static function createForChildProcess(string $cookie, bool $echoOutput, string $imgSavePath): self
    {
        $instance = new self([], $echoOutput);
        $instance->cookie = $cookie;
        $instance->imgSavePath = $imgSavePath;
        return $instance;
    }

    /**
     * Get the default pipes in priority order.
     */
    protected function getDefaultPipes(): Collection
    {
        return collect([
            new AebnPipe(),
            new IafdPipe(),
            new Data18Pipe(),
            new PoppornPipe(),
            new AdmPipe(),
            new AdePipe(),
            new HotmoviesPipe(),
        ]);
    }

    /**
     * Add a provider pipe to the pipeline.
     */
    public function addPipe(AbstractAdultProviderPipe $pipe): self
    {
        $this->pipes->push($pipe);
        $this->pipes = $this->pipes->sortBy(fn (AbstractAdultProviderPipe $p) => $p->getPriority());

        return $this;
    }

    /**
     * Process a single movie title through the pipeline.
     *
     * @param string $movie Movie title to search for
     * @param bool $debug Whether to include debug information
     * @return array Processing result
     */
    public function processMovie(string $movie, bool $debug = false): array
    {
        $context = AdultReleaseContext::fromTitle($movie);
        $passable = new AdultProcessingPassable($context, $debug, $this->cookie);

        // Set echo output on all pipes
        foreach ($this->pipes as $pipe) {
            $pipe->setEchoOutput($this->echoOutput);
        }

        /** @var AdultProcessingPassable $result */
        $result = app(Pipeline::class)
            ->send($passable)
            ->through($this->pipes->values()->all())
            ->thenReturn();

        return $result->toArray();
    }

    /**
     * Process a single release through the pipeline.
     *
     * @param array|object $release Release data
     * @param string $cleanTitle Cleaned movie title
     * @param bool $debug Whether to include debug information
     * @return array Processing result
     */
    public function processRelease(array|object $release, string $cleanTitle, bool $debug = false): array
    {
        $context = AdultReleaseContext::fromRelease($release, $cleanTitle);
        $passable = new AdultProcessingPassable($context, $debug, $this->cookie);

        // Set echo output on all pipes
        foreach ($this->pipes as $pipe) {
            $pipe->setEchoOutput($this->echoOutput);
        }

        /** @var AdultProcessingPassable $result */
        $result = app(Pipeline::class)
            ->send($passable)
            ->through($this->pipes->values()->all())
            ->thenReturn();

        return $result->toArray();
    }

    /**
     * Process all XXX releases where xxxinfo_id is 0.
     *
     * Uses Laravel's native Concurrency facade for parallel processing when possible.
     */
    public function processXXXReleases(): void
    {
        $startTime = microtime(true);
        $this->resetStats();

        try {
            $releases = $this->getReleasesToProcess();
            $releaseCount = count($releases);

            if ($releaseCount === 0) {
                if ($this->echoOutput) {
                    $this->colorCli->header('No XXX releases to process.');
                }
                return;
            }

            if ($this->echoOutput) {
                $this->colorCli->header('Processing ' . $releaseCount . ' XXX releases using pipeline.');
            }

            // Process releases in batches for parallel processing
            $batchSize = min(5, $releaseCount); // Process up to 5 at a time
            $batches = array_chunk($releases->toArray(), $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                if ($this->echoOutput) {
                    $this->colorCli->info('Processing batch ' . ($batchIndex + 1) . ' of ' . count($batches));
                }
                $this->processBatch($batch);
            }
        } catch (\Throwable $e) {
            Log::error('processXXXReleases failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->echoOutput) {
                $this->colorCli->error('Error during processing: ' . $e->getMessage());
            }
        }

        $this->stats['duration'] = microtime(true) - $startTime;

        if ($this->echoOutput) {
            $this->outputStats();
        }
    }

    /**
     * Process a batch of releases using Laravel's native Concurrency for parallel execution.
     *
     * Note: Due to serialization limitations with DOMDocument and HtmlDomParser,
     * we process releases sequentially within the batch but can process multiple
     * batches concurrently using async tasks that create fresh instances.
     */
    protected function processBatch(array $batch): void
    {
        // Check if we can use async processing
        // For now, disable async to avoid child process issues with database connections
        // TODO: Re-enable async when database connection pooling is properly configured
        $useAsync = $this->canUseAsync() && config('nntmux.adult_processing_async', false);

        if (!$useAsync) {
            // Fall back to sequential processing (more reliable)
            foreach ($batch as $release) {
                $releaseId = (int) ($release['id'] ?? 0);

                if ($releaseId <= 0) {
                    Log::warning('Invalid release ID in batch: ' . json_encode($release));
                    $this->stats['failed']++;
                    continue;
                }

                try {
                    $this->processReleaseItem($release);
                } catch (\Throwable $e) {
                    // processReleaseItem should handle its own exceptions, but this is a fallback
                    Log::error('Unexpected error in processBatch for release ' . $releaseId . ': ' . $e->getMessage());
                    $this->stats['failed']++;

                    // Ensure the release is marked as processed to avoid infinite loop
                    try {
                        $this->updateReleaseXxxId($releaseId, -2);
                    } catch (\Throwable $updateError) {
                        Log::error('Failed to update release ' . $releaseId . ' with error code: ' . $updateError->getMessage());
                    }
                }
            }
            return;
        }

        // For async processing using Laravel's native Concurrency facade
        // We create independent tasks with only serializable data
        $cookie = $this->cookie;
        $echoOutput = $this->echoOutput;
        $imgSavePath = $this->imgSavePath;

        $tasks = [];
        foreach ($batch as $idx => $release) {
            // Extract only the serializable data needed
            $releaseData = [
                'id' => $release['id'],
                'searchname' => $release['searchname'],
            ];

            $tasks[$idx] = fn () => self::processReleaseInChildProcess($releaseData, $cookie, $echoOutput, $imgSavePath);
        }

        try {
            $results = Concurrency::run($tasks);

            // Update stats based on results
            foreach ($results as $result) {
                if (is_array($result)) {
                    if ($result['matched']) {
                        $this->stats['matched']++;
                    }
                    if (isset($result['provider'])) {
                        $this->stats['providers'][$result['provider']] =
                            ($this->stats['providers'][$result['provider']] ?? 0) + 1;
                    }
                }
                $this->stats['processed']++;
            }
        } catch (\Throwable $e) {
            Log::error('Async batch processing failed: ' . $e->getMessage());

            // Mark all releases in batch as processed with error to avoid infinite loop
            foreach ($batch as $release) {
                $this->stats['failed']++;
                try {
                    Release::query()->where('id', $release['id'])->update(['xxxinfo_id' => -2]);
                } catch (\Throwable $updateError) {
                    Log::error('Failed to mark release ' . $release['id'] . ' as processed: ' . $updateError->getMessage());
                }
            }
        }
    }

    /**
     * Process a release in a child process with fresh instances.
     * This is a static method to avoid serializing $this.
     */
    protected static function processReleaseInChildProcess(
        array $releaseData,
        string $cookie,
        bool $echoOutput,
        string $imgSavePath
    ): array {
        // Create fresh pipeline instance in child process
        $pipeline = new self([], $echoOutput);
        $pipeline->cookie = $cookie;
        $pipeline->imgSavePath = $imgSavePath;

        $result = [
            'id' => $releaseData['id'],
            'xxxinfo_id' => -2,
            'matched' => false,
            'provider' => null,
        ];

        $cleanTitle = $pipeline->parseXXXSearchName($releaseData['searchname']);

        if ($cleanTitle === false) {
            Release::query()->where('id', $releaseData['id'])->update(['xxxinfo_id' => -2]);
            return $result;
        }

        // Check if we already have this movie in the database
        $existingInfo = $pipeline->checkXXXInfoExists($cleanTitle);

        if ($existingInfo !== null) {
            $result['xxxinfo_id'] = (int) $existingInfo['id'];
            $result['matched'] = true;
        } else {
            $xxxId = $pipeline->updateXXXInfo($cleanTitle, $releaseData);
            $result['xxxinfo_id'] = $xxxId;
            $result['matched'] = $xxxId > 0;

            // Get the provider from the result
            if ($xxxId > 0) {
                $info = XxxInfo::find($xxxId);
                if ($info) {
                    $result['provider'] = $info->classused;
                }
            }
        }

        Release::query()->where('id', $releaseData['id'])->update(['xxxinfo_id' => $result['xxxinfo_id']]);

        return $result;
    }

    /**
     * Process a single release item.
     *
     * @return int XXX info ID or error code
     */
    protected function processReleaseItem(array $release): int
    {
        $idCheck = -2;
        $releaseId = (int) ($release['id'] ?? 0);

        if ($releaseId <= 0) {
            Log::warning('Invalid release ID in processReleaseItem');
            $this->stats['failed']++;
            return $idCheck;
        }

        try {
            $cleanTitle = $this->parseXXXSearchName($release['searchname'] ?? '');

            if ($cleanTitle === false) {
                if ($this->echoOutput) {
                    $this->colorCli->primary('.');
                }
                $this->updateReleaseXxxId($releaseId, $idCheck);
                $this->stats['skipped']++;
                return $idCheck;
            }

            // Check if we already have this movie in the database
            $existingInfo = $this->checkXXXInfoExists($cleanTitle);

            if ($existingInfo !== null) {
                if ($this->echoOutput) {
                    $this->colorCli->info('Local match found for XXX Movie: ' . $cleanTitle);
                }
                $idCheck = (int) $existingInfo['id'];
            } else {
                if ($this->echoOutput) {
                    $this->colorCli->info('Looking up: ' . $cleanTitle);
                    $this->colorCli->info('Local match not found, checking web!');
                }

                $idCheck = $this->updateXXXInfo($cleanTitle, $release);

                // If updateXXXInfo returned false, treat as -2
                if ($idCheck === false) {
                    $idCheck = -2;
                }
            }

            $this->updateReleaseXxxId($releaseId, $idCheck);
            $this->stats['processed']++;

            if ($idCheck > 0) {
                $this->stats['matched']++;
            }
        } catch (\Throwable $e) {
            Log::error('Processing failed for release ' . $releaseId . ': ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still mark as processed with error code to avoid infinite loop
            try {
                $this->updateReleaseXxxId($releaseId, -2);
            } catch (\Throwable $updateError) {
                Log::error('Failed to update release ' . $releaseId . ' after processing error: ' . $updateError->getMessage());
            }
            $this->stats['failed']++;
        }

        return $idCheck;
    }

    /**
     * Update XXX information from pipeline results.
     *
     * @return int|false XXX info ID or false on failure
     */
    public function updateXXXInfo(string $movie, ?array $release = null): int|false
    {
        $result = $this->processMovie($movie);

        if ($result['status'] !== AdultProcessingResult::STATUS_MATCHED) {
            return -2;
        }

        $providerName = $result['provider'];
        $movieData = $result['movieData'];

        if ($this->echoOutput) {
            $fromStr = match ($providerName) {
                'aebn' => 'Adult Entertainment Broadcast Network',
                'ade' => 'Adult DVD Empire',
                'pop' => 'PopPorn',
                'adm' => 'Adult DVD Marketplace',
                'hotm' => 'HotMovies',
                default => $providerName,
            };
            $this->colorCli->primary('Fetching XXX info from: ' . $fromStr);
        }

        // Track provider usage
        $this->stats['providers'][$providerName] = ($this->stats['providers'][$providerName] ?? 0) + 1;

        // Prepare the movie data for database storage
        $cast = !empty($movieData['cast']) ? implode(',', (array) $movieData['cast']) : '';
        $genres = !empty($movieData['genres']) ? $this->getGenreID($movieData['genres']) : '';

        $mov = [
            'trailers' => !empty($movieData['trailers']) ? serialize($movieData['trailers']) : '',
            'extras' => !empty($movieData['extras']) ? serialize($movieData['extras']) : '',
            'productinfo' => !empty($movieData['productinfo']) ? serialize($movieData['productinfo']) : '',
            'backdrop' => !empty($movieData['backcover']) ? $movieData['backcover'] : 0,
            'cover' => !empty($movieData['boxcover']) ? $movieData['boxcover'] : 0,
            'title' => !empty($movieData['title']) ? html_entity_decode($movieData['title'], ENT_QUOTES, 'UTF-8') : '',
            'plot' => !empty($movieData['synopsis']) ? html_entity_decode($movieData['synopsis'], ENT_QUOTES, 'UTF-8') : '',
            'tagline' => !empty($movieData['tagline']) ? html_entity_decode($movieData['tagline'], ENT_QUOTES, 'UTF-8') : '',
            'genre' => !empty($genres) ? html_entity_decode($genres, ENT_QUOTES, 'UTF-8') : '',
            'director' => !empty($movieData['director']) ? html_entity_decode($movieData['director'], ENT_QUOTES, 'UTF-8') : '',
            'actors' => !empty($cast) ? html_entity_decode($cast, ENT_QUOTES, 'UTF-8') : '',
            'directurl' => !empty($movieData['directurl']) ? html_entity_decode($movieData['directurl'], ENT_QUOTES, 'UTF-8') : '',
            'classused' => $providerName,
        ];

        $cover = 0;
        $backdrop = 0;
        $xxxID = false;

        // Check if this movie already exists
        $check = XxxInfo::query()->where('title', $mov['title'])->first(['id']);

        if ($check !== null && $check['id'] > 0) {
            $xxxID = $check['id'];

            // Update BoxCover
            if (!empty($mov['cover'])) {
                $cover = $this->releaseImage->saveImage($xxxID . '-cover', $mov['cover'], $this->imgSavePath);
            }

            // BackCover
            if (!empty($mov['backdrop'])) {
                $backdrop = $this->releaseImage->saveImage($xxxID . '-backdrop', $mov['backdrop'], $this->imgSavePath, 1920, 1024);
            }

            // Update existing record
            XxxInfo::query()->where('id', $check['id'])->update([
                'title' => $mov['title'],
                'tagline' => $mov['tagline'],
                'plot' => "\x1f\x8b\x08\x00" . gzcompress($mov['plot']),
                'genre' => substr($mov['genre'], 0, 64),
                'director' => $mov['director'],
                'actors' => $mov['actors'],
                'extras' => $mov['extras'],
                'productinfo' => $mov['productinfo'],
                'trailers' => $mov['trailers'],
                'directurl' => $mov['directurl'],
                'classused' => $mov['classused'],
                'cover' => empty($cover) ? 0 : $cover,
                'backdrop' => empty($backdrop) ? 0 : $backdrop,
            ]);
        } else {
            // Insert new record
            $xxxID = XxxInfo::query()->insertGetId([
                'title' => $mov['title'],
                'tagline' => $mov['tagline'],
                'plot' => "\x1f\x8b\x08\x00" . gzcompress($mov['plot']),
                'genre' => substr($mov['genre'], 0, 64),
                'director' => $mov['director'],
                'actors' => $mov['actors'],
                'extras' => $mov['extras'],
                'productinfo' => $mov['productinfo'],
                'trailers' => $mov['trailers'],
                'directurl' => $mov['directurl'],
                'classused' => $mov['classused'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update BoxCover
            if (!empty($mov['cover'])) {
                $cover = $this->releaseImage->saveImage($xxxID . '-cover', $mov['cover'], $this->imgSavePath);
            }

            // BackCover
            if (!empty($mov['backdrop'])) {
                $backdrop = $this->releaseImage->saveImage($xxxID . '-backdrop', $mov['backdrop'], $this->imgSavePath, 1920, 1024);
            }

            XxxInfo::whereId($xxxID)->update(['cover' => $cover, 'backdrop' => $backdrop]);
        }

        if ($this->echoOutput) {
            $this->colorCli->primary(
                ($xxxID !== false ? 'Added/updated XXX movie: ' . $mov['title'] : 'Nothing to update for XXX movie: ' . $mov['title']),
                true
            );
        }

        return $xxxID;
    }

    /**
     * Get releases to process.
     */
    protected function getReleasesToProcess(): \Illuminate\Database\Eloquent\Collection
    {
        return Release::query()
            ->where(['xxxinfo_id' => 0])
            ->whereIn('categories_id', [
                Category::XXX_DVD,
                Category::XXX_WMV,
                Category::XXX_XVID,
                Category::XXX_X264,
                Category::XXX_SD,
                Category::XXX_CLIPHD,
                Category::XXX_CLIPSD,
                Category::XXX_WEBDL,
                Category::XXX_UHD,
                Category::XXX_VR,
            ])
            ->limit($this->movieQty)
            ->get(['searchname', 'id']);
    }

    /**
     * Check if async processing can be used.
     */
    protected function canUseAsync(): bool
    {
        return class_exists('Illuminate\Support\Facades\Concurrency') &&
               function_exists('pcntl_fork') &&
               !defined('HHVM_VERSION');
    }

    /**
     * Check if XXX info already exists in database.
     */
    protected function checkXXXInfoExists(string $releaseName): ?array
    {
        $result = XxxInfo::query()->where('title', 'like', '%' . $releaseName . '%')->first(['id', 'title']);
        return $result ? $result->toArray() : null;
    }

    /**
     * Update release with XXX info ID.
     */
    protected function updateReleaseXxxId(int $releaseId, int $xxxInfoId): void
    {
        Release::query()->where('id', $releaseId)->update(['xxxinfo_id' => $xxxInfoId]);
    }

    /**
     * Get Genre ID from genre names.
     */
    protected function getGenreID(array|string $arr): string
    {
        $ret = null;

        if (!is_array($arr)) {
            $res = Genre::query()->where('title', $arr)->first(['id']);
            if ($res !== null) {
                return (string) $res['id'];
            }
            return '';
        }

        foreach ($arr as $value) {
            $res = Genre::query()->where('title', $value)->first(['id']);
            if ($res !== null) {
                $ret .= ',' . $res['id'];
            } else {
                $ret .= ',' . $this->insertGenre($value);
            }
        }

        return ltrim($ret, ',');
    }

    /**
     * Insert a new genre.
     */
    protected function insertGenre(string $genre): int|string
    {
        if ($genre !== null) {
            return Genre::query()->insertGetId([
                'title' => $genre,
                'type' => Category::XXX_ROOT,
                'disabled' => 0,
            ]);
        }
        return '';
    }

    /**
     * Parse XXX search name from release name.
     *
     * @return string|false Cleaned title or false if couldn't parse
     */
    protected function parseXXXSearchName(string $releaseName): string|false
    {
        $name = '';
        $followingList = '[^\w]((2160|1080|480|720)(p|i)|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR)(Rip)?|BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?Proper|RE(pack|Rip)|Sub\.?(fix|pack)|Unrated|WEB-DL|(x|H)[ ._-]?264|xvid|[Dd][Ii][Ss][Cc](\d+|\s*\d+|\.\d+)|XXX|BTS|DirFix|Trailer|WEBRiP|NFO|(19|20)\d\d)[^\w]';

        if (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)' . $followingList . '/i', $releaseName, $hits)) {
            $name = $hits['name'];
        }

        if ($name !== '') {
            $name = preg_replace('/' . $followingList . '/i', ' ', $name);
            $name = preg_replace('/\(.*?\)|[._-]/i', ' ', $name);
            $name = trim(preg_replace('/\s{2,}/', ' ', $name));
            $name = trim(preg_replace('/^Private\s(Specials|Blockbusters|Blockbuster|Sports|Gold|Lesbian|Movies|Classics|Castings|Fetish|Stars|Pictures|XXX|Private|Black\sLabel|Black)\s\d+/i', '', $name));
            $name = trim(preg_replace('/(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)$/i', '', $name));

            if (strlen($name) > 5 &&
                !preg_match('/^\d+$/', $name) &&
                !preg_match('/( File \d+ of \d+|\d+.\d+.\d+)/', $name) &&
                !preg_match('/(E\d+)/', $name) &&
                !preg_match('/\d\d\.\d\d.\d\d/', $name)
            ) {
                return $name;
            }
        }

        return false;
    }

    /**
     * Reset processing statistics.
     */
    protected function resetStats(): void
    {
        $this->stats = [
            'processed' => 0,
            'matched' => 0,
            'failed' => 0,
            'skipped' => 0,
            'duration' => 0.0,
            'providers' => [],
        ];
    }

    /**
     * Output processing statistics.
     */
    protected function outputStats(): void
    {
        $this->colorCli->header("\n=== Adult Processing Statistics ===");
        $this->colorCli->primary('Processed: ' . $this->stats['processed']);
        $this->colorCli->primary('Matched: ' . $this->stats['matched']);
        $this->colorCli->primary('Failed: ' . $this->stats['failed']);
        $this->colorCli->primary('Skipped: ' . $this->stats['skipped']);
        $this->colorCli->primary(sprintf('Duration: %.2f seconds', $this->stats['duration']));

        if (!empty($this->stats['providers'])) {
            $this->colorCli->header("\nProvider Statistics:");
            foreach ($this->stats['providers'] as $provider => $count) {
                $this->colorCli->primary("  {$provider}: {$count} matches");
            }
        }
    }

    /**
     * Get processing statistics.
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get all registered pipes.
     */
    public function getPipes(): Collection
    {
        return $this->pipes;
    }
}

