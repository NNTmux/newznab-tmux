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
 * not, see:
 *
 * @link      <http://www.gnu.org/licenses/>.
 * @author    DariusIII
 * @copyright 2017 NNTmux/nZEDb
 */
namespace App\models;


use Illuminate\Database\Eloquent\Model;

class ReleaseRegexes extends Model
{
	protected $table = 'release_regexes';

	public $timestamps = false;

	public $dateFormat = false;

	public $incrementing = false;

	protected $fillable = ['releases_id', 'collection_regex_id', 'naming_regex_id'];

	protected $primaryKey = [
		['releases_id', 'collection_regex_id', 'naming_regex_id']
	];
}
