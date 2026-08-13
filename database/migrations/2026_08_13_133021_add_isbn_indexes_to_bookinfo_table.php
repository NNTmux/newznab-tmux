<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ISBN_INDEX = 'ix_bookinfo_isbn';

    private const EAN_INDEX = 'ix_bookinfo_ean';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('bookinfo')) {
            return;
        }

        Schema::table('bookinfo', static function (Blueprint $table): void {
            if (! Schema::hasIndex('bookinfo', self::ISBN_INDEX)) {
                $table->index('isbn', self::ISBN_INDEX);
            }
            if (! Schema::hasIndex('bookinfo', self::EAN_INDEX)) {
                $table->index('ean', self::EAN_INDEX);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('bookinfo')) {
            return;
        }

        Schema::table('bookinfo', static function (Blueprint $table): void {
            if (Schema::hasIndex('bookinfo', self::ISBN_INDEX)) {
                $table->dropIndex(self::ISBN_INDEX);
            }
            if (Schema::hasIndex('bookinfo', self::EAN_INDEX)) {
                $table->dropIndex(self::EAN_INDEX);
            }
        });
    }
};
