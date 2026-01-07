<?php

namespace App\Console\Commands;

use App\Models\Settings;
use App\Services\AdultProcessing\AdultProcessingPipeline;
use Illuminate\Console\Command;

class ProcessAdultMovies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:process-adult
                            {--title= : Process a specific movie title}
                            {--debug : Enable debug output}
                            {--limit= : Limit number of releases to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process adult movie releases using the pipeline-based scraper';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ((int) Settings::settingValue('lookupxxx') !== 1) {
            $this->error('Adult movie lookup is disabled in settings. Enable "lookupxxx" to use this command.');

            return Command::FAILURE;
        }

        $debug = $this->option('debug');
        $title = $this->option('title');
        $limit = $this->option('limit');

        $pipeline = new AdultProcessingPipeline([], true);

        if ($title) {
            // Process a single title
            $this->info("Looking up: {$title}");

            $result = $pipeline->processMovie($title, $debug);

            if ($result['status'] === 'matched') {
                $this->info("Match found on {$result['provider']}!");
                $title_display = $result['movieData']['title'] ?? 'N/A';
                $synopsis_display = substr($result['movieData']['synopsis'] ?? 'N/A', 0, 200);
                $this->line("Title: {$title_display}");
                $this->line("Synopsis: {$synopsis_display}...");

                if (! empty($result['movieData']['boxcover'])) {
                    $cover_url = $result['movieData']['boxcover'];
                    $this->line("Cover: {$cover_url}");
                }

                if ($debug && ! empty($result['debug'])) {
                    $this->newLine();
                    $this->line('Debug Info:');
                    $this->line(json_encode($result['debug'], JSON_PRETTY_PRINT));
                }
            } else {
                $this->warn("No match found for: {$title}");

                if ($debug && ! empty($result['debug'])) {
                    $this->newLine();
                    $this->line('Debug Info:');
                    $this->line(json_encode($result['debug'], JSON_PRETTY_PRINT));
                }
            }
        } else {
            // Process all pending releases
            $this->info('Processing adult movie releases using pipeline...');

            if ($limit) {
                $this->info("Limited to {$limit} releases");
            }

            $pipeline->processXXXReleases();

            $stats = $pipeline->getStats();

            $this->newLine();
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Processed', $stats['processed']],
                    ['Matched', $stats['matched']],
                    ['Failed', $stats['failed']],
                    ['Skipped', $stats['skipped']],
                    ['Duration', sprintf('%.2f seconds', $stats['duration'])],
                ]
            );

            if (! empty($stats['providers'])) {
                $this->newLine();
                $this->info('Provider Statistics:');
                $providerData = [];
                foreach ($stats['providers'] as $provider => $count) {
                    $providerData[] = [$provider, $count];
                }
                $this->table(['Provider', 'Matches'], $providerData);
            }
        }

        return Command::SUCCESS;
    }
}
