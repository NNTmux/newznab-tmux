<?php
require_once dirname(__FILE__) . '/../../www/config.php';


$db = new \newznab\db\DB();

//query to find rough matches
$rows = $db->query("SELECT ID, searchname from releases where searchname like '%QWERTY%'");

//loop around them applying some ham fisted regex
foreach ($rows as $row)
{
    $reg = '/^(\[QWERTY\] ")(?P<name>.*?(xvid|x264)\-.*?)"/i';
    preg_match($reg, $row["searchname"], $matches);
    if (isset($matches["name"])) {
        $db->exec(sprintf("update releases set searchname = %s, name = %s where ID = %d",
                $db->escapeString($matches["name"]), $db->escapeString($matches["name"]), $row["id"]));
        echo $matches["name"]."\n";
    }
}
