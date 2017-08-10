<?php


namespace IPS\membermap\setup\upg_working;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * {version_human} Upgrade Code
 */
class _Upgrade
{
	public function step1()
	{
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\membermap\Markers\Groups', 'count' => 0 ), 5, array( 'class' ) );

		return TRUE;
	}
}