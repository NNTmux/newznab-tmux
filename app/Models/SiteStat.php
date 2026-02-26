<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteStat extends Model
{
    use HasFactory; // @phpstan-ignore missingType.generics

    protected $guarded = [];
}
