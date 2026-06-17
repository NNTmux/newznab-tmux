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
        if (! $this->hasRequiredTablesAndColumns()) {
            return;
        }

        DB::table('service_incidents')
            ->where('service_incidents.is_auto', true)
            ->where('service_incidents.title', '[Auto] RSS — Unexpected response (HTTP 400)')
            ->whereExists(static function ($query): void {
                $query->select(DB::raw(1))
                    ->from('service_incident_service_status')
                    ->join('service_statuses', 'service_statuses.id', '=', 'service_incident_service_status.service_status_id')
                    ->whereColumn('service_incident_service_status.service_incident_id', 'service_incidents.id')
                    ->where('service_statuses.slug', 'rss');
            })
            ->update([
                'status' => 'resolved',
                'impact' => 'none',
                'resolved_at' => DB::raw('COALESCE(resolved_at, started_at)'),
                'updated_at' => now(),
            ]);

        $this->forgetSiteStatusCaches();
    }

    public function down(): void
    {
        // Intentionally not reversible: these rows are auto-created false positives
        // from probing an authenticated RSS feed without an API token.
    }

    private function hasRequiredTablesAndColumns(): bool
    {
        return Schema::hasTable('service_statuses')
            && Schema::hasTable('service_incidents')
            && Schema::hasTable('service_incident_service_status')
            && Schema::hasColumn('service_incidents', 'is_auto');
    }

    private function forgetSiteStatusCaches(): void
    {
        Cache::forget('admin:site-status:enabled-services');
        Cache::forget('admin:site-status:active-incidents');
        Cache::forget('admin:dashboard:snapshot');
    }
};
