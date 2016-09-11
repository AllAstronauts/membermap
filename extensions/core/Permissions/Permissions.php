<?php
/**
 * @brief		Permissions Extension
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.0
 */

namespace IPS\membermap\extensions\core\Permissions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Permissions
 */
class _Permissions
{
	/**
	 * Get node classes
	 *
	 * @return	array
	 */
	public function getNodeClasses()
	{		
		return array(
			'IPS\membermap\Markers\Groups' => function( $current, $group )
			{
				$rows = array();
				
				foreach( \IPS\membermap\Markers\Groups::roots( NULL ) AS $groups )
				{
					\IPS\membermap\Markers\Groups::populatePermissionMatrix( $rows, $groups, $group, $current );
				}
				
				return $rows;
			}
		);
	}
}