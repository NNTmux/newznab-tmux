<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRequest extends Model
{

	/**
	 * @var string
	 */
	protected $table = 'user_requests';

	/**
	 * @var bool
	 */
	protected $dateFormat = false;

	/**
	 * @var bool
	 */
	public $timestamps = false;


	protected $fillable = ['users_id', 'request', 'hosthash', 'timestamp'];
}
