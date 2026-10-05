<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConfigurationDomain;
use App\Enums\RegistrationStatus;
use App\Models\RegistrationPeriod;
use App\Models\RegistrationStatusHistory;
use App\Models\User;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Configuration\DomainConfigurationRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RegistrationStatusService
{
    public function __construct(
        private readonly ConfigurationProvider $configuration,
        private readonly DomainConfigurationRepository $repository,
    ) {}

    /**
     * @return array<int, string>
     */
    public function statusOptions(): array
    {
        return [
            RegistrationStatus::Open->value => RegistrationStatus::Open->label(),
            RegistrationStatus::Invite->value => RegistrationStatus::Invite->label(),
            RegistrationStatus::Closed->value => RegistrationStatus::Closed->label(),
        ];
    }

    public function statusLabel(int $status): string
    {
        return $this->statusOptions()[$status] ?? 'Unknown';
    }

    public function getManualStatus(): int
    {
        $manualStatus = $this->configuration->registration()->status->value;

        if (! array_key_exists($manualStatus, $this->statusOptions())) {
            return RegistrationStatus::Open->value;
        }

        return $manualStatus;
    }

    public function getActivePeriod(?CarbonInterface $at = null): ?RegistrationPeriod
    {
        $at ??= now();

        return RegistrationPeriod::query()
            ->activeAt($at)
            ->orderBy('starts_at')
            ->first();
    }

    public function getNextUpcomingPeriod(?CarbonInterface $at = null): ?RegistrationPeriod
    {
        $at ??= now();

        return RegistrationPeriod::query()
            ->enabled()
            ->upcoming($at)
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * @return array{
     *     manual_status: int,
     *     manual_status_label: string,
     *     effective_status: int,
     *     effective_status_label: string,
     *     active_period: RegistrationPeriod|null,
     *     scheduled_override_active: bool,
     *     reason: string,
     *     message: string,
     *     available: bool,
     *     is_open: bool,
     *     is_invite_only: bool,
     *     is_closed: bool
     * }
     */
    public function resolve(?CarbonInterface $at = null): array
    {
        $at ??= now();

        $manualStatus = $this->getManualStatus();
        $activePeriod = $this->getActivePeriod($at);
        $scheduledOverrideActive = $activePeriod !== null && $manualStatus !== RegistrationStatus::Open->value;
        $effectiveStatus = $activePeriod !== null ? RegistrationStatus::Open->value : $manualStatus;
        $reason = match (true) {
            $scheduledOverrideActive => 'scheduled_open_period',
            $effectiveStatus === RegistrationStatus::Open->value => 'manual_open',
            $effectiveStatus === RegistrationStatus::Invite->value => 'manual_invite',
            default => 'manual_closed',
        };

        return [
            'manual_status' => $manualStatus,
            'manual_status_label' => $this->statusLabel($manualStatus),
            'effective_status' => $effectiveStatus,
            'effective_status_label' => $this->statusLabel($effectiveStatus),
            'active_period' => $activePeriod,
            'scheduled_override_active' => $scheduledOverrideActive,
            'reason' => $reason,
            'message' => $this->buildMessage($effectiveStatus, $activePeriod, $scheduledOverrideActive),
            'available' => $effectiveStatus !== RegistrationStatus::Closed->value,
            'is_open' => $effectiveStatus === RegistrationStatus::Open->value,
            'is_invite_only' => $effectiveStatus === RegistrationStatus::Invite->value,
            'is_closed' => $effectiveStatus === RegistrationStatus::Closed->value,
        ];
    }

    public function updateManualStatus(int $newStatus, ?User $changedBy = null, ?string $note = null): void
    {
        $oldStatus = $this->getManualStatus();

        $this->repository->update(ConfigurationDomain::Registration, ['status' => $newStatus]);

        $this->forgetDashboardSnapshot();

        if ($oldStatus === $newStatus && blank($note)) {
            return;
        }

        $description = $oldStatus === $newStatus
            ? sprintf('Manual registration status note added while status remained %s.', $this->statusLabel($newStatus))
            : sprintf(
                'Manual registration status changed from %s to %s.',
                $this->statusLabel($oldStatus),
                $this->statusLabel($newStatus)
            );

        RegistrationStatusHistory::record(
            RegistrationStatusHistory::ACTION_MANUAL_STATUS_CHANGED,
            $description,
            $changedBy?->id,
            $oldStatus,
            $newStatus,
            null,
            [
                'note' => $note,
            ]
        );
    }

    /**
     * @param  array{name: string, starts_at: mixed, ends_at: mixed, is_enabled: bool, notes?: string|null}  $data
     */
    public function createPeriod(array $data, ?User $changedBy = null): RegistrationPeriod
    {
        $period = RegistrationPeriod::query()->create([
            'name' => $data['name'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_enabled' => $data['is_enabled'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $changedBy?->id,
            'updated_by' => $changedBy?->id,
        ]);

        RegistrationStatusHistory::record(
            RegistrationStatusHistory::ACTION_PERIOD_CREATED,
            sprintf('Scheduled open period "%s" created.', $period->name),
            $changedBy?->id,
            null,
            null,
            $period->id,
            [
                'period' => $this->serializePeriod($period),
            ]
        );

        $this->forgetDashboardSnapshot();

        return $period;
    }

    /**
     * @param  array{name: string, starts_at: mixed, ends_at: mixed, is_enabled: bool, notes?: string|null}  $data
     */
    public function updatePeriod(RegistrationPeriod $period, array $data, ?User $changedBy = null): RegistrationPeriod
    {
        $before = $this->serializePeriod($period);

        $period->fill([
            'name' => $data['name'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_enabled' => $data['is_enabled'],
            'notes' => $data['notes'] ?? null,
            'updated_by' => $changedBy?->id,
        ]);
        $period->save();

        RegistrationStatusHistory::record(
            RegistrationStatusHistory::ACTION_PERIOD_UPDATED,
            sprintf('Scheduled open period "%s" updated.', $period->name),
            $changedBy?->id,
            null,
            null,
            $period->id,
            [
                'before' => $before,
                'after' => $this->serializePeriod($period),
            ]
        );

        $this->forgetDashboardSnapshot();

        return $period;
    }

    public function togglePeriod(RegistrationPeriod $period, ?User $changedBy = null, ?string $note = null): RegistrationPeriod
    {
        $period->forceFill([
            'is_enabled' => ! $period->is_enabled,
            'updated_by' => $changedBy?->id,
        ])->save();

        RegistrationStatusHistory::record(
            RegistrationStatusHistory::ACTION_PERIOD_TOGGLED,
            sprintf(
                'Scheduled open period "%s" %s.',
                $period->name,
                $period->is_enabled ? 'enabled' : 'disabled'
            ),
            $changedBy?->id,
            null,
            null,
            $period->id,
            [
                'note' => $note,
                'period' => $this->serializePeriod($period),
            ]
        );

        $this->forgetDashboardSnapshot();

        return $period;
    }

    /**
     * @return Collection<int, RegistrationPeriod>
     */
    public function disableExpiredPeriods(?CarbonInterface $at = null): Collection
    {
        $at ??= now();

        $expiredPeriods = RegistrationPeriod::query()
            ->where('is_enabled', true)
            ->where('ends_at', '<', $at)
            ->get();

        foreach ($expiredPeriods as $period) {
            $period->forceFill([
                'is_enabled' => false,
                'updated_by' => null,
            ])->save();

            RegistrationStatusHistory::record(
                RegistrationStatusHistory::ACTION_PERIOD_COMPLETED,
                sprintf('Scheduled open period "%s" completed after reaching its end time.', $period->name),
                null,
                null,
                null,
                $period->id,
                [
                    'completed_at' => $at->toDateTimeString(),
                    'period' => $this->serializePeriod($period),
                ]
            );
        }

        if ($expiredPeriods->isNotEmpty()) {
            $this->forgetDashboardSnapshot();
        }

        return $expiredPeriods;
    }

    public function deletePeriod(RegistrationPeriod $period, ?User $changedBy = null, ?string $note = null): void
    {
        $snapshot = $this->serializePeriod($period);
        $periodName = $period->name;

        $period->delete();

        RegistrationStatusHistory::record(
            RegistrationStatusHistory::ACTION_PERIOD_DELETED,
            sprintf('Scheduled open period "%s" deleted.', $periodName),
            $changedBy?->id,
            null,
            null,
            null,
            [
                'note' => $note,
                'period' => $snapshot,
            ]
        );

        $this->forgetDashboardSnapshot();
    }

    private function buildMessage(
        int $effectiveStatus,
        ?RegistrationPeriod $activePeriod,
        bool $scheduledOverrideActive
    ): string {
        if ($scheduledOverrideActive && $activePeriod !== null) {
            return sprintf(
                'Registrations are temporarily open until %s because of the scheduled period "%s".',
                $activePeriod->ends_at->format('Y-m-d H:i'),
                $activePeriod->name
            );
        }

        return match ($effectiveStatus) {
            RegistrationStatus::Open->value => 'Registrations are currently open.',
            RegistrationStatus::Invite->value => 'Registrations are currently invite only.',
            default => 'Registrations are currently closed.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePeriod(RegistrationPeriod $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'starts_at' => $period->starts_at?->toDateTimeString(),
            'ends_at' => $period->ends_at?->toDateTimeString(),
            'is_enabled' => $period->is_enabled,
            'notes' => $period->notes,
            'created_by' => $period->created_by,
            'updated_by' => $period->updated_by,
        ];
    }

    /**
     * Drop the cached admin dashboard snapshot so the per-minute warmer (or
     * the next admin request) rebuilds with fresh registration data. Avoids
     * an inline rebuild here to keep write-path latency low.
     */
    private function forgetDashboardSnapshot(): void
    {
        Cache::forget(AdminDashboardSnapshotService::CACHE_KEY);
        Cache::forget('api_registration_status');
    }
}
