<?php
    require("config.php");
    require_once(WWW_DIR . "/lib/framework/cache.php");
    require_once(WWW_DIR . "/lib/framework/db.php");
              
            function quantity($force = false)
            {
                    $db = new DB;
                    $ret = array();
                    if ($force)
                            $alltables = $db->query("SELECT table_schema, table_name, data_free/1024/1024 AS data_free_MB FROM information_schema.tables WHERE engine LIKE 'InnoDB' AND data_free < 35*1024*1024");
                    else
                            $alltables = $db->query("SELECT table_schema, table_name, data_free/1024/1024 AS data_free_MB FROM information_schema.tables WHERE engine LIKE 'InnoDB' AND data_free != 0*1024*1024 AND data_free < 35*1024*1024");
 
                    foreach ($alltables as $tablename)
                    {
                            $ret[] = $tablename['table_name'];
                    }
              
                    return $ret;
            }
 
            function ioptimise($force = false)
            {
                    $db = new DB;
                    $ret = array();
                    if ($force)
                            $alltables = $db->query("SELECT table_schema, table_name, data_free/1024/1024 AS data_free_MB FROM information_schema.tables WHERE engine LIKE 'InnoDB' AND data_free > 30*1024*1024");
                    else
                            $alltables = $db->query("SELECT table_schema, table_name, data_free/1024/1024 AS data_free_MB FROM information_schema.tables WHERE engine LIKE 'InnoDB' AND data_free != 0*1024*1024 AND data_free < 30*1024*1024");
              
                    foreach ($alltables as $tablename)
                    {
                            $ret[] = $tablename['table_name'];
                            $db->queryDirect("OPTIMIZE TABLE `" . $tablename['table_name'] . "`");
                    }
              
                    return $ret;
            }
 
            $force = ((isset($argv[1]) && ($argv[1] == "true")));
            $db = new DB;
            echo "\033[1;41;33mOptmze  : OPTIMIZATION OF THE INNODB MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;33m\n\n";
            $qret = quantity($force);
            echo "Optmze  : Going to start optimizing ".count($qret)." InnoDB tables (if you have InnoDB tables).\n";
            $ret = ioptimise($force);
            echo "Optmze  : Finished optimizing ".count($ret)." InnoDB tables.\n";
              
            if (count($ret) > 0)
            {
                    echo "Optmze  : Optimization completed.\033[1;37m\n";
            }
            else
            {
                    echo "Optmze  : Nothing requires optimization.".(!$force ? " Try using force (optimise_inno.php true)" : "")."\033[1;37m\n";
}
