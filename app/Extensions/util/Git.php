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
 * not, see:.
 *
 * @link      <http://www.gnu.org/licenses/>.
 * @author    niel
 * @copyright 2016 nZEDb
 */

namespace App\Extensions\util;

use GitRepo;
use Symfony\Component\Process\Process;

class Git
{
    /**
     * @var \GitRepo object
     */
    protected $repo;

    /**
     * @var array
     */
    public $_config;

    protected $gitTagLatest = null;

    private $branch;

    public function __construct(array $config = [])
    {
        $defaults = [
			'branches'		=> [
				'stable' => ['0.x', 'Latest-testing', '\d+\.\d+\.\d+(\.\d+)?'],
				'development' => ['dev', 'dev-test'],
			],
			'create'		=> false,
			'initialise'	=> false,
			'filepath'		=> NN_ROOT,
		];

        $config += $defaults;
        $this->_config = $config;

        $this->repo = new GitRepo(
			$this->_config['filepath'],
			$this->_config['create'],
			$this->_config['initialise']
		);
        $this->branch = $this->repo->active_branch();
    }

    /**
     * Run describe command.
     *
     * @param string $options
     *
     * @return string
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function describe($options = null)
    {
        $command = new Process('git describe '.$options);
        $command->run();

        return $command->getOutput();
    }

    /**
     * Return the currently active branch.
     *
     * @return string
     */
    public function getBranch()
    {
        return $this->branch;
    }

    public function getBranchesDevelop()
    {
        return $this->_config['branches']['development'];
    }

    /**
     * Fetches the array of branch names that are considered to be core.
     *
     * @return array
     */
    public function getBranchesMain()
    {
        return array_merge($this->getBranchesStable(), $this->getBranchesDevelop());
    }

    public function getBranchesStable()
    {
        return $this->_config['branches']['stable'];
    }

    public function getHeadHash()
    {
        $command = new Process('git rev-parse HEAD');
        $command->run();

        return $command->getOutput();
    }

    /**
     * Determine if the supplied object is commited to the repository or not.
     *
     * @param $gitObject
     *
     * @return bool
     * @throws \Exception
     */
    public function isCommited($gitObject)
    {
        $cmd = "cat-file -e $gitObject";

        try {
            $result = new Process($cmd);
            $result->run();

            return $result->getOutput();
        } catch (\Exception $e) {
            $message = explode("\n", $e->getMessage());
            if ($message[0] === "fatal: Not a valid object name $gitObject") {
                $result = false;
            } else {
                throw new \RuntimeException($message);
            }
        }

        return $result === '';
    }

    public function isStable($branch)
    {
        foreach ($this->getBranchesStable() as $pattern) {
            if (! preg_match("#$pattern#", $branch)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Run the log command.
     *
     * @param null $options
     *
     * @return string
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function log($options = null)
    {
        $command = new Process("git log $options");
        $command->run();

        return $command->getOutput();
    }

    public function gitPull(array $options = [])
    {
        $default = [
			'branch'	=> $this->getBranch(),
			'remote'	=> 'origin',
		];
        $options += $default;

        return $this->repo->pull($options['remote'], $options['branch']);
    }

    /**
     * Run a git command in the git repository
     * Accepts a git command to run.
     *
     *
     * @param   string  $command Command to run
     *
     * @return  string
     */
    public function gitRun($command)
    {
        return $this->repo->run($command);
    }

    /**
     * Run the tag command.
     *
     * @param string $options
     *
     * @return string
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @throws \Symfony\Component\Process\Exception\LogicException
     */
    public function tag($options = null)
    {
        $command = new Process('git tag'.$options);
        $command->run();

        return $command->getOutput();
    }

    /**
     * Fetch the most recently added tag.
     *
     * Be aware this might cause problems if tags are added out of order?
     *
     * @param bool $cached
     *
     * @return string
     */
    public function tagLatest($cached = true)
    {
        if (empty($this->gitTagLatest) || $cached === false) {
            $this->gitTagLatest = trim($this->describe('--tags --abbrev=0 HEAD'));
        }

        return $this->gitTagLatest;
    }
}
