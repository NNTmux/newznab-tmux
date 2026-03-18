<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Country.
 *
 * @property string $iso_3166_2
 * @property string $name
 * @property string|null $full_name
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereIso31662($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereName($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country query()
 */
class Country extends Model
{
    protected $primaryKey = 'iso_3166_2';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];
}
