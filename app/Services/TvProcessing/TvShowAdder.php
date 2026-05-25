<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

use App\Models\Video;
use App\Services\TmdbClient;
use App\Services\TraktService;
use App\Services\TvProcessing\Providers\TmdbProvider;
use App\Services\TvProcessing\Providers\TraktProvider;
use App\Services\TvProcessing\Providers\TvdbProvider;
use App\Services\TvProcessing\Providers\TvMazeProvider;
use DariusIII\TVMaze\TVMaze as TVMazeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resolve a TV show by external ID (tvdb, tvmaze, tmdb, trakt, imdb) and
 * persist it into the local videos / tv_info / videos_aliases tables.
 *
 * Each branch reuses the existing provider's formatShowInfo() + add() so the
 * data shape, dedupe, and observer-driven search-index inserts stay consistent
 * with the rest of the TV processing pipeline.
 */
class TvShowAdder
{
    public const SUPPORTED_SOURCES = ['tvdb', 'tvmaze', 'tmdb', 'trakt', 'imdb'];

    /**
     * Resolve + persist. Returns metadata about the operation.
     *
     * @return array{videoId: int, existed: bool, source: string, externalId: string, title: ?string}
     *
     * @throws InvalidArgumentException when the source is unsupported.
     * @throws RuntimeException when no provider could resolve the id or the
     *                          required provider is not configured.
     */
    public function add(string $source, string $id, int $type = 0): array
    {
        $source = strtolower(trim($source));
        $id = $this->normalizeId($source, $id);

        if (! in_array($source, self::SUPPORTED_SOURCES, true)) {
            throw new InvalidArgumentException("Unsupported external source: {$source}");
        }

        // Short-circuit if we already have this video by external id.
        $column = $this->videoColumnForSource($source);
        $existing = Video::query()->where($column, $id)->first(['id', 'title']);
        if ($existing !== null) {
            return [
                'videoId' => (int) $existing->id,
                'existed' => true,
                'source' => $source,
                'externalId' => (string) $id,
                'title' => (string) $existing->title,
            ];
        }

        return DB::transaction(function () use ($source, $id, $type) {
            return $this->dispatch($source, $id, $type);
        });
    }

    /**
     * Dry-run resolve. Returns the formatted show array (without persisting)
     * for preview UIs. Returns null when nothing was found.
     *
     * @return array<string, mixed>|null
     */
    public function preview(string $source, string $id): ?array
    {
        $source = strtolower(trim($source));
        $id = $this->normalizeId($source, $id);

        switch ($source) {
            case 'tvdb':
                $provider = new TvdbProvider;
                $show = $this->fetchTvdb($provider, (int) $id);

                return $show ? $provider->formatShowInfo($show) : null;
            case 'tvmaze':
                $provider = new TvMazeProvider;
                $show = $this->fetchTvMaze((int) $id, 'tvmaze');

                return $show ? $provider->formatShowInfo($show) : null;
            case 'tmdb':
                $provider = new TmdbProvider;
                $show = $this->fetchTmdb((int) $id);

                return $show ? $provider->formatShowInfo($show) : null;
            case 'trakt':
                $provider = new TraktProvider;
                $show = $this->fetchTrakt($id);

                return $show ? $provider->formatShowInfo($show) : null;
            case 'imdb':
                return $this->previewImdb($id);
        }

        return null;
    }

    /**
     * @return array{videoId: int, existed: bool, source: string, externalId: string, title: ?string}
     */
    private function dispatch(string $source, string $id, int $type): array
    {
        switch ($source) {
            case 'tvdb':
                return $this->addViaTvdb((int) $id, $type);
            case 'tvmaze':
                return $this->addViaTvMaze((int) $id, $type);
            case 'tmdb':
                return $this->addViaTmdb((int) $id, $type);
            case 'trakt':
                return $this->addViaTrakt($id, $type);
            case 'imdb':
                return $this->addViaImdb($id, $type);
        }

        throw new InvalidArgumentException("Unsupported external source: {$source}");
    }

    // -------------------------------------------------------------------------
    // TVDB
    // -------------------------------------------------------------------------

    private function fetchTvdb(TvdbProvider $provider, int $tvdbId): ?object
    {
        try {
            $extended = $provider->client->series()->extended($tvdbId);
        } catch (\Throwable $e) {
            Log::warning('TvShowAdder: TVDB lookup failed', ['id' => $tvdbId, 'error' => $e->getMessage()]);

            return null;
        }

        if (! is_object($extended)) {
            return null;
        }

        // TvdbProvider::formatShowInfo() expects fields shaped like SearchResult
        // (tvdb_id, name, overview, first_air_time, aliases). The extended
        // endpoint returns SeriesExtendedRecord (camelCase). Adapt it.
        $aliases = [];
        if (! empty($extended->aliases) && is_array($extended->aliases)) {
            foreach ($extended->aliases as $alias) {
                if (is_object($alias) && isset($alias->name)) {
                    $aliases[] = (string) $alias->name;
                } elseif (is_string($alias)) {
                    $aliases[] = $alias;
                }
            }
        }

        return (object) [
            'tvdb_id' => (string) ($extended->id ?? $tvdbId),
            'name' => (string) ($extended->name ?? ''),
            'overview' => (string) ($extended->overview ?? ''),
            'first_air_time' => (string) ($extended->firstAired ?? ''),
            'aliases' => $aliases,
        ];
    }

    private function addViaTvdb(int $tvdbId, int $type): array
    {
        $provider = new TvdbProvider;
        $show = $this->fetchTvdb($provider, $tvdbId);
        if ($show === null) {
            throw new RuntimeException("No TVDB show found for ID {$tvdbId}.");
        }

        $data = $provider->formatShowInfo($show);
        $data['type'] = $type;

        return $this->persist($provider, $data, 'tvdb', (string) $tvdbId);
    }

    // -------------------------------------------------------------------------
    // TVMaze
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTvMaze(int|string $id, string $kind): ?array
    {
        try {
            $client = new TVMazeClient;
            if ($kind === 'tvmaze') {
                $show = $client->getShowByShowID((int) $id);
            } else {
                // 'tvrage' | 'thetvdb' | 'imdb'
                $show = $client->getShowBySiteID($kind, $id);
            }
        } catch (\Throwable $e) {
            Log::warning('TvShowAdder: TVMaze lookup failed', ['id' => $id, 'kind' => $kind, 'error' => $e->getMessage()]);

            return null;
        }

        return is_array($show) ? $show : null;
    }

    private function addViaTvMaze(int $tvmazeId, int $type): array
    {
        $provider = new TvMazeProvider;
        $show = $this->fetchTvMaze($tvmazeId, 'tvmaze');
        if ($show === null) {
            throw new RuntimeException("No TVMaze show found for ID {$tvmazeId}.");
        }

        $data = $provider->formatShowInfo($show);
        $data['type'] = $type;

        return $this->persist($provider, $data, 'tvmaze', (string) $tvmazeId);
    }

    // -------------------------------------------------------------------------
    // TMDB
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTmdb(int $tmdbId): ?array
    {
        $client = app(TmdbClient::class);
        if (! $client->isConfigured()) {
            throw new RuntimeException('TMDB API is not configured.');
        }

        $show = $client->getTvShow($tmdbId, ['external_ids', 'alternative_titles']);
        if ($show === null) {
            return null;
        }

        // Mirror the enrichment that TmdbProvider::matchShowInfo() does so
        // formatShowInfo() has the keys it expects.
        $alternativeTitles = [];
        $altRoot = TmdbClient::getArray($show, 'alternative_titles');
        $results = TmdbClient::getArray($altRoot, 'results');
        foreach ($results as $aka) {
            if (is_array($aka) && isset($aka['title'])) {
                $alternativeTitles[] = $aka['title'];
            }
        }
        $show['alternative_titles'] = $alternativeTitles;

        $networks = TmdbClient::getArray($show, 'networks');
        $show['network'] = ! empty($networks[0]['name']) ? (string) $networks[0]['name'] : '';

        return $show;
    }

    private function addViaTmdb(int $tmdbId, int $type): array
    {
        $provider = new TmdbProvider;
        $show = $this->fetchTmdb($tmdbId);
        if ($show === null) {
            throw new RuntimeException("No TMDB show found for ID {$tmdbId}.");
        }

        $data = $provider->formatShowInfo($show);
        $data['type'] = $type;

        return $this->persist($provider, $data, 'tmdb', (string) $tmdbId);
    }

    // -------------------------------------------------------------------------
    // Trakt
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTrakt(int|string $id): ?array
    {
        $client = app(TraktService::class);
        if (! $client->isConfigured()) {
            throw new RuntimeException('Trakt API is not configured.');
        }

        $show = $client->getShowSummary((string) $id, 'full');

        return is_array($show) && ! empty($show['ids']) ? $show : null;
    }

    private function addViaTrakt(int|string $traktId, int $type): array
    {
        $provider = new TraktProvider;
        $show = $this->fetchTrakt($traktId);
        if ($show === null) {
            throw new RuntimeException("No Trakt show found for ID {$traktId}.");
        }

        $data = $provider->formatShowInfo($show);
        $data['type'] = $type;

        return $this->persist($provider, $data, 'trakt', (string) $traktId);
    }

    // -------------------------------------------------------------------------
    // IMDB (fallback chain: TMDB → Trakt → TVMaze)
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function previewImdb(string $imdbId): ?array
    {
        // Try TMDB first
        try {
            $client = app(TmdbClient::class);
            if ($client->isConfigured()) {
                $found = $client->findTvByExternalId('tt'.$imdbId, 'imdb_id');
                if ($found !== null) {
                    $tmdbId = TmdbClient::getInt($found, 'id');
                    if ($tmdbId > 0) {
                        $provider = new TmdbProvider;
                        $show = $this->fetchTmdb($tmdbId);
                        if ($show !== null) {
                            return $provider->formatShowInfo($show);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('TvShowAdder: IMDB→TMDB preview failed', ['imdb' => $imdbId, 'error' => $e->getMessage()]);
        }

        // Then Trakt
        try {
            $trakt = app(TraktService::class);
            if ($trakt->isConfigured()) {
                $results = $trakt->searchById($imdbId, 'imdb', 'show');
                if (is_array($results) && ! empty($results[0]['show']['ids']['trakt'])) {
                    $traktId = (int) $results[0]['show']['ids']['trakt'];
                    $provider = new TraktProvider;
                    $show = $this->fetchTrakt($traktId);
                    if ($show !== null) {
                        return $provider->formatShowInfo($show);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('TvShowAdder: IMDB→Trakt preview failed', ['imdb' => $imdbId, 'error' => $e->getMessage()]);
        }

        // Then TVMaze
        try {
            $provider = new TvMazeProvider;
            $show = $this->fetchTvMaze('tt'.$imdbId, 'imdb');
            if ($show !== null) {
                return $provider->formatShowInfo($show);
            }
        } catch (\Throwable $e) {
            Log::info('TvShowAdder: IMDB→TVMaze preview failed', ['imdb' => $imdbId, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function addViaImdb(string $imdbId, int $type): array
    {
        // TMDB
        try {
            $client = app(TmdbClient::class);
            if ($client->isConfigured()) {
                $found = $client->findTvByExternalId('tt'.$imdbId, 'imdb_id');
                if ($found !== null) {
                    $tmdbId = TmdbClient::getInt($found, 'id');
                    if ($tmdbId > 0) {
                        return $this->addViaTmdb($tmdbId, $type);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('TvShowAdder: IMDB→TMDB add failed', ['imdb' => $imdbId, 'error' => $e->getMessage()]);
        }

        // Trakt
        try {
            $trakt = app(TraktService::class);
            if ($trakt->isConfigured()) {
                $results = $trakt->searchById($imdbId, 'imdb', 'show');
                if (is_array($results) && ! empty($results[0]['show']['ids']['trakt'])) {
                    return $this->addViaTrakt((int) $results[0]['show']['ids']['trakt'], $type);
                }
            }
        } catch (\Throwable $e) {
            Log::info('TvShowAdder: IMDB→Trakt add failed', ['imdb' => $imdbId, 'error' => $e->getMessage()]);
        }

        // TVMaze (last resort, no extra API key required)
        $provider = new TvMazeProvider;
        $show = $this->fetchTvMaze('tt'.$imdbId, 'imdb');
        if ($show !== null) {
            $data = $provider->formatShowInfo($show);
            $data['type'] = $type;

            return $this->persist($provider, $data, 'imdb', $imdbId);
        }

        throw new RuntimeException("No show found for IMDB ID tt{$imdbId} via TMDB, Trakt, or TVMaze.");
    }

    // -------------------------------------------------------------------------
    // Persistence helper
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @return array{videoId: int, existed: bool, source: string, externalId: string, title: ?string}
     */
    private function persist(object $provider, array $data, string $source, string $externalId): array
    {
        // Ensure required keys formatShowInfo callers might omit
        $data += [
            'country' => '',
            'aliases' => '',
            'localzone' => "''",
            'imdb' => '',
            'tvdb' => 0,
            'trakt' => 0,
            'tvrage' => 0,
            'tvmaze' => 0,
            'tmdb' => 0,
            'summary' => '',
            'publisher' => '',
            'started' => null,
            'type' => 0,
            'source' => 0,
            'title' => '',
        ];

        if (empty($data['title'])) {
            throw new RuntimeException('Provider returned a show without a title.');
        }

        /** @var int $videoId */
        $videoId = $provider->add($data); // @phpstan-ignore-line - AbstractTvProvider::add()

        if ($videoId > 0 && method_exists($provider, 'getPoster')) {
            try {
                $provider->getPoster($videoId);
            } catch (\Throwable $e) {
                Log::info('TvShowAdder: poster fetch failed', ['videoId' => $videoId, 'error' => $e->getMessage()]);
            }
        }

        return [
            'videoId' => (int) $videoId,
            'existed' => false,
            'source' => $source,
            'externalId' => $externalId,
            'title' => (string) $data['title'],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function videoColumnForSource(string $source): string
    {
        return match ($source) {
            'tvdb' => 'tvdb',
            'tvmaze' => 'tvmaze',
            'tmdb' => 'tmdb',
            'trakt' => 'trakt',
            'imdb' => 'imdb',
            default => throw new InvalidArgumentException("Unsupported source: {$source}"),
        };
    }

    private function normalizeId(string $source, string $id): string
    {
        $id = trim($id);
        if ($source === 'imdb') {
            // Accept "tt0944947" or "0944947" — store/lookup as the numeric portion.
            if (preg_match('/^tt?(\d{5,10})$/i', $id, $m)) {
                return $m[1];
            }
            if (ctype_digit($id)) {
                return $id;
            }
            throw new InvalidArgumentException('IMDB ID must look like "tt1234567" or its numeric part.');
        }

        if (! ctype_digit($id) || (int) $id <= 0) {
            throw new InvalidArgumentException(strtoupper($source).' ID must be a positive integer.');
        }

        return $id;
    }
}

