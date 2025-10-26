<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_type',
        'value',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'float',
        'recorded_at' => 'datetime',
    ];

    /**
     * Scope to get CPU metrics
     */
    public function scopeCpu($query)
    {
        return $query->where('metric_type', 'cpu');
    }

    /**
     * Scope to get RAM metrics
     */
    public function scopeRam($query)
    {
        return $query->where('metric_type', 'ram');
    }

    /**
     * Scope to get metrics for a specific time period
     */
    public function scopeForPeriod($query, int $hours)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to get metrics for a specific date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Clean up old metrics (older than specified days)
     */
    public static function cleanupOldMetrics(int $days = 60): int
    {
        return static::where('recorded_at', '<', now()->subDays($days))->delete();
    }
}
