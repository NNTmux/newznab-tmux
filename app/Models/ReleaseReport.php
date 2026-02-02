<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseReport extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'release_reports';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'releases_id',
        'users_id',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Predefined report reasons
     */
    public const REASONS = [
        'duplicate' => 'Duplicate Release',
        'fake' => 'Fake/Malicious Content',
        'password' => 'Password Protected',
        'incomplete' => 'Incomplete/Corrupted',
        'wrong_category' => 'Wrong Category',
        'spam' => 'Spam/Advertisement',
        'other' => 'Other',
    ];

    /**
     * Convert a comma-separated list of reason keys to their labels.
     */
    public static function reasonKeysToLabels(?string $reasonKeys): string
    {
        if (empty($reasonKeys)) {
            return 'Unknown';
        }

        $keys = array_map('trim', explode(',', $reasonKeys));
        $labels = [];

        foreach ($keys as $key) {
            $labels[] = self::REASONS[$key] ?? ucfirst($key);
        }

        return implode(', ', array_unique($labels));
    }

    /**
     * Get the release that was reported.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * Get the user who reported the release.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * Get the admin who reviewed the report.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get a paginated list of reports with optional filters.
     */
    public static function getReportsRange(
        ?string $status = null,
        int $perPage = 50
    ): LengthAwarePaginator {
        $query = self::query()
            ->with(['release', 'user', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get report count by status.
     */
    public static function getCountByStatus(): array
    {
        $counts = self::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => $counts['pending'] ?? 0,
            'reviewed' => $counts['reviewed'] ?? 0,
            'resolved' => $counts['resolved'] ?? 0,
            'dismissed' => $counts['dismissed'] ?? 0,
            'total' => array_sum($counts),
        ];
    }

    /**
     * Check if a user has already reported a specific release.
     */
    public static function hasUserReported(int $releaseId, int $userId): bool
    {
        return self::query()
            ->where('releases_id', $releaseId)
            ->where('users_id', $userId)
            ->exists();
    }

    /**
     * Get the human-readable reason label.
     */
    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
