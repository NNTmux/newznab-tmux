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
 * @author    niel
 * @copyright 2016 nZEDb
 */

namespace app\extensions\util;

use Illuminate\Support\ServiceProvider;
use app\extensions\util\yenc\adapter\NzedbYenc;

class Yenc extends ServiceProvider
{
	/**
	 * @param       $text	yEncoded text to decode back to an 8 bit form.
	 *
	 * @return string		8 bit decoded version of $text.
	 */
	public static function decode(&$text)
	{
		return NzedbYenc::decode($text);
	}

	/**
	 * @param $text
	 *
	 * @return mixed
	 */
	public static function decodeIgnore(&$text)
	{
		return NzedbYenc::decodeIgnore($text);
	}

	/**
	 * @param 		  $data     8 bit data to convert to yEncoded text.
	 * @param string  $filename Name of file to recreate as.
	 * @param int     $line     Maximum number of characters in each line.
	 *                          to use.
	 *
	 * @return string|\Exception The yEncoded version of $data.
	 */
	public static function encode(&$data, $filename, $line = 128)
	{
		return NzedbYenc::encode($data, $filename, $line);
	}
}
