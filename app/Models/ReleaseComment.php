<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseComment extends Model
{
    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
