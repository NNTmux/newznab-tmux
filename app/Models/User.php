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
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $fillable = [
        'username',
        'password',
        'email',
        'role',
        'createddate',
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
