<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Adrenth\Thetvdb\Exception\InvalidArgumentException;
use Adrenth\Thetvdb\Exception\InvalidJsonInResponseException;
use Adrenth\Thetvdb\Exception\RequestFailedException;
use Adrenth\Thetvdb\Exception\UnauthorizedException;
use Blacklight\processing\tv\TVDB;

$c = new Blacklight\ColorCLI();
$tvdb = new TVDB();

if (isset($argv[1]) && ! empty($argv[1]) && isset($argv[2]) && is_numeric($argv[2]) && isset($argv[3]) && is_numeric($argv[3])) {

    // Test if your TvDB API key and configuration are working
    // If it works you should get a var dumped array of the show/season/episode entered

    $season = (int) $argv[2];
    $episode = (int) $argv[3];
    $day = (isset($argv[4]) && is_numeric($argv[4]) ? $argv[4] : '');

    // Search for a show
    $series = $tvdb->client->search()->seriesByName((string) $argv[1]);

    // Use the first show found (highest match) and get the requested season/episode from $argv
    if ($series) {
        $serie = $series->getData();
        print_r($serie);

        if ($season > 0 && $episode > 0 && $day === '') {
            try {
                $episodeObj = $tvdb->client->series()->getEpisodesWithQuery($serie[0]->getid(), ['airedSeason' => $season, 'airedEpisode' => $episode]);
            } catch (InvalidArgumentException $error) {
                echo 'Invalid argument(s) used'.PHP_EOL;

                return false;
            } catch (InvalidJsonInResponseException $error) {
                if (strpos($error->getMessage(), 'Could not decode JSON data') === 0 || strpos($error->getMessage(), 'Incorrect data structure') === 0) {
                    return false;
                }
            } catch (RequestFailedException $error) {
                return false;
            } catch (UnauthorizedException $error) {
                if (strpos($error->getMessage(), 'Unauthorized') === 0) {
                    return false;
                }
            }

            if ($episodeObj) {
                print_r($episodeObj);
            }
        } elseif ($season === 0 && $episode === 0) {
            $episodeObj = $tvdb->client->series()->getEpisodes($serie[0]->getid());
            if (is_object($episodeObj)) {
                foreach ($episodeObj->getData() as $ep) {
                    print_r($ep);
                }
            }
        } elseif (preg_match('#^(19|20)\d{2}\/\d{2}\/\d{2}$#', $season.'/'.$episode.'/'.$day, $airdate)) {
            $episodeObj = $tvdb->client->series()->getEpisodesWithQuery($series[0]->id, ['firstAired' => (string) $airdate[0]]);
            if ($episodeObj) {
                print_r($episodeObj);
            }
        } else {
            exit($c->error('Invalid episode data returned from TVDB API.'));
        }
    } else {
        exit($c->error('Invalid show data returned from TVDB API.'));
    }
} else {
    exit($c->error('Invalid arguments. This script requires a text string (show name) followed by a season and episode number.'.PHP_EOL.
        'You can also optionally supply "YYYY" "MM" "DD" arguments instead of season/episode for an airdate lookup.')
    );
}
