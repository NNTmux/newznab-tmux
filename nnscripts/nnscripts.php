<?php
/**
 * This class contains the functions used by the other NNScripts
 * 
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.1  - Initial version
 */
 
class NNScripts
{
    /**
     * The script name
     * @var string
     */
    private $scriptName;
    
    /**
     * The script version
     * @var string
     */
    private $version;
    
    /**
     * Should output be displayed?
     * @var bool
     */
    private $display = false;
    
    /**
     * The query limit in hours
     * @var bool|int
     */
    private $limit = null;
    
    /**
     * Should data be removed?
     * @var null|bool
     */
    private $remove = null;
    
    /**
     * Should data be updated?
     * @var null|bool
     */
    private $update = null;

    /**
     * Debug mode
     * @var bool|null
     */
    private $debug = null;


    /**
     * NNScripts constructor
     *
     * @param $scriptName
     * @param string $version
     * @internal param string $scriptname
     */
    public function __construct( $scriptName, $version )
    {
        // Check correct php version
        $this->checkPHPVersion();

        // Set the script name and version
        $this->scriptName = $scriptName;
        $this->version = $version;
        
        // Set the display setting
        $this->display = ( !defined('DISPLAY') || true === DISPLAY ? true : false );
        
        // Query Limit
        if( defined('LIMIT') )
        {
            $this->limit = ( ( is_int(LIMIT) && 0 < LIMIT ) ? LIMIT : false );
        }
        
        // Should data be removed?
        if( defined('REMOVE') )
        {
            $this->remove = ( ( true === REMOVE ) ? true : false );
        }
        
        // Should data be updated?
        if( defined('UPDATE') )
        {
            $this->update = ( ( true === UPDATE ) ? true : false );
        }

        // Debug mode?
        if( defined('DEBUG') )
        {
            $this->debug = ( ( true === DEBUG ) ? true : false );
        }
    }
    
    
    /**
     * Destructor
     * 
     */
    public function __destruct()
    {
        if( isset( $_SERVER["HTTP_HOST"] ) && true === $this->display )
        {
            echo '</pre>';
        }
    }


    /**
     * Check php version to be 5.4 or greater
     *
     * @throws Exception
     * @return void
     */
    protected function checkPHPVersion()
    {
        // Init
        $current  = ( false === strpos( phpversion(), '-' ) ? phpversion() : substr( phpversion(), 0, strpos( phpversion(), '-' ) ) );
        $required = '5.3.10';
        
        if( 0 > strnatcmp( $current, $required ) )
        {
            throw new Exception( sprintf( 'NNScripts'. PHP_EOL .'Error: PHP version %s is required but version %s is found.', $required, $current ) );
        }
    }

       
    /**
     * Display a message
     * 
     * The function will also try to detect a browser call and convert
     * new-lines to <br />'s
     * 
     * @param string $message
     * @return void
     */
    public function display( $message )
    {
        if( true === $this->display )
        {
            // Convert for webbrowser?
            if( isset( $_SERVER["HTTP_HOST"] ) )
            {
                $message = nl2br( $message );
            }
            
            // Display
            echo $message;
        }
    }       


    /**
     * Display a page start
     * Only usefull when viewing with a webbrowser
     * 
     * @return void
     */
    public function displayPageStart()
    {
        if( isset( $_SERVER["HTTP_HOST"] ) && true === $this->display )
        {
            echo '<pre style="font-size: 11px; line-height: 7px;">';
        }
    }       


    /**
     * Display the header line and settings info
     * 
     * @return void
     */
    public function displayHeader()
    {
        // Display the page start in case of an html page
        $this->displayPageStart();
        
        // Display the script header line
        $this->display( sprintf( "%s - version %s". PHP_EOL, $this->scriptName, $this->version ) );
        
        // Display settings?
        if( null !== $this->limit || null !== $this->remove || null !== $this->update )
        {
            $this->display( PHP_EOL .'Settings:' );

            // Display the "debug" setting
            if( true === $this->debug )
            {
                 $this->display( PHP_EOL . "- debug mode enabled" );
            }
            
            // Display the "limit" setting
            if( null !== $this->limit )
            {
                $line = 'no limit';
                if( is_int($this->limit) )
                {
                    $line = sprintf( "%d hour%s", $this->limit, ( 1 < $this->limit ? 's' : '' ) );
                }
                $this->display( PHP_EOL . sprintf( "- limit : %s", $line ) );
            }
        
            // Display the "remove" setting
            if( null !== $this->remove )
            {
                $line = "Data will be removed.";
                if( true !== $this->remove )
                {
                    $line = 'No data is removed from the database!'. PHP_EOL .'          Change the "REMOVE" setting if you want to remove releases or parts';
                }
                $this->display( PHP_EOL . sprintf( "- remove: %s", $line ) );
            }
            
            // Display the "update" setting
            if( null !== $this->update )
            {
                $line = "Data will be updated.";
                if( true !== $this->update )
                {
                    $line = 'No data is updated in the database!'. PHP_EOL .'          Change the "UPDATE" setting if you want to enable update';
                }
                $this->display( PHP_EOL . sprintf( "- update: %s", $line ) );
            }
        
            // Spacer
            $this->display( PHP_EOL );
        }
        
        // End spacer
        $this->display( PHP_EOL );
    }
}
