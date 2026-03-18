<?php

namespace Tests\Feature;

use App\Models\Country;
use Database\Seeders\CountriesTableSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CountriesTableSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('countries');

        Schema::create('countries', function (Blueprint $table): void {
            $table->char('iso_3166_2', 2)->primary();
            $table->string('name');
            $table->string('full_name')->nullable();
            $table->index('name');
            $table->index('full_name');
        });
    }

    public function test_it_seeds_countries_using_iso_codes_as_the_primary_key(): void
    {
        $this->seed(CountriesTableSeeder::class);

        $country = Country::query()->find('US');

        $this->assertNotNull($country);
        $this->assertSame('United States', $country->name);
    }

    public function test_it_resolves_country_codes_from_country_names_and_full_names(): void
    {
        $this->seed(CountriesTableSeeder::class);

        $this->assertSame('DE', countryCode('Germany'));
        $this->assertSame('US', countryCode('United States of America'));
        $this->assertSame('', countryCode('Atlantis'));
    }
}
