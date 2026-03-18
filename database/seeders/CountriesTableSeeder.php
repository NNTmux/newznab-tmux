<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = array_map(
            static fn (array $country): array => [
                'iso_3166_2' => $country['iso_3166_2'],
                'name' => $country['name'],
                'full_name' => $country['full_name'] ?? null,
            ],
            require database_path('seeders/data/countries.php'),
        );

        DB::table('countries')->delete();

        foreach (array_chunk($countries, 100) as $chunk) {
            DB::table('countries')->insert($chunk);
        }
    }
}
