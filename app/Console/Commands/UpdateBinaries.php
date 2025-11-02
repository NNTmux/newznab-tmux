<?php

namespace App\Console\Commands;

use App\Models\Settings;
use App\Models\UsenetGroup;
use Blacklight\Binaries;
use Blacklight\NNTP;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateBinaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:binaries
                            {group? : Group name to update (optional, processes all if omitted)}
                            {max? : Maximum headers to download}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update binaries for a specific group or all groups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $groupName = $this->argument('group');
        $max = $this->argument('max');

        $maxHeaders = is_numeric($max) && $max > 0
            ? (int) $max
            : ((int) Settings::settingValue('max_headers_iteration') ?: 1000000);

        try {
            $nntp = $this->getNntp();
            $binaries = new Binaries(['NNTP' => $nntp]);

            if ($groupName && ! is_numeric($groupName)) {
                $this->info("Updating group: {$groupName}");
                $this->updateSingleGroup($binaries, $groupName, $maxHeaders);
            } else {
                $this->info('Updating all groups...');
                $binaries->updateAllGroups($maxHeaders);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Update a single group.
     */
    private function updateSingleGroup(Binaries $binaries, string $groupName, int $maxHeaders): void
    {
        $group = UsenetGroup::getByName($groupName)->toArray();

        if (! is_array($group)) {
            throw new \Exception("Group not found: {$groupName}");
        }

        $binaries->updateGroup($group, $maxHeaders);
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP;

        if ($nntp->doConnect() !== true) {
            throw new \Exception('Unable to connect to usenet.');
        }

        return $nntp;
    }
}
