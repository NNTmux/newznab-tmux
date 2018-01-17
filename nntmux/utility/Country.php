<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:
 *
 * @link      <http://www.gnu.org/licenses/>.
 * @author    ruhllatio
 * @copyright 2015 nZEDb
 */

namespace nntmux\utility;

use App\Models\Country as CountryModel;

/**
 * Class Country.
 */
class Country
{
    /**
     * Get a country code for a country name.
     *
     * @param string $country
     *
     *
     * @return mixed
     */
    public static function countryCode($country)
    {
        if (\strlen($country) > 2) {
            $code = CountryModel::query()->where('country', $country)->first(['id']);
            if ($code !== null && isset($code['id'])) {
                return $code['id'];
            }
        }

        return '';
    }
}
