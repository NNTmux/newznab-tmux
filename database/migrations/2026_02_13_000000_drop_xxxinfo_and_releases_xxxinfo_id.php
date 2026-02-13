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
        Schema::dropIfExists('xxxinfo');

        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('ix_releases_xxxinfo_id');
            $table->dropColumn('xxxinfo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->integer('xxxinfo_id')->default(0)->after('imdbid');
            $table->index('xxxinfo_id', 'ix_releases_xxxinfo_id');
        });

        Schema::create('xxxinfo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 1024);
            $table->string('tagline', 1024);
            $table->binary('plot')->nullable();
            $table->string('genre', 255);
            $table->string('director', 255)->nullable();
            $table->string('actors', 2500);
            $table->text('extras')->nullable();
            $table->text('productinfo')->nullable();
            $table->text('trailers')->nullable();
            $table->string('directurl', 2000);
            $table->string('classused', 20)->default('');
            $table->boolean('cover')->default(false);
            $table->boolean('backdrop')->default(false);
            $table->timestamps();
            $table->unique('title', 'ix_xxxinfo_title');
        });
    }
};
