<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BookInfo;
use App\Support\DTOs\BookParseResult;

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

        $titleScore = $this->similarity($parsed->title, (string) ($candidate['title'] ?? ''));
        $authorScore = $parsed->hasAuthor()
            ? $this->similarity((string) $parsed->author, (string) ($candidate['author'] ?? ''))
            : 0.5;
        $yearScore = $this->yearScore($parsed->year, (string) ($candidate['publishdate'] ?? ''));
        $publisherScore = ! empty($candidate['publisher']) ? 1.0 : 0.0;
        $coverScore = (int) ($candidate['cover'] ?? 0) === 1 ? 1.0 : 0.0;

        return (0.4 * $titleScore)
            + (0.3 * $authorScore)
            + (0.1 * $yearScore)
            + (0.1 * $publisherScore)
            + (0.1 * $coverScore);
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
        $value = strtolower($value);
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
