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
 * @link      <http://www.gnu.org/licenses/>.
 * @author    niel
 * @copyright 2014 nZEDb
 */

namespace Blacklight\db;

use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\utility\Git;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\DB;

class DbUpdate
{
    /**
     * @var
     */
    public $backedup;

    /**
     * @var \PDO
     */
    public $pdo;

    /**
     * @var mixed
     */
    public $git;

    /**
     * @var mixed
     */
    public $log;

    /**
     * @var
     */
    public $settings;

    /**
     * Has the Db been backed up?
     *
     *
     * @var bool
     */
    private $backedUp = false;

    /**
     * DbUpdate constructor.
     *
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $options += [
            'backup' => true,
            'db'     => null,
            'git'    => new Git(),
            'logger' => new ColorCLI(),
        ];

        $this->git = $options['git'];
        $this->log = $options['logger'];
        $this->pdo = DB::connection()->getPdo();
    }

    /**
     * Takes new files in the correct format from the patches directory and turns them into proper patches.
     *
     * The files should be name as '+x~<table>.sql' where x is a number starting at 1 for your first
     * patch. <table> should be the name of the primary table affected. If you have to modify more
     * than one table, consider splitting into multiple patches using different patch modifier
     * numbers to order them. i.e. +1~settings.sql, +2~predb.sql, etc.
     *
     * @param array $options
     *
     * @throws \Exception
     */
    public function newPatches(array $options = []): void
    {
        $defaults = [
            'ext'    => 'sql',
            'path'    => NN_RES.'db'.DS.'patches'.DS.'mysql',
            'regex'    => '#^'.Utility::PATH_REGEX.'\+(?P<order>\d+)~(?P<table>\w+)\.sql$#',
            'safe'    => true,
        ];
        $options += $defaults;

        $this->processPatches(['safe' => $options['safe']]); // Make sure we are completely up to date!

        ColorCLI::doEcho(ColorCLI::primaryOver('Looking for new patches...'));
        $files = Utility::getDirFiles($options);

        $count = \count($files);
        ColorCLI::doEcho(ColorCLI::header(" $count found"), true);
        if ($count > 0) {
            ColorCLI::doEcho(ColorCLI::header('Processing...'), true);
            natsort($files);
            $local = $this->isLocalDb() ? '' : 'LOCAL ';

            foreach ($files as $file) {
                if (! preg_match($options['regex'], $file, $matches)) {
                    ColorCLI::error("$file does not match the pattern {$options['regex']}\nPlease fix this before continuing");
                } else {
                    ColorCLI::doEcho(ColorCLI::header('Processing patch file: '.$file), true);
                    $this->splitSQL($file, ['local' => $local]);
                    $current = Settings::settingValue('..sqlpatch');
                    $current++;
                    Settings::query()->where('setting', '=', 'sqlpatch')->update(['value' => $current]);
                    $newName = $matches['drive'].$matches['path'].
                        str_pad($current, 4, '0', STR_PAD_LEFT).'~'.
                        $matches['table'].'.sql';
                    rename($matches[0], $newName);
                    $this->git->add($newName);
                    if ($this->git->isCommited($this->git->getBranch().':'.str_replace(NN_ROOT, '', $matches[0]))) {
                        $this->git->add(" -u {$matches[0]}"); // remove old filename from the index.
                    }
                }
            }
        }
    }

    /**
     * @param array $options
     *
     * @return int
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function processPatches(array $options = []): int
    {
        $patched = 0;
        $defaults = [
            'ext'    => 'sql',
            'path'    => NN_RES.'db'.DS.'patches'.DS.'mysql',
            'regex'    => '#^'.Utility::PATH_REGEX.'(?P<patch>\d{4})~(?P<table>\w+)\.sql$#',
            'safe'    => true,
        ];
        $options += $defaults;

        $currentVersion = Settings::settingValue('..sqlpatch');
        if (! is_numeric($currentVersion)) {
            exit("Bad sqlpatch value: '$currentVersion'\n");
        }

        $files = empty($options['files']) ? Utility::getDirFiles($options) : $options['files'];

        if (\count($files)) {
            natsort($files);
            $local = $this->isLocalDb() ? '' : 'LOCAL ';
            ColorCLI::doEcho(ColorCLI::primary('Looking for unprocessed patches...'), true);
            foreach ($files as $file) {
                $setPatch = false;
                $fp = fopen($file, 'rb');
                $patch = fread($fp, filesize($file));

                if (preg_match($options['regex'], str_replace('\\', '/', $file), $matches)) {
                    $patch = (int) $matches['patch'];
                    $setPatch = true;
                } elseif (preg_match(
                    '/UPDATE `?site`? SET `?value`? = \'?(?P<patch>\d+)\'? WHERE `?setting`? = \'sqlpatch\'/i',
                    $patch,
                    $matches
                )
                ) {
                    $patch = (int) $matches['patch'];
                } else {
                    throw new \RuntimeException('No patch information available, stopping!!');
                }
                if ($patch > $currentVersion) {
                    ColorCLI::doEcho(ColorCLI::header('Processing patch file: '.$file), true);
                    $this->splitSQL($file, ['local' => $local]);
                    if ($setPatch) {
                        Settings::query()->where('setting', '=', 'sqlpatch')->update(['value' => $patch]);
                    }
                    $patched++;
                }
            }
        } else {
            exit(ColorCLI::error("\nHave you changed the path to the patches folder, or do you have the right permissions?\n"));
        }

        if ($patched === 0) {
            ColorCLI::doEcho(ColorCLI::info("Nothing to patch, you are already on version $currentVersion"), true);
        }

        return $patched;
    }

    /**
     * @param       $file
     * @param array $options
     */
    public function splitSQL($file, array $options = []): void
    {
        $defaults = [
            'delimiter'    => ';',
            'local'        => null,
        ];
        $options += $defaults;

        if (! empty($options['vars'])) {
            extract($options['vars'], 'EXTR_OVERWRITE');
        }

        set_time_limit(0);

        if (is_file($file)) {
            $file = fopen($file, 'r, b');

            if (\is_resource($file)) {
                $query = [];

                $delimiter = $options['delimiter'];
                while (! feof($file)) {
                    $line = fgets($file);

                    if ($line === false) {
                        continue;
                    }

                    // Skip comments.
                    if (preg_match('!^\s*(#|--|//)\s*(.+?)\s*$!', $line, $matches)) {
                        echo ColorCLI::info('COMMENT: '.$matches[2]).PHP_EOL;
                        continue;
                    }

                    // Check for non default delimiters ($$ for example).
                    if (preg_match('#^\s*DELIMITER\s+(?P<delimiter>.+)\s*$#i', $line, $matches)) {
                        $delimiter = $matches['delimiter'];
                        if ($delimiter !== $options['delimiter']) {
                            continue;
                        }
                    }

                    // Check if the line has delimiter that is non default ($$ for example).
                    if ($delimiter !== $options['delimiter'] && preg_match('#^(.+?)'.preg_quote($delimiter).'\s*$#', $line, $matches)) {
                        // Check if the line has also the default delimiter (;), remove it.
                        if (preg_match('#^(.+?)'.preg_quote($options['delimiter']).'\s*$#', $matches[1], $matches2)) {
                            $matches[1] = $matches2[1];
                        }
                        // Change the non default delimiter ($$) to the default one(;).
                        $line = $matches[1].$options['delimiter'];
                    }

                    $query[] = $line;

                    if (preg_match('~'.preg_quote($delimiter, '~').'\s*$~iS', $line) === 1) {
                        $query = trim(implode('', $query));
                        if ($options['local'] !== null) {
                            $query = str_replace('{:local:}', $options['local'], $query);
                        }

                        try {
                            $this->pdo->exec($query);
                            ColorCLI::doEcho(ColorCLI::alternateOver('SUCCESS: ').ColorCLI::primary($query), true);
                        } catch (\PDOException $e) {
                            // Log the problem and the query.
                            file_put_contents(
                                NN_LOGS.'patcherrors.log',
                                '['.date('r').'] [ERROR] ['.
                                trim(preg_replace('/\s+/', ' ', $e->getMessage())).']'.PHP_EOL.
                                '['.date('r').'] [QUERY] ['.
                                trim(preg_replace('/\s+/', ' ', $query)).']'.PHP_EOL,
                                FILE_APPEND
                            );

                            if (
                                \in_array($e->errorInfo[1], [1091, 1060, 1061, 1071, 1146], false) ||
                                \in_array($e->errorInfo[0], [23505, 42701, 42703, '42P07', '42P16'], false)
                            ) {
                                if ($e->errorInfo[1] === 1060) {
                                    ColorCLI::doEcho(ColorCLI::warning(
                                        "$query The column already exists - No need to worry \{".
                                        $e->errorInfo[1]."}.\n"
                                    ), true);
                                } else {
                                    ColorCLI::doEcho(ColorCLI::warning(
                                        "$query Skipped - No need to worry \{".
                                        $e->errorInfo[1]."}.\n"
                                    ), true);
                                }
                            } elseif (preg_match('/ALTER IGNORE/i', $query)) {
                                $this->pdo->exec('SET SESSION old_alter_table = 1');
                                try {
                                    $this->pdo->exec($query);
                                    ColorCLI::doEcho(ColorCLI::alternateOver('SUCCESS: ').ColorCLI::primary($query));
                                } catch (\PDOException $e) {
                                    exit(ColorCLI::error("$query Failed \{".$e->errorInfo[1]."}\n\t".$e->errorInfo[2]));
                                }
                            } else {
                                exit(ColorCLI::error("$query Failed \{".$e->errorInfo[1]."}\n\t".$e->errorInfo[2]));
                            }
                        }

                        while (ob_get_level() > 0) {
                            ob_end_flush();
                        }
                        flush();
                    }

                    if (\is_string($query) === true) {
                        $query = [];
                    }
                }
            }
        }
    }


    /**
     * Attempts to determine if the Db is on the local machine.
     *
     * If the method returns true, then the Db is definitely on the local machine. However,
     * returning false only indicates that it could not positively be determined to be local - so
     * assume remote.
     *
     * @return bool Whether the Db is definitely on the local machine.
     */
    public function isLocalDb(): bool
    {
        $local = false;
        if (! empty(config('database.connections.nntmux.port')) || config('database.connections.nntmux.host') === 'localhost') {
            $local = true;
        } else {
            preg_match_all('/inet'.'6?'.' addr: ?([^ ]+)/', `ifconfig`, $ips);

            // Check for dotted quad - if exists compare against local IP number(s)
            if (preg_match('#^\d+\.\d+\.\d+\.\d+$#', config('database.connections.nntmux.host')) && \in_array(config('database.connections.nntmux.host'), $ips[1], false)) {
                $local = true;
            }
        }

        return $local;
    }
}
