<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrateAnimeCovers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anime:migrate-covers {--dry-run : Show what would be renamed without actually renaming}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate anime cover files from old format ({id}.jpg) to new format ({id}-cover.jpg)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $animeCoverPath = storage_path('covers/anime/');
        $dryRun = $this->option('dry-run');

        if (! is_dir($animeCoverPath)) {
            $this->error("Anime covers directory does not exist: {$animeCoverPath}");

            return 1;
        }

        $this->info("Scanning anime covers directory: {$animeCoverPath}");

        $files = File::files($animeCoverPath);
        $renamed = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Check if file matches old format: {id}.jpg (where id is numeric)
            if (preg_match('/^(\d+)\.jpg$/', $filename, $matches)) {
                $anidbid = $matches[1];
                $newFilename = "{$anidbid}-cover.jpg";
                $newPath = $animeCoverPath.$newFilename;

                // Skip if new format already exists
                if (file_exists($newPath)) {
                    $this->warn("Skipping {$filename} - {$newFilename} already exists");
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("Would rename: {$filename} -> {$newFilename}");
                    $renamed++;
                } else {
                    if (rename($file->getPathname(), $newPath)) {
                        $this->info("Renamed: {$filename} -> {$newFilename}");
                        $renamed++;
                    } else {
                        $this->error("Failed to rename: {$filename}");
                    }
                }
            }
        }

        if ($dryRun) {
            $this->info("\nDry run complete. Would rename {$renamed} file(s), skipped {$skipped}.");
            $this->info('Run without --dry-run to perform the migration.');
        } else {
            $this->info("\nMigration complete. Renamed {$renamed} file(s), skipped {$skipped}.");
        }

        return 0;
    }
}
