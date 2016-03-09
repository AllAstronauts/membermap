<?php


namespace IPS\membermap\setup\upg_100006;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 3.0.3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Renaming tables and adding new columns
	 */
	public function step1()
	{
		\IPS\Db::i()->renameTable( 'membermap_cmarkers_groups', 'membermap_markers_groups' );
		\IPS\Db::i()->renameTable( 'membermap_cmarkers', 'membermap_markers' );

		/*
		 * membermap_markers columns
		 */
		\IPS\Db::i()->addColumn( 'membermap_markers', array(
				"name"		=> "marker_name_seo",
				"type"		=> "VARCHAR",
				"length"	=> 255,
				"null"		=> true,
				"default"	=> null,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers', array(
				"name"		=> "marker_member_id",
				"type"		=> "MEDIUMINT",
				"length"	=> 8,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers', array(
				"name"		=> "marker_added",
				"type"		=> "INT",
				"length"	=> 10,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers', array(
				"name"		=> "marker_updated",
				"type"		=> "INT",
				"length"	=> 10,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers', array(
				"name"		=> "marker_open",
				"type"		=> "TINYINT",
				"length"	=> 1,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers', array(
				"name"		=> "marker_approver",
				"type"		=> "MEDIUMINT",
				"length"	=> 8,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers', array(
				"name"		=> "marker_approvedon",
				"type"		=> "INT",
				"length"	=> 10,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );

		/*
		 * membermap_markers_groups columns
		 */
		\IPS\Db::i()->addColumn( 'membermap_markers_groups', array(
				"name"		=> "group_name_seo",
				"type"		=> "VARCHAR",
				"length"	=> 255,
				"null"		=> true,
				"default"	=> null,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers_groups', array(
				"name"		=> "group_protected",
				"type"		=> "TINYINT",
				"length"	=> 1,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers_groups', array(
				"name"		=> "group_type",
				"type"		=> "VARCHAR",
				"length"	=> 10,
				"null"		=> false,
				"default"	=> 'custom',
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers_groups', array(
				"name"		=> "group_last_marker_id",
				"type"		=> "INT",
				"length"	=> 10,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );
		\IPS\Db::i()->addColumn( 'membermap_markers_groups', array(
				"name"		=> "group_last_marker_date",
				"type"		=> "INT",
				"length"	=> 10,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
		)	 );

		$memberGroupId = \IPS\Db::i()->insert( 'membermap_markers_groups', array(
			'group_name' 		=> "Members",
			'group_name_seo'	=> 'members',
			'group_protected' 	=> 1,
			'group_type'		=> 'member',
			'group_pin_colour'	=> '#FFFFFF',
			'group_pin_bg_colour' 	=> 'darkblue',
			'group_pin_icon'		=> 'fa-user',
		) );

		$_SESSION['_membermapMemberGroupId'] = $memberGroupId;

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Adding new database tables and columns";
	}


	/**
	 * Moving member markers to the new centralised location for all markers 
	 */
	public function step2()
	{
		if ( $_SESSION['_membermapMemberGroupId'] > 0 )
		{
			$memberGroupId = $_SESSION['_membermapMemberGroupId'];
		}
		else
		{
			try
			{
				$memberGroup = \IPS\Db::i()->select( 'group_id', 'membermap_markers_groups', array( 'group_type=?', 'member' ) )->first();
				$memberGroupId = $memberGroup['group_id'];
			}
			catch( \UnderflowException $e )
			{
				/* If this happens, we're in deep shit */
				$memberGroupId = \IPS\Db::i()->insert( 'membermap_markers_groups', array(
					'group_name' 		=> "Members",
					'group_name_seo'	=> 'members',
					'group_protected' 	=> 1,
					'group_type'		=> 'member',
					'group_pin_colour'	=> '#FFFFFF',
					'group_pin_bg_colour' 	=> 'darkblue',
					'group_pin_icon'		=> 'fa-user',
				) );
			}

			$_SESSION['_membermapMemberGroupId'] = $memberGroupId;
		}


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

			\IPS\Db::i()->insert( 'membermap_markers', array(
				'marker_parent_id' 	=> $memberGroupId,
				'marker_name'		=> $member['name'],
				'marker_name_seo'	=> $member['members_seo_name'],
				'marker_lat'		=> $member['lat'],
				'marker_lon'		=> $member['lon'],
				'marker_member_id'	=> $member['member_id'],
				'marker_added'		=> $member['marker_date'],
				'marker_open'		=> 1,
			) );
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step2Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step2Count'] ) )
		{
			$_SESSION['_step2Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'membermap_members' )->first();
		}
		
		return "Upgrading member markers (Upgraded so far: " . ( ( $limit > $_SESSION['_step2Count'] ) ? $_SESSION['_step2Count'] : $limit ) . ' out of ' . $_SESSION['_step2Count'] . ')';
	}

	/**
	 * Filling the gaps in marker groups
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		
		foreach( \IPS\Db::i()->select( '*', 'membermap_markers_groups' ) as $group )
		{
			$group	= \IPS\membermap\Markers\Groups::constructFromData( $group );

			\IPS\Lang::saveCustom( 'membermap', "membermap_marker_group_{$group->id}", trim( $group->name ) );

			$group->name_seo = \IPS\Http\Url::seoTitle( trim( $group->name ) );

			try
			{
				$latestMarker	= \IPS\Db::i()->select( '*', 'membermap_markers', array( 'marker_open=? and marker_parent_id=?', 1, $cid ), 'marker_added DESC', array( 0, 1 ) )->first();

				$group->last_marker_id		= $latestMarker['file_id'];
				$group->last_marker_date	= $latestMarker['file_submitted'];
			}
			catch( \UnderflowException $e )
			{
				$group->last_marker_id		= 0;
				$group->last_marker_date	= 0;
			}

			$group->save();
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
		return "Upgrading marker groups";
	}

	/**
	 * Clean up
	 */
	public function step4()
	{
		unset( $_SESSION['_membermapMemberGroupId'] );
		\IPS\Db::i()->dropTable( 'membermap_members' );
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