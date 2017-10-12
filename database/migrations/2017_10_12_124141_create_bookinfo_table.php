<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBookinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('author');
            $table->string('asin', 128)->nullable()->default(null)->unique('ix_bookinfo_asin');
            $table->string('isbn', 128)->nullable()->default(null);
            $table->string('ean', 128)->nullable()->default(null);
            $table->string('url', 1000)->nullable()->default(null);
            $table->integer('salesrank')->unsigned()->nullable()->default(null);
            $table->string('publisher')->nullable()->default(null);
            $table->dateTime('publishdate')->nullable()->default(null);
            $table->string('pages', 128)->nullable()->default(null);
            $table->string('overview', 3000)->nullable()->default(null);
            $table->string('genre');
            $table->boolean('cover')->default(0);
            $table->timestamps();
            $table->index(['author', 'title'], 'ix_bookinfo_author_title_ft');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bookinfo');
    }
}
