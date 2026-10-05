<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsSingletonConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $tmux_running
 * @property bool $stop_requested
 * @property CarbonImmutable|null $last_binary_run_at
 * @property string|null $monitor_path
 * @property string|null $monitor_path_a
 * @property string|null $monitor_path_b
 */
final class ProcessingRuntimeState extends Model
{
    use IsSingletonConfiguration;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tmux_running' => 'boolean',
            'stop_requested' => 'boolean',
            'last_binary_run_at' => 'immutable_datetime',
        ];
    }
}
