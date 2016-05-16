<?php
/**
 * @brief		locationSync Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	membermap
 * @since		05 May 2016
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\membermap\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * locationSync Task
 */
class _locationSync extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		if ( ! \IPS\Settings::i()->membermap_syncLocationField OR ! \IPS\Settings::i()->membermap_monitorLocationField )
		{
			$this->enabled = FALSE;
			$this->save();
			return;
		}

		$fieldKey 	= \IPS\Settings::i()->membermap_profileLocationField;
		$limit		= 100;
		$counter	= 0;

		$memberMarkerGroupId = \IPS\membermap\Map::i()->getMemberGroupId();

		try
		{
			$where = array();
			$where[] = array( "( pf.field_{$fieldKey} IS NOT NULL OR pf.field_{$fieldKey} != '' )" );
			$where[] = array( "mm.marker_id IS NULL" );
			$where[] = array( "m.membermap_location_synced = 0" );

			if( \IPS\Settings::i()->membermap_monitorLocationField_groupPerm !== '*' )
			{
				$where[] = \IPS\Db::i()->in( 'm.member_group_id', explode( ',', \IPS\Settings::i()->membermap_monitorLocationField_groupPerm ) );
			}

			$members = \IPS\Db::i()->select( '*', array( 'core_members', 'm' ), $where, 'm.last_activity DESC', array( 0, $limit ) )
						->join( array( 'core_pfields_content', 'pf' ), 'pf.member_id=m.member_id' )
						->join( array( 'membermap_markers', 'mm' ), 'mm.marker_member_id=m.member_id AND mm.marker_parent_id=' . $memberMarkerGroupId );

			
			foreach( $members as $member )
			{	
				$_member = \IPS\Member::constructFromData( $member );
			
				/* Need to set this to prevent us from looping over the same members with invalid locations over and over again */
				$_member->membermap_location_synced = 1;
				$_member->save();

				$_location = trim( $member['field_' . $fieldKey ] );
				
				if( empty( $_location ) )
				{
					continue;
				}

				$nominatim = \IPS\membermap\Map::i()->getLatLng( $_location );

				if( is_array( $nominatim ) )
				{
					$lat 		= $nominatim['lat'];
					$lng 		= $nominatim['lng'];
					$location 	= $nominatim['location'];

					$marker = \IPS\membermap\Markers\Markers::createItem( $_member, NULL, new \IPS\DateTime, \IPS\membermap\Markers\Groups::load( $memberMarkerGroupId ), FALSE );
						
					$marker->lat = $lat;
					$marker->lon = $lng;
					$marker->location = $location ?: $_location;
					$marker->name = $_member->name;
					
					$marker->save();

					/* Add to index */
					\IPS\Content\Search\Index::i()->index( $marker );

					$counter++;
				}
			}
		}
		/* We're done here */
		catch ( \UnderflowException $e )
		{
		}
		/* Any other exception means an error which should be logged */
		catch ( \Exception $e )
		{
			throw new \IPS\Task\Exception( $this, $e->getMessage() );
		}

		if( $counter > 0 )
		{
			return "Synchronised {$counter} member locations";
		}
		else
		{
			$this->enabled = FALSE;
			$this->save();

			/* Turn the setting off as well */
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => 0 ), array( 'conf_key=?', 'membermap_syncLocationField' ) );
			unset( \IPS\Data\Store::i()->settings );
			return;
		}

		return NULL;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}