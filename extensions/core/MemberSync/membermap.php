<?php
/**
 * @brief		Member Sync Extension
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.0
 */


namespace IPS\membermap\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _membermap
{	
	/**
	 * Member is merged with another member
	 *
	 * @param	\IPS\Member	$member		Member being kept
	 * @param	\IPS\Member	$member2	Member being removed
	 * @return	void
	 */
	public function onMerge( $member, $member2 )
	{
		/* A member can't have multiple locations, so we'll have to delete one of them */

		$memderLoc 	= \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id );
		$memder2Loc = \IPS\membermap\Map::i()->getMarkerByMember( $member2->member_id );

		// Delete $member2's location if $member have one 
		if ( is_array( $memberLoc ) )
		{
			\IPS\membermap\Map::i()->deleteMarker( $member2->member_id );
		}
		// Or move $member2's location over to $member.
		else if ( is_array( $member2Loc ) )
		{
			\IPS\Db::i()->update( 'membermap_members', array( 'member_id' => $member->member_id ), 'member_id=' . $member2->member_id );
			\IPS\membermap\Map::i()->invalidateJsonCache();
		}
	}
	
	/**
	 * Member is deleted
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onDelete( $member )
	{
		\IPS\membermap\Map::i()->deleteMarker( $member->member_id );
	}

	/**
	 * Member account has been updated
	 *
	 * @param	$member		\IPS\Member	Member updating profile
	 * @param	$changes	array		The changes
	 * @return	void
	 */
	public function onProfileUpdate( $member, $changes )
	{
		if( count( $changes ) AND \IPS\Settings::i()->membermap_monitorLocationField )
		{
			if ( isset( $changes['field_' . \IPS\Settings::i()->membermap_profileLocationField ] ) AND ! empty( $changes['field_' . \IPS\Settings::i()->membermap_profileLocationField ] ) )
			{
				$lat = $lng = NULL;
				$fieldValue = $changes['field_' . \IPS\Settings::i()->membermap_profileLocationField ];

				/* If it's an array, it might be from an address field, which already have the lat/lng data */
				if( is_array( json_decode( $fieldValue, TRUE ) ) )
				{
					$addressData = json_decode( $fieldValue, TRUE );

					if ( is_float( $addressData['lat'] ) AND is_float( $addressData['long'] ) )
					{
						$lat = floatval( $addressData['lat'] );
						$lng = floatval( $addressData['long'] );
					}
				}
				/* It's a text field, or \IPS\Geolocation failed to get coordinates (in which case we won't bother either */
				else
				{
					/* To my understanding we're not allowed to use \IPS\Geolocation, as that uses Google API, and we're not showing the info on a Google Map. */
					debug( \IPS\membermap\Map::getLatLng( $fieldValue ) );
				}


				//$existingMarker = \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id, FALSE );
			}
		}
	}
}