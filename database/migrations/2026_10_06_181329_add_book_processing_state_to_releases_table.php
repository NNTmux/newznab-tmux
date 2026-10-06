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
        Schema::table('releases', function (Blueprint $table) {
            $table->unsignedTinyInteger('book_lookup_attempts')->default(0);
            $table->dateTime('book_lookup_retry_at')->nullable();
            $table->dateTime('book_name_normalized_at')->nullable();
            $table->index(['bookinfo_id', 'book_lookup_retry_at', 'categories_id'], 'ix_releases_book_retry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('ix_releases_book_retry');
            $table->dropColumn(['book_lookup_attempts', 'book_lookup_retry_at', 'book_name_normalized_at']);
        });
    }
};
