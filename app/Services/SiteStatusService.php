<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncidentImpactEnum;
use App\Enums\IncidentStatusEnum;
use App\Enums\ServiceStatusEnum;
use App\Mail\IncidentDetected;
use App\Models\ServiceIncident;
use App\Models\ServiceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SiteStatusService
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ServiceStatus>
     */
    public function getAllStatuses(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getEnabledServices();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ServiceStatus>
     */
    public function getEnabledServices(): \Illuminate\Database\Eloquent\Collection
    {
        return ServiceStatus::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ServiceStatus>
     */
    public function getAllServicesForAdmin(): \Illuminate\Database\Eloquent\Collection
    {
        return ServiceStatus::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getOverallStatus(): ServiceStatusEnum
    {
        $services = $this->getEnabledServices();

        if ($services->isEmpty()) {
            return ServiceStatusEnum::Operational;
        }

        $worst = ServiceStatusEnum::Operational;

        foreach ($services as $service) {
            $current = $service->status;
            if ($current->severity() > $worst->severity()) {
                $worst = $current;
            }
        }

        return $worst;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ServiceIncident>
     */
    public function getActiveIncidents(): \Illuminate\Database\Eloquent\Collection
    {
        return ServiceIncident::query()
            ->with('services')
            ->where('status', '!=', IncidentStatusEnum::Resolved->value)
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ServiceIncident>
     */
    public function getRecentResolvedIncidents(int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        $since = Carbon::now()->subDays($days);

        return ServiceIncident::query()
            ->with('services')
            ->where('status', IncidentStatusEnum::Resolved->value)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->orderByDesc('resolved_at')
            ->get();
    }

    /**
     * Recalculate uptime percentage for a service over a rolling window.
     *
     * Only Major and Critical incidents count as downtime. The downtime
     * for each incident is clamped to the window boundaries and
     * overlapping intervals are merged so they're not double-counted.
     */
    public function recalculateUptime(ServiceStatus $service, int $days = 30): float
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays($days);
        $totalMinutes = (float) $windowStart->diffInMinutes($now);

        if ($totalMinutes <= 0) {
            return 100.0;
        }

        $incidents = ServiceIncident::query()
            ->whereHas('services', static function ($query) use ($service): void {
                $query->where('service_statuses.id', $service->id);
            })
            ->whereIn('impact', [IncidentImpactEnum::Major->value, IncidentImpactEnum::Critical->value])
            ->where(static function ($query) use ($windowStart): void {
                $query->whereNull('resolved_at')
                    ->orWhere('resolved_at', '>=', $windowStart);
            })
            ->where('started_at', '<=', $now)
            ->orderBy('started_at')
            ->get();

        if ($incidents->isEmpty()) {
            return 100.0;
        }

        $intervals = [];
        foreach ($incidents as $incident) {
            $start = $incident->started_at->max($windowStart);
            $end = $incident->resolved_at?->min($now) ?? $now;

            if ($start->lt($end)) {
                $intervals[] = [$start->getTimestamp(), $end->getTimestamp()];
            }
        }

        $merged = $this->mergeIntervals($intervals);

        $downtimeMinutes = 0.0;
        foreach ($merged as [$s, $e]) {
            $downtimeMinutes += ($e - $s) / 60.0;
        }

        $uptime = (($totalMinutes - $downtimeMinutes) / $totalMinutes) * 100.0;

        return round(max(0.0, min(100.0, $uptime)), 2);
    }

    /**
     * Run the periodic uptime check for all enabled services:
     * recalculate uptime percentage, recompute health, and stamp last_checked_at.
     */
    public function refreshAllServiceUptime(int $days = 30): void
    {
        $services = $this->getEnabledServices();

        foreach ($services as $service) {
            $uptime = $this->recalculateUptime($service, $days);

            $service->update([
                'uptime_percentage' => $uptime,
                'last_checked_at' => Carbon::now(),
            ]);

            $this->recomputeServiceHealth($service);
        }
    }

    /**
     * Merge overlapping/adjacent timestamp intervals so downtime isn't double-counted.
     *
     * @param  list<array{0: int, 1: int}>  $intervals  [[start, end], ...]
     * @return list<array{0: int, 1: int}>
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [$intervals[0]];

        for ($i = 1, $count = \count($intervals); $i < $count; $i++) {
            $last = &$merged[\count($merged) - 1];
            if ($intervals[$i][0] <= $last[1]) {
                $last[1] = max($last[1], $intervals[$i][1]);
            } else {
                $merged[] = $intervals[$i];
            }
        }

        return $merged;
    }

    /**
     * Placeholder for per-day uptime history when a dedicated metrics table exists.
     *
     * @return Collection<int, array{date: string, uptime: float}>
     */
    public function getUptimeHistory(ServiceStatus $service, int $days = 90): Collection
    {
        return collect();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createIncident(array $data): ServiceIncident
    {
        $serviceIds = [];
        if (isset($data['service_status_ids']) && is_array($data['service_status_ids'])) {
            $serviceIds = array_values(array_unique(array_map(static fn (mixed $v): int => (int) $v, $data['service_status_ids'])));
        }
        unset($data['service_status_ids']);

        $incident = ServiceIncident::query()->create($data);

        if ($serviceIds !== []) {
            $incident->services()->sync($serviceIds);
        }

        foreach ($incident->services as $service) {
            $this->recomputeServiceHealth($service);
        }

        return $incident->fresh(['services']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIncident(ServiceIncident $incident, array $data): ServiceIncident
    {
        $serviceIds = null;
        if (array_key_exists('service_status_ids', $data)) {
            $raw = $data['service_status_ids'];
            $serviceIds = is_array($raw)
                ? array_values(array_unique(array_map(static fn (mixed $v): int => (int) $v, $raw)))
                : [];
        }
        unset($data['service_status_ids']);

        $oldIds = $incident->services()->pluck('service_statuses.id')->all();

        $incident->update($data);

        if ($serviceIds !== null) {
            $incident->services()->sync($serviceIds);
        }

        $incident->load('services');
        $newIds = $incident->services->pluck('id')->all();
        $affectedIds = array_unique(array_merge($oldIds, $newIds));

        foreach (ServiceStatus::query()->whereIn('id', $affectedIds)->get() as $service) {
            $this->recomputeServiceHealth($service);
        }

        return $incident->fresh(['services']);
    }

    public function resolveIncident(ServiceIncident $incident): ServiceIncident
    {
        $services = $incident->services()->get();

        $incident->update([
            'status' => IncidentStatusEnum::Resolved,
            'resolved_at' => $incident->resolved_at ?? Carbon::now(),
        ]);

        foreach ($services as $service) {
            $this->recomputeServiceHealth($service);
        }

        return $incident->fresh(['services']);
    }

    public function updateServiceStatus(ServiceStatus $service, ServiceStatusEnum $status): void
    {
        $service->update([
            'status' => $status,
            'last_checked_at' => Carbon::now(),
        ]);
    }

    public function recomputeServiceHealth(ServiceStatus $service): void
    {
        $active = ServiceIncident::query()
            ->whereHas('services', static function ($query) use ($service): void {
                $query->where('service_statuses.id', $service->id);
            })
            ->where('status', '!=', IncidentStatusEnum::Resolved->value)
            ->get();

        if ($active->isEmpty()) {
            $service->update(['status' => ServiceStatusEnum::Operational]);

            return;
        }

        $worst = ServiceStatusEnum::Operational;
        foreach ($active as $incident) {
            $mapped = $this->mapIncidentImpactToServiceHealth($incident->impact);
            if ($mapped->severity() > $worst->severity()) {
                $worst = $mapped;
            }
        }

        $service->update(['status' => $worst]);
    }

    private const int SLOW_THRESHOLD_MS = 5000;

    private const string AUTO_INCIDENT_PREFIX = '[Auto]';

    /**
     * Ping a service endpoint and return health check results.
     *
     * @return array{ok: bool, status_code: int, response_time_ms: int, impact: IncidentImpactEnum|null, reason: string}
     */
    public function checkServiceHealth(ServiceStatus $service): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($baseUrl === '' || ! str_starts_with($baseUrl, 'http')) {
            return ['ok' => true, 'status_code' => 0, 'response_time_ms' => 0, 'impact' => null, 'reason' => 'APP_URL not configured'];
        }

        $raw = $service->endpoint_url;

        if ($raw === null || $raw === '') {
            return ['ok' => true, 'status_code' => 0, 'response_time_ms' => 0, 'impact' => null, 'reason' => 'No endpoint configured'];
        }

        $paths = array_filter(array_map('trim', explode(',', $raw)));
        $isRss = $service->slug === 'rss';
        $worstResult = null;

        foreach ($paths as $path) {
            $result = $this->pingEndpoint($baseUrl.$path, $isRss);

            if ($result['ok']) {
                return $result;
            }

            if ($worstResult === null || ($result['impact']?->value ?? '') > ($worstResult['impact']?->value ?? '')) {
                $worstResult = $result;
            }
        }

        return $worstResult ?? ['ok' => false, 'status_code' => 0, 'response_time_ms' => 0, 'impact' => IncidentImpactEnum::Critical, 'reason' => 'All endpoints unreachable'];
    }

    /**
     * @return array{ok: bool, status_code: int, response_time_ms: int, impact: IncidentImpactEnum|null, reason: string}
     */
    private function pingEndpoint(string $url, bool $treat403AsHealthy): array
    {
        try {
            $start = hrtime(true);
            $response = Http::timeout(15)->connectTimeout(10)->get($url);
            $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

            $code = $response->status();

            if ($response->successful() || ($treat403AsHealthy && $code === 403)) {
                if ($elapsed > self::SLOW_THRESHOLD_MS) {
                    return [
                        'ok' => false,
                        'status_code' => $code,
                        'response_time_ms' => $elapsed,
                        'impact' => IncidentImpactEnum::Minor,
                        'reason' => "Slow response ({$elapsed}ms)",
                    ];
                }

                return ['ok' => true, 'status_code' => $code, 'response_time_ms' => $elapsed, 'impact' => null, 'reason' => 'OK'];
            }

            if ($response->serverError()) {
                return [
                    'ok' => false,
                    'status_code' => $code,
                    'response_time_ms' => $elapsed,
                    'impact' => IncidentImpactEnum::Critical,
                    'reason' => "Server error (HTTP {$code})",
                ];
            }

            return [
                'ok' => false,
                'status_code' => $code,
                'response_time_ms' => $elapsed,
                'impact' => IncidentImpactEnum::Major,
                'reason' => "Unexpected response (HTTP {$code})",
            ];
        } catch (\Throwable $e) {
            Log::warning("Health check failed for {$url}: {$e->getMessage()}");

            return [
                'ok' => false,
                'status_code' => 0,
                'response_time_ms' => 0,
                'impact' => IncidentImpactEnum::Critical,
                'reason' => 'Connection failed: '.\Str::limit($e->getMessage(), 120),
            ];
        }
    }

    /**
     * Find the currently open auto-created incident for a service (if any).
     */
    public function getOpenAutoIncident(ServiceStatus $service): ?ServiceIncident
    {
        return ServiceIncident::query()
            ->where('is_auto', true)
            ->where('status', '!=', IncidentStatusEnum::Resolved->value)
            ->whereHas('services', static function ($query) use ($service): void {
                $query->where('service_statuses.id', $service->id);
            })
            ->latest('started_at')
            ->first();
    }

    /**
     * Process a health check result: create an auto-incident on failure, or auto-resolve on recovery.
     *
     * @param  array{ok: bool, status_code: int, response_time_ms: int, impact: IncidentImpactEnum|null, reason: string}  $result
     */
    public function handleHealthCheckResult(ServiceStatus $service, array $result): void
    {
        $service->update([
            'response_time_ms' => $result['response_time_ms'] ?: null,
        ]);

        $existing = $this->getOpenAutoIncident($service);

        if ($result['ok']) {
            if ($existing !== null) {
                $this->resolveIncident($existing);
                $this->sendIncidentEmail($existing->fresh(['services']), resolved: true);
            }

            return;
        }

        if ($existing !== null) {
            return;
        }

        $impact = $result['impact'] ?? IncidentImpactEnum::Major;

        $incident = ServiceIncident::query()->create([
            'title' => self::AUTO_INCIDENT_PREFIX.' '.$service->name.' — '.$result['reason'],
            'description' => "Automated health check detected an issue with {$service->name}.\n\nEndpoint: {$service->endpoint_url}\nHTTP status: {$result['status_code']}\nResponse time: {$result['response_time_ms']}ms\nReason: {$result['reason']}",
            'status' => IncidentStatusEnum::Investigating,
            'impact' => $impact,
            'started_at' => Carbon::now(),
            'is_auto' => true,
        ]);

        $incident->services()->attach($service->id);
        $incident->load('services');

        $this->recomputeServiceHealth($service);
        $this->sendIncidentEmail($incident, resolved: false);
    }

    private function sendIncidentEmail(ServiceIncident $incident, bool $resolved): void
    {
        $adminEmail = (string) config('nntmux.admin_email');

        if ($adminEmail === '' || $adminEmail === 'admin@example.com') {
            return;
        }

        try {
            Mail::to($adminEmail)->send(new IncidentDetected($incident, $resolved));
        } catch (\Throwable $e) {
            Log::warning("Failed to send incident email: {$e->getMessage()}");
        }
    }

    private function mapIncidentImpactToServiceHealth(IncidentImpactEnum $impact): ServiceStatusEnum
    {
        return match ($impact) {
            IncidentImpactEnum::None => ServiceStatusEnum::Degraded,
            IncidentImpactEnum::Minor => ServiceStatusEnum::Degraded,
            IncidentImpactEnum::Major => ServiceStatusEnum::PartialOutage,
            IncidentImpactEnum::Critical => ServiceStatusEnum::MajorOutage,
        };
    }
}
