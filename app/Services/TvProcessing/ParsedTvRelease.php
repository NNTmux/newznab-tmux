<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

/**
 * Show, season, episode and airdate parsed from a release searchname.
 */
final readonly class ParsedTvRelease
{
    public function __construct(
        public string $name,
        public string $cleanName,
        public string $country,
        public ?int $season,
        public ?int $episode,
        public bool $seasonPack,
        public ?string $airdate,
        public ?int $year,
    ) {}

    /**
     * Build from AbstractTvProvider::parseInfo() output; null when it could not parse the name.
     *
     * @param  array<string, mixed>|false  $info
     */
    public static function fromParseInfo(array|false $info): ?self
    {
        if ($info === false || empty($info['name']) || ! isset($info['season'], $info['episode'])) {
            return null;
        }

        $episode = $info['episode'];
        if (\is_array($episode)) {
            $episode = $episode[0] ?? 0;
        }
        $seasonPack = $episode === 'all';
        $airdate = (string) ($info['airdate'] ?? '');
        $year = $info['year'] ?? null;

        return new self(
            name: (string) $info['name'],
            cleanName: (string) ($info['cleanname'] ?? $info['name']),
            country: (string) ($info['country'] ?? ''),
            season: is_numeric($info['season']) ? (int) $info['season'] : null,
            episode: ! $seasonPack && is_numeric($episode) ? (int) $episode : null,
            seasonPack: $seasonPack,
            airdate: $airdate !== '' ? $airdate : null,
            year: is_numeric($year) ? (int) $year : null,
        );
    }

    public function hasEpisodeNumbers(): bool
    {
        return $this->season !== null && $this->season >= 0 && $this->episode !== null && $this->episode > 0;
    }

    public function hasAirdate(): bool
    {
        return $this->airdate !== null;
    }

    public function hasEpisodeReference(): bool
    {
        return $this->hasEpisodeNumbers() || $this->hasAirdate();
    }

    /** The clean name without the " (YYYY)" suffix parseInfo() appends. */
    public function titleWithoutYear(): string
    {
        return trim((string) preg_replace('/\s*\((19|20)\d{2}\)$/', '', $this->cleanName));
    }

    /** Two-letter country parsed from the name, or ''. */
    public function countryCode(): string
    {
        return \strlen($this->country) === 2 ? $this->country : '';
    }
}
