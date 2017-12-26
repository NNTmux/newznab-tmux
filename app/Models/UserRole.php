<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * @param $name
     * @param $apirequests
     * @param $downloadrequests
     * @param $defaultinvites
     * @param $canpreview
     * @param $hideads
     * @param $donation
     * @param $addYear
     * @return int
     */
    public static function addRole($name, $apirequests, $downloadrequests, $defaultinvites, $canpreview, $hideads, $donation, $addYear): int
    {
        return self::query()->insertGetId(
            [
                'name' => $name,
                'apirequests' => $apirequests,
                'downloadrequests' => $downloadrequests,
                'defaultinvites' => $defaultinvites,
                'canpreview' => $canpreview,
                'hideads' => $hideads,
                'donation' => $donation,
                'addyears' => $addYear,
            ]
        );
    }

    /**
     * @param $id
     * @param $name
     * @param $apirequests
     * @param $downloadrequests
     * @param $defaultinvites
     * @param $isdefault
     * @param $canpreview
     * @param $hideads
     * @param $donation
     * @param $addYear
     * @return int
     */
    public static function updateRole($id, $name, $apirequests, $downloadrequests, $defaultinvites, $isdefault, $canpreview, $hideads, $donation, $addYear): int
    {
        if ((int) $isdefault === 1) {
            self::query()->update(['isdefault' => 0]);
        }

        return self::query()->where('id', $id)->update(
            [
                'name' => $name,
                'apirequests' => $apirequests,
                'downloadrequests' => $downloadrequests,
                'defaultinvites' => $defaultinvites,
                'isdefault' => $isdefault,
                'canpreview' => $canpreview,
                'hideads' => $hideads,
                'donation' => $donation,
                'addyears' => $addYear,
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
