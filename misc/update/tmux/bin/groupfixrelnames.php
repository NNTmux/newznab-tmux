<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Category;
use App\Models\Predb;
use App\Models\Release;
use Blacklight\ColorCLI;
use Blacklight\NameFixer;
use Blacklight\Nfo;
use Blacklight\NNTP;
use Blacklight\NZB;
use Blacklight\NZBContents;
use Blacklight\processing\PostProcess;
use Illuminate\Support\Facades\DB;

$colorCli = new ColorCLI;
if (! isset($argv[1])) {
    $colorCli->error('This script is not intended to be run manually, it is called from Multiprocessing.');
    exit();
}
$nameFixer = new NameFixer;
[$type, $guidChar, $maxPerRun, $thread] = explode(' ', $argv[1]);

switch (true) {
    case $type === 'standard' && $guidChar !== null && $maxPerRun !== null && is_numeric($maxPerRun):

        // Allow for larger filename return sets
        DB::statement('SET SESSION group_concat_max_len = 65536');

        // Find releases to process.  We only want releases that have no PreDB match, have not been renamed, exist
        // in Other Categories, have already been PP Add/NFO processed, and haven't been fully fixRelName processed
        $releases = Release::fromQuery(sprintf("
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
					AND r.nzbstatus = %d
					AND r.isrenamed = %d
					AND r.predb_id = 0
					AND r.passwordstatus >= 0
					AND r.nfostatus > %d
					AND
					(
						(
							r.nfostatus = %d
							AND r.proc_nfo = %d
						)
						OR r.proc_files = %d
						OR r.proc_uid = %d
						OR r.proc_par2 = %d
						OR r.proc_srr = %d
						OR r.proc_hash16k = %d
						OR r.proc_crc32 = %d
						OR
						(
							r.ishashed = 1
							AND r.dehashstatus BETWEEN -6 AND 0
						)
					)
					AND r.categories_id IN (%s)
					GROUP BY r.id
					ORDER BY r.id DESC
					LIMIT %s", escapeString($guidChar), NZB::NZB_ADDED, NameFixer::IS_RENAMED_NONE, Nfo::NFO_UNPROC, Nfo::NFO_FOUND, NameFixer::PROC_NFO_NONE, NameFixer::PROC_FILES_NONE, NameFixer::PROC_UID_NONE, NameFixer::PROC_PAR2_NONE, NameFixer::PROC_SRR_NONE, NameFixer::PROC_HASH16K_NONE, NameFixer::PROC_CRC_NONE, Category::getCategoryOthersGroup(), $maxPerRun));

        foreach ($releases as $release) {
            $nameFixer->checked++;
            $nameFixer->reset();

            $colorCli->primary("[{$release->releases_id}]", true);

            if ((int) $release->proc_uid === NameFixer::PROC_UID_NONE && (! empty($release->uid) || ! empty($release->mediainfo))) {
                $colorCli->primaryOver('U');
                if (! empty($release->uid)) {
                    $nameFixer->checkName($release, true, 'UID, ', 1, true);
                }
                if (empty($nameFixer->matched) && ! empty($release->mediainfo)) {
                    $nameFixer->checkName($release, true, 'Mediainfo, ', 1, true);
                }
            }

            $nameFixer->_updateSingleColumn('proc_uid', NameFixer::PROC_UID_DONE, $release->releases_id);

            if ($nameFixer->matched) {
                continue;
            }
            $nameFixer->reset();

            if ((int) $release->proc_crc32 === NameFixer::PROC_CRC_NONE && ! empty($release->crc)) {
                $colorCli->primaryOver('C');
                $nameFixer->checkName($release, true, 'CRC32, ', 1, true);
            }

            $nameFixer->_updateSingleColumn('proc_crc32', NameFixer::PROC_CRC_DONE, $release->releases_id);

            if ($nameFixer->matched) {
                continue;
            }
            $nameFixer->reset();

            if ((int) $release->proc_srr === NameFixer::PROC_SRR_NONE) {
                $colorCli->primaryOver('sr');
                $nameFixer->checkName($release, true, 'SRR, ', 1, true);
            }

            $nameFixer->_updateSingleColumn('proc_srr', NameFixer::PROC_SRR_DONE, $release->releases_id);

            if ($nameFixer->matched) {
                continue;
            }
            $nameFixer->reset();

            if ((int) $release->proc_hash16k === NameFixer::PROC_HASH16K_NONE && ! empty($release->hash)) {
                $colorCli->primaryOver('H');
                $nameFixer->checkName($release, true, 'PAR2 hash, ', 1, true);
            }

            $nameFixer->_updateSingleColumn('proc_hash16k', NameFixer::PROC_HASH16K_DONE, $release->releases_id);

            if ($nameFixer->matched) {
                continue;
            }
            $nameFixer->reset();

            if ((int) $release->nfostatus === Nfo::NFO_FOUND && (int) $release->proc_nfo === NameFixer::PROC_NFO_NONE && ! empty($release->textstring) && ! preg_match('/^=newz\[NZB\]=\w+/', $release->textstring)) {
                $colorCli->primaryOver('n');
                $nameFixer->done = $nameFixer->matched = false;
                $nameFixer->checkName($release, true, 'NFO, ', 1, true);
            }

            $nameFixer->_updateSingleColumn('proc_nfo', NameFixer::PROC_NFO_DONE, $release->releases_id);

            if ($nameFixer->matched) {
                continue;
            }
            $nameFixer->reset();

            if ((int) $release->fileid > 0 && (int) $release->proc_files === NameFixer::PROC_FILES_NONE) {
                $colorCli->primaryOver('F');
                $nameFixer->done = $nameFixer->matched = false;
                $fileNames = explode('|', $release->filestring);
                if (is_array($fileNames)) {
                    $releaseFile = $release;
                    foreach ($fileNames as $fileName) {
                        if ($nameFixer->matched === false) {
                            $colorCli->primaryOver('f');
                            $releaseFile->textstring = $fileName;
                            $nameFixer->checkName($releaseFile, true, 'Filenames, ', 1, true);
                        }
                    }
                }
            }
            // Not all gate requirements in query always set column status as PP Add check is in query
            $nameFixer->_updateSingleColumn('proc_files', NameFixer::PROC_FILES_DONE, $release->releases_id);

            if ($nameFixer->matched) {
                continue;
            }
            $nameFixer->reset();

            if ((int) $release->proc_par2 === NameFixer::PROC_PAR2_NONE) {
                $colorCli->primaryOver('p');
                if (! isset($nzbcontents)) {
                    $nntp = new NNTP;
                    $compressedHeaders = config('nntmux_nntp.compressed_headers');
                    if ((config('nntmux_nntp.use_alternate_nntp_server') === true ? $nntp->doConnect($compressedHeaders, true) : $nntp->doConnect()) !== true) {
                        $colorCli->error('Unable to connect to usenet.');
                    }
                    $Nfo = new Nfo;
                    $nzbcontents = new NZBContents([
                        'Echo' => true,
                        'NNTP' => $nntp,
                        'Nfo' => $Nfo,
                        'PostProcess' => new PostProcess(['Nfo' => $Nfo, 'NameFixer' => $nameFixer]),
                    ]);
                }

                $nzbcontents->checkPAR2($release->guid, $release->releases_id, $release->groups_id, 1, true);
            }

            // Not all gate requirements in query always set column status as PP Add check is in query
            $nameFixer->_updateSingleColumn('proc_par2', NameFixer::PROC_PAR2_DONE, $release->releases_id);
        }
        break;

    case $type === 'predbft' && isset($maxPerRun) && is_numeric($maxPerRun) && isset($thread) && is_numeric($thread):
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
                $thread * $maxPerRun - $maxPerRun
            )
        );

        foreach ($pres as $pre) {
            $nameFixer->done = $nameFixer->matched = false;
            $searched = 0;
            $ftmatched = $nameFixer->matchPredbFT($pre, true, 1, true);
            if ($ftmatched > 0) {
                $searched = 1;
            } elseif ($ftmatched < 0) {
                $searched = -6;
                echo '*';
            } else {
                $searched = $pre['searched'] - 1;
                echo '.';
            }
            Predb::query()->where('id', $pre['predb_id'])->update(['searched' => $searched]);
            $nameFixer->checked++;
        }
}
