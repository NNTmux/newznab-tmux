<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\processing\tv\TMDB;

$tmdb = new TMDB();
$colorCli = new ColorCLI();

if (! empty($argv[1]) && is_numeric($argv[2]) && is_numeric($argv[3])) {

    // Test if your TMDB API configuration is working
    // If it works you should get a var dumped array of the show/season/episode entered

    $season = (int) $argv[2];
    $episode = (int) $argv[3];

    // Search for a show
    $series = $tmdb->client->getSearchApi()->searchTv((string) $argv[1]);
    print_r($series);

    // Use the first show found (highest match) and get the requested season/episode from $argv
    if (! empty($series) && $series['total_results'] > 0) {
        $seriesAppends = [
            'networks' => $tmdb->client->getTvApi()->getTvshow($series['results'][0]['id'])['networks'],
            'alternative_titles' => $tmdb->client->getTvApi()->getAlternativeTitles($series['results'][0]['id']),
            'external_ids' => $tmdb->client->getTvApi()->getExternalIds($series['results'][0]['id']),
        ];
        print_r($seriesAppends);
        if ($seriesAppends) {
            $series['results'][0]['networks'] = $seriesAppends['networks'];
            $series['results'][0]['alternative_titles'] = $seriesAppends['alternative_titles'];
            $series['results'][0]['external_ids'] = $seriesAppends['external_ids'];
        }

        print_r($series['results'][0]);

        if ($season > 0 && $episode > 0) {
            $episodeObj = $tmdb->client->getTvEpisodeApi()->getEpisode($series['results'][0]['id'], $season, $episode);
            if ($episodeObj) {
                print_r($episodeObj);
            }
        } elseif ($season === 0 && $episode === 0) {
            $episodeObj = $tmdb->client->getTvApi()->getTvshow($series['results'][0]['id']);
            if (is_array($episodeObj)) {
                foreach ($episodeObj as $ep) {
                    print_r($ep);
                }
            }
        } else {
            exit($colorCli->error('Invalid episode data returned from TMDB API.'));
        }
    } else {
        exit($colorCli->error('Invalid show data returned from TMDB API.'));
    }
} else {
    exit($colorCli->error('Invalid arguments.  This script requires a text string (show name) followed by a season and episode number.'));
}
