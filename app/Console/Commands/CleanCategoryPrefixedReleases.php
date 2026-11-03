<?php

namespace App\Console\Commands;

use App\Services\NameFixing\CategoryPrefixedCleanerService;
use Illuminate\Console\Command;

class CleanCategoryPrefixedReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:clean-category-prefixed
                            {--limit=0 : Maximum number of releases to process (0 = no limit)}
                            {--dry-run : Preview changes without updating the database}
                            {--preview : Show a preview of releases that would be affected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean releases with category-prefixed subject lines: extract names from subjects like "(Porn Video) Title Here.part01.rar.vol031+32.par2"';

    /**
     * Execute the console command.
     */
    public function handle(CategoryPrefixedCleanerService $cleaner): int
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

        $this->info('Processing category-prefixed releases...');

        $stats = $cleaner->processReleases($limit, $dryRun);

        $this->newLine();
        $this->info('Processing complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $stats['processed']],
                ['Renamed', $stats['renamed']],
                ['Skipped (no match/no change)', $stats['skipped']],
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
    protected function showPreview(CategoryPrefixedCleanerService $cleaner, int $limit): int
    {
        $this->info("Previewing up to {$limit} category-prefixed releases...");
        $this->newLine();

        $previews = $cleaner->previewReleases($limit);

        if (empty($previews)) {
            $this->info('No category-prefixed releases found.');

            return Command::SUCCESS;
        }

        foreach ($previews as $preview) {
            $this->line("Release ID: {$preview['id']}");
            $this->line('  Original:  '.substr($preview['original_name'], 0, 120));
            $this->line('  Current:   '.substr($preview['current_searchname'], 0, 80));
            $this->line('  Category:  '.($preview['extracted_category'] ?? 'N/A'));

            if ($preview['extracted_name']) {
                $style = $preview['will_rename'] ? 'info' : 'comment';
                $this->line("  <{$style}>Extracted: ".substr($preview['extracted_name'], 0, 80)."</{$style}>");
            }

            if ($preview['will_rename']) {
                $this->line('  <fg=green>Action: will rename</>');
            }

            $this->newLine();
        }

        $this->info('Showing '.count($previews).' releases.');

        return Command::SUCCESS;
    }
}
