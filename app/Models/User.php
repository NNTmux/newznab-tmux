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
        return $this->belongsTo(UserRole::class, 'user_roles_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function request()
    {
        return $this->hasMany(UserRequest::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function download()
    {
        return $this->hasMany(UserDownload::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function release()
    {
        return $this->hasMany(UsersRelease::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invitation()
    {
        return $this->hasMany(Invitation::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function failedRelease()
    {
        return $this->hasMany(DnzbFailure::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function excludedCategory()
    {
        return $this->hasMany(UserExcludedCategory::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comment()
    {
        return $this->hasMany(ReleaseComment::class, 'users_id');
    }

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
