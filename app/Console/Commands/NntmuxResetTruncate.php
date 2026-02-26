<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Models\UsenetGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NntmuxResetTruncate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:reset-truncate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command removes releases with no NZBs, resets all groups, truncates article tables. All other releases are left alone.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        UsenetGroup::query()->update(['first_record' => 0, 'first_record_postdate' => null, 'last_record' => 0, 'last_record_postdate' => null, 'last_updated' => null]);
        $this->info('Reseting all groups completed.');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        foreach (['parts', 'missed_parts', 'binaries', 'collections'] as &$value) {
            DB::statement("TRUNCATE TABLE $value");
            $this->info("Truncating $value completed.");
        }
        unset($value);

        $delcount = Release::query()->where('nzbstatus', '=', 0)->delete();
        $this->info($delcount.' releases had no nzb, deleted.');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
