<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('forum_categories', function (Blueprint $table) {
            $table->nestedSet();
            $table->dropColumn(['category_id', 'weight']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('forum_categories', function (Blueprint $table) {
            $table->dropNestedSet();
            $table->integer('category_id')->unsigned();
            $table->integer('weight');
        });
    }
};
