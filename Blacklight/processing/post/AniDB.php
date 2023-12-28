<?php

namespace Blacklight\processing\post;

use App\Models\AnidbEpisode;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\db\populate\AniDB as PaDb;
use Blacklight\NZB;
use Illuminate\Support\Facades\DB;

class AniDB
{
    private const PROC_EXTFAIL = -1; // Release Anime title/episode # could not be extracted from searchname

    private const PROC_NOMATCH = -2; // AniDB ID was not found in anidb table using extracted title/episode #

    /**
     * @var bool Whether or not to echo messages to CLI
     */
    public $echooutput;

    /**
     * @var PaDb
     */
    public $padb;

    /**
     * @var \PDO
     */
    public $pdo;

    /**
     * @var int number of AniDB releases to process
     */
    private $aniqty;

    /**
     * @var int The status of the release being processed
     */
    private $status;

    /**
     * @var ColorCLI
     */
    protected $colorCli;

    /**
     * @param  array  $options  Class instances / Echo to cli.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo' => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && config('nntmux.echocli'));
        $this->padb = new PaDb(['Echo' => $options['Echo']]);
        $this->colorCli = new ColorCLI();

        $qty = (int) Settings::settingValue('..maxanidbprocessed');
        $this->aniqty = $qty ?? 100;

        $this->status = 'NULL';
    }

    /**
     * Queues anime releases for processing.
     *
     * @throws \Exception
     */
    public function processAnimeReleases(): void
    {
        $results = DB::select(
            sprintf(
                '
				SELECT searchname, id
				FROM releases
				WHERE nzbstatus = %d
				AND anidbid IS NULL
				AND categories_id = %d
				ORDER BY postdate DESC
				LIMIT %d',
                NZB::NZB_ADDED,
                Category::TV_ANIME,
                $this->aniqty
            )
        );

        if (\count($results) > 0) {
            $this->doRandomSleep();

            foreach ($results as $release) {
                $matched = $this->matchAnimeRelease($release);
                if ($matched === false) {
                    DB::update(
                        sprintf(
                            '
							UPDATE releases
							SET anidbid = %d
							WHERE id = %d',
                            $this->status,
                            $release->id
                        )
                    );
                }
            }
        } else {
            $this->colorCli->info('No anidb releases to  process.');
        }
    }

    /**
     * Selects episode info for a local match.
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    private function checkAniDBInfo($anidbId, int $episode = -1)
    {
        return AnidbEpisode::query()->where(
            [
                'anidbid' => $anidbId,
                'episode_no' => $episode,
            ]
        )->first(
            [
                'anidbid',
                'episode_no',
                'airdate',
                'episode_title',
            ]
        );
    }

    /**
     * Sleeps between 10 and 15 seconds for AniDB API cooldown.
     *
     *
     * @throws \Exception
     */
    private function doRandomSleep(): void
    {
        sleep(random_int(10, 15));
    }

    /**
     * Extracts anime title and episode info from release searchname.
     *
     * @return array $hits
     */
    private function extractTitleEpisode(string $cleanName = ''): array
    {
        $cleanName = str_replace('_', ' ', $cleanName);

        if (preg_match(
            '/(^|.*\")(\[[a-zA-Z\.\!?-]+\][\s_]*)?(\[BD\][\s_]*)?(\[\d{3,4}[ip]\][\s_]*)?(?P<title>[\w\s_.+!?\'\-\(\)]+)(New Edit|(Blu-?ray)?( ?Box)?( ?Set)?)?([ _]\-[ _]|([ ._-]Epi?(sode)?[ ._-]?0?)?[ ._-]?|[ ._-]Vol\.|[ ._-]E)(?P<epno>\d{1,3}|Movie|OVA|Complete Series)(v\d|-\d+)?[\-_. ].*[\[\(\"]/i',
            $cleanName,
            $hits
        )
        ) {
            $hits['epno'] = (int) $hits['epno'];
            if (\in_array($hits['epno'], ['Movie', 'OVA'], false)) {
                $hits['epno'] = 1;
            }
        } elseif (preg_match(
            '/^(\[[a-zA-Z\.\-!?]+\][\s_]*)?(\[BD\])?(\[\d{3,4}[ip]\])?(?P<title>[\w\s_.+!?\'\-\(\)]+)(New Edit|(Blu-?ray)?( ?Box)?( ?Set)?)?\s*[\(\[](BD|\d{3,4}[ipx])/i',
            $cleanName,
            $hits
        )
        ) {
            $hits['epno'] = 1;
        } elseif (preg_match('#^(\[[a-zA-Z\.\-!?]+\][\s_]*)?(?P<title>[\w -]+)?\s+-\s+(?P<epno>\d+)\s*(\[\d+p\])?$#', $cleanName, $hits)) {
            $hits['epno'] = (int) $hits['epno'];
        } else {
            $this->status = self::PROC_EXTFAIL;
        }

        if (! empty($hits['title'])) {
            $hits['title'] = trim(str_replace(['_', '.'], ' ', $hits['title']));
        }

        return $hits;
    }

    /**
     * Retrieves AniDB Info using a cleaned name.
     *
     *
     * @return mixed
     */
    private function getAnidbByName(string $searchName = '')
    {
        return DB::selectOne(
            sprintf(
                '
				SELECT at.anidbid, at.title
				FROM anidb_titles AS at
				WHERE at.title LIKE %s',
                escapeString('%'.$searchName.'%')
            )
        );
    }

    /**
     * Matches the anime release to AniDB Info
     * If no info is available locally the AniDB API is invoked.
     *
     *
     *
     * @throws \Exception
     */
    private function matchAnimeRelease($release): bool
    {
        $matched = false;
        $type = 'Local';

        // clean up the release name to ensure we get a good chance at getting a valid title
        $cleanArr = $this->extractTitleEpisode($release->searchname);

        if (\is_array($cleanArr) && isset($cleanArr['title']) && is_numeric($cleanArr['epno'])) {
            $this->colorCli->climate()->info('Looking Up:
                     Title: '.$cleanArr['title'].
                '    Episode: '.$cleanArr['epno']);

            // get anidb number for the title of the name
            $anidbId = $this->getAnidbByName($cleanArr['title']);

            if ($anidbId === false) {
                $tmpName = preg_replace('/\s/', '%', $cleanArr['title']);
                $anidbId = $this->getAnidbByName($tmpName);
            }

            if (! empty($anidbId) && is_numeric($anidbId->anidbid) && $anidbId->anidbid > 0) {
                $updatedAni = $this->checkAniDBInfo($anidbId->anidbid, $cleanArr['epno']);

                if ($updatedAni === null) {
                    if ($this->updateTimeCheck($anidbId->anidbid) !== null) {
                        $this->padb->populateTable('info', $anidbId->anidbid);
                        $this->doRandomSleep();
                        $updatedAni = $this->checkAniDBInfo($anidbId->anidbid);
                        $type = 'Remote';
                    } else {
                        echo PHP_EOL.
                            $this->colorCli->info('This AniDB ID was not found to be accurate locally, but has been updated too recently to check AniDB.').
                            PHP_EOL;
                    }
                }

                $episodeTitle = $updatedAni['episode_title'] ?? 'Unknown';

                $this->updateRelease($anidbId->anidbid, $release->id);

                $this->colorCli->headerOver('Matched '.$type.' AniDB ID: ').
                    $this->colorCli->primary($anidbId->anidbid).
                    $this->colorCli->alternateOver('   Title: ').
                    $this->colorCli->primary($anidbId->title).
                    $this->colorCli->alternateOver('   Episode #: ').
                    $this->colorCli->primary($cleanArr['epno']).
                    $this->colorCli->alternateOver('   Episode Title: ').
                    $this->colorCli->primary($episodeTitle);

                $matched = true;
            } else {
                $this->status = self::PROC_NOMATCH;
            }
        }

        return $matched;
    }

    private function updateRelease($anidbId, $relId): void
    {
        Release::update(['anidbid' => $anidbId])->where('id', $relId);
    }

    /**
     * Checks a specific Anime title's last update time.
     *
     *
     * @return mixed
     */
    private function updateTimeCheck($anidbId)
    {
        return DB::selectOne(
            sprintf(
                '
				SELECT anidbid
				FROM anidb_info ai
				WHERE ai.updated < (NOW() - INTERVAL 7 DAY)
				AND ai.anidbid = %d',
                $anidbId
            )
        );
    }
}
