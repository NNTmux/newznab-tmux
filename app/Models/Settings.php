<?php

declare(strict_types=1);

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:.
 *
 * @link      <http://www.gnu.org/licenses/>.
 *
 * @author    niel
 * @author    DariusIII
 * @copyright 2016 nZEDb, 2017 NNTmux
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Settings - model for settings table.
 *
 * @property string $name
 * @property string $value
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings whereValue($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings query()
 */
class Settings extends Model
{
    public const REGISTER_STATUS_OPEN = 0;

    public const REGISTER_STATUS_INVITE = 1;

    public const REGISTER_STATUS_CLOSED = 2;

    public const ERR_BADUNRARPATH = -1;

    public const ERR_BADFFMPEGPATH = -2;

    public const ERR_BADMEDIAINFOPATH = -3;

    public const ERR_BADNZBPATH = -4;

    public const ERR_DEEPNOUNRAR = -5;

    public const ERR_BADTMPUNRARPATH = -6;

    public const ERR_BADLAMEPATH = -11;

    public const ERR_SABCOMPLETEPATH = -12;

    /**
     * @var string
     */
    protected $primaryKey = 'name';

    /**
     * @var string
     */
    protected $keyType = 'string';

    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @var Collection<int, mixed>
     */
    protected static ?\Illuminate\Support\Collection $settingsCollection = null; // @phpstan-ignore generics.notGeneric, property.phpDocType, missingType.generics

    /**
     * Get the value attribute and convert empty strings to null and numeric strings to numbers.
     *
     * @param  string  $value
     */
    public function getValueAttribute($value): mixed
    {
        return self::convertValue($value);
    }

    /**
     * Adapted from https://laravel.io/forum/01-15-2016-overriding-eloquent-attributes.
     *
     * @return mixed
     */
    public function __get($key)
    {
        $override = self::query()->where('name', $key)->first();

        // If there's an override and no mutator has been explicitly defined on
        // the model then use the override value
        if ($override && ! $this->hasGetMutator($key)) {
            return $override->value;
        }

        // If the attribute is not overridden the use the usual __get() magic method
        return parent::__get($key);
    }

    /**
     * Return a simple key-value array of all settings.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public static function toTree(bool $excludeUnsectioned = true): array
    {
        $results = self::query()->pluck('value', 'name')->toArray();

        if (empty($results)) {
            throw new \RuntimeException(
                'No results from Settings table! Check your table has been created and populated.'
            );
        }

        return $results;
    }

    public static function settingValue(mixed $setting): mixed
    {
        $value = self::query()->where('name', $setting)->value('value');

        // Apply the same conversion logic as the accessor
        return self::convertValue($value);
    }

    /**
     * Convert setting value: numeric strings to numbers, preserve empty strings.
     *
     * @param  string|null  $value
     */
    public static function convertValue($value): mixed
    {
        // Handle null
        if ($value === null) {
            return null;
        }

        // Keep empty strings as empty strings (don't convert to null)
        // Many settings expect empty strings, not null
        if ($value === '') {
            return '';
        }

        // Convert numeric strings to actual numbers
        if (is_numeric($value)) {
            // Check if it's an integer or float
            if (strpos((string) $value, '.') !== false) {
                return (float) $value;
            }

            return (int) $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function settingsUpdate(array $data = []): void
    {
        foreach ($data as $key => $value) {
            self::query()->where('name', $key)->update(['value' => \is_array($value) ? implode(', ', $value) : $value]);
        }
    }
}
