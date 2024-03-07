<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('release_unique', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id.');
            $table->string('uniqueid')->comment('Unique_ID from mediainfo.');
            $table->primary(['releases_id', 'uniqueid'], 'ix_releases_id_uniqueid');
            $table->foreign('releases_id', 'FK_ru_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('release_unique');
    }
};
