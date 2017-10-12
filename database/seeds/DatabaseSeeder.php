<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);
        $this->call(CategoriesTableSeeder::class);
        $this->call(CategoryRegexesTableSeeder::class);
        $this->call(CollectionRegexesTableSeeder::class);
        $this->call(BinaryblacklistTableSeeder::class);
        $this->call(ContentTableSeeder::class);
        $this->call(CountriesTableSeeder::class);
        $this->call(ForumpostTableSeeder::class);
        $this->call(GenresTableSeeder::class);
        $this->call(GroupsTableSeeder::class);
        $this->call(MenuTableSeeder::class);
        $this->call(ReleaseNamingRegexesTableSeeder::class);
        $this->call(SettingsTableSeeder::class);
        $this->call(TmuxTableSeeder::class);
        $this->call(UserRolesTableSeeder::class);
    }
}
