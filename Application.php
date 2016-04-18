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

	public static $defaultMaps = array(
		'basemaps' => array(
			'MapQuestOpen.OSM',
			'Thunderforest.Landscape',
			'Esri.WorldStreetMap',
			'Esri.WorldTopoMap',
		)
	);

	public static $apiKeys = array(
		'mapquest' => "pEPBzF67CQ8ExmSbV9K6th4rAiEc3wud",
	);

	public function init()
	{
		if ( \IPS\Settings::i()->membermap_mapQuestAPI )
		{
			self::$apiKeys['mapquest'] = \IPS\Settings::i()->membermap_mapQuestAPI;
		}
	}

	/**
	 * Install 'other' items.
	 *
	 * @return void
	 */
	public function installOther()
	{
	}

	public static function getApiKeys( $service )
	{
		try
		{
			if ( $service )
			{
				return self::$apiKeys[ $service ];
			}
		}
		catch( \Exception $e )
		{
		}

		return self::$apiKeys;
	}

	public static function getEnabledMaps()
	{
		$defaultMaps = self::$defaultMaps;

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

	public static function getJsForMarkerForm()
	{
		/* Get enabled maps */
		$defaultMaps = self::getEnabledMaps();

		/* Load JS and CSS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/leaflet-src.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet-providers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.awesome-markers.js', 'membermap', 'interface' ) );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'plugins.combined.css', 'membermap' ) );

		\IPS\Output::i()->jsVars['membermap_defaultMaps'] = $defaultMaps;
		\IPS\Output::i()->jsVars['membermap_mapquestAPI'] = self::getApiKeys( 'mapquest' ); 
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
