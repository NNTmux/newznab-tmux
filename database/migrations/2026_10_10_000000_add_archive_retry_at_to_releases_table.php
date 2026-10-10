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
        if (! Schema::hasTable('releases') || Schema::hasColumn('releases', 'archive_retry_at')) {
            return;
        }

        // Appended without an index so MariaDB can add it instantly; the candidate
        // query only checks it on rows the claim queue index already narrowed down.
        Schema::table('releases', function (Blueprint $table): void {
            $table->timestamp('archive_retry_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('releases', 'archive_retry_at')) {
            Schema::table('releases', function (Blueprint $table): void {
                $table->dropColumn('archive_retry_at');
            });
        }
    }
};
