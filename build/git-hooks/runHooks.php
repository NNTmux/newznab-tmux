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
define('GIT_PRE_COMMIT', true);

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\utility\Git;
use App\Extensions\util\Versions;

echo "Running pre-commit hooks\n";

$error = false;

// TODO Add code here to check permissions on staged files.
//$files = file(NN_ROOT . 'Blacklight/build/git-hooks'), FILE_IGNORE_NEW_LINES);
//foreach ($files as $file) {
//	echo "Filename: $file\n";
//}

/*
 * Add all hooks BEFORE the versions are updated so they can be skipped on any errors
 */
if ($error === false) {
    $git = new Git();
    $branch = $git->getCurrentBranchName();
    if (in_array($branch, $git->mainBranches(), false)) {
        // Only update versions, etc. on specific branches to lessen conflicts

        if ($error === false) {
            try {
                $vers = new Versions();
                $vers->checkGitTag();
                $vers->checkSQLFileLatest(false);
                $vers->checkSQLDb(false);
                $vers->save();

                $git->addFile(NN_VERSIONS);
            } catch (\Exception $e) {
                $error = 1;
                echo "Error while checking versions!\n";
                echo $e->getMessage().PHP_EOL;
            }
        }
    } else {
        echo "not 'dev' or '0.x' branch, skipping version/patch updates\n";
    }
} else {
    echo "Error in pre-commit hooks!!\n";
}

exit($error);
