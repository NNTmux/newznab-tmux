<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCategoryRegexesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('category_regexes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('group_regex', 255)->default('')->index('ix_category_regexes_group_regex')->comment('This is a regex to match against usenet groups');
            $table->string('regex', 5000)->default('')->comment('Regex used to match a release name to categorize it');
            $table->boolean('status')->default(1)->index('ix_category_regexes_status')->comment('1=ON 0=OFF');
            $table->string('description', 1000)->default('')->comment('Optional extra details on this regex');
            $table->integer('ordinal')->default(0)->index('ix_category_regexes_ordinal')->comment('Order to run the regex in');
            $table->smallInteger('categories_id')->unsigned()->default(10)->index('ix_category_regexes_categories_id')->comment('Which categories id to put the release in');
        });

        DB::statement('ALTER TABLE category_regexes AUTO_INCREMENT = 100000;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::drop('category_regexes');
    }
}
