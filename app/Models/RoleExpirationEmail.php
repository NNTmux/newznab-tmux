<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleExpirationEmail extends Model
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
