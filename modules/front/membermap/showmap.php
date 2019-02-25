<?php
/**
 * @brief       Map view controller
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       20 Oct 2015
 * @version     -storm_version-
 */

namespace IPS\membermap\modules\front\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
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
		/* Calling this here, as it will throw an error if the key isn't present */
		\IPS\membermap\Application::getApiKeys( 'mapquest' );

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
		$club 		= NULL;

		/* Rebuild JSON cache if needed */
		if ( ! \IPS\membermap\Map::i()->checkForCache() )
		{
			/* We clicked the tools menu item to force a rebuild */
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap', NULL, 'membermap' ) );
			}
		}

		/* Get enabled maps */
		$defaultMaps = \IPS\membermap\Application::getEnabledMaps();

		/* Add/edit marker permissions */
		$groupId 	= \IPS\membermap\Map::i()->getMemberGroupId();
		$existing	= \IPS\membermap\Map::i()->getMarkerByMember( \IPS\Member::loggedIn()->member_id, FALSE );

		$canAdd 	= \IPS\membermap\Markers\Groups::load( $groupId )->can( 'add' );
		$canEdit 	= $existing ? $existing->canEdit() : false;
		$canDelete 	= $existing ? $existing->canDelete() : false;

		$cacheTime 	= isset( \IPS\Data\Store::i()->membermap_cacheTime ) ? \IPS\Data\Store::i()->membermap_cacheTime : 0;

		/* Get by user */
		if ( \IPS\Request::i()->filter == 'getByUser' AND \intval( \IPS\Request::i()->member_id ) )
		{
			$markers = \IPS\membermap\Map::i()->getMarkerByMember( \intval( \IPS\Request::i()->member_id ) );
		}
		/* Get club member markers */
		else if ( \IPS\Settings::i()->clubs AND \IPS\Request::i()->filter == 'showClub' AND $clubId = \intval( \IPS\Request::i()->clubId ) )
		{
			if ( \IPS\Settings::i()->membermap_clubs_showInClubHeader )
			{
				if( \IPS\Settings::i()->membermap_clubs == '*' OR \strstr( ','.\IPS\Settings::i()->membermap_clubs.',', ",{$clubId}," ) )
				{
					try
					{
						$club = \IPS\Member\Club::load( $clubId );
						$markers = \IPS\membermap\Map::i()->getClubMemberMarkers( $club );

						if ( \is_array( $markers ) AND \count( $markers ) > 0 )
						{
							\IPS\core\FrontNavigation::$clubTabActive = TRUE;
							\IPS\Output::i()->breadcrumb = array();
							\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
							\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
							\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap', 'front', 'membermap' ), \IPS\Member::loggedIn()->language()->addToStack( 'module__membermap_membermap' ) );

							if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
							{
								\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, \IPS\membermap\Markers\Groups::load( $groupId ), 'sidebar' );
							}
						}
					}
					catch ( \Exception $e ) 
					{
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap', NULL, 'membermap' ), 'membermap_no_club' );
					}
				}
			}
		}


		/* Load JS and CSS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/leaflet.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/Control.FullScreen.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet-providers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.awesome-markers.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.contextmenu-src.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.markercluster-src.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/leaflet.terminator.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'leaflet/plugins/subgroup.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'lz-string/lz-string.min.js', 'membermap', 'interface' ) );

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_main.js', 'membermap', 'front' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'membermap.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.control.fullscreen.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.awesome-markers.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.contextmenu.css', 'membermap' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'leaflet.markercluster.css', 'membermap' ) );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( '__app_membermap' );

        /* Update session location */
        \IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap', 'front', 'membermap' ), array(), 'loc_membermap_viewing_membermap' );

        /* Things we need to know in the Javascript */
        \IPS\Output::i()->jsVars = array_merge( \IPS\Output::i()->jsVars, array(
        	'canModerateMap'				=> \IPS\Member::loggedIn()->modPermission( 'can_delete_membermap_marker' ) ?: 0,
			'member_id'						=> \IPS\Member::loggedIn()->member_id ?: 0,
			'member_group'					=> \IPS\Member::loggedIn()->member_group_id ?: 0,
			'membermap_canAdd'				=> $canAdd ?: 0,
        	'membermap_canEdit'				=> $canEdit ?: 0,
        	'membermap_canDelete'			=> $canDelete ?: 0,
        	'membermap_cacheTime'			=> $cacheTime,
			'membermap_bbox'				=> json_decode( \IPS\Settings::i()->membermap_bbox ),
			'membermap_bbox_zoom'			=> \intval( \IPS\Settings::i()->membermap_bbox_zoom ),
			'membermap_defaultMaps'			=> $defaultMaps,
			'membermap_enable_clustering' 	=> \IPS\Settings::i()->membermap_enable_clustering == 1 ? 1 : 0,
			'membermap_groupByMemberGroup'	=> \IPS\Settings::i()->membermap_groupByMemberGroup == 1 ? 1 : 0,
			'membermap_onlyShowGroup'		=> \IPS\Request::i()->group ? explode( ',', mb_strtolower( \IPS\Request::i()->group ) ) : array(),
			'membermap_showNightAndDay'		=> \IPS\Settings::i()->membermap_showNightAndDay == 1 ?: 0,
			'membermap_membersIFollow'		=> iterator_to_array( \IPS\Db::i()->select( 'follow_rel_id', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_member_id=?', 'core', 'member', \IPS\Member::loggedIn()->member_id ) ) )
        ));


        \IPS\Output::i()->endBodyCode .= <<<EOF
		<script type='text/javascript'>
			ips.membermap.initMap();
		</script>
EOF;

		if ( \IPS\Settings::i()->membermap_showMemberList AND \count( $markers ) !== 1 )
		{
			\IPS\Output::i()->sidebar['contextual'] = ( isset( \IPS\Output::i()->sidebar['contextual'] ) ? \IPS\Output::i()->sidebar['contextual'] : "" ) . \IPS\Theme::i()->getTemplate( 'map' )->memberList();
		}

        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'map' )->showMap( $markers, $cacheTime, $canAdd, $canEdit, $club );
	}

	/**
	 * Loads add/update location form
	 *
	 * @return	void
	 */
	protected function add()
	{
		$existing = NULL;

		if ( \IPS\Member::loggedIn()->member_id )
		{
			/* Get the members location, if it exists */
			$existing = \IPS\membermap\Map::i()->getMarkerByMember( \IPS\Member::loggedIn()->member_id, FALSE );
		}

		$groupId = \IPS\membermap\Map::i()->getMemberGroupId();

		/* Check permissions */
		if ( $existing )
		{
			if ( ! $existing->canEdit() )
			{
				\IPS\Output::i()->error( 'membermap_error_cantEdit', '2MM3/2', 403, '' );
			}
		}
		else if ( ! \IPS\membermap\Markers\Groups::load( $groupId )->can( 'add' ) )
		{
			\IPS\Output::i()->error( 'membermap_error_cantAdd', '2MM3/3', 403, '' );
		}

		/* HTML5 GeoLocation form */
		$geoLocForm =  new \IPS\Helpers\Form( 'membermap_form_geoLocation', NULL, NULL, array( 'id' => 'membermap_form_geoLocation' ) );
		$geoLocForm->class = 'ipsForm_vertical ipsType_center';

		$geoLocForm->addHeader( 'membermap_current_location' );
		$geoLocForm->addHtml( '<li class="ipsType_center"><i class="fa fa-fw fa-4x fa-location-arrow"></i></li>' );
		$geoLocForm->addHtml( '<li class="ipsType_center">' . \IPS\Member::loggedIn()->language()->addToStack( 'membermap_geolocation_desc' ) . '</li>' );
		$geoLocForm->addButton( 'membermap_current_location', 'button', NULL, 'ipsButton ipsButton_primary', array( 'id' => 'membermap_currentLocation' ) );


		$form = new \IPS\Helpers\Form( 'membermap_form_location', NULL, NULL, array( 'id' => 'membermap_form_location' ) );
		$form->class = 'ipsForm_vertical ipsType_center ipsPos_center';

		$form->addHeader( 'membermap_form_location' );

		$form->add( new \IPS\Helpers\Form\Text( 'membermap_location', '', FALSE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack( 'membermap_form_placeholder' ) ), NULL, NULL, NULL, 'membermap_location' ) );

		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$form->addHeader( 'guest_email' );
			$form->add( new \IPS\Helpers\Form\Email( 'guest_email', NULL, TRUE, array( 'accountEmail' => TRUE ), NULL, NULL, NULL, 'guest_email' ) );
			
			if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
			{
				$form->add( new \IPS\Helpers\Form\Captcha );
			}
		}

		$form->addButton( 'save', 'submit', NULL, 'ipsPos_center ipsButton ipsButton_primary', array( 'id' => 'membermap_locationSubmit' ) );

		$form->hiddenValues['lat'] = \IPS\Request::i()->lat;
		$form->hiddenValues['lng'] = \IPS\Request::i()->lng;

		if ( $values = $form->values() )
		{
			$member = \IPS\Member::loggedIn();

			try
			{
				/* Create marker */
				if ( $existing instanceof \IPS\membermap\Markers\Markers )
				{
					$marker = $existing;
					$marker->updated = time();
				}
				else
				{
					$marker = \IPS\membermap\Markers\Markers::createItem( $member, \IPS\Request::i()->ipAddress(), new \IPS\DateTime, \IPS\membermap\Markers\Groups::load( $groupId ) );
					$marker->member_id = (int) $member->member_id;

					$marker->processForm( $values );
				}

				$marker->location = ( isset( $values['membermap_location'] ) AND ! empty( $values['membermap_location'] ) ) ? $values['membermap_location'] : "";
				
				$marker->name 	= !$member->member_id ? 'Created as guest' : $member->name;
				$marker->lat 	= $values['lat'];
				$marker->lon 	= $values['lng'];
				$marker->save();

				/* Add to search index */
				\IPS\Content\Search\Index::i()->index( $marker );

				/* Content approval is required, redirect the member to the marker page, where this is made clear */
				try
				{
					if ( $marker->hidden() === -3 )
					{
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register' ) );
					}
					elseif ( !\IPS\Member::loggedIn()->member_id and $marker->hidden() )
					{
						\IPS\Output::i()->redirect( $marker->container()->url(), 'mod_queue_message' );
					}
					else if ( $marker->hidden() == 1 )
					{
						\IPS\Output::i()->redirect( $marker->url(), 'mod_queue_message' );
					}
					else
					{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=membermap&module=membermap&controller=showmap&dropBrowserCache=1&goHome=1', 'front', 'membermap' ) );
					}
				}
				catch ( \DomainException $e )
				{
					$form->error = $e->getMessage();
				}

				return;
			}
			catch( \Exception $e )
			{
				$form->error	= \IPS\Member::loggedIn()->language()->addToStack( 'membermap_' . $e->getMessage() );
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'map' )->addLocation( $geoLocForm, $form );
				return;
			}
		}

		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack( ( ! $existing ? 'membermap_button_addLocation' : 'membermap_button_editLocation' ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'map' )->addLocation( $geoLocForm, $form );
	}

	/**
	 * Delete a marker
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Session::i()->csrfCheck();

		if ( ! \IPS\Member::loggedIn()->member_id OR ! \intval( \IPS\Request::i()->member_id ) )
		{
			\IPS\Output::i()->error( 'no_permission', '2MM3/4', 403, '' );
		}

		/* Get the marker */
		$existing = \IPS\membermap\Map::i()->getMarkerByMember( \intval( \IPS\Request::i()->member_id ), FALSE );

		if ( isset( $existing ) AND $existing->canDelete() )
		{
			$existing->delete();
			\IPS\Output::i()->json( 'OK' );
		}

		/* Fall back to a generic error */
		\IPS\Output::i()->error( 'no_permission', '2MM3/5', 403, '' );
	}

	/**
	 * Embed map. Used in users profile
	 * 
	 * @return void
	 */
	protected function embed()
	{
		$this->manage();

		\IPS\Output::i()->title = NULL;
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
	}
}