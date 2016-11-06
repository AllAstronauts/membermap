<?php
/**
 * @brief		Location Sync task
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.1.3
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
		if ( ! \IPS\Settings::i()->membermap_syncLocationField OR ! \IPS\Settings::i()->membermap_monitorLocationField OR ! \IPS\Settings::i()->membermap_profileLocationField )
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
			$where[] = array( '( ! ' . \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'bw_is_spammer' ) . ' )' );

			if( \IPS\Settings::i()->membermap_monitorLocationField_groupPerm !== '*' )
			{
				$where[] = \IPS\Db::i()->in( 'm.member_group_id', explode( ',', \IPS\Settings::i()->membermap_monitorLocationField_groupPerm ) );
			}

			$members = \IPS\Db::i()->select( '*', array( 'core_members', 'm' ), $where, 'm.last_activity DESC', array( 0, $limit ) )
						->join( array( 'core_pfields_content', 'pf' ), 'pf.member_id=m.member_id' )
						->join( array( 'membermap_markers', 'mm' ), 'mm.marker_member_id=m.member_id AND mm.marker_parent_id=' . $memberMarkerGroupId );

			
			foreach( $members as $member )
			{	
				$lat = $lng = $location = NULL;

				$_member = \IPS\Member::constructFromData( $member );
			
				/* Need to set this to prevent us from looping over the same members with invalid locations over and over again */
				$_member->membermap_location_synced = 1;
				$_member->save();

				$_location = trim( $member['field_' . $fieldKey ] );
				
				if( empty( $_location ) )
				{
					continue;
				}

				/* If it's an array, it might be from an address field, which already have the lat/lng data */
				if( is_array( json_decode( $_location, TRUE ) ) )
				{
					$addressData = json_decode( $_location, TRUE );

					if ( is_float( $addressData['lat'] ) AND is_float( $addressData['long'] ) )
					{
						$lat = floatval( $addressData['lat'] );
						$lng = floatval( $addressData['long'] );
					}

					$addressData['addressLines'][] = $addressData['city'];

					if ( count( $addressData['addressLines'] ) )
					{
						$location = implode( ', ', $addressData['addressLines'] );
					}
				}
				/* It's a text field, or \IPS\Geolocation failed to get coordinates (in which case we won't bother either */
				else
				{
					/* Remove HTML, newlines, tab, etc, etc */
					$_location = preg_replace( "/[\\x00-\\x20]|\\xc2|\\xa0+/", ' ', strip_tags( $_location ) );
					$_location = trim( preg_replace( "/\s\s+/", ' ', $_location ) );

					/* To my understanding we're not allowed to use \IPS\Geolocation, as that uses Google API, and we're not showing the info on a Google Map. */
					$nominatim = \IPS\membermap\Map::i()->getLatLng( $_location );

					if( is_array( $nominatim ) AND count( $nominatim ) )
					{
						$lat 		= $nominatim['lat'];
						$lng 		= $nominatim['lng'];
						$location 	= $nominatim['location'];
					}
				}

				if( $lat AND $lng )
				{
					$marker = \IPS\membermap\Markers\Markers::createItem( $_member, NULL, new \IPS\DateTime, \IPS\membermap\Markers\Groups::load( $memberMarkerGroupId ), FALSE );
						
					$marker->name 		= $_member->name;
					$marker->lat 		= $lat;
					$marker->lon 		= $lng;
					$marker->location 	= $location ?: $_location;
					
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
		/* Have to catch \RuntimeException to catch the BAD_JSON error */
		catch ( \RuntimeException $e )
		{
			\IPS\Log::log( array( $e->getMessage(), $nominatim ), 'membermap' );
		}
		/* Any other exception means an error which should be logged */
		catch ( \Exception $e )
		{
			\IPS\Log::log( array( $e->getMessage(), $nominatim ), 'membermap' );
			
			throw new \IPS\Task\Exception( $this, $e->getMessage() );
		}

		if( $counter > 0 )
		{
			$foundRows = $members->count();
			return "Synchronised {$counter} out of {$foundRows} member locations";
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