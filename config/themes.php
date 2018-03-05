<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Root path where theme Views will be located.
    | Can be outside default views path e.g.: resources/themes
    | Leave it null if you will put your themes in the default views folder
    | (as defined in config\views.php)
    |--------------------------------------------------------------------------
    */

    'themes_path' => 'public/themes', // eg: base_path('resources/themes')

	/*
	|--------------------------------------------------------------------------
	| Set behavior if an asset is not found in a Theme hierarchy.
	| Available options: THROW_EXCEPTION | LOG_ERROR | IGNORE
	|--------------------------------------------------------------------------
	*/

	'asset_not_found' => 'LOG_ERROR',

	/*
	|--------------------------------------------------------------------------
	| Do we want a theme activated by default? Can be set at runtime with:
	| Theme::set('theme-name');
	|--------------------------------------------------------------------------
	*/

	'default' => 'Gentele',

	/*
	|--------------------------------------------------------------------------
	| Cache theme.json configuration files that are located in each theme's folder
	| in order to avoid searching theme settings in the filesystem for each request
	|--------------------------------------------------------------------------
	*/

	'cache' => true,

	/*
	|--------------------------------------------------------------------------
	| Define available themes. Format:
	|
	| 	'theme-name' => [
	| 		'extends'	 	=> 'theme-to-extend',  // optional
	| 		'views-path' 	=> 'path-to-views',    // defaults to: resources/views/theme-name
	| 		'asset-path' 	=> 'path-to-assets',   // defaults to: public/theme-name
	|
	|		// You can add your own custom keys
	|		// Use Theme::getSetting('key') & Theme::setSetting('key', 'value') to access them
	| 		'key' 			=> 'value',
	| 	],
	|
	|--------------------------------------------------------------------------
	*/

	'themes' => [
	    'Charisma' => [
	        'extends' => null,
            'views-path' => 'Charisma',
            'asset-path' => 'public/themes/shared/assets'
        ],
        'Gamma' => [
            'extends' => null,
            'views-path' => 'Gamma',
            'asset-path' => 'public/themes/shared/assets'
        ],
        'Gentele' => [
            'extends' => null,
            'views-path' => 'Gentele',
            'asset-path' => 'public/themes/shared/assets'
        ],
        'Omicron' => [
            'extends' => null,
            'views-path' => 'Omicron',
            'asset-path' => 'public/themes/shared/assets'
        ],
        'admin' => [
            'extends' => null,
            'views-path' => 'admin',
            'asset-path' => 'public/themes/shared/assets'
        ],

                // Add your themes here. These settings will override theme.json settings defined for each theme

		/*
		|---------------------------[ Example Structure ]--------------------------
		|
		|	// Full theme Syntax:
		|
		|	'example1' => [
		|		'extends'	 	=> null, 	// doesn't extend any theme
		|		'views-path' 	=> example, // = resources/views/example_theme
		|		'asset-path' 	=> example, // = public/example_theme
		|	],
		|
		|	// Use all Defaults:
		|
		|	'example2',	// Assets =\public\example2, Views =\resources\views\example2
		|				// Note that if you use all default values, you can omit declaration completely.
		|				// i.e. defaults will be used when you call Theme::set('undefined-theme')
		|
		|
		|	// This theme shares the views with example2 but defines its own assets in \public\example3
		|
		|	'example3' => [
		|		'views-path'	=> 'example',
		|	],
		|
		|	// This theme extends example1 and may override SOME views\assets in its own paths
		|
		|	'example4' => [
		|		'extends'	=> 'example1',
		|	],
		|
		|--------------------------------------------------------------------------
		*/
	],

];
