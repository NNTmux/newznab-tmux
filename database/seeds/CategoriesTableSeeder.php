<?php

use Illuminate\Database\Seeder;

class CategoriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        \DB::table('categories')->delete();

        \DB::table('categories')->insert([
            1 =>
            [
                'id' => 10,
                'title' => 'Misc',
                'parentid' => 1,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            2 =>
            [
                'id' => 20,
                'title' => 'Hashed',
                'parentid' => 1,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            4 =>
            [
                'id' => 1010,
                'title' => 'NDS',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            5 =>
            [
                'id' => 1020,
                'title' => 'PSP',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            6 =>
            [
                'id' => 1030,
                'title' => 'Wii',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            7 =>
            [
                'id' => 1040,
                'title' => 'Xbox',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            8 =>
            [
                'id' => 1050,
                'title' => 'Xbox 360',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            9 =>
            [
                'id' => 1060,
                'title' => 'WiiWare VC',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            10 =>
            [
                'id' => 1070,
                'title' => 'Xbox 360 DLC',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            11 =>
            [
                'id' => 1080,
                'title' => 'PS3',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            12 =>
            [
                'id' => 1110,
                'title' => '3DS',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            13 =>
            [
                'id' => 1120,
                'title' => 'PS Vita',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            14 =>
            [
                'id' => 1130,
                'title' => 'WiiU',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            15 =>
            [
                'id' => 1140,
                'title' => 'Xbox One',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            16 =>
            [
                'id' => 1180,
                'title' => 'PS4',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            17 =>
            [
                'id' => 1999,
                'title' => 'Other',
                'parentid' => 1000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            19 =>
            [
                'id' => 2010,
                'title' => 'Foreign',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            20 =>
            [
                'id' => 2030,
                'title' => 'SD',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            21 =>
            [
                'id' => 2040,
                'title' => 'HD',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            22 =>
            [
                'id' => 2045,
                'title' => 'UHD',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            23 =>
            [
                'id' => 2050,
                'title' => '3D',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            24 =>
            [
                'id' => 2060,
                'title' => 'BluRay',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            25 =>
            [
                'id' => 2070,
                'title' => 'DVD',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            26 =>
            [
                'id' => 2080,
                'title' => 'WEBDL',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            27 =>
            [
                'id' => 2999,
                'title' => 'Other',
                'parentid' => 2000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            29 =>
            [
                'id' => 3010,
                'title' => 'MP3',
                'parentid' => 3000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            30 =>
            [
                'id' => 3020,
                'title' => 'Video',
                'parentid' => 3000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            31 =>
            [
                'id' => 3030,
                'title' => 'Audiobook',
                'parentid' => 3000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            32 =>
            [
                'id' => 3040,
                'title' => 'Lossless',
                'parentid' => 3000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            33 =>
            [
                'id' => 3060,
                'title' => 'Foreign',
                'parentid' => 3000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            34 =>
            [
                'id' => 3999,
                'title' => 'Other',
                'parentid' => 3000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            36 =>
            [
                'id' => 4010,
                'title' => '0day',
                'parentid' => 4000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            37 =>
            [
                'id' => 4020,
                'title' => 'ISO',
                'parentid' => 4000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            38 =>
            [
                'id' => 4030,
                'title' => 'Mac',
                'parentid' => 4000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            39 =>
            [
                'id' => 4050,
                'title' => 'Games',
                'parentid' => 4000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            40 =>
            [
                'id' => 4060,
                'title' => 'Phone-IOS',
                'parentid' => 4000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            41 =>
            [
                'id' => 4070,
                'title' => 'Phone-Android',
                'parentid' => 4000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            42 =>
            [
                'id' => 4999,
                'title' => 'Phone-Other',
                'parentid' => 4000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            44 =>
            [
                'id' => 5010,
                'title' => 'WEB-DL',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            45 =>
            [
                'id' => 5020,
                'title' => 'Foreign',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            46 =>
            [
                'id' => 5030,
                'title' => 'SD',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            47 =>
            [
                'id' => 5040,
                'title' => 'HD',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            48 =>
            [
                'id' => 5045,
                'title' => 'UHD',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            49 =>
            [
                'id' => 5060,
                'title' => 'Sport',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            50 =>
            [
                'id' => 5070,
                'title' => 'Anime',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            51 =>
            [
                'id' => 5080,
                'title' => 'Documentary',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            52 =>
            [
                'id' => 5999,
                'title' => 'Other',
                'parentid' => 5000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            54 =>
            [
                'id' => 6010,
                'title' => 'DVD',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            55 =>
            [
                'id' => 6020,
                'title' => 'WMV',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            56 =>
            [
                'id' => 6030,
                'title' => 'XviD',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            57 =>
            [
                'id' => 6040,
                'title' => 'x264',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            58 =>
            [
                'id' => 6041,
                'title' => 'HD Clips',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            59 =>
            [
                'id' => 6042,
                'title' => 'SD Clips',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            60 =>
            [
                'id' => 6045,
                'title' => 'UHD',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            61 =>
            [
                'id' => 6060,
                'title' => 'Imageset',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            62 =>
            [
                'id' => 6070,
                'title' => 'Packs',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            63 =>
            [
                'id' => 6080,
                'title' => 'SD',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            64 =>
            [
                'id' => 6090,
                'title' => 'WEBDL',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            65 =>
            [
                'id' => 6999,
                'title' => 'Other',
                'parentid' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            67 =>
            [
                'id' => 7010,
                'title' => 'Ebook',
                'parentid' => 7000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            68 =>
            [
                'id' => 7020,
                'title' => 'Comics',
                'parentid' => 7000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            69 =>
            [
                'id' => 7030,
                'title' => 'Magazines',
                'parentid' => 7000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            70 =>
            [
                'id' => 7040,
                'title' => 'Technical',
                'parentid' => 7000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            71 =>
            [
                'id' => 7060,
                'title' => 'Foreign',
                'parentid' => 7000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            72 =>
            [
                'id' => 7999,
                'title' => 'Other',
                'parentid' => 7000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ],
            73 =>
                [
                    'id' => 2090,
                    'title' => 'X265',
                    'parentid' => 2000,
                    'status' => 1,
                    'description' => null,
                    'disablepreview' => 0,
                    'minsizetoformrelease' => 0,
                    'maxsizetoformrelease' => 0,
                ],
            74 =>
                [
                    'id' => 5090,
                    'title' => 'X265',
                    'parentid' => 5000,
                    'status' => 1,
                    'description' => null,
                    'disablepreview' => 0,
                    'minsizetoformrelease' => 0,
                    'maxsizetoformrelease' => 0,
                ],
        ]);
    }
}
