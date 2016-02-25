<?php

/**
 * @brief		Map Manager Controller
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.1
 */

namespace IPS\membermap\modules\admin\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * mapmanager
 */
class _mapmanager extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'mapmanager_manage' );

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/leaflet-src.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet-providers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_membermap.js', 'membermap', 'admin' ) );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/menumanager.css', 'core', 'admin' ) );

		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$defaultMaps = \IPS\membermap\Application::getEnabledMaps();

		\IPS\Output::i()->jsVars['membermap_defaultMaps'] = $defaultMaps;

		\IPS\Output::i()->sidebar['actions']['preview'] = array(
			'icon'	=> 'eye',
			'link'	=> \IPS\Http\Url::external( 'https://leaflet-extras.github.io/leaflet-providers/preview/index.html' ),
			'title'	=> 'membermap_mapmanager_preview',
			'target' => '_blank',
		);

		\IPS\Output::i()->title  = \IPS\Member::loggedIn()->language()->addToStack('menu__membermap_membermap_mapmanager');
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'mapmanager' )->wrapper();
	}

	public function update()
	{
		$maps = \IPS\Request::i()->maps;
		
		if( !isset( $maps['basemaps'] ) )
		{
			/* You can't have a map with no basemap. Defaulting to MapQuestOpen.OSM */
			$maps['basemaps'] = array( 'MapQuestOpen.OSM' );
		}
		if( !isset( $maps['overlays'] ) )
		{
			$maps['overlays'] = array();
		}

		//debug( $maps );

		\IPS\Settings::i()->membermap_activemaps = json_encode( $maps );
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => \IPS\Settings::i()->membermap_activemaps ), array( 'conf_key=?', 'membermap_activemaps' ) );
		unset( \IPS\Data\Store::i()->settings );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = 1;
			return;
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=membermap&module=membermap&controller=mapmanager" ), 'saved' );
	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}