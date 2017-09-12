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
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'forumid',
        'parentid',
        'users_id',
        'subject',
        'message',
        'locked',
        'sticky',
        'replies',
        'createddate',
        'updatedate',
    ];
}
