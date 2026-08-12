<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Release;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('releases:requeue-missing-video-previews
    {--dry-run : Report the matching releases without changing them (default)}
    {--apply : Return matching releases to the pending state}')]
#[Description('Re-queue non-RAR movie and TV releases affected by missing media previews')]
class RequeueMissingVideoPreviews extends Command
{
    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $candidates = $this->candidates();
        $count = $candidates->count();

        if (! $this->option('apply')) {
            $this->info("Dry run: {$count} releases would be re-queued.");

            return self::SUCCESS;
        }

        $updated = $candidates->update([
            'haspreview' => -1,
            'passwordstatus' => -1,
        ]);

        $this->info("Re-queued {$updated} releases.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Release>
     */
    private function candidates(): Builder
    {
        return Release::query()
            ->whereIn('categories_id', [...Category::MOVIES_GROUP, ...Category::TV_GROUP])
            ->where('haspreview', 0)
            ->where('passwordstatus', 0)
            ->where('rarinnerfilecount', 0);
    }
}
