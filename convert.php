<?php
require_once("/var/www/newznab/www/config.php");
require_once("/var/www/newznab/www/lib/framework/db.php");

$db = new DB();

echo "This script will convert only your releases, parts and binaries table back to InnoDB.\n";
echo "This may take a while depending on how large your tables are. Do not stop this script. It will finish.\n\n";

echo "Converting the binaries table back to InnoDB\n";
$db->query("ALTER TABLE binaries ENGINE=InnoDB ROW_FORMAT=DYNAMIC;");
echo "Finished converting the binaries table back to InnoDB.\n\n";

echo "Converting the parts table back to InnoDB\n";
$db->query("ALTER TABLE parts ENGINE=InnoDB ROW_FORMAT=DYNAMIC;");
echo "Finished converting the parts table back to InnoDB.\n\n";

echo "Converting the releases table back to InnoDB\n";
$db->query("ALTER TABLE releases ENGINE=InnoDB ROW_FORMAT=DYNAMIC;");
echo "Finished converting the releases table back to InnoDB.\n\n";

?>
