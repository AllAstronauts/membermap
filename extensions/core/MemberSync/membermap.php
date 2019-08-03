<?php
/**
 * @brief       Member Sync Extension
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       20 Oct 2015
 * @version     -storm_version-
 */

namespace IPS\membermap\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
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
	public function onMerge( $member, $member2 ): void
	{
		/* A member can't have multiple locations, so we'll have to delete one of them */
		$memberLoc 	= \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id, FALSE );
		$member2Loc = \IPS\membermap\Map::i()->getMarkerByMember( $member2->member_id, FALSE );

		// Delete $member2's location if $member have one 
		if ( $memberLoc instanceof \IPS\membermap\Markers\Markers )
		{
			if ( $member2Loc instanceof \IPS\membermap\Markers\Markers )
			{
				$member2Loc->delete();
			}
		}
		// Or move $member2's location over to $member.
		else if ( $member2Loc instanceof \IPS\membermap\Markers\Markers )
		{
			$member2Loc->member_id = $member->member_id;
			$member2Loc->save();
		}
	}
	
	/**
	 * Member is deleted
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onDelete( $member ): void
	{
		$memberLoc 	= \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id, FALSE, FALSE );

		if ( $memberLoc instanceof \IPS\membermap\Markers\Markers )
		{
			$memberLoc->delete();
		}
	}

	/**
	 * Member is flagged as spammer
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onSetAsSpammer( $member ): void
	{
		$memberLoc 	= \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id, FALSE, FALSE );

		if ( $memberLoc instanceof \IPS\membermap\Markers\Markers )
		{
			$memberLoc->hide( NULL );
		}
	}
	
	/**
	 * Member is unflagged as spammer
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onUnSetAsSpammer( $member ): void
	{
		$memberLoc 	= \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id, FALSE, FALSE );

		if ( $memberLoc instanceof \IPS\membermap\Markers\Markers )
		{
			//$memberLoc->hide( NULL ); // Can't use this because of this bug: https://invisionpower.com/4bugtrack/archived-reports/content-classes-assumes-commenting-is-always-available-r12385/
			
			$memberLoc->open = 1;
			$memberLoc->save();

			/* Update search index */
	        if ( $memberLoc instanceof \IPS\Content\Searchable )
	        {
	            \IPS\Content\Search\Index::i()->index( $memberLoc );
	        }
		}
	}

	/**
	 * Member account has been updated
	 *
	 * @param	$member		\IPS\Member	Member updating profile
	 * @param	$changes	array		The changes
	 * @return	void
	 */
	public function onProfileUpdate( $member, $changes ): void
	{
		/* An endless loop is formed when \Item::createItem() is saving \Member, which then fires this membersync, which then calls \Item::createItem, and so on, and so on */
		static $wereDoneHere = false;

		if ( $wereDoneHere OR ! \count( $changes ) )
		{
			return;
		}
		
		//debug( $changes );
		$wereDoneHere = true;

		if ( isset( $changes['name'] ) )
		{
			$existingMarker = \IPS\membermap\Map::i()->getMarkerByMember( $member->member_id, FALSE, FALSE );

			if( $existingMarker instanceof \IPS\membermap\Markers\Markers )
			{
				$existingMarker->name 		= $member->name;
				$existingMarker->updated 	= time();

				$existingMarker->save();
			}
		}
	}
}