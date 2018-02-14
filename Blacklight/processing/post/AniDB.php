<?php

namespace Blacklight\processing\post;

use Blacklight\NZB;
use Blacklight\db\DB;
use Blacklight\ColorCLI;
use App\Models\Category;
use App\Models\Settings;
use App\Models\AnidbEpisode;
use Blacklight\db\populate\AniDB as PaDb;

class AniDB
{
    protected const PROC_EXTFAIL = -1; // Release Anime title/episode # could not be extracted from searchname
    protected const PROC_NOMATCH = -2; // AniDB ID was not found in anidb table using extracted title/episode #

    protected const REGEX_NOFORN = 'English|Japanese|German|Danish|Flemish|Dutch|French|Swe(dish|sub)|Deutsch|Norwegian';

    /**
     * @var bool Whether or not to echo messages to CLI
     */
    public $echooutput;

    /**
     * @var
     */
    public $padb;

    /**
     * @var \Blacklight\db\DB
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
     * @param array $options Class instances / Echo to cli.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'     => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && NN_ECHOCLI);
        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());

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
        $results = $this->pdo->queryDirect(
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

        if ($results instanceof \Traversable) {
            $this->doRandomSleep();

            $this->padb = new PaDb(
                [
                    'Echo'     => $this->echooutput,
                    'Settings' => $this->pdo,
                ]
            );

            foreach ($results as $release) {
                $matched = $this->matchAnimeRelease($release);
                if ($matched === false) {
                    $this->pdo->queryExec(
                        sprintf(
                            '
							UPDATE releases
							SET anidbid = %d
							WHERE id = %d',
                            $this->status,
                            $release['id']
                        )
                    );
                }
            }
        } else {
            ColorCLI::doEcho(ColorCLI::info('No anidb releases to  process.'), true);
        }
    }

    /**
     * Selects episode info for a local match.
     *
     * @param $anidbId
     * @param int $episode
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    private function checkAniDBInfo($anidbId, $episode = -1)
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
     */
    private function doRandomSleep(): void
    {
        sleep(random_int(10, 15));
    }

    /**
     * Extracts anime title and episode info from release searchname.
     *
     * @param string $cleanName
     *
     * @return array $matches
     */
    private function extractTitleEpisode($cleanName = ''): array
    {
        $cleanName = str_replace('_', ' ', $cleanName);

        if (preg_match(
            '/(^|.*\")(\[[a-zA-Z\.\!?-]+\][\s_]*)?(\[BD\][\s_]*)?(\[\d{3,4}[ip]\][\s_]*)?(?P<title>[\w\s_.+!?\'-\(\)]+)(New Edit|(Blu-?ray)?( ?Box)?( ?Set)?)?([ _]-[ _]|([ ._-]Epi?(sode)?[ ._-]?0?)?[ ._-]?|[ ._-]Vol\.|[ ._-]E)(?P<epno>\d{1,3}|Movie|OVA|Complete Series)(v\d|-\d+)?[-_. ].*[\[\(\"]/i',
            $cleanName,
            $matches
        )
        ) {
            $matches['epno'] = (int) $matches['epno'];
            if (\in_array($matches['epno'], ['Movie', 'OVA'], false)) {
                $matches['epno'] = 1;
            }
        } elseif (preg_match(
            '/^(\[[a-zA-Z\.\-!?]+\][\s_]*)?(\[BD\])?(\[\d{3,4}[ip]\])?(?P<title>[\w\s_.+!?\'-\(\)]+)(New Edit|(Blu-?ray)?( ?Box)?( ?Set)?)?\s*[\(\[](BD|\d{3,4}[ipx])/i',
            $cleanName,
            $matches
        )
        ) {
            $matches['epno'] = 1;
        } else {
            $this->status = self::PROC_EXTFAIL;
        }

        if (! empty($matches['title'])) {
            $matches['title'] = trim(str_replace(['_', '.'], ' ', $matches['title']));
        }

        return $matches;
    }

    /**
     * Retrieves AniDB Info using a cleaned name.
     *
     * @param string $searchName
     *
     * @return array|bool
     */
    private function getAnidbByName($searchName = '')
    {
        return $this->pdo->queryOneRow(
            sprintf(
                '
				SELECT at.anidbid, at.title
				FROM anidb_titles AS at
				WHERE at.title %s',
                $this->pdo->likeString($searchName, true, true)
            )
        );
    }

    /**
     * Matches the anime release to AniDB Info
     * If no info is available locally the AniDB API is invoked.
     *
     * @param array $release
     *
     * @return bool
     * @throws \Exception
     */
    private function matchAnimeRelease(array $release = []): bool
    {
        $matched = false;
        $type = 'Local';

        // clean up the release name to ensure we get a good chance at getting a valid title
        $cleanArr = $this->extractTitleEpisode($release['searchname']);

        if (\is_array($cleanArr) && isset($cleanArr['title']) && is_numeric($cleanArr['epno'])) {
            echo ColorCLI::header(PHP_EOL.'Looking Up: ').
                ColorCLI::primary('   Title: '.$cleanArr['title'].PHP_EOL.
                    '   Episode: '.$cleanArr['epno']);

            // get anidb number for the title of the name
            $anidbId = $this->getAnidbByName($cleanArr['title']);

            if ($anidbId === false) {
                $tmpName = preg_replace('/\s/', '%', $cleanArr['title']);
                $anidbId = $this->getAnidbByName($tmpName);
            }

            if (! empty($anidbId) && is_numeric($anidbId['anidbid']) && $anidbId['anidbid'] > 0) {
                $updatedAni = $this->checkAniDBInfo($anidbId['anidbid'], $cleanArr['epno']);

                if ($updatedAni === false) {
                    if ($this->updateTimeCheck($anidbId['anidbid']) !== false) {
                        $this->padb->populateTable('info', $anidbId['anidbid']);
                        $this->doRandomSleep();
                        $updatedAni = $this->checkAniDBInfo($anidbId['anidbid']);
                        $type = 'Remote';
                    } else {
                        echo PHP_EOL.
                            ColorCLI::info('This AniDB ID was not found to be accurate locally, but has been updated too recently to check AniDB.').
                            PHP_EOL;
                    }
                }

                $this->updateRelease($anidbId['anidbid'], $release['id']);

                ColorCLI::doEcho(
                    ColorCLI::headerOver('Matched '.$type.' AniDB ID: ').
                    ColorCLI::primary($anidbId['anidbid']).
                    ColorCLI::alternateOver('   Title: ').
                    ColorCLI::primary($anidbId['title']).
                    ColorCLI::alternateOver('   Episode #: ').
                    ColorCLI::primary($cleanArr['epno']).
                    ColorCLI::alternateOver('   Episode Title: ').
                    ColorCLI::primary($updatedAni['episode_title']), true
                );

                $matched = true;
            } else {
                $this->status = self::PROC_NOMATCH;
            }
        }

        return $matched;
    }

    /**
     * @param $anidbId
     * @param $relId
     */
    private function updateRelease($anidbId, $relId): void
    {
        $this->pdo->queryExec(
            sprintf(
                '
				UPDATE releases
				SET anidbid = %d
				WHERE id = %d',
                $anidbId,
                $relId
            )
        );
    }

    /**
     * Checks a specific Anime title's last update time.
     *
     * @param int $anidbId
     *
     * @return bool|\PDOStatement Has it been 7 days since we last updated this AniDB ID or not?
     */
    private function updateTimeCheck($anidbId)
    {
        return $this->pdo->queryOneRow(
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
