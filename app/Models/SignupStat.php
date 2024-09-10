<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignupStat extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function insertUsersByMonth(): void
    {
        $users = User::query()->whereNotNull('created_at')->where('created_at', '<>', '0000-00-00 00:00:00')->selectRaw("DATE_FORMAT(created_at, '%M %Y') as mth, COUNT(id) as num")->groupBy(['mth'])->orderByDesc('created_at')->get();
        foreach ($users as $user) {
            self::updateOrCreate(['month' => $user->mth], ['signups' => $user->num]);
        }
    }

    public static function getUsersByMonth(): array
    {
        return self::query()->select(['month', 'signups'])->get()->toArray();
    }
}
