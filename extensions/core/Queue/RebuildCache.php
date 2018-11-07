<?php
/**
 * @brief       Rebuild Cache Queue
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       19 May 2016
 * @version     -storm_version-
 */

namespace IPS\membermap\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RebuildCache
{
	/**
	 * @brief Number of topics to build per cycle
	 */
	public $perCycle	= 500;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		/* Store the timestamp of the cache to force the browser to purge its local storage */
		\IPS\Data\Store::i()->membermap_cacheTime = time();

		try
		{			
			$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'membermap_markers' )->first();
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}

		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	int|null				New offset or NULL if complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		if ( ! \IPS\Application::appIsEnabled( 'membermap' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		/* https://bugs.php.net/bug.php?id=72567 */
		ini_set('serialize_precision', 14);

		$currentMemUsage = \memory_get_usage( TRUE );

		/* Wipe out the old files on the first run */
		if ( $offset === 0 )
		{
			\IPS\membermap\Map::i()->deleteCacheFiles();
		}

		$count = 0;
		$memberMarkers = array();
		$customMarkers = array();
		
		$selectColumns = array( 'mm.*', 'mg.*', 'm.member_id', 'm.name', 'm.members_seo_name', 'm.member_group_id', 'm.pp_photo_type', 'm.pp_main_photo', 'm.pp_thumb_photo', 'pi.perm_2 as viewPerms' );
		
		if ( \IPS\Settings::i()->allow_gravatars )
		{
			$selectColumns[] = 'm.pp_gravatar';
			$selectColumns[] = 'm.email';
			$selectColumns[] = 'm.members_bitoptions';
		}

		/* Remember to update membermap\Map too */
		$_markers = \IPS\Db::i()->select( implode( ',', $selectColumns ), array( 'membermap_markers', 'mm' ), array( 'marker_open=1' ), 'mg.group_position ASC, mm.marker_name ASC', array( $offset, $this->perCycle ) )
					->join( array( 'membermap_markers_groups', 'mg' ), 'mm.marker_parent_id=mg.group_id' )
					->join( array( 'core_permission_index', 'pi' ), "( pi.perm_type_id=mg.group_id AND pi.app='membermap' AND pi.perm_type='membermap' )" )
					->join( array( 'core_members', 'm' ), 'mm.marker_member_id=m.member_id' );

		foreach( $_markers as $marker )
		{
			$count++;

			if ( $marker['group_type'] == 'member' )
			{
				$memberMarkers[] = $marker;
			}
			else
			{
				$customMarkers[] = $marker;
			}
		}
		
		/* Only get from extensions on the first run, if not these will be included in every single cache file */
		if ( $offset == 0 )
		{
			/* Get from extensions */
			$extensions = \IPS\Application::allExtensions( 'membermap', 'Mapmarkers', FALSE );

			foreach ( $extensions as $k => $class )
			{
				$appMarkers = $class->getLocations();
				
				if ( is_array( $appMarkers ) AND count( $appMarkers ) )
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
		}

		if ( $count > 0 )
		{
			$markers = \IPS\membermap\Map::i()->formatMemberMarkers( $memberMarkers );
			$custMarkers = \IPS\membermap\Map::i()->formatCustomMarkers( $customMarkers );

			$markers = array_merge( $markers, $custMarkers );

			$fileNumber = $offset / $this->perCycle;

			$cacheKey = "membermap_cache_{$fileNumber}";
			\IPS\Data\Store::i()->$cacheKey = 
				array( 
					'markers' 	=> $markers,
					'fromQueue'	=> 1,
					'memUsage' 	=> round( ( \memory_get_usage() - $currentMemUsage ) / 1024, 2 ) . 'kB',
			);

		}

		if( ! $count )
		{	
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $offset + $this->perCycle;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('membermap_rebuilding_cache'), 'complete' => round( 100 / $data['count'] * $offset, 2 ) );
	}	
}