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

use app\extensions\console\Command;
use \app\extensions\util\Versions;


/**
 * Returns the current version (or branch) of the indexer.
 *
 * Actions:
 *  * all        Show all of following info.
 *  * branch    Show git branch name.
 *  * git        Show git tag for current version.
 *  * sql        Show SQL patch level
 *
 * @package app\extensions\command
 */
class Version extends Command
{
	/**
	 * @var Versions;
	 */
	protected $versions = null;

	/**
	 * Constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		$this->versions = new Versions();
		$this->setName('version')
			->setHelp('Returns the current version (or branch) of the indexer.
		Actions:
		all		Show all of following info.
		branch		Show git branch name.
  		git		Show git tag for current version.
		sql		Show SQL patch level');
		parent::__construct($config);
	}

	public function all()
	{
		$this->git();
		$this->sql();
	}

	public function branch()
	{
		$this->primary('Git branch: ' . $this->versions->getGitBranch());
	}

	/**
	 * Fetch git tag for latest version.
	 */
	public function git()
	{

		$this->primary('Looking up Git tag version(s)');

		$this->info('Hash: ' . $this->versions->getGitHeadHash());
		$this->info('XML version: ' . $this->versions->getGitTagInFile());
		$this->info('Git version: ' . $this->versions->getGitTagInRepo());
	}

	/**
	 * Fetch SQL latest patch version.
	 */
	public function sql()
	{

		$this->primary('Looking up SQL patch version(s)');


		$latest = $this->versions->getSQLPatchFromFile();
		$this->info("XML version: $latest");

		try {
			$dbVersion = $this->versions->getSQLPatchFromDB();
			$this->info(' DB version: ' . $dbVersion);
		} catch (\Exception $e) {
			$this->error($e->getMessage());
		}

	}
}
