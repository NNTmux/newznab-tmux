<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Blacklight\Nfo;
use Blacklight\NNTP;
use Blacklight\processing\post\ProcessAdditional;
use Blacklight\processing\PostProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PostProcessGuid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postprocess:guid
                            {type : Type: additional, nfo, movie, tv, anime, or books}
                            {guid : First character of release guid (a-f, 0-9)}
                            {renamed? : For movie/tv: process renamed only (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post process releases by GUID character';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $guid = $this->argument('guid');
        $renamed = $this->argument('renamed') ?? '';

        if (! $this->isValidChar($guid)) {
            $this->error('GUID character must be a-f or 0-9.');

            return self::FAILURE;
        }

        try {
            switch ($type) {
                case 'additional':
                    $nntp = $this->getNntp();
                    (new ProcessAdditional(['Echo' => true, 'NNTP' => $nntp]))->start('', $guid);
                    break;

                case 'nfo':
                    $nntp = $this->getNntp();
                    (new Nfo)->processNfoFiles(
                        $nntp,
                        '',
                        $guid,
                        (int) Settings::settingValue('lookupimdb'),
                        (int) Settings::settingValue('lookuptv')
                    );
                    break;

                case 'movie':
                    (new PostProcess)->processMovies('', $guid, $renamed);
                    break;

                case 'tv':
                    (new PostProcess)->processTv('', $guid, $renamed);
                    break;

                case 'anime':
                    (new PostProcess)->processAnime('', $guid);
                    break;

                case 'books':
                    (new PostProcess)->processBooks('', $guid);
                    break;

                default:
                    $this->error('Invalid type. Must be: additional, nfo, movie, tv, anime, or books.');

                    return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Check if the character contains a-f or 0-9.
     */
    private function isValidChar(string $char): bool
    {
        return \in_array(
            $char,
            ['a', 'b', 'c', 'd', 'e', 'f', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            true
        );
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP;

        if ((config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect()) !== true) {
            throw new \Exception('Unable to connect to usenet.');
        }

        return $nntp;
    }
}
