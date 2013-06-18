<?php
/**
 * Fix mallformed PRoDJi movie releases
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
 * Fix PRoDJi releases
 */
class fix_prodji_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Fix PRoDJi releases';
    
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
            $this->display( "No PRoDJi releases to fix". PHP_EOL );
        }
    }
    
    
    /**
     * Get all the releases to fix
     * 
     * @return array
     */
    protected function getReleases()
    {
        $sql = "SELECT r.ID, r.name, rf.name AS filename
                FROM `releases` r
                INNER JOIN `category` c ON (c.id = r.categoryID)
                INNER JOIN `releasefiles` rf ON (rf.releaseID = r.ID)
                WHERE r.name REGEXP '^HD[0-9]{5}.*PRoDJi(\.Dual)?$'
                AND c.parentID = '2000'
                AND rf.name REGEXP '\.mkv$'";
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $sql .= sprintf( ' AND r.adddate >= "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] );
        }                     
        $sql .= " GROUP BY r.ID";

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
        $newName = substr( $release['filename'], 0, -4 );
        if( false !== $newName )
        {
            // Fix name
            if( preg_match( '/[0-9]{4}[\s\.]BluRay/', $newName ) )
            {
                $pattern = '/([0-9]{4})([\s\.])BluRay/';
                $replacement = '(${1})${2}BluRay';
                $newName = preg_replace( $pattern, $replacement, $newName );
            }

            // Update
            $this->fixed = true;
            $this->display( sprintf( 'Renaming release: [%s] to [%s]'. PHP_EOL, $release['name'], str_replace( '.', ' ', $newName ) ) );
            
            $updateSql = sprintf(
                'UPDATE releases r SET r.name = %s, r.searchname = %s WHERE r.id = %d',
                $this->db->escapeString( str_replace( '.', ' ', $newName ) ),
                $this->db->escapeString( $newName ),
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
    $fpr = new fix_prodji_releases();
    $fpr->fix();

} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
