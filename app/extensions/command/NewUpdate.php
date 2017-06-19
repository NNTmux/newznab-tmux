<?php
/**
 * Created by PhpStorm.
 * User: darius
 * Date: 19.6.17.
 * Time: 15.17
 */

namespace app\extensions\command;


use app\extensions\util\Git;
use app\extensions\util\Versions;
use Illuminate\Console\Command;
use nntmux\db\DbUpdate;
use nntmux\ColorCLI;

class NewUpdate extends Command
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
	 */
	public function __construct()
	{

		$this->setName('update');
		parent::__construct();
	}

	public function all()
	{
		$this->nntmux();
	}

	public function db()
	{
		// TODO Add check to determine if the indexer or other scripts are running. Hopefully
		// also prevent web access.
		ColorCLI::doEcho(ColorCLI::primary('Checking database version'));

		$versions = new Versions(['git' => ($this->git instanceof Git) ? $this->git : null]);

		try {
			$currentDb = $versions->getSQLPatchFromDB();
			$currentXML = $versions->getSQLPatchFromFile();
		} catch (\PDOException $e) {
			ColorCLI::doEcho(ColorCLI::alternate('Error fetching patch versions!'));

			return 1;
		}

		ColorCLI::doEcho(ColorCLI::primary("Db: $currentDb,\tFile: $currentXML"));

		if ($currentDb < $currentXML) {
			$db = new DbUpdate(['backup' => false]);
			$db->processPatches(['safe' => false]);
		} else {
			ColorCLI::doEcho(ColorCLI::primary('Up to date.'));
		}
	}

	public function git()
	{
		// TODO Add check to determine if the indexer or other scripts are running. Hopefully
		// also prevent web access.
		$this->initialiseGit();
		if (!in_array($this->git->getBranch(), $this->git->getBranchesMain(), false)) {
			ColorCLI::doEcho(ColorCLI::warning('Not on the stable or dev branch! Refusing to update repository'));

			return;
		}

		ColorCLI::doEcho(ColorCLI::primary($this->git->pull()));
	}

	/**
	 * @return bool
	 */
	public function nntmux()
	{
		try {
			$output = $this->git();
			if ($output === 'Already up-to-date.') {
				ColorCLI::doEcho(ColorCLI::primary($output));
			} else {
				$status = $this->composer();
				if ($status) {
					ColorCLI::doEcho(ColorCLI::error('Composer failed to update!!'));

					return false;
				} else {
					$fail = $this->db();
					if ($fail) {
						ColorCLI::doEcho(ColorCLI::error('Db updating failed!!'));

						return 1;
					}
				}
			}

			$smarty = new \Smarty();
			$smarty->setCompileDir(NN_SMARTY_TEMPLATES);
			$cleared = $smarty->clearCompiledTemplate();
			if ($cleared) {
				ColorCLI::doEcho(ColorCLI::primary('The Smarty compiled template cache has been cleaned for you'));
			} else {
				ColorCLI::doEcho(ColorCLI::primary('You should clear your Smarty compiled template cache at: ' .
					NN_RES . 'smarty' . DS . 'templates_c'
				));
			}
		} catch (\Exception $e) {
			ColorCLI::doEcho(ColorCLI::error($e->getMessage()));
		}
	}

	/**
	 * Import/Update the predb table using tab separated value files.
	 */
	public function predb()
	{
		ColorCLI::doEcho(ColorCLI::error('predb not available yet!'));
	}

	/**
	 * Issues the command to 'install' the composer package.
	 *
	 * It first checks the current branch for stable versions. If found then the '--no-dev'
	 * option is added to the command to prevent development packages being also downloded.
	 *
	 * @return integer Return status from Composer.
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
		ColorCLI::doEcho(ColorCLI::primary('Running composer install process...'));
		system($command, $status);

		return $status;
	}

	protected function initialiseGit()
	{
		if (!($this->git instanceof Git)) {
			$this->git = new Git();
		}
	}
}