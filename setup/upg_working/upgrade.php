<?php


namespace IPS\membermap\setup\upg_working;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * {version_human} Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Remove MapQuest.OSM, as it's no longer "free" */
		$maps = json_decode( \IPS\Settings::i()->membermap_activemaps, true );

		$osm = array_search( 'MapQuestOpen.OSM', $maps['basemaps'] );

		if ( $osm !== FALSE )
		{
			$maps['basemaps'][ $osm ] = 'OpenStreetMap.France';
			$maps['basemaps'] = array_unique( $maps['basemaps'], SORT_REGULAR );

			\IPS\Settings::i()->membermap_activemaps = json_encode( $maps );
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => \IPS\Settings::i()->membermap_activemaps ), array( 'conf_key=?', 'membermap_activemaps' ) );
			unset( \IPS\Data\Store::i()->settings );
		}


		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}