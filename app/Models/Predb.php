<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Predb extends Model
{
    /**
     * @var string
     */
    protected $table = 'predb';

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
        'title',
        'nfo',
        'size',
        'category',
        'predate',
        'source',
        'requestid',
        'groups_id',
        'nuked',
        'nukereason',
        'files',
        'filename',
        'searched',
    ];

    public function hash()
    {
        return $this->hasMany('App\Models\PredbHash', 'predb_id');
    }
}
