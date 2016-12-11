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
	 * 
	 * @return  array(
					'appName'				=> '', // Application name. Will be used as the name of the group in the map
					'popup' 				=> '', // Popup content
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
		if ( ! \IPS\Settings::i()->membermap_calendarExt )
		{
			return;
		}
		
		$where = array();
		$where[] = array( '( event_location IS NOT NULL AND LEFT( event_location, 11 ) != \'{"lat":null\' )' );

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
		$events	= \IPS\calendar\Event::getItemsWithPermission( array_merge( $where, $nonRecurring ), 'event_start_date ASC', NULL );

		/* We need to make sure ranged events repeat each day that they occur on */
		$formattedEvents	= array();

		if( $formatEvents )
		{
			foreach( $events as $event )
			{
				/* Is this a ranged event? */
				if( $event->_end_date !== NULL AND $event->_start_date->mysqlDatetime( FALSE ) < $event->_end_date->mysqlDatetime( FALSE ) )
				{
					$date	= $event->_start_date;
					while( $date->mysqlDatetime( FALSE ) < $event->_end_date->mysqlDatetime( FALSE ) )
					{
						$formattedEvents[ $date->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
						$date	= $date->adjust( '+1 day' );
					}

					$formattedEvents[ $event->_end_date->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
				}
				else
				{
					$formattedEvents[ $event->_start_date->mysqlDatetime( FALSE ) ]['single'][ $event->id ]	= $event;
				}
			}
		}
		else
		{
			$formattedEvents	= iterator_to_array( $events );
		}

		/* Now get the recurring events.... */
		$recurringEvents	= \IPS\calendar\Event::getItemsWithPermission( array_merge( $where, array( array( 'event_recurring IS NOT NULL' ) ) ), 'event_start_date ASC', NULL );

		/* Loop over any results */
		foreach( $recurringEvents as $event )
		{
			/* Find occurrences within our date range (if any) */
			$thisEndDate	= ( $endDate ? $endDate : $startDate->adjust( "+2 years" ) );
			$occurrences	= $event->findOccurrences( $startDate, $thisEndDate );

			/* Do we have any? */
			if( count( $occurrences ) )
			{
				/* Are we formatting events? If so, place into the array as appropriate. */
				if( $formatEvents )
				{
					foreach( $occurrences as $occurrence )
					{
						/* Is this a ranged repeating event? */
						if( $occurrence['endDate'] !== NULL )
						{
							$date	= $occurrence['startDate'];
							$eDate	= ( $thisEndDate->mysqlDatetime( FALSE ) < $occurrence['endDate']->mysqlDatetime( FALSE ) ) ? $thisEndDate : $occurrence['endDate'];
							while( $date->mysqlDatetime( FALSE ) < $eDate->mysqlDatetime( FALSE ) )
							{
								$formattedEvents[ $date->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
								$date	= $date->adjust( '+1 day' );
							}

							$formattedEvents[ $occurrence['endDate']->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
						}
						else
						{
							$formattedEvents[ $occurrence['startDate']->mysqlDatetime( FALSE ) ]['single'][ $event->id ]	= $event;
						}
					}
				}
				/* Otherwise we only want one instance of the event in our final array */
				else
				{
					$formattedEvents[]	= $event;
				}
			}
		}

		/* Resort non-formatted events */
		if( $formatEvents === FALSE )
		{
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
		}

		$return = array();

		if ( is_array( $formattedEvents ) AND count( $formattedEvents ) )
		{
			foreach( $formattedEvents as $event )
			{
				$location = json_decode( $event->location, TRUE );
				if ( ! $location['lat'] OR ! $location['long'] )
				{
					continue;
				}

				$return[] = array(
					'appName'				=> 'Calendar',
					'popup' 				=> \IPS\Theme::i()->getTemplate( 'map', 'membermap', 'front' )->calendarPopup( $event, $startDate ),
					'marker_lat'			=> $location['lat'],
					'marker_lon'			=> $location['long'],
					'group_pin_bg_colour'	=> \IPS\Settings::i()->membermap_calendar_bgcolour ?: "white",
					'group_pin_colour'		=> \IPS\Settings::i()->membermap_calendar_colour ?: "#ff0000",
					'group_pin_icon'		=> \IPS\Settings::i()->membermap_calendar_icon ?: 'fa-calendar',
				);
			}
		}

		return $return;
	}
}