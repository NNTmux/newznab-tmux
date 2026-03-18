<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $countries = [];

        if (Schema::hasTable('countries')) {
            $countries = DB::table('countries')
                ->select(['iso_3166_2', 'name', 'full_name'])
                ->where('iso_3166_2', '!=', '')
                ->get()
                ->map(static fn (object $country): array => [
                    'iso_3166_2' => $country->iso_3166_2,
                    'name' => $country->name,
                    'full_name' => $country->full_name,
                ])
                ->all();

            Schema::drop('countries');
        }

        Schema::create('countries', function (Blueprint $table): void {
            $table->char('iso_3166_2', 2)->primary();
            $table->string('name');
            $table->string('full_name')->nullable();
            $table->index('name');
            $table->index('full_name');
        });

        if ($countries !== []) {
            DB::table('countries')->insert($countries);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
