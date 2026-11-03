<?php

namespace App\Console\Commands;

use App\Models\UsenetGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:reset
                            {group : The group name or ID to reset}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset a specific usenet group so it can be backfilled again';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $group = $this->argument('group');

        try {
            // Try to find the group by name first, then by ID
            /** @var UsenetGroup|null $groupModel */
            $groupModel = UsenetGroup::getByName($group);

            if ($groupModel === null && is_numeric($group)) {
                $groupModel = UsenetGroup::query()->find($group);
            }

            if ($groupModel === null) {
                $this->error("Group '{$group}' not found.");

                return self::FAILURE;
            }

            $this->info("Group found: {$groupModel->name} (ID: {$groupModel->id})");
            $this->info('Current state:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['first_record', $groupModel->first_record ?? 'null'],
                    ['first_record_postdate', $groupModel->first_record_postdate ?? 'null'],
                    ['last_record', $groupModel->last_record ?? 'null'],
                    ['last_record_postdate', $groupModel->last_record_postdate ?? 'null'],
                    ['active', $groupModel->active],
                    ['backfill', $groupModel->backfill ?? 0],
                    ['backfill_target', $groupModel->backfill_target ?? 1],
                ]
            );

            if (! $this->option('force')) {
                if (! $this->confirm('Are you sure you want to reset this group? This will clear article pointers and disable the group.')) {
                    $this->info('Operation cancelled.');

                    return self::SUCCESS;
                }
            }

            $result = UsenetGroup::reset($groupModel->id);

            if ($result) {
                $this->info("Group '{$groupModel->name}' has been reset successfully.");
                $this->info('The group is now inactive. Enable it and run update_binaries before backfilling.');

                return self::SUCCESS;
            }

            $this->error('Failed to reset the group.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
