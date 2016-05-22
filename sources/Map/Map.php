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
	public function getLatLng( $location )
	{
		static $locCache = array();
		$locKey = md5( $location );

		if( isset( $locCache[ 'cache-' . $locKey ] ) )
		{
			return $locCache[ 'cache-' . $locKey ];
		}


		$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );

		if ( $apiKey )
		{
			try
			{
				$data = \IPS\Http\Url::external( 
					( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' ) . "open.mapquestapi.com/nominatim/v1/search.php?key={$apiKey}&format=json&limit=1&q=" . urlencode( $location ) )->request( 5 )->get()->decodeJson();

				if ( is_array( $data ) AND count( $data ) )
				{
					$locCache[ 'cache-' . $locKey ] = array(
						'lat'		=> $data[0]['lat'],
						'lng'		=> $data[0]['lon'],
						'location'	=> $data[0]['display_name'],
					);

					return $locCache[ 'cache-' . $locKey ];
				}
				else
				{
					/* No result for this */
					$locCache[ 'cache-' . $locKey ] = false;
				}
			}
			catch( \RuntimeException $e )
			{
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
		if ( ! is_file ( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-0.json' ) OR \IPS\Request::i()->rebuildCache === '1' OR $cacheTime === 0 )
		{
			$this->recacheJsonFile();

			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Invalidate (delete) JSON cache
	 * There are situations like mass-move or mass-delete where the cache is rewritten for every single node that's created.
	 * This will force the cache to rewrite itself on the next pageload
	 *
	 * @return void
	 */
	public function invalidateJsonCache()
	{
		\IPS\Data\Store::i()->membermap_cacheTime = 0;

		/* Just reset cachetime to 0. checkForCache() will deal with the actual recaching on the next load */
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
		foreach( glob( \IPS\ROOT_PATH . '/datastore/membermap_cache/*' ) as $file )
		{
			if ( is_file( $file ) )
			{
				unlink( $file );
			}
		}

		/* Check if we even have a 'datastore' folder. */
		if ( ! is_dir( \IPS\ROOT_PATH . '/datastore' ) )
		{
			mkdir( \IPS\ROOT_PATH . '/datastore' );
			chmod( \IPS\ROOT_PATH . '/datastore', \IPS\IPS_FOLDER_PERMISSION );
		}

		if ( ! is_dir( \IPS\ROOT_PATH . '/datastore/membermap_cache' ) )
		{
			mkdir( \IPS\ROOT_PATH . '/datastore/membermap_cache' );
			chmod( \IPS\ROOT_PATH . '/datastore/membermap_cache', \IPS\IPS_FOLDER_PERMISSION );
		}
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

		$currentMemUsage 	= memory_get_usage( TRUE );
		$memoryLimit 		= intval( ini_get( 'memory_limit' ) );
		
		$useQueue 			= false;
		if ( $memoryLimit > 0 )
		{
			$howMuchAreWeGoingToUse = $totalMarkers * 0.02; /* ~0.02MB pr marker */
			$howMuchAreWeGoingToUse += 10; /* Plus a bit to be safe */

			$howMuchDoWeHaveLeft = $memoryLimit - ceil( ( $currentMemUsage / 1024 / 1024 ) );

			if ( $howMuchDoWeHaveLeft < $howMuchAreWeGoingToUse )
			{
				$useQueue = true;
			}
		}

		if ( $totalMarkers > 4000 )
		{
			$useQueue = true;
		}

		if ( $useQueue OR ( defined( 'MEMBERMAP_FORCE_QUEUE' ) and MEMBERMAP_FORCE_QUEUE ) )
		{
			\IPS\Task::queue( 'membermap', 'RebuildCache', array( 'class' => '\IPS\membermap\Map' ), 1, array( 'class' ) );
			return;
		}


		$selectColumns = array( 'mm.*', 'mg.*', 'm.member_id', 'm.name', 'm.members_seo_name', 'm.member_group_id', 'm.pp_photo_type', 'm.pp_main_photo', 'm.pp_thumb_photo' );
		
		if ( \IPS\Settings::i()->allow_gravatars )
		{
			$selectColumns[] = 'm.pp_gravatar';
			$selectColumns[] = 'm.email';
			$selectColumns[] = 'm.members_bitoptions';
		}

		/* Remember to update the queue too */
		$_markers = \IPS\Db::i()->select( implode( ',', $selectColumns ), array( 'membermap_markers', 'mm' ), array(), 'mg.group_position ASC, mm.marker_id DESC' )
					->join( array( 'membermap_markers_groups', 'mg' ), 'mm.marker_parent_id=mg.group_id' )
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

		$markers = $this->formatMemberMarkers( $memberMarkers );

		$custMarkers = $this->formatCustomMarkers( $customMarkers );

		$markers = array_merge( $markers, $custMarkers );

		$markers = array_chunk( $markers, 500 );
		
		$this->deleteCacheFiles();
		
		$fileCount = 0;
		foreach( $markers as $chunk )
		{

			touch( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileCount . '.json' );
			chmod( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileCount . '.json', \IPS\IPS_FILE_PERMISSION );
			\file_put_contents( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileCount . '.json', 
				json_encode( 
					array( 
						'markers' => $chunk,
						'memUsage' => ( (memory_get_usage( TRUE ) - $currentMemUsage ) / 1024 ) . 'kB',
					) 
				)
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

		if ( is_array( $markers ) AND count( $markers ) )
		{
			foreach( $markers as $marker )
			{
				if ( $marker['marker_lat'] == 0 AND $marker['marker_lon'] == 0 )
				{
					\IPS\Db::i()->delete( 'membermap_markers', array( 'marker_id=?', $marker['marker_id'] ) );
					
					continue;
				}

				$photo = \IPS\Member::photoUrl( $marker );

				$markersToKeep[] = array(
					'type'			=> "member",
					'lat' 			=> round( (float)$marker['marker_lat'], 5 ),
					'lon' 			=> round( (float)$marker['marker_lon'], 5 ),
					'member_id'		=> $marker['marker_member_id'],
					'parent_id'		=> $marker['member_group_id'],
					'parent_name'	=> \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'core_group_' . $marker['member_group_id'] ),
					'popup' 		=> \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->popupContent( $marker, $photo ),
					'markerColour' 	=> $groupCache[ $marker['member_group_id'] ]['g_membermap_markerColour'] ?: 'darkblue',
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
					'lat' 			=> round( (float)$marker['marker_lat'], 5 ),
					'lon' 			=> round( (float)$marker['marker_lon'], 5 ),
					'popup' 		=> $popup,
					'icon'			=> $marker['group_pin_icon'],
					'colour'		=> $marker['group_pin_colour'],
					'bgColour'		=> in_array( $marker['group_pin_bg_colour'], $validColours ) ? $marker['group_pin_bg_colour'] : 'red',
					'parent_id' 	=> $marker['marker_parent_id'],
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