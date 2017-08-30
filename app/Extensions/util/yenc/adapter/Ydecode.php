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

namespace App\Extensions\util\yenc\adapter;

use App\Extensions\util\Yenc;
use App\Models\Settings;
use nntmux\utility\Utility;

class Ydecode
{
	/**
	 * Path to yyDecoder binary.
	 *
	 * @var bool|string
	 * @access protected
	 */
	protected static $pathBin;

	/**
	 * If on unix, hide yydecode CLI output.
	 *
	 * @var string
	 * @access protected
	 */
	protected static $silent;

	public static function decode(&$text, $ignore = false)
	{
		$result = preg_match('/^(=yBegin.*=yEnd[^$]*)$/ims', $text, $input);
		switch (true) {
			case !$result:
				throw new \RuntimeException('Text does not look like yEnc.');
			case self::$pathBin === false:
				throw new \InvalidArgumentException('No valid path to yydecoder binary found!');
			default:
		}

		$ignoreFlag = $ignore ? '-b ' : '';
		$data = shell_exec(
			"echo '{$input[1]}' | {" . self::$pathBin . '} -o - ' . $ignoreFlag . self::$silent
		);
		if ($data === null) {
			throw new \RuntimeException('Error getting data from yydecode.');
		}

		return $data;
	}

	public static function decodeIgnore(&$text)
	{
		self::decode($text, true);
	}

	/**
	 * Determines if this adapter is enabled by checking if the `yydecode` path is enabled.
	 *
	 * @return boolean Returns `true` if enabled, otherwise `false`.
	 */
	public static function enabled()
	{
		return !empty(self::$pathBin);
	}

	public static function encode($data, $filename, $lineLength, $crc32)
	{
		return Yenc::encode($data, $filename, $lineLength, $crc32, ['name' => 'Php']);
	}
}

?>
