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
 * @link <http://www.gnu.org/licenses/>.
 * @author niel
 * @copyright 2014 nZEDb
 */
namespace App\extensions\console;

use Symfony\Component\Console\Output\ConsoleOutput;

class Command extends \Illuminate\Console\Command
{
	/**
	 * Command constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = array())
	{
		$this->setName('command');
		parent::__construct();
		$this->output = new ConsoleOutput();
	}

	public function info($text, $verbosity = null)
	{
		if ($this->output->isQuiet()) {
			return;
		}
		$this->output->writeln('<info>' . $text . '</info>', $verbosity);
	}

	public function primary($text)
	{
		if ($this->output->isQuiet()) {
			return;
		}
		$this->output->writeln('<comment>' . $text . '</comment>');
	}

	public function error($text, $verbosity = null)
	{
		if ($this->output->isQuiet()) {
			return;
		}
		$this->output->writeln('<error>' . $text . '</error>', $verbosity);
	}
}
?>
