<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_statuses', static function (Blueprint $table): void {
            $table->string('endpoint_url')->nullable()->after('slug');
        });

        $defaults = [
            'api' => '/api/v2/capabilities,/api/v1/api',
            'http' => '/up',
            'rss' => '/rss/full-feed',
        ];

        foreach ($defaults as $slug => $path) {
            DB::table('service_statuses')
                ->where('slug', $slug)
                ->update(['endpoint_url' => $path]);
        }
    }

    public function down(): void
    {
        Schema::table('service_statuses', static function (Blueprint $table): void {
            $table->dropColumn('endpoint_url');
        });
    }
};
