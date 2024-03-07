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
        Schema::create('release_naming_regexes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('group_regex', 255)->default('')->index('ix_release_naming_regexes_group_regex')->comment('This is a regex to match against usenet groups');
            $table->string('regex', 5000)->default('')->comment('Regex used for extracting name from subject');
            $table->boolean('status')->default(1)->index('ix_release_naming_regexes_status')->comment('1=ON 0=OFF');
            $table->string('description', 1000)->default('')->comment('Optional extra details on this regex');
            $table->integer('ordinal')->default(0)->index('ix_release_naming_regexes_ordinal')->comment('Order to run the regex in');
        });

        DB::statement('ALTER TABLE release_naming_regexes AUTO_INCREMENT = 100000;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('release_naming_regexes');
    }
};
