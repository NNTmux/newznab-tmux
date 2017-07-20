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

namespace App\Extensions\util;

use App\Providers\YencServiceProvider;

class Yenc extends YencServiceProvider
{

	/**
	 * @param       $text    yEncoded text to decode back to an 8 bit form.
	 *
	 * @param array $options
	 *
	 * @return string 8 bit decoded version of $text.
	 */
	public static function decode(&$text, array $options = [])
	{
		$options += [
			'name' => 'default',
			'file' => true,
		];
		return static::config($options['name'])->decode($text);
	}

	/**
	 * @param       $text
	 *
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function decodeIgnore(&$text, array $options = [])
	{
		$options += [
			'name' => 'default',
			'file' => true,
		];

		return static::config($options['name'])->decodeIgnore($text);
	}

	/**
	 * @param        $data      8 bit data to convert to yEncoded text.
	 * @param string $filename  Name of file to recreate as.
	 * @param int    $line      Maximum number of characters in each line.
	 *                          to use.
	 *
	 * @param bool   $crc32
	 * @param array  $options
	 *
	 * @return \Exception|string The yEncoded version of $data.
	 */
	public static function encode(&$data, $filename, $line = 128, $crc32 = true, array $options = [])
	{
		$options += ['name' => 'default'];

		return static::config($options['name'])->encode($data, $filename, $line, $crc32);
	}
}
