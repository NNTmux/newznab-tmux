<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenLibraryService
{
    protected const SEARCH_URL = 'https://openlibrary.org/search.json';

    protected const ISBN_URL = 'https://openlibrary.org/isbn/';

    protected Client $client;

    protected int $limit = 5;

    public function __construct(?Client $client = null)
    {
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

    /**
     * @return array<string, mixed>|null
     */
    public function findByIsbn(string $isbn): ?array
    {
        $isbn = strtoupper((string) preg_replace('/[^0-9X]/i', '', $isbn));
        if ($isbn === '') {
            return null;
        }

        $cacheKey = 'openlibrary_isbn_'.$isbn;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->client->get(self::ISBN_URL.rawurlencode($isbn).'.json');
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (! is_array($data)) {
                return null;
            }

            $book = $this->normalizeIsbnResult($data, $isbn);
            Cache::put($cacheKey, $book, now()->addHours(24));

            return $book;
        } catch (GuzzleException $e) {
            Log::error('OpenLibrary ISBN lookup error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function searchBook(string $query): ?array
    {
        $results = $this->searchBooks($query);

        return $results[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchBooks(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = 'openlibrary_search_'.md5($query.'_'.$this->limit);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->client->get(self::SEARCH_URL, [
                'query' => [
                    'q' => $query,
                    'limit' => $this->limit,
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (! is_array($data['docs'] ?? null)) {
                return [];
            }

            $results = [];
            foreach ($data['docs'] as $doc) {
                if (is_array($doc)) {
                    $results[] = $this->normalizeSearchResult($doc);
                }
            }

            Cache::put($cacheKey, $results, now()->addHours(12));

            return $results;
        } catch (GuzzleException $e) {
            Log::error('OpenLibrary search error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $book
     * @return array<string, mixed>
     */
    private function normalizeIsbnResult(array $book, string $isbn): array
    {
        $title = (string) ($book['title'] ?? '');
        $authors = [];
        if (is_array($book['authors'] ?? null)) {
            foreach ($book['authors'] as $author) {
                if (is_array($author) && isset($author['name'])) {
                    $authors[] = (string) $author['name'];
                }
            }
        }

        $publishDate = $this->normalizePublishDate($book['publish_date'] ?? null);
        $coverUrl = '';
        if (is_array($book['covers'] ?? null) && isset($book['covers'][0])) {
            $coverUrl = 'https://covers.openlibrary.org/b/id/'.(int) $book['covers'][0].'-L.jpg';
        }

        return [
            'title' => $title,
            'author' => implode(', ', $authors),
            'asin' => 'openlibrary:'.$isbn,
            'isbn' => strlen($isbn) === 13 ? $isbn : null,
            'ean' => strlen($isbn) === 10 ? $isbn : null,
            'url' => (string) ($book['url'] ?? ''),
            'salesrank' => '',
            'publisher' => is_array($book['publishers'] ?? null) ? (string) ($book['publishers'][0] ?? '') : '',
            'publishdate' => $publishDate,
            'pages' => isset($book['number_of_pages']) && is_numeric($book['number_of_pages']) ? (string) ((int) $book['number_of_pages']) : '',
            'overview' => '',
            'genre' => '',
            'coverurl' => $coverUrl,
            'cover' => $coverUrl !== '' ? 1 : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function normalizeSearchResult(array $doc): array
    {
        $isbn13 = null;
        $isbn10 = null;
        if (is_array($doc['isbn'] ?? null)) {
            foreach ($doc['isbn'] as $isbn) {
                $normalized = strtoupper((string) preg_replace('/[^0-9X]/i', '', (string) $isbn));
                if ($normalized === '') {
                    continue;
                }
                if (strlen($normalized) === 13 && $isbn13 === null) {
                    $isbn13 = $normalized;
                } elseif (strlen($normalized) === 10 && $isbn10 === null) {
                    $isbn10 = $normalized;
                }
            }
        }

        $identifier = $isbn13 ?? $isbn10 ?? (string) ($doc['key'] ?? md5((string) json_encode($doc)));
        $authorNames = is_array($doc['author_name'] ?? null)
            ? array_values(array_filter(array_map('strval', $doc['author_name'])))
            : [];

        $coverUrl = isset($doc['cover_i']) && is_numeric($doc['cover_i'])
            ? 'https://covers.openlibrary.org/b/id/'.(int) $doc['cover_i'].'-L.jpg'
            : '';

        return [
            'title' => (string) ($doc['title'] ?? ''),
            'author' => implode(', ', $authorNames),
            'asin' => 'openlibrary:'.$identifier,
            'isbn' => $isbn13,
            'ean' => $isbn10,
            'url' => isset($doc['key']) ? 'https://openlibrary.org'.$doc['key'] : '',
            'salesrank' => '',
            'publisher' => is_array($doc['publisher'] ?? null) ? (string) ($doc['publisher'][0] ?? '') : '',
            'publishdate' => isset($doc['first_publish_year']) ? ((int) $doc['first_publish_year']).'-01-01' : null,
            'pages' => '',
            'overview' => '',
            'genre' => '',
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
