<?php

use Illuminate\Database\Seeder;

class TmuxTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('tmux')->delete();

        \DB::table('tmux')->insert([
            0 => [
                'id' => 1,
                'setting' => 'defrag_cache',
                'value' => '900',
                'updateddate' => '',
            ],
            1 => [
                'id' => 2,
                'setting' => 'monitor_delay',
                'value' => '30',
                'updateddate' => '',
            ],
            2 => [
                'id' => 3,
                'setting' => 'tmux_session',
                'value' => 'nntmux',
                'updateddate' => '',
            ],
            3 => [
                'id' => 4,
                'setting' => 'niceness',
                'value' => '19',
                'updateddate' => '',
            ],
            4 => [
                'id' => 5,
                'setting' => 'binaries',
                'value' => '2',
                'updateddate' => '',
            ],
            5 => [
                'id' => 6,
                'setting' => 'backfill',
                'value' => '0',
                'updateddate' => '',
            ],
            6 => [
                'id' => 7,
                'setting' => 'import',
                'value' => '0',
                'updateddate' => '',
            ],
            7 => [
                'id' => 8,
                'setting' => 'nzbs',
                'value' => '/path/to/nzbs',
                'updateddate' => '',
            ],
            8 => [
                'id' => 9,
                'setting' => 'running',
                'value' => '0',
                'updateddate' => '',
            ],
            9 => [
                'id' => 10,
                'setting' => 'sequential',
                'value' => '0',
                'updateddate' => '',
            ],
            10 => [
                'id' => 11,
                'setting' => 'nfos',
                'value' => '0',
                'updateddate' => '',
            ],
            11 => [
                'id' => 12,
                'setting' => 'post',
                'value' => '3',
                'updateddate' => '',
            ],
            12 => [
                'id' => 13,
                'setting' => 'releases',
                'value' => '1',
                'updateddate' => '',
            ],
            13 => [
                'id' => 14,
                'setting' => 'releases_threaded',
                'value' => '0',
                'updateddate' => '',
            ],
            14 => [
                'id' => 15,
                'setting' => 'fix_names',
                'value' => '1',
                'updateddate' => '',
            ],
            15 => [
                'id' => 16,
                'setting' => 'seq_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            16 => [
                'id' => 17,
                'setting' => 'bins_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            17 => [
                'id' => 18,
                'setting' => 'back_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            18 => [
                'id' => 19,
                'setting' => 'import_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            19 => [
                'id' => 20,
                'setting' => 'rel_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            20 => [
                'id' => 21,
                'setting' => 'fix_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            21 => [
                'id' => 22,
                'setting' => 'post_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            22 => [
                'id' => 23,
                'setting' => 'import_bulk',
                'value' => '0',
                'updateddate' => '',
            ],
            23 => [
                'id' => 24,
                'setting' => 'backfill_qty',
                'value' => '100000',
                'updateddate' => '',
            ],
            24 => [
                'id' => 25,
                'setting' => 'collections_kill',
                'value' => '0',
                'updateddate' => '',
            ],
            25 => [
                'id' => 26,
                'setting' => 'postprocess_kill',
                'value' => '0',
                'updateddate' => '',
            ],
            26 => [
                'id' => 27,
                'setting' => 'crap_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            27 => [
                'id' => 28,
                'setting' => 'fix_crap',
                'value' => 'nzb',
                'updateddate' => '',
            ],
            28 => [
                'id' => 29,
                'setting' => 'tv_timer',
                'value' => '43200',
                'updateddate' => '',
            ],
            29 => [
                'id' => 30,
                'setting' => 'update_tv',
                'value' => '0',
                'updateddate' => '',
            ],
            30 => [
                'id' => 31,
                'setting' => 'htop',
                'value' => '0',
                'updateddate' => '',
            ],
            31 => [
                'id' => 32,
                'setting' => 'nmon',
                'value' => '0',
                'updateddate' => '',
            ],
            32 => [
                'id' => 33,
                'setting' => 'bwmng',
                'value' => '0',
                'updateddate' => '',
            ],
            33 => [
                'id' => 34,
                'setting' => 'mytop',
                'value' => '0',
                'updateddate' => '',
            ],
            34 => [
                'id' => 35,
                'setting' => 'console',
                'value' => '0',
                'updateddate' => '',
            ],
            35 => [
                'id' => 36,
                'setting' => 'vnstat',
                'value' => '0',
                'updateddate' => '',
            ],
            36 => [
                'id' => 37,
                'setting' => 'vnstat_args',
                'value' => '',
                'updateddate' => '',
            ],
            37 => [
                'id' => 38,
                'setting' => 'tcptrack',
                'value' => '0',
                'updateddate' => '2',
            ],
            38 => [
                'id' => 39,
                'setting' => 'tcptrack_args',
                'value' => '-i eth0 port 443',
                'updateddate' => '',
            ],
            39 => [
                'id' => 40,
                'setting' => 'backfill_groups',
                'value' => '4',
                'updateddate' => '',
            ],
            40 => [
                'id' => 41,
                'setting' => 'post_kill_timer',
                'value' => '300',
                'updateddate' => '',
            ],
            41 => [
                'id' => 42,
                'setting' => 'optimize',
                'value' => '0',
                'updateddate' => '',
            ],
            42 => [
                'id' => 43,
                'setting' => 'optimize_timer',
                'value' => '86400',
                'updateddate' => '',
            ],
            43 => [
                'id' => 44,
                'setting' => 'monitor_path',
                'value' => '',
                'updateddate' => '',
            ],
            44 => [
                'id' => 45,
                'setting' => 'write_logs',
                'value' => '0',
                'updateddate' => '',
            ],
            45 => [
                'id' => 46,
                'setting' => 'sorter',
                'value' => '0',
                'updateddate' => '',
            ],
            46 => [
                'id' => 47,
                'setting' => 'sorter_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            47 => [
                'id' => 48,
                'setting' => 'powerline',
                'value' => '0',
                'updateddate' => '',
            ],
            48 => [
                'id' => 49,
                'setting' => 'patchdb',
                'value' => '0',
                'updateddate' => '',
            ],
            49 => [
                'id' => 50,
                'setting' => 'patchdb_timer',
                'value' => '21600',
                'updateddate' => '',
            ],
            50 => [
                'id' => 51,
                'setting' => 'progressive',
                'value' => '0',
                'updateddate' => '',
            ],
            51 => [
                'id' => 52,
                'setting' => 'dehash',
                'value' => '3',
                'updateddate' => '',
            ],
            52 => [
                'id' => 53,
                'setting' => 'dehash_timer',
                'value' => '30',
                'updateddate' => '',
            ],
            53 => [
                'id' => 54,
                'setting' => 'backfill_order',
                'value' => '2',
                'updateddate' => '',
            ],
            54 => [
                'id' => 55,
                'setting' => 'backfill_days',
                'value' => '1',
                'updateddate' => '',
            ],
            55 => [
                'id' => 56,
                'setting' => 'post_amazon',
                'value' => '1',
                'updateddate' => '',
            ],
            56 => [
                'id' => 57,
                'setting' => 'post_non',
                'value' => '1',
                'updateddate' => '',
            ],
            57 => [
                'id' => 58,
                'setting' => 'post_timer_amazon',
                'value' => '30',
                'updateddate' => '',
            ],
            58 => [
                'id' => 59,
                'setting' => 'post_timer_non',
                'value' => '30',
                'updateddate' => '',
            ],
            59 => [
                'id' => 60,
                'setting' => 'colors_start',
                'value' => '1',
                'updateddate' => '',
            ],
            60 => [
                'id' => 61,
                'setting' => 'colors_end',
                'value' => '250',
                'updateddate' => '',
            ],
            61 => [
                'id' => 62,
                'setting' => 'colors_exc',
                'value' => '4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60',
                'updateddate' => '',
            ],
            62 => [
                'id' => 63,
                'setting' => 'monitor_path_a',
                'value' => '',
                'updateddate' => '',
            ],
            63 => [
                'id' => 64,
                'setting' => 'monitor_path_b',
                'value' => '',
                'updateddate' => '',
            ],
            64 => [
                'id' => 65,
                'setting' => 'colors',
                'value' => '0',
                'updateddate' => '',
            ],
            65 => [
                'id' => 66,
                'setting' => 'showquery',
                'value' => '0',
                'updateddate' => '',
            ],
            66 => [
                'id' => 67,
                'setting' => 'fix_crap_opt',
                'value' => 'Custom',
                'updateddate' => '',
            ],
            67 => [
                'id' => 68,
                'setting' => 'showprocesslist',
                'value' => '0',
                'updateddate' => '',
            ],
            68 => [
                'id' => 69,
                'setting' => 'processupdate',
                'value' => '2',
                'updateddate' => '',
            ],
            69 => [
                'id' => 70,
                'setting' => 'run_ircscraper',
                'value' => '0',
                'updateddate' => '',
            ],
            70 => [
                'id' => 71,
                'setting' => 'run_sharing',
                'value' => '0',
                'updateddate' => '',
            ],
            71 => [
                'id' => 72,
                'setting' => 'sharing_timer',
                'value' => '60',
                'updateddate' => '',
            ],
            72 => [
                'id' => 73,
                'setting' => 'import_count',
                'value' => '50000',
                'updateddate' => '',
            ],
            73 => [
                'id' => 74,
                'setting' => 'debuginfo',
                'value' => '0',
                'updateddate' => '',
            ],
            74 => [
                'id' => 75,
                'setting' => 'bins_kill_timer',
                'value' => '1',
                'updateddate' => '',
            ],
        ]);
    }
}
