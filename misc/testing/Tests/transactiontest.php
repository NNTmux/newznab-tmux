<?php
use nntmux\db\DB;

try {
    // Create the first database class instance (which will initialize the PDO connection)
    $db = new DB();

    // For debug set the error mode
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Start the transaction
    if( $db->beginTransaction() )
    {
        // Loop 20 times
        for($i=1 ; $i<=20 ; $i++ )
        {
            // Header
            echo "--[ Insert run: {$i} ]----------------------------------------". PHP_EOL;

            // Create a new db class instance (multiple can exist)
            $newDb = new DB();

            // Check that there is a new db class instance, but no new PDO instance
            var_dump( $newDb, $newDb->getPDO() );

            // Insert some data
            $sql = sprintf("
                INSERT INTO `testdata`
                (`id` ,`field1` ,`field2` ,`field3` ,`field4`)
                VALUES
                ('%s', '%s', '%s', '%s', '%s')", $i, $i, $i, $i, $i
            );
            $newDb->exec( $sql );

            // Check for inserted data
            var_dump( $newDb->query( sprintf( "SELECT * FROM `testdata` WHERE id = %d", $i ) ) );
        }

        // Now rollback using the last db class instance
        var_dump( $newDb->rollback() );
    }
} catch(PDOException $e) {
    var_dump( $e );
}
