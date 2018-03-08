<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Nfo;
use Blacklight\NZB;
use Blacklight\NNTP;
use App\Models\Predb;
use Blacklight\db\DB;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\NameFixer;
use Blacklight\NZBContents;
use Blacklight\processing\PostProcess;
use Illuminate\Support\Facades\DB as DBFacade;

$pdo = new DB();

if (! isset($argv[1])) {
    exit(ColorCLI::error('This script is not intended to be run manually, it is called from Multiprocessing.'));
}
$nameFixer = new NameFixer(['Settings' => $pdo]);
$pieces = explode(' ', $argv[1]);
[$guidChar, $maxPerRun, $thread] = $pieces;

switch (true) {

    case $pieces[0] === 'standard' && $guidChar !== null && $maxPerRun !== null && is_numeric($maxPerRun):

        // Allow for larger filename return sets
        DBFacade::unprepared('SET SESSION group_concat_max_len = 32768');
        DBFacade::commit();

        // Find releases to process.  We only want releases that have no PreDB match, have not been renamed, exist
        // in Other Categories, have already been PP Add/NFO processed, and haven't been fully fixRelName processed
        $releases = $pdo->queryDirect(
            sprintf(
                "
					SELECT
						r.id AS releases_id, r.guid, r.groups_id, r.categories_id, r.name, r.searchname, r.proc_nfo,
						r.proc_uid, r.proc_files, r.proc_par2, r.proc_srr, r.proc_hash16k, r.proc_sorter, r.ishashed, r
						.dehashstatus, r.nfostatus,
						r.size AS relsize, r.predb_id,
						IFNULL(rf.releases_id, 0) AS fileid, IF(rf.ishashed = 1, rf.name, 0) AS filehash,
						IFNULL(GROUP_CONCAT(rf.name ORDER BY rf.name ASC SEPARATOR '|'), '') AS filestring,
						IFNULL(UNCOMPRESS(rn.nfo), '') AS textstring,
						IFNULL(HEX(ru.uniqueid), '') AS uid,
						IFNULL(ph.hash, 0) AS hash,
						IFNULL(re.mediainfo, '') AS mediainfo
					FROM releases r
					LEFT JOIN release_nfos rn ON r.id = rn.releases_id
					LEFT JOIN release_files rf ON r.id = rf.releases_id
					LEFT JOIN release_unique ru ON ru.releases_id = r.id
					LEFT JOIN par_hashes ph ON ph.releases_id = r.id
					LEFT JOIN releaseextrafull re ON re.releases_id = r.id
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
						OR
						(
							r.ishashed = 1
							AND r.dehashstatus BETWEEN -6 AND 0
						)
						OR (
						    r.name REGEXP '[a-z0-9]{32,64}'
				            AND re.mediainfo REGEXP '\<Movie_name\>'
						)
					)
					AND r.categories_id IN (%s)
					GROUP BY r.id
					ORDER BY r.id+0 DESC
					LIMIT %s",
                $pdo->escapeString($guidChar),
                NZB::NZB_ADDED,
                NameFixer::IS_RENAMED_NONE,
                Nfo::NFO_UNPROC,
                NameFixer::PROC_NFO_NONE,
                NameFixer::PROC_FILES_NONE,
                NameFixer::PROC_UID_NONE,
                NameFixer::PROC_PAR2_NONE,
                NameFixer::PROC_SRR_NONE,
                NameFixer::PROC_HASH16K_NONE,
                Category::getCategoryOthersGroup(),
                $maxPerRun
            )
        );

        if ($releases instanceof \Traversable) {
            foreach ($releases as $release) {
                $nameFixer->checked++;
                $nameFixer->reset();

                echo PHP_EOL.ColorCLI::primaryOver("[{$release['releases_id']}]");

                if ((int) $release['ishashed'] === 1 && (int) $release['dehashstatus'] >= -6 && (int) $release['dehashstatus'] <= 0) {
                    echo ColorCLI::primaryOver('m');
                    if (preg_match('/[a-fA-F0-9]{32,40}/i', $release['name'], $matches)) {
                        $nameFixer->matchPredbHash($matches[0], $release, true, 1, true, 1);
                    }
                    if ($nameFixer->matched === false && ! empty($release['filehash']) && preg_match('/[a-fA-F0-9]{32,40}/i', $release['filehash'], $matches)) {
                        echo ColorCLI::primaryOver('h');
                        $nameFixer->matchPredbHash($matches[0], $release, true, 1, true, 1);
                    }
                }

                if ($nameFixer->matched) {
                    continue;
                }
                $nameFixer->reset();

                if ((int) $release['proc_uid'] === NameFixer::PROC_UID_NONE && (! empty($release['uid']) || ! empty($release['mediainfo']))) {
                    echo ColorCLI::primaryOver('U');
                    $nameFixer->mediaMovieNameCheck($release, true, 'Mediainfo, ', 1, 1);
                    $nameFixer->uidCheck($release, true, 'UID, ', 1, 1);
                }
                // Not all gate requirements in query always set column status as PP Add check is in query
                $nameFixer->_updateSingleColumn('proc_uid', NameFixer::PROC_UID_DONE, $release['releases_id']);

                if ($nameFixer->matched) {
                    continue;
                }
                $nameFixer->reset();

                if ((int) $release['proc_srr'] === NameFixer::PROC_SRR_NONE) {
                    echo ColorCLI::primaryOver('sr');
                    $nameFixer->srrNameCheck($release, true, 'SRR, ', 1, 1);
                }
                // Not all gate requirements in query always set column status as PP Add check is in query
                $nameFixer->_updateSingleColumn('proc_srr', NameFixer::PROC_SRR_DONE, $release['releases_id']);

                if ($nameFixer->matched) {
                    continue;
                }
                $nameFixer->reset();

                if ((int) $release['proc_hash16k'] === NameFixer::PROC_HASH16K_NONE && ! empty($release['hash'])) {
                    echo ColorCLI::primaryOver('H');
                    $nameFixer->hashCheck($release, true, 'PAR2 hash, ', 1, 1);
                }
                // Not all gate requirements in query always set column status as PP Add check is in query
                $nameFixer->_updateSingleColumn('proc_hash16k', NameFixer::PROC_HASH16K_DONE, $release['releases_id']);

                if ($nameFixer->matched) {
                    continue;
                }
                $nameFixer->reset();

                if ((int) $release['nfostatus'] === Nfo::NFO_FOUND && (int) $release['proc_nfo'] === NameFixer::PROC_NFO_NONE) {
                    if (! empty($release['textstring']) && ! preg_match('/^=newz\[NZB\]=\w+/', $release['textstring'])) {
                        echo ColorCLI::primaryOver('n');
                        $nameFixer->done = $nameFixer->matched = false;
                        $nameFixer->checkName($release, true, 'NFO, ', 1, 1);
                    }
                    $nameFixer->_updateSingleColumn('proc_nfo', NameFixer::PROC_NFO_DONE, $release['releases_id']);
                }

                if ($nameFixer->matched) {
                    continue;
                }
                $nameFixer->reset();

                if ((int) $release['fileid'] > 0 && (int) $release['proc_files'] === NameFixer::PROC_FILES_NONE) {
                    echo ColorCLI::primaryOver('F');
                    $nameFixer->done = $nameFixer->matched = false;
                    $fileNames = explode('|', $release['filestring']);
                    if (is_array($fileNames)) {
                        $releaseFile = $release;
                        foreach ($fileNames as $fileName) {
                            if ($nameFixer->matched === false) {
                                echo ColorCLI::primaryOver('f');
                                $releaseFile['textstring'] = $fileName;
                                $nameFixer->checkName($releaseFile, true, 'Filenames, ', 1, 1);
                            }
                        }
                    }
                }
                // Not all gate requirements in query always set column status as PP Add check is in query
                $nameFixer->_updateSingleColumn('proc_files', NameFixer::PROC_FILES_DONE, $release['releases_id']);

                if ($nameFixer->matched) {
                    continue;
                }
                $nameFixer->reset();

                if ((int) $release['proc_par2'] === NameFixer::PROC_PAR2_NONE) {
                    echo ColorCLI::primaryOver('p');
                    if (! isset($nzbcontents)) {
                        $nntp = new NNTP(['Settings' => $pdo]);
                        if (((int) Settings::settingValue('..alternate_nntp') === 1 ? $nntp->doConnect(true, true) : $nntp->doConnect()) !== true) {
                            ColorCLI::error('Unable to connect to usenet.');
                        }
                        $Nfo = new Nfo();
                        $nzbcontents = new NZBContents(
                            [
                                'Echo'        => true, 'NNTP' => $nntp, 'Nfo' => $Nfo, 'Settings' => $pdo,
                                'PostProcess' => new PostProcess(['Settings' => $pdo, 'Nfo' => $Nfo, 'NameFixer' => $nameFixer]),
                            ]
                        );
                    }

                    $nzbcontents->checkPAR2($release['guid'], $release['releases_id'], $release['groups_id'], 1, 1);
                }

                // Not all gate requirements in query always set column status as PP Add check is in query
                $nameFixer->_updateSingleColumn('proc_par2', NameFixer::PROC_PAR2_DONE, $release['releases_id']);

                if ($nameFixer->matched) {
                    continue;
                }
            }
        }
        break;

    case $pieces[0] === 'predbft' && isset($maxPerRun) && is_numeric($maxPerRun) && isset($thread) && is_numeric($thread):
        $pres = $pdo->queryDirect(
            sprintf(
                '
					SELECT p.id AS predb_id, p.title, p.source, p.searched
					FROM predb p
					WHERE LENGTH(title) >= 15 AND title NOT REGEXP "[\"\<\> ]"
					AND searched = 0
					AND predate < (NOW() - INTERVAL 1 DAY)
					ORDER BY predate+0 ASC
					LIMIT %s
					OFFSET %s',
                $maxPerRun,
                $thread * $maxPerRun - $maxPerRun
            )
        );

        if ($pres instanceof \Traversable) {
            foreach ($pres as $pre) {
                $nameFixer->done = $nameFixer->matched = false;
                $searched = 0;
                $ftmatched = $nameFixer->matchPredbFT($pre, true, 1, true, 1);
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
}
