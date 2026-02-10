<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use voku\helper\HtmlDomParser;

class ImdbScraper
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; NNTmuxBot/1.0; +https://github.com/NNTmux)',
            ],
        ]);
    }

    /**
     * Fetch a movie by IMDB numeric ID.
     *
     * @param  string  $id  Numeric part without 'tt'.
     */
    public function fetchById(string $id): array|false
    {
        $id = preg_replace('/[^0-9]/', '', $id);
        if ($id === '') {
            return false;
        }
        $cacheKey = 'imdb_scrape_id_'.$id;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        $url = 'https://www.imdb.com/title/tt'.$id.'/';
        try {
            $res = $this->client->get($url);
            $html = (string) $res->getBody();
            $dom = HtmlDomParser::str_get_html($html);
            if (! $dom) {
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            $titleNode = $dom->findOne('h1');
            $title = trim($titleNode->text() ?? '');
            if ($title === '') {
                Cache::put($cacheKey, false, now()->addHours(6));

                return false;
            }

            // Year
            $year = '';
            $yearNode = $dom->findOne("span[data-testid='title-details-releasedate'] a");
            if ($yearNode) {
                if (preg_match('/(19|20)\d{2}/', $yearNode->text(), $m)) {
                    $year = $m[0];
                }
            }
            if ($year === '') {
                $altYearNode = $dom->findOne("span[data-testid='title-details-releasedate']");
                if ($altYearNode && preg_match('/(19|20)\d{2}/', $altYearNode->text(), $m)) {
                    $year = $m[0];
                }
            }

            // Plot
            $plot = '';
            $plotNode = $dom->findOne("span[data-testid='plot-l']");
            if (! $plotNode) {
                $plotNode = $dom->findOne("span[data-testid='plot-xl']");
            }
            if ($plotNode) {
                $plot = trim($plotNode->text());
            }

            // Rating
            $rating = '';
            $ratingNode = $dom->findOne("div[data-testid='hero-rating-bar__aggregate-rating__score'] span");
            if ($ratingNode) {
                $rating = trim($ratingNode->text());
            }
            $rating = preg_replace('/[^0-9\.]/', '', $rating);

            // Poster
            $cover = '';
            $posterNode = $dom->findOne("div[data-testid='hero-media__poster'] img");
            if ($posterNode) {
                $cover = $posterNode->getAttribute('src');
            }
            if ($cover !== '' && str_contains($cover, '._V1_')) {
                // Attempt higher res by removing size suffix
                $cover = preg_replace('/\._V1_.*\./', '.', $cover);
            }

            // Genres
            $genres = [];
            foreach ($dom->find("div[data-testid='genres'] a") as $g) {
                $gt = trim($g->text());
                if ($gt !== '') {
                    $genres[] = $gt;
                }
            }

            // Directors
            $directors = [];
            foreach ($dom->find("li[data-testid='title-pc-principal-credit']:contains(Director) a") as $d) {
                $dt = trim($d->text());
                if ($dt !== '') {
                    $directors[] = $dt;
                }
            }
            if (empty($directors)) {
                foreach ($dom->find("li[data-testid='title-pc-principal-credit']:contains(Creator) a") as $d) {
                    $dt = trim($d->text());
                    if ($dt !== '') {
                        $directors[] = $dt;
                    }
                }
            }

            // Actors (top billed)
            $actors = [];
            foreach ($dom->find("div[data-testid='title-cast-item'] a[data-testid='title-cast-item__actor']") as $a) {
                $at = trim($a->text());
                if ($at !== '') {
                    $actors[] = $at;
                }
                if (count($actors) >= 10) {
                    break;
                }
            }

            // Languages (from details section)
            $language = '';
            foreach ($dom->find("li[data-testid='title-details-languages'] a") as $ln) {
                $lt = trim($ln->text());
                if ($lt !== '') {
                    $language .= $lt.', ';
                }
            }
            $language = rtrim($language, ', ');

            $data = [
                'imdbid' => $id,
                'title' => $title,
                'year' => $year,
                'plot' => $plot,
                'rating' => $rating,
                'cover' => $cover,
                'genre' => $genres,
                'director' => $directors,
                'actors' => $actors,
                'language' => $language,
                'type' => 'movie',
            ];
            Cache::put($cacheKey, $data, now()->addDays(7));

            return $data;
        } catch (\Throwable $e) {
            Log::debug('IMDb fetch error tt'.$id.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(6));

            return false;
        }
    }

    /**
     * Search IMDb suggestion API for a title.
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $norm = strtolower(preg_replace('/[^a-z0-9 ]/i', '', $query));
        $slug = str_replace(' ', '_', $norm);
        $prefix = substr($slug, 0, 1);
        $cacheKey = 'imdb_search_'.md5($slug);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        $url = 'https://v2.sg.media-imdb.com/suggestion/'.urlencode($prefix).'/'.urlencode($slug).'.json';
        try {
            $res = $this->client->get($url);
            $json = json_decode((string) $res->getBody(), true);
            $results = [];
            foreach (($json['d'] ?? []) as $row) {
                if (! isset($row['id']) || ! str_starts_with($row['id'], 'tt')) {
                    continue;
                }
                $id = substr($row['id'], 2);
                $title = $row['l'] ?? '';
                $year = $row['y'] ?? '';
                if ($title === '') {
                    continue;
                }
                $results[] = [
                    'imdbid' => $id,
                    'title' => $title,
                    'year' => (string) $year,
                ];
                if (count($results) >= 25) {
                    break;
                }
            }
            Cache::put($cacheKey, $results, now()->addHours(12));

            return $results;
        } catch (\Throwable $e) {
            Log::debug('IMDb search error '.$query.': '.$e->getMessage());
            Cache::put($cacheKey, [], now()->addHours(6));

            return [];
        }
    }
}
