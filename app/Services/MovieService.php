<?php

namespace App\Services;

use aharen\OMDbAPI;
use App\Models\Category;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Models\Settings;
use App\Services\TvProcessing\Providers\TraktProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service class for movie data fetching and processing.
 */
class MovieService
{
    protected const MATCH_PERCENT = 75;

    protected const YEAR_MATCH_PERCENT = 80;

    protected string $currentTitle = '';

    protected string $currentYear = '';

    protected string $currentRelID = '';

    protected string $showPasswords;

    protected ReleaseImageService $releaseImage;

    protected Client $client;

    protected string $lookuplanguage;

    public FanartTvService $fanart;

    public ?string $fanartapikey;

    public ?string $omdbapikey;

    public bool $imdburl;

    public int $movieqty;

    public bool $echooutput;

    public string $imgSavePath;

    public string $service;

    public ?TraktProvider $traktTv = null;

    public ?OMDbAPI $omdbApi = null;

    protected ?string $traktcheck;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->releaseImage = new ReleaseImageService;
        $this->traktcheck = config('nntmux_api.trakttv_api_key');
        if ($this->traktcheck !== null) {
            $this->traktTv = new TraktProvider();
        }
        $this->client = new Client;
        $this->fanartapikey = config('nntmux_api.fanarttv_api_key');
        $this->fanart = new FanartTvService($this->fanartapikey);
        $this->omdbapikey = config('nntmux_api.omdb_api_key');
        if ($this->omdbapikey !== null) {
            $this->omdbApi = new OMDbAPI($this->omdbapikey);
        }

        $this->lookuplanguage = Settings::settingValue('imdblanguage') !== '' ? (string) Settings::settingValue('imdblanguage') : 'en';
        $cacheDir = storage_path('framework/cache/imdb_cache');
        if (! File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0777, false, true);
        }

        $this->imdburl = (int) Settings::settingValue('imdburl') !== 0;
        $this->movieqty = Settings::settingValue('maximdbprocessed') !== '' ? (int) Settings::settingValue('maximdbprocessed') : 100;
        $this->showPasswords = app(\App\Services\Releases\ReleaseBrowseService::class)->showPasswords();

        $this->echooutput = config('nntmux.echocli');
        $this->imgSavePath = storage_path('covers/movies/');
        $this->service = '';
    }

    /**
     * Get movie info by IMDB ID.
     */
    public function getMovieInfo(?string $imdbId): ?MovieInfo
    {
        if ($imdbId === null || $imdbId === '' || $imdbId === '0000000') {
            return null;
        }

        return MovieInfo::query()->where('imdbid', $imdbId)->first();
    }

    /**
     * Get trailer using IMDB Id.
     *
     * @throws \Exception
     * @throws GuzzleException
     */
    public function getTrailer(int $imdbId): string|false
    {
        $trailer = MovieInfo::query()->where('imdbid', $imdbId)->where('trailer', '<>', '')->first(['trailer']);
        if ($trailer !== null) {
            return $trailer['trailer'];
        }

        if ($this->traktcheck !== null) {
            $data = $this->traktTv->client->getMovieSummary('tt'.$imdbId, 'full');
            if (($data !== false) && ! empty($data['trailer'])) {
                return $data['trailer'];
            }
        }

        $trailer = imdb_trailers($imdbId);
        if ($trailer) {
            MovieInfo::query()->where('imdbid', $imdbId)->update(['trailer' => $trailer]);

            return $trailer;
        }

        return false;
    }

    /**
     * Parse trakt info, insert into DB.
     */
    public function parseTraktTv(array &$data): mixed
    {
        if (empty($data['ids']['imdb'])) {
            return false;
        }

        if (! empty($data['trailer'])) {
            $data['trailer'] = str_ireplace(
                ['watch?v=', 'http://'],
                ['embed/', 'https://'],
                $data['trailer']
            );
        }
        $imdbId = (str_starts_with($data['ids']['imdb'], 'tt')) ? substr($data['ids']['imdb'], 2) : $data['ids']['imdb'];
        $cover = 0;
        if (File::isFile($this->imgSavePath.$imdbId.'-cover.jpg')) {
            $cover = 1;
        }

        return $this->update([
            'genre' => implode(', ', $data['genres']),
            'imdbid' => $this->checkTraktValue($imdbId),
            'language' => $this->checkTraktValue($data['language']),
            'plot' => $this->checkTraktValue($data['overview']),
            'rating' => $this->checkTraktValue($data['rating']),
            'tagline' => $this->checkTraktValue($data['tagline']),
            'title' => $this->checkTraktValue($data['title']),
            'tmdbid' => $this->checkTraktValue($data['ids']['tmdb']),
            'traktid' => $this->checkTraktValue($data['ids']['trakt']),
            'trailer' => $this->checkTraktValue($data['trailer']),
            'cover' => $cover,
            'year' => $this->checkTraktValue($data['year']),
        ]);
    }

    private function checkTraktValue(mixed $value): mixed
    {
        if (\is_array($value) && ! empty($value)) {
            $temp = '';
            foreach ($value as $val) {
                if (! is_array($val) && ! is_object($val)) {
                    $temp .= $val;
                }
            }
            $value = $temp;
        }

        return ! empty($value) ? $value : '';
    }

    /**
     * Get array of column keys, for inserting / updating.
     */
    public function getColumnKeys(): array
    {
        return [
            'actors', 'backdrop', 'cover', 'director', 'genre', 'imdbid', 'language',
            'plot', 'rating', 'rtrating', 'tagline', 'title', 'tmdbid', 'traktid', 'trailer', 'type', 'year',
        ];
    }

    /**
     * Choose the first non-empty variable from up to five inputs.
     */
    protected function setVariables(string|array $variable1, string|array $variable2, string|array $variable3, string|array $variable4, string|array $variable5 = ''): array|string
    {
        if (! empty($variable1)) {
            return $variable1;
        }
        if (! empty($variable2)) {
            return $variable2;
        }
        if (! empty($variable3)) {
            return $variable3;
        }
        if (! empty($variable4)) {
            return $variable4;
        }
        if (! empty($variable5)) {
            return $variable5;
        }

        return '';
    }

    /**
     * Update movie on movie-edit page.
     */
    public function update(array $values): bool
    {
        if (! count($values)) {
            return false;
        }

        $query = [];
        $onDuplicateKey = ['created_at' => now()];
        $found = 0;
        foreach ($values as $key => $value) {
            if (! empty($value)) {
                $found++;
                if (\in_array($key, ['genre', 'language'], false)) {
                    $value = substr($value, 0, 64);
                }
                $query += [$key => $value];
                $onDuplicateKey += [$key => $value];
            }
        }
        if (! $found) {
            return false;
        }
        foreach ($query as $key => $value) {
            $query[$key] = rtrim($value, ', ');
        }

        MovieInfo::upsert($query, ['imdbid'], $onDuplicateKey);

        // Always attempt to fetch a missing cover if imdbid present and cover not provided.
        if (! empty($query['imdbid'])) {
            $imdbIdForCover = $query['imdbid'];
            $coverProvided = array_key_exists('cover', $values) && ! empty($values['cover']);
            if (! $coverProvided && ! $this->hasCover($imdbIdForCover)) {
                if ($this->fetchAndSaveCoverOnly($imdbIdForCover)) {
                    MovieInfo::query()->where('imdbid', $imdbIdForCover)->update(['cover' => 1]);
                }
            }
        }

        return true;
    }

    /**
     * Fetch IMDB/TMDB/TRAKT/OMDB/iTunes info for the movie.
     *
     * @throws \Exception
     */
    public function updateMovieInfo(string $imdbId): bool
    {
        if ($this->echooutput && $this->service !== '') {
            cli()->primary('Fetching IMDB info from TMDB/IMDB/Trakt/OMDB/iTunes using IMDB id: '.$imdbId);
        }

        // Check TMDB for IMDB info.
        $tmdb = $this->fetchTMDBProperties($imdbId);

        // Check IMDB for movie info.
        $imdb = $this->fetchIMDBProperties($imdbId);

        // Check TRAKT for movie info
        $trakt = $this->fetchTraktTVProperties($imdbId);

        // Check OMDb for movie info
        $omdb = $this->fetchOmdbAPIProperties($imdbId);

        if (! $imdb && ! $tmdb && ! $trakt && ! $omdb) {
            return false;
        }

        // Check FanArt.tv for cover and background images.
        $fanart = $this->fetchFanartTVProperties($imdbId);

        $mov = [];

        $mov['cover'] = $mov['backdrop'] = $mov['banner'] = 0;
        $mov['type'] = $mov['director'] = $mov['actors'] = $mov['language'] = '';

        $mov['imdbid'] = $imdbId;
        $mov['tmdbid'] = (! isset($tmdb['tmdbid']) || $tmdb['tmdbid'] === '') ? 0 : $tmdb['tmdbid'];
        $mov['traktid'] = (! isset($trakt['id']) || $trakt['id'] === '') ? 0 : $trakt['id'];

        // Prefer Fanart.tv cover over TMDB,TMDB over IMDB,IMDB over OMDB and OMDB over iTunes.
        if (! empty($fanart['cover'])) {
            try {
                $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $fanart['cover'], $this->imgSavePath);
                if ($mov['cover'] === 0) {
                    Log::warning('Failed to save FanartTV cover for '.$imdbId.' from URL: '.$fanart['cover']);
                }
            } catch (\Throwable $e) {
                Log::error('Error saving FanartTV cover for '.$imdbId.': '.$e->getMessage());
                $mov['cover'] = 0;
            }
        }

        if ($mov['cover'] === 0 && ! empty($tmdb['cover'])) {
            try {
                $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $tmdb['cover'], $this->imgSavePath);
                if ($mov['cover'] === 0) {
                    Log::warning('Failed to save TMDB cover for '.$imdbId.' from URL: '.$tmdb['cover']);
                }
            } catch (\Throwable $e) {
                Log::error('Error saving TMDB cover for '.$imdbId.': '.$e->getMessage());
                $mov['cover'] = 0;
            }
        }

        if ($mov['cover'] === 0 && ! empty($imdb['cover'])) {
            try {
                $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $imdb['cover'], $this->imgSavePath);
                if ($mov['cover'] === 0) {
                    Log::warning('Failed to save IMDB cover for '.$imdbId.' from URL: '.$imdb['cover']);
                }
            } catch (\Throwable $e) {
                Log::error('Error saving IMDB cover for '.$imdbId.': '.$e->getMessage());
                $mov['cover'] = 0;
            }
        }

        if ($mov['cover'] === 0 && ! empty($omdb['cover'])) {
            try {
                $mov['cover'] = $this->releaseImage->saveImage($imdbId.'-cover', $omdb['cover'], $this->imgSavePath);
                if ($mov['cover'] === 0) {
                    Log::warning('Failed to save OMDB cover for '.$imdbId.' from URL: '.$omdb['cover']);
                }
            } catch (\Throwable $e) {
                Log::error('Error saving OMDB cover for '.$imdbId.': '.$e->getMessage());
                $mov['cover'] = 0;
            }
        }

        // Backdrops.
        if (! empty($fanart['backdrop'])) {
            try {
                $mov['backdrop'] = $this->releaseImage->saveImage($imdbId.'-backdrop', $fanart['backdrop'], $this->imgSavePath, 1920, 1024);
            } catch (\Throwable $e) {
                Log::warning('Error saving FanartTV backdrop for '.$imdbId.': '.$e->getMessage());
                $mov['backdrop'] = 0;
            }
        }

        if ($mov['backdrop'] === 0 && ! empty($tmdb['backdrop'])) {
            try {
                $mov['backdrop'] = $this->releaseImage->saveImage($imdbId.'-backdrop', $tmdb['backdrop'], $this->imgSavePath, 1920, 1024);
            } catch (\Throwable $e) {
                Log::warning('Error saving TMDB backdrop for '.$imdbId.': '.$e->getMessage());
                $mov['backdrop'] = 0;
            }
        }

        // Banner
        if (! empty($fanart['banner'])) {
            try {
                $mov['banner'] = $this->releaseImage->saveImage($imdbId.'-banner', $fanart['banner'], $this->imgSavePath);
            } catch (\Throwable $e) {
                Log::warning('Error saving FanartTV banner for '.$imdbId.': '.$e->getMessage());
                $mov['banner'] = 0;
            }
        }

        // RottenTomatoes rating from OmdbAPI
        if ($omdb !== false && ! empty($omdb['rtRating'])) {
            $mov['rtrating'] = $omdb['rtRating'];
        }

        $mov['title'] = $this->setVariables($imdb['title'] ?? '', $tmdb['title'] ?? '', $trakt['title'] ?? '', $omdb['title'] ?? '');
        $mov['rating'] = $this->setVariables($imdb['rating'] ?? '', $tmdb['rating'] ?? '', $trakt['rating'] ?? '', $omdb['rating'] ?? '');
        $mov['plot'] = $this->setVariables($imdb['plot'] ?? '', $tmdb['plot'] ?? '', $trakt['overview'] ?? '', $omdb['plot'] ?? '');
        $mov['tagline'] = $this->setVariables($imdb['tagline'] ?? '', $tmdb['tagline'] ?? '', $trakt['tagline'] ?? '', $omdb['tagline'] ?? '');
        $mov['year'] = $this->setVariables($imdb['year'] ?? '', $tmdb['year'] ?? '', $trakt['year'] ?? '', $omdb['year'] ?? '');
        $mov['genre'] = $this->setVariables($imdb['genre'] ?? '', $tmdb['genre'] ?? '', $trakt['genres'] ?? '', $omdb['genre'] ?? '');

        if (! empty($imdb['type'])) {
            $mov['type'] = $imdb['type'];
        }

        if (! empty($imdb['director'])) {
            $mov['director'] = \is_array($imdb['director']) ? implode(', ', array_unique($imdb['director'])) : $imdb['director'];
        } elseif (! empty($omdb['director'])) {
            $mov['director'] = \is_array($omdb['director']) ? implode(', ', array_unique($omdb['director'])) : $omdb['director'];
        } elseif (! empty($tmdb['director'])) {
            $mov['director'] = \is_array($tmdb['director']) ? implode(', ', array_unique($tmdb['director'])) : $tmdb['director'];
        }

        if (! empty($imdb['actors'])) {
            $mov['actors'] = \is_array($imdb['actors']) ? implode(', ', array_unique($imdb['actors'])) : $imdb['actors'];
        } elseif (! empty($omdb['actors'])) {
            $mov['actors'] = \is_array($omdb['actors']) ? implode(', ', array_unique($omdb['actors'])) : $omdb['actors'];
        } elseif (! empty($tmdb['actors'])) {
            $mov['actors'] = \is_array($tmdb['actors']) ? implode(', ', array_unique($tmdb['actors'])) : $tmdb['actors'];
        }

        if (! empty($imdb['language'])) {
            $mov['language'] = \is_array($imdb['language']) ? implode(', ', array_unique($imdb['language'])) : $imdb['language'];
        } elseif (! empty($omdb['language']) && ! is_bool($omdb['language'])) {
            $mov['language'] = \is_array($omdb['language']) ? implode(', ', array_unique($omdb['language'])) : $omdb['language'];
        }

        if (\is_array($mov['genre'])) {
            $mov['genre'] = implode(', ', array_unique($mov['genre']));
        }

        if (\is_array($mov['type'])) {
            $mov['type'] = implode(', ', array_unique($mov['type']));
        }

        $mov['title'] = html_entity_decode($mov['title'], ENT_QUOTES, 'UTF-8');

        $mov['title'] = str_replace(['/', '\\'], '', $mov['title']);
        $movieID = $this->update([
            'actors' => html_entity_decode($mov['actors'], ENT_QUOTES, 'UTF-8'),
            'backdrop' => $mov['backdrop'],
            'cover' => $mov['cover'],
            'director' => html_entity_decode($mov['director'], ENT_QUOTES, 'UTF-8'),
            'genre' => html_entity_decode($mov['genre'], ENT_QUOTES, 'UTF-8'),
            'imdbid' => $mov['imdbid'],
            'language' => html_entity_decode($mov['language'], ENT_QUOTES, 'UTF-8'),
            'plot' => html_entity_decode(preg_replace('/\s+See full summary »/u', ' ', $mov['plot']), ENT_QUOTES, 'UTF-8'),
            'rating' => round((int) $mov['rating'], 1),
            'rtrating' => $mov['rtrating'] ?? 'N/A',
            'tagline' => html_entity_decode($mov['tagline'], ENT_QUOTES, 'UTF-8'),
            'title' => $mov['title'],
            'tmdbid' => $mov['tmdbid'],
            'traktid' => $mov['traktid'],
            'type' => html_entity_decode(ucwords(preg_replace('/[._]/', ' ', $mov['type'])), ENT_QUOTES, 'UTF-8'),
            'year' => $mov['year'],
        ]);

        // After updating, if cover flag is still 0 but file now exists (race condition), update DB.
        if ($mov['cover'] === 0 && $this->hasCover($imdbId)) {
            MovieInfo::query()->where('imdbid', $imdbId)->update(['cover' => 1]);
        }

        if ($this->echooutput && $this->service !== '') {
            PHP_EOL.cli()->headerOver('Added/updated movie: ').
            cli()->primary(
                $mov['title'].
                ' ('.
                $mov['year'].
                ') - '.
                $mov['imdbid']
            );
        }

        return $movieID;
    }

    /**
     * Fetch FanArt.tv backdrop / cover / title.
     */
    protected function fetchFanartTVProperties(string $imdbId): false|array
    {
        if (! $this->fanart->isConfigured()) {
            return false;
        }

        try {
            $result = $this->fanart->getMovieProperties($imdbId);

            if ($result !== null) {
                if ($this->echooutput) {
                    cli()->info('Fanart found '.$result['title']);
                }

                return $result;
            }
        } catch (\Throwable $e) {
            Log::warning('FanartTV API error for '.$imdbId.': '.$e->getMessage());
        }

        return false;
    }

    /**
     * Fetch movie information from TMDB using an IMDB ID.
     */
    public function fetchTMDBProperties(string $imdbId, bool $text = false): array|false
    {
        $lookupId = $text === false && (strlen($imdbId) === 7 || strlen($imdbId) === 8) ? 'tt'.$imdbId : $imdbId;

        $cacheKey = 'tmdb_movie_'.md5($lookupId);
        $expiresAt = now()->addDays(7);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $tmdbClient = app(TmdbClient::class);

            if (! $tmdbClient->isConfigured()) {
                return false;
            }

            $tmdbLookup = $tmdbClient->getMovie($lookupId, ['credits']);

            if ($tmdbLookup === null || empty($tmdbLookup)) {
                Cache::put($cacheKey, false, $expiresAt);

                return false;
            }

            $title = TmdbClient::getString($tmdbLookup, 'title');
            if ($this->currentTitle !== '' && ! empty($title)) {
                similar_text($this->currentTitle, $title, $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, $expiresAt);

                    return false;
                }
            }

            $releaseDate = TmdbClient::getString($tmdbLookup, 'release_date');
            if ($this->currentYear !== '' && ! empty($releaseDate)) {
                $tmdbYear = Carbon::parse($releaseDate)->year;

                similar_text($this->currentYear, (string) $tmdbYear, $percent);
                if ($percent < self::YEAR_MATCH_PERCENT) {
                    Cache::put($cacheKey, false, $expiresAt);

                    return false;
                }
            }

            $imdbIdFromResponse = TmdbClient::getString($tmdbLookup, 'imdb_id');
            $ret = [
                'title' => $title,
                'tmdbid' => TmdbClient::getInt($tmdbLookup, 'id'),
                'imdbid' => str_replace('tt', '', $imdbIdFromResponse),
                'rating' => '',
                'actors' => '',
                'director' => '',
                'plot' => TmdbClient::getString($tmdbLookup, 'overview'),
                'tagline' => TmdbClient::getString($tmdbLookup, 'tagline'),
                'year' => '',
                'genre' => '',
                'cover' => '',
                'backdrop' => '',
            ];

            $vote = TmdbClient::getFloat($tmdbLookup, 'vote_average');
            if ($vote > 0) {
                $ret['rating'] = $vote;
            }

            $credits = TmdbClient::getArray($tmdbLookup, 'credits');
            $cast = TmdbClient::getArray($credits, 'cast');
            if (! empty($cast)) {
                $actors = [];
                foreach ($cast as $member) {
                    if (is_array($member) && ! empty($member['name'])) {
                        $actors[] = $member['name'];
                    }
                }
                if (! empty($actors)) {
                    $ret['actors'] = $actors;
                }
            }

            $crew = TmdbClient::getArray($credits, 'crew');
            foreach ($crew as $crewMember) {
                if (! is_array($crewMember)) {
                    continue;
                }
                $department = TmdbClient::getString($crewMember, 'department');
                $job = TmdbClient::getString($crewMember, 'job');
                if ($department === 'Directing' && $job === 'Director') {
                    $ret['director'] = TmdbClient::getString($crewMember, 'name');
                    break;
                }
            }

            if (! empty($releaseDate)) {
                $ret['year'] = Carbon::parse($releaseDate)->year;
            }

            $genresa = TmdbClient::getArray($tmdbLookup, 'genres');
            if (! empty($genresa)) {
                $genres = [];
                foreach ($genresa as $genre) {
                    if (is_array($genre) && ! empty($genre['name'])) {
                        $genres[] = $genre['name'];
                    }
                }
                if (! empty($genres)) {
                    $ret['genre'] = $genres;
                }
            }

            $posterPath = TmdbClient::getString($tmdbLookup, 'poster_path');
            if (! empty($posterPath)) {
                $ret['cover'] = 'https://image.tmdb.org/t/p/original'.$posterPath;
            }

            $backdropPath = TmdbClient::getString($tmdbLookup, 'backdrop_path');
            if (! empty($backdropPath)) {
                $ret['backdrop'] = 'https://image.tmdb.org/t/p/original'.$backdropPath;
            }

            if ($this->echooutput) {
                cli()->info('TMDb found '.$ret['title']);
            }

            Cache::put($cacheKey, $ret, $expiresAt);

            return $ret;

        } catch (\Throwable $e) {
            Log::warning('TMDB API error for '.$lookupId.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from IMDB.
     */
    public function fetchIMDBProperties(string $imdbId): array|false
    {
        $cacheKey = 'imdb_movie_'.md5($imdbId);
        $expiresAt = now()->addDays(7);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        try {
            $scraper = app(ImdbScraper::class);
            $scraped = $scraper->fetchById($imdbId);
            if ($scraped === false || empty($scraped['title'])) {
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }
            if (! empty($this->currentTitle)) {
                similar_text($this->currentTitle, $scraped['title'], $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }
                if (! empty($this->currentYear) && ! empty($scraped['year'])) {
                    similar_text($this->currentYear, $scraped['year'], $yearPercent);
                    if ($yearPercent < self::YEAR_MATCH_PERCENT) {
                        Cache::put($cacheKey, false, now()->addHours(6));

                        return false;
                    }
                }
            }
            Cache::put($cacheKey, $scraped, $expiresAt);
            if ($this->echooutput) {
                cli()->info('IMDb scraped '.$scraped['title']);
            }

            return $scraped;
        } catch (\Throwable $e) {
            Log::warning('IMDb scrape error for '.$imdbId.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from Trakt.tv using IMDB ID.
     *
     * @throws GuzzleException
     */
    public function fetchTraktTVProperties(string $imdbId): array|false
    {
        if ($this->traktcheck === null) {
            return false;
        }

        $cacheKey = 'trakt_movie_'.md5($imdbId);
        $expiresAt = now()->addDays(7);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $resp = $this->traktTv->client->getMovieSummary('tt'.$imdbId, 'full');

            if ($resp === false || empty($resp['title'])) {
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            if (! empty($this->currentTitle)) {
                similar_text($this->currentTitle, $resp['title'], $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }
            }

            if (! empty($this->currentYear) && ! empty($resp['year'])) {
                similar_text($this->currentYear, $resp['year'], $percent);
                if ($percent < self::YEAR_MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }
            }

            $movieData = [
                'id' => $resp['ids']['trakt'] ?? null,
                'title' => $resp['title'],
                'overview' => $resp['overview'] ?? '',
                'tagline' => $resp['tagline'] ?? '',
                'year' => $resp['year'] ?? '',
                'genres' => $resp['genres'] ?? '',
                'rating' => $resp['rating'] ?? '',
                'votes' => $resp['votes'] ?? 0,
                'language' => $resp['language'] ?? '',
                'runtime' => $resp['runtime'] ?? 0,
                'trailer' => $resp['trailer'] ?? '',
            ];

            if ($this->echooutput) {
                cli()->info('Trakt found '.$movieData['title']);
            }

            Cache::put($cacheKey, $movieData, $expiresAt);

            return $movieData;

        } catch (\Throwable $e) {
            Log::warning('Trakt API error for '.$imdbId.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from OMDB API using IMDB ID.
     */
    public function fetchOmdbAPIProperties(string $imdbId): array|false
    {
        if ($this->omdbapikey === null) {
            return false;
        }

        $cacheKey = 'omdb_movie_'.md5($imdbId);
        $expiresAt = now()->addDays(7);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $resp = $this->omdbApi->fetch('i', 'tt'.$imdbId);

            if (! is_object($resp) ||
                $resp->message !== 'OK' ||
                Str::contains($resp->data->Response, 'Error:') ||
                $resp->data->Response === 'False') {

                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            if (! empty($this->currentTitle)) {
                similar_text($this->currentTitle, $resp->data->Title, $percent);
                if ($percent < self::MATCH_PERCENT) {
                    Cache::put($cacheKey, false, now()->addHours(6));

                    return false;
                }

                if (! empty($this->currentYear)) {
                    similar_text($this->currentYear, $resp->data->Year, $percent);
                    if ($percent < self::YEAR_MATCH_PERCENT) {
                        Cache::put($cacheKey, false, now()->addHours(6));

                        return false;
                    }
                }
            }

            $rtRating = '';
            if (isset($resp->data->Ratings) && is_array($resp->data->Ratings) && count($resp->data->Ratings) > 1) {
                $rtRating = $resp->data->Ratings[1]->Value ?? '';
            }

            $movieData = [
                'title' => $resp->data->Title ?? '',
                'cover' => $resp->data->Poster ?? '',
                'genre' => $resp->data->Genre ?? '',
                'year' => $resp->data->Year ?? '',
                'plot' => $resp->data->Plot ?? '',
                'rating' => $resp->data->imdbRating ?? '',
                'rtRating' => $rtRating,
                'tagline' => $resp->data->Tagline ?? '',
                'director' => $resp->data->Director ?? '',
                'actors' => $resp->data->Actors ?? '',
                'language' => $resp->data->Language ?? '',
                'boxOffice' => $resp->data->BoxOffice ?? '',
            ];

            if ($this->echooutput) {
                cli()->info('OMDbAPI Found '.$movieData['title']);
            }

            Cache::put($cacheKey, $movieData, $expiresAt);

            return $movieData;

        } catch (\Throwable $e) {
            Log::warning('OMDB API error for '.$imdbId.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Update a release with an IMDB ID and related movie information.
     *
     * @throws \Exception
     */
    public function doMovieUpdate(string $buffer, string $service, int $id, int $processImdb = 1): string|false
    {
        $existingImdbId = Release::query()->where('id', $id)->value('imdbid');
        if ($existingImdbId !== null && $existingImdbId !== '' && $existingImdbId !== '0000000') {
            return $existingImdbId;
        }

        $imdbId = false;
        if (preg_match('/(?:imdb.*?)?(?:tt|Title\?)(?P<imdbid>\d{5,8})/i', $buffer, $hits)) {
            $imdbId = $hits['imdbid'];
        }

        if ($imdbId !== false) {
            try {
                $this->service = $service;
                if ($this->echooutput && $this->service !== '') {
                    cli()->info($this->service.' found IMDBid: tt'.$imdbId);
                }

                $movieInfoId = MovieInfo::query()->where('imdbid', $imdbId)->first(['id']);

                Release::query()->where('id', $id)->update([
                    'imdbid' => $imdbId,
                    'movieinfo_id' => $movieInfoId !== null ? $movieInfoId['id'] : null,
                ]);

                if ($processImdb === 1) {
                    $movCheck = $this->getMovieInfo($imdbId);
                    $thirtyDaysInSeconds = 30 * 24 * 60 * 60;

                    if ($movCheck === null ||
                        (isset($movCheck['updated_at']) &&
                            (time() - strtotime($movCheck['updated_at'])) > $thirtyDaysInSeconds)) {

                        $info = $this->updateMovieInfo($imdbId);

                        if ($info === false) {
                            Release::query()->where('id', $id)->update(['imdbid' => '0000000']);
                        } elseif ($info === true) {
                            $freshMovieInfo = MovieInfo::query()->where('imdbid', $imdbId)->first(['id']);

                            Release::query()->where('id', $id)->update([
                                'movieinfo_id' => $freshMovieInfo !== null ? $freshMovieInfo['id'] : null,
                            ]);
                        }
                    }
                }

                return $imdbId;
            } catch (\Exception $e) {
                Log::error('Error updating movie information: '.$e->getMessage());

                return false;
            }
        }

        return $imdbId;
    }

    /**
     * Process releases with no IMDB IDs by looking up movie information from various sources.
     *
     * @throws \Exception
     * @throws GuzzleException
     */
    public function processMovieReleases(string $groupID = '', string $guidChar = '', int $lookupIMDB = 1): void
    {
        if ($lookupIMDB === 0) {
            return;
        }

        $query = Release::query()
            ->select(['searchname', 'id'])
            ->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])
            ->whereNull('imdbid');

        if ($groupID !== '') {
            $query->where('groups_id', $groupID);
        }

        if ($guidChar !== '') {
            $query->where('leftguid', $guidChar);
        }

        if ((int) $lookupIMDB === 2) {
            $query->where('isrenamed', '=', 1);
        }

        $res = $query->orderByDesc('id')->limit($this->movieqty)->get();

        $movieCount = count($res);
        $failedIDs = [];

        if ($movieCount > 0) {
            if ($this->echooutput && $movieCount > 1) {
                cli()->header('Processing '.$movieCount.' movie releases.');
            }

            foreach ($res as $arr) {
                if (! $this->parseMovieSearchName($arr['searchname'])) {
                    $failedIDs[] = $arr['id'];

                    continue;
                }

                $this->currentRelID = $arr['id'];
                $movieName = $this->formatMovieName();

                if ($this->echooutput) {
                    cli()->info('Looking up: '.$movieName);
                }

                $foundIMDB = $this->searchLocalDatabase($arr['id']) ||
                    $this->searchIMDb($arr['id']) ||
                    $this->searchOMDbAPI($arr['id']) ||
                    $this->searchTraktTV($arr['id'], $movieName) ||
                    $this->searchTMDB($arr['id']);

                if ($foundIMDB) {
                    if ($this->echooutput) {
                        cli()->primary('Successfully updated release with IMDB ID');
                    }

                    continue;
                } else {
                    $releaseCheck = Release::query()->where('id', $arr['id'])->whereNotNull('imdbid')->exists();
                    if ($releaseCheck) {
                        if ($this->echooutput) {
                            cli()->info('Release already has IMDB ID, skipping');
                        }

                        continue;
                    }
                }

                $failedIDs[] = $arr['id'];
            }

            if (! empty($failedIDs)) {
                if ($this->echooutput) {
                    $failedReleases = Release::query()
                        ->select(['id', 'searchname'])
                        ->whereIn('id', $failedIDs)
                        ->get();

                    cli()->header('Failed to find IMDB IDs for '.count($failedIDs).' releases:');
                    foreach ($failedReleases as $release) {
                        cli()->error("ID: {$release->id} - {$release->searchname}");
                    }
                }

                foreach (array_chunk($failedIDs, 100) as $chunk) {
                    Release::query()->whereIn('id', $chunk)->update(['imdbid' => '0000000']);
                }
            }
        }
    }

    private function formatMovieName(): string
    {
        $movieName = $this->currentTitle;
        if ($this->currentYear !== '') {
            $movieName .= ' ('.$this->currentYear.')';
        }

        return $movieName;
    }

    private function searchLocalDatabase(int $releaseId): bool
    {
        $getIMDBid = $this->localIMDBSearch();
        if ($getIMDBid === false) {
            return false;
        }

        $imdbId = $this->doMovieUpdate('tt'.$getIMDBid, 'Local DB', $releaseId);

        return $imdbId !== false;
    }

    private function searchIMDb(int $releaseId): bool
    {
        try {
            $scraper = app(ImdbScraper::class);
            $matches = $scraper->search($this->currentTitle);
            foreach ($matches as $match) {
                $title = $match['title'] ?? '';
                if ($title === '') {
                    continue;
                }
                similar_text($title, $this->currentTitle, $percent);
                if ($percent < self::MATCH_PERCENT) {
                    continue;
                }
                if (! empty($this->currentYear) && ! empty($match['year'])) {
                    similar_text($this->currentYear, $match['year'], $yearPercent);
                    if ($yearPercent < self::YEAR_MATCH_PERCENT) {
                        continue;
                    }
                }
                $imdbId = $this->doMovieUpdate('tt'.$match['imdbid'], 'IMDb(scrape)', $releaseId);
                if ($imdbId !== false) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('IMDb scraper search failed: '.$e->getMessage());
        }

        return false;
    }

    private function searchOMDbAPI(int $releaseId): bool
    {
        if ($this->omdbapikey === null) {
            return false;
        }

        $omdbTitle = strtolower(str_replace(' ', '_', $this->currentTitle));

        try {
            $buffer = $this->currentYear !== ''
                ? $this->omdbApi->search($omdbTitle, 'movie', $this->currentYear)
                : $this->omdbApi->search($omdbTitle, 'movie');

            if (! is_object($buffer) ||
                $buffer->message !== 'OK' ||
                Str::contains($buffer->data->Response, 'Error:') ||
                $buffer->data->Response !== 'True' ||
                empty($buffer->data->Search[0]->imdbID)) {
                return false;
            }

            $getIMDBid = $buffer->data->Search[0]->imdbID;
            $imdbId = $this->doMovieUpdate($getIMDBid, 'OMDbAPI', $releaseId);

            return $imdbId !== false;

        } catch (\Exception $e) {
            Log::error('OMDb API error: '.$e->getMessage());

            return false;
        }
    }

    private function searchTraktTV(int $releaseId, string $movieName): bool
    {
        if ($this->traktcheck === null) {
            return false;
        }

        try {
            $data = $this->traktTv->client->getMovieSummary($movieName, 'full');
            if ($data === false || empty($data['ids']['imdb'])) {
                return false;
            }

            $this->parseTraktTv($data);
            $imdbId = $this->doMovieUpdate($data['ids']['imdb'], 'Trakt', $releaseId);

            return $imdbId !== false;

        } catch (\Exception $e) {
            Log::error('Trakt.tv error: '.$e->getMessage());

            return false;
        }
    }

    private function searchTMDB(int $releaseId): bool
    {
        try {
            $tmdbClient = app(TmdbClient::class);

            if (! $tmdbClient->isConfigured()) {
                return false;
            }

            $data = $tmdbClient->searchMovies($this->currentTitle);

            if ($data === null || empty($data['total_results']) || empty($data['results'])) {
                return false;
            }

            $results = TmdbClient::getArray($data, 'results');
            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $resultId = TmdbClient::getInt($result, 'id');
                $releaseDate = TmdbClient::getString($result, 'release_date');

                if ($resultId === 0 || empty($releaseDate)) {
                    continue;
                }

                similar_text(
                    $this->currentYear,
                    (string) Carbon::parse($releaseDate)->year,
                    $percent
                );

                if ($percent < self::YEAR_MATCH_PERCENT) {
                    continue;
                }

                $ret = $this->fetchTMDBProperties((string) $resultId, true);
                if ($ret === false || empty($ret['imdbid'])) {
                    continue;
                }

                $imdbId = $this->doMovieUpdate('tt'.$ret['imdbid'], 'TMDB', $releaseId);
                if ($imdbId !== false) {
                    return true;
                }
            }

        } catch (\Throwable $e) {
            Log::warning('TMDB API error: '.$e->getMessage());
        }

        return false;
    }

    protected function localIMDBSearch(): string|false
    {
        if (empty($this->currentTitle)) {
            return false;
        }

        $cacheKey = 'local_imdb_'.md5($this->currentTitle.$this->currentYear);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $query = MovieInfo::query()
            ->select(['imdbid', 'title'])
            ->where('title', 'like', '%'.$this->currentTitle.'%');

        if (! empty($this->currentYear)) {
            $start = Carbon::createFromFormat('Y', $this->currentYear)->subYears(2)->year;
            $end = Carbon::createFromFormat('Y', $this->currentYear)->addYears(2)->year;
            $query->whereBetween('year', [$start, $end]);
        }

        $potentialMatches = $query->get();

        if ($potentialMatches->isEmpty()) {
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }

        foreach ($potentialMatches as $match) {
            similar_text($this->currentTitle, $match['title'], $percent);

            if ($percent >= self::MATCH_PERCENT) {
                Cache::put($cacheKey, $match['imdbid'], now()->addDays(7));

                if ($this->echooutput) {
                    cli()->info("Found local match: {$match['title']} ({$match['imdbid']})");
                }

                return $match['imdbid'];
            }
        }

        Cache::put($cacheKey, false, now()->addHours(6));

        return false;
    }

    protected function parseMovieSearchName(string $releaseName): bool
    {
        if (empty(trim($releaseName))) {
            return false;
        }

        $cacheKey = 'parse_movie_'.md5($releaseName);

        if (Cache::has($cacheKey)) {
            $result = Cache::get($cacheKey);
            if (is_array($result)) {
                $this->currentTitle = $result['title'];
                $this->currentYear = $result['year'];

                return true;
            }

            return false;
        }

        $name = $year = '';

        $followingList = '[^\w]((1080|480|720|2160)p|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR|UHD)(Rip)?|'
            .'BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?PROPER|RE(pack|Rip)|Sub\.?(fix|pack)|'
            .'Unrated|WEB-?DL|WEBRip|(x|H|HEVC)[ ._-]?26[45]|xvid|AAC|REMUX)[^\w]';

        if (preg_match('/(?P<name>[\w. -]+)[^\w](?P<year>(19|20)\d\d)/i', $releaseName, $hits)) {
            $name = $hits['name'];
            $year = $hits['year'];
        } elseif (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $hits)) {
            $name = $hits['name'];
        } elseif (preg_match('/^(?P<name>[\w .-]+?)'.$followingList.'/i', $releaseName, $hits)) {
            $name = $hits['name'];
        } elseif (strlen($releaseName) <= 100 && ! preg_match('/\.(rar|zip|avi|mkv|mp4)$/i', $releaseName)) {
            $name = $releaseName;
        }

        if (! empty($name)) {
            $name = preg_replace('/'.$followingList.'/i', ' ', $name);
            $name = preg_replace('/\([^)]*\)/i', ' ', $name);
            while (($openPos = strpos($name, '[')) !== false && ($closePos = strpos($name, ']', $openPos)) !== false) {
                $name = substr($name, 0, $openPos).' '.substr($name, $closePos + 1);
            }
            $name = str_replace(['.', '_'], ' ', $name);
            $name = preg_replace('/-[A-Z0-9].*$/i', '', $name);
            $name = trim(preg_replace('/\s{2,}/', ' ', $name));

            if (strlen($name) > 2 && ! preg_match('/^\d+$/', $name)) {
                $this->currentTitle = $name;
                $this->currentYear = $year;

                Cache::put($cacheKey, [
                    'title' => $name,
                    'year' => $year,
                ], now()->addDays(7));

                return true;
            }
        }

        Cache::put($cacheKey, false, now()->addHours(24));

        return false;
    }

    /**
     * Get IMDB genres.
     */
    public function getGenres(): array
    {
        return [
            'Action',
            'Adventure',
            'Animation',
            'Biography',
            'Comedy',
            'Crime',
            'Documentary',
            'Drama',
            'Family',
            'Fantasy',
            'Film-Noir',
            'Game-Show',
            'History',
            'Horror',
            'Music',
            'Musical',
            'Mystery',
            'News',
            'Reality-TV',
            'Romance',
            'Sci-Fi',
            'Sport',
            'Talk-Show',
            'Thriller',
            'War',
            'Western',
        ];
    }

    protected function hasCover(string $imdbId): bool
    {
        $record = MovieInfo::query()->select('cover')->where('imdbid', $imdbId)->first();
        $dbHas = $record !== null && (int) $record->cover === 1;
        $filePath = $this->imgSavePath.$imdbId.'-cover.jpg';
        $fileHas = File::isFile($filePath);

        return $dbHas || $fileHas;
    }

    protected function fetchAndSaveCoverOnly(string $imdbId): bool
    {
        try {
            $fanart = $this->fetchFanartTVProperties($imdbId);
            if (! empty($fanart['cover'])) {
                if ($this->releaseImage->saveImage($imdbId.'-cover', $fanart['cover'], $this->imgSavePath)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Fanart cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        try {
            $tmdb = $this->fetchTMDBProperties($imdbId);
            if (! empty($tmdb['cover'])) {
                if ($this->releaseImage->saveImage($imdbId.'-cover', $tmdb['cover'], $this->imgSavePath)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('TMDB cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        try {
            $imdb = $this->fetchIMDBProperties($imdbId);
            if (! empty($imdb['cover'])) {
                if ($this->releaseImage->saveImage($imdbId.'-cover', $imdb['cover'], $this->imgSavePath)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('IMDB cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        try {
            $omdb = $this->fetchOmdbAPIProperties($imdbId);
            if (! empty($omdb['cover'])) {
                if ($this->releaseImage->saveImage($imdbId.'-cover', $omdb['cover'], $this->imgSavePath)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('OMDB cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        return false;
    }
}

