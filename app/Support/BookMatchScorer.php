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
        $parsedIsbn = BookIsbn::normalize($parsed->isbn);
        $candidateIdentifiers = $this->candidateIdentifiers($candidate);
        if ($parsedIsbn !== null && $candidateIdentifiers !== []) {
            foreach ($candidateIdentifiers as $candidateIdentifier) {
                if (! BookIsbn::equivalent($parsedIsbn, $candidateIdentifier)) {
                    return 0.0;
                }
            }

            return 1.0;
        }

        if ($this->hasStructuralConflict($parsed, (string) ($candidate['title'] ?? ''))) {
            return 0.0;
        }

        $titleScore = $this->titleScore($parsed->title, (string) ($candidate['title'] ?? ''));
        $authorScore = $parsed->hasAuthor()
            ? $this->authorScore((string) $parsed->author, (string) ($candidate['author'] ?? ''))
            : 0.0;

        if ($parsed->hasAuthor()) {
            return (0.65 * $titleScore) + (0.35 * $authorScore);
        }

        return $titleScore;
    }

    public function scoreBookInfo(BookInfo $book, BookParseResult $parsed): float
    {
        return $this->score([
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'ean' => $book->ean,
            'publishdate' => $book->publishdate,
            'publisher' => $book->publisher,
            'cover' => $book->cover ? 1 : 0,
        ], $parsed);
    }

    public function titleScore(string $left, string $right): float
    {
        $leftVariants = $this->titleVariants($left);
        $rightVariants = $this->titleVariants($right);
        foreach ($leftVariants as $leftVariant) {
            foreach ($rightVariants as $rightVariant) {
                if ($leftVariant !== '' && $leftVariant === $rightVariant) {
                    return $leftVariant === $this->normalizeText($left)
                        && $rightVariant === $this->normalizeText($right)
                        ? 1.0
                        : 0.97;
                }
            }
        }

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

        $shared = array_intersect(array_unique($leftWords), array_unique($rightWords));
        $tokenScore = (2 * count($shared)) / (count(array_unique($leftWords)) + count(array_unique($rightWords)));

        similar_text($left, $right, $percent);
        $simTextScore = max(0.0, min(1.0, $percent / 100));

        return min(1.0, max($tokenScore, $simTextScore));
    }

    public function authorScore(string $left, string $right): float
    {
        $leftTokens = $this->tokens($left);
        $rightTokens = $this->tokens($right);
        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $leftCoverage = $this->authorTokenCoverage($leftTokens, $rightTokens);
        $rightCoverage = $this->authorTokenCoverage($rightTokens, $leftTokens);

        return ($leftCoverage + $rightCoverage) / 2;
    }

    public function hasStructuralConflict(BookParseResult $parsed, string $candidateTitle): bool
    {
        $parsedMarkers = $this->structuralMarkers($parsed->rawName.' '.$parsed->title);
        $candidateMarkers = $this->structuralMarkers($candidateTitle);
        foreach ($parsedMarkers as $type => $number) {
            if (isset($candidateMarkers[$type]) && $candidateMarkers[$type] !== $number) {
                return true;
            }
        }

        return false;
    }

    public function meaningfulTitleTokenCount(string $title): int
    {
        $stopWords = ['a', 'an', 'and', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with'];

        return count(array_filter(
            $this->tokens($title),
            static fn (string $token): bool => mb_strlen($token) > 1 && ! in_array($token, $stopWords, true)
        ));
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function workKey(array $candidate): string
    {
        $authorTokens = $this->tokens((string) ($candidate['author'] ?? ''));
        sort($authorTokens);

        return $this->normalizeText((string) ($candidate['title'] ?? '')).'|'
            .implode(' ', $authorTokens);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[._-]+/', ' ', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function titleVariants(string $value): array
    {
        $variants = [$this->normalizeText($value)];
        $parts = preg_split('/\s+(?::|-)\s+|:\s*/u', $value, 2);
        if (is_array($parts) && count($parts) === 2) {
            $variants[] = $this->normalizeText($parts[0]);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return list<string>
     */
    private function candidateIdentifiers(array $candidate): array
    {
        $identifiers = [];
        foreach (['isbn', 'ean'] as $field) {
            $isbn = BookIsbn::normalize(isset($candidate[$field]) ? (string) $candidate[$field] : null);
            if ($isbn !== null) {
                $identifiers[] = $isbn;
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $normalized = $this->normalizeText($value);

        return $normalized === '' ? [] : array_values(array_unique(explode(' ', $normalized)));
    }

    /**
     * @param  list<string>  $needles
     * @param  list<string>  $haystack
     */
    private function authorTokenCoverage(array $needles, array $haystack): float
    {
        $matched = 0;
        foreach ($needles as $needle) {
            foreach ($haystack as $candidate) {
                if ($needle === $candidate
                    || (mb_strlen($needle) === 1 && str_starts_with($candidate, $needle))
                    || (mb_strlen($candidate) === 1 && str_starts_with($needle, $candidate))) {
                    $matched++;

                    break;
                }
            }
        }

        return $matched / count($needles);
    }

    /**
     * @return array<string, int>
     */
    private function structuralMarkers(string $value): array
    {
        $markers = [];
        if (preg_match('/\b(?:book|series|vol(?:ume)?)\.?\s*#?\s*(\d{1,3})\b/i', $value, $match) === 1) {
            $markers['volume'] = (int) $match[1];
        }
        if (preg_match('/\b(\d{1,3})(?:st|nd|rd|th)?\s+(?:edition|ed\.?)\b/i', $value, $match) === 1) {
            $markers['edition'] = (int) $match[1];
        }

        return $markers;
    }
}
