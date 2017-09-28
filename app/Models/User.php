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
    protected $fillable = [
        'username',
        'password',
        'email',
        'user_roles_id',
        'created_at',
        'updated_at',
        'host',
        'rsstoken',
        'invites',
        'invitedby',
        'userseed',
        'notes',
    ];

    /**
     * @var array
     */
    protected $hidden = ['password', 'rsstoken'];

    public function role()
    {
        return $this->belongsTo('App\Models\UserRole', 'user_roles_id');
    }

    public function request()
    {
        return $this->hasMany('App\Models\UserRequest', 'users_id');
    }

    public function download()
    {
        return $this->hasMany('App\Models\UserDownload', 'users_id');
    }

    public function release()
    {
        return $this->hasMany('\App\Models\UsersRelease', 'users_id');
    }

    public function invitation()
    {
        return $this->hasMany('App\Models\Invitation', 'users_id');
    }
}
