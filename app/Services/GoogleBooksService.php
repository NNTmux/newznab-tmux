<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleBooksService
{
    protected const API_URL = 'https://www.googleapis.com/books/v1/volumes';

    protected Client $client;

    protected ?string $apiKey;

    protected int $maxResults = 5;

    public function __construct(?Client $client = null, ?string $apiKey = null)
    {
        $this->apiKey = trim($apiKey ?? (string) config('nntmux_api.google_books_api_key', '')) ?: null;

        $this->client = $client ?? new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'NNTmux/1.0',
            ],
        ]);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== null;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIsbn(string $isbn): ?array
    {
        $books = $this->searchBooks('', null, null, $isbn);

        return $books[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function searchBook(string $query, ?string $title = null, ?string $author = null): ?array
    {
        $books = $this->searchBooks($query, $title, $author);

        return $books[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchBooks(string $query, ?string $title = null, ?string $author = null, ?string $isbn = null): array
    {
        $query = trim($query);
        $title = $title !== null ? trim($title) : null;
        $author = $author !== null ? trim($author) : null;
        $isbn = $isbn !== null ? strtoupper((string) preg_replace('/[^0-9X]/i', '', $isbn)) : null;

        $searchQuery = $this->buildSearchQuery($query, $title, $author, $isbn);
        if ($searchQuery === '') {
            return [];
        }

        $cacheKey = 'google_books_search_'.md5($searchQuery.'_'.$this->maxResults.'_'.$this->apiKey);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->request([
            'q' => $searchQuery,
            'maxResults' => $this->maxResults,
            'printType' => 'books',
        ]);
        if (! is_array($response['items'] ?? null)) {
            return [];
        }

        $books = [];
        foreach ($response['items'] as $item) {
            if (is_array($item)) {
                $books[] = $this->normalizeBookResult($item);
            }
        }

        Cache::put($cacheKey, $books, now()->addHours(12));

        return $books;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    protected function request(array $query): ?array
    {
        try {
            if ($this->apiKey !== null) {
                $query['key'] = $this->apiKey;
            }

            $response = $this->client->get(self::API_URL, ['query' => $query]);
            if ($response->getStatusCode() !== 200) {
                Log::warning('Google Books API request failed', ['status' => $response->getStatusCode()]);

                return null;
            }

            $decoded = json_decode($response->getBody()->getContents(), true);

            return is_array($decoded) ? $decoded : null;
        } catch (GuzzleException $e) {
            Log::error('Google Books API request error: '.$e->getMessage());

            return null;
        }
    }

    private function buildSearchQuery(string $query, ?string $title, ?string $author, ?string $isbn): string
    {
        if ($isbn !== null && $isbn !== '') {
            return 'isbn:'.$isbn;
        }

        $parts = [];
        if ($title !== null && $title !== '') {
            $parts[] = 'intitle:'.$title;
        }
        if ($author !== null && $author !== '') {
            $parts[] = 'inauthor:'.$author;
        }

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeBookResult(array $item): array
    {
        $volumeInfo = is_array($item['volumeInfo'] ?? null) ? $item['volumeInfo'] : [];
        $industryIdentifiers = is_array($volumeInfo['industryIdentifiers'] ?? null) ? $volumeInfo['industryIdentifiers'] : [];

        $isbn13 = '';
        $isbn10 = '';
        foreach ($industryIdentifiers as $identifier) {
            if (! is_array($identifier)) {
                continue;
            }
            $type = (string) ($identifier['type'] ?? '');
            $value = strtoupper((string) preg_replace('/[^0-9X]/i', '', (string) ($identifier['identifier'] ?? '')));
            if ($type === 'ISBN_13' && $value !== '') {
                $isbn13 = $value;
            }
            if ($type === 'ISBN_10' && $value !== '') {
                $isbn10 = $value;
            }
        }

        $identifier = $isbn13 !== '' ? $isbn13 : ($isbn10 !== '' ? $isbn10 : (string) ($item['id'] ?? md5(json_encode($item) ?: '')));
        $authors = is_array($volumeInfo['authors'] ?? null)
            ? array_values(array_filter(array_map('strval', $volumeInfo['authors'])))
            : [];
        $categories = is_array($volumeInfo['categories'] ?? null)
            ? array_values(array_filter(array_map('strval', $volumeInfo['categories'])))
            : [];
        $imageLinks = is_array($volumeInfo['imageLinks'] ?? null) ? $volumeInfo['imageLinks'] : [];
        $coverUrl = (string) ($imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? '');

        return [
            'title' => (string) ($volumeInfo['title'] ?? ''),
            'author' => implode(', ', $authors),
            'asin' => 'googlebooks:'.$identifier,
            'isbn' => $isbn13 !== '' ? $isbn13 : null,
            'ean' => $isbn10 !== '' ? $isbn10 : null,
            'url' => (string) ($volumeInfo['infoLink'] ?? ''),
            'salesrank' => '',
            'publisher' => (string) ($volumeInfo['publisher'] ?? ''),
            'publishdate' => $this->normalizePublishDate($volumeInfo['publishedDate'] ?? null),
            'pages' => isset($volumeInfo['pageCount']) && is_numeric($volumeInfo['pageCount']) ? (string) ((int) $volumeInfo['pageCount']) : '',
            'overview' => strip_tags((string) ($volumeInfo['description'] ?? '')),
            'genre' => implode(', ', $categories),
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
}
