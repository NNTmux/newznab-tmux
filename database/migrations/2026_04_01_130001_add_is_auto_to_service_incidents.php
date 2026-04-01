<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_incidents', static function (Blueprint $table): void {
            $table->boolean('is_auto')->default(false)->after('created_by');
            $table->index('is_auto');
        });
    }

    public function down(): void
    {
        Schema::table('service_incidents', static function (Blueprint $table): void {
            $table->dropIndex(['is_auto']);
            $table->dropColumn('is_auto');
        });
    }
};
