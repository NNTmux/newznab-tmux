<?php

namespace App\Services\TvProcessing\Providers;

use App\Services\FanartTvService;
use App\Services\ReleaseImageService;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ParseException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ResourceNotFoundException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException;
use CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Class TvdbProvider -- functions used to post process releases against TVDB.
 */
class TvdbProvider extends AbstractTvProvider
{
    private const MATCH_PROBABILITY = 75;

    public TheTVDbAPI $client;

    /**
     * @var string Authorization token for TVDB v2 API
     */
    public string $token = '';

    /**
     * @string URL for show poster art
     */
    public string $posterUrl = '';

    /**
     * @bool Do a local lookup only if server is down
     */
    private bool $local;

    private FanartTvService $fanart;

    private mixed $fanartapikey;

    /**
     * TvdbProvider constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new TheTVDbAPI;
        $this->local = false;
        $this->authorizeTvdb();

        $this->fanartapikey = config('nntmux_api.fanarttv_api_key');
        $this->fanart = new FanartTvService($this->fanartapikey);
    }

    /**
     * Main processing director function for scrapers
     * Calls work query function and initiates processing.
     */
    public function processSite(mixed $groupID, mixed $guidChar, mixed $process, bool $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TVDB);

        $tvCount = \count($res);

        if ($tvCount === 0) {
            return;
        }

        $this->titleCache = [];
        $processed = 0;
        $matched = 0;
        $skipped = 0;

        foreach ($res as $row) {
            $processed++;
            $siteId = false;
            $this->posterUrl = '';

            $release = $this->parseInfo($row['searchname']);
            if (\is_array($release) && $release['name'] !== '') {
                if (\in_array($release['cleanname'], $this->titleCache, false)) {
                    if ($this->echooutput) {
                        cli()->primaryOver('    → ');
                        cli()->alternateOver($this->truncateTitle($release['cleanname']));
                        cli()->primaryOver(' → ');
                        cli()->alternate('Skipped (previously failed)');
                    }
                    $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                    $skipped++;

                    continue;
                }

                $videoId = $this->getByTitle($release['cleanname'], parent::TYPE_TV);

                if ($videoId !== 0) {
                    $siteId = $this->getSiteByID('tvdb', $videoId);
                }

                $lookupSetting = true;
                if ($local === true || $this->local) {
                    $lookupSetting = false;
                }

                if ($siteId === false && $lookupSetting) {
                    if ($this->echooutput) {
                        cli()->primaryOver('    → ');
                        cli()->headerOver($this->truncateTitle($release['cleanname']));
                        cli()->primaryOver(' → ');
                        cli()->info('Searching TVDB...');
                    }

                    $country = (
                        isset($release['country']) && \strlen($release['country']) === 2
                            ? (string) $release['country']
                            : ''
                    );

                    $tvdbShow = $this->getShowInfo((string) $release['cleanname']);

                    if (\is_array($tvdbShow)) {
                        $tvdbShow['country'] = $country;
                        $videoId = $this->add($tvdbShow);
                        $siteId = (int) $tvdbShow['tvdb'];
                    }
                } elseif ($this->echooutput && $siteId !== false) {
                    cli()->primaryOver('    → ');
                    cli()->headerOver($this->truncateTitle($release['cleanname']));
                    cli()->primaryOver(' → ');
                    cli()->info('Found in DB');
                }

                if ((int) $videoId > 0 && (int) $siteId > 0) {
                    if (! empty($tvdbShow['poster'])) {
                        $this->getPoster($videoId);
                    } elseif ($this->fanart->isConfigured()) {
                        $posterUrl = $this->fanart->getBestTvPoster($siteId);
                        if (! empty($posterUrl)) {
                            $this->posterUrl = $posterUrl;
                            $this->getPoster($videoId);
                        }
                    }

                    $seriesNo = (! empty($release['season']) ? preg_replace('/^S0*/i', '', $release['season']) : '');
                    $episodeNo = (! empty($release['episode']) ? preg_replace('/^E0*/i', '', $release['episode']) : '');
                    $hasAirdate = ! empty($release['airdate']);

                    if ($episodeNo === 'all') {
                        $this->setVideoIdFound($videoId, $row['id'], 0);
                        if ($this->echooutput) {
                            cli()->primaryOver('    → ');
                            cli()->headerOver($this->truncateTitle($release['cleanname']));
                            cli()->primaryOver(' → ');
                            cli()->primary('Full Season matched');
                        }
                        $matched++;

                        continue;
                    }

                    if (! $this->countEpsByVideoID($videoId)) {
                        $this->getEpisodeInfo($siteId, -1, -1, $videoId);
                    }

                    $episode = $this->getBySeasonEp($videoId, $seriesNo, $episodeNo, $release['airdate']);

                    if ($episode === false && $lookupSetting) {
                        if ($seriesNo !== '' && $episodeNo !== '') {
                            $tvdbEpisode = $this->getEpisodeInfo($siteId, (int) $seriesNo, (int) $episodeNo, $videoId);
                            if ($tvdbEpisode) {
                                $episode = $this->addEpisode($videoId, $tvdbEpisode);
                            }
                        }

                        if ($episode === false && $hasAirdate) {
                            $this->getEpisodeInfo($siteId, -1, -1, $videoId);
                            $episode = $this->getBySeasonEp($videoId, 0, 0, $release['airdate']);
                        }
                    }

                    if ($episode !== false && is_numeric($episode) && $episode > 0) {
                        $this->setVideoIdFound($videoId, $row['id'], $episode);
                        if ($this->echooutput) {
                            cli()->primaryOver('    → ');
                            cli()->headerOver($this->truncateTitle($release['cleanname']));
                            if ($seriesNo !== '' && $episodeNo !== '') {
                                cli()->primaryOver(' S');
                                cli()->warningOver(sprintf('%02d', $seriesNo));
                                cli()->primaryOver('E');
                                cli()->warningOver(sprintf('%02d', $episodeNo));
                            } elseif ($hasAirdate) {
                                cli()->primaryOver(' | ');
                                cli()->warningOver($release['airdate']);
                            }
                            cli()->primaryOver(' ✓ ');
                            cli()->primary('MATCHED (TVDB)');
                        }
                        $matched++;
                    } else {
                        $this->setVideoIdFound($videoId, $row['id'], 0);
                        $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                        if ($this->echooutput) {
                            cli()->primaryOver('    → ');
                            cli()->alternateOver($this->truncateTitle($release['cleanname']));
                            if ($hasAirdate) {
                                cli()->primaryOver(' | ');
                                cli()->warningOver($release['airdate']);
                            }
                            cli()->primaryOver(' → ');
                            cli()->warning('Episode not found');
                        }
                    }
                } else {
                    $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                    if ($this->echooutput) {
                        cli()->primaryOver('    → ');
                        cli()->alternateOver($this->truncateTitle($release['cleanname']));
                        cli()->primaryOver(' → ');
                        cli()->warning('Not found');
                    }
                }
            } else {
                $this->setVideoNotFound(parent::FAILED_PARSE, $row['id']);
                $this->titleCache[] = $release['cleanname'] ?? null;
                if ($this->echooutput) {
                    cli()->error(sprintf(
                        '  ✗ [%d/%d] Parse failed: %s',
                        $processed,
                        $tvCount,
                        mb_substr($row['searchname'], 0, 50)
                    ));
                }
            }
        }

        if ($this->echooutput && $matched > 0) {
            echo "\n";
            cli()->primaryOver('  ✓ TVDB: ');
            cli()->primary(sprintf('%d matched, %d skipped', $matched, $skipped));
        }
    }

    public function getBanner(mixed $videoID, mixed $siteId): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UnauthorizedException
     * @throws ParseException
     * @throws ExceptionInterface
     */
    public function getShowInfo(string $name): bool|array
    {
        $return = $response = false;
        $highestMatch = 0;
        try {
            $response = $this->client->search()->search($name, ['type' => 'series']);
        } catch (ResourceNotFoundException $e) {
            $response = false;
            cli()->error('Show not found on TVDB');
        } catch (UnauthorizedException $e) {
            try {
                $this->authorizeTvdb();
            } catch (UnauthorizedException $error) {
                cli()->error('Not authorized to access TVDB');
            }
        }

        sleep(1);

        if (\is_array($response)) {
            foreach ($response as $show) {
                if ($this->checkRequiredAttr($show, 'tvdbS')) {
                    if (strtolower($show->name) === strtolower($name)) {
                        $highest = $show;
                        break;
                    }

                    $matchPercent = $this->checkMatch(strtolower($show->name), strtolower($name), self::MATCH_PROBABILITY);

                    if ($matchPercent > $highestMatch) {
                        $highestMatch = $matchPercent;
                        $highest = $show;
                    }

                    if (! empty($show->aliases)) {
                        foreach ($show->aliases as $akaIndex => $akaName) {
                            $aliasPercent = $this->checkMatch(strtolower($akaName), strtolower($name), self::MATCH_PROBABILITY);
                            if ($aliasPercent > $highestMatch) {
                                $highestMatch = $aliasPercent;
                                $highest = $show;
                            }
                        }
                    }
                }
            }
            if (! empty($highest)) {
                $return = $this->formatShowInfo($highest);
            }
        }

        return $return;
    }

    public function getPoster(int $videoId): int
    {
        $ri = new ReleaseImageService;
        $hasCover = 0;

        if (! empty($this->posterUrl)) {
            $hasCover = $ri->saveImage((string) $videoId, $this->posterUrl, $this->imgSavePath);
            if ($hasCover === 1) {
                $this->setCoverFound($videoId);
            }
        }

        return $hasCover;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ParseException
     * @throws UnauthorizedException
     * @throws ExceptionInterface
     */
    public function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode, int $videoId = 0): bool|array
    {
        $return = $response = false;

        if (! $this->local) {
            if ($videoId > 0) {
                try {
                    $response = $this->client->series()->allEpisodes($siteId);
                } catch (ResourceNotFoundException $error) {
                    return false;
                } catch (UnauthorizedException $error) {
                    try {
                        $this->authorizeTvdb();
                    } catch (UnauthorizedException $error) {
                        cli()->error('Not authorized to access TVDB');
                    }
                }
            } else {
                try {
                    foreach ($this->client->series()->episodes($siteId) as $episodeBaseRecord) {
                        if ($episodeBaseRecord->seasonNumber === $series && $episodeBaseRecord->number === $episode) {
                            $response = $episodeBaseRecord;
                        }
                    }
                } catch (ResourceNotFoundException $error) {
                    return false;
                }
            }

            sleep(1);

            if (\is_object($response)) {
                if ($this->checkRequiredAttr($response, 'tvdbE')) {
                    $return = $this->formatEpisodeInfo($response);
                }
            } elseif ($videoId > 0 && \is_array($response)) {
                foreach ($response as $singleEpisode) {
                    if ($this->checkRequiredAttr($singleEpisode, 'tvdbE')) {
                        $this->addEpisode($videoId, $this->formatEpisodeInfo($singleEpisode));
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ExceptionInterface
     * @throws ParseException
     */
    public function formatShowInfo(mixed $show): array
    {
        try {
            $poster = $this->client->series()->artworks($show->tvdb_id);
            $poster = collect($poster)->where('type', 2)->sortByDesc('score')->first();
            $this->posterUrl = ! empty($poster->image) ? $poster->image : '';
        } catch (ResourceNotFoundException $e) {
            cli()->error('Poster image not found on TVDB');
        } catch (UnauthorizedException $error) {
            try {
                $this->authorizeTvdb();
            } catch (UnauthorizedException $error) {
                cli()->error('Not authorized to access TVDB');
            }
        }

        $imdbId = 0;
        $imdbIdObj = null;
        try {
            $imdbIdObj = $this->client->series()->extended($show->tvdb_id);
            preg_match('/tt(?P<imdbid>\d{6,9})$/i', $imdbIdObj->getIMDBId(), $imdb);
            $imdbId = $imdb['imdbid'] ?? 0;
        } catch (ResourceNotFoundException $e) {
            cli()->error('Show ImdbId not found on TVDB');
        } catch (\Exception) {
            cli()->error('Error on TVDB, aborting');
        }

        // Look up TMDB and Trakt IDs using available external IDs
        $externalIds = $this->lookupExternalIds($show->tvdb_id, $imdbId);

        return [
            'type' => parent::TYPE_TV,
            'title' => $show->name,
            'summary' => $show->overview,
            'started' => $show->first_air_time,
            'publisher' => $imdbIdObj->originalNetwork->name ?? '',
            'poster' => $this->posterUrl,
            'source' => parent::SOURCE_TVDB,
            'imdb' => $imdbId,
            'tvdb' => $show->tvdb_id,
            'trakt' => $externalIds['trakt'],
            'tvrage' => 0,
            'tvmaze' => 0,
            'tmdb' => $externalIds['tmdb'],
            'aliases' => ! empty($show->aliases) ? $show->aliases : '',
            'localzone' => "''",
        ];
    }

    /**
     * Look up TMDB and Trakt IDs using TVDB ID and IMDB ID.
     *
     * @param  int  $tvdbId  TVDB show ID
     * @param  int|string  $imdbId  IMDB ID (numeric, without 'tt' prefix)
     * @return array<string, mixed> ['tmdb' => int, 'trakt' => int]
     */
    protected function lookupExternalIds(int $tvdbId, int|string $imdbId): array
    {
        $result = ['tmdb' => 0, 'trakt' => 0];

        try {
            // Try to get TMDB ID and other IDs via TMDB's find endpoint
            $tmdbClient = app(\App\Services\TmdbClient::class);
            if ($tmdbClient->isConfigured() && $tvdbId > 0) {
                $tmdbIds = $tmdbClient->lookupTvShowIds($tvdbId, 'tvdb');
                if ($tmdbIds !== null) {
                    $result['tmdb'] = $tmdbIds['tmdb'] ?? 0;
                }
            }

            // Try to get Trakt ID via Trakt's search endpoint
            $traktService = app(\App\Services\TraktService::class);
            if ($traktService->isConfigured()) {
                // Try TVDB ID first
                if ($tvdbId > 0) {
                    $traktIds = $traktService->lookupShowIds($tvdbId, 'tvdb');
                    if ($traktIds !== null && ! empty($traktIds['trakt'])) {
                        $result['trakt'] = (int) $traktIds['trakt'];
                        // Also get TMDB if we didn't find it above
                        if ($result['tmdb'] === 0 && ! empty($traktIds['tmdb'])) {
                            $result['tmdb'] = (int) $traktIds['tmdb'];
                        }

                        return $result;
                    }
                }

                // Try IMDB ID as fallback
                if (! empty($imdbId) && $imdbId > 0) {
                    $traktIds = $traktService->lookupShowIds($imdbId, 'imdb');
                    if ($traktIds !== null && ! empty($traktIds['trakt'])) {
                        $result['trakt'] = (int) $traktIds['trakt'];
                        if ($result['tmdb'] === 0 && ! empty($traktIds['tmdb'])) {
                            $result['tmdb'] = (int) $traktIds['tmdb'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently fail - external ID lookup is optional enrichment
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        return [
            'title' => (string) $episode->name,
            'series' => (int) $episode->seasonNumber,
            'episode' => (int) $episode->number,
            'se_complete' => 'S'.sprintf('%02d', $episode->seasonNumber).'E'.sprintf('%02d', $episode->number),
            'firstaired' => $episode->aired,
            'summary' => (string) $episode->overview,
        ];
    }

    protected function authorizeTvdb(): void
    {
        $this->token = '';
        if (config('tvdb.api_key') === null || config('tvdb.user_pin') === null) {
            cli()->warning('TVDB API key or user pin not set. Running in local mode only!', true);
            $this->local = true;
        } else {
            try {
                $this->token = $this->client->authentication()->login(config('tvdb.api_key'), config('tvdb.user_pin'));
            } catch (UnauthorizedException $error) {
                cli()->warning('Could not reach TVDB API. Running in local mode only!', true);
                $this->local = true;
            }

            if ($this->token !== '') {
                $this->client->setToken($this->token);
            }
        }
    }
}
