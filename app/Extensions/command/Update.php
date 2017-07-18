<?php
/**
 * Created by PhpStorm.
 * User: darius
 * Date: 19.6.17.
 * Time: 15.17
 */

namespace App\Extensions\command;

use App\Extensions\console\Command;
use App\Extensions\util\Git;
use App\Extensions\util\Versions;
use nntmux\db\DbUpdate;
use Symfony\Component\Process\Process;

class Update extends Command
{
	const UPDATES_FILE = NN_CONFIGS . 'updates.json';

	/**
	 * @var \app\extensions\util\Git object.
	 */
	protected $git;

	/**
	 * @var array Decoded JSON updates file.
	 */
	protected $updates = null;

	private $gitBranch;

	/**
	 * NewUpdate constructor.
	 *
	 */
	public function __construct()
	{

		$defaults = [
			'git'		=> null,
			'request'	=> null,
			'response'	=> [],
		];
		$this->setName('Update')
			->setHelp('This function is used to update nntmux. Run it by typing: php tmux update and then nntmux or db as second argument. ie: php tmux update nntmux');
		parent::__construct($defaults);


	}

	public function all()
	{
		$this->nntmux();
	}

	public function db()
	{
		// TODO Add check to determine if the indexer or other scripts are running. Hopefully
		// also prevent web access.
		$this->primary('Checking database version');

		$versions = new Versions(['git' => ($this->git instanceof Git) ? $this->git : null]);

		try {
			$currentDb = $versions->getSQLPatchFromDB();
			$currentXML = $versions->getSQLPatchFromFile();
		} catch (\PDOException $e) {
			$this->error('Error fetching patch versions!');

			return 1;
		}

		$this->primary("Db: $currentDb,\tFile: $currentXML");

		if ($currentDb < $currentXML) {
			$db = new DbUpdate(['backup' => false]);
			$db->processPatches(['safe' => false]);
		} else {
			$this->primary('Up to date.');
		}
	}

	public function git()
	{
		// TODO Add check to determine if the indexer or other scripts are running. Hopefully
		// also prevent web access.
		$this->initialiseGit();
		if (!in_array($this->git->getBranch(), $this->git->getBranchesMain(), false)) {
			$this->error('Not on the stable or dev branch! Refusing to update repository');

			return;
		}

		$this->primary($this->git->gitPull());
	}

	/**
	 * @return bool
	 */
	public function nntmux()
	{
		try {
			$output = $this->git();
			if ($output === 'Already up-to-date.') {
				$this->primary($output);
			} else {
				$status = $this->composer();
				if ($status) {
					$this->error('Composer failed to update!!');

					return false;
				}
				$fail = $this->db();
				if ($fail) {
					$this->error('Db updating failed!!');
					return 1;
					}

			}

			$smarty = new \Smarty();
			$smarty->setCompileDir(NN_SMARTY_TEMPLATES);
			$cleared = $smarty->clearCompiledTemplate();
			if ($cleared) {
				$this->primary('The Smarty compiled template cache has been cleaned for you');
			} else {
				$this->primary('You should clear your Smarty compiled template cache at: ' .
					NN_RES . 'smarty' . DS . 'templates_c'
				);
			}
		} catch (\Exception $e) {
			$this->error($e->getMessage());
		}
	}

	/**
	 * Issues the command to 'install' the composer package.
	 *
	 * It first checks the current branch for stable versions. If found then the '--no-dev'
	 * option is added to the command to prevent development packages being also downloded.
	 *
	 * @return integer Return status from Composer.
	 * @throws \Symfony\Component\Process\Exception\LogicException
	 * @throws \Symfony\Component\Process\Exception\RuntimeException
	 */
	protected function composer()
	{
		$this->initialiseGit();
		$command = 'composer install';
		if (in_array($this->gitBranch, $this->git->getBranchesStable(), false)) {
			$command .= ' --prefer-dist --no-dev';
		} else {
			$command .= ' --prefer-source';
		}
		$this->primary('Running composer install process...');
		$process = new Process($command);
		$process->run(function ($type, $buffer){
		if (Process::ERR === $type) {
			echo $buffer;
		}
		});

		return $process->getOutput();
	}

	protected function initialiseGit()
	{
		if (!($this->git instanceof Git)) {
			$this->git = new Git();
		}
	}
}