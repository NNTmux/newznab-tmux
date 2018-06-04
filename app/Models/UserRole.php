<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\UserRole.
 *
 * @property int $id
 * @property string $name
 * @property int $apirequests
 * @property int $downloadrequests
 * @property int $defaultinvites
 * @property bool $isdefault
 * @property bool $canpreview
 * @property bool $hideads
 * @property int $donation
 * @property int $addyears
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\RoleExcludedCategory[] $roleExcludedCategory
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereAddyears($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereApirequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereCanpreview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereDefaultinvites($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereDonation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereDownloadrequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereHideads($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereIsdefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UserRole whereName($value)
 * @mixin \Eloquent
 */
class UserRole extends Model
{
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
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(User::class, 'user_roles_id');
    }

    public function roleExcludedCategory()
    {
        return $this->hasMany(RoleExcludedCategory::class, 'user_roles_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getUsersByRole()
    {
        return self::query()->select(['name'])->withCount('users')->groupBy('name')->having('users_count', '>', 0)->orderBy('users_count', 'desc')->get();
    }

    /**
     * @return array
     */
    public static function getRoles(): array
    {
        return self::all()->toArray();
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getRoleById($id)
    {
        return self::query()->where('id', $id)->first();
    }

    /**
     * @param $request
     *
     * @return int
     */
    public static function addRole($request): int
    {
        return self::query()->insertGetId(
            [
                'name' => $request['name'],
                'apirequests' => $request['apirequests'],
                'downloadrequests' => $request['name'],
                'defaultinvites' => $request['defaultinvites'],
                'canpreview' => $request['canpreview'],
                'hideads' => $request['hideads'],
                'donation' => $request['donation'],
                'addyears' => $request['addyears'],
                'rate_limit' => $request['rate_limit'],
            ]
        );
    }

    /**
     * @param $request
     *
     * @return int
     */
    public static function updateRole($request): int
    {
        return self::query()->where('id', $request['id'])->update(
            [
                'name' => $request['name'],
                'apirequests' => $request['apirequests'],
                'isdefault' => $request['isdefault'],
                'downloadrequests' => $request['name'],
                'defaultinvites' => $request['defaultinvites'],
                'canpreview' => $request['canpreview'],
                'hideads' => $request['hideads'],
                'donation' => $request['donation'],
                'addyears' => $request['addyears'],
                'rate_limit' => $request['rate_limit'],
            ]
        );
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function deleteRole($id)
    {
        $res = User::query()->where('user_roles_id', $id)->get(['id']);
        if (\count($res) > 0) {
            $userIds = [];
            foreach ($res as $user) {
                $userIds[] = $user['id'];
            }
            $defaultRole = self::getDefaultRole();
            if ($defaultRole !== null) {
                User::query()->whereIn('id', $userIds)->update(['user_roles_id' => $defaultRole->id]);
            }
        }

        return self::query()->where('id', $id)->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getDefaultRole()
    {
        return self::query()->where('isdefault', '=', 1)->first();
    }
}
