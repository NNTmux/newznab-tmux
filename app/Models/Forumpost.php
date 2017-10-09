<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forumpost extends Model
{
    /**
     * @var string
     */
    protected $table = 'forumpost';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];
}
