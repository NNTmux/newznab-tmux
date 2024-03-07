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
        Schema::create('missed_parts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->bigInteger('numberid')->unsigned();
            $table->integer('groups_id')->unsigned()->default(0)->comment('FK to groups.id');
            $table->boolean('attempts')->default(0)->index('ix_missed_parts_attempts');
            $table->unique(['numberid', 'groups_id'], 'ix_missed_parts_numberid_groupsid');
            $table->index(['groups_id', 'attempts'], 'ix_missed_parts_groupid_attempts');
            $table->index(['numberid', 'groups_id', 'attempts'], 'ix_missed_parts_numberid_groupsid_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('missed_parts');
    }
};
