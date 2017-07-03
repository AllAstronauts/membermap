<?php
/**
 * @brief		Public Controller
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.0
 */


namespace IPS\membermap\modules\front\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ajax
 */
class _ajax extends \IPS\Dispatcher\Controller
{
	/**
	 * Get the cache file
	 * Proxying it through this instead of exposing the location to the end user, and to send a proper error code
	 *
	 * @return json
	 */
	protected function getCache()
	{
		$fileId = isset( \IPS\Request::i()->id ) ? (int) \IPS\Request::i()->id : NULL;

		if ( $fileId >= 0 )
		{
			if ( file_exists( \IPS\ROOT_PATH . "/datastore/membermap_cache/membermap-{$fileId}.json" ) )
			{
				$output = \file_get_contents( \IPS\ROOT_PATH . "/datastore/membermap_cache/membermap-{$fileId}.json" );
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

		\IPS\Output::i()->sendOutput( $output, 200, 'application/json' );
	}

	protected function mapquestSearch()
	{
		$location 	= \IPS\Request::i()->q;
		$data 		= array();
		
		if ( $location )
		{
			$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );
			try
			{
				$data = \IPS\Http\Url::external( "https://open.mapquestapi.com/nominatim/v1/search.php?key={$apiKey}&format=json&q=" . urlencode( $location ) )->request( 15 )->get()->decodeJson();
			}
			catch( \Exception $e ) 
			{
				\IPS\Log::log( $e, 'membermap' );
			}
		}
		
		\IPS\Output::i()->json( $data );	
	}

	protected function mapquestReverseLookup()
	{
		$lat 	= floatval( \IPS\Request::i()->lat );
		$lng 	= floatval( \IPS\Request::i()->lng );
		$data 	= array();
		
		if ( $lat AND $lng )
		{
			$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );
			try
			{
				$data = \IPS\Http\Url::external( "https://www.mapquestapi.com/geocoding/v1/reverse?key={$apiKey}&lat={$lat}&lng={$lng}" )->request( 15 )->get()->decodeJson();
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'membermap' );
			}
		}
		
		\IPS\Output::i()->json( $data );	
	}
	
}