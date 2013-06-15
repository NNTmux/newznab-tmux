<?php
/**
 * This script removes releases which do not pass the black/whitelist
 * filters. 
 * 
 * Releases could be renamed with scripts like "update_parsing". When
 * this happens, the new release name may not pass the black/whitelist
 * filters, but they are not removed. This script can remove all
 * releases which do not pass the filters.
 * 
 * Warning: the script does not remove releases by default.
 * If you want to remove the releases, change the settings.ini file
 * or use the commandline parameters
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.6 - Added limit from site config
 *       changes in libraries
 *       Added settings.ini
 *       Start using nnscript library
 *
 * 0.5 - Added the option to check all releases against regexes
 *       The option "-f" must be provided (commandline) to scan the 
 *       full database (Warning: This may take some time)
 * 
 *       Added the option to remove releases only in one group
 * 
 *       Moved the nnscript libarary to the lib directory
 *       
 *       Added the 3th party Getopt library
 *       (https://github.com/ulrichsg/getopt-php)
 * 
 * 0.4 - Show first black/whitelist id with the release when removing
 * 
 * 0.3 - Moved checking of regexes to php.
 *       Mysql regexes are not compatible with php's preg regexes.
 *
 *       Some regexes cannot be converted, which means that checking
 *       the regexes now get's slower. To prevent overloading your
 *       system (by retrieving all releases) the LIMIT setting now
 *       only checks releases added within the last x hours.
 *       For example, when entering 24 the script will only check
 *       releases added within the last 24 hours.
 *
 * 0.2 - Make script php 5.3 compatible
 *
 * 0.1 - Initial version
 */

//----------------------------------------------------------------------
// Load the application
require_once(dirname(__FILE__)."/../bin/config.php");

// nnscripts includes
require_once("lib/nnscripts.php");

// newznab includes
require_once(WWW_DIR."/lib/releases.php");



/**
 * Remove releases which do not match black or whitelists
 */
class remove_blacklist_releases extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Remove black or whitelisted releases';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.6';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display', 'limit', 'full', 'remove', 'debug');

    /**
     * The releases object
     * @var Releases
     */
    private $releases;

    /**
     * A list of all the active groups
     * @var array
     */
    private $groups;

    /**
     * All the regexes (grouped by group)
     * @var array
     */
    private $regexes;

    /**
     * The last debug message
     * @var string
     */
    private $lastDebug = '';

    /**
     * The known list types
     * @var array
     */
    private $listType = array(
        '1' => 'blacklist',
        '2' => 'whitelist'
    );



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
            array( 'f', 'full',  Ulrichsg\Getopt::NO_ARGUMENT,       'Check all releases (full database)' ),
            array( 'g', 'group', Ulrichsg\Getopt::REQUIRED_ARGUMENT, 'Remove releases only from one group' ),
        );
        $this->setCliOptions( $options, array('limit', 'remove', 'display', 'debug', 'help') );
                
        // Show the header
        $this->displayHeader();                
                
        // Show the settings
        $this->displaySettings();

        // Load the releases class
        $this->releases = new Releases();
    }


    /**
     * Remove black/white list releases
     */
    public function cleanup()
    {
        // Get a list of the active groups with theire regexes
        $this->buildGroupList();

        // Build the regex list
        $this->buildGroupRegexes();

        // Check full database or normal (active/one group)
        if( true === $this->settings['full'] )
        {
            // Check the full database
            $this->checkFullDatabase();
        }
        else
        {
            // Check all groups or a single group
            $this->checkGroups();
        }
    }


    /**
     * Build a list of all the active groups with there regexes
     *
     * @return void
     */
    protected function buildGroupList()
    {
        // Init
        $sql = "SELECT g.ID, g.name
                FROM groups AS g
                WHERE %s
                ORDER BY g.name ASC";

        // One or all active groups
        $group = $this->options->getOption('group');
        $full  = $this->options->getOption('full');
        if( null === $group || null !== $full )
        {
            // Check all groups
            $sql = sprintf( $sql, "g.active = 1" );
        }
        else
        {
            // Check one group
            $group = preg_replace('/^a\.b\./i', 'alt.binaries.', $group);
            $sql = sprintf( $sql, sprintf("g.name = %s", $this->db->escapeString( $group ) ) );
        }

        // Get the groups
        $groups = $this->db->query( $sql );
        if( is_array( $groups ) && 0 < count( $groups ) )
        {
            // Loop all the active groups and gather the regexes.
            foreach( $groups AS $group )
            {
                // Get the regexes
                //$group['regexes'] = $this->getGroupRegexes( $group );
                $this->groups[ $group['name'] ] = (int)$group['ID'];
            }
        }
    }


    /**
     * Get all the black/whitelist regexes for a group
     *
     * @internal param array $group
     * @return array
     */
    protected function buildGroupRegexes()
    {
        // Init
        $ret = array(
            'alt.binaries.*' => array()     // Make sure the a.b.* exists
        );

        // Get all the active regexes for a group
        $sql = "SELECT b.ID, b.groupname, b.regex, b.optype
                FROM binaryblacklist AS b
                WHERE b.msgcol = 1
                AND b.status = 1
                ORDER BY b.groupname ASC";
        $dbRegexes = $this->db->query( $sql );
        if( is_array( $dbRegexes ) && 0 < count( $dbRegexes ) )
        {
            // Build the regexes array
            foreach( $dbRegexes AS $row )
            {
                if( !array_key_exists( $row['groupname'], $ret ) )
                {
                    $ret[ $row['groupname'] ] = array();
                }
                $ret[ $row['groupname'] ][ $this->listType[ $row['optype'] ] ][ $row['ID'] ] = $row['regex'];
            }
        }

        // Return
        $this->regexes = $ret;
    }


    /**
     * Check the full database
     *
     * @return void
     */
    protected function checkFullDatabase()
    {
        $total = $this->getTotalReleasesForGroup( null );
        $this->display(
            sprintf(
                'Checking full database (%d release%s):'. PHP_EOL,
                $total,
                ( 1 === $total ? '' : 's')
            )
        );

        // Loop all the releases
        $this->loopReleases( $total );
    }


    /**
     * Check all or one group
     *
     * @return void
     */
    protected function checkGroups()
    {
        // Loop all the groups
        if( is_array( $this->groups ) && 0 < count( $this->groups ) )
        {
            foreach( $this->groups AS $group => $groupId )
            {
                // Count the number of releases (not flooding memory by retreiving all releases at once)
                $total = $this->getTotalReleasesForGroup( $groupId );

                // Init
                $this->display(
                    sprintf(
                        'Checking group %s (%d release%s):'. PHP_EOL,
                        $group,
                        $total,
                        ( 1 === $total ? '' : 's')
                    )
                );

                // Loop all the releases
                $this->loopReleases( $total, $groupId );
            }
        }
        else
        {
            $this->display( "No group to check against". PHP_EOL );
        }
    }


    /**
     * Get the total number of releases for a group.
     *
     * @param int $groupId
     * @return int
     */
    protected function getTotalReleasesForGroup( $groupId=null )
    {
        $ret = 0;

        // Build the sql
        $sql = sprintf(
            "SELECT count(1) AS total
             FROM `releases` r
             WHERE %s",
            ( null !== $groupId ? sprintf( "r.groupID = %d", $groupId ) : '1 = 1' )
        );
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $sql .= sprintf( ' AND r.adddate >= "%s" - INTERVAL %s HOUR', $this->settings['now'], $this->settings['limit'] );
        }
        
        $result = $this->db->queryOneRow( $sql );
        if( is_array($result) && 1 === count($result) )
        {
            $ret = (int)$result['total'];
        }

        // Return
        return $ret;
    }


    /**
     * Loop all the releases
     *
     * @param int $total
     * @param null|int $groupId
     * @return void
     */
    protected function loopReleases( $total, $groupId=null )
    {
        // Retrieve in groups of 100 releases
        $lastId = null;
        $matched = false;
        $full = $this->options->getOption('full');
        while( $total > 0 )
        {
            // Get the releases
            $releases = $this->getReleases( $groupId, $lastId, 100 );
            foreach( $releases AS $release )
            {
                // Process the releases
                $checkResult = $this->checkRelease( $release );
                if( false !== $checkResult )
                {
                    // Match found, removing
                    $matched = true;
                    $this->display( sprintf(
                            ' - %s release: %s (added: %s%s, matches: %d)'. PHP_EOL,
                            ( true === $this->settings['remove'] ? 'Removing' : 'Keeping' ),
                            $release['name'],
                            $release['adddate'],
                            ( (null !== $full && null !== $release['groupname']) ? sprintf(', group: %s', preg_replace('/^alt\.binaries\./', 'a.b.', $release['groupname']) ) : '' ),
                            $checkResult
                        ) );

                    if( true === $this->settings['debug'] && '' !== $this->lastDebug )
                    {
                        $this->display( $this->lastDebug . PHP_EOL );
                    }

                    if( true === $this->settings['remove'] )
                    {
                        $this->releases->delete( $release['ID'] );
                    }
                }

                // Update the lastId
                $lastId = $release['ID'];
            }

            // Done, lower the total count
            $total -= 100;
        }

        // spacer
        if( true === $matched )
        {
            $this->display( PHP_EOL );
        }
    }


    /**
     * Get releases for a group without flooding the memory
     *
     * @param int|null$groupId
     * @param null|int $lastId
     * @param int $limit
     * @return array
     */
    protected function getReleases( $groupId=null, $lastId=null, $limit=100 )
    {
        // Init
        $ret = array();

        // Built the query
        $sql = sprintf(
            "SELECT r.ID, r.name, r.adddate, r.groupID,
                    g.name AS groupname
             FROM `releases` r
             LEFT JOIN groups g ON (g.ID = r.groupID)
             WHERE %s",
            ( null !== $groupId ? sprintf( "r.groupID = %d", $groupId ) : '1 = 1' )
        );

        // Add the lastId
        if( null !== $lastId )
        {
            $sql .= sprintf( " AND r.ID > %d", $lastId );
        }

        // Date limit
        if( is_numeric( $this->settings['limit'] ) && 0 < $this->settings['limit'] )
        {
            $sql .= sprintf( ' AND r.adddate >= "%s" - INTERVAL %d HOUR', $this->settings['now'], $this->settings['limit'] );
        }

        // Add the sorting of the records and the record limit
        $sql .= sprintf(" ORDER BY r.ID ASC
                         LIMIT %d", $limit);

        // Run the query and return the results
        $result = $this->db->query( $sql );
        if( is_array($result) && 0 < count($result) )
        {
            $ret = $result;
        }

        // Return
        return $ret;
    }


    /**
     * Check a release for black/white lists matches
     *
     * @param array $release
     * @return bool
     */
    protected function checkRelease( $release )
    {
        // Remove (init)
        $remove = false;
        $this->lastDebug = '';

        // Groups array
        $groups = array('alt.binaries.*', $release['groupname']);
        foreach( $groups AS $group )
        {
            // Check if the group exists withing the regexes
            if( array_key_exists( $group, $this->regexes ) )
            {
                // Whitelist
                if( array_key_exists('whitelist', $this->regexes[ $group ] ) )
                {
                    if( false === $remove )
                        $this->checkWhitelist( $this->regexes[ $group ]['whitelist'], $release['name'], $remove );
                }

                // Blacklist
                if( array_key_exists('blacklist', $this->regexes[ $group ] ) )
                {
                    if( false === $remove )
                        $this->checkBlacklist( $this->regexes[ $group ]['blacklist'], $release['name'], $remove );
                }
            }
        }

        // Return
        return $remove;
    }


    /**
     * Check whitelist regexes
     *
     * @param array $regexes
     * @param string $releaseName
     * @param bool $remove
     * @void return
     */
    protected function checkWhitelist( array $regexes, $releaseName, &$remove )
    {
        foreach( $regexes AS $id => $regex )
        {
            if( !preg_match('/'. $regex .'/i', $releaseName) )
            {
                // Debug message
                if( true === $this->settings['debug'] )
                {
                    $this->lastDebug  = sprintf( "   DEBUG: Release [%s] does not match whitelist regex:". PHP_EOL, $releaseName );
                    $this->lastDebug .= sprintf( "   DEBUG: Regex: %s". PHP_EOL, $regex );
                }

                // Matches, so remove
                $remove = $id;
                return;
            }
        }
    }


    /**
     * Check blacklist regexes
     *
     * @param array $regexes
     * @param string $releaseName
     * @param bool $remove
     * @void return
     */
    protected function checkBlacklist( array $regexes, $releaseName, &$remove )
    {
        foreach( $regexes AS $id => $regex )
        {
            if( preg_match_all('/'. $regex .'/i', $releaseName, $matches) )
            {
                // Debug message
                if( true === $this->settings['debug'] )
                {
                    $this->lastDebug  = sprintf( "   DEBUG: Release [%s] does matches blacklist regex:". PHP_EOL, $releaseName );
                    $this->lastDebug .= sprintf( "   DEBUG: Regex (%d): %s". PHP_EOL, $id, $regex );
                    $this->lastDebug .= sprintf( "   DEBUG: Matches: %s". PHP_EOL, $this->buildMatches( $matches ) );
                }

                // Matches blacklist, remove.
                $remove = $id;
                return;
            }
        }
    }


    /**
     * DEBUG: Build the match string
     *
     * @param array $matches
     * @param bool $last
     * @return string
     */
    protected function buildMatches( $matches, $last=true )
    {
        // Init
        $ret = '';
        $comma = '';

        // Loop the found matches
        if( is_array( $matches ) && 0 < count( $matches ) )
        {
            foreach( $matches AS $row )
            {
                if( is_array( $row ) )
                {
                    $row = $this->buildMatches( $row, false );
                }

                // Add the row
                $row = trim( $row );
                if( '' !== $row )
                {
                    $ret .= $comma . $row;
                    $comma = '|';
                }
            }
        }

        // Build the correct string
        if( true === $last )
        {
            $ret = implode(' | ', array_unique( explode('|', $ret) ) );
        }

        // Return
        return $ret;
    }
}

// Main application
try
{
    // Init
    $sphinx = new Sphinx();

    // Load the blacklistReleases class
    $blr = new remove_blacklist_releases();
    $blr->cleanup();

} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
