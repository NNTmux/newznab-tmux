<?php

namespace App\Services\TvProcessing\Providers;

use App\Services\FanartTvService;
use Blacklight\ReleaseImage;
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
    public function processSite($groupID, $guidChar, $process, bool $local = false): void
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
                        $this->colorCli->primaryOver('    → ');
                        $this->colorCli->alternateOver($this->truncateTitle($release['cleanname']));
                        $this->colorCli->primaryOver(' → ');
                        $this->colorCli->alternate('Skipped (previously failed)');
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
                        $this->colorCli->primaryOver('    → ');
                        $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                        $this->colorCli->primaryOver(' → ');
                        $this->colorCli->info('Searching TVDB...');
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
                    $this->colorCli->primaryOver('    → ');
                    $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                    $this->colorCli->primaryOver(' → ');
                    $this->colorCli->info('Found in DB');
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
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                            $this->colorCli->primaryOver(' → ');
                            $this->colorCli->primary('Full Season matched');
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
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                            if ($seriesNo !== '' && $episodeNo !== '') {
                                $this->colorCli->primaryOver(' S');
                                $this->colorCli->warningOver(sprintf('%02d', $seriesNo));
                                $this->colorCli->primaryOver('E');
                                $this->colorCli->warningOver(sprintf('%02d', $episodeNo));
                            } elseif ($hasAirdate) {
                                $this->colorCli->primaryOver(' | ');
                                $this->colorCli->warningOver($release['airdate']);
                            }
                            $this->colorCli->primaryOver(' ✓ ');
                            $this->colorCli->primary('MATCHED (TVDB)');
                        }
                        $matched++;
                    } else {
                        $this->setVideoIdFound($videoId, $row['id'], 0);
                        $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                        if ($this->echooutput) {
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->alternateOver($this->truncateTitle($release['cleanname']));
                            if ($hasAirdate) {
                                $this->colorCli->primaryOver(' | ');
                                $this->colorCli->warningOver($release['airdate']);
                            }
                            $this->colorCli->primaryOver(' → ');
                            $this->colorCli->warning('Episode not found');
                        }
                    }
                } else {
                    $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                    if ($this->echooutput) {
                        $this->colorCli->primaryOver('    → ');
                        $this->colorCli->alternateOver($this->truncateTitle($release['cleanname']));
                        $this->colorCli->primaryOver(' → ');
                        $this->colorCli->warning('Not found');
                    }
                }
            } else {
                $this->setVideoNotFound(parent::FAILED_PARSE, $row['id']);
                $this->titleCache[] = $release['cleanname'] ?? null;
                if ($this->echooutput) {
                    $this->colorCli->error(sprintf(
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
            $this->colorCli->primaryOver('  ✓ TVDB: ');
            $this->colorCli->primary(sprintf('%d matched, %d skipped', $matched, $skipped));
        }
    }

    public function getBanner($videoID, $siteId): bool
    {
        return false;
    }

    /**
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
            $this->colorCli->error('Show not found on TVDB');
        } catch (UnauthorizedException $e) {
            try {
                $this->authorizeTvdb();
            } catch (UnauthorizedException $error) {
                $this->colorCli->error('Not authorized to access TVDB');
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
        $ri = new ReleaseImage;
        $hasCover = 0;

        if (! empty($this->posterUrl)) {
            $hasCover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath);
            if ($hasCover === 1) {
                $this->setCoverFound($videoId);
            }
        }

        return $hasCover;
    }

    /**
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
                        $this->colorCli->error('Not authorized to access TVDB');
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
     * @throws ExceptionInterface
     * @throws ParseException
     */
    public function formatShowInfo($show): array
    {
        try {
            $poster = $this->client->series()->artworks($show->tvdb_id);
            $poster = collect($poster)->where('type', 2)->sortByDesc('score')->first();
            $this->posterUrl = ! empty($poster->image) ? $poster->image : '';
        } catch (ResourceNotFoundException $e) {
            $this->colorCli->error('Poster image not found on TVDB');
        } catch (UnauthorizedException $error) {
            try {
                $this->authorizeTvdb();
            } catch (UnauthorizedException $error) {
                $this->colorCli->error('Not authorized to access TVDB');
            }
        }

        try {
            $imdbId = $this->client->series()->extended($show->tvdb_id);
            preg_match('/tt(?P<imdbid>\d{6,9})$/i', $imdbId->getIMDBId(), $imdb);
        } catch (ResourceNotFoundException $e) {
            $this->colorCli->error('Show ImdbId not found on TVDB');
        } catch (\Exception) {
            $this->colorCli->error('Error on TVDB, aborting');
        }

        return [
            'type' => parent::TYPE_TV,
            'title' => $show->name,
            'summary' => $show->overview,
            'started' => $show->first_air_time,
            'publisher' => $imdbId->originalNetwork->name ?? '',
            'poster' => $this->posterUrl,
            'source' => parent::SOURCE_TVDB,
            'imdb' => $imdb['imdbid'] ?? 0,
            'tvdb' => $show->tvdb_id,
            'trakt' => 0,
            'tvrage' => 0,
            'tvmaze' => 0,
            'tmdb' => 0,
            'aliases' => ! empty($show->aliases) ? $show->aliases : '',
            'localzone' => "''",
        ];
    }

    public function formatEpisodeInfo($episode): array
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
            $this->colorCli->warning('TVDB API key or user pin not set. Running in local mode only!', true);
            $this->local = true;
        } else {
            try {
                $this->token = $this->client->authentication()->login(config('tvdb.api_key'), config('tvdb.user_pin'));
            } catch (UnauthorizedException $error) {
                $this->colorCli->warning('Could not reach TVDB API. Running in local mode only!', true);
                $this->local = true;
            }

            if ($this->token !== '') {
                $this->client->setToken($this->token);
            }
        }
    }
}

