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

class Yenc extends ServiceProvider
{
	/**
	 * @var string
	 */
	protected static $_adapter = 'app\extensions\util\yenc\adapter\\';

	/**
	 * @param string $adapter
	 */
	public static function setAdapter(string $adapter)
	{
		self::$_adapter = self::$_adapter .= $adapter;
	}

	/**
	 * @return string
	 */
	public static function getAdapter(): string
	{
		return self::$_adapter;
	}

	/**
	 * @param       $text	 yEncoded text to decode back to an 8 bit form.
	 * @param array $options Options needed for method. Mainly:
	 *						 - 'name' of the configuration to use.
	 *                       * 'file' whether to create the file or just return the string.
	 *
	 * @return string		 8 bit decoded version of $text.
	 */
	public static function decode(&$text, array $options = [])
	{
		$options += [
			'name' => 'default',
			'file' => true,
		];
		$_adapters = self::getAdapter();

		return $_adapters . $options['name']->decode($text);
	}

	public static function decodeIgnore(&$text, array $options = [])
	{
		$options += [
			'name' => 'default',
			'file' => true,
		];

		return (\app\extensions\util\yenc\adapter\$options['name'])->decodeIgnore($text);
	}

	/**
	 * @param binary  $data     8 bit data to convert to yEncoded text.
	 * @param string  $filename Name of file to recreate as.
	 * @param int     $line     Maximum number of characters in each line.
	 * @param boolean $crc32    Whether to add CRC checksum to yend line. This is recommended.
	 * @param array $options    Options needed for method. Mainly the 'name' of the configuration
	 *                          to use.
	 *
	 * @return string|\Exception The yEncoded version of $data.
	 */
	public static function encode(&$data, $filename, $line = 128, $crc32 = true, array $options = [])
	{
		$options += ['name' => 'default'];

		$_adapters = self::getAdapter();

		return $_adapters . $options['name']->encode($data, $filename, $line, $crc32);
	}
}
