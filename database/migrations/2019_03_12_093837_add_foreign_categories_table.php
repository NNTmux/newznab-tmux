<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('root_categories_id', 'parentid');
            $table->dropForeign('fk_root_categories_id');
        });
        Schema::enableForeignKeyConstraints();
    }
};
