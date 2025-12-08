<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Services\TmdbClient;
use Blacklight\ColorCLI;

$colorCli = new ColorCLI;

if (! empty($argv[1]) && is_numeric($argv[2]) && is_numeric($argv[3])) {
    // Test if your TMDB API configuration is working
    // If it works you should get a var dumped array of the show/season/episode entered

    $tmdbClient = app(TmdbClient::class);

    if (! $tmdbClient->isConfigured()) {
        exit($colorCli->error('TMDB API key is not configured. Please set your API key in the configuration.'));
    }

    $season = (int) $argv[2];
    $episode = (int) $argv[3];

    // Search for a show
    $series = $tmdbClient->searchTv((string) $argv[1]);

    // Use the first show found (highest match) and get the requested season/episode from $argv
    $totalResults = $series !== null ? TmdbClient::getInt($series, 'total_results') : 0;
    $results = $series !== null ? TmdbClient::getArray($series, 'results') : [];

    if (! empty($results) && $totalResults > 0) {
        $showId = TmdbClient::getInt($results[0], 'id');

        if ($showId === 0) {
            exit($colorCli->error('Invalid show ID returned from TMDB API.'));
        }

        // Get TV show details with networks
        $tvShowDetails = $tmdbClient->getTvShow($showId);
        $networks = $tvShowDetails !== null ? TmdbClient::getArray($tvShowDetails, 'networks') : [];

        // Get alternative titles
        $alternativeTitlesResponse = $tmdbClient->getTvAlternativeTitles($showId);
        $alternativeTitles = $alternativeTitlesResponse !== null ? TmdbClient::getArray($alternativeTitlesResponse, 'results') : [];

        // Get external IDs
        $externalIds = $tmdbClient->getTvExternalIds($showId);

        // Merge additional data into results
        $results[0]['networks'] = $networks;
        $results[0]['alternative_titles'] = $alternativeTitles;
        $results[0]['external_ids'] = $externalIds ?? [];

        if ($season > 0 && $episode > 0) {
            $episodeObj = $tmdbClient->getTvEpisode($showId, $season, $episode);
            if ($episodeObj !== null) {
                echo "Show: ".TmdbClient::getString($results[0], 'name')."\n";
                echo "Season: $season, Episode: $episode\n";
                print_r($episodeObj);
            } else {
                exit($colorCli->error('Episode not found on TMDB API.'));
            }
        } elseif ($season === 0 && $episode === 0) {
            $tvShowFull = $tmdbClient->getTvShow($showId);
            if ($tvShowFull !== null) {
                print_r($tvShowFull);
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
