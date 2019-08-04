<?php
/**
 * @brief       Member Map Mapmarkers extension: Pages
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Member Map
 * @since       27 Aug 2017
 * @version     -storm_version-
 */

namespace IPS\membermap\extensions\membermap\Mapmarkers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Pages
 */
class _Pages
{
	/**
	 * Settings Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @return	void
	 */
	public function getSettings( &$form )
	{
		if ( ! \IPS\Application::appIsEnabled( 'cms' ) )
		{
			return $form;
		}

		$form->addTab( 'membermap_settings_tab_pages' );
		$form->addHeader( 'membermap_pagesExt_header' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_pagesExt', \IPS\Settings::i()->membermap_pagesExt, FALSE, 
			array( 'togglesOn' => array( 'membermap_pages_databases', 'membermap_pages_days_behind', 'form_header_membermap_pages_icon', 'membermap_pages_icon', 'membermap_pages_colour', 'membermap_pages_bgcolour', 'membermap_pages_marker_example' ) ) 
		) );


		$databases = array();

		foreach( \IPS\cms\Databases::databases() as $database )
		{
			$class = 'IPS\cms\Fields' . $database->id;
			
			foreach( $class::roots() as $field )
			{
				if ( $field->type == 'Address' )
				{
					$databases[ $database->id ]	= $database->_title;
					break;
				}
			}
		}

		$form->add( new \IPS\Helpers\Form\Select(
			'membermap_pages_databases',
			\IPS\Settings::i()->membermap_pages_databases != '' ? ( \IPS\Settings::i()->membermap_pages_databases === '*' ? '*' : explode( ",", \IPS\Settings::i()->membermap_pages_databases ) ) : '*',
			FALSE,array( 'options' => $databases, 'multiple' => TRUE, 'parse' => 'normal', 'unlimited' => '*', 'unlimitedLang' => 'all' ), NULL, NULL, NULL, 'membermap_pages_databases'
		) );


		$form->add( new \IPS\Helpers\Form\Number( 'membermap_pages_days_behind', \IPS\Settings::i()->membermap_pages_days_behind, TRUE, array( 'min' => 7 ), NULL, NULL, NULL, 'membermap_pages_days_behind' ) );

		$form->addHeader( 'membermap_pages_icon');

		$colours = array( 
			'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
			'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black', 'white'
		);


		$icon 		= \IPS\Settings::i()->membermap_pages_icon ?: 'fa-files-o';
		$iconColour = \IPS\Settings::i()->membermap_pages_colour ?: '#FFFFFF';
		$bgColour 	= \IPS\Settings::i()->membermap_pages_bgcolour ?: 'red';

		/* Selected a valid colour? */
		$bgColour = \in_array( $bgColour, $colours ) ? $bgColour : 'red';
		
		$radioOpt = array();
		foreach( $colours as $c )
		{
			$radioOpt[ $c ] = \IPS\Theme::i()->resource( "awesome-marker-icon-{$c}.png", "membermap", 'admin' );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'membermap_pages_icon', $icon, TRUE, array(), NULL, NULL, NULL, 'membermap_pages_icon' ) );
		$form->add( new \IPS\Helpers\Form\Color( 'membermap_pages_colour', $iconColour, TRUE, array(), NULL, NULL, NULL, 'membermap_pages_colour' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'membermap_pages_bgcolour', $bgColour, TRUE, array(
			'options' => $radioOpt,
			'parse' => 'image',
			'descriptions' => array( 'white' => \IPS\Member::loggedIn()->language()->addToStack( 'group_pin_bg_colour_white' ) ) /* Just because white is difficult to see on the page */
		), NULL, NULL, NULL, 'membermap_pages_bgcolour'));

		$form->addDummy( 'membermap_pages_marker_example', "<span class='awesome-marker awesome-marker-icon-{$bgColour} markerExample' data-prefix='membermap_pages'><i class='fa fa-fw {$icon}' style='color: {$iconColour}'></i></span>", '', '', 'membermap_pages_marker_example' );

		return $form;
	}

	/**
	 * 
	 * @return  array(
					'appName'				=> '', // Application name. Will be used as the name of the group in the map
					'expiryDate'			=> 0,  // Unix timestamp for when the cache would need to be re-done.
					'marker_lat'			=> 0,  // Latitude
					'marker_lon'			=> 0,  // Longitude
					'group_pin_bg_colour'	=> "", // Marker pin colour. +
					'group_pin_colour'		=> "", // Any HTML colour names
					'group_pin_icon'		=> "fa-", // FontAwesome icon
				);

		+: Valid colours are 'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
			'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black' and 'white'.
	 */
	public function getLocations()
	{
		if ( ! \IPS\Settings::i()->membermap_pagesExt OR ! \IPS\Application::appIsEnabled( 'cms' ) )
		{
			return array();
		}

		$return 	= [];
		$databases  = [];
		$_databases = \IPS\Settings::i()->membermap_pages_databases != '' ? 
						( \IPS\Settings::i()->membermap_pages_databases === '*' ? '*' : explode( ",", \IPS\Settings::i()->membermap_pages_databases ) ) : '*';

		foreach( \IPS\cms\Databases::databases() as $database )
		{
			if ( $_databases == '*' OR \in_array( $database->id, $_databases ) )
			{
				$class = 'IPS\cms\Fields' . $database->id;
				
				foreach( $class::roots() as $field )
				{
					if ( $field->type == 'Address' )
					{
						$databases[ $database->id ]	= $field->id;
						break;
					}
				}
			}
		}

		if ( ! \count( $databases ) )
		{
			return array();
		}

		$timestamp = time() - ( \IPS\Settings::i()->membermap_pages_days_behind * 60 * 60 * 24 );
		foreach( $databases as $database => $fieldId )
		{

			$appName 		= \IPS\cms\Databases::load( $database )->_title;
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $appName );
			$class 			= 'IPS\cms\Records' . $database;
			$fieldColumn 	= 'field_' . $fieldId;

			$where = [
						[
							"{$fieldColumn} IS NOT NULL AND record_publish_date > ?", $timestamp
						]
			];

			$records = $class::getItemsWithPermission( $where, NULL, NULL, NULL, NULL, NULL, ( new \IPS\Member ) );
			foreach( $records as $record )
			{
				$location = json_decode( $record->$fieldColumn, TRUE );

				$location['lat']  = $location['mm_lat'] ?? $location['lat'] ?? FALSE;
				$location['long'] = $location['mm_long'] ?? $location['long'] ?? FALSE;

				if ( ! $location['lat'] OR ! $location['long'] )
				{
					continue;
				}

				$return[] = array(
					'marker_id'				=> "{$database}-{$record->_id}",
					'ext'					=> 'membermap_Pages',
					'appName'				=> $appName,
					'marker_lat'			=> $location['lat'],
					'marker_lon'			=> $location['long'],
					'group_pin_bg_colour'	=> \IPS\Settings::i()->membermap_pages_bgcolour ?: "red",
					'group_pin_colour'		=> \IPS\Settings::i()->membermap_pages_colour ?: "#FFFFFF",
					'group_pin_icon'		=> \IPS\Settings::i()->membermap_pages_icon ?: 'fa-files-o',
					'viewPerms'				=> '*',
				);
			}
		}

		return $return;
	}

	/**
	 * Get popup HTML
	 * @param  	int 	$id 	Marker ID
	 * @return html
	 */
	public function getPopup( $id )
	{
		try
		{
			list( $database, $record ) = explode( '-', $id );

			$recordsClass = 'IPS\cms\Records' . $database;
			$record = $recordsClass::load( $record );

			$fieldsClass = 'IPS\cms\Fields' . $database;

			foreach( $fieldsClass::roots() as $field )
			{
				if ( $field->type == 'Address' )
				{
					$fieldId 	= $field->id;
					break;
				}
			}

			$fields = $fieldsClass::display( $record->fieldValues(), 'processed', $record->container(), 'id', $record );

			$location = $fields[ $fieldId ] ?? '';

			return \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->pagesPopup( $record, $location );
		}
		catch( \Exception $e )
		{
			return 'invalid_id';
		}
	}
}