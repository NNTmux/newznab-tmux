<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$sphinxConnection = '';
if ($argc === 3 && is_numeric($argv[2])) {
    $sphinxConnection = sprintf('sphinx://%s:%d/', $argv[1], $argv[2]);
} elseif ($argc === 2) {
    // Checks that argv[1] exists AND that there are no other arguments, which would be an error.
    $socket = preg_replace('#^(?:unix://)?(.*)$#', '$1', $argv[1]);
    if (str_starts_with($socket, '/')) {
        // Make sure the socket path is fully qualified (and using correct separator).
        $sphinxConnection = sprintf('unix://%s:', $socket);
    }
} else {
    exit("Argument 1 must the hostname or IP to the Sphinx searchd server ('sphinx' protocol), Argument 2 must be the port to the Sphinx searchd server ('sphinx' protocol) (the default is 9312).\nAlternatively, Argument 1 can be a unix domain socket.".PHP_EOL);
}

$tableSQL_releases = <<<'DDLSQL'
CREATE TABLE releases_se
(
	id          BIGINT UNSIGNED NOT NULL,
	weight      INTEGER NOT NULL,
	query       VARCHAR(1024) NOT NULL,
	name        VARCHAR(255) NOT NULL DEFAULT '',
	searchname  VARCHAR(255) NOT NULL DEFAULT '',
	fromname    VARCHAR(255) NULL,
	categories_id   INT UNSIGNED NOT NULL DEFAULT 10,
	filename    VARCHAR(1000) NULL,
	INDEX(query)
) ENGINE=SPHINX CONNECTION="%sreleases_rt"
DDLSQL;

$tables = [];
$tables['releases_se'] = sprintf($tableSQL_releases, $sphinxConnection);

foreach ($tables as $table => $query) {
    DB::statement("DROP TABLE IF EXISTS $table;");
    DB::statement($query);
}

$tableSQL_predb = <<<'DDLSQL'
CREATE TABLE predb_se
(
	id          BIGINT UNSIGNED NOT NULL,
	weight      INTEGER NOT NULL,
	query       VARCHAR(1024) NOT NULL,
	title       VARCHAR(255) NOT NULL DEFAULT '',
	source      VARCHAR(255) NOT NULL,
	filename    VARCHAR(1000) NULL,
	INDEX(query)
) ENGINE=SPHINX CONNECTION="%spredb_rt"
DDLSQL;

$tables2 = [];
$tables2['predb_se'] = sprintf($tableSQL_predb, $sphinxConnection);

foreach ($tables2 as $table => $query) {
    DB::statement("DROP TABLE IF EXISTS $table;");
    DB::statement($query);
}

echo 'All done! If you messed up your manticore search connection info, you can rerun this script.'.PHP_EOL;
