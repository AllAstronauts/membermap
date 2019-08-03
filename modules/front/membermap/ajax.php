<?php
/**
 * @brief       AJAX controller
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       5 Jun 2017
 * @version     -storm_version-
 */

namespace IPS\membermap\modules\front\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
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
	protected function getCache(): void
	{
		$fileId = isset( \IPS\Request::i()->id ) ? (int) \IPS\Request::i()->id : NULL;

		if ( $fileId >= 0 )
		{
			$cacheKey = "membermap_cache_{$fileId}";
			if ( isset( \IPS\Data\Store::i()->$cacheKey ) )
			{
				$output = json_encode( \IPS\Data\Store::i()->$cacheKey );
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

	/**
	 * Get popup HTML
	 * 
	 * @return html
	 */
	protected function getPopup(): void
	{
		$markerId 	= \IPS\Request::i()->id;
		$markerExt 	= \IPS\Request::i()->ext ?: '';
		$output 	= '';

		if ( ! $markerId )
		{
			$output = 'invalid_id';
		}
		else
		{
			/* Get a regular member/custom marker */
			if ( ! $markerExt )
			{
				/* Remember to update the queue too */
				$marker = \IPS\membermap\Markers\Markers::load( \intval( $markerId ) );

				if ( $marker->container()->type == 'member' )
				{

					$output = \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->popupContent( $marker );

				}
				else
				{
					$output = \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->customMarkerPopup( $marker );
				}

			}
			else
			{
				list( $app, $ext ) = explode( '_', $markerExt );

				if ( $app AND $ext )
				{
					$output = \IPS\Application::load( $app )->extensions( 'membermap', 'Mapmarkers' )[ $ext ]->getPopup( $markerId );
				}
			}
		}

		\IPS\Output::i()->sendOutput( $output );
	}

	/**
	 * Search for locations usign MapQuest Nominatim
	 * 
	 * @return json
	 */
	protected function mapquestSearch(): void
	{
		$location 	= \IPS\Request::i()->q;
		$data 		= array();
		
		if ( $location )
		{
			$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );
			
			if ( $apiKey )
			{
				$queryString = array(
					'key' 				=> $apiKey, 
					'format' 			=> 'json', 
					'q' 				=> $location,
					'accept-language' 	=> isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : NULL,
					'debug' 			=> 0
				);
				
				if ( mb_strlen( \IPS\Settings::i()->membermap_restrictCountries ) >= 2 )
				{
					$queryString['countrycodes'] = \IPS\Settings::i()->membermap_restrictCountries;
				}

				try
				{
					$url 	= \IPS\Http\Url::external( "https://open.mapquestapi.com/nominatim/v1/search.php" )->setQueryString( $queryString );
					$data 	= $url->request()->get();
					$data = $data->decodeJson();
				}
				catch( \Exception $e ) 
				{
					\IPS\Log::log( $e, 'membermap' );
				}
			}
		}
		
		\IPS\Output::i()->json( $data );	
	}

	/**
	 * MapQuest reverse lookup. Takes coordinates, return an address/location
	 * 
	 * @return json
	 */
	protected function mapquestReverseLookup(): void
	{
		$lat 	= \floatval( \IPS\Request::i()->lat );
		$lng 	= \floatval( \IPS\Request::i()->lng );
		$data 	= array();
		
		if ( $lat AND $lng )
		{
			$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );

			try
			{
				$url = \IPS\Http\Url::external( "https://www.mapquestapi.com/geocoding/v1/reverse" )->setQueryString( 
							array(
								'key'				=> $apiKey,
								'lat' 				=> $lat,
								'lng' 				=> $lng,
								'accept-language' 	=> isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : NULL,
							)
				);

				$data = $url->request( 15 )->get()->decodeJson();
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'membermap' );
			}
		}
		
		\IPS\Output::i()->json( $data );	
	}
}