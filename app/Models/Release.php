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

    public function category()
    {
        return $this->belongsTo('App\Models\Category', 'categories_id');
    }

    public function predb()
    {
        return $this->belongsTo('App\Models\Predb', 'predb_id');
    }

    public function failed()
    {
        return $this->hasMany('App\Models\DnzbFailure', 'release_id');
    }

    public function releaseExtra()
    {
        return $this->hasOne('App\Models\Release', 'releases_id');
    }

    public function nfo()
    {
        return $this->hasOne('App\Models\ReleaseNfo', 'releases_id');
    }
}
