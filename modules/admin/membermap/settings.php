<?php

/**
 * @brief		Settings Controller
 * @author		<a href='http://ipb.silvesterwebdesigns.com'>Stuart Silvester & Martin Aronsen</a>
 * @copyright	(c) 2015 Stuart Silvester & Martin Aronsen
 * @package		IPS Social Suite
 * @subpackage	Member Map
 * @since		20 Oct 2015
 * @version		3.0.1
 */

namespace IPS\membermap\modules\admin\membermap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__membermap_membermap_settings');

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'membermap', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_membermap.js', 'membermap', 'admin' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'jquery-ui.css', 'membermap', 'global' ) );
		
		\IPS\Output::i()->jsVars['membermap_mapquestAPI'] = \IPS\membermap\Application::getApiKeys( 'mapquest' ); 

		$form = new \IPS\Helpers\Form;

		/* API Key */
		$form->addHeader('api_settings');
		$form->add( new \IPS\Helpers\Form\Text( 'membermap_mapQuestAPI', \IPS\Settings::i()->membermap_mapQuestAPI, TRUE, array(), NULL, NULL, NULL, 'membermap_mapQuestAPI' ) );

		if ( ! empty( \IPS\Settings::i()->membermap_mapQuestAPI ) )
		{
			/* Map Settings */
			$form->attributes['data-controller'] 	= 'membermap.admin.membermap.settings';
			$form->attributes['id'] 				= 'membermap_form_settings';

			$form->addHeader('map_settings');
			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_groupByMemberGroup', \IPS\Settings::i()->membermap_groupByMemberGroup ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_enable_clustering', \IPS\Settings::i()->membermap_enable_clustering ) );
			$form->add( new \IPS\Helpers\Form\Text( 'membermap_bbox_location', \IPS\Settings::i()->membermap_bbox_location, FALSE, array(), NULL, NULL, NULL, 'membermap_bbox_location' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'membermap_bbox_zoom', intval( \IPS\Settings::i()->membermap_bbox_zoom ), FALSE, array( 'min' => 1, 'max' => 18 ) ) );
			$form->hiddenValues['membermap_bbox'] = \IPS\Settings::i()->membermap_bbox;


			/* Profile Synchronization */
			$form->addHeader( 'membermap_autoUpdate' );

			$profileFields = array( '' => ' -- ' . \IPS\Member::loggedIn()->language()->addToStack( 'membermap_profileLocationField' ) . ' -- ' );
			foreach ( \IPS\core\ProfileFields\Field::fieldData() as $group => $fields )
			{
				foreach ( $fields as $id => $field )
				{
					$field = \IPS\core\ProfileFields\Field::constructFromData( $field )->buildHelper();
					
					$profileFields[ 'core_pfieldgroups_' . $group ][ $id ] = $field->name;
				}
			}

			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_monitorLocationField', \IPS\Settings::i()->membermap_monitorLocationField, FALSE, 
				array( 'togglesOn' => array( 'membermap_profileLocationField', 'membermap_monitorLocationField_groupPerm', 'membermap_syncLocationField' ) ) 
			) );

			$form->add( new \IPS\Helpers\Form\Select( 
				'membermap_profileLocationField', 
				\IPS\Settings::i()->membermap_profileLocationField ? intval( \IPS\Settings::i()->membermap_profileLocationField ) : NULL, 
				FALSE, array( 'options' => $profileFields ), NULL, NULL, NULL, 'membermap_profileLocationField' 
			) );

			$form->add( new \IPS\Helpers\Form\Select(
				'membermap_monitorLocationField_groupPerm',
				\IPS\Settings::i()->membermap_monitorLocationField_groupPerm != '' ? ( \IPS\Settings::i()->membermap_monitorLocationField_groupPerm === '*' ? '*' : explode( ",", \IPS\Settings::i()->membermap_monitorLocationField_groupPerm ) ) : '*',
				FALSE,array( 'options' => \IPS\Member\Group::groups(), 'multiple' => TRUE, 'parse' => 'normal', 'unlimited' => '*', 'unlimitedLang' => 'all' ), NULL, NULL, NULL, 'membermap_monitorLocationField_groupPerm'
			) );

			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_syncLocationField', \IPS\Settings::i()->membermap_syncLocationField, FALSE, array(), NULL, NULL, NULL, 'membermap_syncLocationField' ) );


			/* Calendar Extension */
			$form->addHeader( 'membermap_calendarExt_header' );
			$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_calendarExt', \IPS\Settings::i()->membermap_calendarExt, FALSE, 
				array( 'togglesOn' => array( 'membermap_calendars', 'membermap_calendar_icon', 'membermap_calendar_colour', 'membermap_calendar_bgcolour', 'membermap_calendar_marker_example' ) ) 
			) );


			$calendars = array();

			foreach( \IPS\calendar\Calendar::roots() as $calendar )
			{
				$calendars[ $calendar->id ]	= $calendar->_title;
			}

			$form->add( new \IPS\Helpers\Form\Select(
				'membermap_calendars',
				\IPS\Settings::i()->membermap_calendars != '' ? ( \IPS\Settings::i()->membermap_calendars === '*' ? '*' : explode( ",", \IPS\Settings::i()->membermap_calendars ) ) : '*',
				FALSE,array( 'options' => $calendars, 'multiple' => TRUE, 'parse' => 'normal', 'unlimited' => '*', 'unlimitedLang' => 'all' ), NULL, NULL, NULL, 'membermap_calendars'
			) );


			$form->add( new \IPS\Helpers\Form\Number( 'membermap_calendar_days_ahead', \IPS\Settings::i()->membermap_calendar_days_ahead, TRUE, array( 'min' => 7 ), NULL, NULL, NULL, 'membermap_calendar_days_ahead' ) );

			$colours = array( 
				'red', 'darkred', 'lightred', 'orange', 'beige', 'green', 'darkgreen', 'lightgreen', 'blue', 'darkblue', 'lightblue',
				'purple', 'darkpurple', 'pink', 'cadetblue', 'gray', 'lightgray', 'black', 'white'
			);


			$icon 		= \IPS\Settings::i()->membermap_calendar_icon ?: 'fa-calendar';
			$iconColour = \IPS\Settings::i()->membermap_calendar_colour ?: '#ff0000';
			$bgColour 	= \IPS\Settings::i()->membermap_calendar_bgcolour ?: 'red';

			/* Selected a valid colour? */
			$bgColour = in_array( $bgColour, $colours ) ? $bgColour : 'red';
			
			$radioOpt = array();
			foreach( $colours as $c )
			{
				$radioOpt[ $c ] = \IPS\Theme::i()->resource( "awesome-marker-icon-{$c}.png", "membermap", 'admin' );
			}

			$form->add( new \IPS\Helpers\Form\Text( 'membermap_calendar_icon', $icon, TRUE, array(), NULL, NULL, NULL, 'membermap_calendar_icon' ) );
			$form->add( new \IPS\Helpers\Form\Color( 'membermap_calendar_colour', $iconColour, TRUE, array(), NULL, NULL, NULL, 'membermap_calendar_colour' ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'membermap_calendar_bgcolour', $bgColour, TRUE, array(
				'options' => $radioOpt,
				'parse' => 'image',
				'descriptions' => array( 'white' => \IPS\Member::loggedIn()->language()->addToStack( 'group_pin_bg_colour_white' ) ) /* Just because white is difficult to see on the page */
			), NULL, NULL, NULL, 'membermap_calendar_bgcolour'));

			$form->addDummy( 'membermap_calendar_marker_example', "<span class='awesome-marker awesome-marker-icon-{$bgColour}' id='markerExample'><i class='fa fa-fw {$icon}' style='color: {$iconColour}'></i></span>", '', '', 'membermap_calendar_marker_example' );


		}

		if ( $values = $form->values( TRUE ) )
		{
			$values['membermap_bbox'] = \IPS\Request::i()->membermap_bbox;

			if ( empty( $values['membermap_bbox_location'] ) )
			{
				$values['membermap_bbox'] = "";
			}

			\IPS\DB::i()->update( 'core_tasks', array( 'enabled' => isset( $values['membermap_syncLocationField'] ) AND $values['membermap_syncLocationField'] ? 1 : 0 ), array( '`key`=?', 'locationSync' ) );


			$form->saveAsSettings( $values );
			\IPS\membermap\Map::i()->invalidateJsonCache();
			
			\IPS\Session::i()->log( 'acplogs__membermap_settings' );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=membermap&module=membermap&controller=settings" ), 'saved' );
		}
		
		\IPS\Output::i()->output = $form;
	}
}