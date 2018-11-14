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

use App\Models\Settings;
use Blacklight\utility\Utility;

class Versions
{
    /**
     * These constants are bitwise for checking what has changed.
     */
    protected const UPDATED_GIT_TAG = 1;
    protected const UPDATED_SQL_DB_PATCH = 2;
    protected const UPDATED_SQL_FILE_LAST = 4;

    /**
     * @var int Bitwise mask of elements that have been changed.
     */
    protected $changes = 0;

    /**
     * @var \App\Extensions\util\Git
     */
    protected $git;

    /**
     * @var
     */
    protected $_config;

    /**
     * @var
     */
    protected $versions;

    /**
     * @var
     */
    protected $xml;

    /**
     * Versions constructor.
     *
     * @param array $config
     * @throws \Cz\Git\GitException
     */
    public function __construct(array $config = [])
    {
        $defaults = [
            'git'	=> null,
            'path'	=> NN_VERSIONS,
        ];
        $config += $defaults;

        $this->_config = $config;
        $this->initialiseGit();
    }

    /**
     * @throws \Cz\Git\GitException
     */
    public function checkGitTag(): void
    {
        $this->checkGitTagInFile();
    }

    /**
     * Checks the git's latest version tag against the XML's stored value. Version should be
     * Major.Minor.Revision[.fix][-dev|-RCx].
     *
     * @param bool $update
     *
     * @return false|string version string if matched or false.
     * @throws \Cz\Git\GitException
     */
    public function checkGitTagInFile($update = false)
    {
        $this->initialiseGit();
        $result = preg_match(Utility::VERSION_REGEX, $this->git->getHeadHash(), $matches) ? $matches['all'] : false;

        if ($result !== false) {
            if (! $this->git->isStable($this->git->getBranch())) {
                $this->loadXMLFile();
                $result = preg_match(
                    Utility::VERSION_REGEX,
                    $this->versions->git->tag->__toString(),
                    $matches
                ) ? $matches['digits'] : false;
                if ($result !== false && version_compare($matches['digits'], '0.0.0', '!=')) {
                    $this->versions->git->tag = '0.0.0-dev';
                    $this->changes |= self::UPDATED_GIT_TAG;
                }

                $result = $this->versions->git->tag;
            } else {
                $result = $this->checkGitTagsAreEqual(['update' => $update]);
            }
        }

        return $result;
    }

    /**
     * @param array $options
     * @return bool|\SimpleXMLElement
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \RuntimeException
     */
    public function checkGitTagsAreEqual(array $options = [])
    {
        $options += [
            'update' => true,
            'verbose' => true,
        ];

        $this->loadXMLFile();
        $latestTag = $this->git->tagLatest();

        // Check if file's entry is the same as current branch's tag
        if (version_compare($this->versions->git->tag, $latestTag, '!=')) {
            if ($options['update'] === true) {
                if ($options['verbose'] === true) {
                    echo "Updating tag version to $latestTag".PHP_EOL;
                }
                $this->versions->git->tag = $this->git->tagLatest();
                $this->changes |= self::UPDATED_GIT_TAG;

                return $this->versions->git->tag;
            }  // They're NOT the same but we were told not to update.
            if ($options['verbose'] === true) {
                echo "Current tag version $latestTag, skipping update!".PHP_EOL;
            }

            return false;
        }

        // They're the same so return true
        return true;
    }

    /**
     * Checks the database sqlpatch setting against the XML's stored value.
     *
     * @param bool $verbose
     *
     * @return bool|string The new database sqlpatch version, or false.
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function checkSQLDb($verbose = true)
    {
        $this->loadXMLFile();
        $patch = $this->getSQLPatchFromDB();

        if ($this->versions->sql->db->__toString() !== $patch) {
            if ($verbose) {
                echo "Updating Db revision to $patch".PHP_EOL;
            }
            $this->versions->sql->db = $patch;
            $this->changes |= self::UPDATED_SQL_DB_PATCH;
        }

        return $this->isChanged(self::UPDATED_SQL_DB_PATCH) ? $patch : false;
    }

    /**
     * @param bool $verbose
     * @throws \RuntimeException
     */
    public function checkSQLFileLatest($verbose = true): void
    {
        $this->loadXMLFile();
        $lastFile = $this->getSQLPatchLast();

        if ($lastFile !== false && $this->versions->sql->file->__toString() !== $lastFile) {
            if ($verbose === true) {
                echo "Updating latest patch file to $lastFile".PHP_EOL;
            }
            $this->versions->sql->file = $lastFile;
            $this->changes |= self::UPDATED_SQL_FILE_LAST;
        }
    }

    /**
     * @return string
     * @throws \Cz\Git\GitException
     */
    public function getGitBranch(): string
    {
        $this->initialiseGit();

        return $this->git->getBranch();
    }

    /**
     * @return string
     * @throws \Cz\Git\GitException
     */
    public function getGitHeadHash(): string
    {
        $this->initialiseGit();

        return $this->git->getHeadHash();
    }

    /**
     * @return null|string
     * @throws \RuntimeException
     */
    public function getGitTagInFile(): ?string
    {
        $this->loadXMLFile();

        return ($this->versions === null) ? null : $this->versions->git->tag->__toString();
    }

    /**
     * @return string
     * @throws \Cz\Git\GitException
     */
    public function getGitTagInRepo(): string
    {
        $this->initialiseGit();

        return $this->git->tagLatest();
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    public function getSQLPatchFromDB(): ?string
    {
        $dbVersion = Settings::settingValue('..sqlpatch');

        if (! is_numeric($dbVersion)) {
            throw new \RuntimeException('Bad sqlpatch value');
        }

        return $dbVersion;
    }

    /**
     * @return null|string
     * @throws \RuntimeException
     */
    public function getSQLPatchFromFile(): ?string
    {
        $this->loadXMLFile();

        return ($this->versions === null) ? null : $this->versions->sql->file->__toString();
    }

    /**
     * @return bool|int
     */
    public function getSQLPatchLast()
    {
        $options = [
            'data'  => NN_RES.'db'.DS.'schema'.DS.'data'.DS,
            'ext'   => 'sql',
            'path'  => NN_RES.'db'.DS.'patches',
            'regex' => '#^'.Utility::PATH_REGEX.'(?P<patch>\d{4})~(?P<table>\w+)\.sql$#',
            'safe'  => true,
        ];
        $files = Utility::getDirFiles($options);
        natsort($files);

        return preg_match($options['regex'], end($files), $matches) ? (int) $matches['patch'] : false;
    }

    /**
     * @return string
     * @throws \Cz\Git\GitException
     */
    public function getTagVersion(): string
    {
        $this->deprecated(__METHOD__, 'getGitTagInRepo');

        return $this->getGitTagInRepo();
    }

    /**
     * @return \simpleXMLElement
     * @throws \RuntimeException
     */
    public function getValidVersionsFile(): \simpleXMLElement
    {
        $this->loadXMLFile();

        return $this->xml;
    }

    /**
     * Check whether the XML has been changed by one of the methods here.
     *
     * @return bool True if the XML has been changed.
     */
    public function hasChanged(): bool
    {
        return $this->changes !== 0;
    }

    /**
     * @param bool $verbose
     */
    public function save($verbose = true): void
    {
        if ($this->hasChanged()) {
            if ($verbose === true && $this->changes > 0) {
                if ($this->isChanged(self::UPDATED_GIT_TAG)) {
                    echo 'Updated git tag version to '.$this->versions->git->tag.PHP_EOL;
                }

                if ($this->isChanged(self::UPDATED_SQL_DB_PATCH)) {
                    echo 'Updated Db SQL revision to '.$this->versions->sql->db.PHP_EOL;
                }

                if ($this->isChanged(self::UPDATED_SQL_FILE_LAST)) {
                    echo 'Updated latest SQL file to '.$this->versions->sql->file.PHP_EOL;
                }
            } elseif ($this->changes === 0) {
                echo 'Version file already up to date.'.PHP_EOL;
            }
            $this->xml->asXML($this->_config['path']);
            $this->changes = false;
        }
    }

    /**
     * @param $message
     */
    protected function error($message): void
    {
        // TODO handle console error message.
    }

    /**
     * @throws \Cz\Git\GitException
     */
    protected function initialiseGit(): void
    {
        if (! ($this->git instanceof Git)) {
            $this->git = new Git();
        }
    }

    /**
     * @param $property
     * @return bool
     */
    protected function isChanged($property): bool
    {
        return ($this->changes & $property) === $property;
    }

    /**
     * @throws \RuntimeException
     */
    protected function loadXMLFile(): void
    {
        if ($this->versions === null) {
            $temp = libxml_use_internal_errors(true);
            $this->xml = simplexml_load_string(file_get_contents($this->_config['path']));
            libxml_use_internal_errors($temp);

            if ($this->xml === false) {
                $this->error("Your versions XML file ($this->_config['path']) is broken, try updating from git.");
                throw new \RuntimeException("Failed to open versions XML file '{$this->_config['path']}'");
            }

            if ($this->xml->count() > 0) {
                $vers = $this->xml->xpath('/nntmux/versions');

                if ($vers[0]->count() === 0) {
                    $this->error("Your versions XML file ({$this->_config['path']}) does not contain version info, try updating from git.");
                    throw new \RuntimeException("Failed to find versions node in XML file '{$this->_config['path']}'");
                }

                $this->versions = &$this->xml->versions; // Create a convenience shortcut
            } else {
                throw new \RuntimeException("No elements in file!\n");
            }
        }
    }

    /**
     *
     */
    protected function _init(): void
    {
        if ($this->_config['git'] instanceof Git) {
            $this->git = &$this->_config['git'];
        }
    }

    /**
     * @param $methodOld
     * @param $methodUse
     */
    private function deprecated($methodOld, $methodUse): void
    {
        trigger_error(
            "This method ($methodOld) is deprecated. Please use '$methodUse' instead.",
            E_USER_NOTICE
        );
    }
}
