#!/usr/bin/env php
<?php

/**
 * Adult Scrapers Test Runner
 *
 * Standalone test script for validating adult content scrapers
 * against real data. This helps identify when sites change their
 * HTML structure and scraping needs updating.
 *
 * Usage:
 *   php test_adult_scrapers.php [scraper] [movie]
 *
 * Examples:
 *   php test_adult_scrapers.php                    # Test all scrapers
 *   php test_adult_scrapers.php ade                # Test only ADE
 *   php test_adult_scrapers.php ade "Pirates"      # Test ADE with specific movie
 *
 * Options:
 *   --verbose    Show detailed output
 *   --json       Output results as JSON
 *   --save       Save results to file
 */

require __DIR__.'/vendor/autoload.php';

use Blacklight\processing\adult\ADE;
use Blacklight\processing\adult\ADM;
use Blacklight\processing\adult\AEBN;
use Blacklight\processing\adult\Hotmovies;
use Blacklight\processing\adult\Popporn;

class AdultScraperTester
{
    private bool $verbose = false;

    private bool $jsonOutput = false;

    private bool $saveResults = false;

    private array $results = [];

    // Test movies that should be findable
    private array $testMovies = [
        'Pirates' => ['year' => 2005, 'type' => 'feature'],
        'The Masseuse' => ['year' => 1990, 'type' => 'classic'],
        'Debbie Does Dallas' => ['year' => 1978, 'type' => 'classic'],
    ];

    private array $scrapers = [
        'ade' => ['class' => ADE::class, 'name' => 'Adult DVD Empire'],
        'adm' => ['class' => ADM::class, 'name' => 'Adult DVD Marketplace'],
        'aebn' => ['class' => AEBN::class, 'name' => 'AEBN Theater'],
        'hotmovies' => ['class' => Hotmovies::class, 'name' => 'HotMovies'],
        'popporn' => ['class' => Popporn::class, 'name' => 'Popporn'],
    ];

    public function __construct(array $options = [])
    {
        $this->verbose = $options['verbose'] ?? false;
        $this->jsonOutput = $options['json'] ?? false;
        $this->saveResults = $options['save'] ?? false;
    }

    public function run(?string $scraperName = null, ?string $movieTitle = null): void
    {
        $this->printHeader();

        $scrapersToTest = $scraperName
            ? [$scraperName => $this->scrapers[$scraperName]]
            : $this->scrapers;

        $moviesToTest = $movieTitle
            ? [$movieTitle => ['year' => null, 'type' => 'custom']]
            : $this->testMovies;

        foreach ($scrapersToTest as $key => $scraper) {
            $this->testScraper($key, $scraper, $moviesToTest);
        }

        $this->printSummary();

        if ($this->jsonOutput) {
            echo json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($this->saveResults) {
            $this->saveResultsToFile();
        }
    }

    private function testScraper(string $key, array $scraperInfo, array $movies): void
    {
        $scraperClass = $scraperInfo['class'];
        $scraperName = $scraperInfo['name'];

        if (! $this->jsonOutput) {
            $this->printSection("Testing {$scraperName} ({$key})");
        }

        try {
            $scraper = new $scraperClass;
            $scraperResults = [];

            foreach ($movies as $title => $info) {
                $result = $this->testMovie($scraper, $key, $title, $info);
                $scraperResults[] = $result;
            }

            $this->results[$key] = [
                'name' => $scraperName,
                'tests' => $scraperResults,
                'success_rate' => $this->calculateSuccessRate($scraperResults),
            ];
        } catch (\Exception $e) {
            if (! $this->jsonOutput) {
                echo "  ❌ ERROR: {$e->getMessage()}\n";
            }
            $this->results[$key] = [
                'name' => $scraperName,
                'error' => $e->getMessage(),
            ];
        }

        if (! $this->jsonOutput) {
            echo "\n";
        }
    }

    private function testMovie($scraper, string $scraperKey, string $title, array $info): array
    {
        $startTime = microtime(true);

        try {
            $found = $scraper->processSite($title);
            $duration = microtime(true) - $startTime;

            if (! $found) {
                if (! $this->jsonOutput) {
                    echo "  ❌ Not found: {$title}\n";
                }

                return [
                    'title' => $title,
                    'found' => false,
                    'duration' => round($duration, 2),
                ];
            }

            $data = $scraper->getAll();
            $validation = $this->validateData($data);

            if (! $this->jsonOutput) {
                echo "  ✓ Found: {$data['title']}\n";
                echo "    URL: {$data['directurl']}\n";
                echo '    Time: '.round($duration, 2)."s\n";

                if ($this->verbose) {
                    $this->printDetailedData($data, $validation);
                }

                if (! empty($validation['missing'])) {
                    echo '    ⚠ Missing fields: '.implode(', ', $validation['missing'])."\n";
                }
            }

            return [
                'title' => $title,
                'found' => true,
                'matched_title' => $data['title'] ?? null,
                'url' => $data['directurl'] ?? null,
                'duration' => round($duration, 2),
                'fields' => $validation['present'],
                'missing_fields' => $validation['missing'],
                'data' => $this->verbose ? $data : null,
            ];
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            if (! $this->jsonOutput) {
                echo "  ❌ Error testing {$title}: {$e->getMessage()}\n";
            }

            return [
                'title' => $title,
                'found' => false,
                'error' => $e->getMessage(),
                'duration' => round($duration, 2),
            ];
        }
    }

    private function validateData(array $data): array
    {
        $expectedFields = [
            'required' => ['title', 'directurl'],
            'optional' => ['synopsis', 'cast', 'genres', 'boxcover', 'backcover', 'director', 'productinfo'],
        ];

        $present = [];
        $missing = [];

        foreach ($expectedFields['required'] as $field) {
            if (isset($data[$field]) && ! empty($data[$field])) {
                $present[] = $field;
            } else {
                $missing[] = $field.' (required)';
            }
        }

        foreach ($expectedFields['optional'] as $field) {
            if (isset($data[$field]) && ! empty($data[$field])) {
                $present[] = $field;
            } else {
                $missing[] = $field;
            }
        }

        return ['present' => $present, 'missing' => $missing];
    }

    private function printDetailedData(array $data, array $validation): void
    {
        echo '    Fields present: '.implode(', ', $validation['present'])."\n";

        if (isset($data['synopsis'])) {
            $synopsis = substr($data['synopsis'], 0, 100).(strlen($data['synopsis']) > 100 ? '...' : '');
            echo "    Synopsis: {$synopsis}\n";
        }

        if (isset($data['cast']) && is_array($data['cast'])) {
            echo '    Cast: '.count($data['cast'])." members\n";
            if (! empty($data['cast'])) {
                echo '      - '.implode("\n      - ", array_slice($data['cast'], 0, 3))."\n";
            }
        }

        if (isset($data['genres']) && is_array($data['genres'])) {
            echo '    Genres: '.implode(', ', $data['genres'])."\n";
        }

        if (isset($data['director'])) {
            echo "    Director: {$data['director']}\n";
        }

        if (isset($data['boxcover'])) {
            echo "    Box Cover: ✓\n";
        }
    }

    private function calculateSuccessRate(array $results): float
    {
        $total = count($results);
        if ($total === 0) {
            return 0.0;
        }

        $successful = count(array_filter($results, fn ($r) => $r['found'] ?? false));

        return round(($successful / $total) * 100, 1);
    }

    private function printHeader(): void
    {
        if ($this->jsonOutput) {
            return;
        }

        echo str_repeat('=', 80)."\n";
        echo "Adult Scrapers Test Suite\n";
        echo 'Testing against real data - '.date('Y-m-d H:i:s')."\n";
        echo str_repeat('=', 80)."\n\n";
    }

    private function printSection(string $title): void
    {
        echo str_repeat('-', 80)."\n";
        echo $title."\n";
        echo str_repeat('-', 80)."\n";
    }

    private function printSummary(): void
    {
        if ($this->jsonOutput) {
            return;
        }

        echo str_repeat('=', 80)."\n";
        echo "SUMMARY\n";
        echo str_repeat('=', 80)."\n";

        foreach ($this->results as $key => $result) {
            if (isset($result['error'])) {
                echo "{$result['name']}: ERROR - {$result['error']}\n";
            } else {
                $rate = $result['success_rate'];
                $emoji = $rate >= 80 ? '✓' : ($rate >= 50 ? '⚠' : '❌');
                echo "{$emoji} {$result['name']}: {$rate}% success rate\n";
            }
        }

        echo "\n";
    }

    private function saveResultsToFile(): void
    {
        $filename = 'scraper_test_results_'.date('Y-m-d_His').'.json';
        $filepath = __DIR__.'/storage/logs/'.$filename;

        $dir = dirname($filepath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $filepath,
            json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if (! $this->jsonOutput) {
            echo "Results saved to: {$filepath}\n";
        }
    }
}

// Parse command line arguments
$options = [
    'verbose' => in_array('--verbose', $argv) || in_array('-v', $argv),
    'json' => in_array('--json', $argv),
    'save' => in_array('--save', $argv) || in_array('-s', $argv),
];

$scraperArg = null;
$movieArg = null;

// Extract scraper and movie from args
$nonFlagArgs = array_values(array_filter($argv, fn ($arg) => ! str_starts_with($arg, '-') && $arg !== basename(__FILE__)));
if (isset($nonFlagArgs[0])) {
    $scraperArg = $nonFlagArgs[0];
}
if (isset($nonFlagArgs[1])) {
    $movieArg = $nonFlagArgs[1];
}

// Validate scraper name
$validScrapers = ['ade', 'adm', 'aebn', 'hotmovies', 'popporn'];
if ($scraperArg && ! in_array($scraperArg, $validScrapers)) {
    echo "Invalid scraper: {$scraperArg}\n";
    echo 'Valid scrapers: '.implode(', ', $validScrapers)."\n";
    exit(1);
}

// Run tests
try {
    $tester = new AdultScraperTester($options);
    $tester->run($scraperArg, $movieArg);
} catch (Exception $e) {
    echo "Fatal error: {$e->getMessage()}\n";
    exit(1);
}
