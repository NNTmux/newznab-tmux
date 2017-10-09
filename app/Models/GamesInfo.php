<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamesInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'gamesinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];
}
