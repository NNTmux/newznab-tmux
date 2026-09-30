<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $rule */
final class TmuxCleanupRule extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
