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

namespace IPS\membermap\setup\upg_130000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 3.3.0 Upgrade Code
 */
class _Upgrade
{
	public function step1()
	{
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\membermap\Markers\Groups', 'count' => 0 ), 5, array( 'class' ) );

		\IPS\Db::i()->update( 'core_permission_index', 'perm_4=perm_3, perm_5=perm_3', array( 'app=?', 'membermap' ) );

		return TRUE;
	}
}