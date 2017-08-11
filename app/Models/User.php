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
	protected $fillable = ['username', 'email', 'password', 'role'];

	/**
	 * @var array
	 */
	protected $hidden = ['password', 'rsstoken'];
}
