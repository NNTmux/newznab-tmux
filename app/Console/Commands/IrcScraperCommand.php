<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IRCScraper;
use Illuminate\Console\Command;

class IrcScraperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'irc:scrape
                            {--debug : Turn on debug (shows sent/received messages from the socket)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape IRC for PRE information';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('irc_settings.scrape_irc_username') === '') {
            $this->error('ERROR! You must put a username in config/irc_settings.php');

            return self::FAILURE;
        }

        // Use Laravel's built-in quiet mode for silent operation
        $silent = $this->option('quiet');
        $debug = $this->option('debug');

        if (! $silent) {
            $this->info('Starting IRC Scraper...');
            if ($debug) {
                $this->warn('Debug mode enabled');
            }
        }

        try {
            new IRCScraper($silent, $debug);

            return self::SUCCESS;
        } catch (\Exception $e) {
            if (! $silent) {
                $this->error($e->getMessage());
            }

            return self::FAILURE;
        }
    }
}
