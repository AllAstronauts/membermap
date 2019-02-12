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

namespace IPS\membermap\setup\upg_140006;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 3.4.2.4 Upgrade Code
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
		if ( \is_dir( \IPS\ROOT_PATH . '/datastore/membermap_cache' ) )
		{
			foreach( glob( \IPS\ROOT_PATH . '/datastore/membermap_cache/*' ) as $file )
			{
				if ( \is_file( $file ) )
				{
					@unlink( $file );
				}
			}
			
			@rmdir( \IPS\ROOT_PATH . '/datastore/membermap_cache/' );
		}


		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}