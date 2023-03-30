<?php

namespace Database\Seeders;

use Bhuvidya\Countries\CountriesSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run(): void
    {
        // $this->call(UsersTableSeeder::class);
        // \App\Models\User::factory(10)->create();
        $this->call(CountriesSeeder::class);
        $this->call(RootCategoriesTableSeeder::class);
        $this->call(CategoriesTableSeeder::class);
        $this->call(CategoryRegexesTableSeeder::class);
        $this->call(CollectionRegexesTableSeeder::class);
        $this->call(BinaryblacklistTableSeeder::class);
        $this->call(ContentTableSeeder::class);
        $this->call(GenresTableSeeder::class);
        $this->call(GroupsTableSeeder::class);
        $this->call(ReleaseNamingRegexesTableSeeder::class);
        $this->call(SettingsTableSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
