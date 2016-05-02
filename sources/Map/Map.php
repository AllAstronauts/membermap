<?php
/**
 * @brief		Map Library
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.0
 */

namespace IPS\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Map
{
	protected static $instance = NULL;

	/**
	 * Get instance
	 *
	 * @return	static
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$classname = get_called_class();
			static::$instance = new $classname;
		}
		
		return static::$instance;
	}

	public function getMemberGroupId()
	{
		static $groupId = null;

		if ( $groupId !== null )
		{
			return $groupId;
		}

		if ( isset( \IPS\Data\Store::i()->membermap_memberGroupId ) )
		{
			$groupId = \IPS\Data\Store::i()->membermap_memberGroupId;
		}
		else
		{
			try
			{
				$groupId = \IPS\Db::i()->select( 'group_id', 'membermap_markers_groups', array( 'group_type=?', 'member' ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				/* This shouldn't really happen, but you'll never know. */
				$groupId = \IPS\Db::i()->insert( 'membermap_markers_groups', array(
					'group_name' 		=> "Members",
					'group_name_seo'	=> 'members',
					'group_protected' 	=> 1,
					'group_type'		=> 'member',
					'group_pin_colour'	=> '#FFFFFF',
					'group_pin_bg_colour' 	=> 'darkblue',
					'group_pin_icon'		=> 'fa-user',
					'group_position'		=> 1,
				) );
			}

			\IPS\Data\Store::i()->membermap_memberGroupId = $groupId;
		}

		return $groupId;
	}

	/**
	 * Get a single member's location
	 * 
	 * @param 		int 	Member ID
	 * @return		mixed 	Members location record, or false if non-existent
	 */
	public function getMarkerByMember( $memberId, $format=TRUE )
	{
		static $marker = array();

		if ( ! intval( $memberId ) )
		{
			return false;
		}

		if( isset( $marker[ $memberId ] ) )
		{
			$_marker = $marker[ $memberId ];
		}
		else
		{

			try
			{
				$groupId = $this->getMemberGroupId();

				$_marker = \IPS\Db::i()->select( '*', array( 'membermap_markers', 'mm' ), array( 'mm.marker_member_id=? AND mm.marker_parent_id=?', intval( $memberId ), $groupId ) )
						->join( array( 'core_members', 'm' ), 'mm.marker_member_id=m.member_id' )
						->join( array( 'core_groups', 'g' ), 'm.member_group_id=g.g_id' )
						->first();

				$marker[ $memberId ] = $_marker = \IPS\membermap\Markers\Markers::constructFromData( $_marker );
						
			}
			catch( \UnderflowException $e )
			{
				return false;
			}
		}
		
		return $format ? $this->formatMemberMarkers( array( $_marker ) ) : $_marker;
	}

	/**
     * Get all markers created by (or for) an online member
     */
    public function getMarkersByOnlineMembers()
    {
    	$doEmbed = (bool)\IPS\Request::i()->embed;
		$rows 	 = $blogMarkers = $markers = array();

		$where = array( 
			array( "core_sessions.running_time>?", \IPS\DateTime::create()->sub( new \DateInterval( 'PT60M' ) )->getTimeStamp() ),
			array( "core_sessions.login_type!=?", \IPS\Session\Front::LOGIN_TYPE_SPIDER )
		);
		
		if ( !\IPS\Member::loggedIn()->isAdmin() )
		{
			$where[] = array( "core_sessions.login_type!=?", \IPS\Session\Front::LOGIN_TYPE_ANONYMOUS );
		}

		$where[] = "core_groups.g_hide_online_list=0";

		$results = \IPS\Db::i()->select( 'core_sessions.*', 'core_sessions', $where )
					->join( 'core_groups', 'core_groups.g_id=core_sessions.member_group' );

		
		foreach ( $results as $r )
		{
			$rows[ $r['member_id'] ] = $r;
		}		
		
		if ( ! count( $rows ) )
		{
			return false;
		}
		/*
		 * Get markers
		 */

		$dbMarkers = iterator_to_array( 
						\IPS\Db::i()->select( 
							'membermap_members.*, core_members.name, core_members.member_id, core_members.members_seo_name', 
							'membermap_members', 
							\IPS\Db::i()->in( 'membermap_members.member_id', array_keys( $rows ) ) 
						)
							->join( 'core_members', 'core_members.member_id=membermap_members.member_id' )
		);

		
		
		$markers = $this->formatMarkers( $dbMarkers );
		
		
		return $markers;
	}

	/**
	 * Geocode, get lat/lng by location
	 *
	 * @param 	string 	Location
	 * @return 	array 	Lat/lng/formatted address
	*/
	public static function getLatLng( $location )
	{
		$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );

		if ( $apiKey )
		{
			try
			{
				$data = \IPS\Http\Url::external( 
					( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' ) . "www.mapquestapi.com/geocoding/v1/address?key={$apiKey}&location=" . urlencode( $location . ", Norge" ) )->request( 5 )->get()->decodeJson();
			}
			catch( \RuntimeException $e )
			{
				debug( $e );
				return;
			}
			debug( $data );
		}		

		return;
	}

	/**
	 * Rewrite cache file
	 * 
	 * @return	array	Parsed list of markers
	 */
	public function recacheJsonFile()
	{	
		/* The upgrader kept firing this off whenever a group/marker was saved. */
		if ( isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'applications' )
		{
			return;
		}

		$memberMarkers = array();
		$customMarkers = array();

		foreach( \IPS\membermap\Markers\Groups::roots( NULL ) as $group )
		{
			$_markers = iterator_to_array( \IPS\membermap\Markers\Markers::getItemsWithPermission( 
				array( array( \IPS\membermap\Markers\Markers::$databasePrefix . \IPS\membermap\Markers\Markers::$databaseColumnMap['container'] . '=?', $group->_id ) ), /* $where */
				NULL, /* $order */
				NULL, /* $limit */
				NULL, /* $permissionKey */
				FALSE, /* $includeHiddenItems */
				0, /* $queryFlags */
				new \IPS\Member, /* \IPS\Member */
				TRUE, /* $joinContainer */
				FALSE, /* $joinComments */
				FALSE, /* $joinReviews */
				FALSE,  /* $countOnly */
				NULL, /* $joins */
				TRUE, /* $skipPermission */
				FALSE /* $joinTags */
			) );

			if ( $group->type == 'member' )
			{
				$memberMarkers = array_merge( $memberMarkers, $_markers );
			}
			else
			{
				$customMarkers = array_merge( $customMarkers, $_markers );
			}
		}		

		$markers = $this->formatMemberMarkers( $memberMarkers );

		$custMarkers = $this->formatCustomMarkers( $customMarkers );

		$markers = array_merge( $markers, $custMarkers );

		$markers = array_chunk( $markers, 500 );

		if ( ! is_dir( \IPS\ROOT_PATH . '/datastore/membermap_cache' ) )
		{
			mkdir( \IPS\ROOT_PATH . '/datastore/membermap_cache' );
			chmod( \IPS\ROOT_PATH . '/datastore/membermap_cache', \IPS\IPS_FOLDER_PERMISSION );
		}

		/* Remove all files from cache dir. 
		 * We need to do this in case of situations were a file won't be overwritten (when deleting markers), 
		 * and old markers will be left in place, or markers are shown multiple times.*/
		foreach( glob( \IPS\ROOT_PATH . '/datastore/membermap_cache/*' ) as $file )
		{
			if ( is_file( $file ) )
			{
				unlink( $file );
			}
		}
		
		$fileCount = 1;
		$fileList = array();
		foreach( $markers as $chunk )
		{
			touch( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileCount . '.json' );
			chmod( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileCount . '.json', \IPS\IPS_FILE_PERMISSION );
			\file_put_contents( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileCount . '.json', json_encode( $chunk ) );
			$fileList[] = 'membermap_cache/membermap-' . $fileCount . '.json';
			
			$fileCount++;
		}
		
		/* Build index file */
		$index = array(
			'fileList'		=> $fileList
		);

		if ( ! is_file( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-index.json' ) )
		{
			touch( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-index.json' );
			chmod( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-index.json', \IPS\IPS_FILE_PERMISSION );
		}
		
		\file_put_contents( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-index.json', json_encode( $index ) );

		/* Store the timestamp of the cache to force the browser to purge its local storage */
		\IPS\Data\Store::i()->membermap_cacheTime = time();
		
	}
	
	/**
	 * Do formatation to the array of markers
	 * 
	 * @param 		array 	Markers
	 * @return		array	Markers
	 */
	public function formatMemberMarkers( array $markers )
	{
		$markersToKeep = array();

		if ( is_array( $markers ) AND count( $markers ) )
		{
			foreach( $markers as $marker )
			{
				if ( $marker->lat == 0 AND $marker->lon == 0 )
				{
					if ( $marker instanceof \IPS\membermap\Markers\Markers )
					{
						$marker->delete();
					}
					
					continue;
				}

				$photo = $marker->author()->photo;

				$markersToKeep[] = array(
					'type'			=> "member",
					'lat' 			=> round( (float)$marker->lat, 5 ),
					'lon' 			=> round( (float)$marker->lon, 5 ),
					'member_id'		=> $marker->member_id,
					'parent_id'		=> $marker->author()->member_group_id,
					'parent_name'	=> \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'core_group_' . $marker->author()->member_group_id ),
					'popup' 		=> \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->popupContent( $marker, $photo ),
					'markerColour' 	=> $marker->author()->group['g_membermap_markerColour'] ?: 'darkblue',
				);
			}
		}
		
		return $markersToKeep;
	}

	/**
	 * Do formatation to the array of markers
	 * 
	 * @param 		array 	Markers
	 * @return		array	Markers
	 */
	public function formatCustomMarkers( array $markers )
	{
		$markersToKeep = array();
		$validColours = array( 
			'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
			'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black', 'white'
		);

		if ( is_array( $markers ) AND count( $markers ) )
		{
			foreach( $markers as $marker )
			{
				$popup = \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->customMarkerPopup( $marker );
				\IPS\Output::i()->parseFileObjectUrls( $popup );
				
				$markersToKeep[] = array(
					'type'			=> "custom",
					'lat' 			=> round( (float)$marker->lat, 5 ),
					'lon' 			=> round( (float)$marker->lon, 5 ),
					'popup' 		=> $popup,
					'icon'			=> $marker->container()->pin_icon,
					'colour'		=> $marker->container()->pin_colour,
					'bgColour'		=> in_array( $marker->container()->pin_bg_colour, $validColours ) ? $marker->container()->pin_bg_colour : 'red',
					'parent_id' 	=> $marker->parent_id,
					'parent_name' 	=> \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'membermap_marker_group_' . $marker->parent_id ),
				);
			}
		}

		return $markersToKeep;
	}


	/**
     * Locale friendly floatval() ready for MySQL
     *
     * @param   string  float value
     * @return  integer floated integer
     */
    private function _floatVal($floatString)
    {
        $floatString = floatval($floatString);

        if($floatString)
        {
            $localeInfo = localeconv();
            $floatString = str_replace($localeInfo["thousands_sep"], "", $floatString);
            $floatString = str_replace($localeInfo["decimal_point"], ".", $floatString);
        }
        return $floatString;
    }
}