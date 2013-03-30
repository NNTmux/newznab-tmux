<?php
/**
 * This script will update missing movie information
 * 
 * Releases may be added/eddited with an imdb-id that does not exists
 * in the movieinfo table. This script will fetch all the missing imdb
 * id's from the releases table and updates the movie info.
 *
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.2 - Added settings.ini
 *       Start using nnscript library
 * 
 * 0.1 - Initial version
 */
// Load the application
require_once(dirname(__FILE__)."/../bin/config.php");

// nnscripts includes
require_once("lib/nnscripts.php");

// newznab includes
require_once(WWW_DIR."/lib/movie.php"); 


/**
 * Update missing movie info
 */
class update_missing_movie_info extends NNScripts
{
    /**
     * The script name
     * @var string
     */
    protected $scriptName = 'Update missing movie information';
    
    /**
     * The script version
     * @var string
     */
    protected $scriptVersion = '0.2';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array('display');
    
    /**
     * The movie object
     * @var Movie
     */
    private $movie;
    
    
    
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
        $this->setCliOptions( $options, array('display', 'help') );
        
        // Show the header
        $this->displayHeader();
        
        // Set the movie variable
        $this->movie = new Movie();
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

                $this->display( sprintf( "Updating: %s ", $name ) );

                // Update the movie info
                $this->movie->updateMovieInfo( $row['imdbID'] );
                        
                // Prevent overloading the remote servers
                sleep(2);
                
                $this->display( PHP_EOL );
            }
        }
        else
        {
            $this->display( 'No missing movie information found'. PHP_EOL );
        }
    } 
}


// Main application
try
{
    // Update missing movie information
    $ummi = new update_missing_movie_info();
    $ummi->update();
} catch( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}
