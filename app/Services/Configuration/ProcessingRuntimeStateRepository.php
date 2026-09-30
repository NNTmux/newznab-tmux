<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Models\ProcessingRuntimeState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ProcessingRuntimeStateRepository
{
    public function isTmuxRunning(): bool
    {
        return $this->state()->tmux_running;
    }

    public function setTmuxRunning(bool $running): void
    {
        $this->update(['tmux_running' => $running]);
    }

    public function stopRequested(): bool
    {
        return $this->state()->stop_requested;
    }

    public function requestStop(bool $requested = true): void
    {
        $this->update(['stop_requested' => $requested]);
    }

    /** @return array{monitor_path: string|null, monitor_path_a: string|null, monitor_path_b: string|null} */
    public function monitorPaths(): array
    {
        $state = $this->state();

        return [
            'monitor_path' => $state->monitor_path,
            'monitor_path_a' => $state->monitor_path_a,
            'monitor_path_b' => $state->monitor_path_b,
        ];
    }

    /** @param  array{monitor_path?: string|null, monitor_path_a?: string|null, monitor_path_b?: string|null}  $paths */
    public function updateMonitorPaths(array $paths): void
    {
        $this->update($paths);
    }

    public function markBinaryRun(?CarbonImmutable $ranAt = null): void
    {
        $this->update(['last_binary_run_at' => $ranAt ?? now()->toImmutable()]);
    }

    public function state(): ProcessingRuntimeState
    {
        if (! Schema::hasTable('processing_runtime_states')) {
            return (new ProcessingRuntimeState)->forceFill([
                'id' => ProcessingRuntimeState::SINGLETON_ID,
                'tmux_running' => false,
                'stop_requested' => false,
                'last_binary_run_at' => null,
                'monitor_path' => null,
                'monitor_path_a' => null,
                'monitor_path_b' => null,
            ]);
        }

        return ProcessingRuntimeState::singleton();
    }

    /** @param  array<string, mixed>  $attributes */
    private function update(array $attributes): void
    {
        $this->ensureAvailable();
        $state = ProcessingRuntimeState::singleton();
        $state->fill($attributes);
        $state->save();
    }

    private function ensureAvailable(): void
    {
        if (! Schema::hasTable('processing_runtime_states')) {
            throw new RuntimeException('The processing runtime state table is not available. Run migrations before starting workers.');
        }
    }
}
