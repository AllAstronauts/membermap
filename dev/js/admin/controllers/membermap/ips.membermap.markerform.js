;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.markerform', {

		initialize: function () 
		{
			this.setup();
		},

		setup: function()
		{
			$( '#elInput_marker_location' ).autocomplete({
				source: function( request, response ) 
				{
					ips.getAjax()({ 
						//url: 'http://www.mapquestapi.com/geocoding/v1/address', 
						url: 'http://open.mapquestapi.com/nominatim/v1/search.php',
						type: 'get',
						dataType: 'json',
						data: {
							key: "pEPBzF67CQ8ExmSbV9K6th4rAiEc3wud",

							// MapQuest Geocode
							/*location: request.term,
							outFormat: 'json'*/

							// MapQuest Nominatim
							format: 'json',
							q: request.term,
							extratags: 0,

						},
						success: function( data ) 
						{
							// MapQuest
							/* If adminArea5 is empty, it's likely we don't have a result */
							/*if ( data.results[0].locations[0].adminArea5 )
							{
								response( $.map( data.results[0].locations, function( item )
								{
									return {
										value: item.adminArea5 + 
											( item.adminArea4 ? ', ' + item.adminArea4 : '' ) + 
											( item.adminArea3 ? ', ' + item.adminArea3 : '' ) + 
											( item.adminArea2 ? ', ' + item.adminArea2 : '' ) +
											( item.adminArea1 ? ', ' + item.adminArea1 : '' ),
										latLng: item.latLng
									};
								}));
							}
							else
							{
								response([]);
							}*/

							// MapQuest Nominatim
							response( $.map( data, function( item )
							{
								return {
									value: item.display_name,
									latLng: {
										lat: item.lat,
										lng: item.lon
									}
								};
							}));

						}
					})
				},
				minLength: 3,
				select: function( event, ui ) 
				{
					$( '#membermap_add_marker input[name="marker_lat"]').val( parseFloat( ui.item.latLng.lat ).toFixed(6) );
					$( '#membermap_add_marker input[name="marker_lon"]' ).val( parseFloat( ui.item.latLng.lng ).toFixed(6) );
				}
			});
		}
	});
}(jQuery, _));