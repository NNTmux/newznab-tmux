<?php

// Load the config
require_once(dirname(__FILE__)."/../../bin/config.php");

// Require the commandline options script
require_once("Getopt.php");

// Require the newznab site script
require_once(WWW_DIR."lib/site.php");



/**
 * This class contains the functions used by the other NNScripts
 * 
 * @author    NN Scripts
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2013 - NN Scripts
 *
 * Changelog:
 * 0.3 - Made the class abstract
 * 0.2 - Added retrieval of limit from site
 * 0.1 - Initial version
 */
abstract class NNScripts
{
    /**
     * The script title (needs to be set in the real script)
     * @var string
     */
    protected $scriptName = 'Unknown NNScript';
    
    /**
     * The script version (needs to be set in the real script)
     * @var string
     */
    protected $scriptVersion = '0.0';
    
    /**
     * Allowed settings
     * @var array
     */
    protected $allowedSettings = array();

    /**
     * The site object
     * @var Sites
     */
    protected $site;

    /**
     * The database connection
     * @var DB
     */
    protected $db;
    
    /**
     * The settings read from the ini file
     * @var string
     */
    protected $settings = array();

    /**
     * The commandline options
     * @var null|Getopt
     */
    protected $options = null;
    
    /**
     * Disable the limit option
     * @var bool
     */
    protected $nolimit = false;




    /**
     * NNScripts constructor
     *
     * @internal param $scriptName
     * @internal param string $version
     * @internal param string $scriptname
     */
    public function __construct()
    {
        // Check correct php version
        $this->checkPHPVersion();

        // Build the site variable
        $site = new Sites();
        $this->site = $site->get();

        // Initiate the database connection
        $this->db = new DB();

        // Read the settings from the ini file
        $this->readSettings();

        // Set the limit
        $this->setLimit();

        // Add the "now" date
        $date = new DateTime();
        $this->settings['now'] = $date->format('Y-m-d H:i:s');
    }


    /**
     * Destructor
     *
     */
    public function __destruct()
    {
        if( isset( $_SERVER["HTTP_HOST"] ) && true === $this->settings['display'] )
        {
            echo '</pre>';
        }
    }


    /**
     * Check php version to be 5.3.10 or greater
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
     * Set the limit in the settings
     * The limit is read from the site setting "rawretentiondays"
     *
     * @return void
     */
    protected function setLimit()
    {
        $limit = ( isset( $this->settings['limit'] ) ? $this->settings['limit'] : null );        
        if( null === $limit && in_array( 'limit', $this->allowedSettings ) )
        {
            // Not set by settings.ini
            if( isset( $this->site ) && isset( $this->site->rawretentiondays ) && ( !isset($this->nolimit) || true !== $this->nolimit ) )
            {
                // Get the limit and convert to hours
                $limit = (float)str_replace( ',', '.', $this->site->rawretentiondays ) * 24;
            }
            
            // Set the new limit
            $this->settings['limit'] = $limit;
        }
    }

    
    /**
     * Read the settings from the config.ini fille
     * 
     * @return void
     */
    protected function readSettings()
    {
        // The default settings
        $settings = array(
            'display'    => true,       // Display output
            'remove'     => null,       // Remove or keep releases/data
            'limit'      => null,       // Limit
            'full'       => null,       // Full database
            'debug'      => null,       // Debug mode
            'querylimit' => 10000       // Query limit (used in remove_parts_without_releases)
        );

        // Try to read the ini file
        $settingsFile = "/settings.ini";
        $iniArray = $this->parseIniFile( $settingsFile, true );
        
        // Parse the "global" settings
        $this->parseIniSettings( $settings, $iniArray, 'global' );

        // Parse the class settings
        $this->parseIniSettings( $settings, $iniArray, get_class( $this ) );
        
        // Remove all the not used settings
        if( is_array( $this->allowedSettings ) && 0 < count( $this->allowedSettings ) )
        {
            $allowed = $this->allowedSettings;
            array_map( function( $key ) use ( &$settings, $allowed ) {
                if( !in_array( $key, $allowed ) )
                {
                    unset( $settings[ $key ] );
                }
            }, array_keys( $settings ) );
        }

        // Return
        $this->settings = $settings;
    }


    /**
     * Parse ini settings
     *
     * @param array $settings
     * @param array $iniArray
     * @param string $key
     * @return void
     */
    protected function parseIniSettings( array &$settings, array $iniArray, $key )
    {
        // Parse the global settings
        if( array_key_exists( $key, $iniArray ) && is_array( $iniArray[ $key ] ) )
        {
            foreach( array_keys( $settings ) AS $settingKey )
            {
                if( array_key_exists( $settingKey, $iniArray[ $key ] ) )
                {
                    $value = $iniArray[ $key ][ $settingKey ];          
                    if( is_numeric( $value ) )
                    {
                        $value = ( preg_match('/^[0-9]+$/', $value ) ? (int)$value : (float)$value );
                    }
                    $settings[ $settingKey ] = $value;
                }
            }
        }
    }


    /**
     * Parse the ini file
     *
     * @param string $settingsFile
     * @return array
     */
    protected function parseIniFile( $settingsFile )
    {
        // Init
        $ret = array();

        if( file_exists( $settingsFile ) && is_readable( $settingsFile ) )
        {
            $ini = file( $settingsFile );

            if ( 0 === count( $ini ) )
                return $ret;

            $sections = $values = $globals = array();
            $i = 0;

            foreach( $ini as $line )
            {
                $line = trim( $line );

                // Comments
                if ( '' === $line || ';' === $line{0} )
                    continue;

                // Sections
                if ( '[' === $line{0} )
                {
                    $sections[] = substr( $line, 1, -1 );
                    $i++;
                    continue;
                }

                // Key-value pair
                list( $key, $value ) = explode( '=', $line, 2 );
                $key = trim( $key );
                $value = trim( $value );

                // True / Fasle
                if( "true" === $value )
                    $value = true;
                if( "false" === $value )
                    $value = false;

                if ( 0 === $i )
                {
                    // Array values
                    if ( '[]' === substr( $line, -1, 2 ) )
                        $globals[ $key ][] = $value;
                    else
                        $globals[ $key ] = $value;
                }
                else
                {
                    // Array values
                    if ( '[]' === substr( $line, -1, 2 ) )
                        $values[ $i - 1 ][ $key ][] = $value;
                    else
                        $values[ $i - 1 ][ $key ] = $value;
                }
            }
            
            for( $j=0; $j<$i; $j++ )
            {
                if( array_key_exists( $j, $sections ) && array_key_exists( $j, $values ) )
                {
                    $ret[ $sections[ $j ] ] = $values[ $j ];
                }
            }

            return $ret + $globals;
        }

        // Return
        return $ret;
    }


    /**
     * Process the cli options
     *
     * @param array $options
     * @param bool $addLimit
     * @param bool $addRemove
     * @param bool $addDebug
     * @param bool $addHelp
     * @internal param bool $addNoLimit
     * @return void
     */
    protected function setCliOptions( array $options, array $basics=array('limit', 'remove') )
    {
        // Add a PHP_EOL to the last option
        if( 0 < count( $options ) )
        {
            $last = array_keys( $options );
            $last = end( $last );
            if( isset( $options[ $last ][3] ) )
                $options[ $last ][3] = $options[ $last ][3] . PHP_EOL;
        }
        
        // Add the quiet option
        if( in_array( 'display', $basics ) )
        {
            $o1 = ( true === $this->settings['display'] ? ' (settings default)' : '' );
            $options[] = array( 'd', 'display', Ulrichsg\Getopt::NO_ARGUMENT, sprintf( 'Enable output%s', $o1 ) );
            
            $o2 = ( false === $this->settings['display'] ? ' (settings default)' : '' );
            $options[] = array( 'q', 'quiet', Ulrichsg\Getopt::NO_ARGUMENT, sprintf( 'Disable output%s'. PHP_EOL, $o2 ) );
        }
        
        // Add the nolimit option
        if( in_array( 'limit', $basics ) )
        {
            $options[] = array( 'l', 'limit',  Ulrichsg\Getopt::REQUIRED_ARGUMENT, sprintf('The limit in hours (default limit is %d hour%s)', $this->settings['limit'], (1 === $this->settings['limit'] ? '' : 's') ) );
            $options[] = array( 'n', 'nolimit', Ulrichsg\Getopt::NO_ARGUMENT, 'Disable the limit' . PHP_EOL );
        }

        // Remove options
        if( in_array( 'remove', $basics ) )
        {
            $o1 = ( true === $this->settings['remove'] ? ' (settings default)' : '' );
            $options[] = array( 'r', 'remove', Ulrichsg\Getopt::NO_ARGUMENT, sprintf( 'Enable the removal of releases%s', $o1 ) );

            $o2 = ( false === $this->settings['remove'] ? ' (settings default)' : '' );
            $options[] = array( 'k', 'keep', Ulrichsg\Getopt::NO_ARGUMENT, sprintf( 'Disable the removal of releases%s'. PHP_EOL, $o2 ) );
        }
                
        // Add the debug option
        if( in_array( 'debug', $basics ) )
        {
            $o1 = ( true === $this->settings['debug'] ? ' (settings default)' : '' );
            $options[] = array( null, 'debug', Ulrichsg\Getopt::NO_ARGUMENT, sprintf( 'Enable debug mode%s'. PHP_EOL, $o1 ) );
        }

        // Add the help option
        if( in_array( 'help', $basics ) )
        {
            $options[] = array( 'h', 'help', Ulrichsg\Getopt::NO_ARGUMENT, 'Shows this help'. PHP_EOL );
        }

        // Set the command line options
        $this->options = new Ulrichsg\Getopt( $options );
        $this->options->parse();

        // Check for the help
        if( $this->options->getOption('help') )
        {
            $this->displayHeader();
            $this->options->showHelp();
            die();
        }

        // Merge the cli options with the settings
        $this->mergeSettings();
    }


    /**
     * Merge the commandline options with the settings
     *
     * @return void
     */
    protected function mergeSettings()
    {
        if( null !== $this->options && $this->options instanceof Ulrichsg\Getopt )
        {
            // Check for limit settings
            if( $this->options->getOption('nolimit') )
            {
                $this->settings['limit'] = false;
            }
            if( $this->options->getOption('limit') )
            {
                if( !is_numeric( $this->options->getOption('limit') ) )
                {
                    throw new Exception( 'Option \'limit\' must be numeric' );
                }
                
                $this->settings['limit'] = false;
                if( 0 < (float)$this->options->getOption('limit') )
                {
                    $this->settings['limit'] = (float)$this->options->getOption('limit');
                }
            }
            
            // Full database
            if( $this->options->getOption('full') )
                $this->settings['full'] = true;

            // Check for remove setting
            if( $this->options->getOption('remove') )
                $this->settings['remove'] = true;
            if( $this->options->getOption('keep') )
                $this->settings['remove'] = false;
                
            // Check for display options
            if( $this->options->getOption('quiet') )
                $this->settings['display'] = false;
            if( $this->options->getOption('display') )
                $this->settings['display'] = true;

            // Debug?
            if( $this->options->getOption('debug') )
                $this->settings['debug'] = true;
                
            // Query limit
            if( $this->options->getOption('querylimit') )
                if( is_numeric( $this->options->getOption('querylimit') ) )
                    $this->settings['querylimit'] = (int)$this->options->getOption('querylimit');
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
    protected function display( $message )
    {
        if( true === $this->settings['display'] )
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
    protected function displayPageStart()
    {
        if( isset( $_SERVER["HTTP_HOST"] ) && true === $this->display )
        {
            echo '<pre style="font-size: 11px; line-height: 7px;">';
        }
    }

    /**
     * Display the title
     *
     * @return void
     */
    protected function displayTitle()
    {
        // Display the script header line
        $this->display( sprintf( "%s - version %s". PHP_EOL, $this->scriptName, $this->scriptVersion ) );
    }


    /**
     * Display the header line and settings info
     *
     * @return void
     */
    protected function displayHeader()
    {
        // Display the page start in case of an html page
        $this->displayPageStart();

        // Show the title
        $this->displayTitle();
        $this->display( PHP_EOL );
    }
    
    /**
     * Display app settings
     * @return void
     */
    protected function displaySettings()
    {
        $show = false;
        // Display settings?
        if( ( isset( $this->settings['remove'] ) && null !== $this->settings['remove'] ) ||
            ( isset( $this->settings['debug'] ) && null !== $this->settings['debug'] ) ||
            ( isset( $this->settings['limit'] ) && null !== $this->settings['limit'] ) ||
            ( isset( $this->settings['querylimit'] ) && null !== $this->settings['querylimit'] ) )
        {
            $show = true;
            $this->display( 'Settings:' );

            // "debug" setting
            if( isset( $this->settings['debug'] ) && is_bool( $this->settings['debug'] ) )
            {
                 $this->display( PHP_EOL . sprintf( "- debug : %sabled", ( true === $this->settings['debug'] ? 'En' : 'Dis' ) ) );
            }

            // "limit" setting
            if( isset( $this->settings['limit'] ) && null !== $this->settings['limit'] )
            {
                $line = 'no limit';
                if( is_float( $this->settings['limit'] ) )
                {
                    $line = sprintf( "%s hour%s", $this->settings['limit'], ( 1.0 !== $this->settings['limit'] ? 's' : '' ) );
                }
                $this->display( PHP_EOL . sprintf( "- limit : %s", $line ) );
            }

            // "remove" setting
            if( isset( $this->settings['remove'] ) && null !== $this->settings['remove'] )
            {
                $line = "Data will be removed.";
                if( true !== $this->settings['remove'] )
                {
                    $line = 'No data is removed from the database!'. PHP_EOL .'          Use the --remove option or change the settings.ini file if you want to remove releases or parts';
                }
                $this->display( PHP_EOL . sprintf( "- remove: %s", $line ) );
            }

            // "update" setting
            if( isset( $this->settings['update'] ) && null !== $this->settings['update'] )
            {
                $line = "Data will be updated.";
                if( true !== $this->settings['update'] )
                {
                    $line = 'No data is updated in the database!'. PHP_EOL .'          Change the "UPDATE" setting if you want to enable update';
                }
                $this->display( PHP_EOL . sprintf( "- update: %s", $line ) );
            }
            
            // querylimit
            if( isset( $this->settings['querylimit'] ) && null !== $this->settings['querylimit'] )
            {
                $this->display( PHP_EOL . sprintf( "- querylimit: %s", $this->settings['querylimit'] ) );
            }

            // Spacer
            $this->display( PHP_EOL );
        }

        // End spacer
        if( true === $show )
            $this->display( PHP_EOL );
    }
}