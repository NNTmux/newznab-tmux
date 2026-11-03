<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Exceptions\TvProviderException;
use App\Services\TmdbClient;
use App\Support\MetadataSources;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ResourceNotFoundException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException;
use CanIHaveSomeCoffee\TheTVDbAPI\Model\SeriesExtendedRecord;
use CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Class TvdbProvider -- functions used to post process releases against TVDB (API v4).
 */
class TvdbProvider extends AbstractTvProvider
{
    public TheTVDbAPI $client;

    /**
     * @var string Authorization token for the TVDB API
     */
    public string $token = '';

    /** True when TVDB is unconfigured. */
    private bool $local = false;

    /** Seconds to stop logging in after TVDB rejected the credentials. */
    private const int REJECTED_LOGIN_PAUSE = 600;

    /** Unix time until which a rejected login isn't retried. */
    private int $loginRejectedUntil = 0;

    private bool $authorized = false;

    /**
     * One client per process: the library's RouteFactory caches its route objects
     * statically, so every route keeps using the first TheTVDbAPI instance it saw.
     */
    private static ?TheTVDbAPI $sharedClient = null;

    public function __construct(?TheTVDbAPI $client = null)
    {
        parent::__construct();
        $this->client = $client ?? self::$sharedClient ??= new TheTVDbAPI('eng', '', new Client([
            'base_uri' => TheTVDbAPI::API_BASE_URI,
            'timeout' => (int) config('tvdb.timeout', 30),
            'connect_timeout' => 10,
            'headers' => ['accept' => 'application/json'],
        ]));
        $this->local = ! MetadataSources::isAvailable(MetadataSources::TVDB);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name, ?int $year = null, string $country = ''): array|false
    {
        $name = (string) preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        // A wrong year (an episode title, a revival's year) filters the right show out,
        // so an unconvincing year-filtered search is retried without the year.
        $highest = null;
        if ($year !== null) {
            $highest = $this->bestSearchResult(
                $this->call(fn (TheTVDbAPI $client) => $client->search()->search($name, ['type' => 'series', 'year' => $year])),
                $name, $year, $country,
            );
        }
        if ($highest === null) {
            $highest = $this->bestSearchResult(
                $this->call(fn (TheTVDbAPI $client) => $client->search()->search($name, ['type' => 'series'])),
                $name, $year, $country,
            );
        }

        return $highest !== null ? $this->formatShowInfo($highest) : false;
    }

    /**
     * The highest scoring series of a search response, comparing its name, English
     * translation and aliases; null when nothing scores.
     */
    private function bestSearchResult(mixed $response, string $name, ?int $year, string $country): ?object
    {
        if (! \is_array($response)) {
            return null;
        }

        $highestScore = 0.0;
        $highest = null;
        foreach ($response as $show) {
            if (! $this->checkRequiredAttr($show, 'tvdbS')) {
                continue;
            }

            $names = [(string) $show->name, $this->englishName($show)];
            foreach (\is_array($show->aliases ?? null) ? $show->aliases : [] as $alias) {
                if (\is_string($alias)) {
                    $names[] = $alias;
                }
            }

            foreach (array_unique(array_filter($names)) as $candidateName) {
                $score = $this->scoreCandidate($name, $year, $candidateName, $show->first_air_time ?? ($show->year ?? null), $country, (string) ($show->country ?? ''));
                if ($score > $highestScore) {
                    $highestScore = $score;
                    $highest = $show;
                }
            }
        }

        return $highest;
    }

    /** The English title of a search result, or '' when TVDB has none. */
    private function englishName(object $show): string
    {
        $translations = \is_array($show->translations ?? null) ? $show->translations : [];

        return trim((string) ($translations['eng'] ?? ''));
    }

    public function resolveSiteId(array $knownIds): int
    {
        $tmdb = app(TmdbClient::class)->throwingOnTransientFailure();
        if ($knownIds['tmdb'] <= 0 || ! $tmdb->isConfigured()) {
            return 0;
        }

        return (int) ($tmdb->getTvExternalIds($knownIds['tmdb'])['tvdb_id'] ?? 0);
    }

    public function fetchAllEpisodes(int $siteId, int $videoId): int
    {
        if ($siteId <= 0 || $videoId <= 0) {
            return 0;
        }

        $episodes = $this->call(fn (TheTVDbAPI $client) => $client->series()->allEpisodes($siteId));

        // Shows without an English translation come back empty from the /eng listing.
        if ($episodes === [] || $episodes === null) {
            $series = $this->call(fn (TheTVDbAPI $client) => $client->series()->extended($siteId));
            if (\is_object($series) && ($series->originalLanguage ?? 'eng') !== 'eng') {
                $episodes = $this->call(fn (TheTVDbAPI $client) => $client->series()->allEpisodesOriginalLanguage($series));
            }
        }

        if (! \is_array($episodes)) {
            return 0;
        }

        $formatted = [];
        foreach ($episodes as $episode) {
            if ($this->checkRequiredAttr($episode, 'tvdbE')) {
                $formatted[] = $this->formatEpisodeInfo($episode);
            }
        }

        return $this->addEpisodes($videoId, $formatted);
    }

    public function supportsAirdateLookup(): bool
    {
        return true;
    }

    public function fetchEpisodesByAirdate(int $siteId, int $videoId, string $airdate): int
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $airdate);
        if ($siteId <= 0 || $videoId <= 0 || $date === false) {
            return 0;
        }

        $episodes = $this->call(fn (TheTVDbAPI $client) => $client->series()->episodes($siteId, 0, 0, 'default', '', -1, $date));
        if (! \is_array($episodes)) {
            return 0;
        }

        $formatted = [];
        foreach ($episodes as $episode) {
            if ($this->checkRequiredAttr($episode, 'tvdbE')) {
                $formatted[] = $this->formatEpisodeInfo($episode);
            }
        }

        return $this->addEpisodes($videoId, $formatted);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchEpisode(int $siteId, int $season, int $episode, int $videoId = 0): array|false
    {
        if ($siteId <= 0 || $season < 0 || $episode <= 0) {
            return false;
        }

        $episodes = $this->call(fn (TheTVDbAPI $client) => $client->series()->episodes($siteId, $season, 0, 'default', '', $episode));
        foreach (\is_array($episodes) ? $episodes : [] as $record) {
            if ($this->checkRequiredAttr($record, 'tvdbE') && (int) $record->seasonNumber === $season && (int) $record->number === $episode) {
                return $this->formatEpisodeInfo($record);
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        $this->posterUrl = '';
        $tvdbId = (int) $show->tvdb_id;

        // The search result already carries the series poster; only look it up without one.
        if (! empty($show->image_url)) {
            $this->posterUrl = (string) $show->image_url;
        } else {
            $artworks = $this->callQuietly(fn (TheTVDbAPI $client) => $client->series()->artworks($tvdbId));
            $poster = collect(\is_array($artworks) ? $artworks : [])->where('type', 2)->sortByDesc('score')->first();
            $this->posterUrl = ! empty($poster->image) ? (string) $poster->image : '';
        }

        // Store English titles; the original-language name stays findable as an alias.
        $originalName = (string) $show->name;
        $englishName = \is_object($show) ? $this->englishName($show) : '';
        $title = $englishName !== '' ? $englishName : $originalName;
        $aliases = \is_array($show->aliases ?? null) ? $show->aliases : [];
        if ($title !== $originalName) {
            $aliases[] = $originalName;
        }
        $overviews = \is_array($show->overviews ?? null) ? $show->overviews : [];
        $summary = (string) ($overviews['eng'] ?? ($show->overview ?? ''));

        $imdbId = '';
        $extended = $this->callQuietly(fn (TheTVDbAPI $client) => $client->series()->extended($tvdbId));
        if (\is_object($extended)) {
            preg_match('/tt(?P<imdbid>\d{6,})$/i', (string) ($extended->getIMDBId() ?? ''), $imdb);
            $imdbId = (string) ($imdb['imdbid'] ?? '');
        }

        $externalIds = $this->lookupExternalIds($tvdbId, $imdbId);

        return [
            'type' => parent::TYPE_TV,
            'title' => $title,
            'summary' => $summary,
            'started' => (string) ($show->first_air_time ?? ''),
            'publisher' => \is_object($extended) ? (string) ($extended->originalNetwork->name ?? '') : '',
            'country' => (string) ($show->country ?? ''),
            'poster' => $this->posterUrl,
            'source' => parent::SOURCE_TVDB,
            'imdb' => $imdbId,
            'tvdb' => $tvdbId,
            'trakt' => $externalIds['trakt'],
            'tvrage' => 0,
            'tvmaze' => 0,
            'tmdb' => $externalIds['tmdb'],
            'aliases' => $aliases !== [] ? $aliases : '',
            'localzone' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        return [
            'title' => (string) ($episode->name ?? ''),
            'series' => (int) $episode->seasonNumber,
            'episode' => (int) $episode->number,
            'se_complete' => 'S'.sprintf('%02d', $episode->seasonNumber).'E'.sprintf('%02d', $episode->number),
            'firstaired' => (string) ($episode->aired ?? ''),
            'summary' => (string) ($episode->overview ?? ''),
        ];
    }

    /**
     * The library client, logged in; null when TVDB is unconfigured or rejected the credentials.
     */
    public function authorizedClient(): ?TheTVDbAPI
    {
        try {
            return $this->authorize() ? $this->client : null;
        } catch (TvProviderException) {
            return null;
        }
    }

    /**
     * The extended record of a series, or null when TVDB has none.
     */
    public function seriesExtended(int $tvdbId): ?SeriesExtendedRecord
    {
        return $this->call(fn (TheTVDbAPI $client) => $client->series()->extended($tvdbId));
    }

    /**
     * Run a TVDB request: null when TVDB has nothing (404) or is unconfigured.
     *
     * Re-authorises once on 401. Every other failure (rate limit, server error, rejected
     * credentials, other 4xx, connection error) surfaces as TvProviderException, so the
     * release is retried later instead of being recorded as "show not found". Responses
     * the library can't parse stay a miss for that one request.
     *
     * @template T
     *
     * @param  callable(TheTVDbAPI): T  $request
     * @return T|null
     */
    private function call(callable $request): mixed
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if (! $this->authorize($attempt > 0)) {
                return null;
            }

            try {
                return $request($this->client);
            } catch (ResourceNotFoundException) {
                return null;
            } catch (UnauthorizedException) {
                $this->authorized = false;

                continue;
            } catch (GuzzleException $e) {
                throw new TvProviderException('tvdb', 'TVDB request failed: '.$e->getMessage(), null, null, $e);
            } catch (\Exception $e) {
                $status = self::statusOf($e);
                if ($status !== null) {
                    throw new TvProviderException('tvdb', $e->getMessage(), $status, null, $e);
                }

                Log::warning('TVDB response could not be read', ['error' => $e->getMessage()]);

                return null;
            }
        }

        throw new TvProviderException('tvdb', 'TVDB keeps answering 401 after a fresh login', 401, self::REJECTED_LOGIN_PAUSE);
    }

    /** The HTTP status the library puts in its generic exception message, if any. */
    private static function statusOf(\Exception $e): ?int
    {
        return preg_match('/Got status code (\d{3})/', $e->getMessage(), $status) === 1 ? (int) $status[1] : null;
    }

    /**
     * Like call(), for optional enrichment: transient failures are logged instead of thrown.
     *
     * @template T
     *
     * @param  callable(TheTVDbAPI): T  $request
     * @return T|null
     */
    private function callQuietly(callable $request): mixed
    {
        try {
            return $this->call($request);
        } catch (TvProviderException $e) {
            Log::info('TVDB enrichment request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Log in on first use, or again when $force is set after a 401.
     */
    private function authorize(bool $force = false): bool
    {
        if ($this->local) {
            return false;
        }

        if ($this->authorized && ! $force) {
            return true;
        }

        $pauseLeft = $this->loginRejectedUntil - time();
        if ($pauseLeft > 0) {
            throw new TvProviderException('tvdb', 'TVDB rejected the configured credentials', 401, $pauseLeft);
        }

        $pin = config('tvdb.user_pin');

        try {
            $this->token = $this->client->authentication()->login(
                (string) config('tvdb.api_key'),
                \is_string($pin) && $pin !== '' ? $pin : null,
            );
        } catch (UnauthorizedException|ResourceNotFoundException $e) {
            // Not a reason to call every show "not found": the releases wait and retry.
            Log::warning('TVDB rejected the configured credentials; TVDB lookups pause and are retried later.', ['error' => $e->getMessage()]);
            $this->loginRejectedUntil = time() + self::REJECTED_LOGIN_PAUSE;

            throw new TvProviderException('tvdb', 'TVDB rejected the configured credentials', 401, self::REJECTED_LOGIN_PAUSE, $e);
        } catch (GuzzleException $e) {
            throw new TvProviderException('tvdb', 'TVDB login failed: '.$e->getMessage(), null, null, $e);
        } catch (\Exception $e) {
            throw new TvProviderException('tvdb', 'TVDB login failed: '.$e->getMessage(), self::statusOf($e), null, $e);
        }

        $this->client->setToken($this->token);
        $this->authorized = true;

        return true;
    }
}
