<?php
/**
 * @brief       Member Map Mapmarkers Extension: Clubs
 * @author      Stuart Silvester & Martin Aronsen
 * @copyright   (c) 2015 Stuart Silvester & Martin Aronsen
 * @license     http://www.invisionpower.com/legal/standards/
 * @package     IPS Community Suite
 * @subpackage	Member Map
 * @since       27 Aug 2017
 * @version     SVN_VERSION_NUMBER
 */

/*
 *  If you store location data in your app you can use this extension to present it in Member Map.
 *  If you need to force a rebuild of the Member Map cache, call \IPS\membermap\Map::i()->invalidateJsonCache()
 */

namespace IPS\membermap\extensions\membermap\Mapmarkers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs
 */
class _Clubs
{
	/**
	 * Settings Form
	 *
	 * @param   \IPS\Helpers\Form       $form   The form
	 * @return  void
	 */
	public function getSettings( &$form )
	{
		if ( ! \IPS\Settings::i()->clubs )
		{
			return $form;
		}

		$form->addTab( 'membermap_settings_tab_clubs' );
		$form->addHeader( 'membermap_clubsExt_header' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_clubsExt', \IPS\Settings::i()->membermap_clubsExt, FALSE, 
			array( 'togglesOn' => array( 'membermap_clubs', 'membermap_clubs_showClubLocations', 'membermap_clubs_showInClubHeader' ) ) 
		) );


		$clubs = iterator_to_array( \IPS\Db::i()->select( 'id, name', 'core_clubs' )->setKeyField( 'id' )->setValueField( 'name' ) );

		$form->add( new \IPS\Helpers\Form\Select(
			'membermap_clubs',
			\IPS\Settings::i()->membermap_clubs != '' ? ( \IPS\Settings::i()->membermap_clubs === '*' ? '*' : explode( ",", \IPS\Settings::i()->membermap_clubs ) ) : '*',
			FALSE,array( 'options' => $clubs, 'multiple' => TRUE, 'parse' => 'normal', 'unlimited' => '*', 'unlimitedLang' => 'all' ), NULL, NULL, NULL, 'membermap_clubs'
		) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_clubs_showClubLocations', \IPS\Settings::i()->membermap_clubs_showClubLocations, FALSE, 
			array( 'togglesOn' => array( 'form_header_membermap_clubs_icon', 'membermap_clubs_icon', 'membermap_clubs_colour', 'membermap_clubs_bgcolour', 'membermap_clubs_marker_example' ) ), NULL, NULL, NULL, 'membermap_clubs_showClubLocations' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_clubs_showInClubHeader', \IPS\Settings::i()->membermap_clubs_showInClubHeader, FALSE, array(), NULL, NULL, NULL, 'membermap_clubs_showInClubHeader' ) );


		$form->addHeader( 'membermap_clubs_icon' );

		$colours = array( 
			'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
			'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black', 'white'
		);


		$icon       = \IPS\Settings::i()->membermap_clubs_icon ?: 'fa-users';
		$iconColour = \IPS\Settings::i()->membermap_clubs_colour ?: '#ffffff';
		$bgColour   = \IPS\Settings::i()->membermap_clubs_bgcolour ?: 'orange';

		/* Selected a valid colour? */
		$bgColour = in_array( $bgColour, $colours ) ? $bgColour : 'orange';
		
		$radioOpt = array();
		foreach( $colours as $c )
		{
			$radioOpt[ $c ] = \IPS\Theme::i()->resource( "awesome-marker-icon-{$c}.png", "membermap", 'admin' );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'membermap_clubs_icon', $icon, TRUE, array(), NULL, NULL, NULL, 'membermap_clubs_icon' ) );
		$form->add( new \IPS\Helpers\Form\Color( 'membermap_clubs_colour', $iconColour, TRUE, array(), NULL, NULL, NULL, 'membermap_clubs_colour' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'membermap_clubs_bgcolour', $bgColour, TRUE, array(
			'options' => $radioOpt,
			'parse' => 'image',
			'descriptions' => array( 'white' => \IPS\Member::loggedIn()->language()->addToStack( 'group_pin_bg_colour_white' ) ) /* Just because white is difficult to see on the page */
		), NULL, NULL, NULL, 'membermap_clubs_bgcolour'));

		$form->addDummy( 'membermap_clubs_marker_example', "<span class='awesome-marker awesome-marker-icon-{$bgColour} markerExample' data-prefix='membermap_clubs'><i class='fa fa-fw {$icon}' style='color: {$iconColour}'></i></span>", '', '', 'membermap_clubs_marker_example' );

		return $form;
	}
	/**
	 * 
	 * @return  array(
					'appName'               => '', // Application name. Will be used as the name of the group in the map
					'popup'                 => '', // Popup content
					'marker_lat'            => 0,  // Latitude
					'marker_lon'            => 0,  // Longitude
					'group_pin_bg_colour'   => "", // Marker pin colour. +
					'group_pin_colour'      => "", // Any HTML colour names
					'group_pin_icon'        => "fa-", // FontAwesome icon
				);

		+: Valid colours are 'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
			'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black' and 'white'.
	 */
	public function getLocations()
	{
		if ( ! \IPS\Settings::i()->membermap_clubsExt OR ! \IPS\Settings::i()->clubs OR ! \IPS\Settings::i()->clubs_locations )
		{
			return;
		}
		
		$where = array();
		$where[] = array( 'location_lat IS NOT NULL' );

		$where[] = array( "type<>?", \IPS\Member\Club::TYPE_PRIVATE );

		if( \IPS\Settings::i()->membermap_clubs !== '*' )
		{
			$where[] = array( \IPS\Db::i()->in( 'id', explode( ',', \IPS\Settings::i()->membermap_clubs ) ) );
		}

		$appName = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'membermap_marker_group_Clubs' );
		$return  = array();
		
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_clubs', $where ), 'IPS\Member\Club' ) as $club )
		{
			$return[] = array(
				'appName'				=> $appName,
				'popup' 				=> \IPS\Theme::i()->getTemplate( 'clubs', 'core', 'front' )->mapPopup( $club ),
				'marker_lat'			=> $club->location_lat,
				'marker_lon'			=> $club->location_long,
				'group_pin_bg_colour'	=> \IPS\Settings::i()->membermap_clubs_bgcolour ?: "orange",
				'group_pin_colour'		=> \IPS\Settings::i()->membermap_clubs_colour ?: "#ffffff",
				'group_pin_icon'		=> \IPS\Settings::i()->membermap_clubs_icon ?: 'fa-users',
			);
		}

		return $return;
	}
}