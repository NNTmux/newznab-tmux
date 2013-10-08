<?php
/**
 * This script removes:
 *  - parts which do not have a binary matched
 *  - parts where the binary matched is missing
 *  - binaries which do not have a part matched
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.4 - Added remove of orphan binary records
 * 
 * 0.3 - Added settings.ini
 *       Start using nnscript library
 * 
 * 0.2 - Change retrieval of parts in7groups of 10000
 * 
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Load the application
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR. "lib/framework/db.php");

// nnscripts includes
require_once("lib/nnscripts.php");


/**
 * Remove parts which are not linked to a binary
 */
class remove_parts_without_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Remove parts which are not linked to a binary';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.4';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display', 'limit', 'querylimit');

    
    /**
     * The constructor
     *
     */
    public function __construct()
    {
        // Call the parent constructor
        parent::__construct();
        
        // Set the commandline options
        $options = array(
            array( null, 'querylimit',  Ulrichsg\Getopt::REQUIRED_ARGUMENT, 'The query limit (default 10000)' ),
        );
        $this->setCliOptions( $options, array('display', 'limit', 'help') );
        
        // Show the header
        $this->displayHeader();

        // Show the settings
        $this->displaySettings();
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
        
        // Remove binaries without parts
        $this->removeBinariesWithoutParts();
    }
    
    
    /**
     * Remove parts without a binary linked
     * 
     * @return void
     */
    private function removePartsWithoutBinary()
    {
        $this->display( "Removing old part records which are not linked to a binary: " );
        
        // Build the sql
        $sql = "DELETE FROM parts
                WHERE binaryID = 0";
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $sql .= sprintf( ' AND dateadded < "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] );
        }
        $result = $this->db->queryDirect( $sql );
        
        $this->display( ( $result ? "Done" : "Error" ) . PHP_EOL );
    }
    
    
    /**
     * Remove parts where the binary is missing
     * 
     * @return void
     */
    private function removePartsWithMissingBinary()
    {
        // Get the total parts to remove
        $total = $this->getTotalPartsToRemove();
        if( 0 < $total )
        {
            $line = sprintf( "Removing %s parts with missing binaries", number_format($total, 0, '.', '') );
            if( $total > $this->settings['querylimit'] )
            {
                $line .= sprintf( ' (in groups of %d parts)', $this->settings['querylimit'] );
            }
            $line .= ': ';
            $this->display( $line );
            
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
                    (
                        ( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
                        ? sprintf( ' AND p.dateadded < "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] )
                        : ''
                    ),
                    $this->settings['querylimit']
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
                        if( $this->settings['querylimit'] === $counter )
                        {
                            $this->display( "." );
                            $sql = sprintf( "DELETE FROM parts WHERE ID IN (%s)", implode(',', $ids) );
                            $this->db->queryDirect( $sql );
                            $ids = array();
                            $counter = 0;
                        }
                    }

                    // Cleanup
                    unset( $result );
                    
                    // Lower the total
                    $total -= $this->settings['querylimit'];
                }
                else
                {
                    // Query without result?
                    $total = 0; 
                }
            }
            
            // And remove the rest
            if( is_array($ids) && 0 < count($ids) )
            {
                $this->display( "." );
                $sql = sprintf( "DELETE FROM parts WHERE ID IN (%s)", implode(',', $ids) );
                $this->db->queryDirect( $sql );
            }
            
            $this->display( PHP_EOL );
        }
        else
        {
            $this->display( 'No parts with missing binaries to remove.'. PHP_EOL );
        }
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
        $sql = "SELECT count(*) AS total
                FROM parts AS p
                LEFT JOIN binaries AS b ON (b.ID = p.binaryID)
                WHERE b.ID IS NULL";
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $sql .= sprintf( ' AND p.dateadded < "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] );
        }
        
        $result = $this->db->queryOneRow( $sql );
        if( is_array($result) && array_key_exists('total', $result) )
        {
            $ret = (int)$result['total'];
        }

        // Return
        return $ret;
    }
    
    
    /**
     * Remove orhpan binary records
     * 
     * @return void
     */
    protected function removeBinariesWithoutParts()
    {
        $this->display( "Removing old binary records which do not have parts: " );
        
        $sql = "DELETE binaries FROM binaries
                LEFT JOIN parts ON (parts.binaryID = binaries.ID)
                WHERE parts.ID IS NULL";
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $sql .= sprintf( ' AND binaries.dateadded < "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] );
        }
        $result = $this->db->queryDirect( $sql );
        
        $this->display( ( $result ? "Done" : "Error" ) . PHP_EOL );
    }
}

// Main application
try
{
    // Load the remove_parts_without_releases class
    $rpwr = new remove_parts_without_releases();
    $rpwr->cleanup();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
