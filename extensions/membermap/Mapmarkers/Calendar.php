<?php

namespace IPS\membermap\extensions\membermap\Mapmarkers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Calendar
 */
class _Calendar
{
	/**
	 * Settings Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @return	void
	 */
	public function getSettings( &$form )
	{
		if ( ! \IPS\Application::appIsEnabled( 'calendar' ) )
		{
			return $form;
		}

		$form->addTab( 'membermap_settings_tab_calendar' );
		$form->addHeader( 'membermap_calendarExt_header' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'membermap_calendarExt', \IPS\Settings::i()->membermap_calendarExt, FALSE, 
			array( 'togglesOn' => array( 'membermap_calendars', 'membermap_calendar_days_ahead', 'form_header_membermap_calendar_icon', 'membermap_calendar_icon', 'membermap_calendar_colour', 'membermap_calendar_bgcolour', 'membermap_calendar_marker_example' ) ) 
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

		$form->addHeader( 'membermap_calendar_icon');

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

		$form->addDummy( 'membermap_calendar_marker_example', "<span class='awesome-marker awesome-marker-icon-{$bgColour} markerExample' data-prefix='membermap_calendar'><i class='fa fa-fw {$icon}' style='color: {$iconColour}'></i></span>", '', '', 'membermap_calendar_marker_example' );

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
		if ( ! \IPS\Settings::i()->membermap_calendarExt OR ! \IPS\Application::appIsEnabled( 'calendar' ) )
		{
			return;
		}
		
		$where = array();
		$where[] = array( 'event_location IS NOT NULL' );

		if( \IPS\Settings::i()->membermap_calendars !== '*' )
		{
			$where[] = array( \IPS\Db::i()->in( 'event_calendar_id', explode( ',', \IPS\Settings::i()->membermap_calendars ) ) );
		}

		$startDate	= new \IPS\calendar\Date( "now",  NULL );
		$endDate	= $startDate->adjust( "+" . intval( \IPS\Settings::i()->membermap_calendar_days_ahead ) . " days" );

		/* Get timezone adjusted versions of start/end time */
		$startDateTimezone	= \IPS\calendar\Date::parseTime( $startDate->mysqlDatetime() );
		$endDateTimezone	= ( $endDate !== NULL ) ? \IPS\calendar\Date::parseTime( $endDate->mysqlDatetime() ) : NULL;

		/* First we get the non recurring events based on the timestamps */
		$nonRecurring	= array();
		$nonRecurring[]	= array( 'event_recurring IS NULL' );

		if( $endDate !== NULL AND $startDate == $endDate )
		{
			$nonRecurring[]	= array( 
				'( 
					( event_end_date IS NULL AND DATE( event_start_date ) = ? AND event_all_day=1 )
					OR
					( event_end_date IS NOT NULL AND DATE( event_start_date ) <= ? AND DATE( event_end_date ) >= ? AND event_all_day=1 )
					OR
					( event_end_date IS NULL AND event_start_date >= ? AND event_start_date <= ? AND event_all_day=0 )
					OR
					( event_end_date IS NOT NULL AND event_start_date <= ? AND event_end_date >= ? AND event_all_day=0 )
				)',
				$startDate->mysqlDatetime( FALSE ),
				$endDate->mysqlDatetime( FALSE ),
				$startDate->mysqlDatetime( FALSE ),
				$startDateTimezone->mysqlDatetime(),
				$startDateTimezone->adjust('+1 day')->mysqlDatetime(),
				$endDateTimezone->adjust('+1 day')->mysqlDatetime(),
				$startDateTimezone->mysqlDatetime()
			);
		}
		elseif( $endDate !== NULL )
		{
			$nonRecurring[]	= array( 
				'( 
					( event_end_date IS NULL AND DATE( event_start_date ) >= ? AND DATE( event_start_date ) <= ? AND event_all_day=1 )
					OR
					( event_end_date IS NOT NULL AND DATE( event_start_date ) <= ? AND DATE( event_end_date ) >= ? AND event_all_day=1 )
					OR
					( event_end_date IS NULL AND event_start_date >= ? AND event_start_date <= ? AND event_all_day=0 )
					OR
					( event_end_date IS NOT NULL AND event_start_date <= ? AND event_end_date >= ? AND event_all_day=0 )
				)',
				$startDate->mysqlDatetime( FALSE ),
				$endDate->mysqlDatetime( FALSE ),
				$endDate->mysqlDatetime( FALSE ),
				$startDate->mysqlDatetime( FALSE ),
				$startDateTimezone->mysqlDatetime(),
				$endDateTimezone->mysqlDatetime(),
				$endDateTimezone->mysqlDatetime(),
				$startDateTimezone->mysqlDatetime()
			);
		}
		else
		{
			$nonRecurring[]	= array( 
				"( 
					( DATE( event_start_date ) >= ? AND event_all_day=1 )
					OR
					( event_start_date >= ? AND event_all_day=0 )
					OR 
					( event_end_date IS NOT NULL AND DATE( event_start_date ) <= ? AND DATE( event_end_date ) >= ? AND event_all_day=1 ) 
					OR
					( event_end_date IS NOT NULL AND event_start_date <= ? AND event_end_date >= ? AND event_all_day=0 ) 
				)",
				$startDate->mysqlDatetime( FALSE ),
				$startDateTimezone->mysqlDatetime(),
				$startDate->mysqlDatetime( FALSE ),
				$startDate->mysqlDatetime( FALSE ),
				$startDateTimezone->adjust('+1 day')->mysqlDatetime(),
				$startDateTimezone->mysqlDatetime()
			);
		}

		/* Get the non-recurring events */
		$events	= \IPS\calendar\Event::getItemsWithPermission( array_merge( $where, $nonRecurring ), 'event_start_date ASC', NULL, NULL );

		/* We need to make sure ranged events repeat each day that they occur on */
		$formattedEvents	= array();

		$formattedEvents	= iterator_to_array( $events );


		/* Now get the recurring events.... */
		$recurringEvents	= \IPS\calendar\Event::getItemsWithPermission( array_merge( $where, array( array( 'event_recurring IS NOT NULL' ) ) ), 'event_start_date ASC', NULL, NULL );

		/* Loop over any results */
		foreach( $recurringEvents as $event )
		{
			/* Find occurrences within our date range (if any) */
			$thisEndDate	= ( $endDate ? $endDate : $startDate->adjust( "+2 years" ) );
			$occurrences	= $event->findOccurrences( $startDate, $thisEndDate );

			/* Do we have any? */
			if( count( $occurrences ) )
			{
				$formattedEvents[]	= $event;
			}
		}

		/* @note: Error suppressor is needed due to PHP bug https://bugs.php.net/bug.php?id=50688 */
		@usort( $formattedEvents, function( $a, $b ) use ( $startDate )
		{
			if( $a->nextOccurrence( $startDate, 'startDate' ) === NULL )
			{
				return -1;
			}

			if( $b->nextOccurrence( $startDate, 'startDate' ) === NULL )
			{
				return 1;
			}

			if ( $a->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() == $b->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() )
			{
				return 0;
			}
			
			return ( $a->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() < $b->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() ) ? -1 : 1;
		} );

		$return = array();
		if ( is_array( $formattedEvents ) AND count( $formattedEvents ) )
		{
			$appName = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'frontnavigation_calendar' );
			
			foreach( $formattedEvents as $event )
			{
				$location = json_decode( $event->location, TRUE );

				$location['lat']  = isset( $location['mm_lat'] ) ? $location['mm_lat'] : $location['lat'];
				$location['long'] = isset( $location['mm_long'] ) ? $location['mm_long'] : $location['long'];

				if ( ! $location['lat'] OR ! $location['long'] )
				{
					continue;
				}

				$nextDate = NULL;

				if ( $event->all_day )
				{
					$nextDate = $event->nextOccurrence( $startDate, 'endDate' ) !== NULL ? $event->nextOccurrence( $startDate, 'endDate' ) : $event->lastOccurrence( 'endDate' );
					
					/* No end date, then use the start date */
					if ( $nextDate == NULL )
					{
						$nextDate = $event->nextOccurrence( $startDate, 'startDate' ) !== NULL ? $event->nextOccurrence( $startDate, 'startDate' ) : $event->lastOccurrence( 'startDate' );
					}

					/* I give up ... */
					if ( $nextDate == NULL )
					{
						continue;
					}

					$nextDate = $nextDate->adjust( "+1 day 3 hours" );
				}
				else
				{
					$nextDate = $event->nextOccurrence( $startDate, 'endDate' ) !== NULL ? $event->nextOccurrence( $startDate, 'endDate' ) : $event->lastOccurrence( 'endDate' );

					/* No end date, then use the start date */
					if ( $nextDate == NULL )
					{
						$nextDate = $event->nextOccurrence( $startDate, 'startDate' ) !== NULL ? $event->nextOccurrence( $startDate, 'startDate' ) : $event->lastOccurrence( 'startDate' );
					}

					/* I give up ... */
					if ( $nextDate == NULL )
					{
						continue;
					}

					$nextDate = $nextDate->adjust( '+3 hours' );
				}

				/* If the generated timestamp is less than the current, there's no point in storing this */
				if ( $nextDate->getTimestamp() <= time() )
				{
					continue;
				}

				$viewPerms = $event->container()->permissions(); 
				$viewPerms = $viewPerms['perm_2'];

				$return[] = array(
					'marker_id'				=> $event->id,
					'ext'					=> 'membermap_Calendar',
					'appName'				=> $appName,
					'expiryDate'			=> $nextDate->getTimestamp(),
					'marker_lat'			=> $location['lat'],
					'marker_lon'			=> $location['long'],
					'group_pin_bg_colour'	=> \IPS\Settings::i()->membermap_calendar_bgcolour ?: "white",
					'group_pin_colour'		=> \IPS\Settings::i()->membermap_calendar_colour ?: "#ff0000",
					'group_pin_icon'		=> \IPS\Settings::i()->membermap_calendar_icon ?: 'fa-calendar',
					'viewPerms'				=> $viewPerms,
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
			$event = \IPS\calendar\Event::load( intval( $id ) );

			$startDate	= new \IPS\calendar\Date( "now",  NULL );

			return \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->calendarPopup( $event, $startDate );
		}
		catch( \Exception $e )
		{
			return 'invalid_id';
		}
	}
}