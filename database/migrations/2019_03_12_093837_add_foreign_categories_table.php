<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddForeignCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('parentid', 'root_categories_id');
            $table->foreign('root_categories_id', 'fk_root_categories_id')->references('id')->on('root_categories')->onUpdate('cascade')->onDelete('cascade');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('root_categories_id', 'parentid');
            $table->dropForeign('fk_root_categories_id');
        });
        Schema::enableForeignKeyConstraints();
    }
}
