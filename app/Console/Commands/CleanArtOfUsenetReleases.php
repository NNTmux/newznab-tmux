<?php

namespace App\Console\Commands;

use App\Services\NameFixing\ArtOfUsenetCleanerService;
use Illuminate\Console\Command;

class CleanArtOfUsenetReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:clean-art-of-usenet
                            {--limit=0 : Maximum number of releases to process (0 = no limit)}
                            {--dry-run : Preview changes without updating the database}
                            {--preview : Show a preview of releases that would be affected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decode reversed/obfuscated release names from Art-of-Usenet style posts';

    /**
     * Execute the console command.
     */
    public function handle(ArtOfUsenetCleanerService $cleaner): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $preview = (bool) $this->option('preview');

        if ($preview) {
            return $this->showPreview($cleaner, $limit ?: 100);
        }

        if ($dryRun) {
            $this->info('Dry run mode - no changes will be made.');
        }

        $this->info('Processing Art-of-Usenet style releases...');

        $stats = $cleaner->processReleases($limit, $dryRun);

        $this->newLine();
        $this->info('Processing complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $stats['processed']],
                ['Renamed (decoded)', $stats['renamed']],
                ['Marked as passworded', $stats['passwordMarked']],
                ['Skipped', $stats['skipped']],
            ]
        );

        if ($dryRun && ($stats['renamed'] > 0 || $stats['passwordMarked'] > 0)) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }

    /**
     * Show a preview of releases that would be affected.
     */
    protected function showPreview(ArtOfUsenetCleanerService $cleaner, int $limit): int
    {
        $this->info("Previewing up to {$limit} Art-of-Usenet style releases...");
        $this->newLine();

        $previews = $cleaner->previewExtractions($limit);

        if (empty($previews)) {
            $this->info('No Art-of-Usenet releases found.');

            return Command::SUCCESS;
        }

        foreach ($previews as $preview) {
            $this->line("Release ID: {$preview['id']}");
            $this->line('  Original:    '.substr($preview['original_name'], 0, 100));
            $this->line('  Obfuscated:  '.($preview['obfuscated'] ?? 'N/A'));

            if ($preview['decoded_name']) {
                $this->line("  <info>Decoded:     {$preview['decoded_name']}</info>");
            } else {
                $this->line('  <comment>Decoded:     (could not decode)</comment>');
            }

            if ($preview['has_password']) {
                $this->line('  <comment>Password:    [PROTECTED] - password required</comment>');
            }

            if ($preview['already_renamed']) {
                $this->line('  <comment>Status:      Already renamed</comment>');
            }

            $this->newLine();
        }

        $this->info('Found '.count($previews).' Art-of-Usenet style releases.');

        return Command::SUCCESS;
    }
}
