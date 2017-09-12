<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('id', 2)->comment('2 character country code');
            $table->char('iso3', 3)->comment('3 character country code');
            $table->string('country', 180)->coment('Name of the country');
            $table->index('iso3', 'ix_code3')->unique();
            $table->index('country', 'ix_country')->unique();
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
}
