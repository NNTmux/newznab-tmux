<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Predb;
use App\Models\Release;
use Blacklight\NameFixer;
use Blacklight\Nfo;
use Blacklight\NNTP;
use Blacklight\NZBContents;
use Blacklight\processing\PostProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleasesFixNamesGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:fix-names-group
                            {type : Type of fix (standard|predbft)}
                            {--guid-char= : GUID character to process (for standard type)}
                            {--limit=1000 : Maximum releases to process}
                            {--thread=1 : Thread number (for predbft type)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix release names using various methods (group-based processing)';

    private NameFixer $nameFixer;

    private int $checked = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $maxPerRun = (int) $this->option('limit');

        $this->nameFixer = new NameFixer;

        switch ($type) {
            case 'standard':
                return $this->processStandard($maxPerRun);

            case 'predbft':
                return $this->processPredbFulltext($maxPerRun);

            default:
                $this->error("Invalid type: {$type}. Use 'standard' or 'predbft'");

                return Command::FAILURE;
        }
    }

    /**
     * Process standard name fixing
     */
    protected function processStandard(int $maxPerRun): int
    {
        $guidChar = $this->option('guid-char');

        if ($guidChar === null) {
            $this->error('--guid-char is required for standard type');

            return Command::FAILURE;
        }

        $this->info("Processing releases with GUID starting with: {$guidChar}");
        $this->info("Maximum per run: {$maxPerRun}");

        // Allow for larger filename return sets
        DB::statement('SET SESSION group_concat_max_len = 65536');

        // Find releases to process
        $releases = $this->fetchReleases($guidChar, $maxPerRun);

        if ($releases->isEmpty()) {
            $this->info('No releases to process');

            return Command::SUCCESS;
        }

        $this->info("Found {$releases->count()} releases to process");
        $bar = $this->output->createProgressBar($releases->count());
        $bar->start();

        $nntp = null;
        $nzbcontents = null;

        foreach ($releases as $release) {
            $this->checked++;
            $this->nameFixer->reset();

            // Process UID
            if ((int) $release->proc_uid === NameFixer::PROC_UID_NONE &&
                (! empty($release->uid) || ! empty($release->mediainfo))) {

                if (! empty($release->uid)) {
                    $this->nameFixer->checkName($release, true, 'UID, ', 1, true);
                }

                if (empty($this->nameFixer->matched) && ! empty($release->mediainfo)) {
                    $this->nameFixer->checkName($release, true, 'Mediainfo, ', 1, true);
                }
            }

            $this->nameFixer->_updateSingleColumn('proc_uid', NameFixer::PROC_UID_DONE, $release->releases_id);

            if ($this->nameFixer->matched) {
                $bar->advance();

                continue;
            }

            // Process CRC32
            if ((int) $release->proc_crc32 === NameFixer::PROC_CRC_NONE && ! empty($release->crc)) {
                $this->nameFixer->reset();
                $this->nameFixer->checkName($release, true, 'CRC32, ', 1, true);
            }

            $this->nameFixer->_updateSingleColumn('proc_crc32', NameFixer::PROC_CRC_DONE, $release->releases_id);

            if ($this->nameFixer->matched) {
                $bar->advance();

                continue;
            }

            // Process SRR
            if ((int) $release->proc_srr === NameFixer::PROC_SRR_NONE) {
                $this->nameFixer->reset();
                $this->nameFixer->checkName($release, true, 'SRR, ', 1, true);
            }

            $this->nameFixer->_updateSingleColumn('proc_srr', NameFixer::PROC_SRR_DONE, $release->releases_id);

            if ($this->nameFixer->matched) {
                $bar->advance();

                continue;
            }

            // Process PAR2 hash
            if ((int) $release->proc_hash16k === NameFixer::PROC_HASH16K_NONE && ! empty($release->hash)) {
                $this->nameFixer->reset();
                $this->nameFixer->checkName($release, true, 'PAR2 hash, ', 1, true);
            }

            $this->nameFixer->_updateSingleColumn('proc_hash16k', NameFixer::PROC_HASH16K_DONE, $release->releases_id);

            if ($this->nameFixer->matched) {
                $bar->advance();

                continue;
            }

            // Process NFO
            if ((int) $release->nfostatus === Nfo::NFO_FOUND &&
                (int) $release->proc_nfo === NameFixer::PROC_NFO_NONE &&
                ! empty($release->textstring) &&
                ! preg_match('/^=newz\[NZB\]=\w+/', $release->textstring)) {

                $this->nameFixer->reset();
                $this->nameFixer->checkName($release, true, 'NFO, ', 1, true);
            }

            $this->nameFixer->_updateSingleColumn('proc_nfo', NameFixer::PROC_NFO_DONE, $release->releases_id);

            if ($this->nameFixer->matched) {
                $bar->advance();

                continue;
            }

            // Process filenames
            if ((int) $release->fileid > 0 && (int) $release->proc_files === NameFixer::PROC_FILES_NONE) {
                $this->nameFixer->reset();
                $fileNames = explode('|', $release->filestring);

                if (is_array($fileNames)) {
                    $releaseFile = $release;
                    foreach ($fileNames as $fileName) {
                        if ($this->nameFixer->matched === false) {
                            $releaseFile->textstring = $fileName;
                            $this->nameFixer->checkName($releaseFile, true, 'Filenames, ', 1, true);
                        }
                    }
                }
            }

            $this->nameFixer->_updateSingleColumn('proc_files', NameFixer::PROC_FILES_DONE, $release->releases_id);

            if ($this->nameFixer->matched) {
                $bar->advance();

                continue;
            }

            // Process PAR2
            if ((int) $release->proc_par2 === NameFixer::PROC_PAR2_NONE) {
                // Initialize NZB contents if needed
                if (! isset($nzbcontents)) {
                    $nntp = new NNTP;
                    $compressedHeaders = config('nntmux_nntp.compressed_headers');

                    if ((config('nntmux_nntp.use_alternate_nntp_server') === true
                        ? $nntp->doConnect($compressedHeaders, true)
                        : $nntp->doConnect()) !== true) {
                        $this->warn('Unable to connect to usenet for PAR2 processing');
                    } else {
                        $Nfo = new Nfo;
                        $nzbcontents = new NZBContents([
                            'Echo' => false,
                            'NNTP' => $nntp,
                            'Nfo' => $Nfo,
                            'PostProcess' => new PostProcess(['Nfo' => $Nfo, 'NameFixer' => $this->nameFixer]),
                        ]);
                    }
                }

                if (isset($nzbcontents)) {
                    $nzbcontents->checkPAR2($release->guid, $release->releases_id, $release->groups_id, 1, true);
                }
            }

            $this->nameFixer->_updateSingleColumn('proc_par2', NameFixer::PROC_PAR2_DONE, $release->releases_id);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Processed {$this->checked} releases");
        $this->info("✅ Fixed {$this->nameFixer->fixed} release names");

        return Command::SUCCESS;
    }

    /**
     * Process PreDB fulltext matching
     */
    protected function processPredbFulltext(int $maxPerRun): int
    {
        $thread = (int) $this->option('thread');
        $offset = $thread * $maxPerRun - $maxPerRun;

        $this->info('Processing PreDB fulltext matching');
        $this->info("Thread: {$thread}, Limit: {$maxPerRun}, Offset: {$offset}");

        $pres = Predb::fromQuery(
            sprintf(
                '
                SELECT p.id AS predb_id, p.title, p.source, p.searched
                FROM predb p
                WHERE LENGTH(p.title) >= 15 AND p.title NOT REGEXP "[\"\<\> ]"
                AND p.searched = 0
                AND p.predate < (NOW() - INTERVAL 1 DAY)
                ORDER BY p.predate ASC
                LIMIT %s
                OFFSET %s',
                $maxPerRun,
                $offset
            )
        );

        if ($pres->isEmpty()) {
            $this->info('No PreDB entries to process');

            return Command::SUCCESS;
        }

        $this->info("Found {$pres->count()} PreDB entries to process");
        $bar = $this->output->createProgressBar($pres->count());
        $bar->start();

        foreach ($pres as $pre) {
            $this->nameFixer->done = $this->nameFixer->matched = false;
            $searched = 0;

            $ftmatched = $this->nameFixer->matchPredbFT($pre, true, 1, true);

            if ($ftmatched > 0) {
                $searched = 1;
            } elseif ($ftmatched < 0) {
                $searched = -6;
            } else {
                $searched = $pre['searched'] - 1;
            }

            Predb::query()->where('id', $pre['predb_id'])->update(['searched' => $searched]);
            $this->checked++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Processed {$this->checked} PreDB entries");

        return Command::SUCCESS;
    }

    /**
     * Fetch releases for processing
     */
    protected function fetchReleases(string $guidChar, int $maxPerRun)
    {
        return Release::fromQuery(sprintf("
            SELECT
                r.id AS releases_id, r.fromname, r.guid, r.groups_id, r.categories_id, r.name, r.searchname, r.proc_nfo,
                r.proc_uid, r.proc_files, r.proc_par2, r.ishashed, r.dehashstatus, r.nfostatus,
                r.size AS relsize, r.predb_id, r.proc_hash16k, r.proc_srr, r.proc_crc32,
                IFNULL(rf.releases_id, 0) AS fileid, IF(rf.ishashed = 1, rf.name, 0) AS filehash,
                IFNULL(GROUP_CONCAT(rf.name ORDER BY rf.name ASC SEPARATOR '|'), '') AS filestring,
                IFNULL(UNCOMPRESS(rn.nfo), '') AS textstring,
                IFNULL(ru.uniqueid, '') AS uid,
                IFNULL(ph.hash, 0) AS hash,
                IFNULL(rf.crc32, '') AS crc
            FROM releases r
            LEFT JOIN release_nfos rn ON rn.releases_id = r.id
            LEFT JOIN release_files rf ON rf.releases_id = r.id
            LEFT JOIN release_unique ru ON ru.releases_id = r.id
            LEFT JOIN par_hashes ph ON ph.releases_id = r.id
            WHERE r.leftguid = %s
            AND r.isrenamed = %d
            AND r.predb_id = 0
            AND r.passwordstatus >= 0
            AND r.nfostatus > %d
            AND (
                (r.nfostatus = %d AND r.proc_nfo = %d)
                OR r.proc_files = %d
                OR r.proc_uid = %d
                OR r.proc_par2 = %d
                OR r.proc_srr = %d
                OR r.proc_hash16k = %d
                OR r.proc_crc32 = %d
                OR (r.ishashed = 1 AND r.dehashstatus BETWEEN -6 AND 0)
            )
            AND r.categories_id IN (%s)
            GROUP BY r.id
            ORDER BY r.id DESC
            LIMIT %s",
            escapeString($guidChar),
            NameFixer::IS_RENAMED_NONE,
            Nfo::NFO_UNPROC,
            Nfo::NFO_FOUND,
            NameFixer::PROC_NFO_NONE,
            NameFixer::PROC_FILES_NONE,
            NameFixer::PROC_UID_NONE,
            NameFixer::PROC_PAR2_NONE,
            NameFixer::PROC_SRR_NONE,
            NameFixer::PROC_HASH16K_NONE,
            NameFixer::PROC_CRC_NONE,
            Category::getCategoryOthersGroup(),
            $maxPerRun
        ));
    }
}
