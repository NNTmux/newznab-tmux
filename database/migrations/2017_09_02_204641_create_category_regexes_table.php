<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCategoryRegexesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_regexes', function (Blueprint $table) {
            $table->engine('innodb');
            $table->increments('id');
            $table->string('group_regex')->default('')->comment('This is a regex to match against usenet groups');
            $table->string('regex', '5000')->default('')->comment('Regex used to match a release name to categorize it');
            $table->unsignedTinyInteger('status')->default('1')->comment('1=ON 0=OFF');
            $table->string('description', '1000')->default('')->comment('Optional extra details on this regex');
            $table->integer('ordinal')->default('0')->comment('Order to run the regex in');
            $table->unsignedSmallInteger('categories_id')->default('0010')->comment('Which categories id to put the release in');
            $table->primary('id');
            $table->index('group_regex');
            $table->index('status');
            $table->index('ordinal');
            $table->index('categories_id');
        });

        DB::update('ALTER TABLE category_regexes AUTO_INCREMENT = 100000');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('category_regexes');
    }
}
