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
 * @author    niel
 * @copyright 2016 nZEDb
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MultigroupPoster extends Model
{
    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return string
     */
    public static function commaSeparatedList(): string
    {
        $list = [];
        $posters = self::all('poster');

        foreach ($posters as $poster) {
            $list[] = $poster->poster;
        }

        return implode(',', $list);
    }
}
