<?php
require dirname(__FILE__) . '/../www/config.php';
require_once(WWW_DIR . "/lib/ReleaseSearch.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/ColorCLI.php");

if (!isset($argv[1]) || !in_array($argv[1], ['sphinx', 'standard'])) {
	exit('Argument1 (required) is the method of search you would like to optimize for.  Choices are sphinx or standard.' . PHP_EOL .
		'Argument2 (optional) is the storage engine and row_format you would like the releasesearch table to use. If not entered it will be left default.' . PHP_EOL .
		'Choices are (c|d)(myisam|innodb) (Compressed|Dynamic)(MyISAM|InnoDB) entered like dinnodb.  This argument has no effect if optimizinf for Sphinx.' . PHP_EOL .
		'Please stop all processing scripts before running this script.' . PHP_EOL);
}

switch ($argv[1]) {
	case 'sphinx':
		if (NN_RELEASE_SEARCH_TYPE == ReleaseSearch::SPHINX) {
			optimizeForSphinx(new DB());
		} else {
			echo PHP_EOL . $c->error('Error, nZEDb_RELEASE_SEARCH_TYPE in www/settings.php must be set to SPHINX to optimize for Sphinx!' . PHP_EOL);
		}
		break;
	case 'standard':
		revertToStandard(new DB());
		break;
}

// Optimize database usage for Sphinx full-text
function optimizeForSphinx($pdo)
{
	$c = new ColorCLI();
	$pdo = new DB();
	echo PHP_EOL . $c->info('Dropping search triggers to save CPU and lower QPS. (Quick)' . PHP_EOL);
	dropSearchTriggers($pdo);

	echo $c->info('Truncating releasesearch table to free up memory pools/buffers.  (Quick)' . PHP_EOL);
	$pdo->queryExec('TRUNCATE TABLE releasesearch');

	echo $c->header('Optimization for Sphinx process complete!' . PHP_EOL);
}

//Revert database to standard schema
function revertToStandard($pdo)
{
	$engFormat = '';
	$pdo = new DB();
	$c = new ColorCLI();

	if (isset($argv[2]) && in_array($argv[2], ['cinnodb', 'dinnodb', 'cmyisam', 'dmyisam'])) {

		switch ($argv[2]) {
			case 'cinnnodb':
				$engFormat = 'ENGINE = InnoDB ROW_FORMAT = Compressed';
				break;
			case 'dinnodb':
				$engFormat = 'ENGINE = InnoDB ROW_FORMAT = Dynamic';
				break;
			case 'cmyisam':
				$engFormat = 'ENGINE = MyISAM ROW_FORMAT = Compressed';
				break;
			case 'dmyisam':
				$engFormat = 'ENGINE = MyISAM ROW_FORMAT = Dynamic';
				break;
		}
	}

	echo PHP_EOL . $c->info('Dropping old table data and recreating fresh from schema. (Quick)' . PHP_EOL);
	$pdo->queryExec('DROP TABLE IF EXISTS releasesearch');
	$pdo->queryExec(
		sprintf("
				CREATE TABLE releasesearch (
					ID INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					releaseID INT(11) UNSIGNED NOT NULL,
					guid VARCHAR(50) NOT NULL,
					name VARCHAR(255) NOT NULL DEFAULT '',
					searchname VARCHAR(255) NOT NULL DEFAULT '',
					PRIMARY KEY (ID),
					FULLTEXT INDEX ix_releasesearch_name_searchname_ft (name, searchname),
					INDEX ix_releasesearch_releaseID (releaseID),
					INDEX ix_releasesearch_guid (guid)
				)
				%s
				DEFAULT CHARSET = utf8
				COLLATE = utf8_unicode_ci
				AUTO_INCREMENT = 1",
			$engFormat
		)
	);

	echo $c->info('Populating the releasearch table with initial data. (Slow)' . PHP_EOL);
	$pdo->queryInsert('INSERT INTO releasesearch (releaseID, guid, name, searchname)
				SELECT ID, guid, name, searchname FROM releases'
	);

	echo $c->info('Adding the auto-population triggers. (Quick)' . PHP_EOL);

	dropSearchTriggers($pdo);

	$pdo->exec('
				CREATE TRIGGER insert_search AFTER INSERT ON releases FOR EACH ROW
					BEGIN
						INSERT INTO releasesearch (releaseID, guid, name, searchname)
						VALUES (NEW.id, NEW.guid, NEW.name, NEW.searchname);
					END;

				CREATE TRIGGER update_search AFTER UPDATE ON releases FOR EACH ROW
					BEGIN
						IF NEW.guid != OLD.guid
						THEN UPDATE releasesearch
							SET guid = NEW.guid
							WHERE releaseID = OLD.ID;
						END IF;
						IF NEW.name != OLD.name
						THEN UPDATE releasesearch
							SET name = NEW.name
							WHERE releaseID = OLD.ID;
						END IF;
						IF NEW.searchname != OLD.searchname
						THEN UPDATE releasesearch
							SET searchname = NEW.searchname
							WHERE releaseID = OLD.ID;
						END IF;
					END;

				CREATE TRIGGER delete_search AFTER DELETE ON releases FOR EACH ROW
					BEGIN
						DELETE FROM releasesearch
						WHERE releaseID = OLD.ID;
					END;'
	);
	echo $c->header('Standard search should once again be available.' . PHP_EOL);
}

//Drops existing triggers
function dropSearchTriggers($pdo)
{
	$pdo->queryExec('DROP TRIGGER IF EXISTS insert_search');
	$pdo->queryExec('DROP TRIGGER IF EXISTS update_search');
	$pdo->queryExec('DROP TRIGGER IF EXISTS delete_search');
}
