;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.settings', {

		initialize: function () 
		{
			$( '#elInput_membermap_bbox_location' ).autocomplete({
				source: function( request, response ) 
				{
					ips.getAjax()({ 
						//url: 'http://www.mapquestapi.com/geocoding/v1/address', 
						url: 'https://open.mapquestapi.com/nominatim/v1/search.php',
						type: 'get',
						dataType: 'json',
						data: {
							key: ips.getSetting( 'membermap_mapquestAPI' ),

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

							// MapQuest Nominatim
							response( $.map( data, function( item )
							{
								Debug.log( item );
								return {
									value: item.display_name,
									bbox: {
										minLng: parseFloat( item.boundingbox[0] ).toFixed( 6 ),
										minLat: parseFloat( item.boundingbox[1] ).toFixed( 6 ),
										maxLng: parseFloat( item.boundingbox[2] ).toFixed( 6 ),
										maxLat: parseFloat( item.boundingbox[3] ).toFixed( 6 )
									}
								};
							}));

						}
					});
				},
				minLength: 3,
				select: function( event, ui ) 
				{
					$( '#membermap_form_settings input[name="membermap_bbox"]').val( JSON.stringify( ui.item.bbox ) );
				}
			});
		}
	});
}(jQuery, _));