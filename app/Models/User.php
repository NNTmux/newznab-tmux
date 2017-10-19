<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var array
     */
    protected $hidden = ['password', 'rsstoken'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo('App\Models\UserRole', 'user_roles_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function request()
    {
        return $this->hasMany('App\Models\UserRequest', 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function download()
    {
        return $this->hasMany('App\Models\UserDownload', 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function release()
    {
        return $this->hasMany('\App\Models\UsersRelease', 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invitation()
    {
        return $this->hasMany('App\Models\Invitation', 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function failedRelease()
    {
        return $this->hasMany('App\Models\DnzbFailure', 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function excludedCategory()
    {
        return $this->hasMany('App\Models\UserExcludedCategory', 'users_id');
    }

    /**
     *
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (User $user) {
            $user->release()->delete();
            $user->failedRelease()->delete();
            $user->excludedCategory()->delete();
            $user->download()->delete();
            $user->request()->delete();
        });
    }
}
