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
 *
 * @author    ruhllatio
 * @copyright 2015 nZEDb
 */

namespace Blacklight\utility;

use App\Models\Country as CountryModel;

/**
 * Class Country.
 */
class Country
{
    /**
     * Get a country code for a country name.
     *
     * @return mixed
     */
    public static function countryCode(string $country)
    {
        if (\strlen($country) > 2) {
            $code = CountryModel::whereFullName($country)->orWhere('name', $country)->first(['iso_3166_2']);
            if ($code !== null && isset($code['iso_3166_2'])) {
                return $code['iso_3166_2'];
            }
        }

        return '';
    }
}
