<?php

namespace Blacklight\libraries;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;

/**
 * Class TraktAPI
 * Retrive info from the Trakt API.
 */
class TraktAPI
{
    protected const API_URL = 'https://api.trakt.tv/';

    /**
     * @var array
     */
    private static $types = ['imdb', 'tmdb', 'trakt', 'tvdb'];

    /**
     * List of headers to send to Trakt.tv when making a request.
     *
     * @see http://docs.trakt.apiary.io/#introduction/required-headers
     *
     * @var array
     */
    private $requestHeaders;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

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

        $this->client = new Client();
    }

    /**
     * Fetches summary from trakt.tv for the TV show using the trakt ID/season/episode.
     *
     * @param  int  $id
     * @param  string  $season
     * @param  string  $ep
     * @param  string  $type
     * @return array|false
     *
     * @see    http://docs.trakt.apiary.io/#reference/episodes/summary/get-a-single-episode-for-a-show
     */
    public function episodeSummary($id, $season = '', $ep = '', $type = 'min')
    {
        switch ($type) {
            case 'aliases':
            case 'full':
            case 'full,aliases':
                $extended = $type;
                break;
            default:
                $extended = 'min';
        }

        $url = self::API_URL."shows/{$id}/seasons/{$season}/episodes/{$ep}";

        $array = $this->getJsonArray($url, $extended);
        if (! \is_array($array)) {
            return false;
        }

        return $array;
    }

    /**
     * Fetches weekend box office data from trakt.tv, updated every monday.
     *
     * @return array|false
     *
     * @see    http://docs.trakt.apiary.io/#reference/movies/box-office/get-the-weekend-box-office
     */
    public function getBoxOffice()
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
     * Fetches shows calendar from trakt.tv .
     *
     * @param  string  $start  Start date of calendar ie. 2015-09-01.Default value is today.
     * @param  int  $days  Number of days to lookup ahead. Default value is 7 days
     * @return array|false
     *
     * @see    http://docs.trakt.apiary.io/#reference/calendars/all-shows/get-shows
     */
    public function getCalendar($start = '', $days = 7)
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
     * Download JSON from Trakt, convert to array.
     *
     * @param  string  $URI  URI to download.
     * @param  string  $extended  Extended info from trakt tv.
     *                            Valid values:
     *                            'min'         Returns enough info to match locally. (Default)
     *                            'full'        Complete info for an item
     * @return array|false
     */
    private function getJsonArray($URI, $extended = 'min')
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
     * Fetches summary from trakt.tv for the movie.
     * Accept a title (the-big-lebowski-1998), a IMDB id, or a TMDB id.
     *
     * @param  string  $movie  Title or IMDB id.
     * @param  string  $type  imdbid:      Return only the IMDB ID (returns string)
     *                        full:        Return all extended properties. (returns array)
     *
     * @see    http://docs.trakt.apiary.io/#reference/movies/summary/get-a-movie
     *
     * @return array|string|false
     */
    public function movieSummary($movie = '', $type = 'imdbid')
    {
        switch ($type) {
            case 'full':
                $extended = $type;
                break;
            case 'imdbid':
            default:
                $extended = 'min';
        }
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
     * @param  int|string  $id
     * @param  string  $site
     * @param  int|string  $type
     * @return array|false|null
     */
    public function searchId($id, $site = 'trakt', $type = 0)
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
     * Fetches summary from trakt.tv for the show by doing a search.
     * Accepts a search string.
     *
     * @param  string  $show  title
     * @param  string  $type  show
     *
     * @see    http://docs.trakt.apiary.io/#reference/search/get-text-query-results
     *
     * @return array|false
     */
    public function showSearch($show = '', $type = 'show')
    {
        $searchUrl = self::API_URL.'search?query='.
                Str::slug($show).
                '&type='.$type;

        return $this->getJsonArray($searchUrl, '');
    }

    /**
     * Fetches summary from trakt.tv for the show.
     * Accepts a trakt slug (game-of-thrones), a IMDB id, or Trakt id.
     *
     * @param  string  $show  Title or IMDB id.
     * @param  string  $type  full:        Return all extended properties. (returns array)
     *
     * @see    http://docs.trakt.apiary.io/#reference/shows/summary/get-a-single-show
     *
     * @return array|false|null
     */
    public function showSummary($show = '', $type = 'full')
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
