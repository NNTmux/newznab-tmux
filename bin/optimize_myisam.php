<?php
    require("config.php");
    require_once(WWW_DIR . "/lib/framework/cache.php");
    require_once(WWW_DIR . "/lib/framework/db.php");
 
            function quantity($force = false)
            {
                    $db = new DB;
                    $ret = array();
                    if ($force)
                            $alltables = $db->query("show table status where `Engine` = 'MyISAM' and Data_free > 30000000");
                    else
                            $alltables = $db->query("show table status where `Engine` = 'MyISAM' and Data_free != 0 and Data_free < 30000001");
 
                    foreach ($alltables as $tablename)
                    {
                            $ret[] = $tablename['Name'];
                    }
              
                    return $ret;
            }
 
            function moptimise($force = false)
            {
                    $db = new DB;
                    $ret = array();
                    if ($force)
                            $alltables = $db->query("show table status where `Engine` = 'MyISAM' and Data_free > 30000000");
                    else
                            $alltables = $db->query("show table status where `Engine` = 'MyISAM' and Data_free != 0 and Data_free < 30000001");
 
                    foreach ($alltables as $tablename)
                    {
                            $ret[] = $tablename['Name'];
                            $db->queryDirect("REPAIR TABLE `" . $tablename['Name'] . "`");
                            $db->queryDirect("OPTIMIZE TABLE `" . $tablename['Name'] . "`");
                            $db->queryDirect("ANALYZE TABLE `" . $tablename['Name'] . "`");
                    }
              
                    return $ret;
            }
             
            $force = ((isset($argv[1]) && ($argv[1] == "true")));  
            $db = new DB;
            echo "\033[1;41;33mOptmze  : OPTIMIZATION OF THE MYISAM MYSQL TABLES HAS STARTED, DO NOT STOP THIS SCRIPT!\033[1;0;33m\n\n";
            $qret = quantity($force);
            echo "Optmze  : Going to start optimizing ".count($qret)." MyISAM tables (if you have MyISAM tables).\n";
            $ret = moptimise($force);
            echo "Optmze  : Finished optimizing ".count($ret)." MyISAM tables.\n";
 
            if (count($ret) > 0)
            {
                    echo "Optmze  : Optimization completed.\033[1;37m\n";
            }
            else
            {
                    echo "Optmze  : Nothing requires optimization.".(!$force ? " Try using force (optimise_myisam.php true)" : "")."\033[1;37m\n";
}
