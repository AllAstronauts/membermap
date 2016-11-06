<?php
/**
 * @brief		Application Class
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.0
 */
 
namespace IPS\membermap;

/**
 * Member Map Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * List of default map providers
	 * @var array
	 */
	public static $defaultMaps = array(
		'basemaps' => array(
			'OpenStreetMap.France',
			'Esri.WorldStreetMap',
			'Esri.WorldTopoMap',
		)
	);

	/**
	 * Shared API keys. Currently not in use
	 * @var array
	 */
	public static $apiKeys = array(
		/*'mapquest' => "pEPBzF67CQ8ExmSbV9K6th4rAiEc3wud",*/
	);

	public function init()
	{
	}

	/**
	 * Install 'other' items.
	 *
	 * @return void
	 */
	public function installOther()
	{
		/* Install default Members marker group */
		/* Calling this will create a group if one don't exist */
		\IPS\membermap\Map::i()->getMemberGroupId();
	}

	/**
	 * Get API keys
	 * Currently it only serves MapQuest, but others may be added in the future
	 * 
	 * @param  string 	$service 	Name of the service, will return all keys if param is empty
	 * @return mixed 	Single API key, or all in an array
	 */
	public static function getApiKeys( $service )
	{
		if ( ! isset( static::$apiKeys['mapquest'] ) )
		{
			if ( \IPS\Settings::i()->membermap_mapQuestAPI )
			{
				static::$apiKeys['mapquest'] = \IPS\Settings::i()->membermap_mapQuestAPI;
			}
		}

		if ( \IPS\Dispatcher::i()->controllerLocation == 'front' AND ( ! isset( static::$apiKeys['mapquest'] ) OR empty( static::$apiKeys['mapquest'] ) ) )
		{
			if ( \IPS\Member::loggedIn()->isAdmin() )
			{
				\IPS\Output::i()->error( 'membermap_noAPI_admin', '4MM5/1', 401 );
			}
			else
			{
				\IPS\Output::i()->error( '401_error_title', '4MM5/2', 401 );
			}
		}

		try
		{
			if ( $service )
			{
				return static::$apiKeys[ $service ];
			}
		}
		catch( \Exception $e ) {}
	

		return static::$apiKeys;
	}

	/**
	 * Get an array of enabled maps
	 * @return array 	List of maps
	 */
	public static function getEnabledMaps()
	{
		$defaultMaps = static::$defaultMaps;

		if ( \IPS\Settings::i()->membermap_activemaps )
		{
			$maps = json_decode( \IPS\Settings::i()->membermap_activemaps, true );
			if ( is_array( $maps ) )
			{
				$defaultMaps = $maps;
			}
		}

		return $defaultMaps;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'map-marker';
	}

	/**
	 * All assets required for the marker form(s)
	 * @return void
	 */
	public static function getJsForMarkerForm()
	{
		/* Get enabled maps */
		$defaultMaps = static::getEnabledMaps();

		/* Load JS and CSS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/leaflet-src.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet-providers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.awesome-markers.js', 'membermap', 'interface' ) );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'plugins.combined.css', 'membermap' ) );


		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_markers.js', 'membermap', 'front' ) );

		\IPS\Output::i()->jsVars['membermap_defaultMaps'] = $defaultMaps;
		\IPS\Output::i()->jsVars['membermap_mapquestAPI'] = static::getApiKeys( 'mapquest' ); 
	}

	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		return array(
			'rootTabs'		=> array( 
				array( 
					'key' => 'membermap',
					'app' => 'membermap', 
				) 
			),
			'browseTabs'	=> array(),
			'browseTabsEnd'	=> array(),
			'activityTabs'	=> array()
		);
	}
}
