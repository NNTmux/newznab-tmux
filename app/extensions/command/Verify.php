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
namespace app\extensions\command;

use app\models\Settings;
use lithium\console\command\Help;


/**
 * Verifies various parts of your indexer.
 *
 * Actions:
 *  * settings_table	Checks that all settings in the 10~settings.tsv exist in your Db.
 */
class Verify extends \app\extensions\console\Command
{
	/**
	 * Constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		$defaults = [
			'classes'  => $this->_classes,
			'request'  => null,
			'response' => [],
		];
		$this->setName('verify')
			->setHelp('This function is used to verify that settings table is properly populated');
		parent::__construct($config + $defaults);
	}

	public function settingstable()
	{
		$dummy = Settings::hasAllEntries($this);
	}
}
