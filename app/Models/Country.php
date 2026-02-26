<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Country.
 *
 * @property int $id
 * @property string|null $capital
 * @property string|null $citizenship
 * @property string $country_code
 * @property string|null $currency
 * @property string|null $currency_code
 * @property string|null $currency_sub_unit
 * @property string|null $currency_symbol
 * @property int|null $currency_decimals
 * @property string|null $full_name
 * @property string $iso_3166_2
 * @property string $iso_3166_3
 * @property string $name
 * @property string $region_code
 * @property string $sub_region_code
 * @property bool $eea
 * @property string|null $calling_code
 * @property string|null $flag
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCallingCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCapital($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCitizenship($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCurrencyDecimals($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCurrencySubUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereCurrencySymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereEea($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereIso31662($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereIso31663($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereRegionCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country whereSubRegionCode($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Country query()
 */
class Country extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;

    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];
}
