<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_statuses') || ! Schema::hasColumn('service_statuses', 'endpoint_url')) {
            return;
        }

        DB::table('service_statuses')
            ->where('slug', 'rss')
            ->where('endpoint_url', '/rss/full-feed')
            ->update(['endpoint_url' => '/rss/health']);

        $this->forgetSiteStatusCaches();
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_statuses') || ! Schema::hasColumn('service_statuses', 'endpoint_url')) {
            return;
        }

        DB::table('service_statuses')
            ->where('slug', 'rss')
            ->where('endpoint_url', '/rss/health')
            ->update(['endpoint_url' => '/rss/full-feed']);

        $this->forgetSiteStatusCaches();
    }

    private function forgetSiteStatusCaches(): void
    {
        Cache::forget('admin:site-status:enabled-services');
        Cache::forget('admin:site-status:active-incidents');
        Cache::forget('admin:dashboard:snapshot');
    }
};
