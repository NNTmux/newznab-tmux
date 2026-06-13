<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IsbnDbService
{
    protected const API_URL = 'https://api2.isbndb.com';

    protected string $apiKey;

    protected int $pageSize = 5;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = trim($apiKey ?? (string) config('nntmux_api.isbndb_api_key', ''));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function searchBook(string $query): ?array
    {
        $books = $this->searchBooks($query);

        return $books[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchBooks(string $query): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->isConfigured()) {
            return [];
        }

        $cacheKey = 'isbndb_search_'.md5($query.'_'.$this->pageSize);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->request('/books/'.rawurlencode($query), [
            'shouldMatchAll' => true,
            'pageSize' => $this->pageSize,
        ]);

        $books = $response['data'] ?? null;
        if (! is_array($books) || $books === []) {
            return [];
        }

        $normalized = [];
        foreach ($books as $book) {
            if (is_array($book)) {
                $normalized[] = $this->normalizeBookResult($book);
            }
        }

        Cache::put($cacheKey, $normalized, now()->addHours(24));

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIsbn(string $isbn): ?array
    {
        $isbn = $this->normalizeIsbn($isbn);
        if ($isbn === '' || ! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'isbndb_isbn_'.$isbn;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->request('/book/'.rawurlencode($isbn));
        $bookRaw = $response['book'] ?? null;
        if (! is_array($bookRaw) || $bookRaw === []) {
            return null;
        }

        $book = $this->normalizeBookResult($bookRaw);
        Cache::put($cacheKey, $book, now()->addHours(24));

        return $book;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    protected function request(string $path, array $query = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'NNTmux/1.0',
                    'Authorization' => $this->apiKey,
                ])
                ->get(self::API_URL.$path, $query);

            $statusCode = $response->status();
            $this->logRateLimitHeaders($response);

            if ($statusCode === 404) {
                return null;
            }

            if ($statusCode === 401 || $statusCode === 429) {
                Log::warning("ISBNdb request failed with status {$statusCode}");

                return null;
            }

            if (! $response->successful()) {
                Log::warning("ISBNdb request returned status {$statusCode}");

                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::error('ISBNdb API request error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    private function normalizeBookResult(array $book): array
    {
        $isbn13 = isset($book['isbn13']) ? $this->normalizeIsbn((string) $book['isbn13']) : '';
        if ($isbn13 === '' && isset($book['isbn'])) {
            $isbn13 = $this->normalizeIsbn((string) $book['isbn']);
        }

        $isbn10 = isset($book['isbn10']) ? $this->normalizeIsbn((string) $book['isbn10']) : '';

        $authors = is_array($book['authors'] ?? null)
            ? array_values(array_filter(array_map('strval', $book['authors'])))
            : [];
        $subjects = is_array($book['subjects'] ?? null)
            ? array_values(array_filter(array_map('strval', $book['subjects'])))
            : [];

        $identifier = $isbn13 !== '' ? $isbn13 : ($isbn10 !== '' ? $isbn10 : md5((string) json_encode($book)));
        $coverUrl = (string) ($book['image'] ?? $book['image_original'] ?? '');
        $overview = trim((string) ($book['synopsis'] ?? $book['overview'] ?? $book['excerpt'] ?? ''));

        return [
            'title' => (string) ($book['title'] ?? ''),
            'author' => implode(', ', $authors),
            'asin' => 'isbndb:'.$identifier,
            'isbn' => $isbn13 !== '' ? $isbn13 : null,
            'ean' => $isbn10 !== '' ? $isbn10 : null,
            'url' => '',
            'salesrank' => '',
            'publisher' => (string) ($book['publisher'] ?? ''),
            'publishdate' => $this->normalizePublishDate($book['date_published'] ?? null),
            'pages' => isset($book['pages']) && is_numeric($book['pages']) ? (string) ((int) $book['pages']) : '',
            'overview' => strip_tags($overview),
            'genre' => implode(', ', $subjects),
            'coverurl' => $coverUrl,
            'cover' => $coverUrl !== '' ? 1 : 0,
        ];
    }

    private function normalizePublishDate(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeIsbn(string $isbn): string
    {
        return strtoupper((string) preg_replace('/[^0-9X]/i', '', $isbn));
    }

    private function logRateLimitHeaders(Response $response): void
    {
        $rateLimit = $response->header('ratelimit') ?? '';
        $rateLimitPolicy = $response->header('ratelimit-policy') ?? '';

        if ($rateLimit === '' && $rateLimitPolicy === '') {
            return;
        }

        preg_match('/remaining[=:\s"]+(\d+)/i', $rateLimit, $remainingMatches);
        $remaining = isset($remainingMatches[1]) ? (int) $remainingMatches[1] : null;

        if ($remaining !== null && $remaining <= 50) {
            Log::warning('ISBNdb API rate limit getting low', [
                'ratelimit' => $rateLimit,
                'ratelimit_policy' => $rateLimitPolicy,
            ]);

            return;
        }

        Log::debug('ISBNdb API rate limit status', [
            'ratelimit' => $rateLimit,
            'ratelimit_policy' => $rateLimitPolicy,
        ]);
    }
}
