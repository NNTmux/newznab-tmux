<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Invitation extends Model
{
    public const DEFAULT_INVITES = 1;
    public const DEFAULT_INVITE_EXPIRY_DAYS = 7;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @param int $uid
     * @param string $inviteToken
     */
    public static function addInvite(int $uid, string $inviteToken)
    {
        self::query()->insertGetId(['guid' => $inviteToken, 'users_id' => $uid, 'created_at' => Carbon::now()]);
    }

    /**
     * @param $inviteToken
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getInvite($inviteToken)
    {
        //
        // Tidy any old invites sent greater than DEFAULT_INVITE_EXPIRY_DAYS days ago.
        //
        self::query()->where('created_at', '<', Carbon::now()->subDays(self::DEFAULT_INVITE_EXPIRY_DAYS));

        return self::query()->where('guid', $inviteToken)->first();
    }

    /**
     * @param $inviteToken
     */
    public static function deleteInvite(string $inviteToken): void
    {
        self::query()->where('guid', $inviteToken)->delete();
    }
}
