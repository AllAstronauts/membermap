<?php


namespace IPS\membermap\setup\upg_110003;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 3.1.1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* These changes should have been done by the 3.1.0 upgrader, but I forgot. */
		if ( ! \IPS\Db::i()->checkForIndex( 'membermap_markers_groups', 'group_position' ) )
		{
			\IPS\Db::i()->addIndex( 'membermap_markers_groups', array(
				'type'			=> 'key',
				'name'			=> 'group_position',
				'columns'		=> array( 'group_position' )
			) );
		}

		if ( ! \IPS\Db::i()->checkForIndex( 'membermap_markers', 'marker_member_id' ) )
		{
			\IPS\Db::i()->addIndex( 'membermap_markers', array(
				'type'			=> 'key',
				'name'			=> 'marker_member_id',
				'columns'		=> array( 'marker_member_id' )
			) );
		}

		if ( ! \IPS\Db::i()->checkForIndex( 'membermap_markers', 'marker_parent_id' ) )
		{
			\IPS\Db::i()->addIndex( 'membermap_markers', array(
				'type'			=> 'key',
				'name'			=> 'marker_parent_id',
				'columns'		=> array( 'marker_parent_id' )
			) );
		}

		\IPS\Db::i()->changeColumn( 'membermap_markers', 'marker_open', array(
			'name'			=> 'marker_open',
			'type'			=> 'tinyint',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->update( 'membermap_markers', array( 'marker_open' => 0 ), 'marker_open IS NULL' );


		\IPS\Task::queue( 'membermap', 'RebuildCache', array( 'class' => '\IPS\membermap\Map' ), 1, array( 'class' ) );


		return TRUE;
	}
}