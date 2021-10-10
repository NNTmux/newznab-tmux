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
 *
 * @author niel
 * @copyright 2014 nZEDb
 */

namespace Blacklight\utility;

use CzProject\GitPhp\GitRepository;

/**
 * Class Git - Wrapper for various git operations.
 */
class Git extends GitRepository
{
    /**
     * @var string
     */
    private string $branch;

    /**
     * @var array
     */
    private array $mainBranches = ['dev', 'master'];

    /**
     * Git constructor.
     *
     * @param  array  $options
     *
     * @throws \CzProject\GitPhp\GitException
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'create'        => false,
            'initialise'    => false,
            'filepath'        => base_path().'/',
        ];
        $options += $defaults;

        parent::__construct($options['filepath']);
        $this->branch = $this->getCurrentBranchName();
    }

    /**
     * Return the number of commits made to repo.
     *
     * @throws \CzProject\GitPhp\GitException
     */
    public function commits(): int
    {
        $count = 0;
        foreach (explode("\n", $this->log()) as $line) {
            if (str_starts_with($line, 'commit')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param null $options
     * @return \CzProject\GitPhp\RunnerResult
     * @throws \CzProject\GitPhp\GitException
     */
    public function describe($options = null): \CzProject\GitPhp\RunnerResult
    {
        return $this->run("describe $options");
    }

    /**
     * @return string
     */
    public function getBranch(): string
    {
        return $this->branch;
    }

    /**
     * @param $gitObject
     * @return false|bool
     */
    public function isCommited($gitObject): bool
    {
        $cmd = "cat-file -e $gitObject";

        try {
            $result = $this->run($cmd);
        } catch (\Throwable $e) {
            $message = explode("\n", $e->getMessage());
            if ($message[0] === "fatal: Not a valid object name $gitObject") {
                $result = false;
            } else {
                throw new \RuntimeException($message);
            }
        }

        return $result === '';
    }

    /**
     * @param null $options
     * @return \CzProject\GitPhp\RunnerResult
     * @throws \CzProject\GitPhp\GitException
     */
    public function log($options = null): \CzProject\GitPhp\RunnerResult
    {
        return $this->run("log $options");
    }

    /**
     * @return array
     */
    public function mainBranches(): array
    {
        return $this->mainBranches;
    }

    /**
     * @param null $options
     * @return \CzProject\GitPhp\RunnerResult
     * @throws \CzProject\GitPhp\GitException
     */
    public function tag($options = null): \CzProject\GitPhp\RunnerResult
    {
        return $this->run("tag $options");
    }

    /**
     * @return \CzProject\GitPhp\RunnerResult
     * @throws \CzProject\GitPhp\GitException
     */
    public function tagLatest(): \CzProject\GitPhp\RunnerResult
    {
        return $this->describe('--tags --abbrev=0 HEAD');
    }
}
