<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
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
        'name',
        'backfill_target',
        'first_record',
        'first_record_postdate',
        'last_record',
        'last_record_postdate',
        'last_updated',
        'minfilestoformrelease',
        'minsizetoformrelease',
        'active',
        'backfill',
        'description',
    ];
}
