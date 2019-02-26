<?php
/**
 * @brief       Uninstall callback
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       13 Dec 2015
 * @version     -storm_version-
 */

namespace IPS\membermap\extensions\core\Uninstall;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Uninstall callback
 */
class _membermap
{
	/**
	 * Code to execute before the application has been uninstalled
	 *
	 * @param	string	$application	Application directory
	 * @return	array
	 */
	public function preUninstall( $application )
	{
	}

	/**
	 * Code to execute after the application has been uninstalled
	 *
	 * @param	string	$application	Application directory
	 * @return	array
	 */
	public function postUninstall( $application )
	{
		if( \IPS\Db::i()->checkForColumn( 'core_groups', 'g_membermap_canAdd' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_groups', 'g_membermap_canAdd' );
		}
		
		if( \IPS\Db::i()->checkForColumn( 'core_groups', 'g_membermap_canEdit' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_groups', 'g_membermap_canEdit' );
		}
		
		if( \IPS\Db::i()->checkForColumn( 'core_groups', 'g_membermap_canDelete' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_groups', 'g_membermap_canDelete' );
		}

		if( \IPS\Db::i()->checkForColumn( 'core_groups', 'g_membermap_delete_own' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_groups', 'g_membermap_delete_own' );
		}

		if( \IPS\Db::i()->checkForColumn( 'core_members', 'membermap_location_synced' ) )
		{
			\IPS\Db::i()->dropColumn( 'core_members', 'membermap_location_synced' );
		}
	}
}