<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BookInfo;
use App\Support\Data\BookParseResult;

class BookMatchScorer
{
    /**
     * @param  array<string, mixed>  $candidate
     */
    public function score(array $candidate, BookParseResult $parsed): float
    {
        $candidateIsbn = $this->normalizeIsbn((string) ($candidate['isbn'] ?? ''));
        $parsedIsbn = $this->normalizeIsbn((string) ($parsed->isbn ?? ''));
        if ($parsedIsbn !== '' && $candidateIsbn !== '' && $parsedIsbn === $candidateIsbn) {
            return 1.0;
        }

        $titleScore = $this->titleSimilarity($parsed->title, (string) ($candidate['title'] ?? ''));
        $authorScore = $parsed->hasAuthor()
            ? $this->similarity((string) $parsed->author, (string) ($candidate['author'] ?? ''))
            : 0.0;
        $yearScore = $this->yearScore($parsed->year, (string) ($candidate['publishdate'] ?? ''));
        $publisherScore = ! empty($candidate['publisher']) ? 1.0 : 0.0;
        $coverScore = (int) ($candidate['cover'] ?? 0) === 1 ? 1.0 : 0.0;

        if ($parsed->hasAuthor()) {
            return (0.45 * $titleScore)
                + (0.30 * $authorScore)
                + (0.10 * $yearScore)
                + (0.08 * $publisherScore)
                + (0.07 * $coverScore);
        }

        // When the release has no reliable author, avoid over-trusting
        // "has cover/publisher" signals for weak title matches.
        return (0.80 * $titleScore)
            + (0.10 * $yearScore)
            + (0.05 * $publisherScore)
            + (0.05 * $coverScore);
    }

    public function scoreBookInfo(BookInfo $book, BookParseResult $parsed): float
    {
        return $this->score([
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'publishdate' => $book->publishdate,
            'publisher' => $book->publisher,
            'cover' => $book->cover ? 1 : 0,
        ], $parsed);
    }

    private function titleSimilarity(string $left, string $right): float
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);
        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 1.0;
        }

        $leftWords = array_filter(explode(' ', $left), fn (string $w): bool => mb_strlen($w) > 1);
        $rightWords = array_filter(explode(' ', $right), fn (string $w): bool => mb_strlen($w) > 1);
        if ($leftWords === [] || $rightWords === []) {
            return 0.0;
        }

        $shared = array_intersect($leftWords, $rightWords);
        $total = max(count($leftWords), count($rightWords));
        $jaccardScore = count($shared) / $total;

        similar_text($left, $right, $percent);
        $simTextScore = max(0.0, min(1.0, $percent / 100));

        $lengthRatio = min(mb_strlen($left), mb_strlen($right)) / max(mb_strlen($left), mb_strlen($right));
        $lengthPenalty = $lengthRatio < 0.5 ? 0.7 : 1.0;

        return min(1.0, ((0.5 * $jaccardScore) + (0.5 * $simTextScore)) * $lengthPenalty);
    }

    private function similarity(string $left, string $right): float
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);
        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }

    private function yearScore(?int $parsedYear, string $candidatePublishDate): float
    {
        if ($parsedYear === null) {
            return 0.5;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $candidatePublishDate, $matches) !== 1) {
            return 0.0;
        }

        $candidateYear = (int) $matches[0];
        if ($parsedYear === $candidateYear) {
            return 1.0;
        }

        if (abs($parsedYear - $candidateYear) <= 1) {
            return 0.6;
        }

        return 0.0;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[._-]+/', ' ', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function normalizeIsbn(string $value): string
    {
        return strtoupper((string) preg_replace('/[^0-9X]/i', '', $value));
    }
}
