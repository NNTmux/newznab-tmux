<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryRegexesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     */
    public function run(): void
    {
        DB::table('category_regexes')->delete();

        DB::table('category_regexes')->insert([
            0 => [
                'id' => 1,
                'group_regex' => '^alt\\.binaries\\.sony\\.psvita$',
                'regex' => '/.*/ ',
                'status' => 1,
                'description' => '',
                'ordinal' => 50,
                'categories_id' => 1120,
            ],
        ]);
    }
}
