<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Services\Search\DTO\SearchCursor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use InvalidArgumentException;

final readonly class SearchCursorCodec
{
    public function __construct(private StringEncrypter $encrypter) {}

    public function encode(SearchCursor $cursor): string
    {
        return $this->encrypter->encryptString(json_encode([
            'sort' => $cursor->sortValues,
            'total' => $cursor->total,
            'query' => $cursor->queryHash,
            'driver' => $cursor->driver,
            'generation' => $cursor->indexGeneration,
            'expires' => $cursor->expiresAt,
        ], JSON_THROW_ON_ERROR));
    }

    public function decode(string $token): SearchCursor
    {
        try {
            $payload = json_decode($this->encrypter->decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $e) {
            throw new InvalidArgumentException('Invalid search cursor.', previous: $e);
        }

        if (! is_array($payload) || ! is_array($payload['sort'] ?? null) || (int) ($payload['expires'] ?? 0) < time()) {
            throw new InvalidArgumentException('Invalid or expired search cursor.');
        }

        return new SearchCursor(
            sortValues: array_values($payload['sort']),
            total: (int) ($payload['total'] ?? 0),
            queryHash: (string) ($payload['query'] ?? ''),
            driver: (string) ($payload['driver'] ?? ''),
            indexGeneration: (string) ($payload['generation'] ?? ''),
            expiresAt: (int) $payload['expires'],
        );
    }
}
