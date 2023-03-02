<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\processing\tv\TVMaze;

$c = new Blacklight\ColorCLI();
$tvmaze = new TVMaze();

if (! empty($argv[1]) && is_numeric($argv[2]) && is_numeric($argv[3])) {
    // Test if your TVMaze API configuration is working
    // If it works you should get a var dumped array of the show/season/episode entered

    $season = (int) $argv[2];
    $episode = (int) $argv[3];

    // Search for a show
    $series = $tvmaze->client->search((string) $argv[1]);

    // Use the first show found (highest match) and get the requested season/episode from $argv
    if ($series) {
        echo PHP_EOL.$c->info('Server Time: '.$serverTime).PHP_EOL;
        print_r($series[0]);

        if ($season > 0 and $episode > 0) {
            $episodeObj = $tvmaze->client->getEpisodeByNumber($series[0]->id, $season, $episode);
            if ($episodeObj) {
                print_r($episodeObj);
            }
        } elseif ($season == 0 && $episode == 0) {
            $episodeObj = $tvmaze->client->getEpisodesByShowID($series[0]->id);
            if (is_array($episodeObj)) {
                echo '*';
                foreach ($episodeObj as $ep) {
                    print_r($ep);
                }
            }
        } else {
            exit($c->error('Invalid episode data returned from TVMaze API.'));
        }
    } else {
        exit($c->error('Invalid show data returned from TVMaze API.'));
    }
} else {
    exit($c->error('Invalid arguments.  This script requires a text string (show name) followed by a season and episode number.'));
}
