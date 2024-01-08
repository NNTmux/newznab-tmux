<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\processing\tv\TVDB;

$c = new Blacklight\ColorCLI();
$tvDB = new TVDB();

if (! empty($argv[1]) && isset($argv[2], $argv[3]) && is_numeric($argv[2]) && is_numeric($argv[3])) {
    // Test if your TvDB API key and configuration are working
    // If it works you should get a var dumped array of the show/season/episode entered

    $season = (int) $argv[2];
    $episode = (int) $argv[3];

    // Search for a show
    $series = $tvDB->client->search()->search((string) $argv[1], ['type' => 'series']);

    // Use the first show found (highest match) and get the requested season/episode from $argv
    if ($series) {
        print_r($series[0]);
        $initialEpisodeObj = '';

        if ($season > 0 && $episode > 0) {
            try {
                $initialEpisodeObj = $tvDB->client->series()->episodes($series[0]->tvdb_id);
            } catch (InvalidArgumentException $error) {
                echo 'Invalid argument(s) used'.PHP_EOL;

                return false;
            } catch (CanIHaveSomeCoffee\TheTVDbAPI\Exception\ParseException $error) {
                if (str_starts_with($error->getMessage(), 'Could not decode JSON data') || str_starts_with($error->getMessage(), 'Incorrect data structure')) {
                    return false;
                }
            } catch (CanIHaveSomeCoffee\TheTVDbAPI\Exception\ResourceNotFoundException $error) {
                return false;
            } catch (CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException $error) {
                if (str_starts_with($error->getMessage(), 'Unauthorized')) {
                    return false;
                }
            }

            if ($initialEpisodeObj) {
                foreach ($initialEpisodeObj as $episodeBaseRecord) {
                    if ($episodeBaseRecord->number === $episode && $episodeBaseRecord->seasonNumber === $season) {
                        $episodeObj = $episodeBaseRecord;
                    }
                }
                print_r($episodeObj);
            }
        } elseif ($season === 0 && $episode === 0) {
            $initialEpisodeObj = $tvDB->client->series()->allEpisodes($series[0]->tvdb_id);
            if (is_object($initialEpisodeObj)) {
                foreach ($initialEpisodeObj as $ep) {
                    print_r($ep);
                }
            }
        } else {
            exit($c->error('Invalid episode data returned from TVDB API.'));
        }
    } else {
        exit($c->error('Invalid show data returned from TVDB API.'));
    }
} else {
    exit($c->error('Invalid arguments. This script requires a text string (show name) followed by a season and episode number.')
    );
}
