<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSerie extends Model
{
    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    protected $dateFormat = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
