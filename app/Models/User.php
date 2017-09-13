<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    const CREATED_AT = 'createddate';
    const UPDATED_AT = 'updateddate';

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
        'role',
        'createddate',
        'updateddate',
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
}
