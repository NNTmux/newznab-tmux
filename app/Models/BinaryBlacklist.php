<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BinaryBlacklist extends Model
{
	/**
	 * @var string
	 */
	protected $table = 'binaryblacklist';

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
	protected $fillable = [
		'id',
		'groupname',
		'regex',
		'msgcol',
		'optype',
		'status',
		'description',
		'last_activity'
	];
}
