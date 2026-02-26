<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignupStat extends Model
{
    use HasFactory; // @phpstan-ignore missingType.generics

    protected $guarded = [];

    public static function insertUsersByMonth(): void
    {
        $users = User::query()
            ->whereNotNull('created_at')
            ->where('created_at', '<>', '0000-00-00 00:00:00')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-01') as sort_date, DATE_FORMAT(created_at, '%M %Y') as mth, COUNT(id) as num")
            ->groupBy(['sort_date', 'mth'])
            ->orderByDesc('sort_date')
            ->get();

        foreach ($users as $user) {
            self::updateOrCreate(
                ['month' => $user->mth],
                ['signups' => $user->num, 'sort_date' => $user->sort_date]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getUsersByMonth(): array
    {
        return self::query()
            ->select(['month', 'signups'])
            ->orderByDesc('sort_date')
            ->get()
            ->toArray();
    }
}
