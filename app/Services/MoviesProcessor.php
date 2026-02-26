<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;
use GuzzleHttp\Exception\GuzzleException;

class MoviesProcessor
{
    /** @phpstan-ignore property.onlyWritten */
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    /**
     * @param  int|string|null  $processMovies  0/1/2 or '' to read from settings
     *
     * @throws GuzzleException
     */
    public function process(string $groupID = '', string $guidChar = '', int|string|null $processMovies = ''): void
    {
        $processMovies = (is_numeric($processMovies) ? $processMovies : Settings::settingValue('lookupimdb'));
        if ($processMovies > 0) {
            (new MovieService)->processMovieReleases($groupID, $guidChar, $processMovies);
        }
    }
}
