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
        Schema::create('binaryblacklist', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('groupname')->nullable()->index('ix_binaryblacklist_groupname');
            $table->string('regex', 2000);
            $table->integer('msgcol')->unsigned()->default(1);
            $table->integer('optype')->unsigned()->default(1);
            $table->integer('status')->unsigned()->default(1)->index('ix_binaryblacklist_status');
            $table->string('description', 1000)->nullable();
            $table->date('last_activity')->nullable();
        });

        DB::statement('ALTER TABLE binaryblacklist AUTO_INCREMENT = 100000;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('binaryblacklist');
    }
};
