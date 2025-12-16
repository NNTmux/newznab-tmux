<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Checkers;

use App\Services\NameFixing\DTO\NameFixResult;
use App\Services\NameFixing\Patterns\TvPatterns;

/**
 * Name checker for TV show releases.
 *
 * Checks release names against TV-specific patterns including streaming services,
 * daily shows, sports, complete seasons, and anime.
 */
class TvNameChecker extends AbstractNameChecker
{
    protected int $priority = 10;
    protected string $name = 'TV';

    /**
     * {@inheritdoc}
     */
    protected function getPatterns(): array
    {
        return TvPatterns::getAllPatterns();
    }

    /**
     * {@inheritdoc}
     */
    protected function formatMethod(string $patternName): string
    {
        $methodMap = [
            'STREAMING_4K_HDR' => 'Title.SxxExx.4K.streaming.source.hdr.vcodec',
            'STREAMING_HD' => 'Title.SxxExx.res.streaming.source',
            'TV_SOURCE_GROUP' => 'Title.SxxExx.Text.source.group',
            'TV_WITH_YEAR' => 'Title.SxxExx.Text.year.group',
            'TV_RES_SOURCE_VCODEC' => 'Title.SxxExx.Text.resolution.source.vcodec.group',
            'TV_SOURCE_VCODEC' => 'Title.SxxExx.source.vcodec.group',
            'TV_AUDIO_SOURCE_RES_VCODEC' => 'Title.SxxExx.acodec.source.res.vcodec.group',
            'TV_YEAR_SEASON' => 'Title.year.###(season/episode).source.group',
            'DAILY_SHOW' => 'Daily show with date',
            'SPORTS' => 'Sports',
            'COMPLETE_SEASON' => 'Complete season',
            'ANIME' => 'Anime episode',
        ];

        return $methodMap[$patternName] ?? $this->patternNameToMethod($patternName);
    }

    /**
     * {@inheritdoc}
     *
     * Override to provide more specific TV checking with pattern priority.
     */
    public function check(object $release, string $textstring): ?NameFixResult
    {
        // Try streaming patterns first (most specific)
        foreach (TvPatterns::getStreamingPatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.95,
                );
            }
        }

        // Then try standard patterns
        foreach (TvPatterns::getStandardPatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.85,
                );
            }
        }

        // Finally try special format patterns
        $specialPatterns = [
            'DAILY_SHOW' => TvPatterns::DAILY_SHOW,
            'SPORTS' => TvPatterns::SPORTS,
            'COMPLETE_SEASON' => TvPatterns::COMPLETE_SEASON,
            'ANIME' => TvPatterns::ANIME,
        ];

        foreach ($specialPatterns as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.80,
                );
            }
        }

        return null;
    }
}

