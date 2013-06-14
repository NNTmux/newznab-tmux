<?php
/**
 * Fix CORE releases named "Keymaker Windows-CORE" in the category "PC > 0day"
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.1 - Initial version
 */
//----------------------------------------------------------------------
// Load the application
require_once(dirname(__FILE__)."/../bin/config.php");

// nnscripts includes
require_once("lib/nnscripts.php");

// Sphinx library
require_once(WWW_DIR ."/lib/sphinx.php");


/**
 * Fix CORE releases
 */
class fix_core_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Fix CORE releases in "PC >0day"';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.1';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display', 'limit');

    /**
     * The releases object
     * @var Releases
     */
    private $releases;
    
    /**
     * Is there a releases fixed?
     * @var bool
     */
    private $fixed = false;




    /**
     * The constructor
     *
     */
    public function __construct()
    {
        // Call the parent constructor
        parent::__construct();
        
        // Set the commandline options
        $options = array();
        $this->setCliOptions( $options, array('limit', 'display', 'help') );

        // Show the header
        $this->displayHeader();

        // Show the settings
        $this->displaySettings();
    }


    /**
     * Fix android releases
     * 
     * @return void
     */
    public function fix()
    {
        // Get the releases to check
        $releases = $this->getReleases();
        foreach( $releases AS $release )
        {
            $this->fixRelease( $release );
        }
        
        // No releases to fix
        if( false === $this->fixed )
        {
            $this->display( "No CORE releases to fix". PHP_EOL );
        }
    }
    
    
    /**
     * Get all the releases to fix
     * 
     * @return array
     */
    protected function getReleases()
    {
        $sql = "SELECT r.ID, r.name, uncompress(rn.nfo) AS nfo
                FROM releases r
                INNER JOIN releasenfo rn ON (rn.releaseID = r.ID)
                WHERE r.searchname = ' Keymaker Windows-CORE'
                AND r.categoryID = 4010";
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $sql .= sprintf( ' AND r.adddate >= "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] );
        }                     
        $releases = $this->db->query( $sql );
        if( is_array( $releases ) && 0 < count( $releases ) )
        {
            return $releases;
        }
        return array();
    }
    
            
    /**
     * Try to fix a single release
     * 
     * @return void
     */
    protected function fixRelease( $release )
    {
        // Init
        $this->fixed = false;

	// Loop all lines to find the one matching the softwarename
	$regex = '/[\s]([a-z0-9\.\s\+_-])*?\*INCL\.KEYMAKER\*/i';
        foreach( explode("\n", $release['nfo']) AS $line )
        {
            if( preg_match( $regex, $line, $matches ) )
            {
                // Update
                $this->fixed = true;

                $title = trim( $matches[0] ) .' - CORE';
                $this->display( sprintf( 'Fixing release: %s'. PHP_EOL, $title ) );

                $updateSql = sprintf(
                    'UPDATE releases r SET r.name = %s, r.searchname = %s WHERE r.id = %d',
                    $this->db->escapeString( str_replace( '.', ' ', $title ) ),
                    $this->db->escapeString( $title ),
                    (int)$release['ID']
                );  
                $this->db->query( $updateSql );
            }
        }
    }
}

// Main application
try
{
    // Init
    $sphinx = new Sphinx();

    // Load the fix_core_releases class
    $fcr = new fix_core_releases();
    $fcr->fix();
	
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
