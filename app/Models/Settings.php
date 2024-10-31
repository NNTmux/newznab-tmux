<?php
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
 * @property string $section
 * @property string $subsection
 * @property string $name
 * @property string $value
 * @property string $hint
 * @property string $setting
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings whereHint($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings whereSection($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings whereSetting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Settings whereSubsection($value)
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

    public const ERR_BADNZBPATH_UNREADABLE = -7;

    public const ERR_BADNZBPATH_UNSET = -8;

    public const ERR_BAD_COVERS_PATH = -9;

    public const ERR_BAD_YYDECODER_PATH = -10;

    public const ERR_BADLAMEPATH = -11;

    public const ERR_SABCOMPLETEPATH = -12;

    /**
     * @var array
     */
    protected $primaryKey = ['section', 'subsection', 'name'];

    /**
     * @var string
     */

    /**
     * @var bool
     */
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
     * @var array
     */
    protected $guarded = [];

    protected static $settingsCollection;

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
     * Return a tree-like array of all or selected settings.
     *
     * @param  bool  $excludeUnsectioned  If rows with empty 'section' field should be excluded.
     *                                    Note this doesn't prevent empty 'subsection' fields.
     *
     * @throws \RuntimeException
     */
    public static function toTree(bool $excludeUnsectioned = true): array
    {
        $results = self::cursor()->remember();

        $tree = [];
        if (! empty($results)) {
            foreach ($results as $result) {
                if (! $excludeUnsectioned || ! empty($result->section)) {
                    $tree[$result->section][$result->subsection][$result->name] =
                        ['value' => $result->value, 'hint' => $result->hint];
                }
            }
        } else {
            throw new \RuntimeException(
                'No results from Settings table! Check your table has been created and populated.'
            );
        }

        return $tree;
    }

    public static function settingValue($setting): mixed
    {
        return self::query()->where(
            [
                'name' => $setting,
            ]
        )->value('value');
    }

    public static function settingsUpdate(array $data = []): void
    {
        foreach ($data as $key => $value) {
            self::query()->where('name', $key)->update(['value' => \is_array($value) ? implode(', ', $value) : $value]);
        }
    }
}
