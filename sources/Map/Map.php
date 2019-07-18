<?php
/**
 * @brief       Member Map Library
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       20 Oct 2015
 * @version     -storm_version-
 */

namespace IPS\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
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
			$classname = \get_called_class();
			static::$instance = new $classname;
		}
		
		return static::$instance;
	}

	/**
	 * Get the marker group ID for member markers
	 * 
	 * @return int Group ID
	 */
	public function getMemberGroupId()
	{
		static $groupId = null;

		if ( $groupId !== null )
		{
			return $groupId;
		}

		/* Get from cache */
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
				/* No group exists. Need to create one then */
				$memberGroup = new \IPS\membermap\Markers\Groups;
				$memberGroup->name 			= "Members";
				$memberGroup->name_seo 		= "members";
				$memberGroup->protected 	= 1;
				$memberGroup->type 			= "member";
				$memberGroup->pin_colour 	= "#FFFFFF";
				$memberGroup->pin_bg_colour = "darkblue";
				$memberGroup->pin_icon 		= "fa-user";
				$memberGroup->position 		= 1;

				$memberGroup->save();

				/* Add in permissions */
				$groups	= array_filter( iterator_to_array( \IPS\Db::i()->select( 'g_id', 'core_groups' ) ), function( $groupId ) 
				{
					if( $groupId == \IPS\Settings::i()->guest_group )
					{
						return FALSE;
					}

					return TRUE;
				});

				$default = implode( ',', $groups );

				\IPS\Db::i()->insert( 'core_permission_index', array(
		             'app'			=> 'membermap',
		             'perm_type'	=> 'membermap',
		             'perm_type_id'	=> $memberGroup->id,
		             'perm_view'	=> '*', # view
		             'perm_2'		=> '*', # read
		             'perm_3'		=> $default, # add
		             'perm_4'		=> $default, # comment
		             'perm_5'		=> $default, # review
		        ) );

				\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$memberGroup->id}", trim( $memberGroup->name ) );
				\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$memberGroup->id}_JS", trim( $memberGroup->name ), 1 );

				$groupId = $memberGroup->id;
			}

			\IPS\Data\Store::i()->membermap_memberGroupId = $groupId;
		}

		return $groupId;
	}

	/**
	 * Get a single member's location
	 * 
	 * @param 		int 	Member ID
	 * @param    	bool 	Format marker. $loadMemberData needs to be TRUE for this to happen
	 * @param 		bool 	Load member and group data
	 * @return		mixed 	Members location record, or false if non-existent
	 */
	public function getMarkerByMember( $memberId, $format=TRUE, $loadMemberdata=TRUE )
	{
		static $marker = array();
		if ( ! \intval( $memberId ) )
		{
			return false;
		}

		if( isset( $marker[ $memberId . '-' . ( $format ? '1' : '0' ) ] ) )
		{
			$_marker = $marker[ $memberId . '-' . ( $format ? '1' : '0' ) ];
		}
		else
		{
			try
			{
				$groupId = $this->getMemberGroupId();

				$db = \IPS\Db::i()->select( '*', array( 'membermap_markers', 'mm' ), array( 'mm.marker_member_id=? AND mm.marker_parent_id=?', \intval( $memberId ), \intval( $groupId ) ) );

				if ( $loadMemberdata )
				{
					$db->join( array( 'core_members', 'm' ), 'mm.marker_member_id=m.member_id' );
					$db->join( array( 'core_groups', 'g' ), 'm.member_group_id=g.g_id' );
				}
				
				$_marker = $db->first();

				if ( ! $format OR ! $loadMemberdata )
				{
					$_marker = \IPS\membermap\Markers\Markers::constructFromData( $_marker );
				}

				$marker[ $memberId . '-' . ( $format ? '1' : '0' ) ] = $_marker;
						
			}
			catch( \UnderflowException $e )
			{
				return false;
			}
		}
		
		return ( $format AND $loadMemberdata ) ? $this->formatMemberMarkers( array( $_marker ) ) : $_marker;
	}

	/**
	 * Get Club member markers 
	 * 
	 * @param 		object 	Club
	 * @return		mixed 	Member markers, or false
	 */
	public function getClubMemberMarkers( $club )
	{
		try
		{
			$groupId = $this->getMemberGroupId();

			$clubMembers = iterator_to_array( \IPS\Db::i()->select( 'member_id', 'core_clubs_memberships', array( array( 'club_id=?', $club->id ), array( \IPS\Db::i()->in( 'status', array( 'member', 'moderator', 'leader' ) ) ) ) ) );

			$db = \IPS\Db::i()->select( '*', array( 'membermap_markers', 'mm' ), array( array( 'mm.marker_parent_id=?', $groupId ), array( \IPS\Db::i()->in( 'mm.marker_member_id', $clubMembers ) ) ) );

			$db->join( array( 'core_members', 'm' ), 'mm.marker_member_id=m.member_id' );
			$db->join( array( 'core_groups', 'g' ), 'm.member_group_id=g.g_id' );
			
			
			$_markers = $this->formatMemberMarkers( iterator_to_array( $db ) );

			/* Get the club's location */
			if ( $club->location_lat !== NULL )
			{
				$clubMarker =  array( array(
					'appName'				=> \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'membermap_marker_group_Clubs' ),
					'popup' 				=> \IPS\Theme::i()->getTemplate( 'clubs', 'core', 'front' )->mapPopup( $club ),
					'marker_lat'			=> $club->location_lat,
					'marker_lon'			=> $club->location_long,
					'group_pin_bg_colour'	=> \IPS\Settings::i()->membermap_clubs_bgcolour ?: "orange",
					'group_pin_colour'		=> \IPS\Settings::i()->membermap_clubs_colour ?: "#ffffff",
					'group_pin_icon'		=> \IPS\Settings::i()->membermap_clubs_icon ?: 'fa-users',
				) );

				$_markers = array_merge( $this->formatCustomMarkers( $clubMarker ), $_markers );
			}

			return $_markers;
					
		}
		catch( \UnderflowException $e )
		{
			return false;
		}
	}

	/**
	 * Get the associated club
	 *
	 * @return	\IPS\Member\Club|NULL
	 */
	public function club()
	{
		if ( \IPS\Settings::i()->clubs )
		{
			if ( isset( \IPS\Request::i()->clubId ) AND \intval( \IPS\Request::i()->clubId ) )
			{
				try
				{
					return \IPS\Member\Club::load( \intval( \IPS\Request::i()->clubId ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}

		return NULL;
	}

	/**
	 * Geocode, get lat/lng by location
	 *
	 * @param 	string 	Location
	 * @param 	bool 	Request coming from a task?
	 * @return 	array 	Lat/lng/formatted address
	*/
	public function getLatLng( $location, $fromTask = FALSE )
	{
		static $locCache = array();
		$locKey = md5( $location );

		if( isset( $locCache[ 'cache-' . $locKey ] ) )
		{
			return $locCache[ 'cache-' . $locKey ];
		}

		$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );

		/* 
			HTTP Language 
			Defaults to 'en', unless the constant 'MEMBERMAP_HTTP_LANGUAGE' is defined to something else.
			Uses the browser's "HTTP_ACCEPT_LANGUAGE" unless the request is coming from a task, 
			where the request could be triggered by a russian crawler, causing all your results to be in cyrillic.
		*/
		$language = 'en'; 
		if ( \defined( 'MEMBERMAP_HTTP_LANGUAGE' ) AND MEMBERMAP_HTTP_LANGUAGE )
		{
			$language = MEMBERMAP_HTTP_LANGUAGE;
		}
		
		if ( ! $fromTask AND isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
		{
			$language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		}

		if ( $apiKey )
		{
			$queryString = array(
				'key' 				=> $apiKey, 
				'format' 			=> 'json', 
				'q' 				=> $location,
				'accept-language' 	=> $language,
				'debug' 			=> 0
			);

			if ( mb_strlen( \IPS\Settings::i()->membermap_restrictCountries ) >= 2 )
			{
				$queryString['countrycodes'] = \IPS\Settings::i()->membermap_restrictCountries;
			}

			try
			{
				$url 	= \IPS\Http\Url::external( "https://open.mapquestapi.com/nominatim/v1/search.php" )->setQueryString( $queryString );
				$data 	= $url->request()->get();
				$json 	= $data->decodeJson();


				if ( \is_array( $json ) AND \count( $json ) )
				{
					$locCache[ 'cache-' . $locKey ] = array(
						'lat'		=> $json[0]['lat'],
						'lng'		=> $json[0]['lon'],
						'location'	=> $json[0]['display_name'],
					);

					return $locCache[ 'cache-' . $locKey ];
				}
				else
				{
					/* No result for this */
					return $locCache[ 'cache-' . $locKey ] = false;
				}
			}
			/* \RuntimeException catches BAD_JSON and \IPS\Http\Request\Exception both */
			catch ( \RuntimeException $e )
			{
				\IPS\Log::log( $e, 'membermap' );

				return false;
			}
		}

		return false;
	}

	/** 
	 * Check if cache is up to date, and Ok
	 *
	 * @return 	bool 	TRUE when OK, FALSE when rewrite was needed
	 */
	public function checkForCache()
	{
		$cacheTime 	= isset( \IPS\Data\Store::i()->membermap_cacheTime ) ? \IPS\Data\Store::i()->membermap_cacheTime : 0;

		/* Rebuild JSON cache if needed */
		if ( ! isset( \IPS\Data\Store::i()->membermap_cache_0 ) OR \IPS\Request::i()->rebuildCache === '1' OR $cacheTime === 0 )
		{
			$this->recacheJsonFile();

			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Invalidate (delete) JSON cache
	 * There are situations like mass-move or mass-delete where the cache is rewritten for every single node that's created.
	 * This will force the cache to rewrite itself on the next page load
	 *
	 * @return void
	 */
	public function invalidateJsonCache()
	{
		/* Just reset cachetime to 0. checkForCache() will deal with the actual recaching on the next load */
		\IPS\Data\Store::i()->membermap_cacheTime = 0;

	}

	/**
	 * Delete cache files 
	 *
	 * @return void
	 */
	public function deleteCacheFiles()
	{
		/* Remove all files from cache dir. 
		 * We need to do this in case of situations were a file won't be overwritten (when deleting markers), 
		 * and old markers will be left in place, or markers are shown multiple times.*/
		$fileCount = 0;
		while( TRUE )
		{
			$cacheKey = "membermap_cache_{$fileCount}";
			$fileCount++;
			if ( isset( \IPS\Data\Store::i()->$cacheKey ) )
			{
				unset( \IPS\Data\Store::i()->$cacheKey );
			}
			else
			{
				break;
			}
		}
	}

	/**
	 * Rewrite cache file
	 * 
	 * @return	array	Parsed list of markers
	 */
	public function recacheJsonFile()
	{
		/* https://bugs.php.net/bug.php?id=72567 */
		ini_set('serialize_precision', 14);

		/* The upgrader kept firing this off whenever a group/marker was saved. */
		if ( isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'applications' )
		{
			return;
		}

		$totalMarkers = 0;
		$memberMarkers = array();
		$customMarkers = array();

		try
		{			
			$totalMarkers = \IPS\Db::i()->select( 'COUNT(*)', 'membermap_markers' )->first();
		}
		catch( \Exception $ex )
		{
		}

		/* Trigger the queue if the marker count is too large to do in one go. */
		/* We'll hardcode the cap at 4000 now, that consumes roughly 50MB */
		/* We'll also see if we have enough memory available to do it */
		/* memory_get_usage() may not return a sensible value, so we set the lower entrypoint to even consider using the queue to 1000 */

		if ( $totalMarkers < 1000 )
		{
			$currentMemUsage 	= \memory_get_usage();
			$memoryLimit 		= \intval( ini_get( 'memory_limit' ) );

			$useQueue 			= FALSE;
			if ( $memoryLimit > 0 )
			{
				$howMuchAreWeGoingToUse = $totalMarkers * 0.01; /* ~0.01MB pr marker */
				$howMuchAreWeGoingToUse += 10; /* Plus a bit to be safe */

				$howMuchDoWeHaveLeft = $memoryLimit - ceil( ( $currentMemUsage / 1024 / 1024 ) );

				if ( $howMuchDoWeHaveLeft < $howMuchAreWeGoingToUse )
				{
					$useQueue = TRUE;
				}
			}

			if ( $totalMarkers > 4000 )
			{
				$useQueue = TRUE;
			}
		}

		if ( $useQueue AND \defined( 'MEMBERMAP_FORCE_QUEUE' ) AND ! MEMBERMAP_FORCE_QUEUE )
		{
			$useQueue = FALSE;
		}

		if ( $useQueue OR ( \defined( 'MEMBERMAP_FORCE_QUEUE' ) AND MEMBERMAP_FORCE_QUEUE ) )
		{
			\IPS\Task::queue( 'membermap', 'RebuildCache', array( 'class' => '\IPS\membermap\Map' ), 1, array( 'class' ) );
			return;
		}


		$selectColumns = array( 'mm.*', 'mg.*', 'm.member_id', 'm.name', 'm.members_seo_name', 'm.member_group_id', 'm.pp_photo_type', 'm.pp_main_photo', 'm.pp_thumb_photo', 'm.timezone', 'pi.perm_2 as viewPerms' );

		/* Remember to update the queue too */
		$_markers = \IPS\Db::i()->select( implode( ',', $selectColumns ), array( 'membermap_markers', 'mm' ), array( 'marker_open=1' ), 'mg.group_position ASC, mm.marker_name ASC' )
					->join( array( 'membermap_markers_groups', 'mg' ), 'mm.marker_parent_id=mg.group_id' )
					->join( array( 'core_permission_index', 'pi' ), "( pi.perm_type_id=mg.group_id AND pi.app='membermap' AND pi.perm_type='membermap' )" )
					->join( array( 'core_members', 'm' ), 'mm.marker_member_id=m.member_id' );

		foreach( $_markers as $marker )
		{
			if ( $marker['group_type'] == 'member' )
			{
				$memberMarkers[] = $marker;
			}
			else
			{
				$customMarkers[] = $marker;
			}
		}

		/* Get from extensions */
		$extensions = \IPS\Application::allExtensions( 'membermap', 'Mapmarkers', FALSE );

		foreach ( $extensions as $k => $class )
		{
			$appMarkers = $class->getLocations();
			
			if ( \is_array( $appMarkers ) AND \count( $appMarkers ) )
			{
				/* Set 'appName' if it isn't already */
				array_walk( $appMarkers, function( &$v, $key ) use ( $k )
				{
					if ( ! $v['appName'] )
					{
						$appName = substr( $k, strpos( $k, '_' ) + 1 );
						$v['appName'] = $appName;
					}
				} );

				$customMarkers = array_merge( $customMarkers, $class->getLocations() );
			}
		}

		/* Format markers */
		$markers = $this->formatMemberMarkers( $memberMarkers );
		$custMarkers = $this->formatCustomMarkers( $customMarkers );

		$markers = array_merge( $markers, $custMarkers );

		/* Split into decent chuncks */
		$markers = array_chunk( $markers, 500 );
		
		$this->deleteCacheFiles();
		
		$fileCount = 0;
		foreach( $markers as $chunk )
		{
			$cacheKey = "membermap_cache_{$fileCount}";
			\IPS\Data\Store::i()->$cacheKey = 
				array( 
					'markers' 					=> $chunk,
					'memUsage' 					=> round( ( \memory_get_usage() - $currentMemUsage ) / 1024, 2 ) . 'kB',
					'howMuchAreWeGoingToUse' 	=> $howMuchAreWeGoingToUse,
					'howMuchDoWeHaveLeft'		=> $howMuchDoWeHaveLeft,
			);
			
			$fileCount++;
		}

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
		$groupCache = \IPS\Data\Store::i()->groups;

		/* Get staff groups */
		$_staff = iterator_to_array( \IPS\Db::i()->select( '*', 'core_leaders' ) );
		$staff 	= array();
		foreach( $_staff as $s )
		{
			$staff[ $s['leader_type'] ][ $s['leader_type_id'] ] = $s;
		}

		if ( \is_array( $markers ) AND \count( $markers ) )
		{
			foreach( $markers as $marker )
			{
				/* Member don't exists or lat/lon == 0 (Middle of the ocean) */
				if ( $marker['member_id'] === NULL OR ( $marker['marker_lat'] == 0 AND $marker['marker_lon'] == 0 ) )
				{
					//\IPS\Db::i()->delete( 'membermap_markers', array( 'marker_id=?', $marker['marker_id'] ) );
					\IPS\membermap\Markers\Markers::constructFromData( $marker )->delete();
					continue;
				}

				$photo = \IPS\Member::photoUrl( $marker );

				try
				{
					$groupName = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'core_group_' . $marker['member_group_id'] );
				}
				catch ( \UnderflowException $e )
				{
					$groupName = '';
				}

				if ( isset( $groupCache[ $marker['member_group_id'] ]['g_membermap_markerColour'] ) )
				{
					$markerColour = $groupCache[ $marker['member_group_id'] ]['g_membermap_markerColour'];
				}
				else
				{
					$markerColour = 'darkblue';
				}

				$isStaff = FALSE;

				if ( isset( $staff['g'][ $marker['member_group_id'] ] ) )
				{
					$isStaff = TRUE;
				}
				elseif ( isset( $staff['m'][ $marker['marker_member_id'] ] ) )
				{
					$isStaff = TRUE;
				}

				$markersToKeep[] = array(
					'id'			=> $marker['marker_id'],
					'ext'			=> '',
					'type'			=> "member",
					'lat' 			=> round( (float)$marker['marker_lat'], 5 ),
					'lon' 			=> round( (float)$marker['marker_lon'], 5 ),
					'member_id'		=> $marker['marker_member_id'],
					'member_name'	=> $marker['marker_name'],
					'parent_id'		=> $marker['member_group_id'],
					'parent_name'	=> $groupName,
					'popup' 		=> '',
					'markerColour' 	=> $markerColour,
					'viewPerms'		=> ( ! isset( $marker['viewPerms'] ) OR $marker['viewPerms'] === '*' OR $marker['viewPerms'] === NULL ) ? '*' : array_map( 'intval', explode( ',', $marker['viewPerms'] ) ),
					'isStaff'		=> $isStaff,
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

		if ( \is_array( $markers ) AND \count( $markers ) )
		{
			foreach( $markers as $marker )
			{
				$popup = "";
				if(  isset( $marker['popup'] ) )
				{
					$popup = $marker['popup'];
					\IPS\Output::i()->parseFileObjectUrls( $popup );
					\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $popup );
				}


				$markersToKeep[] = array(
					'id'			=> $marker['marker_id'],
					'ext'			=> isset( $marker['ext'] ) ? $marker['ext'] : '',
					'type'			=> "custom",
					'lat' 			=> round( (float)$marker['marker_lat'], 5 ),
					'lon' 			=> round( (float)$marker['marker_lon'], 5 ),
					'popup' 		=> $popup,
					'icon'			=> $marker['group_pin_icon'],
					'colour'		=> $marker['group_pin_colour'],
					'bgColour'		=> \in_array( $marker['group_pin_bg_colour'], $validColours ) ? $marker['group_pin_bg_colour'] : 'red',
					'parent_id' 	=> isset( $marker['marker_parent_id'] ) ? $marker['marker_parent_id'] : NULL,
					'from_app'		=> isset( $marker['appName'] ) ? TRUE : FALSE,
					'appName'		=> isset( $marker['appName'] ) ? $marker['appName'] : NULL,
					'expiryDate'	=> isset( $marker['expiryDate'] ) ? $marker['expiryDate'] : NULL,
					'viewPerms'		=> ( ! isset( $marker['viewPerms'] ) OR $marker['viewPerms'] === '*' OR $marker['viewPerms'] === NULL ) ? '*' : array_map( 'intval', explode( ',', $marker['viewPerms'] ) ),
				);
			}
		}

		return $markersToKeep;
	}
}