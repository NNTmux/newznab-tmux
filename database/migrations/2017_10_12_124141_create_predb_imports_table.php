<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePredbImportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('predb_imports', function (Blueprint $table) {
            $table->string('title')->default('\'\'');
            $table->string('nfo')->nullable()->default(null);
            $table->string('size', 50)->nullable()->default(null);
            $table->string('category')->nullable()->default(null);
            $table->dateTime('predate')->nullable()->default(null);
            $table->string('source', 50)->default('\'\'');
            $table->integer('requestid')->unsigned()->default(0);
            $table->integer('groups_id')->unsigned()->default(0)->comment('FK to groups');
            $table->boolean('nuked')->default(0)->comment('Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked');
            $table->string('nukereason')->nullable()->default(null)->comment('If this pre is nuked, what is the reason?');
            $table->string('files', 50)->nullable()->default(null)->comment('How many files does this pre have ?');
            $table->string('filename')->default('\'\'');
            $table->boolean('searched')->default(0);
            $table->string('groupname')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('predb_imports');
    }
}
