<?php

namespace App\Console\Commands;

use App\Models\ShortGroup;
use App\Models\UsenetGroup;
use App\Services\NNTP\NNTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GroupsUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'groups:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update first/last article numbers for all active groups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $start = now();

        // Create NNTP connection
        $nntp = new NNTPService;
        $connectResult = $nntp->doConnect();
        if ($connectResult !== true) {
            $errorMessage = '❌ Unable to connect to usenet server';
            if (NNTPService::isError($connectResult)) {
                $errorMessage .= ' Error: '.$connectResult->getMessage();
            }
            $this->error($errorMessage);

            return Command::FAILURE;
        }

        $this->info('📡 Getting first/last for all active groups...');

        try {
            $data = $nntp->getGroups();

            if ($nntp->isError($data)) {
                $this->error('❌ Failed to getGroups() from NNTP server');

                return Command::FAILURE;
            }

            $this->info('🔄 Updating short_groups table...');

            // Truncate and rebuild
            DB::statement('TRUNCATE TABLE short_groups');

            // Get all active groups
            $activeGroups = Arr::pluck(
                UsenetGroup::query()
                    ->where('active', '=', 1)
                    ->orWhere('backfill', '=', 1)
                    ->get(['name']),
                'name'
            );

            $updated = 0;
            $bar = $this->output->createProgressBar(count($data));
            $bar->start();

            foreach ($data as $newgroup) {
                if (\in_array($newgroup['group'], $activeGroups, true)) {
                    ShortGroup::query()->insert([
                        'name' => $newgroup['group'],
                        'first_record' => $newgroup['first'],
                        'last_record' => $newgroup['last'],
                        'updated' => now(),
                    ]);

                    $updated++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $elapsed = now()->diffInSeconds($start, true);
            $this->info("✅ Updated {$updated} groups");
            $this->info("⏱️  Running time: {$elapsed} seconds");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Update failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
