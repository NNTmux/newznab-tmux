<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NntmuxResetTruncate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:reset-truncate {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command removes releases with no NZBs, resets all groups, truncates or drops(tpg)
        article tables. All other releases are left alone.';

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
    public function handle()
    {
        Group::query()->update(['first_record' => 0, 'first_record_postdate' => null, 'last_record' => 0, 'last_record_postdate' => null, 'last_updated' => null]);
        $this->info('Reseting all groups completed.');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        $arr = ['parts', 'missed_parts', 'binaries', 'collections', 'multigroup_parts', 'multigroup_missed_parts', 'multigroup_binaries', 'multigroup_collections'];

        foreach ($arr as &$value) {
            DB::statement("TRUNCATE TABLE $value");
            $this->info("Truncating $value completed.");
        }
        unset($value);

        foreach (DB::select('SHOW table status') as $row) {
            $tbl = $row->Name;
            if (preg_match('/collections_\d+/', $tbl) || preg_match('/binaries_\d+/', $tbl) || preg_match('/parts_\d+/', $tbl) || preg_match('/missed_parts_\d+/', $tbl) || preg_match('/\d+_collections/', $tbl) || preg_match('/\d+_binaries/', $tbl) || preg_match('/\d+_parts/', $tbl) || preg_match('/\d+_missed_parts_\d+/', $tbl)) {
                if ($this->argument('type') === 'true') {
                    DB::statement("DROP TABLE $tbl");
                    $this->info("Dropping $tbl completed.");
                } else {
                    DB::statement("TRUNCATE TABLE $tbl");
                    $this->info("Truncating $tbl completed.");

                }
            }
        }

        $delcount = Release::query()->where('nzbstatus', '=', 0)->delete();
        $this->info($delcount.' releases had no nzb, deleted.');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
