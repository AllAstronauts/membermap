<?php
/**
 * @brief		Background Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Member Map
 * @since		19 May 2016
 * @version		SVN_VERSION_NUMBER
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
		/* Wipe out the old files on the first run */
		if ( $offset === 0 )
		{
			\IPS\membermap\Map::i()->deleteCacheFiles();
		}

		$count = 0;
		$memberMarkers = array();
		$customMarkers = array();
		
		$selectColumns = array( 'mm.*', 'mg.*', 'm.member_id', 'm.name', 'm.members_seo_name', 'm.member_group_id', 'm.pp_photo_type', 'm.pp_main_photo', 'm.pp_thumb_photo' );
		
		if ( \IPS\Settings::i()->allow_gravatars )
		{
			$selectColumns[] = 'm.pp_gravatar';
			$selectColumns[] = 'm.email';
			$selectColumns[] = 'm.members_bitoptions';
		}

		/* Remember to update membermap\Map too */
		$_markers = \IPS\Db::i()->select( implode( ',', $selectColumns ), array( 'membermap_markers', 'mm' ), array(), 'mg.group_position ASC, mm.marker_id DESC', array( $offset, $this->perCycle ) )
					->join( array( 'membermap_markers_groups', 'mg' ), 'mm.marker_parent_id=mg.group_id' )
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

		if ( $count > 0 )
		{
			$markers = \IPS\membermap\Map::i()->formatMemberMarkers( $memberMarkers );
			$custMarkers = \IPS\membermap\Map::i()->formatCustomMarkers( $customMarkers );

			$markers = array_merge( $markers, $custMarkers );

			$fileNumber = $offset / $this->perCycle;

			touch( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileNumber . '.json' );
			chmod( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileNumber . '.json', \IPS\IPS_FILE_PERMISSION );
			\file_put_contents( \IPS\ROOT_PATH . '/datastore/membermap_cache/membermap-' . $fileNumber . '.json', json_encode( $markers ) );

			/* Store the timestamp of the cache to force the browser to purge its local storage */
			\IPS\Data\Store::i()->membermap_cacheTime = time();
		}

		return $count ? ( $offset + $count ) : NULL;
		
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