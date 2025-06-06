<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\NNTP;
use Blacklight\processing\PostProcess;

/**
 * Array with possible arguments for run and
 * whether or not those methods of operation require NNTP.
 **/
$args = [
    'additional' => true,
    'all' => true,
    'allinf' => true,
    'amazon' => false,
    'anime' => false,
    'book' => false,
    'console' => false,
    'games' => false,
    'movies' => false,
    'music' => false,
    'nfo' => true,
    'pre' => true,
    'sharing' => true,
    'tv' => false,
    'tvdb' => false,
    'xxx' => false,
];

$bool = [
    'true',
    'false',
];

if (! isset($argv[1], $argv[2]) || ! in_array($argv[1], $args, false) || ! in_array($argv[2], $bool, false)) {
    exit(
        (new Blacklight\ColorCLI)->error(
            "\nIncorrect arguments.\n"
            ."The second argument (true/false) determines wether to echo or not.\n\n"
            ."php postprocess.php all true         ...: Does all the types of post processing.\n"
            ."php postprocess.php pre true         ...: Processes all Predb sites.\n"
            ."php postprocess.php nfo true         ...: Processes NFO files.\n"
            ."php postprocess.php movies true      ...: Processes movies.\n"
            ."php postprocess.php music true       ...: Processes music.\n"
            ."php postprocess.php console true     ...: Processes console games.\n"
            ."php postprocess.php games true       ...: Processes games.\n"
            ."php postprocess.php book true        ...: Processes books.\n"
            ."php postprocess.php anime true       ...: Processes anime.\n"
            ."php postprocess.php tv true          ...: Processes tv.\n"
            ."php postprocess.php xxx true         ...: Processes xxx.\n"
            ."php postprocess.php additional true  ...: Processes previews/mediainfo/etc...\n"
            ."php postprocess.php sharing true     ...: Processes uploading/downloading comments.\n"
            ."php postprocess.php allinf true      ...: Does all the types of post processing on a loop, sleeping 15 seconds between.\n"
            ."php postprocess.php amazon true      ...: Does all the amazon (books/console/games/music/xxx).\n"
        )
    );
}

$nntp = null;
if ($args[$argv[1]] === true) {
    $nntp = new NNTP;
    $compressedHeaders = config('nntmux_nntp.compressed_headers');
    if ((config('nntmux_nntp.use_alternate_nntp_server') === true ? $nntp->doConnect($compressedHeaders, true) : $nntp->doConnect()) !== true) {
        echo 'Unable to connect to usenet.'.PHP_EOL;
        exit;
    }
}

$postProcess = new PostProcess(['Echo' => $argv[2] === 'true']);

$charArray = ['a', 'b', 'c', 'd', 'e', 'f', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

switch ($argv[1]) {
    case 'all':
        $postProcess->processAll($nntp);
        break;
    case 'allinf':
        $i = 1;
        while ($i = 1) {
            $postProcess->processAll($nntp);
            sleep(15);
        }
        break;
    case 'additional':
        $postProcess->processAdditional($nntp, '', (isset($argv[3]) && in_array($argv[3], $charArray, false) ? $argv[3] : ''));
        break;
    case 'amazon':
        $postProcess->processBooks();
        $postProcess->processConsoles();
        $postProcess->processGames();
        $postProcess->processMusic();
        $postProcess->processXXX();
        break;
    case 'anime':
        $postProcess->processAnime();
        break;
    case 'book':
        $postProcess->processBooks();
        break;
    case 'console':
        $postProcess->processConsoles();
        break;
    case 'games':
        $postProcess->processGames();
        break;
    case 'nfo':
        $postProcess->processNfos($nntp, '', (isset($argv[3]) && in_array($argv[3], $charArray, false) ? $argv[3] : ''));
        break;
    case 'movies':
        $postProcess->processMovies('', (isset($argv[3]) && in_array($argv[3], $charArray, false) ? $argv[3] : ''));
        break;
    case 'music':
        $postProcess->processMusic();
        break;
    case 'pre':
        break;
    case 'sharing':
        $postProcess->processSharing($nntp);
        break;
    case 'tv':
        $postProcess->processTv('', (isset($argv[3]) && in_array($argv[3], $charArray, false) ? $argv[3] : ''));
        break;
    case 'xxx':
        $postProcess->processXXX();
        break;
    default:
        exit;
}
