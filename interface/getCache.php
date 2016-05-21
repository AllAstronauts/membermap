<?php


$fileId = isset( $_GET['id'] ) ? (int) $_GET['id'] : NULL;

$rootDir = str_replace( 'applications/membermap/interface/getCache.php', '', str_replace( '\\', '/', __FILE__ ) );

if ( $fileId >= 0 )
{
	if ( file_exists( $rootDir . "/datastore/membermap_cache/membermap-{$fileId}.json" ) )
	{
		$output = \file_get_contents( $rootDir . "/datastore/membermap_cache/membermap-{$fileId}.json" );
	}
	else
	{
		$output = json_encode( array( 'error' => 'not_found' ) );
	}
}
else
{
	$output = json_encode( array( 'error' => 'invalid_id' ) );
}

header('Content-Type: application/json');
echo $output;
exit;