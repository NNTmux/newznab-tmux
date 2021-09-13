<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Invitation.
 *
 * @property int $id
 * @property string $guid
 * @property int $users_id
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation whereGuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation whereUsersId($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation query()
 */
class Invitation extends Model
{
    public const DEFAULT_INVITES = 1;
    public const DEFAULT_INVITE_EXPIRY_DAYS = 7;

    /**
     * @var bool
     */
    public $timestamps = true;

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
     * @param  int  $uid
     * @param  string  $inviteToken
     */
    public static function addInvite(int $uid, string $inviteToken)
    {
        self::create(['guid' => $inviteToken, 'users_id' => $uid]);
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
        self::query()->where('created_at', '<', now()->subDays(self::DEFAULT_INVITE_EXPIRY_DAYS));

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
