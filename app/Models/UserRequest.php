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

    /**
     * @var array
     */
    protected $fillable = ['id', 'users_id', 'request', 'hosthash', 'timestamp'];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'users_id');
    }
}
