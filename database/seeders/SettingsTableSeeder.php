<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     */
    public function run(): void
    {
        DB::table('settings')->delete();

        DB::table('settings')->insert([
            0 => [
                'name' => 'addpar2',
                'value' => '0',
            ],
            2 => [
                'name' => 'alternate_nntp',
                'value' => '0',
            ],
            4 => [
                'name' => 'backfillthreads',
                'value' => '1',
            ],
            5 => [
                'name' => 'binarythreads',
                'value' => '1',
            ],
            6 => [
                'name' => 'book_reqids',
                'value' => '7010',
            ],
            7 => [
                'name' => 'checkpasswordedrar',
                'value' => '0',
            ],
            8 => [
                'name' => 'completionpercent',
                'value' => '95',
            ],
            10 => [
                'name' => 'crossposttime',
                'value' => '2',
            ],
            11 => [
                'name' => 'currentppticket',
                'value' => '0',
            ],
            12 => [
                'name' => 'debuginfo',
                'value' => '0',
            ],
            13 => [
                'name' => 'delaytime',
                'value' => '2',
            ],
            16 => [
                'name' => 'disablebackfillgroup',
                'value' => '0',
            ],
            18 => [
                'name' => 'ffmpeg_duration',
                'value' => '5',
            ],
            19 => [
                'name' => 'ffmpeg_image_time',
                'value' => '5',
            ],
            20 => [
                'name' => 'fixnamesperrun',
                'value' => '10',
            ],
            21 => [
                'name' => 'fixnamethreads',
                'value' => '1',
            ],
            22 => [
                'name' => 'grabstatus',
                'value' => '1',
            ],
            23 => [
                'name' => 'lastpretime',
                'value' => '0',
            ],
            25 => [
                'name' => 'lookupanidb',
                'value' => '0',
            ],
            26 => [
                'name' => 'lookupbooks',
                'value' => '1',
            ],
            27 => [
                'name' => 'lookupgames',
                'value' => '1',
            ],
            28 => [
                'name' => 'lookupimdb',
                'value' => '1',
            ],
            29 => [
                'name' => 'lookupmusic',
                'value' => '1',
            ],
            30 => [
                'name' => 'lookupnfo',
                'value' => '1',
            ],
            32 => [
                'name' => 'lookuptv',
                'value' => '1',
            ],
            33 => [
                'name' => 'lookupxxx',
                'value' => '1',
            ],
            34 => [
                'name' => 'max_headers_iteration',
                'value' => '1000000',
            ],
            35 => [
                'name' => 'maxaddprocessed',
                'value' => '25',
            ],
            36 => [
                'name' => 'maxanidbprocessed',
                'value' => '100',
            ],
            37 => [
                'name' => 'maxbooksprocessed',
                'value' => '300',
            ],
            38 => [
                'name' => 'maxgamesprocessed',
                'value' => '150',
            ],
            39 => [
                'name' => 'maximdbprocessed',
                'value' => '100',
            ],
            40 => [
                'name' => 'maxmssgs',
                'value' => '20000',
            ],
            41 => [
                'name' => 'maxmusicprocessed',
                'value' => '150',
            ],
            42 => [
                'name' => 'maxnestedlevels',
                'value' => '3',
            ],
            43 => [
                'name' => 'maxnfoprocessed',
                'value' => '100',
            ],
            44 => [
                'name' => 'maxnforetries',
                'value' => '5',
            ],
            45 => [
                'name' => 'maxnzbsprocessed',
                'value' => '1000',
            ],
            46 => [
                'name' => 'maxpartrepair',
                'value' => '15000',
            ],
            47 => [
                'name' => 'maxpartsprocessed',
                'value' => '3',
            ],
            48 => [
                'name' => 'maxrageprocessed',
                'value' => '75',
            ],
            49 => [
                'name' => 'maxsizetopostprocess',
                'value' => '100',
            ],
            50 => [
                'name' => 'maxsizetoprocessnfo',
                'value' => '100',
            ],
            51 => [
                'name' => 'maxxxxprocessed',
                'value' => '100',
            ],
            52 => [
                'name' => 'minsizetopostprocess',
                'value' => '1',
            ],
            53 => [
                'name' => 'minsizetoprocessnfo',
                'value' => '1',
            ],
            54 => [
                'name' => 'mischashedretentionhours',
                'value' => '0',
            ],
            55 => [
                'name' => 'miscotherretentionhours',
                'value' => '0',
            ],
            56 => [
                'name' => 'newgroupdaystoscan',
                'value' => '1',
            ],
            57 => [
                'name' => 'newgroupmsgstoscan',
                'value' => '100000',
            ],
            58 => [
                'name' => 'newgroupscanmethod',
                'value' => '0',
            ],
            59 => [
                'name' => 'nextppticket',
                'value' => '0',
            ],
            60 => [
                'name' => 'nfothreads',
                'value' => '1',
            ],
            61 => [
                'name' => 'nntpretries',
                'value' => '10',
            ],
            62 => [
                'name' => 'nzbpath',
                'value' => '/var/www/nntmux/storage/nzb/',
            ],
            63 => [
                'name' => 'nzbsplitlevel',
                'value' => '4',
            ],
            64 => [
                'name' => 'nzbthreads',
                'value' => '1',
            ],
            65 => [
                'name' => 'partrepair',
                'value' => '1',
            ],
            66 => [
                'name' => 'partrepairmaxtries',
                'value' => '3',
            ],
            67 => [
                'name' => 'partretentionhours',
                'value' => '72',
            ],
            68 => [
                'name' => 'passchkattempts',
                'value' => '1',
            ],
            69 => [
                'name' => 'postdelay',
                'value' => '300',
            ],
            70 => [
                'name' => 'postthreads',
                'value' => '1',
            ],
            71 => [
                'name' => 'postthreadsamazon',
                'value' => '1',
            ],
            72 => [
                'name' => 'postthreadsnon',
                'value' => '1',
            ],
            75 => [
                'name' => 'processjpg',
                'value' => '0',
            ],
            76 => [
                'name' => 'processthumbnails',
                'value' => '0',
            ],
            77 => [
                'name' => 'processvideos',
                'value' => '0',
            ],
            78 => [
                'name' => 'registerstatus',
                'value' => '0',
            ],
            79 => [
                'name' => 'releaseretentiondays',
                'value' => '0',
            ],
            80 => [
                'name' => 'releasethreads',
                'value' => '1',
            ],
            85 => [
                'name' => 'safebackfilldate',
                'value' => '2012-06-24',
            ],
            86 => [
                'name' => 'safepartrepair',
                'value' => '0',
            ],
            87 => [
                'name' => 'saveaudiopreview',
                'value' => '0',
            ],
            88 => [
                'name' => 'segmentstodownload',
                'value' => '2',
            ],
            90 => [
                'name' => 'showdroppedyencparts',
                'value' => '0',
            ],
            91 => [
                'name' => 'showpasswordedrelease',
                'value' => '0',
            ],
            96 => [

                'name' => 'timeoutseconds',
                'value' => '0',
            ],
            98 => [
                'name' => 'userhostexclusion',
                'value' => '',
            ],
            99 => [
                'name' => 'maxsizetoformrelease',
                'value' => '0',
            ],
            100 => [
                'name' => 'minfilestoformrelease',
                'value' => '1',
            ],
            101 => [
                'name' => 'minsizetoformrelease',
                'value' => '0',
            ],
            112 => [
                'name' => 'banned',
                'value' => '0',
            ],
            126 => [
                'name' => 'end',
                'value' => '0',
            ],
            127 => [
                'name' => 'categorizeforeign',
                'value' => '1',
            ],
            128 => [
                'name' => 'catwebdl',
                'value' => '0',
            ],
            131 => [
                'name' => 'innerfileblacklist',
                'value' => '/setup.exe|password.url/i',
            ],
            132 => [
                'name' => 'collection_timeout',
                'value' => '48',
            ],
            133 => [
                'name' => 'last_run_time',
                'value' => '3015-08-04 15:58:23',
            ],
            141 => [
                'name' => 'code',
                'value' => 'NNTmux',
            ],
            143 => [
                'name' => 'dereferrer_link',
                'value' => '',
            ],
            145 => [
                'name' => 'footer',
                'value' => 'Usenet binary indexer.',
            ],
            146 => [
                'name' => 'home_link',
                'value' => '/',
            ],
            150 => [
                'name' => 'metadescription',
                'value' => 'A usenet indexing website',
            ],
            151 => [
                'name' => 'metakeywords',
                'value' => 'usenet,nzbs,cms,community',
            ],
            152 => [
                'name' => 'metatitle',
                'value' => 'An indexer',
            ],
            153 => [
                'name' => 'strapline',
                'value' => 'A great usenet indexer',
            ],
            155 => [
                'name' => 'tandc',
                'value' => '<p>All information within this database is indexed by an automated process, without any human intervention. It is obtained from global Usenet newsgroups over which this site has no control. We cannot prevent that you might find obscene or objectionable material by using this service. If you do come across obscene, incorrect or objectionable results, let us know by using the contact form.</p>',
            ],
            158 => [
                'name' => 'back_timer',
                'value' => '30',
            ],
            159 => [
                'name' => 'backfill',
                'value' => '0',
            ],
            160 => [
                'name' => 'backfill_days',
                'value' => '1',
            ],
            161 => [
                'name' => 'backfill_groups',
                'value' => '4',
            ],
            162 => [
                'name' => 'backfill_order',
                'value' => '2',
            ],
            163 => [
                'name' => 'backfill_qty',
                'value' => '100000',
            ],
            164 => [
                'name' => 'binaries',
                'value' => '0',
            ],
            165 => [
                'name' => 'bins_kill_timer',
                'value' => '1',
            ],
            166 => [
                'name' => 'bins_timer',
                'value' => '30',
            ],
            167 => [
                'name' => 'bwmng',
                'value' => '0',
            ],
            168 => [
                'name' => 'collections_kill',
                'value' => '0',
            ],
            169 => [
                'name' => 'colors',
                'value' => '0',
            ],
            170 => [
                'name' => 'colors_end',
                'value' => '250',
            ],
            171 => [
                'name' => 'colors_exc',
                'value' => '4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60',
            ],
            172 => [
                'name' => 'colors_start',
                'value' => '1',
            ],
            173 => [
                'name' => 'console',
                'value' => '0',
            ],
            174 => [
                'name' => 'crap_timer',
                'value' => '30',
            ],
            178 => [
                'name' => 'fix_crap',
                'value' => '0',
            ],
            179 => [
                'name' => 'fix_crap_opt',
                'value' => 'Disabled',
            ],
            180 => [
                'name' => 'fix_names',
                'value' => '0',
            ],
            181 => [
                'name' => 'fix_timer',
                'value' => '30',
            ],
            182 => [
                'name' => 'htop',
                'value' => '0',
            ],
            187 => [
                'name' => 'monitor_delay',
                'value' => '30',
            ],
            188 => [
                'name' => 'monitor_path',
                'value' => 'NULL',
            ],
            189 => [
                'name' => 'monitor_path_a',
                'value' => 'NULL',
            ],
            190 => [
                'name' => 'monitor_path_b',
                'value' => 'NULL',
            ],
            191 => [
                'name' => 'mytop',
                'value' => '0',
            ],
            192 => [
                'name' => 'nfos',
                'value' => '0',
            ],
            193 => [
                'name' => 'niceness',
                'value' => '19',
            ],
            194 => [
                'name' => 'nmon',
                'value' => '0',
            ],
            200 => [
                'name' => 'post',
                'value' => '0',
            ],
            201 => [
                'name' => 'post_amazon',
                'value' => '0',
            ],
            202 => [
                'name' => 'post_kill_timer',
                'value' => '300',
            ],
            203 => [
                'name' => 'post_non',
                'value' => '0',
            ],
            204 => [
                'name' => 'post_timer',
                'value' => '30',
            ],
            205 => [
                'name' => 'post_timer_amazon',
                'value' => '30',
            ],
            206 => [
                'name' => 'post_timer_non',
                'value' => '30',
            ],
            207 => [
                'name' => 'postprocess_kill',
                'value' => '0',
            ],
            209 => [
                'name' => 'processupdate',
                'value' => '2',
            ],
            210 => [
                'name' => 'progressive',
                'value' => '0',
            ],
            211 => [
                'name' => 'redis',
                'value' => '0',
            ],
            237 => [
                'name' => 'redis_args',
                'value' => 'NULL',
            ],
            212 => [
                'name' => 'rel_timer',
                'value' => '30',
            ],
            213 => [
                'name' => 'releases',
                'value' => '0',
            ],
            215 => [
                'name' => 'run_ircscraper',
                'value' => '0',
            ],
            217 => [
                'name' => 'running',
                'value' => '0',
            ],
            218 => [
                'name' => 'seq_timer',
                'value' => '30',
            ],
            219 => [
                'name' => 'sequential',
                'value' => '0',
            ],
            221 => [
                'name' => 'showprocesslist',
                'value' => '0',
            ],
            222 => [
                'name' => 'showquery',
                'value' => '0',
            ],
            223 => [

                'name' => 'sorter',
                'value' => '0',
            ],
            224 => [
                'name' => 'sorter_timer',
                'value' => '30',
            ],
            225 => [
                'name' => 'tcptrack',
                'value' => '0',
            ],
            226 => [
                'name' => 'tcptrack_args',
                'value' => '-i eth0 port 443',
            ],
            227 => [
                'name' => 'tmux_session',
                'value' => 'nntmux',
            ],
            230 => [
                'name' => 'vnstat',
                'value' => '0',
            ],
            231 => [
                'name' => 'vnstat_args',
                'value' => 'NULL',
            ],
            233 => [
                'name' => 'trailers_display',
                'value' => '1',
            ],
            234 => [
                'name' => 'trailers_size_x',
                'value' => '480',
            ],
            235 => [
                'name' => 'trailers_size_y',
                'value' => '345',
            ],
            236 => [
                'name' => 'exit',
                'value' => '0',
            ],
            239 => [
                'name' => 'releaseprocessingtimeout',
                'value' => '120',
            ],
            240 => [
                'name' => 'maxpptimeoutcount',
                'value' => '3',
            ],
        ]);
    }
}
