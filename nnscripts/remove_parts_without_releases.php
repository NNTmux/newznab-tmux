<?php
/**
 * This parts which do not have a binary matched. 
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 34 to 36 to require the correct files.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.2 - Change retrieval of parts in groups of 10000
 * 0.1 - Initial version
 */

//----------------------------------------------------------------------
// Settings

// Display settings
define('DISPLAY', true);

// Time limit on releases to remove in hours
// example: 24 for 1 day old releases (based on add date)
// false or 0 to disable
define('LIMIT', 48);

// The number of parts that should be grouped when removing
define('QUERYLIMIT', 10000);
//----------------------------------------------------------------------

// Load the application
require(dirname(__FILE__)."/../bin/config.php");
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
     * The current date and time
     * @var DateTime
     */
    private $now;



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

        // Add the "now" date
        $this->now = new DateTime();
        if( defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT )
        {
            // substract the limit hours
            $this->now->sub( new DateInterval('PT'. LIMIT .'H') );
        }
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
            ( ( defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT ) ? sprintf("AND parts.dateadded < '%s'", $this->now->format('Y-m-d H:i:s')) : '' )
        );
        $result = $this->db->queryDirect( $sql );
        $this->nnscripts->display( ( $result ? "Done" : "Error" ) . PHP_EOL );
    }


    /**
     * Get the total count of parts to remove
     * 
     * @return int
     */
    protected function getTotalPartsToRemove()
    {
        // Init
        $ret = 0;

        // Get the total to remove
        $sql = sprintf("SELECT count(*) AS total
                        FROM parts AS p
                        LEFT JOIN binaries AS b ON (b.ID = p.binaryID)
                        WHERE b.ID IS NULL
                        %s",
                        ( ( defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT ) ? sprintf("AND p.dateadded < '%s'", $this->now->format('Y-m-d H:i:s')) : '' ) );
        $result = $this->db->query( $sql );
        if( is_array($result) && 1 === count($result) )
        {
            $ret = $result[0]['total'];
        }

        // Return
        return $ret;
    }
    

    /**
     * Remove parts where the binary is missing
     * 
     * @return void
     */
    private function removePartsWithMissingBinary()
    {
        // The query limit
        $queryLimit = ( defined('QUERYLIMIT') && is_int(QUERYLIMIT) && 0 < QUERYLIMIT ? QUERYLIMIT : 10000 );
        
        // Get the total parts to remove
        $total = $this->getTotalPartsToRemove();
        if( 0 < $total )
        {
            $line = sprintf( "Removing %s parts with missing binaries", number_format($total, 0, '.', '') );
            if( $total > $queryLimit )
                $line .= sprintf( ' (in groups of %d parts)', $queryLimit );
            $line .= ': ';
            $this->nnscripts->display( $line );
            
            // Set the lastID
            $lastId = null;
            $ids = array();
            
            // Loop until there are no parts to remove
            while( 0 < $total )
            {
                // Build the query
                $sql = sprintf("SELECT DISTINCT p.ID
                                FROM parts AS p
                                LEFT JOIN binaries AS b ON (b.ID = p.binaryID)
                                WHERE b.ID IS NULL
                                %s
                                %s
                                ORDER BY p.ID ASC
                                LIMIT %d",
                    ( null !== $lastId ? sprintf('AND p.ID > %d', $lastId) : '' ),
                    ( ( defined('LIMIT') && is_int(LIMIT) && 0 < LIMIT ) ? sprintf("AND p.dateadded < '%s'", $this->now->format('Y-m-d H:i:s')) : '' ),
                    $queryLimit
                );
                $result = $this->db->query( $sql );
                if( is_array( $result ) && 0 < count( $result ) )
                {
                    // Build the total ID list
                    $ids = array();
                    $counter = 0;
                    foreach( $result AS $row )
                    {
                        $ids[] = $lastId = $row['ID'];
                        $counter++;
                        if( $queryLimit === $counter )
                        {
                            $this->nnscripts->display( "." );
                            $sql = sprintf( "DELETE FROM parts WHERE ID IN (%s)", implode(',', $ids) );
                            $this->db->queryDirect( $sql );
                            $ids = array();
                            $counter = 0;
                        }
                    }

                    // Cleanup
                    unset( $result );
                    
                    // Lower the total
                    $total -= $queryLimit;
                }
                else
                {
                    // Query without result?
                    $total = 0; 
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
        else
        {
            $this->nnscripts->display( 'No parts with missing binaries to remove.'. PHP_EOL );
        }
    }
}

try
{
    // Init
    $scriptName    = 'Remove parts which are not linked to a binary';
    $scriptVersion = '0.2';
    
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

