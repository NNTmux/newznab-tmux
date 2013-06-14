<?php
/**
 * Fix mallformed android releases
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
 * Remove releases which do not match black or whitelists
 */
class fix_android_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Fix malformed android releases';
    
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
        // Get a list of the active groups with theire regexes
        $releases = $this->getReleases();
        foreach( $releases AS $release )
        {
            $this->fixRelease( $release );
        }
        
        // No releases to fix
        if( false === $this->fixed )
        {
            $this->display( "No android releases to fix". PHP_EOL );
        }
    }
    
    
    /**
     * Get all the releases to fix
     * 
     * @return array
     */
    protected function getReleases()
    {
        $sql = "SELECT r.ID, r.name,
                REPLACE(rf.name, '.apk', '') AS filename
                FROM releases r
                LEFT JOIN releasefiles rf ON (rf.releaseID = r.ID)
                WHERE r.name REGEXP '^[v]?[0-9]+([\\s\\.][0-9]+)+(-(Game|Pro))?-AnDrOiD$'";
        if( null !== $this->settings['limit'] )
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
        
        // Build the check regex
        $regex = sprintf( '/%s$/i',preg_quote( $release['name'] ) );
        if( preg_match( $regex, $release['filename'] ) )
        {
            // Update
            $this->fixed = true;
            $this->display( sprintf( 'Fixing release: %s'. PHP_EOL, $release['filename'] ) );
            
            $updateSql = sprintf(
                'UPDATE releases r SET r.name = %s, r.searchname = %s WHERE r.id = %d',
                $this->db->escapeString( str_replace( '.', ' ', $release['filename'] ) ),
                $this->db->escapeString( $release['filename'] ),
                (int)$release['ID']
            );
            $this->db->query( $updateSql );
        }
    }
}

// Main application
try
{
    // Init
    $sphinx = new Sphinx();

    // Load the blacklistReleases class
    $blr = new fix_android_releases();
    $blr->fix();
}
