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
        if (! Schema::hasTable('releases') || Schema::hasColumn('releases', 'tv_lookup_retry_at')) {
            return;
        }

        // Appended without an index so MariaDB can add them instantly; the TV candidate
        // queries only check them on rows ix_releases_tv_processing already narrowed down.
        Schema::table('releases', function (Blueprint $table): void {
            $table->unsignedTinyInteger('tv_lookup_attempts')->default(0);
            $table->dateTime('tv_lookup_retry_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('releases', 'tv_lookup_retry_at')) {
            Schema::table('releases', function (Blueprint $table): void {
                $table->dropColumn(['tv_lookup_attempts', 'tv_lookup_retry_at']);
            });
        }
    }
};
