<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Checkers;

use App\Services\NameFixing\DTO\NameFixResult;
use App\Services\NameFixing\Patterns\MoviePatterns;

/**
 * Name checker for Movie releases.
 *
 * Checks release names against movie-specific patterns including 4K/UHD,
 * streaming services, and standard HD releases.
 */
class MovieNameChecker extends AbstractNameChecker
{
    protected int $priority = 20;

    protected string $name = 'Movie';

    /**
     * {@inheritdoc}
     */
    protected function getPatterns(): array
    {
        return MoviePatterns::getAllPatterns();
    }

    /**
     * {@inheritdoc}
     */
    protected function formatMethod(string $patternName): string
    {
        $methodMap = [
            'UHD_HDR' => '4K/UHD with HDR',
            'UHD_REMUX' => '4K REMUX',
            'STREAMING_4K' => '4K Streaming service',
            'STREAMING_HD' => 'HD Streaming service',
            'YEAR_RES_VCODEC' => 'Title.year.Text.res.vcod.group',
            'YEAR_SOURCE_VCODEC_RES' => 'Title.year.source.vcodec.res.group',
            'YEAR_SOURCE_VCODEC_ACODEC' => 'Title.year.source.vcodec.acodec.group',
            'RES_SOURCE_ACODEC_VCODEC' => 'Title.year.resolution.source.acodec.vcodec.group',
            'RES_ACODEC_SOURCE_YEAR' => 'Title.resolution.acodec.eptitle.source.year.group',
            'MULTI_LANGUAGE' => 'Title.language.year.acodec.src',
            'GENERIC' => 'Title.year.res.source.group',
        ];

        return $methodMap[$patternName] ?? $this->patternNameToMethod($patternName);
    }

    /**
     * {@inheritdoc}
     *
     * Override to provide more specific movie checking with pattern priority.
     */
    public function check(object $release, string $textstring): ?NameFixResult
    {
        // Try 4K/UHD patterns first (most specific and modern)
        foreach (MoviePatterns::get4KPatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.95,
                );
            }
        }

        // Then try standard patterns in order of specificity
        $standardPatterns = [
            'STREAMING_HD' => MoviePatterns::STREAMING_HD,
            'YEAR_RES_VCODEC' => MoviePatterns::YEAR_RES_VCODEC,
            'YEAR_SOURCE_VCODEC_RES' => MoviePatterns::YEAR_SOURCE_VCODEC_RES,
            'YEAR_SOURCE_VCODEC_ACODEC' => MoviePatterns::YEAR_SOURCE_VCODEC_ACODEC,
            'RES_SOURCE_ACODEC_VCODEC' => MoviePatterns::RES_SOURCE_ACODEC_VCODEC,
            'RES_ACODEC_SOURCE_YEAR' => MoviePatterns::RES_ACODEC_SOURCE_YEAR,
            'MULTI_LANGUAGE' => MoviePatterns::MULTI_LANGUAGE,
            'GENERIC' => MoviePatterns::GENERIC,
        ];

        foreach ($standardPatterns as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                $confidence = $patternName === 'GENERIC' ? 0.70 : 0.85;

                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: $confidence,
                );
            }
        }

        return null;
    }
}
