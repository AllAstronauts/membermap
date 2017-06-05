;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.settings', {

		initialize: function () 
		{
			$( '#elInput_membermap_bbox_location' ).autocomplete({
				source: function( request, response ) 
				{
					ips.getAjax()({ 
						url: '?app=membermap&module=membermap&controller=settings&do=mapquestSearch',
						type: 'get',
						dataType: 'json',
						data: {
							format: 'json',
							q: request.term,
							extratags: 0,

						},
						success: function( data ) 
						{
							// MapQuest Nominatim
							response( $.map( data, function( item )
							{
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

			$( '.markerExample' ).each( function() 
			{
				var elem = $( this );
				var idPrefix = elem.attr( 'data-prefix' );

				var icon 		= $( 'input[name="' + idPrefix + '_icon"]' ).eq(0);
				var iconColour 	= $( 'input[name="' + idPrefix + '_colour"]' ).eq(0);
				var bgColour 	= $( 'input[name="' + idPrefix + '_bgcolour"]' );


				bgColour.on('change', function()
				{
					var colour = $( 'input[name="' + idPrefix + '_bgcolour"]:checked' ).val();
					elem.removeClass().addClass( 'awesome-marker awesome-marker-icon-' + colour )
				});

				iconColour.on('change', function()
				{
					Debug.log( elem.children( 'i' ) );
					elem.children( 'i' ).css({ 'color': '#' + iconColour.val() });
				});

				icon.on('change', function()
				{
					elem.children( 'i' ).removeClass().addClass( 'fa fa-fw' ).addClass( icon.val() );
				});
			});
		}
	});
}(jQuery, _));