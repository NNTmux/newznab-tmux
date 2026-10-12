<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('release_verification_scratch', function (Blueprint $table): void {
            $table->unsignedBigInteger('reserved_bytes')->default(0);
        });
        Schema::create('release_verification_scratch_budget', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('reserved_bytes')->default(0);
            $table->boolean('initialized')->default(false);
        });
        DB::table('release_verification_scratch_budget')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('release_verification_scratch_budget');
        Schema::table('release_verification_scratch', function (Blueprint $table): void {
            $table->dropColumn('reserved_bytes');
        });
    }
};
