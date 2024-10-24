<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleStat extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function insertUsersByRole(): void
    {
        $roles = Role::query()->select(['name'])->withCount('users')->groupBy('name')->having('users_count', '>', 0)->orderByDesc('users_count')->get();
        foreach ($roles as $role) {
            // Check if we already have the information and if we do just update the count
            if (self::query()->where('role', $role->name)->exists()) {
                self::query()->where('role', $role->name)->update(['users' => $role->users_count]);

                continue;
            }
            self::query()->create(['role' => $role->name, 'users' => $role->users_count]);
        }
    }

    public static function getUsersByRole(): array
    {
        return self::query()->select(['role', 'users'])->get()->toArray();
    }
}
