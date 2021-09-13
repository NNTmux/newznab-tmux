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
 *
 * @author    niel
 * @copyright 2015 nZEDb
 */
require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\db\PreDb;
use Illuminate\Support\Facades\File;

if (! File::isReadable(NN_RES)) {
    exit('The  '.NN_RES.' folder must be readable.'.PHP_EOL);
}

if (! File::isWritable(NN_RES)) {
    exit('The ('.NN_RES.') folder must be writable.'.PHP_EOL);
}

if (! isset($argv[1]) || (! is_numeric($argv[1]) && $argv[1] !== 'progress') || ! isset($argv[2]) ||
    ! \in_array($argv[2], ['local', 'remote'], false) || ! isset($argv[3]) ||
    ! \in_array($argv[3], ['true', 'false'], false)
) {
    exit('This script quickly imports the daily PreDB dumps.'.PHP_EOL.
        'Argument 1: Enter the unix time of the patch to start at.'.PHP_EOL.
        'You can find the unix time in the file name of the patch, it\'s the long number.'.
        PHP_EOL.
        'You can put in 0 to import all the daily PreDB dumps.'.PHP_EOL.
        'You can put in progress to track progress of the imports and only import newer ones.'.
        PHP_EOL.
        'Argument 2: If your MySQL server is local, type local else type remote.'.PHP_EOL.
        'Argument 3: Show output of queries or not, true | false'.PHP_EOL
    );
}

$url = 'https://api.github.com/repos/nZEDb/nZEDbPre_Dumps/contents/dumps/';
$filePattern = '(?P<filename>(?P<stamp>\d+)_predb_dump\.csv\.gz)';

$result = getDirListing($url);

if ($result === null || (isset($result['message']) && strpos($result['message'], 'API rate limit exceeded for') === 0)) {
    exit("Error: $result");
}

foreach ($result as $dir) {
    if ($dir['name'] === '0README.txt') {
        continue;
    }

    $result = getDirListing($url.$dir['name'].'/');

    if ($result === false) {
        exit("Error: $result");
    }

    $data[$dir['name']] = $result;
}

$total = 0;
foreach ($data as $dir => $files) {
    $total += count($files);
}
$total--;

$predb = new PreDb();
$colorCli = new ColorCLI();

$progress = $predb->progress(settings_array());

foreach ($data as $dir => $files) {
    foreach ($files as $file) {
        if (preg_match("#^https://raw\.githubusercontent\.com/nZEDb/nZEDbPre_Dumps/master/dumps/$dir/$filePattern$#", $file['download_url'])) {
            if (preg_match("#^$filePattern$#", $file['name'], $hit)) {
                $timeMatch = max($progress['last'], $argv[1]);

                // Skip patches the user does not want.
                if ($hit[1] < $timeMatch) {
                    echo 'Skipping dump '.$hit[2].
                        ', as your minimum unix time argument is '.
                        $timeMatch.PHP_EOL;
                    $total--;
                    continue;
                }

                // Download the dump.
                $dump = getRawHtml($file['download_url']);
                echo "Downloading: {$file['download_url']}\n";

                if (! $dump) {
                    echo "Error downloading dump {$hit[2]} you can try manually importing it.".
                        PHP_EOL;
                    continue;
                }

                // Make sure we didn't get an HTML page.
                if (strpos($dump, '<!DOCTYPE html>') !== false) {
                    echo "The dump file {$hit[2]} might be missing from GitHub.".PHP_EOL;
                    continue;
                }

                // Decompress.
                $dump = gzdecode($dump);

                if (! $dump) {
                    echo "Error decompressing dump {$hit[2]}.".PHP_EOL;
                    continue;
                }

                // Store the dump.
                $dumpFile = NN_RES.$hit[2].'_predb_dump.csv';
                $fetched = File::put($dumpFile, $dump);
                if (! $fetched) {
                    echo "Error storing dump file {$hit[2]} in (".NN_RES.').'.
                        PHP_EOL;
                    continue;
                }

                // Make sure it's readable by all.
                chmod($dumpFile, 0777);
                $local = strtolower($argv[2]) === 'local' ? 'LOCAL' : '';
                $verbose = $argv[3] === 'true';

                if ($verbose) {
                    $colorCli->info('Clearing import table');
                }

                // Truncate to clear any old data
                DB::statement('TRUNCATE TABLE predb_imports');

                // Import file into predb_imports
                DB::unprepared("LOAD DATA {$local} INFILE '{$dumpFile}' IGNORE INTO TABLE predb_imports FIELDS TERMINATED BY '\\t\\t' OPTIONALLY ENCLOSED BY \"'\" LINES TERMINATED BY '\\r\\n' (title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groupname)");

                // Remove any titles where length <=8
                if ($verbose === true) {
                    $colorCli->info('Deleting any records where title <=8 from Temporary Table');
                }
                DB::delete('DELETE FROM predb_imports WHERE LENGTH(title) <= 8');

                // Add any groups that do not currently exist
                DB::insert("INSERT IGNORE INTO usenet_groups (name, description)
	                        SELECT groupname, 'Added by predb import script'
	                        FROM predb_imports AS pi LEFT JOIN usenet_groups AS g ON pi.groupname = g.name
	                        WHERE pi.groupname IS NOT NULL AND g.name IS NULL
	                        GROUP BY groupname");

                // Fill the groups_id
                DB::update('UPDATE predb_imports AS pi SET groups_id = (SELECT id FROM usenet_groups WHERE name = pi.groupname) WHERE groupname IS NOT NULL');

                $colorCli->info('Inserting records from temporary table into predb table');
                $inserted = DB::insert("INSERT INTO predb (title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groups_id)
                        SELECT pi.title, pi.nfo, pi.size, pi.files, pi.filename, pi.nuked, pi.nukereason, pi.category, pi.predate, pi.source, pi.requestid, groups_id
                        FROM predb_imports AS pi
                        ON DUPLICATE KEY UPDATE predb.nfo = IF(predb.nfo IS NULL, pi.nfo, predb.nfo),
	                    predb.size = IF(predb.size IS NULL, pi.size, predb.size),
	                    predb.files = IF(predb.files IS NULL, pi.files, predb.files),
	                    predb.filename = IF(predb.filename = '', pi.filename, predb.filename),
	                    predb.nuked = IF(pi.nuked > 0, pi.nuked, predb.nuked),
	                    predb.nukereason = IF(pi.nuked > 0, pi.nukereason, predb.nukereason),
	                    predb.category = IF(predb.category IS NULL, pi.category, predb.category),
	                    predb.requestid = IF(predb.requestid = 0, pi.requestid, predb.requestid),
	                    predb.groups_id = IF(predb.groups_id = 0, pi.groups_id, predb.groups_id)");

                // Delete the dump.
                unlink($dumpFile);

                $progress = $predb->progress(
                    settings_array($hit[2] + 1, $progress),
                    ['read' => false]
                );
                if ($inserted > 0) {
                    echo sprintf("Successfully imported PreDB dump %d (%s), %d dumps remaining\n", $hit[2], date('Y-m-d', $hit[2]), --$total);
                }
            } else {
                echo "Ignoring: {$file['download_url']}\n";
            }
        }
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////

function settings_array($last = null, $settings = null)
{
    if ($settings === null) {
        $settings['last'] = 0;
    }

    if ($last !== null) {
        $settings['last'] = $last;
    }

    return $settings;
}

function getDirListing($url)
{
    $result = getRawHtml($url);

    if ($result === false) {
        exit('Error connecting to GitHub, try again later?'.PHP_EOL);
    }

    return $result;
}
