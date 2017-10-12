<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRoleExcludedCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('role_excluded_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('role');
            $table->integer('categories_id')->nullable()->default(null);
            $table->timestamps();
            $table->unique(['role', 'categories_id'], 'ix_roleexcat_rolecat');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('role_excluded_categories');
    }
}
