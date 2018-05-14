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

        \DB::table('categories')->insert(array (
            0 =>
            array (
                'id' => 1,
                'title' => 'Other',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            1 =>
            array (
                'id' => 10,
                'title' => 'Misc',
                'parentid' => 1,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            2 =>
            array (
                'id' => 20,
                'title' => 'Hashed',
                'parentid' => 1,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            3 =>
            array (
                'id' => 1000,
                'title' => 'Console',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            4 =>
            array (
                'id' => 1010,
                'title' => 'NDS',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            5 =>
            array (
                'id' => 1020,
                'title' => 'PSP',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            6 =>
            array (
                'id' => 1030,
                'title' => 'Wii',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            7 =>
            array (
                'id' => 1040,
                'title' => 'Xbox',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            8 =>
            array (
                'id' => 1050,
                'title' => 'Xbox 360',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            9 =>
            array (
                'id' => 1060,
                'title' => 'WiiWare VC',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            10 =>
            array (
                'id' => 1070,
                'title' => 'XBOX 360 DLC',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            11 =>
            array (
                'id' => 1080,
                'title' => 'PS3',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            12 =>
            array (
                'id' => 1110,
                'title' => '3DS',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            13 =>
            array (
                'id' => 1120,
                'title' => 'PS Vita',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            14 =>
            array (
                'id' => 1130,
                'title' => 'WiiU',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            15 =>
            array (
                'id' => 1140,
                'title' => 'Xbox One',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            16 =>
            array (
                'id' => 1180,
                'title' => 'PS4',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            17 =>
            array (
                'id' => 1999,
                'title' => 'Other',
                'parentid' => 1000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            18 =>
            array (
                'id' => 2000,
                'title' => 'Movies',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            19 =>
            array (
                'id' => 2010,
                'title' => 'Foreign',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            20 =>
            array (
                'id' => 2030,
                'title' => 'SD',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            21 =>
            array (
                'id' => 2040,
                'title' => 'HD',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            22 =>
            array (
                'id' => 2045,
                'title' => 'UHD',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            23 =>
            array (
                'id' => 2050,
                'title' => '3D',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            24 =>
            array (
                'id' => 2060,
                'title' => 'BluRay',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            25 =>
            array (
                'id' => 2070,
                'title' => 'DVD',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            26 =>
            array (
                'id' => 2080,
                'title' => 'WEBDL',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            27 =>
            array (
                'id' => 2999,
                'title' => 'Other',
                'parentid' => 2000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            28 =>
            array (
                'id' => 3000,
                'title' => 'Audio',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            29 =>
            array (
                'id' => 3010,
                'title' => 'MP3',
                'parentid' => 3000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            30 =>
            array (
                'id' => 3020,
                'title' => 'Video',
                'parentid' => 3000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            31 =>
            array (
                'id' => 3030,
                'title' => 'Audiobook',
                'parentid' => 3000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            32 =>
            array (
                'id' => 3040,
                'title' => 'Lossless',
                'parentid' => 3000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            33 =>
            array (
                'id' => 3060,
                'title' => 'Foreign',
                'parentid' => 3000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            34 =>
            array (
                'id' => 3999,
                'title' => 'Other',
                'parentid' => 3000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            35 =>
            array (
                'id' => 4000,
                'title' => 'PC',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            36 =>
            array (
                'id' => 4010,
                'title' => '0day',
                'parentid' => 4000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            37 =>
            array (
                'id' => 4020,
                'title' => 'ISO',
                'parentid' => 4000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            38 =>
            array (
                'id' => 4030,
                'title' => 'Mac',
                'parentid' => 4000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            39 =>
            array (
                'id' => 4050,
                'title' => 'Games',
                'parentid' => 4000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            40 =>
            array (
                'id' => 4060,
                'title' => 'Phone-IOS',
                'parentid' => 4000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            41 =>
            array (
                'id' => 4070,
                'title' => 'Phone-Android',
                'parentid' => 4000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            42 =>
            array (
                'id' => 4999,
                'title' => 'Phone-Other',
                'parentid' => 4000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            43 =>
            array (
                'id' => 5000,
                'title' => 'TV',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            44 =>
            array (
                'id' => 5010,
                'title' => 'WEB-DL',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            45 =>
            array (
                'id' => 5020,
                'title' => 'Foreign',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            46 =>
            array (
                'id' => 5030,
                'title' => 'SD',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            47 =>
            array (
                'id' => 5040,
                'title' => 'HD',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            48 =>
            array (
                'id' => 5045,
                'title' => 'UHD',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            49 =>
            array (
                'id' => 5060,
                'title' => 'Sport',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            50 =>
            array (
                'id' => 5070,
                'title' => 'Anime',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            51 =>
            array (
                'id' => 5080,
                'title' => 'Documentary',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            52 =>
            array (
                'id' => 5999,
                'title' => 'Other',
                'parentid' => 5000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            53 =>
            array (
                'id' => 6000,
                'title' => 'XXX',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            54 =>
            array (
                'id' => 6010,
                'title' => 'DVD',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            55 =>
            array (
                'id' => 6020,
                'title' => 'WMV',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            56 =>
            array (
                'id' => 6030,
                'title' => 'XviD',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            57 =>
            array (
                'id' => 6040,
                'title' => 'x264',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            58 =>
            array (
                'id' => 6041,
                'title' => 'HD Clips',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            59 =>
            array (
                'id' => 6042,
                'title' => 'SD Clips',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            60 =>
            array (
                'id' => 6045,
                'title' => 'UHD',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            61 =>
            array (
                'id' => 6060,
                'title' => 'Imageset',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            62 =>
            array (
                'id' => 6070,
                'title' => 'Packs',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            63 =>
            array (
                'id' => 6080,
                'title' => 'SD',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            64 =>
            array (
                'id' => 6090,
                'title' => 'WEBDL',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            65 =>
            array (
                'id' => 6999,
                'title' => 'Other',
                'parentid' => 6000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            66 =>
            array (
                'id' => 7000,
                'title' => 'Books',
                'parentid' => NULL,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            67 =>
            array (
                'id' => 7010,
                'title' => 'Ebook',
                'parentid' => 7000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            68 =>
            array (
                'id' => 7020,
                'title' => 'Comics',
                'parentid' => 7000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            69 =>
            array (
                'id' => 7030,
                'title' => 'Magazines',
                'parentid' => 7000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            70 =>
            array (
                'id' => 7040,
                'title' => 'Technical',
                'parentid' => 7000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            71 =>
            array (
                'id' => 7060,
                'title' => 'Foreign',
                'parentid' => 7000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
            72 =>
            array (
                'id' => 7999,
                'title' => 'Other',
                'parentid' => 7000,
                'status' => 1,
                'description' => NULL,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ),
        ));


    }
}