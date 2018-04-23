<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sharing extends Model
{
    /**
     * @var string
     */
    protected $table = 'sharing';

    /**
     * @var string
     */
    protected $primaryKey = 'site_guid';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;
}
