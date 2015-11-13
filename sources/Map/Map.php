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

class _Map extends \IPS\Patterns\Singleton
{
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
		elseif ( $data['membermap_location'] )
		{
			$coordinates = $this->getMapCoordinates( $data['membermap_location'] );
			
			if ( $coordinates === false )
			{
				return false;
			}
			
			list( $lat, $lng ) = $coordinates;
		}
		else
		{
			throw new \Exception( 'invalid_data' );
		}
		
		$save = array(
			'member_id'		=> $data['member_id'],
			'lat'			=> $this->_floatVal( $lat ),
			'lon'			=> $this->_floatVal( $lng )
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
	 * Query Google for map coordinates
	 * 
	 * @return		array	Map coordinates
	 */
	public function getMapCoordinates( $location )
	{
		static $fileManager = null;
		
		if ( $fileManager === null )
		{
			$classToLoad = IPSLib::loadLibrary( IPS_KERNEL_PATH . 'classFileManagement.php', 'classFileManagement' );
			$fileManager = new $classToLoad();
		}
		
		if ( $location )
		{
			$result = \IPS\Http\Url::external( 'http://maps.googleapis.com/maps/api/geocode/json?sensor=false&amp;address=' . urlencode( $location ) )->request()->get()->decodeJson();
			
			if ( $result['status'] == 'ZERO_RESULTS' )
			{
				return false;
			}
			
			if ( is_array( $result['results'][0]['types'] ) AND $result['results'][0]['types'][0] == 'country' )
			{
				return false;
			}
			
			return array( $result['results'][0]['geometry']['location']['lat'], $result['results'][0]['geometry']['location']['lng'] );
		}
		
		return false;
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
			$marker = \IPS\Db::i()->select( '*', 'membermap_members', array( 'member_id=?', intval( $memberId ) ) )->first();
		
			if ( is_array( $marker ) AND count( $marker ) )
			{
				return $marker;
			}
		}
		catch( \UnderflowException $e )
		{
			return false;
		}
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
						\IPS\Db::i()->select( 'membermap_members.*,  core_members.*', 'membermap_members' )
							->join( 'core_members', 'membermap_members.member_id=core_members.member_id' )
		);

		$markers = $this->formatMarkers( $dbMarkers );


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
				$photo = \IPS\Member::photoUrl( $marker, TRUE );
				
				$markersToKeep[] = array(
					'lat' 		=> round( (float)$marker['lat'], 5 ),
					'lon' 		=> round( (float)$marker['lon'], 5 ),
					'member_id'	=> $marker['member_id'],
					'popup' 	=> \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->popupContent( $marker, $photo ),
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