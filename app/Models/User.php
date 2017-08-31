<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
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
