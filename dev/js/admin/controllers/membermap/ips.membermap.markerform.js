;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.markerform', {

		initialize: function () 
		{
			this.setup();
		},

		setup: function()
		{
			ips.loader.get( ['https://maps.google.com/maps/api/js?sensor=false&libraries=places'] ).then( function () {
				var geocoder = new google.maps.Geocoder();

				$( '#elInput_marker_location' ).keydown( function(event)
				{
					if( event.keyCode === 13 ) 
					{
						event.preventDefault();
						return false;
					}
				});

				var autocomplete = new google.maps.places.Autocomplete( document.getElementById( 'elInput_marker_location' ), { types: ['establishment', 'geocode'] } );
				
				google.maps.event.addListener( autocomplete, 'place_changed', function() 
				{
					var item = autocomplete.getPlace();
					
					$( '#membermap_add_marker input[name="marker_lat"]').val( parseFloat( item.geometry.location.lat() ).toFixed(6) );
					$( '#membermap_add_marker input[name="marker_lon"]' ).val( parseFloat( item.geometry.location.lng() ).toFixed(6) );
					$( '#elInput_marker_location' ).val( ( item.name || item.formatted_address ) );
				});
			});
		}
	});
}(jQuery, _));