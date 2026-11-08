<?php

declare(strict_types=1);

namespace App\Services;

use aharen\OMDbAPI;
use App\Enums\ImageAssetProfile;
use App\Facades\Search;
use App\Models\Category;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\TvProcessing\Providers\TraktProvider;
use App\Support\ReleaseSearchIndexSync;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /** Lower threshold when year matches exactly; allows matching by alternate title (e.g. "Girl Cops" vs primary). */
    protected const MATCH_PERCENT_ALT_TITLE = 55;

    /** Threshold for search results when the release name has no year to confirm the match. */
    protected const MATCH_PERCENT_NO_YEAR = 85;

    /** Normalized titles this short only match exactly ("Pi" must not match "Pig"). */
    private const SHORT_TITLE_LENGTH = 3;

    /** Shortest local title accepted as a word prefix of a longer release title. */
    private const PREFIX_MATCH_MIN_LENGTH = 5;

    private const TMDB_SEARCH_RESULT_LIMIT = 5;

    private const IMDB_SEARCH_CANDIDATE_LIMIT = 3;

    private const MAX_ALTERNATE_TITLES = 3;

    /**
     * Source, resolution and codec tags. The release year must come before the first of these.
     */
    private const SOURCE_TAG_PATTERN = '(?:(?:480|540|576|720|1080|2160|4320)[pi]|4K|Blu-?Ray|BD(?:Rip|Remux)|BRRip|DVD(?:Rip|Scr|R|9|5)?|HDRip|WEB[ -]?(?:DL|Rip)|HDTV|PDTV|UHD|REMUX|[xh][ .-]?26[45]|HEVC|AVC|AV1|XviD|DivX|E?AC3D?|DTS(?:-HD)?|AAC(?:2)?|DD[P+]?\d?|TrueHD|Atmos|HDCAM|TELESYNC)';

    /**
     * Language and edition tags. A title without a year ends at the first of these or of the source tags.
     */
    private const EDITION_TAG_PATTERN = '(?:German|Deutsch|MULTi|FRENCH|TRUEFRENCH|iTA|SUBBED|DUBBED|UNCUT|UNRATED|EXTENDED|REMASTERED|RESTORED|COMPLETE|REPACK|PROPER|iNTERNAL|LiMiTED|Doku|DOCU|HDR(?:10\+?)?|DoVi|10bit|HSBS|AmazonHD)';

    /**
     * Tags removed from the end of a parsed title ("Armand German" → "Armand").
     */
    private const TRAILING_TAG_PATTERN = '(?:German|Deutsch|DL|MULTi|FRENCH|TRUEFRENCH|iTA|ENG|SUBBED|DUBBED|Line[ ]Dubbed|UNCUT|UNRATED|EXTENDED|REMASTERED|RESTORED|THEATRICAL|IMAX|OPEN[ ]MATTE|DC|Directors[ ]Cut|COMPLETE|REPACK|PROPER|iNTERNAL|LiMiTED|Doku|DOCU|DUAL|3D|PAL|NTSC)';

    protected string $currentTitle = '';

    protected string $currentYear = '';

    /**
     * Other titles found in the release name, tried when the main title finds nothing
     * ("Starman - Uzayli Yildiz Adam" → "Starman", "Uzayli Yildiz Adam").
     *
     * @var list<string>
     */
    protected array $alternateTitles = [];

    /** Whether the release name is marked German, so searches may restore umlauts ("traegt" → "trägt"). */
    protected bool $currentIsGerman = false;

    /**
     * Set when a metadata fetcher rejects a candidate IMDb id because its
     * title or year does not match the release currently being processed.
     */
    protected bool $candidateMismatch = false;

    protected string $currentRelID = '';

    protected string $currentParsedImdbId = '';

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
        $traktApiKey = trim((string) config('nntmux_api.trakttv_api_key', ''));
        $this->traktcheck = $traktApiKey !== '' ? $traktApiKey : null;
        if ($this->traktcheck !== null) {
            $this->traktTv = new TraktProvider;
        }
        $this->client = new Client;
        $fanartApiKey = trim((string) config('nntmux_api.fanarttv_api_key', ''));
        $this->fanartapikey = $fanartApiKey !== '' ? $fanartApiKey : null;
        $this->fanart = new FanartTvService($this->fanartapikey);
        $omdbApiKey = trim((string) config('nntmux_api.omdb_api_key', ''));
        $this->omdbapikey = $omdbApiKey !== '' ? $omdbApiKey : null;
        if ($this->omdbapikey !== null) {
            $this->omdbApi = new OMDbAPI($this->omdbapikey);
        }

        $this->lookuplanguage = app(ConfigurationProvider::class)->metadata()->movieLanguage !== '' ? (string) app(ConfigurationProvider::class)->metadata()->movieLanguage : 'en';
        $cacheDir = storage_path('framework/cache/imdb_cache');
        if (! File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0777, false, true);
        }

        $this->imdburl = (int) app(ConfigurationProvider::class)->metadata()->imdbAlternateUrl !== 0;
        $this->movieqty = app(ConfigurationProvider::class)->metadata()->maxMoviesProcessed !== '' ? (int) app(ConfigurationProvider::class)->metadata()->maxMoviesProcessed : 100;
        $this->showPasswords = app(ReleaseBrowseService::class)->showPasswords();

        $this->echooutput = config('nntmux.echocli');
        $this->imgSavePath = storage_path('covers/movies/');
        $this->service = '';
    }

    /**
     * Get movie info by IMDB ID.
     */
    public function getMovieInfo(int|string|null $imdbId): ?MovieInfo
    {
        $sanitized = $this->sanitizeImdbId($imdbId);
        if ($sanitized === null) {
            return null;
        }

        return MovieInfo::query()->where('imdbid', $sanitized)->first();
    }

    /**
     * Get trailer using IMDB Id.
     *
     * @throws \Exception
     * @throws GuzzleException
     */
    public function getTrailer(int|string|null $imdbId): string|false
    {
        $sanitized = $this->sanitizeImdbId($imdbId);
        if ($sanitized === null) {
            return false;
        }

        $trailer = MovieInfo::query()->where('imdbid', $sanitized)->where('trailer', '<>', '')->first(['trailer']);
        if ($trailer !== null) {
            return $trailer['trailer'];
        }

        if ($this->traktcheck !== null) {
            $data = $this->traktTv->client->getMovieSummary('tt'.$sanitized, 'full');
            if ($data !== null && ! empty($data['trailer'])) {
                return $data['trailer'];
            }
        }

        $trailer = imdb_trailers($sanitized);
        if ($trailer) {
            MovieInfo::query()->where('imdbid', $sanitized)->update(['trailer' => $trailer]);

            return $trailer;
        }

        return false;
    }

    /**
     * Sanitize an IMDB ID to its raw numeric string, preserving leading zeros.
     * Returns null if the ID is empty or all zeros.
     */
    private function sanitizeImdbId(int|string|null $imdbId): ?string
    {
        if ($imdbId === null || $imdbId === '') {
            return null;
        }

        $sanitizedImdbId = preg_replace('/\D/', '', trim((string) $imdbId));
        if ($sanitizedImdbId === null || $sanitizedImdbId === '' || ! imdb_id_is_valid($sanitizedImdbId)) {
            return null;
        }

        return $sanitizedImdbId;
    }

    /**
     * Parse trakt info, insert into DB.
     *
     * @param  array<string, mixed>  $data
     */
    public function parseTraktTv(array &$data): mixed
    {
        if (empty($data['ids']['imdb'])) {
            return false;
        }

        $data['trailer'] = $this->traktTrailerUrl($data['trailer'] ?? '');
        $imdbId = (string) $data['ids']['imdb'];
        $imdbId = str_starts_with($imdbId, 'tt') ? substr($imdbId, 2) : $imdbId;
        $genres = $data['genres'] ?? [];
        $genre = is_array($genres) ? implode(', ', $genres) : (string) $genres;
        $cover = 0;
        if ($this->releaseImage->imageExists($this->imgSavePath, $imdbId.'-cover')) {
            $cover = 1;
        }

        return $this->update([
            'genre' => $genre,
            'imdbid' => $this->checkTraktValue($imdbId),
            'language' => $this->checkTraktValue($data['language'] ?? ''),
            'plot' => $this->checkTraktValue($data['overview'] ?? ''),
            'rating' => $this->checkTraktValue($data['rating'] ?? ''),
            'tagline' => $this->checkTraktValue($data['tagline'] ?? ''),
            'title' => $this->checkTraktValue($data['title'] ?? ''),
            'tmdbid' => $this->checkTraktValue($data['ids']['tmdb'] ?? 0),
            'traktid' => $this->checkTraktValue($data['ids']['trakt'] ?? 0),
            'trailer' => $data['trailer'],
            'cover' => $cover,
            'year' => $this->checkTraktValue($data['year'] ?? ''),
        ]);
    }

    /**
     * Trakt's YouTube trailer link as an embeddable https URL.
     */
    private function traktTrailerUrl(mixed $trailer): string
    {
        if (! is_string($trailer) || $trailer === '') {
            return '';
        }

        return str_ireplace(['watch?v=', 'http://'], ['embed/', 'https://'], $trailer);
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
     *
     * @return array<int, string>
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
     *
     * @param  array<string, mixed>  $variable1
     * @param  array<string, mixed>  $variable2
     * @param  array<string, mixed>  $variable3
     * @param  array<string, mixed>  $variable4
     * @param  array<string, mixed>  $variable5
     * @return array<string, mixed>
     */
    protected function setVariables(string|array|int|float $variable1, string|array|int|float $variable2, string|array|int|float $variable3, string|array|int|float $variable4, string|array|int|float $variable5 = ''): array|string
    {
        if (! empty($variable1)) {
            return is_array($variable1) ? $variable1 : (string) $variable1;
        }
        if (! empty($variable2)) {
            return is_array($variable2) ? $variable2 : (string) $variable2;
        }
        if (! empty($variable3)) {
            return is_array($variable3) ? $variable3 : (string) $variable3;
        }
        if (! empty($variable4)) {
            return is_array($variable4) ? $variable4 : (string) $variable4;
        }
        if (! empty($variable5)) {
            return is_array($variable5) ? $variable5 : (string) $variable5;
        }

        return '';
    }

    /**
     * Update movie on movie-edit page.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): bool
    {
        if (! count($values)) {
            return false;
        }

        $query = [];
        foreach ($values as $key => $value) {
            if (! empty($value)) {
                if (\in_array($key, ['genre', 'language'], true)) {
                    $value = substr((string) $value, 0, 64);
                }
                $query[$key] = rtrim((string) $value, ', ');
            }
        }
        if ($query === [] || empty($query['imdbid'])) {
            return false;
        }

        $imdbId = $query['imdbid'];
        unset($query['imdbid']);

        // Saved through Eloquent so MovieInfoObserver keeps the movie search index in sync.
        $movie = MovieInfo::query()->firstOrNew(['imdbid' => $imdbId]);
        $movie->fill($query);
        if ($movie->exists && ! $movie->isDirty()) {
            // Unchanged data still counts as refreshed, or it is fetched again for every release.
            $movie->touch();
        } else {
            try {
                $movie->save();
            } catch (UniqueConstraintViolationException) {
                // Another worker inserted the same movie first.
                $movie = MovieInfo::query()->where('imdbid', $imdbId)->firstOrFail();
                $movie->fill($query)->save();
            }
        }

        $this->linkReleasesToMovieInfo($imdbId, (int) $movie->id);

        // Always attempt to fetch a missing cover if cover not provided.
        $coverProvided = array_key_exists('cover', $values) && ! empty($values['cover']);
        if (! $coverProvided && ! $this->hasCover($imdbId)) {
            // The cover pass re-reads cached provider data and must not change this release's match verdict.
            $candidateMismatch = $this->candidateMismatch;
            if ($this->fetchAndSaveCoverOnly($imdbId)) {
                MovieInfo::query()->where('imdbid', $imdbId)->update(['cover' => 1]);
            }
            $this->candidateMismatch = $candidateMismatch;
        }

        return true;
    }

    /**
     * Point releases that already carry this IMDb id but no movieinfo row at it.
     */
    private function linkReleasesToMovieInfo(string $imdbId, int $movieInfoId): void
    {
        if ($movieInfoId <= 0) {
            return;
        }

        $waitingForLink = static function ($query) use ($imdbId): void {
            $query->where('imdbid', $imdbId)
                ->where(function ($query): void {
                    $query->whereNull('movieinfo_id')->orWhere('movieinfo_id', 0);
                });
        };

        $releaseIds = Release::query()->where($waitingForLink)->pluck('id');

        if ($releaseIds->isEmpty()) {
            return;
        }

        // Re-check the conditions in the update: another worker may have re-matched a release
        // to a different movie since the select, and that link must not be overwritten.
        $updated = Release::query()
            ->whereIn('id', $releaseIds)
            ->where($waitingForLink)
            ->update(['movieinfo_id' => $movieInfoId]);

        if ($updated > 0) {
            ReleaseSearchIndexSync::forIds(
                Release::query()->whereIn('id', $releaseIds)->where('movieinfo_id', $movieInfoId)->pluck('id')
            );
        }
    }

    /**
     * Fetch IMDB/TMDB/TRAKT/OMDB/iTunes info for the movie.
     *
     * @phpstan-impure
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
                $mov['cover'] = $this->saveRemoteAsset($imdbId.'-cover', $fanart['cover']);
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
                $mov['cover'] = $this->saveRemoteAsset($imdbId.'-cover', $tmdb['cover']);
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
                $mov['cover'] = $this->saveRemoteAsset($imdbId.'-cover', $imdb['cover']);
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
                $mov['cover'] = $this->saveRemoteAsset($imdbId.'-cover', $omdb['cover']);
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
                $mov['backdrop'] = $this->saveRemoteAsset($imdbId.'-backdrop', $fanart['backdrop'], ImageAssetProfile::Backdrop);
            } catch (\Throwable $e) {
                Log::warning('Error saving FanartTV backdrop for '.$imdbId.': '.$e->getMessage());
                $mov['backdrop'] = 0;
            }
        }

        if ($mov['backdrop'] === 0 && ! empty($tmdb['backdrop'])) {
            try {
                $mov['backdrop'] = $this->saveRemoteAsset($imdbId.'-backdrop', $tmdb['backdrop'], ImageAssetProfile::Backdrop);
            } catch (\Throwable $e) {
                Log::warning('Error saving TMDB backdrop for '.$imdbId.': '.$e->getMessage());
                $mov['backdrop'] = 0;
            }
        }

        // Banner
        if (! empty($fanart['banner'])) {
            try {
                $mov['banner'] = $this->saveRemoteAsset($imdbId.'-banner', $fanart['banner']);
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
            'rating' => is_numeric($mov['rating']) ? round((float) $mov['rating'], 1) : '',
            'rtrating' => $mov['rtrating'] ?? 'N/A',
            'tagline' => html_entity_decode($mov['tagline'], ENT_QUOTES, 'UTF-8'),
            'title' => $mov['title'],
            'tmdbid' => $mov['tmdbid'],
            'traktid' => $mov['traktid'],
            'trailer' => $this->traktTrailerUrl($trakt['trailer'] ?? ''),
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
     *
     * @return array<string, mixed>
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
     * Fetch movie information from TMDB by IMDb id, or by TMDB id when $text is true.
     *
     * The provider data is cached as is and checked against the current release on every
     * read, so one release's rejection never hides the movie from another release.
     *
     * @return array<string, mixed>|false
     */
    public function fetchTMDBProperties(string $imdbId, bool $text = false): array|false
    {
        $movie = $this->fetchTmdbMovieData($text ? $imdbId : 'tt'.preg_replace('/^tt/i', '', $imdbId));

        if ($movie === false || ! $this->matchesCurrentContext($movie['known_titles'], $movie['year'])) {
            return false;
        }

        if ($this->echooutput) {
            cli()->info('TMDb found '.$movie['title']);
        }

        return $movie;
    }

    /**
     * TMDB movie details with every known title (original, alternative, translated),
     * cached under both the TMDB id and the IMDb id so the lookup and the later
     * metadata refresh share one request.
     *
     * @return array<string, mixed>|false
     */
    private function fetchTmdbMovieData(string $lookupId): array|false
    {
        $cacheKey = 'tmdb_movie_v2_'.$lookupId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) || $cached === false) {
            return $cached;
        }

        $tmdbClient = app(TmdbClient::class);
        if (! $tmdbClient->isConfigured()) {
            return false;
        }

        try {
            $tmdbLookup = $tmdbClient->getMovie($lookupId, ['credits', 'alternative_titles', 'translations']); // @phpstan-ignore argument.type
        } catch (\Throwable $e) {
            Log::warning('TMDB API error for '.$lookupId.': '.$e->getMessage());
            $tmdbLookup = null;
        }

        if (empty($tmdbLookup)) {
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }

        $movie = $this->mapTmdbMovie($tmdbLookup);
        $expiresAt = now()->addDays(7);

        Cache::put($cacheKey, $movie, $expiresAt);
        Cache::put('tmdb_movie_v2_'.$movie['tmdbid'], $movie, $expiresAt);
        if ($movie['imdbid'] !== '') {
            Cache::put('tmdb_movie_v2_tt'.$movie['imdbid'], $movie, $expiresAt);
        }

        return $movie;
    }

    /**
     * @param  array<string, mixed>  $tmdbLookup
     * @return array<string, mixed>
     */
    private function mapTmdbMovie(array $tmdbLookup): array
    {
        $title = TmdbClient::getString($tmdbLookup, 'title');
        $releaseDate = TmdbClient::getString($tmdbLookup, 'release_date');

        $knownTitles = [$title, TmdbClient::getString($tmdbLookup, 'original_title')];
        foreach (TmdbClient::getArray(TmdbClient::getArray($tmdbLookup, 'alternative_titles'), 'titles') as $alternative) {
            if (is_array($alternative)) {
                $knownTitles[] = (string) ($alternative['title'] ?? '');
            }
        }
        foreach (TmdbClient::getArray(TmdbClient::getArray($tmdbLookup, 'translations'), 'translations') as $translation) {
            if (is_array($translation) && is_array($translation['data'] ?? null)) {
                $knownTitles[] = (string) ($translation['data']['title'] ?? '');
            }
        }

        $ret = [
            'title' => $title,
            'known_titles' => array_values(array_unique(array_filter($knownTitles, static fn (string $known): bool => $known !== ''))),
            'tmdbid' => TmdbClient::getInt($tmdbLookup, 'id'),
            'imdbid' => str_replace('tt', '', TmdbClient::getString($tmdbLookup, 'imdb_id')),
            'rating' => '',
            'actors' => '',
            'director' => '',
            'plot' => TmdbClient::getString($tmdbLookup, 'overview'),
            'tagline' => TmdbClient::getString($tmdbLookup, 'tagline'),
            'year' => preg_match('/^\d{4}/', $releaseDate) ? (int) substr($releaseDate, 0, 4) : '',
            'genre' => '',
            'cover' => '',
            'backdrop' => '',
        ];

        $vote = TmdbClient::getFloat($tmdbLookup, 'vote_average');
        if ($vote > 0) {
            $ret['rating'] = $vote;
        }

        $credits = TmdbClient::getArray($tmdbLookup, 'credits');
        $actors = [];
        foreach (TmdbClient::getArray($credits, 'cast') as $member) {
            if (is_array($member) && ! empty($member['name'])) {
                $actors[] = $member['name'];
            }
        }
        if ($actors !== []) {
            $ret['actors'] = $actors;
        }

        foreach (TmdbClient::getArray($credits, 'crew') as $crewMember) {
            if (is_array($crewMember)
                && TmdbClient::getString($crewMember, 'department') === 'Directing'
                && TmdbClient::getString($crewMember, 'job') === 'Director') {
                $ret['director'] = TmdbClient::getString($crewMember, 'name');
                break;
            }
        }

        $genres = [];
        foreach (TmdbClient::getArray($tmdbLookup, 'genres') as $genre) {
            if (is_array($genre) && ! empty($genre['name'])) {
                $genres[] = $genre['name'];
            }
        }
        if ($genres !== []) {
            $ret['genre'] = $genres;
        }

        $posterPath = TmdbClient::getString($tmdbLookup, 'poster_path');
        if ($posterPath !== '') {
            $ret['cover'] = 'https://image.tmdb.org/t/p/original'.$posterPath;
        }

        $backdropPath = TmdbClient::getString($tmdbLookup, 'backdrop_path');
        if ($backdropPath !== '') {
            $ret['backdrop'] = 'https://image.tmdb.org/t/p/original'.$backdropPath;
        }

        return $ret;
    }

    /**
     * Fetch movie information from IMDB.
     *
     * @return array<string, mixed>
     */
    public function fetchIMDBProperties(string $imdbId): array|false
    {
        $cacheKey = 'imdb_movie_'.md5($imdbId);
        $expiresAt = now()->addDays(7);
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if ($cached === false) {
                return false;
            }

            return $this->matchesCurrentContext($cached['known_titles'] ?? $cached['title'] ?? '', $cached['year'] ?? '') ? $cached : false;
        }
        try {
            $scraper = app(ImdbScraper::class);
            if (! $scraper->isEnabled()) {
                return false;
            }
            $scraped = $scraper->fetchById($imdbId);
            if ($scraped === false && $scraper->wasSkipped()) {
                return false;
            }
            if ($scraped === false || empty($scraped['title'])) {
                // Blocks, timeouts and server errors are retried soon; only a real miss is cached for hours.
                $transient = $scraper->wasBlockedByWaf() || $scraper->wasTransientFailure();
                $ttl = $transient ? now()->addMinutes(30) : now()->addHours(6);

                $failureReason = $scraper->getLastFailureReason() ?? 'unknown';
                $fallbackFailureReason = $scraper->getLastFallbackFailureReason();

                if (! $scraper->wasBlockedByWaf()) {
                    Log::warning('IMDb metadata fetch failed.', [
                        'imdb_id' => $imdbId,
                        'reason' => $failureReason,
                        'fallback_reason' => $fallbackFailureReason,
                        'source' => $scraper->getLastFetchSource(),
                        'negative_cache' => $transient ? '30m' : '6h',
                    ]);
                }

                if ($this->echooutput) {
                    $message = 'IMDb fetch failed ['.$failureReason;
                    if ($fallbackFailureReason !== null) {
                        $message .= ' -> '.$fallbackFailureReason;
                    }
                    $message .= '] for tt'.$imdbId;

                    cli()->warning($message);
                }

                Cache::put($cacheKey, false, $ttl);

                return false;
            }
            // Cache the raw metadata regardless of the current context; the title/year
            // check is per-release and must not poison the cache for other releases.
            Cache::put($cacheKey, $scraped, $expiresAt);

            if (! $this->matchesCurrentContext($scraped['known_titles'] ?? $scraped['title'], $scraped['year'] ?? '')) {
                return false;
            }
            if ($this->echooutput) {
                $sourceLabel = match ($scraper->getLastFetchSource()) {
                    'imdbapi_dev' => 'IMDb fallback (imdbapi.dev)',
                    'imdb_html' => 'IMDb scrape',
                    default => 'IMDb',
                };

                cli()->info($sourceLabel.' found '.$scraped['title']);
            }

            return $scraped;
        } catch (\Throwable $e) {
            Log::warning('IMDb scrape error for '.$imdbId.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addMinutes(30));

            return false;
        }
    }

    /**
     * Fetch movie information from Trakt.tv using IMDB ID.
     *
     * @return array<string, mixed>
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
            $cached = Cache::get($cacheKey);
            if ($cached === false) {
                return false;
            }

            return $this->matchesCurrentContext($cached['title'] ?? '', $cached['year'] ?? '') ? $cached : false;
        }

        try {
            $resp = $this->traktTv->client->getMovieSummary('tt'.$imdbId, 'full');

            if ($resp === null || empty($resp['title'])) {
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
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

            Cache::put($cacheKey, $movieData, $expiresAt);

            if (! $this->matchesCurrentContext($movieData['title'], $movieData['year'])) {
                return false;
            }

            if ($this->echooutput) {
                cli()->info('Trakt found '.$movieData['title']);
            }

            return $movieData;

        } catch (\Throwable $e) {
            Log::warning('Trakt API error for '.$imdbId.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Fetch movie information from OMDB API using IMDB ID.
     *
     * The provider data is cached as is and checked against the current release on every read.
     *
     * @return array<string, mixed>|false
     */
    public function fetchOmdbAPIProperties(string $imdbId): array|false
    {
        if ($this->omdbapikey === null || $this->omdbApi === null) {
            return false;
        }

        $cacheKey = 'omdb_movie_v2_'.$imdbId;
        $movieData = Cache::get($cacheKey);
        if (! is_array($movieData) && $movieData !== false) {
            $movieData = $this->fetchOmdbMovieData($imdbId);
            Cache::put($cacheKey, $movieData, $movieData === false ? now()->addHours(6) : now()->addDays(7));
        }

        if ($movieData === false || ! $this->matchesCurrentContext($movieData['title'], $movieData['year'])) {
            return false;
        }

        if ($this->echooutput) {
            cli()->info('OMDbAPI Found '.$movieData['title']);
        }

        return $movieData;
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchOmdbMovieData(string $imdbId): array|false
    {
        try {
            $resp = $this->omdbApi?->fetch('i', 'tt'.$imdbId);
        } catch (\Throwable $e) {
            Log::warning('OMDB API error for '.$imdbId.': '.$e->getMessage());

            return false;
        }

        if (! is_object($resp) || ($resp->message ?? '') !== 'OK' || ($resp->data->Response ?? '') !== 'True') {
            return false;
        }

        $rtRating = '';
        foreach (is_array($resp->data->Ratings ?? null) ? $resp->data->Ratings : [] as $rating) {
            if (is_object($rating) && ($rating->Source ?? '') === 'Rotten Tomatoes') {
                $rtRating = (string) ($rating->Value ?? '');
                break;
            }
        }

        return [
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
    }

    /**
     * Update a release with an IMDB ID and related movie information.
     *
     * @throws \Exception
     */
    public function doMovieUpdate(string $buffer, string $service, int $id, int $processImdb = 1): string|false
    {
        $this->candidateMismatch = false;

        $existingImdbId = Release::query()->where('id', $id)->value('imdbid');
        if ($existingImdbId !== null && imdb_id_is_valid($existingImdbId)) {
            return $existingImdbId;
        }

        $imdbId = false;
        if (preg_match('/(?:imdb.*?)?(?:tt|Title\?)(?P<imdbid>\d{5,})/i', $buffer, $hits)) {
            $imdbId = $hits['imdbid'];
        }

        if ($imdbId !== false) {
            try {
                $this->service = $service;
                if ($this->echooutput && $this->service !== '') {
                    cli()->info($this->service.' found IMDBid: tt'.$imdbId);
                }

                if (! $this->candidateMatchesLocalInfo($imdbId)) {
                    if ($this->echooutput) {
                        cli()->warning('Rejected tt'.$imdbId.' for release '.$id.': local title/year mismatch');
                    }

                    return false;
                }

                if ($processImdb === 1) {
                    $movCheck = $this->getMovieInfo($imdbId);
                    $thirtyDaysInSeconds = 30 * 24 * 60 * 60;

                    if ($movCheck === null ||
                        (isset($movCheck['updated_at']) &&
                            (time() - strtotime((string) $movCheck['updated_at'])) > $thirtyDaysInSeconds)) {

                        $this->updateMovieInfo($imdbId);

                        // A fetcher recognised the candidate but rejected its title/year
                        // against this release: the IMDb id itself is wrong, so do not assign it.
                        if ($this->candidateMismatch) {
                            if ($this->echooutput) {
                                cli()->warning('Rejected tt'.$imdbId.' for release '.$id.': metadata title/year mismatch');
                            }

                            return false;
                        }
                    }
                }

                $movieInfoId = MovieInfo::query()->where('imdbid', $imdbId)->value('id');

                Release::query()->where('id', $id)->update([
                    'imdbid' => $imdbId,
                    'movieinfo_id' => $movieInfoId,
                ]);

                Search::updateRelease($id);

                if ($movieInfoId !== null) {
                    $this->linkReleasesToMovieInfo($imdbId, (int) $movieInfoId);
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
            ->where(function ($query): void {
                $query->whereNull('imdbid')
                    ->orWhereIn('imdbid', imdb_id_pending_values());
            });

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

        if ($res->isEmpty()) {
            return;
        }

        if ($this->echooutput && $res->count() > 1) {
            cli()->header('Processing '.$res->count().' movie releases.');
        }

        $failedCount = 0;
        foreach ($res as $arr) {
            $releaseId = (int) $arr['id'];
            $searchName = (string) $arr['searchname'];

            try {
                $found = $this->lookupReleaseImdb($releaseId, $searchName);
            } catch (\Throwable $e) {
                Log::warning('Movie lookup failed for release '.$releaseId.': '.$e->getMessage(), [
                    'release_id' => $releaseId,
                    'exception' => $e,
                ]);
                $found = false;
            }

            if ($found) {
                continue;
            }

            try {
                if ($this->markImdbLookupFailed($releaseId, $searchName)) {
                    $failedCount++;
                    if ($this->echooutput) {
                        cli()->error("No IMDB ID found for ID: {$releaseId} - {$searchName}");
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Could not mark movie lookup as failed for release '.$releaseId.': '.$e->getMessage());
            }
        }

        if ($this->echooutput && $failedCount > 0) {
            cli()->header('Failed to find IMDB IDs for '.$failedCount.' releases.');
        }
    }

    /**
     * Look up and store the IMDb ID for one release.
     *
     * Tries the main title first, then any alternate titles found in the release name.
     */
    private function lookupReleaseImdb(int $releaseId, string $searchName): bool
    {
        if (! $this->parseMovieSearchName($searchName)) {
            return false;
        }

        $this->currentRelID = (string) $releaseId;

        if ($this->currentParsedImdbId !== '') {
            if ($this->echooutput) {
                cli()->info('Found embedded IMDb id in search name: '.$this->currentParsedImdbId.' for '.$this->formatMovieName());
            }
            if ($this->doMovieUpdate($this->currentParsedImdbId, 'SearchName', $releaseId) !== false) {
                if ($this->echooutput) {
                    cli()->info('Updated release using embedded IMDb id');
                }

                return true;
            }
        }

        if ($this->currentTitle === '') {
            return false;
        }

        foreach ([$this->currentTitle, ...$this->alternateTitles] as $title) {
            $this->currentTitle = $title;
            $movieName = $this->formatMovieName();

            if ($this->echooutput) {
                cli()->info('Looking up: '.$movieName);
            }

            $found = $this->searchLocalDatabase($releaseId)
                || $this->searchIMDb($releaseId)
                || $this->searchOMDbAPI($releaseId)
                || $this->searchTraktTV($releaseId, $movieName)
                || $this->searchTMDB($releaseId);

            if ($found) {
                if ($this->echooutput) {
                    cli()->primary('Successfully updated release with IMDB ID');
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Mark a release's IMDb lookup as failed so it isn't looked up again.
     *
     * Skips a release that got an IMDb ID or was renamed in the meantime.
     */
    private function markImdbLookupFailed(int $releaseId, string $searchName): bool
    {
        $updated = Release::query()
            ->whereKey($releaseId)
            ->where('searchname', $searchName)
            ->where(function ($query): void {
                $query->whereNull('imdbid')
                    ->orWhereIn('imdbid', imdb_id_pending_values());
            })
            ->update(['imdbid' => '', 'movieinfo_id' => null]);

        if ($updated === 0) {
            return false;
        }

        ReleaseSearchIndexSync::forIds([$releaseId]);

        return true;
    }

    private function formatMovieName(): string
    {
        $movieName = $this->currentTitle;
        if ($this->currentYear !== '') {
            $movieName .= ' ('.$this->currentYear.')';
        }

        return $movieName;
    }

    /**
     * @throws \Exception
     */
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
            if (! $scraper->isScrapingEnabled()) {
                return false;
            }

            $candidates = [];
            foreach ($scraper->search($this->currentTitle) as $position => $match) {
                $title = $match['title'];
                if ($title === '' || $match['imdbid'] === '') {
                    continue;
                }

                if (! $this->isAcceptableSearchCandidate([$title], $match['year'])) {
                    continue;
                }

                $candidates[] = [
                    'imdbid' => $match['imdbid'],
                    'exactYear' => $this->yearDifference($match['year']) === 0,
                    'similarity' => $this->bestTitleSimilarity([$title]),
                    'position' => $position,
                ];
            }

            // Exact-year matches first, then the closest title, then IMDb's own ranking.
            usort($candidates, static fn (array $left, array $right): int => [$right['exactYear'], $right['similarity'], $left['position']]
                <=> [$left['exactYear'], $left['similarity'], $right['position']]);

            // Each attempt fetches full metadata, so stop after a few plausible candidates.
            foreach (array_slice($candidates, 0, self::IMDB_SEARCH_CANDIDATE_LIMIT) as $candidate) {
                if ($this->doMovieUpdate('tt'.$candidate['imdbid'], 'IMDb(scrape)', $releaseId) !== false) {
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
        if ($this->omdbapikey === null || $this->omdbApi === null) {
            return false;
        }

        $omdbTitle = strtolower(str_replace(' ', '_', $this->currentTitle));

        try {
            $results = $this->currentYear !== ''
                ? $this->omdbSearchResults($this->omdbApi->search($omdbTitle, 'movie', $this->currentYear))
                : [];

            if ($results === []) {
                $results = $this->omdbSearchResults($this->omdbApi->search($omdbTitle, 'movie'));
            }

            foreach ($results as $result) {
                $imdbId = (string) ($result->imdbID ?? '');
                if ($imdbId === '' || (isset($result->Type) && $result->Type !== 'movie')) {
                    continue;
                }

                if (! $this->isAcceptableSearchCandidate([(string) ($result->Title ?? '')], $result->Year ?? '')) {
                    continue;
                }

                if ($this->doMovieUpdate($imdbId, 'OMDbAPI', $releaseId) !== false) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::error('OMDb API error: '.$e->getMessage());
        }

        return false;
    }

    /**
     * The result rows of an OMDb search response, or an empty list when the search failed.
     *
     * @return list<object>
     */
    private function omdbSearchResults(mixed $buffer): array
    {
        if (! is_object($buffer)
            || ($buffer->message ?? '') !== 'OK'
            || ($buffer->data->Response ?? '') !== 'True'
            || ! is_array($buffer->data->Search ?? null)) {
            return [];
        }

        return array_values(array_filter($buffer->data->Search, 'is_object'));
    }

    private function searchTraktTV(int $releaseId, string $movieName): bool
    {
        if ($this->traktcheck === null || $this->traktTv === null) {
            return false;
        }

        try {
            $data = $this->traktTv->client->getMovieSummary($movieName, 'full');
            if ($data === null || empty($data['ids']['imdb'])) {
                return false;
            }

            if (! $this->isAcceptableSearchCandidate([(string) ($data['title'] ?? '')], $data['year'] ?? '')) {
                return false;
            }

            // doMovieUpdate builds the full movieinfo row from every provider once the match is accepted.
            return $this->doMovieUpdate((string) $data['ids']['imdb'], 'Trakt', $releaseId) !== false;
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

            foreach ($this->tmdbSearchQueries() as $query) {
                $results = $this->tmdbSearchResults($tmdbClient, $query);

                foreach (array_slice($results, 0, self::TMDB_SEARCH_RESULT_LIMIT) as $result) {
                    if (! is_array($result)) {
                        continue;
                    }

                    $resultId = TmdbClient::getInt($result, 'id');
                    if ($resultId === 0) {
                        continue;
                    }

                    // Cheap pre-filter on the search row before fetching full details.
                    $releaseDate = TmdbClient::getString($result, 'release_date');
                    $yearDifference = $this->yearDifference(substr($releaseDate, 0, 4));
                    if ($yearDifference !== null && $yearDifference > 1) {
                        continue;
                    }

                    $movie = $this->fetchTMDBProperties((string) $resultId, true);
                    if ($movie === false || empty($movie['imdbid'])) {
                        continue;
                    }

                    if (! $this->isAcceptableSearchCandidate($movie['known_titles'] ?? [$movie['title']], $movie['year'])) {
                        continue;
                    }

                    if ($this->doMovieUpdate('tt'.$movie['imdbid'], 'TMDB', $releaseId) !== false) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TMDB API error: '.$e->getMessage());
        }

        return false;
    }

    /**
     * TMDB search rows for a query, retried without the year filter when the release year finds nothing
     * (release groups often use the streaming year, TMDB the festival year).
     *
     * @return array<array-key, mixed>
     */
    private function tmdbSearchResults(TmdbClient $tmdbClient, string $query): array
    {
        if ($this->currentYear !== '') {
            $results = TmdbClient::getArray($tmdbClient->searchMovies($query, 1, $this->currentYear) ?? [], 'results');
            if ($results !== []) {
                return $results;
            }
        }

        return TmdbClient::getArray($tmdbClient->searchMovies($query) ?? [], 'results');
    }

    /**
     * The current title, plus a German spelling with umlauts restored ("traegt" → "trägt").
     *
     * @return list<string>
     */
    private function tmdbSearchQueries(): array
    {
        $queries = [$this->currentTitle];

        if ($this->currentIsGerman) {
            $withUmlauts = (string) preg_replace(
                ['/ae/', '/oe/', '/(?<![qQ])ue/', '/Ae/', '/Oe/', '/Ue/'],
                ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'],
                $this->currentTitle,
            );
            if ($withUmlauts !== $this->currentTitle) {
                $queries[] = $withUmlauts;
            }
        }

        return $queries;
    }

    protected function localIMDBSearch(): string|false
    {
        if ($this->currentTitle === '') {
            return false;
        }

        $cacheKey = 'local_imdb_v2_'.md5($this->currentTitle.'|'.$this->currentYear);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) || $cached === false) {
            return $cached;
        }

        $match = $this->findLocalTitleMatch() ?? $this->findLocalTitlePrefixMatch();

        if ($match === null) {
            // Short negative cache: the same title is often posted several times in a row
            // and the first release may add the movie locally.
            Cache::put($cacheKey, false, now()->addMinutes(15));

            return false;
        }

        Cache::put($cacheKey, $match, now()->addDays(7));

        if ($this->echooutput) {
            cli()->info("Found local match: {$this->currentTitle} ({$match})");
        }

        return $match;
    }

    /**
     * Best local movie whose title is similar to the release title, from a compatible year.
     */
    private function findLocalTitleMatch(): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $this->currentTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return null;
        }

        // Wildcards between words so punctuation differences still match ("Spider Man" vs "Spider-Man:").
        $query = MovieInfo::query()
            ->select(['imdbid', 'title', 'year'])
            ->where('title', 'like', '%'.implode('%', $words).'%');

        if ($this->currentYear !== '') {
            $year = (int) $this->currentYear;
            $query->whereBetween('year', [$year - 2, $year + 2]);
        }

        $candidates = [];

        foreach ($query->get() as $match) {
            $percent = $this->titleSimilarity($this->currentTitle, (string) $match['title']);

            if ($percent < self::MATCH_PERCENT) {
                continue;
            }

            // Reject candidates from a conflicting year so sequels/remakes in adjacent
            // years (e.g. "Moana 2" (2024) for a "Moana" (2026) release) cannot match.
            // Near-exact titles get a ±1 year tolerance for mislabeled release years.
            $yearDifference = $this->yearDifference($match['year']);
            if ($yearDifference !== null && $yearDifference > ($percent >= 95 ? 1 : 0)) {
                continue;
            }

            $candidates[] = ['imdbid' => (string) $match['imdbid'], 'percent' => $percent];
        }

        return $this->pickBestLocalCandidate($candidates);
    }

    /**
     * A local movie whose whole title starts the release title, from exactly the same year.
     *
     * Catches German releases that append a subtitle to the original title
     * ("Surrogates Mein zweites Ich" (2009) → "Surrogates" (2009)). Only a single
     * unambiguous candidate is accepted, and never when the rest looks like a sequel.
     */
    private function findLocalTitlePrefixMatch(): ?string
    {
        if ($this->currentYear === '') {
            return null;
        }

        $releaseTitle = $this->normalizeTitleForComparison($this->currentTitle);
        $firstWord = strtok($releaseTitle, ' ');
        if ($firstWord === false || ! str_contains($releaseTitle, ' ')) {
            return null;
        }

        $matches = [];
        $candidates = MovieInfo::query()
            ->select(['imdbid', 'title'])
            ->where('year', $this->currentYear)
            ->where('title', 'like', '%'.$firstWord.'%')
            ->limit(200)
            ->get();

        foreach ($candidates as $movie) {
            $localTitle = $this->normalizeTitleForComparison((string) $movie['title']);
            if (strlen($localTitle) < self::PREFIX_MATCH_MIN_LENGTH || ! str_starts_with($releaseTitle, $localTitle.' ')) {
                continue;
            }

            $remainder = substr($releaseTitle, strlen($localTitle) + 1);
            if (preg_match('/^(?:\d+|ii|iii|iv|v|part|teil|chapter|kapitel)\b/', $remainder)) {
                continue;
            }

            $matches[(string) $movie['imdbid']] = true;
        }

        return count($matches) === 1 ? (string) array_key_first($matches) : null;
    }

    /**
     * @param  array<int, array{imdbid: string, percent: float}>  $candidates
     */
    private function pickBestLocalCandidate(array $candidates): ?string
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $candidates[0]['imdbid'];
    }

    /**
     * Normalize release searchname before movie title/year parsing.
     * Replaces dots and underscores with spaces so patterns like "Title_2024" or "Title.Year.2024"
     * can be matched by the name+year regex (which expects a non-word character before the year).
     */
    protected function cleanReleaseNameForMovieLookup(string $searchname): string
    {
        $s = str_replace(['.', '_'], ' ', mb_scrub($searchname, 'UTF-8'));

        return trim((string) preg_replace('/\s{2,}/u', ' ', $s));
    }

    /**
     * Fold a title to a comparable form: lowercase ASCII, German umlauts spelled out
     * ("trägt" = "traegt"), "&" = "and", no punctuation, no leading English article.
     */
    protected function normalizeTitleForComparison(string $title): string
    {
        $title = mb_strtolower($title, 'UTF-8');
        $title = strtr($title, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $title = Str::ascii($title);
        $title = str_replace('&', ' and ', $title);
        $title = (string) preg_replace("/['`]/", '', $title);
        $title = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $title));

        return (string) preg_replace('/^(?:the|a|an) (?=\S)/', '', $title);
    }

    /**
     * Similarity of two titles in percent, after normalization.
     */
    private function titleSimilarity(string $left, string $right): float
    {
        $left = $this->normalizeTitleForComparison($left);
        $right = $this->normalizeTitleForComparison($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 100.0;
        }

        if (strlen($left) <= self::SHORT_TITLE_LENGTH || strlen($right) <= self::SHORT_TITLE_LENGTH) {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return $percent;
    }

    /**
     * Best similarity between the current release title and any of a candidate's titles.
     *
     * @param  array<int|string, mixed>  $titles
     */
    private function bestTitleSimilarity(array $titles): float
    {
        $best = 0.0;
        foreach ($titles as $title) {
            if (is_scalar($title) && (string) $title !== '') {
                $best = max($best, $this->titleSimilarity($this->currentTitle, (string) $title));
            }
        }

        return $best;
    }

    /**
     * Validate fetched metadata against the release currently being processed.
     * Sets $candidateMismatch when the candidate is rejected so callers can
     * distinguish a wrong IMDb id from a plain fetch failure.
     *
     * @param  string|array<int|string, mixed>|null  $titles
     */
    private function matchesCurrentContext(string|array|null $titles, mixed $year): bool
    {
        if ($this->currentTitle === '') {
            return true;
        }

        $titlePercent = $this->bestTitleSimilarity(is_array($titles) ? $titles : [(string) $titles]);
        $nearExactTitle = $titlePercent >= 95;

        $yearDifference = $this->yearDifference($year);
        $yearMatches = $yearDifference === 0;

        // A conflicting year means a different movie (e.g. a "Moana" (2026) release
        // matched to "Moana 2" (2024)). Near-exact titles get a ±1 year tolerance:
        // release groups occasionally mislabel the year (festival vs streaming release).
        if ($yearDifference !== null && $yearDifference > ($nearExactTitle ? 1 : 0)) {
            $this->candidateMismatch = true;

            return false;
        }

        // Alternate/international titles are acceptable when the exact year confirms
        // the movie (e.g. a "Salve Geral Irmandade" (2026) release vs the locally
        // stored international title "State of Fear" (2026)).
        if ($titlePercent < self::MATCH_PERCENT && ! $yearMatches) {
            $this->candidateMismatch = true;

            return false;
        }

        return true;
    }

    /**
     * Whether a search result is close enough to the current release to try it.
     *
     * Stricter than {@see matchesCurrentContext()}: a matching year alone is not enough,
     * the title must also resemble one of the candidate's titles.
     *
     * @param  array<int|string, mixed>  $titles
     */
    private function isAcceptableSearchCandidate(array $titles, mixed $year): bool
    {
        $titlePercent = $this->bestTitleSimilarity($titles);
        $yearDifference = $this->yearDifference($year);

        if ($yearDifference === null) {
            return $titlePercent >= ($this->currentYear === '' ? self::MATCH_PERCENT_NO_YEAR : self::MATCH_PERCENT);
        }

        return match ($yearDifference) {
            0 => $titlePercent >= self::MATCH_PERCENT_ALT_TITLE,
            1 => $titlePercent >= 95,
            default => false,
        };
    }

    /**
     * Absolute difference in years between the candidate and the current release,
     * or null when either year is unknown.
     */
    private function yearDifference(mixed $year): ?int
    {
        if ($this->currentYear === '' || ! is_scalar($year) || (string) $year === '') {
            return null;
        }

        if (! preg_match('/(\d{4})/', (string) $year, $hits)) {
            return null;
        }

        return abs((int) $this->currentYear - (int) $hits[1]);
    }

    /**
     * Validate a candidate IMDb id against the locally stored movieinfo row (if any).
     *
     * @phpstan-impure
     */
    private function candidateMatchesLocalInfo(string $imdbId): bool
    {
        if ($this->currentTitle === '') {
            return true;
        }

        $movieInfo = MovieInfo::query()->where('imdbid', $imdbId)->first(['title', 'year']);
        if ($movieInfo === null) {
            return true;
        }

        return $this->matchesCurrentContext($movieInfo->title, $movieInfo->year);
    }

    /**
     * Re-verify a release's IMDb match against its searchname and the local movieinfo row.
     * Returns null when the release cannot be verified (unparseable name or no local info).
     */
    public function verifyReleaseMovieMatch(string $searchname, string $imdbId): ?bool
    {
        if (! $this->parseMovieSearchName($searchname)) {
            return null;
        }

        return $this->candidateMatchesLocalInfo($imdbId);
    }

    /**
     * Parse the movie title, year, alternate titles and any embedded IMDb id from a release name.
     *
     * Returns false when nothing usable was found.
     */
    protected function parseMovieSearchName(string $releaseName): bool
    {
        $this->currentTitle = '';
        $this->currentYear = '';
        $this->currentParsedImdbId = '';
        $this->alternateTitles = [];
        $this->currentIsGerman = false;

        $releaseName = $this->cleanReleaseNameForMovieLookup($releaseName);
        if ($releaseName === '') {
            return false;
        }

        // Embedded IMDb id: imdb-tt1234567, imdb_tt1234567, imdb.tt1234567 or plain tt1234567.
        if (preg_match('/(?:^|[\W_])(?:imdb[\-_.])?(tt\d{5,9})(?=[\W_]|$)/i', $releaseName, $imdbHit)) {
            $this->currentParsedImdbId = strtolower($imdbHit[1]);
        }

        $this->currentIsGerman = (bool) preg_match('/(?<!\S)(?:German|Deutsch)(?!\S)/i', $releaseName);

        // TV episodes filed under movies ("Das Strafgericht S2025E975") never match a movie.
        if ($this->currentParsedImdbId === '' && preg_match('/(?<!\S)S\d{1,4} ?E\d{1,4}(?!\S)/i', $releaseName)) {
            return false;
        }

        [$title, $year] = $this->splitTitleAndYear($releaseName);
        [$title, $alternates] = $this->cleanParsedTitle($title);

        if ($this->isUsableTitle($title, $year)) {
            $this->currentTitle = $title;
            $this->currentYear = $year;
            $this->alternateTitles = $this->filterAlternateTitles($title, $alternates);

            return true;
        }

        return $this->currentParsedImdbId !== '';
    }

    /**
     * Split a cleaned release name into the raw title part and the release year.
     *
     * The year is the last plausible year before the first source tag, so titles
     * containing numbers keep them ("Blade Runner 2049 2017" → "Blade Runner 2049", 2017;
     * "2001 A Space Odyssey 1968" → "2001 A Space Odyssey", 1968).
     *
     * @return array{0: string, 1: string}
     */
    private function splitTitleAndYear(string $name): array
    {
        $sourceTagOffset = $this->firstTagOffset($name, self::SOURCE_TAG_PATTERN);
        $latestYear = (int) now()->year + 1;
        $year = '';
        $yearOffset = null;

        if (preg_match_all('/(?<![^\s(\[])((?:19|20)\d{2})(?![^\s)\]])/', $name, $hits, PREG_OFFSET_CAPTURE)) {
            foreach ($hits[1] as [$candidate, $offset]) {
                if ($sourceTagOffset !== null && $offset > $sourceTagOffset) {
                    break;
                }
                if ($offset === 0 || (int) $candidate > $latestYear) {
                    continue;
                }
                $year = $candidate;
                $yearOffset = $offset;
            }
        }

        if ($yearOffset !== null) {
            return [substr($name, 0, $yearOffset), $year];
        }

        // Cut at the first tag that leaves a real title; an edition word may be part of the
        // title ("The German 1080p" must not become "The").
        $editionTagOffset = $this->firstTagOffset($name, self::EDITION_TAG_PATTERN);
        $tagOffsets = array_filter([$sourceTagOffset, $editionTagOffset], static fn (?int $offset): bool => $offset !== null);
        sort($tagOffsets);
        foreach ($tagOffsets as $tagOffset) {
            $title = substr($name, 0, $tagOffset);
            if ($this->hasTitleWords($title)) {
                return [$title, ''];
            }
        }

        // No year and no tags: use the whole name minus a trailing "-GROUP".
        if (strlen($name) <= 100 && ! preg_match('/(?:^|\s)(?:rar|zip|avi|mkv|mp4|par2|nzb)$/i', $name)) {
            return [(string) preg_replace('/-[^\s-]+$/', '', $name), ''];
        }

        return ['', ''];
    }

    /**
     * Byte offset of the first whole-word tag matching the pattern, never at the start of the name.
     */
    private function firstTagOffset(string $name, string $pattern): ?int
    {
        if (preg_match('/(?<=\s)'.$pattern.'(?![^\s-])/iu', $name, $hit, PREG_OFFSET_CAPTURE)) {
            return $hit[0][1];
        }

        return null;
    }

    /**
     * Clean the raw title part and collect alternate titles from brackets and " - " separators.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function cleanParsedTitle(string $title): array
    {
        $alternates = [];

        // "Orumcek Adam Yepyeni Bir Gun (Spider Man Brand New Day)": bracketed text is an alternate title.
        $title = (string) preg_replace_callback('/[(\[]([^)\]]*)(?:[)\]]|$)/u', static function (array $hit) use (&$alternates): string {
            $inner = trim($hit[1]);
            if (preg_match('/\p{L}{3,}/u', $inner) && ! preg_match('/www|\.(?:com|net|org)\b/i', $inner)) {
                $alternates[] = $inner;
            }

            return ' ';
        }, $title);

        $title = $this->trimTitle((string) preg_replace("/[^\p{L}\p{N}\s'&!,:+\-]/u", ' ', $title));

        // Tag words can also be the title ("The German" (2008), "French" (2024)): stop
        // stripping before only an article or nothing is left.
        while (true) {
            $stripped = $this->trimTitle((string) preg_replace('/(?<!\S)'.self::TRAILING_TAG_PATTERN.'$/iu', '', $title));
            if ($stripped === $title || ! $this->hasTitleWords($stripped)) {
                break;
            }
            $title = $stripped;
        }

        $parts = preg_split('/\s+-\s+/u', $title) ?: [];
        if (count($parts) > 1) {
            foreach ($parts as $part) {
                $alternates[] = $this->trimTitle($part);
            }
        }

        return [$title, $alternates];
    }

    private function trimTitle(string $title): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $title), " -:,\t");
    }

    /**
     * Whether a title has more than a leading article ("The", "Der", "La", ...).
     */
    private function hasTitleWords(string $title): bool
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_diff($words, ['the', 'a', 'an', 'der', 'die', 'das', 'le', 'la', 'les', 'el', 'il', 'lo']) !== [];
    }

    private function isUsableTitle(string $title, string $year): bool
    {
        $normalized = $this->normalizeTitleForComparison($title);
        if ($normalized === '') {
            return false;
        }

        // A year confirms short or numeric titles ("Pi" 1998, "1917" 2019).
        if ($year !== '') {
            return true;
        }

        return mb_strlen($title) > 2 && ! preg_match('/^\d+$/', $normalized);
    }

    /**
     * @param  list<string>  $alternates
     * @return list<string>
     */
    private function filterAlternateTitles(string $title, array $alternates): array
    {
        $seen = [$this->normalizeTitleForComparison($title) => true];
        $filtered = [];

        foreach ($alternates as $alternate) {
            $normalized = $this->normalizeTitleForComparison($alternate);
            if (strlen($normalized) < 3 || preg_match('/^\d+$/', $normalized) || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $filtered[] = $alternate;

            if (count($filtered) >= self::MAX_ALTERNATE_TITLES) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Get IMDB genres.
     *
     * @return array<int, string>
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
        $fileHas = $this->releaseImage->imageExists($this->imgSavePath, $imdbId.'-cover');

        return $dbHas || $fileHas;
    }

    protected function fetchAndSaveCoverOnly(string $imdbId): bool
    {
        try {
            $fanart = $this->fetchFanartTVProperties($imdbId);
            if (! empty($fanart['cover'])) {
                if ($this->saveRemoteAsset($imdbId.'-cover', $fanart['cover']) === 1) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Fanart cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        try {
            $tmdb = $this->fetchTMDBProperties($imdbId);
            if (! empty($tmdb['cover'])) {
                if ($this->saveRemoteAsset($imdbId.'-cover', $tmdb['cover']) === 1) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('TMDB cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        try {
            $imdb = $this->fetchIMDBProperties($imdbId);
            if (! empty($imdb['cover'])) {
                if ($this->saveRemoteAsset($imdbId.'-cover', $imdb['cover']) === 1) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('IMDB cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        try {
            $omdb = $this->fetchOmdbAPIProperties($imdbId);
            if (! empty($omdb['cover'])) {
                if ($this->saveRemoteAsset($imdbId.'-cover', $omdb['cover']) === 1) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('OMDB cover fetch failed for '.$imdbId.': '.$e->getMessage());
        }

        return false;
    }

    private function saveRemoteAsset(
        string $name,
        string $url,
        ImageAssetProfile $profile = ImageAssetProfile::Original,
    ): int {
        return (int) $this->releaseImage->saveRemoteImage(
            $name,
            $url,
            $this->imgSavePath,
            $profile,
        )->success;
    }
}
