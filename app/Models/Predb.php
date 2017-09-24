<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Predb extends Model
{
    public $timestamps = false;

    protected $dateFormat = false;

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
}
