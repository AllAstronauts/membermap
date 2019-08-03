//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class membermap_hook_calendarEvent extends _HOOK_CLASS_
{
	/**
	 * Save Changed Columns
	 *
	 * @return void
	 */
	public function save(): void
	{
		parent::save();

		if ( ! \IPS\Settings::i()->membermap_calendarExt )
		{
			return;
		}

		/* Rebuild the cache if any event with a location is changed */
		if ( $this->location !== NULL )
		{
			\IPS\membermap\Map::i()->invalidateJsonCache();

			$location = json_decode( $this->location, TRUE );
			$_locationArr = array();

			if ( ! $location['lat'] OR ! $location['long'] )
			{
				foreach ( array( 'country', 'region', 'city', 'addressLines' ) as $k )
				{
					if ( $location[ $k ] )
					{
						if ( \is_array( $location[ $k ] ) )
						{
							foreach ( array_reverse( $location[ $k ] ) as $v )
							{
								if ( $v )
								{
									$_locationArr[] = $v;
								}
							}
						}
						else
						{
							$_locationArr[] = $location[ $k ];
						}
					}
				}

				$_locationStr = trim( implode( ', ', array_reverse( $_locationArr ) ), ', ' );

				if ( $_locationStr AND mb_strlen( $_locationStr ) > 4 )
				{
					if ( $latLng = \IPS\membermap\Map::i()->getLatLng( $_locationStr ) )
					{
						if ( $latLng['lat'] AND $latLng['lng'] )
						{
							/* Don't overwrite what's already in the JSON, as that will change how the mini map looks */
							$location['mm_lat'] = $latLng['lat'];
							$location['mm_long'] = $latLng['lng'];

							$this->location = json_encode( $location );
							parent::save();
						}
					}
				}
			}
		}
	}
}