<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Central view of which external metadata sources are usable, so callers can
 * skip unconfigured ones silently and the reason is logged once per start/day.
 */
final class MetadataSources
{
    public const string TMDB = 'tmdb';

    public const string OMDB = 'omdb';

    public const string TVDB = 'tvdb';

    public const string FANARTTV = 'fanarttv';

    public const string TRAKT = 'trakt';

    public const string IMDB_SCRAPER = 'imdb_scraper';

    public const string IMDBAPI_DEV = 'imdbapi_dev';

    public const string IGDB = 'igdb';

    public const string ISBNDB = 'isbndb';

    private const string SUMMARY_CACHE_PREFIX = 'metadata_sources:summary:';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::TMDB => 'TMDB',
            self::OMDB => 'OMDb',
            self::TVDB => 'TVDB',
            self::FANARTTV => 'Fanart.tv',
            self::TRAKT => 'Trakt',
            self::IMDB_SCRAPER => 'IMDb scraping',
            self::IMDBAPI_DEV => 'imdbapi.dev',
            self::IGDB => 'IGDB',
            self::ISBNDB => 'ISBNdb',
        ];
    }

    /**
     * Why a source is unavailable, or null when it can be used.
     */
    public static function unavailableReason(string $source): ?string
    {
        return match ($source) {
            self::TMDB => self::missingKey('tmdb.api_key', 'TMDB_APIKEY'),
            self::OMDB => self::missingKey('nntmux_api.omdb_api_key', 'OMDB_APIKEY'),
            self::TVDB => self::missingKey('tvdb.api_key', 'TVDB_APIKEY')
                ?? (config('tvdb.user_pin') === null ? 'no user PIN (TVDB_PIN)' : null),
            self::FANARTTV => self::missingKey('nntmux_api.fanarttv_api_key', 'FANARTTV_APIKEY'),
            self::TRAKT => self::missingKey('nntmux_api.trakttv_api_key', 'TRAKTTV_APIKEY'),
            self::IMDB_SCRAPER => self::disabled('nntmux_api.imdb_scraper_enabled', 'IMDB_SCRAPER_ENABLED'),
            self::IMDBAPI_DEV => self::disabled('nntmux_api.imdbapi_dev_enabled', 'IMDBAPI_DEV_ENABLED'),
            self::IGDB => self::missingKey('igdb.credentials.client_id', 'TWITCH_CLIENT_ID')
                ?? self::missingKey('igdb.credentials.client_secret', 'TWITCH_CLIENT_SECRET'),
            self::ISBNDB => self::missingKey('nntmux_api.isbndb_api_key', 'ISBNDB_API_KEY'),
            default => throw new \InvalidArgumentException('Unknown metadata source: '.$source),
        };
    }

    public static function isAvailable(string $source): bool
    {
        return self::unavailableReason($source) === null;
    }

    /**
     * @return array<string, array{label: string, available: bool, reason: string|null}>
     */
    public static function all(): array
    {
        $sources = [];
        foreach (self::labels() as $source => $label) {
            $reason = self::unavailableReason($source);
            $sources[$source] = ['label' => $label, 'available' => $reason === null, 'reason' => $reason];
        }

        return $sources;
    }

    /**
     * @return list<string>
     */
    public static function summaryLines(): array
    {
        $lines = [];
        foreach (self::all() as $source) {
            if (! $source['available']) {
                $lines[] = 'Metadata source '.$source['label'].': '.$source['reason'].', lookups disabled';
            }
        }

        return $lines;
    }

    /**
     * Log the summary unconditionally, e.g. when processing starts.
     *
     * @return list<string>
     */
    public static function logSummary(): array
    {
        Cache::put(self::summaryCacheKey(), true, now()->addDay());

        $lines = self::summaryLines();
        foreach ($lines as $line) {
            Log::info($line);
        }

        return $lines;
    }

    /**
     * Log the summary the first time this is called on a given day.
     */
    public static function logDailySummary(): bool
    {
        if (! Cache::add(self::summaryCacheKey(), true, now()->addDay())) {
            return false;
        }

        foreach (self::summaryLines() as $line) {
            Log::info($line);
        }

        return true;
    }

    private static function summaryCacheKey(): string
    {
        return self::SUMMARY_CACHE_PREFIX.now()->toDateString();
    }

    private static function missingKey(string $configKey, string $env): ?string
    {
        return trim((string) config($configKey, '')) === '' ? 'no API key ('.$env.')' : null;
    }

    private static function disabled(string $configKey, string $env): ?string
    {
        return filter_var(config($configKey, true), FILTER_VALIDATE_BOOL) ? null : 'disabled by '.$env;
    }
}
