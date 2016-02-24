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

	/**
	 * Save marker to database
	 * 
	 * @param 	array 	$data
	 * @return	bool	
	 */
	public function saveMarker( $data )
	{
		if ( ! $data['member_id'] )
		{
			throw new \Exception( 'invalid_data' );
		}

		if ( $data['lat'] AND $data['lng'] )
		{
			$lat = $data['lat'];
			$lng = $data['lng'];
		}
		else
		{
			throw new \Exception( 'invalid_data' );
		}

		if ( $lat == 0 AND $lng == 0 )
		{
			throw new \Exception( 'invalid_data' );
		}
		
		$save = array(
			'member_id'			=> $data['member_id'],
			'lat'				=> $this->_floatVal( $lat ),
			'lon'				=> $this->_floatVal( $lng ),
			'marker_date'		=> time()
		);

		
		\IPS\Db::i()->replace( 'membermap_members', $save, 'member_id=' . $data['member_id'] );

		$this->recacheJsonFile();
	}


	/**
	 * Delete a marker
	 *
	 * @param 		int 	Member ID
	 * @return 		bool
	 */
	public function deleteMarker( $memberId )
	{
		$memberId = intval( $memberId );

		\IPS\Db::i()->delete( 'membermap_members', 'member_id=' . $memberId );

		$this->recacheJsonFile();
	}

	/**
	 * Get a single member's location
	 * 
	 * @param 		int 	Member ID
	 * @return		mixed 	Members location record, or false if non-existent
	 */
	public function getMarkerByMember( $memberId )
	{
		if ( ! intval( $memberId ) )
		{
			return false;
		}

		try
		{
			$marker = \IPS\Db::i()->select( '*', 'membermap_members', array( 'membermap_members.member_id=?', intval( $memberId ) ) )
					->join( 'core_members', 'membermap_members.member_id=core_members.member_id' )
					->join( 'core_groups', 'core_members.member_group_id=core_groups.g_id' )
					->first();
					
			return $this->formatMarkers( array( $marker ) );
		}
		catch( \UnderflowException $e )
		{
			return false;
		}
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
	 * Rewrite cache file
	 * 
	 * @return	array	Parsed list of markers
	 */
	public function recacheJsonFile()
	{	
		$markers = array();
		
		$dbMarkers = iterator_to_array( 
						\IPS\Db::i()->select( 'membermap_members.*,  core_members.*, core_groups.g_membermap_markerColour', 'membermap_members' )
							->join( 'core_members', 'membermap_members.member_id=core_members.member_id' )
							->join( 'core_groups', 'core_members.member_group_id=core_groups.g_id' )
		);

		$markers = $this->formatMarkers( $dbMarkers );


		$customMarkers = iterator_to_array( 
						\IPS\Db::i()->select( 'membermap_cmarkers.*,  membermap_cmarkers_groups.*', 'membermap_cmarkers' )
							->join( 'membermap_cmarkers_groups', 'membermap_cmarkers.marker_parent_id=membermap_cmarkers_groups.group_id' )
		);

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
	public function formatMarkers( array $markers )
	{
		$markersToKeep = array();

		if ( is_array( $markers ) AND count( $markers ) )
		{
			foreach( $markers as $marker )
			{
				if ( $marker['lat'] == 0 AND $marker['lon'] == 0 )
				{
					\IPS\Db::i()->delete( 'membermap_members', array( 'member_id=?', $marker['member_id'] ) );
					continue;
				}

				$photo = \IPS\Member::photoUrl( $marker, TRUE );
				
				$markersToKeep[] = array(
					'type'			=> "member",
					'lat' 			=> round( (float)$marker['lat'], 5 ),
					'lon' 			=> round( (float)$marker['lon'], 5 ),
					'member_id'		=> $marker['member_id'],
					'popup' 		=> \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->popupContent( $marker, $photo ),
					'markerColour' 	=> $marker['g_membermap_markerColour'] ?: 'darkblue',
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
					'parent_name' 	=> $marker['group_name'],
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