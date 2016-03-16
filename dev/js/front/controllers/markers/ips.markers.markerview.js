;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.front.markers.markerview', {
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
			this.setupMap();
					
			var icon = L.AwesomeMarkers.icon({
				prefix: 'fa',
				icon: 'map-marker', 
				color: 'darkblue'
			});

			this.marker = new L.Marker(
				new L.LatLng( parseFloat( $(this.scope).attr('data-lat') ), parseFloat( $(this.scope).attr('data-lon') ) ),
				{
					icon: icon
				} 
			).bindPopup( '<h4>' + $(this.scope).attr('data-name') + '</h4>' ).addTo( this.map );
			

			this.marker.openPopup();
		
			this.map.flyTo( this.marker.getLatLng(), 8 );
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

		}
	});
}(jQuery, _));