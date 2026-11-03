<?php

namespace App\Console\Commands;

use App\Services\NameFixing\HouseOfUsenetCleanerService;
use Illuminate\Console\Command;

class CleanHouseOfUsenetReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:clean-hou
                            {--limit=0 : Maximum number of releases to process (0 = no limit)}
                            {--dry-run : Preview changes without updating the database}
                            {--preview : Show a preview of releases that would be affected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean House-of-Usenet style releases: extract names from promotional subject lines';

    /**
     * Execute the console command.
     */
    public function handle(HouseOfUsenetCleanerService $cleaner): int
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
        $this->info('Processing House-of-Usenet releases...');
        $stats = $cleaner->processReleases($limit, $dryRun);
        $this->newLine();
        $this->info('Processing complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $stats['processed']],
                ['Renamed', $stats['renamed']],
                ['Skipped (no match)', $stats['skipped']],
            ]
        );
        if ($dryRun && $stats['renamed'] > 0) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }

    /**
     * Show a preview of releases that would be affected.
     */
    protected function showPreview(HouseOfUsenetCleanerService $cleaner, int $limit): int
    {
        $this->info("Previewing up to {$limit} House-of-Usenet releases...");
        $this->newLine();
        $previews = $cleaner->previewReleases($limit);
        if (empty($previews)) {
            $this->info('No House-of-Usenet releases found.');

            return Command::SUCCESS;
        }
        foreach ($previews as $preview) {
            $this->line("Release ID: {$preview['id']}");
            $this->line('  Original:  '.substr($preview['original_name'], 0, 120));
            $this->line('  Current:   '.substr($preview['current_searchname'], 0, 80));
            if ($preview['extracted_name']) {
                $style = $preview['will_rename'] ? 'info' : 'comment';
                $this->line("  <{$style}>Extracted: ".substr($preview['extracted_name'], 0, 80)."</{$style}>");
            }
            $actions = [];
            if ($preview['will_rename']) {
                $actions[] = 'will rename';
            }
            if (! empty($actions)) {
                $this->line('  <fg=green>Actions: '.implode(', ', $actions).'</>');
            }
            $this->newLine();
        }
        $this->info('Showing '.count($previews).' releases.');

        return Command::SUCCESS;
    }
}
