<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;

class NntmuxCheckIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:check-index
                                       {--manticore : Check ManticoreSearch}
                                       {--elastic : Check ElasticSearch}
                                       {--releases : Check the releases index}
                                       {--predb : Check the predb index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if data exists in search indexes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $engine = $this->getSelectedEngine();
        $index = $this->getSelectedIndex();

        if (! $engine || ! $index) {
            $this->error('You must specify both an engine (--manticore or --elastic) and an index (--releases or --predb).');

            return Command::FAILURE;
        }

        $this->info("Checking {$engine} {$index} index...");

        try {
            if ($engine === 'elastic') {
                $this->checkElasticIndex($index);
            } else {
                $this->checkManticoreIndex($index);
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error checking index: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Check ElasticSearch index
     */
    private function checkElasticIndex(string $index): void
    {
        try {
            // Check if index exists
            $exists = \Elasticsearch::indices()->exists(['index' => $index]);

            if (! $exists) {
                $this->error("ElasticSearch index '{$index}' does not exist.");

                return;
            }

            $this->info("ElasticSearch index '{$index}' exists.");

            // Get index stats
            $stats = \Elasticsearch::indices()->stats(['index' => $index]);
            $docCount = $stats['indices'][$index]['total']['docs']['count'] ?? 0;
            $storeSize = $stats['indices'][$index]['total']['store']['size_in_bytes'] ?? 0;

            $this->info('Document count: '.number_format($docCount));
            $this->info('Index size: '.$this->formatBytes($storeSize));

            // Get a sample document
            if ($docCount > 0) {
                $sample = \Elasticsearch::search([
                    'index' => $index,
                    'size' => 1,
                    'body' => [
                        'query' => ['match_all' => (object) []],
                    ],
                ]);

                if (isset($sample['hits']['hits'][0])) {
                    $this->info('Sample document:');
                    $this->info(json_encode($sample['hits']['hits'][0]['_source'], JSON_PRETTY_PRINT));
                }
            }

        } catch (Exception $e) {
            $this->error("Error checking ElasticSearch index: {$e->getMessage()}");
        }
    }

    /**
     * Check ManticoreSearch index
     */
    private function checkManticoreIndex(string $index): void
    {
        try {
            $indexName = $index === 'releases' ? 'releases_rt' : 'predb_rt';

            // This is a basic check - you may need to adjust based on your ManticoreSearch setup
            $this->info("Checking ManticoreSearch index '{$indexName}'...");
            $this->warn('ManticoreSearch index checking not fully implemented yet.');

        } catch (Exception $e) {
            $this->error("Error checking ManticoreSearch index: {$e->getMessage()}");
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * Get selected engine
     */
    private function getSelectedEngine(): ?string
    {
        return $this->option('manticore') ? 'manticore' : ($this->option('elastic') ? 'elastic' : null);
    }

    /**
     * Get selected index
     */
    private function getSelectedIndex(): ?string
    {
        return $this->option('releases') ? 'releases' : ($this->option('predb') ? 'predb' : null);
    }
}
