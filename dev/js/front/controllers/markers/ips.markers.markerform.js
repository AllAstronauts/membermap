;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.front.markers.markerform', {
		map: null,
		popup: null,
		marker: null,
		infowindow: null,
		bounds: null,

		initialize: function () 
		{
			this.setupMap();
			this.setup();
			this.setupEvents();
		},

		setup: function()
		{
					
			var icon = L.AwesomeMarkers.icon({
				prefix: 'fa',
				icon: 'map-marker', 
				color: 'darkblue'
			});

			this.infowindow = new L.Popup();
			this.marker = new L.Marker(
				this.bounds.getCenter(),
				{
					draggable: true,
					opacity: 0.0,
					icon: icon
				} 
			).bindPopup( this.infowindow ).addTo( this.map );
			
			/* Show initial marker */
			if ( $( '#membermap_marker_form input[name="marker_lat"]' ).val() != 0 )
			{
				Debug.log( 'Setting initial marker' );
				Debug.log( 'Lat: ' + $( '#membermap_marker_form input[name="marker_lat"]' ).val() );
				Debug.log( 'Lng: ' + $( '#membermap_marker_form input[name="marker_lon"]' ).val() );
				
				this.marker.setLatLng( new L.LatLng( parseFloat( $( '#membermap_marker_form input[name="marker_lat"]' ).val() ), parseFloat( $( '#membermap_marker_form input[name="marker_lon"]' ).val() ) ) );
				this.marker.setOpacity( 1 );

				this.infowindow.setContent( '<h4>' + ( $( '#elInput_marker_title' ).val() || '' ) + '</h4>' + $( '#elInput_marker_location' ).val() );

				this.marker.openPopup();
			
				this.map.flyTo( this.marker.getLatLng(), 8 );

			}
			else
			{
				this.map.panTo( this.bounds.getCenter() );
				this.map.setZoom( this.map.getZoom() + 1 );
			}
		},

		setupEvents: function()
		{
			var that = this;
			
			this.marker.on( 'dragend', function( e ) 
			{
				var coords = e.target.getLatLng();
				that.findMarkerPosition( coords.lat, coords.lng );
			});
			
			this.map.on( 'click', function( e )
			{
				that.findMarkerPosition( e.latlng.lat, e.latlng.lng );
			});


			$( '#elInput_marker_location' ).autocomplete({
				minLength: 3,
				source: function( request, response ) 
				{
					Debug.log( request );
					ips.getAjax()({ 
						url: ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=ajax&do=mapquestSearch',
						type: 'get',
						dataType: 'json',
						data: 
						{
							q: request.term
						},
						success: function( data ) 
						{
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
					});
				},
				select: function( event, ui ) 
				{
					$( '#membermap_marker_form input[name="marker_lat"]').val( parseFloat( ui.item.latLng.lat ).toFixed(6) );
					$( '#membermap_marker_form input[name="marker_lon"]' ).val( parseFloat( ui.item.latLng.lng ).toFixed(6) );

					that.marker.setLatLng( new L.LatLng( parseFloat( $( '#membermap_marker_form input[name="marker_lat"]' ).val() ), parseFloat( $( '#membermap_marker_form input[name="marker_lon"]' ).val() ) ), 8 );

					that.map.flyTo( that.marker.getLatLng(), 8 );

					that.infowindow.setContent( '<h4>' + ( $( '#elInput_marker_title' ).val() || '' ) + '</h4>' + ui.item.value );
				}
			});
		},

		setupMap: function()
		{
			var southWest = new L.LatLng( 56.83, -7.14 );
			var northEast = new L.LatLng( 74.449, 37.466 );
			this.bounds = new L.LatLngBounds(southWest, northEast);

			var mapServices = {};
			var baseMaps = {};
			var defaultMaps = ips.getSetting( 'membermap_defaultMaps' );
			var defaultMap = '';

			$.each( defaultMaps.basemaps, function( id, name )
			{
				try 
				{
					var key = name.toLowerCase().replace( '.', '' );
					var prettyName = name.replace( '.', ' ' );

					baseMaps[ prettyName ] = mapServices[ key ] = L.tileLayer.provider( name );

					if ( defaultMap == '' )
					{
						defaultMap = key;
					}
				}
				catch(e)
				{
					Debug.log( e.message );
				}
			});

			this.map = L.map( 'mapCanvas', 
			{
				zoom: 7,
				layers: [ mapServices[ defaultMap ] ],
				attributionControl: true,
				crs: L.CRS.EPSG3857
			});

			this.map.fitBounds( this.bounds );

			L.control.layers( baseMaps ).addTo( this.map );

		},

		findMarkerPosition: function( lat, lng )
		{
			var that = this;

			$( '#membermap_marker_form input[name="marker_lat"]' ).val( parseFloat( lat ).toFixed( 6 ) );
			$( '#membermap_marker_form input[name="marker_lon"]' ).val( parseFloat( lng ).toFixed( 6 ) );

			this.marker.setLatLng( [ lat, lng ] );
			this.marker.setOpacity( 1 );
						
			ips.getAjax()({ 
				url: ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=ajax&do=mapquestReverseLookup', 
				type: 'get',
				dataType: 'json',
				data: 
				{
					lat: lat,
					lng: lng

				},
				success: function( data ) 
				{
					// MapQuest
					/* If adminArea5 is empty, it's likely we don't have a result */
					if ( data.results[0].locations[0].adminArea5 )
					{
						var item = data.results[0].locations[0];
						var location = ( item.adminArea5 ? item.adminArea5 : '' ) + 
									( item.adminArea4 ? ', ' + item.adminArea4 : '' ) + 
									( item.adminArea3 ? ', ' + item.adminArea3 : '' ) + 
									( item.adminArea2 ? ', ' + item.adminArea2 : '' ) +
									( item.adminArea1 ? ', ' + item.adminArea1 : '' );

						location = location.replace( /(^\s*,)|(,\s*$)/g, '' );

						$( '#elInput_marker_location' ).val( location );
						that.infowindow.setContent( '<h4>' + ( $( '#elInput_marker_title' ).val() || '' ) + '</h4>' + location );
					}
					else
					{
						$( '#elInput_marker_location' ).val( '' );
						that.infowindow.setContent( '<h4>' + $( '#elInput_marker_title' ).val() + '</h4>' );
					}
					
					that.marker.openPopup();
				}
			});
		}
	});
}(jQuery, _));