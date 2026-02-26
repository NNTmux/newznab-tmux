<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Elasticsearch;
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
            $exists = Elasticsearch::indices()->exists(['index' => $index]);

            if (! $exists) {
                $this->error("ElasticSearch index '{$index}' does not exist.");

                return;
            }

            $this->info("ElasticSearch index '{$index}' exists.");

            // Get index stats
            $stats = Elasticsearch::indices()->stats(['index' => $index]);
            $docCount = $stats['indices'][$index]['total']['docs']['count'] ?? 0;
            $storeSize = $stats['indices'][$index]['total']['store']['size_in_bytes'] ?? 0;

            $this->info('Document count: '.number_format($docCount));
            $this->info('Index size: '.$this->formatBytes($storeSize));

            // Get a sample document
            if ($docCount > 0) {
                $sample = Elasticsearch::search([
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
            $host = config('nntmux.manticore.host', '127.0.0.1');
            $port = config('nntmux.manticore.port', 9308);

            // Use HTTP API endpoint
            $baseUrl = "http://{$host}:{$port}";

            // Check if table exists using HTTP API
            $client = new \GuzzleHttp\Client([
                'base_uri' => $baseUrl,
                'timeout' => 10,
                'http_errors' => false, // Don't throw on HTTP errors
            ]);

            // Use raw mode which seems to work
            $query = "SELECT COUNT(*) FROM {$indexName}";

            $response = $client->post('/sql?mode=raw', [
                'body' => $query,
                'headers' => [
                    'Content-Type' => 'text/plain',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                $this->error("HTTP Status: {$statusCode}");

                // Check if it's a table not found error
                if (strpos($body, 'no such table') !== false ||
                    strpos($body, 'unknown table') !== false ||
                    strpos($body, "doesn't exist") !== false) {
                    $this->error("ManticoreSearch table '{$indexName}' does not exist.");

                    return;
                }
                $this->error("Response: {$body}");

                return;
            }

            // Parse the JSON response
            $jsonData = json_decode($body, true);
            $docCount = 0;

            if ($jsonData !== null && isset($jsonData['data'])) {
                // ManticoreSearch returns data as array of arrays
                $docCount = $jsonData['data'][0]['COUNT(*)'] ?? $jsonData['data'][0][0] ?? 0;
            }

            $this->info("ManticoreSearch table '{$indexName}' exists.");
            $this->info('Document count: '.number_format($docCount));

            // Get a sample document if there are any
            if ($docCount > 0) {
                $sampleQuery = "SELECT * FROM {$indexName} LIMIT 1";

                $sampleResponse = $client->post('/sql?mode=raw', [
                    'body' => $sampleQuery,
                    'headers' => [
                        'Content-Type' => 'text/plain',
                    ],
                ]);

                if ($sampleResponse->getStatusCode() === 200) {
                    $sampleBody = $sampleResponse->getBody()->getContents();
                    $sampleJson = json_decode($sampleBody, true);

                    if ($sampleJson !== null && isset($sampleJson['data'][0])) {
                        $this->info('Sample document:');

                        $document = [];
                        $columns = $sampleJson['columns'] ?? [];
                        $data = $sampleJson['data'][0] ?? [];

                        foreach ($columns as $i => $column) {
                            // Extract column name from the nested structure
                            $columnName = array_keys($column)[0];
                            $document[$columnName] = $data[$i] ?? null;
                        }

                        $this->info(json_encode($document, JSON_PRETTY_PRINT));
                    }
                }
            }

            // Get table structure
            try {
                $describeQuery = "DESCRIBE {$indexName}";
                $describeResponse = $client->post('/sql?mode=raw', [
                    'body' => $describeQuery,
                    'headers' => [
                        'Content-Type' => 'text/plain',
                    ],
                ]);

                if ($describeResponse->getStatusCode() === 200) {
                    $describeBody = $describeResponse->getBody()->getContents();
                    $describeJson = json_decode($describeBody, true);

                    if ($describeJson !== null && isset($describeJson['data'])) {
                        $this->info("\nTable structure:");
                        $this->table(
                            ['Field', 'Type', 'Properties'],
                            array_map(function ($row) {
                                return [
                                    $row['Field'] ?? $row[0] ?? '',
                                    $row['Type'] ?? $row[1] ?? '',
                                    $row['Properties'] ?? $row[2] ?? '',
                                ];
                            }, $describeJson['data'])
                        );
                    }
                }
            } catch (\Exception $e) {
                // Ignore describe errors
            }

        } catch (\Exception $e) {
            $this->error("Error checking ManticoreSearch table: {$e->getMessage()}");
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $this->error('Response body: '.$e->getResponse()->getBody());
            }
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
