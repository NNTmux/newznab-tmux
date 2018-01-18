<?php

use Illuminate\Database\Seeder;

class TmuxTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('tmux')->delete();

        \DB::table('tmux')->insert(array (
            0 =>
            array (
                'id' => 1,
                'setting' => 'defrag_cache',
                'value' => '900',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            1 =>
            array (
                'id' => 2,
                'setting' => 'monitor_delay',
                'value' => '300',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            2 =>
            array (
                'id' => 3,
                'setting' => 'tmux_session',
                'value' => 'nntmux',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            3 =>
            array (
                'id' => 4,
                'setting' => 'niceness',
                'value' => '19',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            4 =>
            array (
                'id' => 5,
                'setting' => 'binaries',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            5 =>
            array (
                'id' => 6,
                'setting' => 'backfill',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            6 =>
            array (
                'id' => 7,
                'setting' => 'import',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            7 =>
            array (
                'id' => 8,
                'setting' => 'nzbs',
                'value' => '/path/to/nzbs',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            8 =>
            array (
                'id' => 9,
                'setting' => 'running',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            9 =>
            array (
                'id' => 10,
                'setting' => 'sequential',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            10 =>
            array (
                'id' => 11,
                'setting' => 'nfos',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            11 =>
            array (
                'id' => 12,
                'setting' => 'post',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            12 =>
            array (
                'id' => 13,
                'setting' => 'releases',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            13 =>
            array (
                'id' => 14,
                'setting' => 'releases_threaded',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            14 =>
            array (
                'id' => 15,
                'setting' => 'fix_names',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            15 =>
            array (
                'id' => 16,
                'setting' => 'seq_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            16 =>
            array (
                'id' => 17,
                'setting' => 'bins_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            17 =>
            array (
                'id' => 18,
                'setting' => 'bins_kill_timer',
                'value' => '1',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            18 =>
            array (
                'id' => 19,
                'setting' => 'back_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            19 =>
            array (
                'id' => 20,
                'setting' => 'import_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            20 =>
            array (
                'id' => 21,
                'setting' => 'rel_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            21 =>
            array (
                'id' => 22,
                'setting' => 'fix_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            22 =>
            array (
                'id' => 23,
                'setting' => 'post_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            23 =>
            array (
                'id' => 24,
                'setting' => 'import_bulk',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            24 =>
            array (
                'id' => 25,
                'setting' => 'backfill_qty',
                'value' => '100000',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            25 =>
            array (
                'id' => 26,
                'setting' => 'collections_kill',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            26 =>
            array (
                'id' => 27,
                'setting' => 'postprocess_kill',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            27 =>
            array (
                'id' => 28,
                'setting' => 'crap_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            28 =>
            array (
                'id' => 29,
                'setting' => 'fix_crap',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            29 =>
            array (
                'id' => 30,
                'setting' => 'tv_timer',
                'value' => '43200',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            30 =>
            array (
                'id' => 31,
                'setting' => 'update_tv',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            31 =>
            array (
                'id' => 32,
                'setting' => 'htop',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            32 =>
            array (
                'id' => 33,
                'setting' => 'nmon',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            33 =>
            array (
                'id' => 34,
                'setting' => 'bwmng',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            34 =>
            array (
                'id' => 35,
                'setting' => 'mytop',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            35 =>
            array (
                'id' => 36,
                'setting' => 'console',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            36 =>
            array (
                'id' => 37,
                'setting' => 'vnstat',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            37 =>
            array (
                'id' => 38,
                'setting' => 'vnstat_args',
                'value' => 'NULL',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            38 =>
            array (
                'id' => 39,
                'setting' => 'tcptrack',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            39 =>
            array (
                'id' => 40,
                'setting' => 'tcptrack_args',
                'value' => '-i eth0 port 443',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            40 =>
            array (
                'id' => 41,
                'setting' => 'backfill_groups',
                'value' => '4',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            41 =>
            array (
                'id' => 42,
                'setting' => 'post_kill_timer',
                'value' => '300',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            42 =>
            array (
                'id' => 43,
                'setting' => 'optimize',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            43 =>
            array (
                'id' => 44,
                'setting' => 'optimize_timer',
                'value' => '86400',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            44 =>
            array (
                'id' => 45,
                'setting' => 'monitor_path',
                'value' => 'NULL',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            45 =>
            array (
                'id' => 46,
                'setting' => 'write_logs',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            46 =>
            array (
                'id' => 47,
                'setting' => 'sorter',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            47 =>
            array (
                'id' => 48,
                'setting' => 'sorter_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            48 =>
            array (
                'id' => 49,
                'setting' => 'powerline',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            49 =>
            array (
                'id' => 50,
                'setting' => 'patchdb',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            50 =>
            array (
                'id' => 51,
                'setting' => 'patchdb_timer',
                'value' => '21600',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            51 =>
            array (
                'id' => 52,
                'setting' => 'progressive',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            52 =>
            array (
                'id' => 53,
                'setting' => 'dehash',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            53 =>
            array (
                'id' => 54,
                'setting' => 'dehash_timer',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            54 =>
            array (
                'id' => 55,
                'setting' => 'backfill_order',
                'value' => '2',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            55 =>
            array (
                'id' => 56,
                'setting' => 'backfill_days',
                'value' => '1',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            56 =>
            array (
                'id' => 57,
                'setting' => 'post_amazon',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            57 =>
            array (
                'id' => 58,
                'setting' => 'post_non',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            58 =>
            array (
                'id' => 59,
                'setting' => 'post_timer_amazon',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            59 =>
            array (
                'id' => 60,
                'setting' => 'post_timer_non',
                'value' => '30',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            60 =>
            array (
                'id' => 61,
                'setting' => 'colors_start',
                'value' => '1',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            61 =>
            array (
                'id' => 62,
                'setting' => 'colors_end',
                'value' => '250',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            62 =>
            array (
                'id' => 63,
                'setting' => 'colors_exc',
                'value' => '4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            63 =>
            array (
                'id' => 64,
                'setting' => 'monitor_path_a',
                'value' => 'NULL',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            64 =>
            array (
                'id' => 65,
                'setting' => 'monitor_path_b',
                'value' => 'NULL',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            65 =>
            array (
                'id' => 66,
                'setting' => 'colors',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            66 =>
            array (
                'id' => 67,
                'setting' => 'showquery',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            67 =>
            array (
                'id' => 68,
                'setting' => 'fix_crap_opt',
                'value' => 'Disabled',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            68 =>
            array (
                'id' => 69,
                'setting' => 'showprocesslist',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            69 =>
            array (
                'id' => 70,
                'setting' => 'processupdate',
                'value' => '2',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            70 =>
            array (
                'id' => 71,
                'setting' => 'run_ircscraper',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            71 =>
            array (
                'id' => 72,
                'setting' => 'run_sharing',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            72 =>
            array (
                'id' => 73,
                'setting' => 'sharing_timer',
                'value' => '60',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            73 =>
            array (
                'id' => 74,
                'setting' => 'import_count',
                'value' => '50000',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            74 =>
            array (
                'id' => 75,
                'setting' => 'debuginfo',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
            75 =>
            array (
                'id' => 76,
                'setting' => 'redis',
                'value' => '0',
                'updated_at' => '2018-01-18 11:11:35',
            ),
        ));


    }
}