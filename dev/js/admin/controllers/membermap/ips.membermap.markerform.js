;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.markerform', {
		map: null,
		popup: null,
		marker: null,
		infowindow: null,
		bounds: null,

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
						url: '//open.mapquestapi.com/nominatim/v1/search.php',
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
					});
				},
				minLength: 3,
				select: function( event, ui ) 
				{
					$( '#membermap_add_marker input[name="marker_lat"]').val( parseFloat( ui.item.latLng.lat ).toFixed(6) );
					$( '#membermap_add_marker input[name="marker_lon"]' ).val( parseFloat( ui.item.latLng.lng ).toFixed(6) );
				}
			});

			var that = this;

			/* Setup map popup */
			$( '#marker_addViaMap' ).click( function()
			{
				ips.loader.get( [ 'membermap/interface/leaflet/leaflet-src.js', 'membermap/interface/leaflet/plugins/leaflet-providers.js', 'membermap/interface/leaflet/plugins/leaflet.awesome-markers.js' ] ).then( function () 
				{
					if(  _.isObject( that.popup ) )
					{
						that.popup.remove( false );
					}

					that.popup = ips.ui.dialog.create({
						content: 	"<div><div id='mapWrapper' class='ipsPad'><div id='mapCanvas' style='height:400px;'></div></div><div id='geocodingError' style='display:none' class='message error'></div>"
								+ "<div class='ipsAreaBackground ipsPad ipsType_right'><span class='ipsButton ipsButton_primary' data-action='dialogClose'>Select</span></div>"
								+ "</div>",
						size: 'wide',
						title: ips.getString( 'marker_addViaMap' )
					});
					that.popup.show();

					that.setupMap();
							
					var icon = L.AwesomeMarkers.icon({
						prefix: 'fa',
						icon: 'map-marker', 
						color: 'darkblue'
					});

					that.infowindow = new L.Popup();
					that.marker = new L.Marker(
						that.bounds.getCenter(),
						{
							draggable: true,
							opacity: 0.0,
							icon: icon
						} 
					).bindPopup( that.infowindow ).addTo( that.map );
					
					/* Show initial marker */
					if ( $( '#membermap_add_marker input[name="marker_lat"]' ).val() != 0 )
					{
						Debug.log( 'Setting initial marker' );
						Debug.log( 'Lat: ' + $( '#membermap_add_marker input[name="marker_lat"]' ).val() );
						Debug.log( 'Lng: ' + $( '#membermap_add_marker input[name="marker_lon"]' ).val() );
						
						that.marker.setLatLng( new L.LatLng( parseFloat( $( '#membermap_add_marker input[name="marker_lat"]' ).val() ), parseFloat( $( '#membermap_add_marker input[name="marker_lon"]' ).val() ) ) );
						that.marker.setOpacity( 1 );

						that.infowindow.setContent( $( '#elInput_marker_name' ).val() + '<br />' + $( '#elInput_marker_location' ).val() );

						that.marker.openPopup();
					
						that.map.flyTo( that.marker.getLatLng(), 8 );

					}
					else
					{
						that.map.panTo( that.bounds.getCenter() );
						that.map.setZoom( that.map.getZoom() + 1 );
					}
					
					that.marker.on( 'dragend', function( e ) 
					{
						var coords = e.target.getLatLng();
						that.findMarkerPosition( coords.lat, coords.lng );
					});
					
					that.map.on( 'click', function( e )
					{
						that.findMarkerPosition( e.latlng.lat, e.latlng.lng );
					});
				});
				
			});
		},

		setupMap: function()
		{
			if (_.isObject( this.map ) )
			{
				this.map.remove();
			}

			var southWest = new L.LatLng( 56.83, -7.14 );
			var northEast = new L.LatLng( 74.449, 37.466 );
			this.bounds = new L.LatLngBounds(southWest, northEast);

			var mapServices = {};
			mapServices.esriworldstreetmap = L.tileLayer.provider( 'Esri.WorldStreetMap' );
			mapServices.thunderforestlandscape = L.tileLayer.provider( 'Thunderforest.Landscape' );
			mapServices.mapquest = L.tileLayer.provider('MapQuestOpen.OSM');			
			mapServices.esriworldtopomap = L.tileLayer.provider( 'Esri.WorldTopoMap' );

			this.map = L.map( 'mapCanvas', 
			{
				zoom: 7,
				layers: [ mapServices.mapquest ],
				attributionControl: true,
				crs: L.CRS.EPSG3857
			});

			this.map.fitBounds( this.bounds );


			
			var baseMaps = {
				"MapQuest": mapServices.mapquest,
				"Thunderforest Landscape": mapServices.thunderforestlandscape,
				'Esri WorldTopoMap': mapServices.esriworldtopomap,
				'Esri World Street Map': mapServices.esriworldstreetmap
			};

			L.control.layers( baseMaps ).addTo( this.map );

		},

		findMarkerPosition: function( lat, lng )
		{
			var that = this;

			ips.getAjax()({ 
				url: '//www.mapquestapi.com/geocoding/v1/reverse', 
				type: 'get',
				dataType: 'json',
				data: {
					key: ips.getSetting( 'membermap_mapquestAPI' ),
					lat: lat,
					lng: lng

				},
				success: function( data ) 
				{
					// MapQuest
					/* If adminArea5 is empty, it's likely we don't have a result */
					if ( data.results[0].locations[0].adminArea4 )
					{
						var item = data.results[0].locations[0];
						var location = ( item.adminArea5 ? item.adminArea5 : '' ) + 
									( item.adminArea4 ? ', ' + item.adminArea4 : '' ) + 
									( item.adminArea3 ? ', ' + item.adminArea3 : '' ) + 
									( item.adminArea2 ? ', ' + item.adminArea2 : '' ) +
									( item.adminArea1 ? ', ' + item.adminArea1 : '' );

						that.marker.setLatLng( [ lat, lng ] );
						that.marker.setOpacity( 1 );
						that.infowindow.setContent( location );

						that.marker.openPopup();
						
						$( '#elInput_marker_location' ).val( location );
						$( '#membermap_add_marker input[name="marker_lat"]' ).val( lat );
						$( '#membermap_add_marker input[name="marker_lon"]' ).val( lng );
						$( '#geocodingError' ).hide();
					}
					else
					{
						$( '#geocodingError' ).text( 'No results found' ).show();
					}

				}
			});
		}
	});
}(jQuery, _));