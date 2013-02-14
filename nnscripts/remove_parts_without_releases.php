<?php
/**
 * This parts which do not have a binary matched. 
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 30 to 32 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.1  - Initial version
 */

//----------------------------------------------------------------------
// Settings

// Display settings
define('DISPLAY', true);

// Time limit on releases to remove in hours
// example: 24 for 1 day old releases (based on add date)
// false or 0 to disable
define('LIMIT', 48);
//----------------------------------------------------------------------

// Load the application
require_once("config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once('nnscripts.php');


/**
 * Remove parts which are not linked to a binary
 */
class removeParts
{
    /**
     * NNScripts class
     * @var NNScripts
     */
    private $nnscripts;     
    
    /**
     * The database instance
     * @db DB
     */
    private $db;



    /**
     * Constructor
     * 
     * @param NNScripts $nnscripts
     * @param DB $db
     */
    public function __construct( NNScripts $nnscripts, DB $db )
    {
        // Set the NNScripts variable
        $this->nnscripts = $nnscripts;
        
        // Set the database variable
        $this->db = $db;
    }
    
    
    /**
     * Remove all the parts which are not linked to a binary
     * 
     * @return void
     */
    public function cleanup()
    {
        // Remove old parts without binaries        
        $this->removePartsWithoutBinary();
        
        // Remove parts where the binary is missing
        $this->removePartsWithMissingBinary();
    }


    /**
     * Remove parts without a binary linked
     * 
     * @return void
     */
    private function removePartsWithoutBinary()
    {
        $this->nnscripts->display( "Removing old part records which are not linked to a binary: " );
        
        // Build the sql
        $sql = sprintf(
            "DELETE FROM parts
             WHERE parts.binaryID = 0
             %s",
            ( ( defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT ) ? sprintf("AND parts.dateadded < NOW() - INTERVAL %d HOUR", LIMIT) : '' )
        );
        $result = $this->db->queryDirect( $sql );
        $this->nnscripts->display( ( $result ? "Done" : "Error" ) . PHP_EOL );
    }


    /**
     * Remove parts where the binary is missing
     * 
     * @return void
     */
    private function removePartsWithMissingBinary()
    {
        // Remove parts with missing binary
        $sql = sprintf(
            "SELECT DISTINCT p.ID
             FROM parts AS p
             LEFT JOIN binaries AS b ON (b.ID = p.binaryID)
             WHERE b.ID IS NULL
             %s",
            ( ( defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT ) ? sprintf("AND p.dateadded < NOW() - INTERVAL %d HOUR", LIMIT) : '' )
        );
        $result = $this->db->query( $sql );
        if( is_array( $result ) && 0 < count( $result ) )
        {
            $this->nnscripts->display( sprintf( "Removing %d parts with missing binaries: ", count( $result ) ) );

            // Build the total ID list
            $ids = array();
            $counter = 0;
            foreach( $result AS $row )
            {
                $ids[] = $row['ID'];
                $counter++;

                if( 100 === $counter )
                {
                    $this->nnscripts->display( "." );
                    $sql = sprintf( "DELETE FROM parts WHERE ID IN (%s)", implode(',', $ids) );
                    $this->db->queryDirect( $sql );
                    $ids = array();
                    $counter = 0;
                }
            }
            
            // And remove the rest
            if( 0 < count($ids) )
            {
                $this->nnscripts->display( "." );
                $sql = sprintf( "DELETE FROM parts WHERE ID IN (%s)", implode(',', $ids) );
                $this->db->queryDirect( $sql );
            }
            
            $this->nnscripts->display( PHP_EOL );
        }
    }
}

try
{
    // Init
    $scriptName    = 'Remove parts which are not linked to a binary';
    $scriptVersion = '0.1';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();

    // Load the application part
    $db = new DB;
    if( !$db )
        throw new Exception("Error loading database library");
        
    // Load the categoryReleases class
    $cr = new removeParts( $nnscripts, $db );
    $cr->cleanup();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
