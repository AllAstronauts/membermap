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

	/**
	 * Get popup HTML
	 * 
	 * @return html
	 */
	protected function getPopup()
	{
		$markerId 	= intval( \IPS\Request::i()->id );
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
				$selectColumns = array( 'mm.*', 'mg.*', 'm.member_id', 'm.name', 'm.members_seo_name', 'm.member_group_id', 'm.pp_photo_type', 'm.pp_main_photo', 'm.pp_thumb_photo', 'm.timezone' );
		
				if ( \IPS\Settings::i()->allow_gravatars )
				{
					$selectColumns[] = 'm.pp_gravatar';
					$selectColumns[] = 'm.email';
					$selectColumns[] = 'm.members_bitoptions';
				}

				/* Remember to update the queue too */
				$marker = \IPS\Db::i()->select( implode( ',', $selectColumns ), array( 'membermap_markers', 'mm' ), array( 'mm.marker_id=?', $markerId ) )
							->join( array( 'membermap_markers_groups', 'mg' ), 'mm.marker_parent_id=mg.group_id' )
							->join( array( 'core_members', 'm' ), 'mm.marker_member_id=m.member_id' )
							->first();

				if ( $marker['group_type'] == 'member' )
				{
					$photo = \IPS\Member::photoUrl( $marker );

					$output = \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->popupContent( $marker, $photo );

				}
				else
				{
					$output = isset( $marker['popup'] ) ? $marker['popup'] : \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->customMarkerPopup( $marker );
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
	protected function mapquestSearch()
	{
		$location 	= \IPS\Request::i()->q;
		$data 		= array();
		
		if ( $location )
		{
			$apiKey = \IPS\membermap\Application::getApiKeys( 'mapquest' );
			try
			{
				$data = \IPS\Http\Url::external( "https://open.mapquestapi.com/nominatim/v1/search.php" )->setQueryString( 
					array(
						'key' => $apiKey, 
						'format' => 'json', 
						'q' => urlencode( $location ),
						'accept-language' => isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : NULL,
					) )->request( 15 )->get()->decodeJson();
			}
			catch( \Exception $e ) 
			{
				\IPS\Log::log( $e, 'membermap' );
			}
		}
		
		\IPS\Output::i()->json( $data );	
	}

	/**
	 * MapQuest reverse lookup. Takes coordinates, return an address/location
	 * 
	 * @return json
	 */
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