<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Release extends Model
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
    protected $guarded = ['id'];

    public function group()
    {
        return $this->belongsTo('App\Models\Group', 'groups_id');
    }

    public function download()
    {
        return $this->hasMany('App\Models\UserDownload', 'releases_id');
    }

    public function userRelease()
    {
        return $this->hasMany('App\Models\UsersRelease', 'releases_id');
    }

    public function file()
    {
        return $this->hasMany('App\Models\ReleaseFile', 'releases_id');
    }
}
