<?php

namespace Blacklight\processing\post;

use App\Models\AnidbEpisode;
use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\PopulateAniDB as PaDb;

class AniDB
{
    private const PROC_EXTFAIL = -1; // Release Anime title/episode # could not be extracted from searchname

    private const PROC_NOMATCH = -2; // AniDB ID was not found in anidb table using extracted title/episode #

    /**
     * @var bool Whether to echo messages to CLI
     */
    public bool $echooutput;

    public PaDb $padb;

    /**
     * @var int number of AniDB releases to process
     */
    private int $aniqty;

    /**
     * @var int|null The status of the release being processed
     */
    private ?int $status;

    protected ColorCLI $colorCli;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echooutput = config('nntmux.echocli');
        $this->padb = new PaDb;
        $this->colorCli = new ColorCLI;

        $quantity = (int) Settings::settingValue('maxanidbprocessed');
        $this->aniqty = $quantity ?? 100;

        $this->status = null;
    }

    /**
     * Queues anime releases for processing.
     *
     * @throws \Exception
     */
    public function processAnimeReleases(): void
    {
        $results = Release::query()->whereNull('anidbid')->where('categories_id', Category::TV_ANIME)->orderByDesc('postdate')->limit($this->aniqty)->get();

        if (\count($results) > 0) {
            $this->doRandomSleep();

            foreach ($results as $release) {
                $matched = $this->matchAnimeRelease($release);
                if ($matched === false) {
                    Release::query()->where('id', $release->id)->update(['anidbid' => $this->status]);
                }
            }
        } else {
            $this->colorCli->info('No anidb releases to  process.');
        }
    }

    /**
     * Retrieves a list of Anime titles, optionally filtered by starting character and title.
     */
    private function checkAniDBInfo($anidbId, int $episode = -1): array
    {
        $result = AnidbEpisode::query()->where(
            [
                'anidbid' => $anidbId,
                'episode_no' => $episode,
            ]
        )->select([
            'anidbid',
            'episode_no',
            'airdate',
            'episode_title',
        ])->first();
        if (! empty($result)) {
            return $result->toArray();
        }

        return [];
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
     * Retrieves the AniDB ID for a given anime title.
     */
    private function getAnidbByName(string $searchName = ''): mixed
    {
        return AnidbTitle::query()->where('title', 'like', '%'.$searchName.'%')->select(['anidbid', 'title'])->first();
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

        if (isset($cleanArr['title']) && is_numeric($cleanArr['epno'])) {
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
                if (empty($updatedAni)) {
                    if (! empty($this->updateTimeCheck($anidbId->anidbid))) {
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
        Release::query()->where('id', $relId)->update(['anidbid' => $anidbId]);
    }

    /**
     * Checks if the AniDB ID has been updated in the last week.
     */
    private function updateTimeCheck($anidbId): mixed
    {
        return AnidbInfo::query()->where('updated', '<', now()->subWeek())->where('anidbid', $anidbId)->select('anidbid')->first();
    }
}
