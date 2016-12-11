;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.settings', {

		initialize: function () 
		{
			$( '#elInput_membermap_bbox_location' ).autocomplete({
				source: function( request, response ) 
				{
					ips.getAjax()({ 
						url: 'https://open.mapquestapi.com/nominatim/v1/search.php',
						type: 'get',
						dataType: 'json',
						data: {
							key: ips.getSetting( 'membermap_mapquestAPI' ),
							format: 'json',
							q: request.term,
							extratags: 0,

						},
						success: function( data ) 
						{
							// MapQuest Nominatim
							response( $.map( data, function( item )
							{
								Debug.log( item );
								return {
									value: item.display_name,
									bbox: {
										minLat: parseFloat( item.boundingbox[0] ).toFixed( 6 ),
										maxLat: parseFloat( item.boundingbox[1] ).toFixed( 6 ),
										minLng: parseFloat( item.boundingbox[2] ).toFixed( 6 ),
										maxLng: parseFloat( item.boundingbox[3] ).toFixed( 6 )
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

			this.monitorColourPickers();
		},

		monitorColourPickers: function()
		{
			var self		= this;
			var icon 		= $( 'input[name="membermap_calendar_icon"]' ).eq(0);
			var iconColour 	= $( 'input[name="membermap_calendar_colour"]' ).eq(0);
			var bgColour 	= $( 'input[name="membermap_calendar_bgcolour"]' );


			bgColour.on('change', function()
			{
				var colour = $( 'input[name="membermap_calendar_bgcolour"]:checked' ).val();
				$( '#markerExample' ).removeClass().addClass( 'awesome-marker awesome-marker-icon-' + colour )
			});

			iconColour.on('change', function()
			{
				$('#markerExample i').css({ 'color': '#' + iconColour.val() });
			});

			icon.on('change', function()
			{
				$('#markerExample i').removeClass().addClass( 'fa fa-fw' ).addClass( icon.val() );
			});

		}
	});
}(jQuery, _));