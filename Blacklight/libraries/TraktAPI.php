<?php

namespace Blacklight\libraries;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;

/**
 * Class TraktAPI
 * Retrive info from the Trakt API.
 */
class TraktAPI
{
    protected const API_URL = 'https://api.trakt.tv/';

    private static array $types = ['imdb', 'tmdb', 'trakt', 'tvdb'];

    /**
     * List of headers to send to Trakt.tv when making a request.
     *
     * @see http://docs.trakt.apiary.io/#introduction/required-headers
     */
    private array $requestHeaders;

    protected Client $client;

    /**
     * Construct. Assign passed request headers.  Headers should be complete with API key.
     *
     *
     *
     * @throws \Exception
     */
    public function __construct($headers)
    {
        if (empty($headers)) {
            // Can't work without headers.
            exit;
        }
        $this->requestHeaders = $headers;

        $this->client = new Client;
    }

    /**
     * @throws GuzzleException
     */
    public function episodeSummary(int $id, string $season = '', string $ep = '', string $type = 'min'): bool|array
    {
        $extended = match ($type) {
            'aliases', 'full', 'full,aliases' => $type,
            default => 'min',
        };

        $url = self::API_URL."shows/{$id}/seasons/{$season}/episodes/{$ep}";

        $array = $this->getJsonArray($url, $extended);
        if (! \is_array($array)) {
            return false;
        }

        return $array;
    }

    /**
     * @throws GuzzleException+
     */
    public function getBoxOffice(): bool|array
    {
        $array = $this->getJsonArray(
            self::API_URL.'movies/boxoffice'
        );
        if (! $array) {
            return false;
        }

        return $array;
    }

    /**
     * @throws GuzzleException
     */
    public function getCalendar(string $start = '', int $days = 7): bool|array
    {
        $array = $this->getJsonArray(
            self::API_URL.'calendars/all/shows/'.$start.'/'.$days
        );
        if (! $array) {
            return false;
        }

        return $array;
    }

    /**
     * @return array|false
     *
     * @throws GuzzleException
     */
    private function getJsonArray(string $URI, string $extended = 'min'): bool|array
    {
        if ($extended === '') {
            $extendedString = '';
        } else {
            $extendedString = '?extended='.$extended;
        }

        if (! empty($this->requestHeaders)) {
            try {
                $json = $this->client->get(
                    $URI.$extendedString,
                    [
                        'headers' => $this->requestHeaders,
                    ]
                )->getBody()->getContents();
            } catch (RequestException $e) {
                return false;
            } catch (\RuntimeException $e) {
                return false;
            }

            if (! empty($json)) {
                $json = json_decode($json, true);
                if (! \is_array($json) || (isset($json['status']) && $json['status'] === 'failure')) {
                    return false;
                }

                return $json;
            }
        }

        return false;
    }

    /**
     * @return array|bool|mixed
     *
     * @throws GuzzleException
     */
    public function movieSummary(string $movie = '', string $type = 'imdbid'): mixed
    {
        $extended = match ($type) {
            'full' => $type,
            default => 'min',
        };
        $array = $this->getJsonArray(self::API_URL.'movies/'.Str::slug($movie), $extended);
        if (! $array) {
            return false;
        }
        if ($type === 'imdbid' && isset($array['ids']['imdb'])) {
            return $array['ids']['imdb'];
        }

        return $array;
    }

    /**
     * @return array|bool|void
     *
     * @throws GuzzleException
     */
    public function searchId(int|string $id, string $site = 'trakt', int|string $type = 0)
    {
        if (! \in_array($site, self::$types, false) || ! ctype_digit($id)) {
            return;
        }
        if ($site === 'imdb') {
            $id = 'tt'.$id;
        }

        switch (true) {
            case $site === 'trakt' && ($type === 0 || $type === 2):
                $type = $site.'-show';
                break;
            case $site === 'trakt' && $type === 1:
                $type = $site.'-movie';
                break;
            case $site === 'trakt' && $type === -1:
                $type = $site.'-episode';
                break;
            default:
        }

        $url = self::API_URL.'search?id_type='.$type.'&id='.$id;

        return $this->getJsonArray($url, '');
    }

    /**
     * @throws GuzzleException
     */
    public function showSearch(string $show = '', string $type = 'show'): bool|array
    {
        $searchUrl = self::API_URL.'search?query='.
                Str::slug($show).
                '&type='.$type;

        return $this->getJsonArray($searchUrl, '');
    }

    /**
     * @return array|bool|void
     *
     * @throws GuzzleException
     */
    public function showSummary(string $show = '', string $type = 'full')
    {
        if (empty($show)) {
            return;
        }
        $showUrl = self::API_URL.'shows/'.Str::slug($show);

        if ($type === 'full') {
            $extended = 'full';
        } else {
            $extended = '';
        }

        return $this->getJsonArray($showUrl, $extended);
    }
}
