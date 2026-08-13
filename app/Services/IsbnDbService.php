<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookProviderException;
use App\Support\BookIsbn;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class IsbnDbService
{
    public const COOLDOWN_CACHE_KEY = 'isbndb_provider_cooldown';

    protected const API_URL = 'https://api2.isbndb.com';

    protected Client $client;

    protected string $apiKey;

    protected int $pageSize = 10;

    public function __construct(?Client $client = null, ?string $apiKey = null)
    {
        $this->apiKey = trim($apiKey ?? (string) config('nntmux_api.isbndb_api_key', ''));

        $this->client = $client ?? new Client([
            'base_uri' => self::API_URL,
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'NNTmux/1.0',
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function searchBook(string $query, ?string $author = null, bool $shouldMatchAll = true): ?array
    {
        $books = $this->searchBooks($query, $author, $shouldMatchAll);

        return $books[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchBooks(string $query, ?string $author = null, bool $shouldMatchAll = true): array
    {
        $query = trim($query);
        $author = $author !== null ? trim($author) : null;
        if ($query === '' || ! $this->isConfigured()) {
            return [];
        }

        if (Cache::has(self::COOLDOWN_CACHE_KEY)) {
            throw new BookProviderException('isbndb', 'ISBNdb is temporarily cooling down after a failed request.');
        }

        $cacheKey = 'isbndb_search_'.md5(implode('|', [
            mb_strtolower((string) preg_replace('/\s+/', ' ', $query)),
            mb_strtolower((string) preg_replace('/\s+/', ' ', (string) $author)),
            $shouldMatchAll ? 'all' : 'any',
            (string) $this->pageSize,
        ]));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $requestQuery = [
            'shouldMatchAll' => $shouldMatchAll ? 'true' : 'false',
            'pageSize' => $this->pageSize,
            'column' => 'title',
        ];

        $response = $this->request('/books/'.rawurlencode($query), $requestQuery);

        $books = $response['data'] ?? null;
        if (! is_array($books)) {
            Cache::put(self::COOLDOWN_CACHE_KEY, true, now()->addMinutes(5));

            throw new BookProviderException('isbndb', 'ISBNdb search response did not contain a data array.', 200, 300);
        }
        if ($books === []) {
            Cache::put($cacheKey, [], now()->addHours(6));

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
        $isbn = BookIsbn::normalize($isbn);
        if ($isbn === null || ! $this->isConfigured()) {
            return null;
        }

        if (Cache::has(self::COOLDOWN_CACHE_KEY)) {
            throw new BookProviderException('isbndb', 'ISBNdb is temporarily cooling down after a failed request.');
        }

        $cacheKey = 'isbndb_isbn_'.$isbn;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->request('/book/'.rawurlencode($isbn));
        $bookRaw = $response['book'] ?? null;
        if (! is_array($bookRaw) || $bookRaw === []) {
            Cache::put(self::COOLDOWN_CACHE_KEY, true, now()->addMinutes(5));

            throw new BookProviderException('isbndb', 'ISBNdb ISBN response did not contain a book object.', 200, 300);
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
            $response = $this->client->get($path, [
                'http_errors' => false,
                'headers' => [
                    'Authorization' => $this->apiKey,
                ],
                'query' => $query,
            ]);

            $statusCode = $response->getStatusCode();
            $this->logRateLimitHeaders($response);

            if ($statusCode === 404) {
                return null;
            }

            if ($statusCode !== 200) {
                $retryAfter = $this->retryAfterSeconds($response);
                if ($statusCode === 429 || $statusCode >= 500) {
                    $retryAfter ??= 300;
                    Cache::put(self::COOLDOWN_CACHE_KEY, true, now()->addSeconds($retryAfter));
                }

                throw new BookProviderException(
                    'isbndb',
                    "ISBNdb request returned status {$statusCode}.",
                    $statusCode,
                    $retryAfter,
                );
            }

            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                throw new BookProviderException('isbndb', 'ISBNdb returned an invalid response payload.', $statusCode);
            }

            return $data;
        } catch (BookProviderException $exception) {
            throw $exception;
        } catch (JsonException $exception) {
            Cache::put(self::COOLDOWN_CACHE_KEY, true, now()->addMinutes(5));

            throw new BookProviderException(
                'isbndb',
                'ISBNdb returned malformed JSON.',
                200,
                300,
                $exception,
            );
        } catch (GuzzleException $e) {
            Cache::put(self::COOLDOWN_CACHE_KEY, true, now()->addMinutes(5));

            throw new BookProviderException(
                'isbndb',
                'ISBNdb API request error: '.$e->getMessage(),
                null,
                300,
                $e,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    private function normalizeBookResult(array $book): array
    {
        $isbn13 = isset($book['isbn13']) ? BookIsbn::normalize((string) $book['isbn13']) : null;
        if ($isbn13 === null && isset($book['isbn'])) {
            $isbn13 = BookIsbn::normalize((string) $book['isbn']);
        }
        if ($isbn13 !== null && strlen($isbn13) === 10) {
            $isbn13 = BookIsbn::toIsbn13($isbn13);
        }

        $isbn10 = isset($book['isbn10']) ? BookIsbn::normalize((string) $book['isbn10']) : null;
        if ($isbn10 !== null && strlen($isbn10) === 13) {
            $isbn10 = BookIsbn::toIsbn10($isbn10);
        }

        $authors = is_array($book['authors'] ?? null)
            ? array_values(array_filter(array_map('strval', $book['authors'])))
            : [];
        $subjects = is_array($book['subjects'] ?? null)
            ? array_values(array_filter(array_map('strval', $book['subjects'])))
            : [];

        $identifier = $isbn13 ?? $isbn10 ?? md5((string) json_encode($book));
        $coverUrl = (string) ($book['image'] ?? $book['image_original'] ?? '');
        $overview = trim((string) ($book['synopsis'] ?? $book['overview'] ?? $book['excerpt'] ?? ''));

        return [
            'title' => (string) ($book['title'] ?? ''),
            'author' => implode(', ', $authors),
            'asin' => 'isbndb:'.$identifier,
            'isbn' => $isbn13,
            'ean' => $isbn10,
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

    private function logRateLimitHeaders(ResponseInterface $response): void
    {
        $rateLimit = $response->getHeaderLine('ratelimit');
        $rateLimitPolicy = $response->getHeaderLine('ratelimit-policy');

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

    private function retryAfterSeconds(ResponseInterface $response): ?int
    {
        $retryAfter = trim($response->getHeaderLine('Retry-After'));
        if ($retryAfter === '') {
            return null;
        }
        if (ctype_digit($retryAfter)) {
            return max(1, (int) $retryAfter);
        }

        $retryAt = strtotime($retryAfter);

        return $retryAt === false ? null : max(1, $retryAt - time());
    }
}
