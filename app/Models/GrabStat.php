<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrabStat extends Model
{
    use HasFactory; // @phpstan-ignore missingType.generics

    protected $guarded = [];

    public static function insertTopGrabbers(): void
    {
        $users = User::query()->selectRaw('id, username, SUM(grabs) as grabs')->groupBy('id', 'username')->having('grabs', '>', 0)->orderByDesc('grabs')->limit(10)->get();
        // Insert data into the grab_stats table
        foreach ($users as $user) {
            self::updateOrCreate(['username' => $user->username], ['grabs' => $user->grabs]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getTopGrabbers(): array
    {
        return self::query()->select(['username', 'grabs'])->orderByDesc('grabs')->limit(10)->get()->toArray();
    }
}
