<?php

declare(strict_types=1);

namespace App\Services\IGDB;

use App\Services\IGDB\Exceptions\IgdbHttpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Client
{
    protected const string TOKEN_CACHE_KEY = 'igdb:access_token';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function request(string $endpoint, string $query): array
    {
        $cacheLifetime = max(0, (int) config('igdb.cache_lifetime', 86400));
        $cacheKey = 'igdb:query:'.md5($endpoint.'|'.$query);

        if ($cacheLifetime > 0) {
            /** @var array<int, array<string, mixed>>|null $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $response = Http::baseUrl(rtrim((string) config('igdb.base_url', 'https://api.igdb.com/v4'), '/'))
            ->withHeaders([
                'Accept' => 'application/json',
                'Client-ID' => $this->getClientId(),
            ])
            ->withToken($this->getAccessToken())
            ->withBody($query, 'text/plain')
            ->timeout(30)
            ->post(ltrim($endpoint, '/'));

        if ($response->failed()) {
            throw new IgdbHttpException(
                message: 'IGDB request failed with status '.$response->status(),
                statusCode: $response->status(),
                responseBody: $response->body(),
            );
        }

        /** @var array<int, array<string, mixed>> $payload */
        $payload = $response->json() ?? [];

        if ($cacheLifetime > 0) {
            Cache::put($cacheKey, $payload, $cacheLifetime);
        }

        return $payload;
    }

    protected function getClientId(): string
    {
        $clientId = (string) config('igdb.credentials.client_id', '');

        if ($clientId === '') {
            throw new IgdbHttpException('IGDB client ID is not configured.', 0);
        }

        return $clientId;
    }

    protected function getAccessToken(): string
    {
        $cachedToken = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $clientSecret = (string) config('igdb.credentials.client_secret', '');
        if ($clientSecret === '') {
            throw new IgdbHttpException('IGDB client secret is not configured.', 0);
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post((string) config('igdb.token_url', 'https://id.twitch.tv/oauth2/token'), [
                'client_id' => $this->getClientId(),
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new IgdbHttpException(
                message: 'Unable to authenticate with Twitch for IGDB access.',
                statusCode: $response->status(),
                responseBody: $response->body(),
            );
        }

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            throw new IgdbHttpException('Twitch authentication response did not include an access token.', 0, $response->body());
        }

        $expiresIn = max(60, ((int) $response->json('expires_in', 3600)) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $expiresIn);

        return $token;
    }
}
