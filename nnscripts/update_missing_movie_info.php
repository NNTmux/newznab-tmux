<?php
/**
 * This script will update missing movie information
 * 
 * Releases may be added/eddited with an imdb-id that does not exists
 * in the movieinfo table. This script will fetch all the missing imdb
 * id's from the releases table.
 * 
 * The location of the script needs to be "misc/custom" or the
 * "misc/testing" directory. if used from another location,
 * change lines 32 to 35 to require the correct files.
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

// Should the move info be updated?
define('UPDATE', true);
//----------------------------------------------------------------------

// Load the application
require(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."lib/movie.php");
require_once('nnscripts.php');

/**
 * Update missing movie information
 */
class movieInfo
{
    /**
     * NNScripts class
     * @var NNScripts
     */
    private $nnscripts;    
    
    /**
     * The database object
     * @var DB
     */
    private $db;
    
    /**
     * The movie object
     * @var Movie
     */
    private $movie;
    

    /**
     * Constructor
     * 
     * @param NNScripts $nnscripts
     * @param DB $db
     * @param Movie $movie
     */
    public function __construct( NNScripts $nnscripts, DB $db, Movie $movie )
    {
        // Set the NNScripts variable
        $this->nnscripts = $nnscripts;
        
        // Set the database variable
        $this->db = $db;
        
        // Set the movie variable
        $this->movie = $movie;
    }
    
    
    /**
     * Process the missing movie information
     * 
     * @return void
     */
    public function update()
    {
        // Gather the movie IMDB id's
        $sql = "SELECT r.imdbID, r.name
                FROM `releases` r
                LEFT JOIN `movieinfo` mi ON (mi.imdbID = r.imdbID)
                WHERE r.imdbID IS NOT NULL
                AND r.imdbID != '0000000'
                AND mi.ID IS NULL
                GROUP BY r.imdbID";
        $movies = $this->db->query( $sql );
        if( is_array( $movies ) && 0 < count( $movies ) )
        {
            // Build the regexes array
            foreach( $movies AS $row )
            {
                $name = $row['name'];
                if( preg_match('/^([a-z\.\_0-9\'\s]+)[\s\._]\(?(19[0-9]{2}|2[0-9]{3})\)?[\s\._].*?$/i', $row['name'], $matches) )
                {
                    $name = sprintf("%s (%s)", str_replace(array('.', '_'), ' ', trim($matches[1])), $matches[2]);
                }

                $this->nnscripts->display( sprintf( "Updating: %s ", $name ) );
                if( defined('UPDATE') && true === UPDATE )
                {
                    // Update the movie info
                    $this->movie->updateMovieInfo( $row['imdbID'] );
                        
                    // Prevent overloading the remote servers
                    sleep(2);
                }
                $this->nnscripts->display( PHP_EOL );
            }
        }
        else
        {
            $this->nnscripts->display( 'No missing movie information found'. PHP_EOL );
        }
        
        $this->nnscripts->display( PHP_EOL );
    }
}

try
{
    // Init
    $scriptName    = 'Update missing movie information';
    $scriptVersion = '0.1';
    
    // Load the NNscript class
    $nnscripts = new NNScripts( $scriptName, $scriptVersion );
    
    // Display the header
    $nnscripts->displayHeader();
    
    // Load the application part
    $movie = new Movie;
    if( !$movie )
        throw new Exception("Error loading movie library");
    
    $db = new DB;
    if( !$db )
        throw new Exception("Error loading database library");
        
    // Load the blacklistReleases class
    $mi = new movieInfo( $nnscripts, $db, $movie );
    $mi->update();
    
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

