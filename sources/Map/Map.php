<?php


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
		if ( \IPS\Request::i()->lat AND \IPS\Request::i()->lon )
		{
			$lat = \IPS\Request::i()->lat;
			$lng = \IPS\Request::i()->lon;
		}
		elseif ( $data['location'] )
		{
			$coordinates = $this->getMapCoordinates( $data['location'] );
			
			if ( $coordinates === false )
			{
				return false;
			}
			
			list( $lat, $lng ) = $coordinates;
		}
		else
		{
			throw new Exception( 'invalid_data' );
		}
		
		$save = array(
			'lat'			=> $this->_floatVal( $lat ),
			'lon'			=> $this->_floatVal( $lng )
		);

		
		\IPS\Db::i()->replace( 'membermap_members', $save, 'member_id=' . \IPS\Member::loggedIn()->member_id );

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
	 * Rewrite cache file
	 * 
	 * @return	array	Parsed list of markers
	 */
	public function recacheJsonFile()
	{	
		$markers = array();
		
		$markers = iterator_to_array( 
						\IPS\Db::i()->select( 'membermap_members.*,  core_members.name, core_members.members_seo_name', 'membermap_members' )
							->join( 'core_members', 'membermap_members.member_id=core_members.member_id' )
		);

		$this->formatMarkers( $markers );

		$markers = array_chunk( $markers, 500 );

		if ( ! is_dir( \IPS\ROOT_PATH . '/datastore/membermap_cache' ) )
		{
			mkdir( \IPS\ROOT_PATH . '/datastore/membermap_cache' );
			chmod( \IPS\ROOT_PATH . '/datastore/membermap_cache', \IPS\IPS_FOLDER_PERMISSION );
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
	
	public function formatMarkers( array &$markers )
	{
		if ( is_array( $markers ) AND count( $markers ) )
		{
			foreach( $markers as &$marker )
			{
				$marker['member_link'] = (string) \IPS\Http\Url::internal( 'app=core&module=members&controller=profile&id=' . $marker['member_id'], 'front', 'profile', $marker['members_seo_name'] ); 
			}
		}
		
		return $markers;
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