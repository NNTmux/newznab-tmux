<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Facades\Search;
use App\Services\NameFixing\Extractors\FileNameExtractor;
use App\Services\NameFixing\Extractors\NfoNameExtractor;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbContentsService;
use RuntimeException;

/**
 * Main service for name fixing operations.
 *
 * Orchestrates the various name fixing sources (NFO, Files, CRC, SRR, etc.)
 * and handles the overall processing flow.
 */
class NameFixingService
{
    // Constants for name fixing status
    public const PROC_NFO_NONE = 0;

    public const PROC_NFO_DONE = 1;

    public const PROC_FILES_NONE = 0;

    public const PROC_FILES_DONE = 1;

    public const PROC_PAR2_NONE = 0;

    public const PROC_PAR2_DONE = 1;

    public const PROC_UID_NONE = 0;

    public const PROC_UID_DONE = 1;

    public const PROC_HASH16K_NONE = 0;

    public const PROC_HASH16K_DONE = 1;

    public const PROC_SRR_NONE = 0;

    public const PROC_SRR_DONE = 1;

    public const PROC_CRC_NONE = 0;

    public const PROC_CRC_DONE = 1;

    // Constants for overall rename status
    public const IS_RENAMED_NONE = 0;

    public const IS_RENAMED_DONE = 1;

    protected ReleaseUpdateService $updateService;

    protected NameCheckerService $checkerService;

    protected NfoNameExtractor $nfoExtractor;

    protected FileNameExtractor $fileExtractor;

    protected FileNameCleaner $fileNameCleaner;

    protected FilePrioritizer $filePrioritizer;

    protected PredbMatchSelector $predbMatchSelector;

    protected NameFixingQueryService $queries;

    protected DonorMatchSelector $donorMatchSelector;

    /**
     * @var array<string, array<string, mixed>|null>
     */
    protected array $predbMatchCache = [];

    protected bool $echoOutput;

    protected int $_totalReleases = 0;

    public function __construct(
        ?ReleaseUpdateService $updateService = null,
        ?NameCheckerService $checkerService = null,
        ?NfoNameExtractor $nfoExtractor = null,
        ?FileNameExtractor $fileExtractor = null,
        ?FileNameCleaner $fileNameCleaner = null,
        ?FilePrioritizer $filePrioritizer = null,
        ?PredbMatchSelector $predbMatchSelector = null,
        ?NameFixingQueryService $queries = null,
        ?DonorMatchSelector $donorMatchSelector = null
    ) {
        $this->updateService = $updateService ?? new ReleaseUpdateService;
        $this->checkerService = $checkerService ?? new NameCheckerService;
        $this->nfoExtractor = $nfoExtractor ?? new NfoNameExtractor;
        $this->fileExtractor = $fileExtractor ?? new FileNameExtractor;
        $this->fileNameCleaner = $fileNameCleaner ?? new FileNameCleaner;
        $this->filePrioritizer = $filePrioritizer ?? new FilePrioritizer;
        $this->predbMatchSelector = $predbMatchSelector ?? new PredbMatchSelector($this->fileNameCleaner);
        $this->queries = $queries ?? new NameFixingQueryService;
        $this->donorMatchSelector = $donorMatchSelector ?? new DonorMatchSelector;
        $this->echoOutput = config('nntmux.echocli');
    }

    /**
     * Fix names using NFO content.
     */
    public function fixNamesWithNfo(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, '.nfo files');
        $type = 'NFO, ';
        $preIdOnly = $cats === 3;

        if (! $this->startBatch(NameFixingQueryService::SOURCE_NFO, $time, $cats, ' releases to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_NFO, $time, $cats) as $releases) {
            $nfos = $this->queries->groupByReleaseId($this->queries->nfoRows($this->releaseIds($releases)));

            foreach ($releases as $release) {
                $this->updateService->incrementChecked();
                $this->updateService->reset();
                $nfo = $nfos[(int) $release->releases_id][0] ?? null;
                if ($nfo === null) {
                    $this->markProcessed($echo, $nameStatus, 'proc_nfo', (int) $release->releases_id);

                    continue;
                }

                $release->textstring = (string) ($nfo->textstring ?? '');
                if (preg_match('/^=newz\[NZB\]=\w+/', $release->textstring)) {
                    $this->markProcessed($echo, $nameStatus, 'proc_nfo', (int) $release->releases_id);

                    continue;
                }

                $nfoResult = $this->nfoExtractor->extractFromNfo($release->textstring);
                if ($nfoResult !== null) {
                    $this->updateService->updateRelease(
                        $release,
                        $nfoResult->newName,
                        'nfoCheck: '.$nfoResult->method,
                        $echo,
                        $type,
                        $nameStatus,
                        $show
                    );
                }

                if (! $this->updateService->matched) {
                    $this->checkWithPatternMatchers($release, $echo, $type, $nameStatus, $show, $preIdOnly);
                }

                if (! $this->updateService->matched) {
                    $this->markProcessed($echo, $nameStatus, 'proc_nfo', (int) $release->releases_id);
                }

                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' NFO\'s');
    }

    /**
     * Fix names using file names.
     */
    public function fixNamesWithFiles(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'file names');
        $type = 'Filenames, ';

        if (! $this->startBatch(NameFixingQueryService::SOURCE_FILES, $time, $cats, ' file names to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_FILES, $time, $cats) as $releases) {
            $files = $this->queries->groupByReleaseId($this->queries->fileRows($this->releaseIds($releases)));

            foreach ($releases as $release) {
                $this->processFileCandidates($release, $files[(int) $release->releases_id] ?? [], $echo, $nameStatus, $show);
            }
        }

        $this->echoFoundCount($echo, ' files');
    }

    /**
     * Fix names using SRR files.
     */
    public function fixNamesWithSrr(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'SRR file names');
        $type = 'SRR, ';

        if (! $this->startBatch(NameFixingQueryService::SOURCE_SRR, $time, $cats, ' srr file extensions to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_SRR, $time, $cats) as $releases) {
            $files = $this->queries->groupByReleaseId(
                $this->queries->fileRows($this->releaseIds($releases), NameFixingQueryService::SOURCE_SRR)
            );

            foreach ($releases as $release) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();

                foreach ($files[(int) $release->releases_id] ?? [] as $file) {
                    $candidate = clone $release;
                    $candidate->textstring = (string) $file->textstring;
                    if ($this->srrNameCheck($candidate, $echo, $type, $nameStatus, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    $this->markProcessed($echo, $nameStatus, 'proc_srr', (int) $release->releases_id);
                }

                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' files');
    }

    /**
     * Fix names using CRC32 hashes.
     */
    public function fixNamesWithCrc(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'CRC32');
        $type = 'CRC32, ';

        if (! $this->startBatch(NameFixingQueryService::SOURCE_CRC, $time, $cats, ' CRC32\'s to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_CRC, $time, $cats) as $releases) {
            $files = $this->queries->groupByReleaseId(
                $this->queries->fileRows($this->releaseIds($releases), NameFixingQueryService::SOURCE_CRC)
            );
            $crcs = $this->distinctValues($files, 'crc32');
            $donors = $this->queries->crcDonors($crcs);

            foreach ($releases as $release) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();
                $prioritized = [];

                foreach ($files[(int) $release->releases_id] ?? [] as $file) {
                    $priority = $this->filePrioritizer->getCrcPriority((string) $file->filename);
                    $prioritized[$priority][(string) $file->crc32] = (string) $file->crc32;
                }

                ksort($prioritized);
                foreach ($prioritized as $values) {
                    foreach ($values as $crc) {
                        if ($this->applyDonorMatch($release, $donors[$crc] ?? [], 5, 'crcCheck: CRC32', $type, $echo, $nameStatus, $show)) {
                            break 2;
                        }
                    }
                }

                if (! $this->updateService->matched) {
                    $this->markProcessed($echo, $nameStatus, 'proc_crc32', (int) $release->releases_id);
                }

                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' crc32\'s');
    }

    /**
     * Fix names using Media info unique IDs.
     */
    public function fixNamesWithMedia(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $type = 'UID, ';
        $this->echoStartMessage($time, 'mediainfo Unique_IDs');

        if (! $this->startBatch(NameFixingQueryService::SOURCE_UID, $time, $cats, ' unique ids to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_UID, $time, $cats) as $releases) {
            $media = $this->queries->groupByReleaseId($this->queries->mediaRows($this->releaseIds($releases)));
            $uids = $this->distinctValues($media, 'uid');
            $donors = $this->queries->uidDonors($uids);

            foreach ($releases as $release) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();

                foreach ($media[(int) $release->releases_id] ?? [] as $row) {
                    $uid = (string) ($row->uid ?? '');
                    if ($uid !== '' && $this->applyDonorMatch($release, $donors[$uid] ?? [], 10, 'uidCheck: Unique_ID', $type, $echo, $nameStatus, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    $this->markProcessed($echo, $nameStatus, 'proc_uid', (int) $release->releases_id);
                }

                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' UID\'s');
    }

    /**
     * Fix names using PAR2 hash_16K.
     */
    public function fixNamesWithParHash(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $type = 'PAR2 hash, ';
        $this->echoStartMessage($time, 'PAR2 hash_16K');

        if (! $this->startBatch(NameFixingQueryService::SOURCE_HASH, $time, $cats, ' hash_16K to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_HASH, $time, $cats) as $releases) {
            $hashes = $this->queries->groupByReleaseId($this->queries->hashRows($this->releaseIds($releases)));
            $hashValues = $this->distinctValues($hashes, 'hash');
            $donors = $this->queries->hashDonors($hashValues);

            foreach ($releases as $release) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();

                foreach ($hashes[(int) $release->releases_id] ?? [] as $row) {
                    $hash = (string) ($row->hash ?? '');
                    if ($hash !== '' && $this->applyDonorMatch($release, $donors[$hash] ?? [], 5, 'hashCheck: PAR2 hash_16K', $type, $echo, $nameStatus, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    $this->markProcessed($echo, $nameStatus, 'proc_hash16k', (int) $release->releases_id);
                }

                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' hashes');
    }

    /**
     * Check with pattern matchers (TV, Movie, Game, App).
     */
    protected function checkWithPatternMatchers(object $release, bool $echo, string $type, bool $nameStatus, bool $show, bool $preId): void
    {
        // Check for PreDB match first
        $preDbMatch = $this->updateService->checkPreDbMatch($release, $release->textstring);
        if ($preDbMatch !== null) {
            $this->updateService->updateRelease(
                $release,
                $preDbMatch['title'],
                'preDB: Match',
                $echo,
                $type,
                $nameStatus,
                $show,
                $preDbMatch['id']
            );

            return;
        }

        if ($preId) {
            return;
        }

        // Try pattern checkers
        $result = $this->checkerService->check($release, $release->textstring);
        if ($result !== null) {
            $this->updateService->updateRelease(
                $release,
                $result->newName,
                $result->getFormattedMethod(),
                $echo,
                $type,
                $nameStatus,
                $show
            );
        }
    }

    /**
     * Check SRR file for release name.
     */
    protected function srrNameCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        $extractedName = null;

        if (preg_match('/^(.+)\.srr$/i', $release->textstring, $hit)) {
            $extractedName = $hit[1];
        } elseif (preg_match('/^(.+)\.srs$/i', $release->textstring, $hit)) {
            $extractedName = $hit[1];
        }

        if ($extractedName !== null) {
            if (preg_match('/[\\\\\/]([^\\\\\/]+)$/', $extractedName, $pathMatch)) {
                $extractedName = $pathMatch[1];
            }

            if (preg_match(ReleaseUpdateService::PREDB_REGEX, $extractedName)) {
                $this->updateService->updateRelease(
                    $release,
                    $extractedName,
                    'fileCheck: SRR extension',
                    $echo,
                    $type,
                    $nameStatus,
                    $show
                );

                return $this->updateService->matched;
            }
        }

        return false;
    }

    /**
     * Check PreDB for filename matches.
     */
    protected function preDbFileCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        $fileName = $this->fileNameCleaner->cleanForMatching($release->textstring);

        if (empty($fileName)) {
            return false;
        }

        $bestMatch = $this->findBestPredbMatch($fileName);
        if ($bestMatch !== null) {
            if (strcasecmp((string) ($bestMatch['title'] ?? ''), (string) $release->searchname) === 0) {
                $this->updateService->attachPredbId(
                    (int) $release->releases_id,
                    (int) ($bestMatch['id'] ?? 0)
                );

                return $this->updateService->matched;
            }

            $this->updateService->updateRelease(
                $release,
                $bestMatch['title'] ?? '',
                'PreDb: Filename match',
                $echo,
                $type,
                $nameStatus,
                $show,
                $bestMatch['id'] ?? null
            );

            return $this->updateService->matched;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findBestPredbMatch(string $fileName): ?array
    {
        if (array_key_exists($fileName, $this->predbMatchCache)) {
            return $this->predbMatchCache[$fileName];
        }

        if (count($this->predbMatchCache) >= 5000) {
            $this->predbMatchCache = [];
        }

        $results = Search::searchPredb($fileName);

        return $this->predbMatchCache[$fileName] = $this->predbMatchSelector->selectBestMatch($fileName, $results);
    }

    /**
     * @return \Generator<int, list<object>>
     */
    protected function candidateBatches(string $source, int $time, int $categories): \Generator
    {
        $afterId = 0;

        do {
            $batch = $this->queries->candidateBatch($source, $time, $categories, $afterId);
            if ($batch === []) {
                break;
            }

            yield $batch;
            $afterId = (int) $batch[array_key_last($batch)]->releases_id;
        } while (count($batch) === NameFixingQueryService::BATCH_SIZE);
    }

    protected function startBatch(string $source, int $time, int $categories, string $message): bool
    {
        $total = $this->queries->countCandidates($source, $time, $categories);
        $this->_totalReleases = $total;

        if ($total === 0) {
            return false;
        }

        cli()->info(number_format($total).$message);

        return true;
    }

    /**
     * @param  list<object>  $releases
     * @return list<int>
     */
    protected function releaseIds(array $releases): array
    {
        return array_values(array_map(
            static fn (object $release): int => (int) $release->releases_id,
            $releases
        ));
    }

    /**
     * @param  array<int, list<object>>  $groupedRows
     * @return list<string>
     */
    protected function distinctValues(array $groupedRows, string $column): array
    {
        $values = [];

        foreach ($groupedRows as $rows) {
            foreach ($rows as $row) {
                $value = (string) ($row->{$column} ?? '');
                if ($value !== '') {
                    $values[$value] = $value;
                }
            }
        }

        return array_values($values);
    }

    /**
     * @param  list<object>  $donors
     */
    protected function applyDonorMatch(
        object $release,
        array $donors,
        int $tolerancePercent,
        string $method,
        string $type,
        bool $echo,
        bool $nameStatus,
        bool $show
    ): bool {
        $donor = $this->donorMatchSelector->select($donors, (int) $release->relsize, $tolerancePercent);
        if ($donor === null) {
            return false;
        }

        if (strcasecmp((string) $donor->searchname, (string) $release->searchname) === 0) {
            $this->updateService->attachPredbId(
                (int) $release->releases_id,
                (int) $donor->predb_id
            );

            return $this->updateService->matched;
        }

        $this->updateService->updateRelease(
            $release,
            (string) $donor->searchname,
            $method,
            $echo,
            $type,
            $nameStatus,
            $show,
            (int) $donor->predb_id
        );

        return $this->updateService->matched;
    }

    /**
     * @param  list<object>  $files
     */
    protected function processFileCandidates(
        object $release,
        array $files,
        bool $echo,
        bool $nameStatus,
        bool $show,
        bool $incrementChecked = true,
        bool $showProgress = true
    ): void {
        $this->updateService->reset();
        if ($incrementChecked) {
            $this->updateService->incrementChecked();
        }
        $filenames = array_values(array_unique(array_map(
            static fn (object $file): string => (string) $file->textstring,
            $files
        )));
        $prioritized = $this->filePrioritizer->prioritizeForMatching($filenames);

        foreach ($prioritized as $filename) {
            $candidate = clone $release;
            $candidate->textstring = $filename;
            $fileResult = $this->fileExtractor->extractFromFile($filename);

            if ($fileResult !== null) {
                $this->updateService->updateRelease(
                    $candidate,
                    $fileResult->newName,
                    'fileCheck: '.$fileResult->method,
                    $echo,
                    'Filenames, ',
                    $nameStatus,
                    $show
                );
            }

            if (! $this->updateService->matched) {
                $this->preDbFileCheck($candidate, $echo, 'Filenames, ', $nameStatus, $show);
            }

            if ($this->updateService->matched) {
                break;
            }
        }

        if (! $this->updateService->matched) {
            $this->markProcessed($echo, $nameStatus, 'proc_files', (int) $release->releases_id);
        }

        if ($showProgress) {
            $this->echoRenamed($show);
        }
    }

    protected function markProcessed(bool $echo, bool $nameStatus, string $column, int $releaseId): void
    {
        if ($echo && $nameStatus) {
            $this->updateService->updateSingleColumn($column, 1, $releaseId);
        }
    }

    /**
     * Echo start message.
     */
    protected function echoStartMessage(int $time, string $type): void
    {
        cli()->info(
            sprintf(
                'Fixing search names %s using %s.',
                ($time === 1 ? 'in the past 6 hours' : 'since the beginning'),
                $type
            )
        );
    }

    /**
     * Echo found count.
     */
    protected function echoFoundCount(bool $echo, string $type): void
    {
        $stats = $this->updateService->getStats();
        if ($echo === true) {
            cli()->info(
                PHP_EOL.
                number_format($stats['fixed']).
                ' releases have had their names changed out of: '.
                number_format($stats['checked']).
                $type.'.'
            );
        } else {
            cli()->info(
                PHP_EOL.
                number_format($stats['fixed']).
                ' releases could have their names changed. '.
                number_format($stats['checked']).
                $type.' were checked.'
            );
        }
    }

    /**
     * Echo renamed progress.
     */
    protected function echoRenamed(bool $show): void
    {
        $stats = $this->updateService->getStats();

        // Show milestone message every 500 releases
        if ($stats['checked'] % 500 === 0 && $stats['checked'] > 0) {
            cli()->alternate(PHP_EOL.number_format($stats['checked']).' files processed.'.PHP_EOL);
        }

        // Show progress at meaningful intervals to reduce tmux pane clutter
        if ($show === true) {
            $percent = $this->_totalReleases > 0
                ? round(($stats['checked'] / $this->_totalReleases) * 100, 1)
                : 0;

            // Calculate progress interval - show update every 10% or at completion
            $progressInterval = max(1, (int) ($this->_totalReleases / 10));
            $isLastItem = $stats['checked'] === $this->_totalReleases;
            $isIntervalPoint = $stats['checked'] % $progressInterval === 0;

            // Only output at intervals or completion to keep tmux pane clean
            if ($isIntervalPoint || $isLastItem) {
                cli()->info(
                    'Renamed: '.number_format($stats['fixed']).
                    ' | Processed: '.number_format($stats['checked']).
                    '/'.number_format($this->_totalReleases).
                    ' ('.$percent.'%)'
                );
            }
        }
    }

    /**
     * Process one GUID-partitioned release batch using all standard name sources.
     *
     * @param  null|callable(object): bool  $par2Processor
     * @return array{checked: int, fixed: int}
     */
    public function processStandardBatch(
        string $leftGuid,
        int $limit,
        bool $show,
        ?callable $par2Processor = null
    ): array {
        $releases = $this->queries->standardCandidateBatch($leftGuid, $limit);
        if ($releases === []) {
            return ['checked' => 0, 'fixed' => 0];
        }

        $releaseIds = $this->releaseIds($releases);
        $nfos = $this->queries->groupByReleaseId($this->queries->nfoRows($releaseIds));
        $files = $this->queries->groupByReleaseId($this->queries->fileRows($releaseIds));
        $media = $this->queries->groupByReleaseId($this->queries->mediaRows($releaseIds));
        $hashes = $this->queries->groupByReleaseId($this->queries->hashRows($releaseIds));
        $uidDonors = $this->queries->uidDonors($this->distinctValues($media, 'uid'));
        $crcDonors = $this->queries->crcDonors($this->distinctValues($files, 'crc32'));
        $hashDonors = $this->queries->hashDonors($this->distinctValues($hashes, 'hash'));
        $fixedBefore = $this->updateService->fixed;

        foreach ($releases as $release) {
            $this->updateService->incrementChecked();
            $this->updateService->reset();
            $releaseId = (int) $release->releases_id;
            $releaseMedia = $media[$releaseId] ?? [];
            $releaseFiles = $files[$releaseId] ?? [];

            if ((int) $release->proc_uid === self::PROC_UID_NONE) {
                $this->updateService->reset();
                foreach ($releaseMedia as $row) {
                    $uid = (string) ($row->uid ?? '');
                    if ($uid !== '' && $this->applyDonorMatch($release, $uidDonors[$uid] ?? [], 10, 'uidCheck: Unique_ID', 'UID, ', true, true, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    foreach ($releaseMedia as $row) {
                        if (empty($row->movie_name)) {
                            continue;
                        }

                        $candidate = clone $release;
                        $candidate->movie_name = $row->movie_name;
                        $candidate->file_name = $row->file_name;
                        if ($this->mediaMovieNameCheck($candidate, true, 'Mediainfo, ', true, $show)) {
                            break;
                        }
                    }
                }

                if (! $this->updateService->matched) {
                    $this->updateService->updateSingleColumn('proc_uid', self::PROC_UID_DONE, $releaseId);
                }
            }

            if ($this->updateService->matched) {
                continue;
            }

            if ((int) $release->proc_crc32 === self::PROC_CRC_NONE) {
                $this->updateService->reset();
                $prioritizedCrcs = [];
                foreach ($releaseFiles as $file) {
                    $crc = (string) ($file->crc32 ?? '');
                    if ($crc === '') {
                        continue;
                    }

                    $priority = $this->filePrioritizer->getCrcPriority((string) $file->filename);
                    $prioritizedCrcs[$priority][$crc] = $crc;
                }
                ksort($prioritizedCrcs);

                foreach ($prioritizedCrcs as $crcs) {
                    foreach ($crcs as $crc) {
                        if ($this->applyDonorMatch($release, $crcDonors[$crc] ?? [], 5, 'crcCheck: CRC32', 'CRC32, ', true, true, $show)) {
                            break 2;
                        }
                    }
                }

                if (! $this->updateService->matched) {
                    $this->updateService->updateSingleColumn('proc_crc32', self::PROC_CRC_DONE, $releaseId);
                }
            }

            if ($this->updateService->matched) {
                continue;
            }

            if ((int) $release->proc_srr === self::PROC_SRR_NONE) {
                $this->updateService->reset();
                foreach ($releaseFiles as $file) {
                    $candidate = clone $release;
                    $candidate->textstring = (string) $file->textstring;
                    if ($this->srrNameCheck($candidate, true, 'SRR, ', true, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    $this->updateService->updateSingleColumn('proc_srr', self::PROC_SRR_DONE, $releaseId);
                }
            }

            if ($this->updateService->matched) {
                continue;
            }

            if ((int) $release->proc_hash16k === self::PROC_HASH16K_NONE) {
                $this->updateService->reset();
                foreach ($hashes[$releaseId] ?? [] as $row) {
                    $hash = (string) ($row->hash ?? '');
                    if ($hash !== '' && $this->applyDonorMatch($release, $hashDonors[$hash] ?? [], 5, 'hashCheck: PAR2 hash_16K', 'PAR2 hash, ', true, true, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    $this->updateService->updateSingleColumn('proc_hash16k', self::PROC_HASH16K_DONE, $releaseId);
                }
            }

            if ($this->updateService->matched) {
                continue;
            }

            if ((int) $release->proc_nfo === self::PROC_NFO_NONE) {
                $this->updateService->reset();
                $nfo = $nfos[$releaseId][0] ?? null;
                if ($nfo !== null) {
                    $release->textstring = (string) ($nfo->textstring ?? '');
                    if (! preg_match('/^=newz\[NZB\]=\w+/', $release->textstring)) {
                        $nfoResult = $this->nfoExtractor->extractFromNfo($release->textstring);
                        if ($nfoResult !== null) {
                            $this->updateService->updateRelease(
                                $release,
                                $nfoResult->newName,
                                'nfoCheck: '.$nfoResult->method,
                                true,
                                'NFO, ',
                                true,
                                $show
                            );
                        }

                        if (! $this->updateService->matched) {
                            $this->checkWithPatternMatchers($release, true, 'NFO, ', true, $show, false);
                        }
                    }
                }

                if (! $this->updateService->matched) {
                    $this->updateService->updateSingleColumn('proc_nfo', self::PROC_NFO_DONE, $releaseId);
                }
            }

            if ($this->updateService->matched) {
                continue;
            }

            if ((int) $release->proc_files === self::PROC_FILES_NONE) {
                $this->processFileCandidates($release, $releaseFiles, true, true, $show, false, false);
            }

            if ($this->updateService->matched) {
                continue;
            }

            if ((int) $release->proc_par2 === self::PROC_PAR2_NONE) {
                $this->updateService->reset();
                $matched = $par2Processor !== null && $par2Processor($release);
                if ($matched) {
                    $this->updateService->fixed++;
                } else {
                    $this->updateService->updateSingleColumn('proc_par2', self::PROC_PAR2_DONE, $releaseId);
                }
            }
        }

        return [
            'checked' => count($releases),
            'fixed' => $this->updateService->fixed - $fixedBefore,
        ];
    }

    /**
     * Match one PreDB title against indexed release candidates.
     */
    public function matchPredbFulltext(object $pre, bool $show = false): int
    {
        $title = (string) ($pre->title ?? '');
        if (strlen($title) < 15 || ! preg_match('/([\w()]+[\s._-]){2,}[\w()]+-\w+/u', $title)) {
            return 0;
        }

        if (! Search::isAvailable()) {
            throw new RuntimeException('The configured search backend is unavailable for PreDB matching.');
        }

        $searchResults = Search::searchReleases(['name' => $title, 'searchname' => $title], 21);
        $candidateIds = [];
        foreach ($searchResults as $key => $value) {
            $candidateId = is_numeric($value) ? (int) $value : (is_numeric($key) ? (int) $key : 0);
            if ($candidateId > 0) {
                $candidateIds[$candidateId] = $candidateId;
            }
        }

        $matches = $this->queries->confirmPredbCandidates(array_values($candidateIds), $title);
        if (count($matches) >= 16) {
            return -1;
        }

        $matching = 0;
        foreach ($matches as $release) {
            if (strcasecmp($title, (string) $release->searchname) === 0) {
                $this->updateService->attachPredbId((int) $release->releases_id, (int) $pre->predb_id);
            } else {
                $this->updateService->reset();
                $this->updateService->updateRelease(
                    $release,
                    $title,
                    'Title Match source: '.(string) ($pre->source ?? ''),
                    true,
                    'PreDB FT Exact, ',
                    true,
                    $show,
                    (int) $pre->predb_id
                );
            }

            $matching++;
        }

        return $matching;
    }

    /**
     * Get the update service.
     */
    public function getUpdateService(): ReleaseUpdateService
    {
        return $this->updateService;
    }

    /**
     * Get the checker service.
     */
    public function getCheckerService(): NameCheckerService
    {
        return $this->checkerService;
    }

    /**
     * Fix names using PAR2 files (requires NNTP connection).
     */
    public function fixNamesWithPar2(int $time, bool $echo, int $cats, bool $nameStatus, bool $show, NNTPService $nntp): void
    {
        $this->echoStartMessage($time, 'par2 files');

        if (! $this->startBatch(NameFixingQueryService::SOURCE_PAR2, $time, $cats, ' releases to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        $nzbContentsService = app(NzbContentsService::class);
        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_PAR2, $time, $cats) as $releases) {
            foreach ($releases as $release) {
                if ($nzbContentsService->checkPar2(
                    $release->guid,
                    $release->releases_id,
                    $release->groups_id,
                    (int) ($echo && $nameStatus),
                    (int) $show
                )) {
                    $this->updateService->fixed++;
                } else {
                    $this->markProcessed($echo, $nameStatus, 'proc_par2', (int) $release->releases_id);
                }

                $this->updateService->incrementChecked();
                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' files');
    }

    /**
     * Fix XXX release names using specific file names.
     */
    public function fixXXXNamesWithFiles(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'file names');
        $type = 'Filenames, ';

        if (! $this->startBatch(NameFixingQueryService::SOURCE_XXX, $time, $cats, ' xxx file names to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_XXX, $time, $cats) as $releases) {
            $files = $this->queries->groupByReleaseId(
                $this->queries->fileRows($this->releaseIds($releases), NameFixingQueryService::SOURCE_XXX)
            );

            foreach ($releases as $release) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();

                foreach ($files[(int) $release->releases_id] ?? [] as $file) {
                    $candidate = clone $release;
                    $candidate->textstring = (string) $file->textstring;
                    if ($this->xxxNameCheck($candidate, $echo, $type, $nameStatus, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    $this->markProcessed($echo, $nameStatus, 'proc_files', (int) $release->releases_id);
                }

                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' files');
    }

    /**
     * Fix release names using mediainfo movie_name.
     */
    public function fixNamesWithMediaMovieName(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $type = 'Mediainfo, ';
        $this->echoStartMessage($time, 'Mediainfo movie_name');

        if (! $this->startBatch(NameFixingQueryService::SOURCE_MEDIA_MOVIE, $time, $cats, ' mediainfo movie names to process.')) {
            cli()->info('Nothing to fix.');

            return;
        }

        foreach ($this->candidateBatches(NameFixingQueryService::SOURCE_MEDIA_MOVIE, $time, $cats) as $releases) {
            $media = $this->queries->groupByReleaseId($this->queries->mediaRows($this->releaseIds($releases)));

            foreach ($releases as $release) {
                $this->updateService->incrementChecked();
                $this->updateService->reset();

                foreach ($media[(int) $release->releases_id] ?? [] as $row) {
                    if (empty($row->movie_name)) {
                        continue;
                    }

                    $candidate = clone $release;
                    $candidate->movie_name = $row->movie_name;
                    $candidate->file_name = $row->file_name;
                    if ($this->mediaMovieNameCheck($candidate, $echo, $type, $nameStatus, $show)) {
                        break;
                    }
                }

                if (! $this->updateService->matched) {
                    $this->markProcessed($echo, $nameStatus, 'proc_uid', (int) $release->releases_id);
                }

                $this->echoRenamed($show);
            }
        }

        $this->echoFoundCount($echo, ' MediaInfo\'s');
    }

    /**
     * Check for XXX release name.
     */
    protected function xxxNameCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        if (preg_match('/^.+?SDPORN/i', $release->textstring, $hit)) {
            $this->updateService->updateRelease($release, $hit[0], 'fileCheck: XXX SDPORN', $echo, $type, $nameStatus, $show);

            return $this->updateService->matched;
        }

        return false;
    }

    /**
     * Check mediainfo movie_name for release name.
     */
    protected function mediaMovieNameCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        $newName = '';

        if (! empty($release->movie_name)) {
            if (preg_match(ReleaseUpdateService::PREDB_REGEX, $release->movie_name, $hit)) {
                $newName = $hit[1];
            } elseif (preg_match('/(.+),(\sRMZ\.cr)?$/i', $release->movie_name, $hit)) {
                $newName = $hit[1];
            } else {
                $newName = $release->movie_name;
            }
        }

        if ($newName !== '') {
            $this->updateService->updateRelease($release, $newName, 'MediaInfo: Movie Name', $echo, $type, $nameStatus, $show, $release->predb_id ?? 0);

            return $this->updateService->matched;
        }

        return false;
    }

    /**
     * Check the array using regex for a clean name.
     *
     * @throws \Exception
     */
    public function checkName(object $release, bool $echo, string $type, bool $nameStatus, bool $show, bool $preId = false): bool
    {
        // Check PreDB first
        $preDbMatch = $this->updateService->checkPreDbMatch($release, $release->textstring);
        if ($preDbMatch !== null) {
            if (strcasecmp((string) $preDbMatch['title'], (string) $release->searchname) === 0) {
                if ($echo) {
                    $this->updateService->attachPredbId((int) $release->releases_id, (int) $preDbMatch['id']);
                }
            } else {
                $this->updateService->updateRelease($release, $preDbMatch['title'], 'preDB: Match', $echo, $type, $nameStatus, $show, $preDbMatch['id']);
            }

            return $this->updateService->matched;
        }

        if ($preId) {
            return $this->updateService->matched;
        }

        // Route to appropriate checker based on type
        switch ($type) {
            case 'PAR2, ':
                $result = $this->fileExtractor->extractFromFile($release->textstring);
                if ($result !== null) {
                    $this->updateService->updateRelease($release, $result->newName, 'fileCheck: '.$result->method, $echo, $type, $nameStatus, $show);
                }
                break;

            case 'NFO, ':
                $result = $this->nfoExtractor->extractFromNfo($release->textstring);
                if ($result !== null) {
                    $this->updateService->updateRelease($release, $result->newName, 'nfoCheck: '.$result->method, $echo, $type, $nameStatus, $show);
                }
                break;

            case 'Filenames, ':
                // Try direct file name extraction first (handles NZBSPLIT wrappers)
                if (! $this->updateService->matched) {
                    $result = $this->fileExtractor->extractFromFile($release->textstring);
                    if ($result !== null) {
                        $this->updateService->updateRelease($release, $result->newName, 'fileCheck: '.$result->method, $echo, $type, $nameStatus, $show);
                    }
                }
                // Try PreDB file check
                if (! $this->updateService->matched) {
                    $this->preDbFileCheck($release, $echo, $type, $nameStatus, $show);
                }
                break;

            default:
                // Use pattern checker service
                $result = $this->checkerService->check($release, $release->textstring);
                if ($result !== null) {
                    $this->updateService->updateRelease($release, $result->newName, $result->getFormattedMethod(), $echo, $type, $nameStatus, $show);
                }
        }

        // Update processing flags if not matched
        if ($echo && $nameStatus && ! $this->updateService->matched) {
            $this->updateProcessingFlags($type, $release->releases_id);
        }

        return $this->updateService->matched;
    }

    /**
     * Update processing flags based on type.
     */
    protected function updateProcessingFlags(string $type, int $releaseId): void
    {
        switch ($type) {
            case 'NFO, ':
                $this->updateService->updateSingleColumn('proc_nfo', self::PROC_NFO_DONE, $releaseId);
                break;
            case 'Filenames, ':
                $this->updateService->updateSingleColumn('proc_files', self::PROC_FILES_DONE, $releaseId);
                break;
            case 'PAR2, ':
                $this->updateService->updateSingleColumn('proc_par2', self::PROC_PAR2_DONE, $releaseId);
                break;
            case 'PAR2 hash, ':
                $this->updateService->updateSingleColumn('proc_hash16k', self::PROC_HASH16K_DONE, $releaseId);
                break;
            case 'SRR, ':
                $this->updateService->updateSingleColumn('proc_srr', self::PROC_SRR_DONE, $releaseId);
                break;
            case 'UID, ':
            case 'Mediainfo, ':
                $this->updateService->updateSingleColumn('proc_uid', self::PROC_UID_DONE, $releaseId);
                break;
            case 'CRC32, ':
                $this->updateService->updateSingleColumn('proc_crc32', self::PROC_CRC_DONE, $releaseId);
                break;
        }
    }

    /**
     * Match a release filename to a PreDB filename or title.
     *
     * @throws \Exception
     */
    public function matchPreDbFiles(object $release, bool $echo, bool $nameStatus, bool $show): int
    {
        $matching = 0;

        $files = explode('||', $release->filename ?? '');
        $prioritizedFiles = $this->filePrioritizer->prioritizeForPreDb($files);

        foreach ($prioritizedFiles as $fileName) {
            $cleanedFileName = $this->fileNameCleaner->cleanForMatching($fileName);

            if (empty($cleanedFileName) || strlen($cleanedFileName) < 8) {
                continue;
            }

            $bestMatch = $this->findBestPredbMatch($cleanedFileName);

            if ($bestMatch !== null) {
                if (strcasecmp((string) $bestMatch['title'], (string) $release->searchname) !== 0) {
                    $this->updateService->updateRelease($release, $bestMatch['title'], 'file matched source: '.($bestMatch['source'] ?? ''), $echo, 'PreDB file match, ', $nameStatus, $show);
                } elseif ($echo) {
                    $this->updateService->attachPredbId((int) $release->releases_id, (int) ($bestMatch['id'] ?? 0));
                }
                $matching++;

                return $matching;
            }
        }

        return $matching;
    }

    /**
     * Check if a release name looks like a season pack.
     * Season packs have S01/S02 etc. without an episode (E01) suffix.
     * Uses atomic group so "S02E07" matches "S02" then fails the (?!E\d+) lookahead
     * instead of backtracking to "S0" and incorrectly matching.
     */
    public function isSeasonPack(string $name): bool
    {
        return (bool) preg_match('/S(?>\d{1,2})(?!E\d+)/i', $name);
    }

    /**
     * Reset the update service state.
     */
    public function reset(): void
    {
        $this->updateService->reset();
    }

    /**
     * Retrieves releases and their file names to attempt PreDB matches.
     *
     * @param  list<mixed>  $args
     *
     * @throws \Exception
     */
    public function getPreFileNames(array $args = []): void
    {
        $show = isset($args[2]) && $args[2] === 'show';
        $limited = isset($args[1]) && is_numeric($args[1]);
        $requestedLimit = $limited ? max(1, (int) $args[1]) : PHP_INT_MAX;

        cli()->info(PHP_EOL.'Match PreFiles '.($args[1] ?? 'all').' Started at '.now());
        cli()->info('Matching predb filename to cleaned release_files.name.');

        $counter = $counted = 0;
        $timeStart = now();
        $available = $this->queries->countPrefileCandidates();
        $total = min($available, $requestedLimit);
        if ($total === 0) {
            cli()->info('Nothing to do.');

            return;
        }

        cli()->info(PHP_EOL.number_format($total).' releases to process.');
        $cursor = $limited ? PHP_INT_MAX : 0;

        while ($counter < $total) {
            $batchLimit = min(NameFixingQueryService::BATCH_SIZE, $total - $counter);
            $batch = $this->queries->prefileCandidateBatch($cursor, $batchLimit, $limited);
            if ($batch === []) {
                break;
            }

            $files = $this->queries->groupByReleaseId($this->queries->fileRows($this->releaseIds($batch)));
            foreach ($batch as $row) {
                $releaseFiles = $files[(int) $row->releases_id] ?? [];
                usort($releaseFiles, static fn (object $left, object $right): int => strlen((string) $right->textstring) <=> strlen((string) $left->textstring));
                $row->filename = implode('||', array_map(
                    static fn (object $file): string => (string) $file->textstring,
                    $releaseFiles
                ));

                if ($this->matchPreDbFiles($row, true, true, $show) === 1) {
                    $counted++;
                }
                $counter++;

                if (! $show) {
                    cli()->info('Renamed Releases: ['.number_format($counted).'] '.cli()->percentString($counter, $total));
                }
            }

            $cursor = (int) $batch[array_key_last($batch)]->releases_id;
        }

        cli()->info(PHP_EOL.'Renamed '.number_format($counted).' releases in '.now()->diffInSeconds($timeStart, true).' seconds.');
    }
}
