<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\NameFixing\Extractors\FileNameExtractor;
use App\Services\NameFixing\Extractors\NfoNameExtractor;
use App\Services\NNTP\NNTPService;

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

    protected bool $echoOutput;

    protected string $othercats;

    protected string $timeother;

    protected string $timeall;

    protected string $fullother;

    protected string $fullall;

    protected int $_totalReleases = 0;

    public function __construct(
        ?ReleaseUpdateService $updateService = null,
        ?NameCheckerService $checkerService = null,
        ?NfoNameExtractor $nfoExtractor = null,
        ?FileNameExtractor $fileExtractor = null,
        ?FileNameCleaner $fileNameCleaner = null,
        ?FilePrioritizer $filePrioritizer = null
    ) {
        $this->updateService = $updateService ?? new ReleaseUpdateService;
        $this->checkerService = $checkerService ?? new NameCheckerService;
        $this->nfoExtractor = $nfoExtractor ?? new NfoNameExtractor;
        $this->fileExtractor = $fileExtractor ?? new FileNameExtractor;
        $this->fileNameCleaner = $fileNameCleaner ?? new FileNameCleaner;
        $this->filePrioritizer = $filePrioritizer ?? new FilePrioritizer;
        $this->echoOutput = config('nntmux.echocli');

        $this->othercats = implode(',', Category::OTHERS_GROUP);
        $this->timeother = sprintf(' AND rel.adddate > (NOW() - INTERVAL 6 HOUR) AND rel.categories_id IN (%s) GROUP BY rel.id ORDER BY postdate DESC', $this->othercats);
        $this->timeall = ' AND rel.adddate > (NOW() - INTERVAL 6 HOUR) GROUP BY rel.id ORDER BY postdate DESC';
        $this->fullother = sprintf(' AND rel.categories_id IN (%s) GROUP BY rel.id', $this->othercats);
        $this->fullall = '';
    }

    /**
     * Fix names using NFO content.
     */
    public function fixNamesWithNfo(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, '.nfo files');
        $type = 'NFO, ';

        $preId = false;
        if ($cats === 3) {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.fromname
                FROM releases rel
                INNER JOIN release_nfos nfo ON (nfo.releases_id = rel.id)
                WHERE rel.predb_id = 0'
            );
            $cats = 2;
            $preId = true;
        } else {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.fromname
                FROM releases rel
                INNER JOIN release_nfos nfo ON (nfo.releases_id = rel.id)
                WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND rel.proc_nfo = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_NFO_NONE
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases->count();

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' releases to process.');

            foreach ($releases as $rel) {
                $releaseRow = Release::fromQuery(
                    sprintf(
                        'SELECT nfo.releases_id AS nfoid, rel.groups_id, rel.fromname, rel.categories_id, rel.name, rel.searchname,
                            UNCOMPRESS(nfo) AS textstring, rel.id AS releases_id
                        FROM releases rel
                        INNER JOIN release_nfos nfo ON (nfo.releases_id = rel.id)
                        WHERE rel.id = %d LIMIT 1',
                        $rel->releases_id
                    )
                );

                $this->updateService->incrementChecked();

                // Ignore encrypted NFOs
                if (preg_match('/^=newz\[NZB\]=\w+/', $releaseRow[0]->textstring)) {
                    $this->updateService->updateSingleColumn('proc_nfo', self::PROC_NFO_DONE, $rel->releases_id);

                    continue;
                }

                $this->updateService->reset();

                // Try NFO extraction
                $nfoResult = $this->nfoExtractor->extractFromNfo($releaseRow[0]->textstring);
                if ($nfoResult !== null) {
                    $this->updateService->updateRelease(
                        $releaseRow[0],
                        $nfoResult->newName,
                        'nfoCheck: '.$nfoResult->method,
                        $echo,
                        $type,
                        $nameStatus,
                        $show
                    );
                }

                // If NFO extraction didn't work, try pattern checkers
                if (! $this->updateService->matched) {
                    $this->checkWithPatternMatchers($releaseRow[0], $echo, $type, $nameStatus, $show, $preId);
                }

                $this->echoRenamed($show);
            }
            $this->echoFoundCount($echo, ' NFO\'s');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Fix names using file names.
     */
    public function fixNamesWithFiles(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'file names');
        $type = 'Filenames, ';

        $preId = false;
        if ($cats === 3) {
            $query = sprintf(
                'SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON rf.releases_id = rel.id
                WHERE predb_id = 0'
            );
            $cats = 2;
            $preId = true;
        } else {
            $query = sprintf(
                'SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON rf.releases_id = rel.id
                WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND proc_files = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_FILES_NONE
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases->count();

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' file names to process.');

            // Group files by release
            $releaseFiles = [];
            foreach ($releases as $release) {
                $releaseId = $release->releases_id;
                if (! isset($releaseFiles[$releaseId])) {
                    $releaseFiles[$releaseId] = [
                        'release' => $release,
                        'files' => [],
                    ];
                }
                $releaseFiles[$releaseId]['files'][] = $release->textstring;
            }

            foreach ($releaseFiles as $releaseId => $data) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();

                // Prioritize files for matching
                $prioritizedFiles = $this->filePrioritizer->prioritizeForMatching($data['files']);

                foreach ($prioritizedFiles as $filename) {
                    $release = clone $data['release'];
                    $release->textstring = $filename;

                    // Try file name extraction
                    $fileResult = $this->fileExtractor->extractFromFile($filename);
                    if ($fileResult !== null) {
                        $this->updateService->updateRelease(
                            $release,
                            $fileResult->newName,
                            'fileCheck: '.$fileResult->method,
                            $echo,
                            $type,
                            $nameStatus,
                            $show
                        );
                    }

                    // If not matched, try PreDB search
                    if (! $this->updateService->matched) {
                        $this->preDbFileCheck($release, $echo, $type, $nameStatus, $show);
                    }

                    if (! $this->updateService->matched) {
                        $this->preDbTitleCheck($release, $echo, $type, $nameStatus, $show);
                    }

                    if ($this->updateService->matched) {
                        break;
                    }
                }

                $this->echoRenamed($show);
            }

            $this->echoFoundCount($echo, ' files');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Fix names using SRR files.
     */
    public function fixNamesWithSrr(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'SRR file names');
        $type = 'SRR, ';

        if ($cats === 3) {
            $query = sprintf(
                'SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON (rf.releases_id = rel.id)
                WHERE predb_id = 0
                AND (rf.name LIKE %s OR rf.name LIKE %s)',
                escapeString('%.srr'),
                escapeString('%.srs')
            );
            $cats = 2;
        } else {
            $query = sprintf(
                'SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON (rf.releases_id = rel.id)
                WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND (rf.name LIKE %s OR rf.name LIKE %s)
                AND rel.proc_srr = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                escapeString('%.srr'),
                escapeString('%.srs'),
                self::PROC_SRR_NONE
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases->count();

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' srr file extensions to process.');

            foreach ($releases as $release) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();

                $this->srrNameCheck($release, $echo, $type, $nameStatus, $show);
                $this->echoRenamed($show);
            }

            $this->echoFoundCount($echo, ' files');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Fix names using CRC32 hashes.
     */
    public function fixNamesWithCrc(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'CRC32');
        $type = 'CRC32, ';

        $preId = false;
        if ($cats === 3) {
            $query = sprintf(
                'SELECT rf.crc32 AS textstring, rf.name AS filename, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id, rel.size as relsize,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON rf.releases_id = rel.id
                WHERE predb_id = 0
                AND rf.crc32 != \'\'
                AND rf.crc32 IS NOT NULL'
            );
            $cats = 2;
            $preId = true;
        } else {
            $query = sprintf(
                'SELECT rf.crc32 AS textstring, rf.name AS filename, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id, rel.size as relsize,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON rf.releases_id = rel.id
                WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND rel.proc_crc32 = %d
                AND rf.crc32 != \'\'
                AND rf.crc32 IS NOT NULL',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_CRC_NONE
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases->count();

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' CRC32\'s to process.');

            // Group by release
            $releasesCrc = [];
            foreach ($releases as $release) {
                $releaseId = $release->releases_id;
                if (! isset($releasesCrc[$releaseId])) {
                    $releasesCrc[$releaseId] = [
                        'release' => $release,
                        'crcs' => [],
                    ];
                }
                if (! empty($release->textstring)) {
                    $priority = $this->filePrioritizer->getCrcPriority($release->filename ?? '');
                    $releasesCrc[$releaseId]['crcs'][$priority][] = $release->textstring;
                }
            }

            foreach ($releasesCrc as $releaseId => $data) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();

                ksort($data['crcs']);
                foreach ($data['crcs'] as $crcs) {
                    foreach ($crcs as $crc) {
                        $release = clone $data['release'];
                        $release->textstring = $crc;

                        $this->crcCheck($release, $echo, $type, $nameStatus, $show, $preId);

                        if ($this->updateService->matched) {
                            break 2;
                        }
                    }
                }

                $this->echoRenamed($show);
            }

            $this->echoFoundCount($echo, ' crc32\'s');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Fix names using Media info unique IDs.
     */
    public function fixNamesWithMedia(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $type = 'UID, ';
        $this->echoStartMessage($time, 'mediainfo Unique_IDs');

        if ($cats === 3) {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
                    rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
                    ru.unique_id AS uid
                FROM releases rel
                LEFT JOIN media_infos ru ON ru.releases_id = rel.id
                WHERE ru.releases_id IS NOT NULL
                AND rel.predb_id = 0'
            );
            $cats = 2;
        } else {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
                    rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
                    ru.unique_id AS uid
                FROM releases rel
                LEFT JOIN media_infos ru ON ru.releases_id = rel.id
                WHERE ru.releases_id IS NOT NULL
                AND (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND rel.proc_uid = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_UID_NONE
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases->count();

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' unique ids to process.');

            foreach ($releases as $rel) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();
                $this->uidCheck($rel, $echo, $type, $nameStatus, $show);
                $this->echoRenamed($show);
            }

            $this->echoFoundCount($echo, ' UID\'s');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Fix names using PAR2 hash_16K.
     */
    public function fixNamesWithParHash(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $type = 'PAR2 hash, ';
        $this->echoStartMessage($time, 'PAR2 hash_16K');

        if ($cats === 3) {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
                    rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
                    IFNULL(ph.hash, \'\') AS hash
                FROM releases rel
                LEFT JOIN par_hashes ph ON ph.releases_id = rel.id
                WHERE ph.hash != \'\'
                AND rel.predb_id = 0'
            );
            $cats = 2;
        } else {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
                    rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
                    IFNULL(ph.hash, \'\') AS hash
                FROM releases rel
                LEFT JOIN par_hashes ph ON ph.releases_id = rel.id
                WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND ph.hash != \'\'
                AND rel.proc_hash16k = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_HASH16K_NONE
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases->count();

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' hash_16K to process.');

            foreach ($releases as $rel) {
                $this->updateService->reset();
                $this->updateService->incrementChecked();
                $this->hashCheck($rel, $echo, $type, $nameStatus, $show);
                $this->echoRenamed($show);
            }

            $this->echoFoundCount($echo, ' hashes');
        } else {
            cli()->info('Nothing to fix.');
        }
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

                return true;
            }
        }

        $this->updateService->updateSingleColumn('proc_srr', self::PROC_SRR_DONE, $release->releases_id);

        return false;
    }

    /**
     * Check CRC32 for matches.
     */
    protected function crcCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show, bool $preId): bool
    {
        if ($release->textstring === '') {
            $this->updateService->updateSingleColumn('proc_crc32', self::PROC_CRC_DONE, $release->releases_id);

            return false;
        }

        $result = Release::fromQuery(
            sprintf(
                'SELECT rf.crc32, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id, rel.size as relsize, rel.predb_id as predb_id,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                LEFT JOIN release_files rf ON rf.releases_id = rel.id
                WHERE rel.predb_id > 0
                AND rf.crc32 = %s',
                escapeString($release->textstring)
            )
        );

        foreach ($result as $res) {
            $floor = round(($res->relsize - $release->relsize) / $res->relsize * 100, 1);
            if ($floor >= -5 && $floor <= 5) {
                $this->updateService->updateRelease(
                    $release,
                    $res->searchname,
                    'crcCheck: CRC32',
                    $echo,
                    $type,
                    $nameStatus,
                    $show,
                    $res->predb_id
                );

                return true;
            }
        }

        $this->updateService->updateSingleColumn('proc_crc32', self::PROC_CRC_DONE, $release->releases_id);

        return false;
    }

    /**
     * Check UID for matches.
     */
    protected function uidCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        if (empty($release->uid)) {
            $this->updateService->updateSingleColumn('proc_uid', self::PROC_UID_DONE, $release->releases_id);

            return false;
        }

        $result = Release::fromQuery(sprintf(
            'SELECT r.id AS releases_id, r.size AS relsize, r.name AS textstring, r.searchname, r.fromname, r.predb_id
            FROM releases r
            LEFT JOIN media_infos ru ON ru.releases_id = r.id
            WHERE ru.releases_id IS NOT NULL
            AND ru.unique_id = %s
            AND ru.releases_id != %d
            AND (r.predb_id > 0 OR r.anidbid > 0 OR r.fromname = %s)',
            escapeString($release->uid),
            $release->releases_id,
            escapeString('nonscene@Ef.net (EF)')
        ));

        foreach ($result as $res) {
            $floor = round(($res->relsize - $release->relsize) / $res->relsize * 100, 1);
            if ($floor >= -10 && $floor <= 10) {
                $this->updateService->updateRelease(
                    $release,
                    $res->searchname,
                    'uidCheck: Unique_ID',
                    $echo,
                    $type,
                    $nameStatus,
                    $show,
                    $res->predb_id
                );

                return true;
            }
        }

        $this->updateService->updateSingleColumn('proc_uid', self::PROC_UID_DONE, $release->releases_id);

        return false;
    }

    /**
     * Check PAR2 hash for matches.
     */
    protected function hashCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        $result = Release::fromQuery(sprintf(
            'SELECT r.id AS releases_id, r.size AS relsize, r.name AS textstring, r.searchname, r.fromname, r.predb_id
            FROM releases r
            LEFT JOIN par_hashes ph ON ph.releases_id = r.id
            WHERE ph.hash = %s
            AND ph.releases_id != %d
            AND (r.predb_id > 0 OR r.anidbid > 0)',
            escapeString($release->hash),
            $release->releases_id
        ));

        foreach ($result as $res) {
            $floor = round(($res->relsize - $release->relsize) / $res->relsize * 100, 1);
            if ($floor >= -5 && $floor <= 5) {
                $this->updateService->updateRelease(
                    $release,
                    $res->searchname,
                    'hashCheck: PAR2 hash_16K',
                    $echo,
                    $type,
                    $nameStatus,
                    $show,
                    $res->predb_id
                );

                return true;
            }
        }

        $this->updateService->updateSingleColumn('proc_hash16k', self::PROC_HASH16K_DONE, $release->releases_id);

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

        $results = Search::searchPredb($fileName);
        foreach ($results as $hit) {
            if (! empty($hit)) {
                $hitData = is_array($hit) ? $hit : (array) $hit;
                $this->updateService->updateRelease(
                    $release,
                    $hitData['title'] ?? '',
                    'PreDb: Filename match',
                    $echo,
                    $type,
                    $nameStatus,
                    $show,
                    $hitData['id'] ?? null
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Check PreDB for title matches.
     */
    protected function preDbTitleCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        $fileName = $this->fileNameCleaner->cleanForMatching($release->textstring);

        if (empty($fileName)) {
            return false;
        }

        $results = Search::searchPredb($fileName);
        foreach ($results as $hit) {
            if (! empty($hit)) {
                $hitData = is_array($hit) ? $hit : (array) $hit;
                $this->updateService->updateRelease(
                    $release,
                    $hitData['title'] ?? '',
                    'PreDb: Title match',
                    $echo,
                    $type,
                    $nameStatus,
                    $show,
                    $hitData['id'] ?? null
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Get releases based on time and category parameters.
     */
    protected function getReleases(int $time, int $cats, string $query, int $limit = 0): \Illuminate\Database\Eloquent\Collection|bool
    {
        $releases = false;
        $queryLimit = ($limit === 0) ? '' : ' LIMIT '.$limit;

        if ($time === 1 && $cats === 1) {
            $releases = Release::fromQuery($query.$this->timeother.$queryLimit);
        }
        if ($time === 1 && $cats === 2) {
            $releases = Release::fromQuery($query.$this->timeall.$queryLimit);
        }
        if ($time === 2 && $cats === 1) {
            $releases = Release::fromQuery($query.$this->fullother.$queryLimit);
        }
        if ($time === 2 && $cats === 2) {
            $releases = Release::fromQuery($query.$this->fullall.$queryLimit);
        }

        return $releases;
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

        // Show active counter on the same line (overwrites previous)
        if ($show === true) {
            $percent = $this->_totalReleases > 0
                ? round(($stats['checked'] / $this->_totalReleases) * 100, 1)
                : 0;

            // Use carriage return to overwrite the same line
            echo "\rRenamed: ".number_format($stats['fixed']).
                 ' | Processed: '.number_format($stats['checked']).
                 '/'.number_format($this->_totalReleases).
                 ' ('.$percent.'%)    ';
        }
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

        if ($cats === 3) {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.guid, rel.groups_id, rel.fromname
                FROM releases rel
                WHERE rel.predb_id = 0'
            );
            $cats = 2;
        } else {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.guid, rel.groups_id, rel.fromname
                FROM releases rel
                WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND rel.proc_par2 = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_PAR2_NONE
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases ? $releases->count() : 0;

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' releases to process.');
            $nzbContentsService = app(\App\Services\Nzb\NzbContentsService::class);

            foreach ($releases as $release) {
                if ($nzbContentsService->checkPar2($release->guid, $release->releases_id, $release->groups_id, (int) $nameStatus, (int) $show)) {
                    $this->updateService->fixed++;
                }

                $this->updateService->incrementChecked();
                $this->echoRenamed($show);
            }
            $this->echoFoundCount($echo, ' files');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Fix XXX release names using specific file names.
     */
    public function fixXXXNamesWithFiles(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $this->echoStartMessage($time, 'file names');
        $type = 'Filenames, ';

        if ($cats === 3) {
            $query = sprintf(
                'SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON rf.releases_id = rel.id
                WHERE predb_id = 0'
            );
            $cats = 2;
        } else {
            $query = sprintf(
                'SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
                    rf.releases_id AS fileid, rel.id AS releases_id
                FROM releases rel
                INNER JOIN release_files rf ON rf.releases_id = rel.id
                WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
                AND rel.predb_id = 0
                AND rf.name LIKE %s',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                escapeString('%SDPORN%')
            );
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases ? $releases->count() : 0;

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' xxx file names to process.');

            foreach ($releases as $release) {
                $this->updateService->reset();
                $this->xxxNameCheck($release, $echo, $type, $nameStatus, $show);
                $this->updateService->incrementChecked();
                $this->echoRenamed($show);
            }
            $this->echoFoundCount($echo, ' files');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Fix release names using mediainfo movie_name.
     */
    public function fixNamesWithMediaMovieName(int $time, bool $echo, int $cats, bool $nameStatus, bool $show): void
    {
        $type = 'Mediainfo, ';
        $this->echoStartMessage($time, 'Mediainfo movie_name');

        if ($cats === 3) {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.name, rel.name AS textstring, rel.predb_id, rel.searchname, rel.fromname, rel.groups_id, rel.categories_id, rel.id AS releases_id, rf.movie_name as movie_name
                FROM releases rel
                INNER JOIN media_infos rf ON rf.releases_id = rel.id
                WHERE rel.predb_id = 0'
            );
            $cats = 2;
        } else {
            $query = sprintf(
                'SELECT rel.id AS releases_id, rel.name, rel.name AS textstring, rel.predb_id, rel.searchname, rel.fromname, rel.groups_id, rel.categories_id, rel.id AS releases_id, rf.movie_name as movie_name, rf.file_name as file_name
                FROM releases rel
                INNER JOIN media_infos rf ON rf.releases_id = rel.id
                WHERE rel.isrenamed = %d
                AND rel.predb_id = 0',
                self::IS_RENAMED_NONE
            );
            if ($cats === 2) {
                $query .= PHP_EOL.'AND rel.categories_id IN ('.Category::OTHER_MISC.','.Category::OTHER_HASHED.')';
            }
        }

        $releases = $this->getReleases($time, $cats, $query);
        $total = $releases ? $releases->count() : 0;

        if ($total > 0) {
            $this->_totalReleases = $total;
            cli()->info(number_format($total).' mediainfo movie names to process.');

            foreach ($releases as $rel) {
                $this->updateService->incrementChecked();
                $this->updateService->reset();
                $this->mediaMovieNameCheck($rel, $echo, $type, $nameStatus, $show);
                $this->echoRenamed($show);
            }
            $this->echoFoundCount($echo, ' MediaInfo\'s');
        } else {
            cli()->info('Nothing to fix.');
        }
    }

    /**
     * Check for XXX release name.
     */
    protected function xxxNameCheck(object $release, bool $echo, string $type, bool $nameStatus, bool $show): bool
    {
        if (preg_match('/^.+?SDPORN/i', $release->textstring, $hit)) {
            $this->updateService->updateRelease($release, $hit[0], 'fileCheck: XXX SDPORN', $echo, $type, $nameStatus, $show);

            return true;
        }

        $this->updateService->updateSingleColumn('proc_files', self::PROC_FILES_DONE, $release->releases_id);

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

            return true;
        }

        $this->updateService->updateSingleColumn('proc_uid', self::PROC_UID_DONE, $release->releases_id);

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
            $this->updateService->updateRelease($release, $preDbMatch['title'], 'preDB: Match', $echo, $type, $nameStatus, $show, $preDbMatch['id']);

            return true;
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
                // Try PreDB file check
                if (! $this->updateService->matched) {
                    $this->preDbFileCheck($release, $echo, $type, $nameStatus, $show);
                }
                // Try PreDB title check
                if (! $this->updateService->matched) {
                    $this->preDbTitleCheck($release, $echo, $type, $nameStatus, $show);
                }
                // Try file name extraction
                if (! $this->updateService->matched) {
                    $result = $this->fileExtractor->extractFromFile($release->textstring);
                    if ($result !== null) {
                        $this->updateService->updateRelease($release, $result->newName, 'fileCheck: '.$result->method, $echo, $type, $nameStatus, $show);
                    }
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
        if ($nameStatus === true && ! $this->updateService->matched) {
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

            $preMatch = $this->preMatch($cleanedFileName);
            if ($preMatch[0] === true) {
                $results = Search::searchPredb($preMatch[1]);

                if (! empty($results)) {
                    foreach ($results as $result) {
                        if (! empty($result)) {
                            $resultData = is_array($result) ? $result : (array) $result;
                            $preFtMatch = $this->preMatch($resultData['filename'] ?? '');
                            if ($preFtMatch[0] === true) {
                                if ($resultData['title'] !== $release->searchname) {
                                    $this->updateService->updateRelease($release, $resultData['title'], 'file matched source: '.($resultData['source'] ?? ''), $echo, 'PreDB file match, ', $nameStatus, $show);
                                } else {
                                    $this->updateService->updateSingleColumn('predb_id', $resultData['id'] ?? 0, $release->releases_id);
                                }
                                $matching++;

                                return $matching;
                            }
                        }
                    }
                }
            }
        }

        return $matching;
    }

    /**
     * Pre-match check for filename patterns.
     */
    protected function preMatch(string $fileName): array
    {
        $result = preg_match('/(\d{2}\.\d{2}\.\d{2})+([\w\-.]+[\w]$)/i', $fileName, $hit);

        return [$result === 1, $hit[0] ?? ''];
    }

    /**
     * Check if a release name looks like a season pack.
     */
    public function isSeasonPack(string $name): bool
    {
        // Season pack pattern: S01 without E01
        return (bool) preg_match('/S\d{1,2}(?!E\d)/i', $name);
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
     * @throws \Exception
     */
    public function getPreFileNames(array $args = []): void
    {
        $show = isset($args[2]) && $args[2] === 'show';

        if (isset($args[1]) && is_numeric($args[1])) {
            $limit = 'LIMIT '.$args[1];
            $orderBy = 'ORDER BY r.id DESC';
        } else {
            $orderBy = 'ORDER BY r.id ASC';
            $limit = 'LIMIT 1000000';
        }

        cli()->info(PHP_EOL.'Match PreFiles '.($args[1] ?? 'all').' Started at '.now());
        cli()->info('Matching predb filename to cleaned release_files.name.');

        $counter = $counted = 0;
        $timeStart = now();

        $query = Release::fromQuery(
            sprintf(
                "SELECT r.id AS releases_id, r.name, r.searchname,
                    r.fromname, r.groups_id, r.categories_id,
                    GROUP_CONCAT(rf.name ORDER BY LENGTH(rf.name) DESC SEPARATOR '||') AS filename
                FROM releases r
                INNER JOIN release_files rf ON r.id = rf.releases_id
                WHERE rf.name IS NOT NULL
                AND r.predb_id = 0
                AND r.categories_id IN (%s)
                AND r.isrenamed = 0
                GROUP BY r.id
                %s %s",
                implode(',', Category::OTHERS_GROUP),
                $orderBy,
                $limit
            )
        );

        if ($query->isNotEmpty()) {
            $total = $query->count();

            if ($total > 0) {
                cli()->info(PHP_EOL.number_format($total).' releases to process.');

                foreach ($query as $row) {
                    $success = $this->matchPreDbFiles($row, true, true, $show);
                    if ($success === 1) {
                        $counted++;
                    }
                    if ($show === false) {
                        cli()->info('Renamed Releases: ['.number_format($counted).'] '.(new ColorCLI)->percentString(++$counter, $total));
                    }
                }
                cli()->info(PHP_EOL.'Renamed '.number_format($counted).' releases in '.now()->diffInSeconds($timeStart, true).' seconds.');
            } else {
                cli()->info('Nothing to do.');
            }
        }
    }
}
