<?php
/**
 * @brief		Public Controller
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.0
 */


namespace IPS\membermap\modules\front\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * showmap
 */
class _showmap extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		
		parent::execute();
	}

	/**
	 * Show the map
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$markers 	= array();
		$cacheTime 	= isset( \IPS\Data\Store::i()->membermap_cacheTime ) ? \IPS\Data\Store::i()->membermap_cacheTime : 0;

		/* Rebuild JSON cache if needed */
		if ( ! is_file ( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-index.json' ) OR \IPS\Request::i()->rebuildCache === '1' OR $cacheTime === 0 )
		{
			\IPS\membermap\Map::i()->recacheJsonFile();

			/* We clicked the tools menu item to force a rebuild */
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=membermap', NULL, 'membermap' ) );
			}
		}


		$getByUser = intval( \IPS\Request::i()->member_id );

		if ( \IPS\Request::i()->filter == 'getByUser' AND $getByUser )
		{
			$markers = \IPS\membermap\Map::i()->getMarkerByMember( $getByUser );
		}
		else if ( \IPS\Request::i()->filter == 'getOnlineUsers' )
		{
			$markers = \IPS\membermap\Map::i()->getMarkersByOnlineMembers();
		}

		/* Get enabled maps */
		$defaultMaps = \IPS\membermap\Application::getEnabledMaps();

		/* Load JS and CSS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/leaflet-src.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/Control.FullScreen.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/Control.Loading.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet-providers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.awesome-markers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.contextmenu-src.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.markercluster-src.js', 'membermap', 'interface' ) );

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_main.js', 'membermap', 'front' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'plugins.combined.css', 'membermap' ) );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( '__app_membermap' );
		
		\IPS\Output::i()->sidebar['enabled'] = FALSE;

        /* Update session location */
        \IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=membermap', 'front', 'membermap' ), array(), 'loc_membermap_viewing_membermap' );

        /* Things we need to know in the Javascript */
		\IPS\Output::i()->jsVars['is_supmod']			= \IPS\Member::loggedIn()->modPermission() ?: 0;
		\IPS\Output::i()->jsVars['member_id']			= \IPS\Member::loggedIn()->member_id ?: 0;
		\IPS\Output::i()->jsVars['membermap_canAdd']	= \IPS\Member::loggedIn()->group['g_membermap_canAdd'] ?: 0;
        \IPS\Output::i()->jsVars['membermap_canEdit']	= \IPS\Member::loggedIn()->group['g_membermap_canEdit'] ?: 0;
        \IPS\Output::i()->jsVars['cmembermap_anDelete']	= \IPS\Member::loggedIn()->group['g_membermap_canDelete'] ?: 0;
        \IPS\Output::i()->jsVars['membermap_cacheTime'] = isset( \IPS\Data\Store::i()->membermap_cacheTime ) ? \IPS\Data\Store::i()->membermap_cacheTime : 0;
		\IPS\Output::i()->jsVars['membermap_bbox'] 		= json_decode( \IPS\Settings::i()->membermap_bbox );
		\IPS\Output::i()->jsVars['membermap_bbox_zoom'] = intval( \IPS\Settings::i()->membermap_bbox_zoom );
		\IPS\Output::i()->jsVars['membermap_defaultMaps'] = $defaultMaps;
		\IPS\Output::i()->jsVars['membermap_mapquestAPI'] = \IPS\membermap\Application::getApiKeys( 'mapquest' ); 
		\IPS\Output::i()->jsVars['membermap_enable_clustering'] = \IPS\Settings::i()->membermap_enable_clustering == 1 ? 1 : 0;


        \IPS\Output::i()->endBodyCode .= <<<EOF
		<script type='text/javascript'>
			ips.membermap.initMap();
		</script>
EOF;

        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'map' )->showMap( json_encode( $markers ), $cacheTime );
	}

	/**
	 * Loads add/update location form
	 *
	 * @return	void
	 */
	protected function add()
	{
		if ( ! \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_permission', '', 403, '' );
		}

		/* Get the members location, if it exists */
		$existing = \IPS\membermap\Map::i()->getMarkerByMember( \IPS\Member::loggedIn()->member_id );

		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack( ( ! $existing ? 'membermap_button_addLocation' : 'membermap_button_editLocation' ) );

		/* Check permissions */
		if ( $existing AND ! \IPS\Member::loggedIn()->group['g_membermap_canEdit'] )
		{
			\IPS\Output::i()->error( 'membermap_error_cantEdit', '', 403, '' );
		}
		else if ( ! \IPS\Member::loggedIn()->group['g_membermap_canAdd'] )
		{
			\IPS\Output::i()->error( 'membermap_error_cantAdd', '123', 403, '' );
		}

		$geoLocForm =  new \IPS\Helpers\Form( 'membermap_form_geoLocation', NULL, NULL, array( 'id' => 'membermap_form_geoLocation' ) );
		$geoLocForm->class = 'ipsForm_vertical ipsType_center';

		$geoLocForm->addHeader( 'membermap_current_location' );
		$geoLocForm->addHtml( '<li class="ipsType_center"><i class="fa fa-fw fa-4x fa-location-arrow"></i></li>' );
		$geoLocForm->addHtml( '<li class="ipsType_center">' . \IPS\Member::loggedIn()->language()->addToStack( 'membermap_geolocation_desc' ) . '</li>' );
		$geoLocForm->addButton( 'membermap_current_location', 'button', NULL, 'ipsButton ipsButton_primary', array( 'id' => 'membermap_currentLocation' ) );


		$form = new \IPS\Helpers\Form( 'membermap_form_location', NULL, NULL, array( 'id' => 'membermap_form_location' ) );
		$form->class = 'ipsForm_vertical ipsType_center';

		$form->addHeader( 'membermap_form_location' );
		$form->add( new \IPS\Helpers\Form\Text( 'membermap_location', '', FALSE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack( 'membermap_form_placeholder' ) ), NULL, NULL, NULL, 'membermap_location' ) );
		$form->addButton( 'save', 'submit', NULL, 'ipsPos_center ipsButton ipsButton_primary', array( 'id' => 'membermap_locationSubmit' ) );

		$form->hiddenValues['lat'] = \IPS\Request::i()->lat;
		$form->hiddenValues['lng'] = \IPS\Request::i()->lng;

		if ( $values = $form->values() )
		{
			try
			{
				$values['member_id'] = \IPS\Member::loggedIn()->member_id;

				\IPS\membermap\Map::i()->saveMarker( $values );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=membermap&dropBrowserCache=1&goHome=1' ) );
				return;
			}
			catch( \Exception $e )
			{
				$form->error	= \IPS\Member::loggedIn()->language()->addToStack( 'membermap_' . $e->getMessage() );
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'map' )->addLocation( $geoLocForm, $form );
				return;
			}
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'map' )->addLocation( $geoLocForm, $form );
	}

	/**
	 * Delete a marker
	 *
	 * @return	void
	 */
	protected function delete()
	{
		if ( ! \IPS\Member::loggedIn()->member_id OR ! intval( \IPS\Request::i()->member_id ) )
		{
			\IPS\Output::i()->error( 'no_permission', '1', 403, '' );
		}

		/* Get the marker */
		$existing = \IPS\membermap\Map::i()->getMarkerByMember( intval( \IPS\Request::i()->member_id ) )[0];

		if ( isset( $existing['member_id'] ) )
		{
			$is_supmod		= \IPS\Member::loggedIn()->modPermission() ?: 0;

			if ( $is_supmod OR ( $existing['member_id'] == \IPS\Member::loggedIn()->member_id AND \IPS\Member::loggedIn()->group['g_membermap_canDelete'] ) )
			{
				\IPS\membermap\Map::i()->deleteMarker( $existing['member_id'] );
				\IPS\Output::i()->json( 'OK' );
			}
		}

		/* Fall back to a generic error */
		\IPS\Output::i()->error( 'no_permission', '2', 403, '' );
	}

	protected function embed()
	{
		$this->manage();

		\IPS\Output::i()->title = NULL;
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
	}
}