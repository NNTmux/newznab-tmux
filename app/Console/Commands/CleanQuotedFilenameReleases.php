<?php

namespace App\Console\Commands;

use App\Services\NameFixing\QuotedFilenameCleanerService;
use Illuminate\Console\Command;

class CleanQuotedFilenameReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:clean-quoted
                            {--limit=0 : Maximum number of releases to process (0 = no limit)}
                            {--dry-run : Preview changes without updating the database}
                            {--preview : Show a preview of releases that would be affected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean releases with quoted filename patterns: extract names from [##/##] "filename.ext" style subject lines';

    /**
     * Execute the console command.
     */
    public function handle(QuotedFilenameCleanerService $cleaner): int
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

        $this->info('Processing quoted filename releases...');

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
    protected function showPreview(QuotedFilenameCleanerService $cleaner, int $limit): int
    {
        $this->info("Previewing up to {$limit} quoted filename releases...");
        $this->newLine();

        $previews = $cleaner->previewReleases($limit);

        if (empty($previews)) {
            $this->info('No quoted filename releases found.');

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

            if ($preview['will_rename']) {
                $this->line('  <fg=green>Action: will rename</>');
            }

            $this->newLine();
        }

        $this->info('Showing '.count($previews).' releases.');

        return Command::SUCCESS;
    }
}
