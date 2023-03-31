<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePredbHashesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('predb_hashes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('predb_id')->unsigned()->default(0)->comment('id, of the predb entry, this hash belongs to');
        });

        DB::statement('ALTER TABLE predb_hashes ADD COLUMN hash VARBINARY(40) DEFAULT ""');
        DB::statement('ALTER TABLE predb_hashes ADD PRIMARY KEY (hash)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('predb_hashes');
    }
}
