<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    public $incrementing = false;

    protected $dateFormat = false;

    public $timestamps = false;

    protected $fillable = ['id', 'iso3', 'country'];
}
