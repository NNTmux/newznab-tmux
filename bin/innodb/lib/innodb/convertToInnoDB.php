<?php
$newzpath = getenv('NEWZPATH');
require_once("$newzpath/www/config.php");
require_once(WWW_DIR."/lib/framework/db.php");

$db = new DB();

echo "This script will convert only your parts and binaries table to InnoDB with compressed row format..\n";
echo "This may take a while depending on how large your tables are. Do not stop this script. It will finish.\n\n";

echo "Converting the binaries table to InnoDB with compressed row format.\n";
$db->query("ALTER TABLE binaries ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;");
echo "Finished converting the binaries table to InnoDB with compressed row format..\n\n";

echo "Converting the parts table to InnoDB with compressed row format.\n";
$db->query("ALTER TABLE parts ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;");
echo "Finished converting the parts table to InnoDB with compressed row format..\n\n";

?>
