<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RootCategoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('root_categories')->delete();

        DB::table('root_categories')->insert(
            [
                0 => [
                    'id' => 1,
                    'title' => 'Other',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
                1 => [
                    'id' => 1000,
                    'title' => 'Console',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
                2 => [
                    'id' => 2000,
                    'title' => 'Movies',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
                3 => [
                    'id' => 3000,
                    'title' => 'Audio',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
                4 => [
                    'id' => 4000,
                    'title' => 'PC',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
                5 => [
                    'id' => 5000,
                    'title' => 'TV',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
                6 => [
                    'id' => 6000,
                    'title' => 'XXX',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
                7 => [
                    'id' => 7000,
                    'title' => 'Books',
                    'status' => 1,
                    'disablepreview' => 0,
                ],
            ]
        );
    }
}
