<?php

namespace App\Console\Commands;

use App\Services\NameFixing\ReversedNameDetectorService;
use Illuminate\Console\Command;

class DecodeReversedReleaseNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:decode-reversed
                            {--limit=0 : Maximum number of releases to process (0 = no limit)}
                            {--dry-run : Preview changes without updating the database}
                            {--preview : Show a preview of releases that would be affected}
                            {--min-confidence=low : Minimum confidence level to apply changes (low, medium, high)}
                            {--test= : Test a specific string to see if it would be decoded}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and decode reversed/obfuscated release names';

    /**
     * Execute the console command.
     */
    public function handle(ReversedNameDetectorService $detector): int
    {
        // Handle test mode
        $testString = $this->option('test');
        if ($testString) {
            return $this->testString($detector, $testString);
        }
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $preview = (bool) $this->option('preview');
        $minConfidence = $this->option('min-confidence');
        // Validate confidence level
        if (! in_array($minConfidence, ['low', 'medium', 'high'])) {
            $this->error('Invalid confidence level. Use: low, medium, or high');

            return Command::FAILURE;
        }
        if ($preview) {
            return $this->showPreview($detector, $limit ?: 100);
        }
        if ($dryRun) {
            $this->info('Dry run mode - no changes will be made.');
        }
        $this->info("Processing releases for reversed names (min confidence: {$minConfidence})...");
        $stats = $detector->processReleases($limit, $dryRun, $minConfidence);
        $this->newLine();
        $this->info('Processing complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $stats['processed']],
                ['Decoded', $stats['decoded']],
                ['Skipped', $stats['skipped']],
            ]
        );
        if ($dryRun && $stats['decoded'] > 0) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }

    /**
     * Test a specific string to see if it would be decoded.
     */
    protected function testString(ReversedNameDetectorService $detector, string $testString): int
    {
        $this->info('Testing string for reversed name detection:');
        $this->newLine();
        $this->line("  Original: {$testString}");
        $this->line('  Reversed: '.strrev($testString));
        $this->newLine();
        $result = $detector->decode($testString);
        if ($result['decoded']) {
            $this->info('  Detected as REVERSED');
            $this->line("  Decoded:    {$result['result']}");
            $this->line("  Confidence: {$result['confidence']}");
        } else {
            $this->warn('  NOT detected as reversed');
            $this->line('  The original name appears to be valid.');
        }
        $this->newLine();
        // Show scoring details
        $this->line('  Scoring details:');
        $this->line('    Original score: '.$this->getScore($detector, $testString));
        $this->line('    Reversed score: '.$this->getScore($detector, strrev($testString)));

        return Command::SUCCESS;
    }

    /**
     * Get the score for a name (using reflection to access protected method).
     */
    protected function getScore(ReversedNameDetectorService $detector, string $name): int
    {
        $reflection = new \ReflectionClass($detector);
        $method = $reflection->getMethod('scoreValidName');
        $method->setAccessible(true);

        return $method->invoke($detector, $name);
    }

    /**
     * Show a preview of releases that would be affected.
     */
    protected function showPreview(ReversedNameDetectorService $detector, int $limit): int
    {
        $this->info("Previewing up to {$limit} releases with potentially reversed names...");
        $this->newLine();
        $previews = $detector->previewDecodings($limit);
        if (empty($previews)) {
            $this->info('No reversed release names detected.');

            return Command::SUCCESS;
        }
        foreach ($previews as $preview) {
            $this->line("Release ID: {$preview['id']}");
            $this->line('  Original: '.$preview['original_name']);
            $confidenceColor = match ($preview['confidence']) {
                'high' => 'info',
                'medium' => 'comment',
                default => 'line',
            };
            $this->{"$confidenceColor"}("  Decoded:  {$preview['decoded_name']}");
            $this->line("  Confidence: {$preview['confidence']}");
            if ($preview['already_renamed']) {
                $this->line('  Status: Already renamed');
            }
            $this->newLine();
        }
        $this->info('Found '.count($previews).' releases with potentially reversed names.');
        // Show confidence breakdown
        $confidenceCounts = array_count_values(array_column($previews, 'confidence'));
        $this->newLine();
        $this->line('Confidence breakdown:');
        foreach (['high', 'medium', 'low'] as $level) {
            $count = $confidenceCounts[$level] ?? 0;
            $this->line("  {$level}: {$count}");
        }

        return Command::SUCCESS;
    }
}
