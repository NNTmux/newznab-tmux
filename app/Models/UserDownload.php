<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDownload extends Model
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
    protected $fillable = ['id', 'users_id', 'releases_id', 'hosthash', 'timestamp'];

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }
}
