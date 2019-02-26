<?php

/**
 * @brief       Upgrade Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       3.5.0
 * @version     -storm_version-
 */

namespace IPS\membermap\setup\upg_110000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 3.1.0 Upgrade Code
 */
class _Upgrade
{

	/**
	 * Moving member markers to the new centralised location for all markers 
	 */
	public function step1()
	{
		$memberGroupId = \IPS\membermap\Map::i()->getMemberGroupId();

		$limit		= 0;
		$did		= 0;
		$perCycle	= 500;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( 'mm.*, m.name, m.members_seo_name', array( 'membermap_members', 'mm' ), '', 'mm.member_id ASC', array( $limit, $perCycle )  )->join( array( 'core_members', 'm' ), 'mm.member_id=m.member_id') as $member )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* We don't have a name, likely this member has been deleted */
			if ( $member['name'] == '' OR $member['name'] == NULL )
			{
				continue;
			}

			\IPS\Db::i()->insert( 'membermap_markers', array(
				'marker_parent_id' 	=> $memberGroupId,
				'marker_name'		=> $member['name'],
				'marker_name_seo'	=> $member['members_seo_name'],
				'marker_lat'		=> $member['lat'],
				'marker_lon'		=> $member['lon'],
				'marker_member_id'	=> $member['member_id'],
				'marker_added'		=> $member['marker_date'] ?: time(),
			) );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step1Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'membermap_members' )->first();
		}
		
		return "Updating member markers (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}

	/**
	 * Filling the gaps in marker groups
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$order = 2;
		foreach( \IPS\Db::i()->select( '*', 'membermap_markers_groups' ) as $group )
		{
			$group	= \IPS\membermap\Markers\Groups::constructFromData( $group );

			\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$group->id}", trim( $group->name ) );
			\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$group->id}_JS", trim( $group->name ), 1 );

			$group->name_seo 	= \IPS\Http\Url\Friendly::seoTitle( trim( $group->name ) );

			if ( $group->type == 'custom' )
			{
				$group->position 	= $order;
				$group->moderate	= 1;
				$order++;
			}

			try
			{
				$latestMarker	= \IPS\Db::i()->select( '*', 'membermap_markers', array( 'marker_open=? and marker_parent_id=?', 1, $group->id ), 'marker_updated DESC, marker_added DESC', array( 0, 1 ) )->first();

				$group->last_marker_id		= $latestMarker['marker_id'];
				$group->last_marker_date	= $latestMarker['marker_added'];
			}
			catch( \UnderflowException $e )
			{
				$group->last_marker_id		= 0;
				$group->last_marker_date	= 0;
			}

			$group->save();

			/* Reset permissions */
			$perms = $group->permissions();
			\IPS\Db::i()->update( 'core_permission_index', array(
				'perm_view'	 => '*',
				'perm_2'	 => '*',  #read
				'perm_3'     => \IPS\Settings::i()->admin_group,  #add
			    'perm_4'     => \IPS\Settings::i()->admin_group,  #comment
			    'perm_5'     => \IPS\Settings::i()->admin_group,  #review
			), array( 'perm_id=?', $perms['perm_id'] ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading marker groups";
	}

	/**
	 * Updating markers without a seo name
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		foreach( \IPS\Db::i()->select( '*', 'membermap_markers', 'marker_name_seo = "" OR marker_name_seo IS NULL' ) as $marker )
		{
			$seoName = \IPS\Http\Url\Friendly::seoTitle( trim( $marker['marker_name'] ) );
			\IPS\Db::i()->update( 'membermap_markers', array( 'marker_name_seo' => $seoName ), 'marker_id=' . $marker['marker_id'] );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Setting SEO titles";
	}

	/**
	 * Clean up
	 */
	public function step4()
	{
		unset( $_SESSION['_membermapMemberGroupId'] );

		/* Restore content in the search index */
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\membermap\Markers\Markers' ) );
		
		try
		{
			// \IPS\Db::i()->dropTable( 'membermap_members', TRUE ); # Want to keep this table as backup for a couple of versions

			\IPS\Db::i()->dropColumn( 'core_groups', 'g_membermap_canAdd' );
			\IPS\Db::i()->dropColumn( 'core_groups', 'g_membermap_canEdit' );
			\IPS\Db::i()->dropColumn( 'core_groups', 'g_membermap_canDelete' );
		}
		catch( \IPS\Db\Exception $e ) { } #meh

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Cleaning up";
	}
}