//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class membermap_hook_coreModuleFrontMembersProfile extends _HOOK_CLASS_
{
	/**
	 * Save Member
	 *
	 * @param $form
	 * @param array $values
	 */
	protected function _saveMember( $form, array $values )
	{
		parent::_saveMember( $form, $values );

		\IPS\Log::log( $values );
		$hasMemberLocationPlugin = FALSE;

		if ( isset( $values['member_location']['lat'] ) and $values['member_location']['lat'] )
		{
			if ( \IPS\Db::i()->checkForTable( 'core_member_locations' ) )
			{
				$hasMemberLocationPlugin = TRUE;
			}
		}

		$_fields 	= array_map( 'intval', explode( ',', \IPS\Settings::i()->membermap_profileLocationField ) );
		$fieldValue = NULL;
		$lat = $lng = $location = NULL;

		foreach( $_fields as $fieldKey )
		{
			/* borisMemberLocation */
			if ( $fieldKey == 999999 AND isset( $values['member_location']['lat'] ) and $values['member_location']['lat'] )
			{
				$lat 		= $values['member_location']['lat'];
				$lng 		= $values['member_location']['long'];
				$fieldValue	= $values['member_location']['description'] ?: "";

				break;
			}
			else if ( isset( $values['core_pfield_' . $fieldKey ] ) )
			{
				$fieldValue = trim( $values['core_pfield_' . $fieldKey ] );
			
				if ( ! empty( $fieldValue ) AND $fieldValue != "null" )
				{
					break;
				}
			}
		}
		
		if ( $fieldValue !== NULL AND ! empty( $fieldValue ) AND $fieldValue != "null" )
		{
			try
			{
				/* If it's an array, it might be from an address field, which already have the lat/lng data */
				if( \is_array( json_decode( $fieldValue, TRUE ) ) )
				{
					$addressData = json_decode( $fieldValue, TRUE );

					if ( isset( $addressData['lat'] ) AND \is_float( $addressData['lat'] ) AND \is_float( $addressData['long'] ) )
					{
						$lat = \floatval( $addressData['lat'] );
						$lng = \floatval( $addressData['long'] );
					}

					$addressData['addressLines'][] = $addressData['city'];

					if ( \is_array( $addressData['addressLines'] ) AND \count( $addressData['addressLines'] ) )
					{
						$location = implode( ', ', $addressData['addressLines'] );
					}
				}
				
				/* If lat and lng is still null, \IPS\Geolocation was not able to find it.  */
				if ( $lat === NULL AND $lng === NULL )
				{
					/* Remove HTML, newlines, tab, etc, etc */
					if ( $location === NULL )
					{
						$fieldValue = preg_replace( "/[\\x00-\\x20]|\\xc2|\\xa0+/", ' ', strip_tags( $fieldValue ) );
						$fieldValue = trim( preg_replace( "/\s\s+/", ' ', $fieldValue ) );
					}

					/* To my understanding we're not allowed to use \IPS\Geolocation, as that uses Google API, and we're not showing the info on a Google Map. */
					$nominatim = \IPS\membermap\Map::i()->getLatLng( $location ?: $fieldValue );

					if( \is_array( $nominatim ) AND \count( $nominatim ) )
					{
						$lat 		= $nominatim['lat'];
						$lng 		= $nominatim['lng'];
						$location 	= $location ?: $nominatim['location'];
					}
				}

				if( $lat AND $lng )
				{
					$existingMarker = \IPS\membermap\Map::i()->getMarkerByMember( $this->member->member_id, FALSE, FALSE );

					if( $existingMarker instanceof \IPS\membermap\Markers\Markers )
					{
						$marker 			= $existingMarker;
						$marker->updated 	= time();
					}
					else
					{
						$groupId = \IPS\membermap\Map::i()->getMemberGroupId();

						$marker = \IPS\membermap\Markers\Markers::createItem( $this->member, \IPS\Request::i()->ipAddress(), new \IPS\DateTime, \IPS\membermap\Markers\Groups::load( $groupId ) );
					}

					$marker->name 		= $this->member->name;
					$marker->lat 		= $lat;
					$marker->lon 		= $lng;
					$marker->location 	= $location ?: $fieldValue;
					

					/* Save and add to search index */
					$marker->save();

					\IPS\Content\Search\Index::i()->index( $marker );
				}
			}
			catch ( \Exception $e )
			{
				/* Something went wrong. Such as the input field being an editor */
				\IPS\Log::log( $e, 'membermap' );
			}
		}

	}

}
