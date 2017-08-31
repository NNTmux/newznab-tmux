<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    /**
     * @var string
     */
    protected $table = 'user_roles';

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
		'name',
		'apirequests',
		'downloadrequests',
		'defaultinvites',
		'isdefault',
		'canpreview',
		'hideads',
	];
}
