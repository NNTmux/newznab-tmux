<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

use App\Models\TvEpisode;
use App\Models\Video;
use App\Models\VideoAlias;
use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\LocalDbProvider;

/**
 * Finds the stored show a parsed release belongs to.
 *
 * Several stored shows can share a title once punctuation, the year and the country
 * qualifier are ignored ("Jeopardy" 2002 GB and "Jeopardy!" 1964 US, "Doctor Who" and
 * "Doctor Who (2005)"). Instead of the oldest row, the show that already holds the
 * release's episode wins, then the one whose year and country agree, then the one with
 * the most episodes.
 */
final class LocalShowMatcher
{
    /** Stored titles fetched per lookup before the exact comparison in PHP. */
    private const int CANDIDATE_ROWS = 200;

    /** Shows compared in detail; more than this means the title is too generic to decide. */
    private const int MAX_CANDIDATES = 20;

    /** Titles whose candidates are memoized per instance (batch commands look up the same shows often). */
    private const int MEMO_SIZE = 2000;

    /** @var array<string, list<int>> */
    private array $memo = [];

    /** @var array<string, list<int>>|null comparable title => show ids, once preloadTitles() ran */
    private ?array $titleIndex = null;

    public function __construct(private readonly LocalDbProvider $provider = new LocalDbProvider) {}

    /**
     * Load every stored TV title and alias into memory so candidates() needs no queries.
     * For batch commands that look up many titles; the pipeline keeps the per-title queries.
     */
    public function preloadTitles(): void
    {
        $index = [];
        $add = function (int $videoId, string $title) use (&$index): void {
            foreach (array_unique([$this->provider->comparableTitle($title), $this->provider->comparableTitle($this->withoutQualifier($title))]) as $key) {
                if ($key !== '') {
                    $index[$key][$videoId] = $videoId;
                }
            }
        };

        Video::query()->where('type', AbstractTvProvider::TYPE_TV)->select(['id', 'title'])
            ->chunkById(5000, function ($videos) use ($add): void {
                foreach ($videos as $video) {
                    $add((int) $video->id, (string) $video->title);
                }
            });

        VideoAlias::query()
            ->join('videos', 'videos.id', '=', 'videos_aliases.videos_id')
            ->where('videos.type', AbstractTvProvider::TYPE_TV)
            ->select(['videos_aliases.videos_id', 'videos_aliases.title'])
            ->orderBy('videos_aliases.videos_id')
            ->lazy(5000)
            ->each(function ($alias) use ($add): void {
                $add((int) $alias->videos_id, (string) $alias->title);
            });

        $this->titleIndex = array_map(static fn (array $ids): array => array_values($ids), $index);
        $this->memo = [];
    }

    /**
     * The best stored show for the release, or 0.
     *
     * Without a comparable-title match it falls back to the legacy loose lookups
     * (BaseVideoProvider::getByTitle), unless $looseFallback is false: batch commands
     * skip them, they scan the whole videos table per title.
     */
    public function find(ParsedTvRelease $parsed, bool $looseFallback = true): int
    {
        $candidates = $this->candidates($parsed->titleWithoutYear());
        $countryRequired = false;

        // "Big Brother Australia" is stored as "Big Brother (AU)": retry without the
        // country, but then only shows of that country qualify.
        if ($candidates === [] && $parsed->countryCode() !== '' && $parsed->searchTitle() !== $parsed->titleWithoutYear()) {
            $candidates = $this->candidates($parsed->searchTitle());
            $countryRequired = true;
        }

        if ($candidates !== []) {
            $videoId = $this->pick($candidates, $parsed, $countryRequired);
            if ($videoId > 0) {
                return $videoId;
            }
        }

        return $looseFallback ? $this->provider->getByTitle($parsed->cleanName, AbstractTvProvider::TYPE_TV) : 0;
    }

    /**
     * Ids of stored TV shows whose title or alias equals $title once both are reduced
     * by comparableTitle(), with or without a "(YYYY)" or "(CC)" qualifier.
     *
     * @return list<int>
     */
    public function candidates(string $title): array
    {
        $wanted = $this->provider->comparableTitle($title);
        if ($this->titleIndex !== null) {
            return $this->titleIndex[$wanted] ?? [];
        }

        if (isset($this->memo[$wanted])) {
            return $this->memo[$wanted];
        }

        if (\count($this->memo) >= self::MEMO_SIZE) {
            $this->memo = [];
        }

        return $this->memo[$wanted] = $this->lookupCandidates($wanted);
    }

    /**
     * @return list<int>
     */
    private function lookupCandidates(string $wanted): array
    {
        $pattern = $this->likePattern($wanted);
        if ($pattern === null) {
            return [];
        }

        $rows = Video::query()
            ->where('type', AbstractTvProvider::TYPE_TV)
            ->where('title', 'like', $pattern)
            ->orderByRaw('LENGTH(title)')
            ->limit(self::CANDIDATE_ROWS)
            ->pluck('title', 'id')
            ->all();

        $aliases = VideoAlias::query()
            ->join('videos', 'videos.id', '=', 'videos_aliases.videos_id')
            ->where('videos.type', AbstractTvProvider::TYPE_TV)
            ->where('videos_aliases.title', 'like', $pattern)
            ->limit(self::CANDIDATE_ROWS)
            ->get(['videos_aliases.videos_id', 'videos_aliases.title']);

        $ids = [];
        foreach ($rows as $id => $storedTitle) {
            if ($this->sameTitle((string) $storedTitle, $wanted)) {
                $ids[(int) $id] = true;
            }
        }
        foreach ($aliases as $alias) {
            if ($this->sameTitle((string) $alias->title, $wanted)) {
                $ids[(int) $alias->videos_id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  list<int>  $candidates
     */
    private function pick(array $candidates, ParsedTvRelease $parsed, bool $countryRequired): int
    {
        if (\count($candidates) > self::MAX_CANDIDATES) {
            return 0;
        }

        // Nothing to choose between: skip the per-show queries.
        if (\count($candidates) === 1 && ! $countryRequired) {
            return $candidates[0];
        }

        $ourCountry = $this->provider->normalizeCountry($parsed->countryCode());
        $bestId = 0;
        $bestScore = -INF;

        $videos = Video::query()->whereIn('id', $candidates)->orderBy('id')->get(['id', 'title', 'countries_id', 'started']);
        foreach ($videos as $video) {
            $videoId = (int) $video->id;
            $country = $this->countryOf($video);
            if ($countryRequired && ($country === '' || $country !== $ourCountry)) {
                continue;
            }

            $score = 0.0;
            if ($parsed->hasEpisodeReference()
                && $this->provider->getBySeasonEp($videoId, $parsed->season ?? -1, $parsed->episode ?? 0, (string) $parsed->airdate) !== false) {
                $score += 100.0;
            }

            $year = $this->yearOf($video);
            if ($parsed->year !== null && $year !== null) {
                $score += abs($parsed->year - $year) <= 1 ? 20.0 : -20.0;
            }

            if ($ourCountry !== '' && $country !== '') {
                $score += $ourCountry === $country ? 10.0 : -10.0;
            }

            // Last tie-breaker: the fuller record is the one the scrapers keep updating.
            $score += min(5.0, log10(TvEpisode::query()->where('videos_id', $videoId)->count() + 1));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $videoId;
            }
        }

        return $bestId;
    }

    private function sameTitle(string $storedTitle, string $wanted): bool
    {
        if ($this->provider->comparableTitle($storedTitle) === $wanted) {
            return true;
        }

        return $this->provider->comparableTitle($this->withoutQualifier($storedTitle)) === $wanted;
    }

    /** "Ghosts (US)" and "Doctor Who (2005)" without the qualifier. */
    private function withoutQualifier(string $title): string
    {
        return (string) preg_replace('/\s*\((?:(?:19|20)\d{2}|[A-Z]{2})\)$/', '', trim($title));
    }

    /**
     * A LIKE prefilter that every equal title passes: the first three characters of each
     * word, in order. Punctuation inside words ("Grey's") doesn't break a 3-char prefix
     * often, and "and" is skipped because stored titles may spell it "&".
     */
    private function likePattern(string $wanted): ?string
    {
        if ($wanted === '') {
            return null;
        }

        $pieces = [];
        foreach (explode(' ', $wanted) as $word) {
            if ($word === 'and') {
                continue;
            }
            $pieces[] = mb_substr($word, 0, 3);
        }

        return $pieces === [] ? null : '%'.implode('%', $pieces).'%';
    }

    private function countryOf(Video $video): string
    {
        $country = $this->provider->normalizeCountry((string) $video->countries_id);
        if ($country === '' && preg_match('/\((?P<code>[A-Z]{2})\)$/', trim((string) $video->title), $match)) {
            $country = $this->provider->normalizeCountry($match['code']);
        }

        return $country;
    }

    private function yearOf(Video $video): ?int
    {
        if (preg_match('/\((?P<year>(?:19|20)\d{2})\)$/', trim((string) $video->title), $match)) {
            return (int) $match['year'];
        }

        $started = (string) $video->started;

        return preg_match('/^(?P<year>(?:19|20)\d{2})/', $started, $match) ? (int) $match['year'] : null;
    }
}
