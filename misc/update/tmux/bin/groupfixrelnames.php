<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Nfo;
use Blacklight\NZB;
use Blacklight\NNTP;
use App\Models\Predb;
use App\Models\Release;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\NameFixer;
use Blacklight\NZBContents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Blacklight\processing\PostProcess;

if (! isset($argv[1])) {
    exit(ColorCLI::error('This script is not intended to be run manually, it is called from Multiprocessing.'));
}
$nameFixer = new NameFixer();
[$type, $guidChar, $maxPerRun, $thread] = explode(' ', $argv[1]);

switch (true) {

    case $type === 'standard' && $guidChar !== null && $maxPerRun !== null && is_numeric($maxPerRun):

        // Allow for larger filename return sets
        DB::unprepared('SET SESSION group_concat_max_len = 32768');
        DB::commit();

        // Find releases to process.  We only want releases that have no PreDB match, have not been renamed, exist
        // in Other Categories, have already been PP Add/NFO processed, and haven't been fully fixRelName processed
        $releases = Release::query()
            ->select(
                [
                    'releases.id as releases_id',
                    'releases.guid',
                    'releases.groups_id',
                    'releases.categories_id',
                    'releases.name',
                    'releases.searchname',
                    'releases.proc_nfo',
                    'releases.proc_uid',
                    'releases.proc_files',
                    'releases.proc_par2',
                    'releases.proc_srr',
                    'releases.proc_hash16k',
                    'releases.ishashed',
                    'releases.dehashstatus',
                    'releases.nfostatus',
                    'releases.size as relsize',
                    'releases.predb_id',
                ]
            )
            ->selectRaw('IFNULL(rf.releases_id, 0) AS fileid, IF(rf.ishashed = 1, rf.name, 0) AS filehash')
            ->selectRaw("IFNULL(GROUP_CONCAT(rf.name ORDER BY rf.name ASC SEPARATOR '|'), '') AS filestring")
            ->selectRaw("IFNULL(UNCOMPRESS(rn.nfo), '') AS textstring")
            ->selectRaw("IFNULL(HEX(ru.uniqueid), '') AS uid")
            ->selectRaw('IFNULL(ph.hash, 0) AS hash')
            ->selectRaw("IFNULL(re.mediainfo, '') AS mediainfo")
            ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'releases.id')
            ->leftJoin('release_files as rf', 'rf.releases_id', '=', 'releases.id')
            ->leftJoin('release_unique as ru', 'ru.releases_id', '=', 'releases.id')
            ->leftJoin('par_hashes as ph', 'ph.releases_id', '=', 'releases.id')
            ->leftJoin('releaseextrafull as re', 're.releases_id', '=', 'releases.id')
            ->where('releases.leftguid', $guidChar)
            ->where('releases.nzbstatus', NZB::NZB_ADDED)
            ->where('releases.isrenamed', NameFixer::IS_RENAMED_NONE)
            ->where('releases.predb_id', '=', 0)
            ->where('releases.passwordstatus', '>=', 0)
            ->where('releases.nfostatus', '>', Nfo::NFO_UNPROC)
            ->whereNested(function ($query) {
                $query->orWhere(function ($query) {
                    $query->where('releases.nfostatus', Nfo::NFO_FOUND)
                        ->where('releases.proc_nfo', NameFixer::PROC_NFO_NONE);
                })
                    ->orWhere('releases.proc_files', NameFixer::PROC_FILES_NONE)
                    ->orWhere('releases.proc_uid', NameFixer::PROC_UID_NONE)
                    ->orWhere('releases.proc_par2', NameFixer::PROC_PAR2_NONE)
                    ->orwhere('releases.proc_srr', NameFixer::PROC_SRR_NONE)
                    ->orWhere('releases.proc_hash16k', NameFixer::PROC_HASH16K_NONE)
                    ->orwhere('releases.isrenamed', '=', 1)
                    ->orWhere(function ($query) {
                        $query->where('releases.ishashed', '=', 1)
                        ->whereBetween('releases.dehashstatus', [-6, 0]);
                    })
                    ->orWhereRaw("releases.name REGEXP '[a-z0-9]{32,64}' AND re.mediainfo REGEXP '\<Movie_name\>'");
            })
            ->whereIn('releases.categories_id', Category::OTHERS_GROUP)
            ->groupBy('releases.id')
            ->orderByDesc('releases.id')
            ->limit($maxPerRun)
            ->get();

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
                        $nntp = new NNTP();
                        if (((int) Settings::settingValue('..alternate_nntp') === 1 ? $nntp->doConnect(true, true) : $nntp->doConnect()) !== true) {
                            ColorCLI::error('Unable to connect to usenet.');
                        }
                        $Nfo = new Nfo();
                        $nzbcontents = new NZBContents(
                            [
                                'Echo'        => true, 'NNTP' => $nntp, 'Nfo' => $Nfo,
                                'PostProcess' => new PostProcess(['Nfo' => $Nfo, 'NameFixer' => $nameFixer]),
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

    case $type === 'predbft' && isset($maxPerRun) && is_numeric($maxPerRun) && isset($thread) && is_numeric($thread):
        $pres = Predb::query()
            ->whereRaw('LENGTH(title) >= 15 AND title NOT REGEXP "[\"\<\> ]"')
            ->where('searched', '=', 0)
            ->where('predate', '<', Carbon::now()->subDay())
            ->select(['id as predb_id', 'title', 'source', 'searched'])
            ->orderBy('predate')
            ->limit($maxPerRun)
            ->offset($thread * $maxPerRun - $maxPerRun)
            ->get();

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
