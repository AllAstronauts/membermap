/**
 * Trip Report, by Martin Aronsen
 */
;( function($, _, undefined){
	"use strict";

	ips.createModule('ips.membermap', function() 
	{
		var map = null,
			oms = null,
			geocoder = null,
			defaultMapTypeId = null,
			activeLayers = null,
			
			zoomLevel = null,
			previousZoomLevel = null,
			
			initialCenter = null,
			
			mapServices = [],
			
			baseMaps = {},
			overlayMaps = {},
			
			mapMarkers = null,
			allMarkers = [],
			
			icons = [],
			infoWindow = null,
			info = null,
			currentPlace = null,
			isMobileDevice = false,
			isEmbedded = false,
			
			bounds = null,
			
			stuffSize = 0,
			popups = [],

			oldMarkersIndicator = null;
	
		var initMap = function()
		{
			/* Safari gets cranky if this is loaded after the map is set up */
			$( window ).on( 'scroll resize', function()
			{
				/* Attempting to scroll above viewport caused flickering */
				if ( window.scrollY < 0 )
				{
					return false;
				}
				
				setMapHeight();
				
				map.invalidateSize();
			});

			setMobileDevice( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) );


			var getByUser = ips.utils.url.getParam( 'filter' ) == 'getByUser' ? true : false;
			var getOnlineUsers = ips.utils.url.getParam( 'filter' ) == 'getOnlineUsers' ? true : false;

			if ( getByUser || getOnlineUsers )
			{
				if ( !!$( '#mapMarkers' ).html() )
				{
					try {
						var markersJSON = $.parseJSON( $( '#mapMarkers' ).html() );
						setMarkers( markersJSON );
					}
					catch(err) {}
				}
			}

			var centerLat = parseFloat( unescape( ips.utils.url.getParam( 'lat' ) ).replace( ',', '.' ) );
			var centerLng = parseFloat( unescape( ips.utils.url.getParam( 'lng' ) ).replace( ',', '.' ) );
			if ( centerLat && centerLng )
			{
				setCenter( centerLat, centerLng );
			}

			var initZoom = parseInt( ips.utils.url.getParam( 'zoom' ) );
			
			if ( initZoom )
			{
				setZoomLevel( initZoom );
			}

			var defaultMap = ips.utils.url.getParam( 'map' );
			if ( defaultMap )
			{
				setDefaultMap( defaultMap );
			}

			
			/* Set a height of the map that fits our browser height */
			setMapHeight();
			
			setupMap();
			

			/* Load all markers */
			loadMarkers();
		
			
			/* Init events */
			initEvents();	
		},
		
		setMobileDevice = function( bool )
		{
			isMobileDevice = bool;
		},
		
		setDefaultMap = function( map )
		{
			defaultMapTypeId = map;
		},
		
		setEmbed = function( bool )
		{
			isEmbedded = bool;
		},
		
		clear =function()
		{
			mapMarkers.clearLayers();
			oms.clearMarkers();
		},
		
		reloadMap = function()
		{
			Debug.log( "Reloading map" );
			clear();
			showMarkers( true );
		},

		setupMap = function(minimode)
		{
			minimode = typeof minimode !== 'undefined' ? minimode : false;

			
			var southWest = new L.LatLng( 56.83, -7.14 );
			var northEast = new L.LatLng( 74.449, 37.466 );
			bounds = new L.LatLngBounds(southWest, northEast);

			mapServices.thunderforestlandscape = L.tileLayer.provider( 'Thunderforest.Landscape' );
			mapServices.mapquest = L.tileLayer.provider('MapQuestOpen.OSM');			
			mapServices.esriworldtopomap = L.tileLayer.provider( 'Esri.WorldTopoMap' );
			mapServices.acetate = L.tileLayer.provider( 'Acetate' );
			
			
			var contextMenu = [];
			if ( ! minimode )
			{
				contextMenu.push(
				{
					text: 'Center map here',
					callback: function(e) 
					{
						map.flyTo(e.latlng);
					}
				}, 
				'-', 
				{
					text: 'Zoom in',
					icon: icons.zoomIn,
					callback: function() 
					{
						map.zoomIn();
					}
				}, 
				{
					text: 'Zoom out',
					icon: icons.zoomOut,
					callback: function() 
					{
						map.zoomOut();
					}
				});
			}
			

			var defaultMap = 'mapquest';
			var newDefault = '';
			
			if ( ips.utils.cookie.get( 'membermap_baseMap' ) !== '' )
			{
				newDefault = ips.utils.cookie.get( 'membermap_baseMap' );
			}
			
			if ( defaultMapTypeId !== null )
			{
				newDefault = defaultMapTypeId;
			}
			
			if ( newDefault !== '' )
			{
				if ( mapServices[ newDefault ] !== undefined )
				{
					defaultMap = newDefault;
				}
			}

			map = L.map( 'mapCanvas', 
			{
				zoom: ( zoomLevel || 7 ),
				layers: [ mapServices[ defaultMap ] ],
				contextmenu: ( minimode || isMobileDevice ? false : true ),
				contextmenuWidth: 180,
				contextmenuItems: contextMenu,
				fullscreenControl: minimode || isMobileDevice ? false : true,
				loadingControl: isMobileDevice ? false : true,
				attributionControl: true,
				crs: L.CRS.EPSG3857
			});
			
			
			if ( isMobileDevice === false ) 
			{
				L.control.scale().addTo(map);
			}

			map.fitBounds( bounds );
			
			if ( ! minimode )
			{
				oms = new OverlappingMarkerSpiderfier( map, { keepSpiderfied: true } );
				
				var popup = new L.Popup({
					offset: new L.Point(0, -20),
					keepInView: true,
					maxWidth: ( isMobileDevice ? 250 : 300 )
				});
				
				oms.addListener( 'click', function( marker ) 
				{
					var popupContent = 	'<h3><a href="' + marker.markerData.member_link + '" target="_blank">' + marker.markerData.name + '</h3>';
				
					
					popup.setContent( popupContent );
					popup.setLatLng( marker.getLatLng() );
					map.openPopup( popup );
				});
				
				oms.addListener('spiderfy', function( omsMarkers ) 
				{
					omsMarkers.each( function( omsMarker )
					{
						omsMarker.setIcon( omsMarker.options.spiderifiedIcon );
					});
					map.closePopup();
				});
				
				oms.addListener('unspiderfy', function(omsMarkers) 
				{
					omsMarkers.each( function( omsMarker )
					{
						omsMarker.setIcon( omsMarker.options.defaultIcon );
					});
				});
			}

			mapMarkers = new L.MarkerClusterGroup({ spiderfyOnMaxZoom: false, zoomToBoundsOnClick: false, disableClusteringAtZoom: ( $( '#mapWrapper' ).height() > 1000 ? 12 : 9 ) });
			
			mapMarkers.on( 'clusterclick', function (a) 
			{
				map.fitBounds( a.layer._bounds );
				if ( map.getZoom() > ( $( '#mapWrapper' ).height() > 1000 ? 12 : 9 ) )
				{
					map.setZoom( $( '#mapWrapper' ).height() > 1000 ? 12 : 9 );
				}
			});
			map.addLayer( mapMarkers );
			
			baseMaps = {
				"MapQuest": mapServices.mapquest,
				"Thunderforest Landscape": mapServices.thunderforestlandscape,
				'Esri WorldTopoMap': mapServices.esriworldtopomap,
				'Acetate': mapServices.acetate
			};

			overlayMaps = {
				"Members": mapMarkers,
			};

			activeLayers = new L.Control.ActiveLayers( baseMaps, overlayMaps, { collapsed: ( minimode || isMobileDevice || isEmbedded ? true : false ) } ).addTo( map );
			
			ips.membermap.map = map;
		},
		
		setMarkers = function( markers )
		{
			allMarkers = markers.markers;
		},

		reloadMarkers = function()
		{
			if ( oldMarkersIndicator !== null )
			{
				ips.membermap.map.removeControl( oldMarkersIndicator );
			}
			
			clear();

			loadMarkers( true );
		},
		
		loadMarkers = function( forceReload )
		{
			forceReload = typeof forceReload !== 'undefined' ? forceReload : false;

			/* Skip this if markers was loaded from DOM */
			if ( allMarkers && allMarkers.length > 0 )
			{
				showMarkers();
				return;
			}

			if ( forceReload || ! ( 'localStorage' in window && window['localStorage'] !== null ) )
			{
				allMarkers = [];

				$.ajax( ipsSettings.baseURL.replace('&amp;','&') + 'datastore/membermap_cache/membermap-index.json',
				{	
					cache : false,
					success: function( res )
					{
						if ( typeof res.error !== 'undefined' )
						{
							alert(res.error);
						}

						if ( res.fileList.length === 0 )
						{
							return false;
						}

						var promise;

						$.each( res.fileList, function( id, file )
						{
							promise = $.when( promise, 
								$.ajax({
									url: ipsSettings.baseURL.replace('&amp;','&') + '/datastore/' + file,
									cache : false,
									success:function( res )
									{
										/* Show marker layer */
										showMarkers( false, res );
										allMarkers = allMarkers.concat( res );
									}
								})
							);
						});

						/* Store data in browser when all AJAX calls complete */
						promise.done(function()
						{
							if ( 'localStorage' in window && window['localStorage'] !== null )
							{
								var date = new Date();
								window['localStorage'].setItem( 'membermapMarkers', JSON.stringify({ time: ( date.getTime() / 1000 ), data: allMarkers }) );
							}
						});
					}
				});
			}
			else
			{
				/* Get data from browser storage */
				var data = window['localStorage'].getItem( 'membermapMarkers' );
			
				if ( data === null )
				{
					reloadMarkers();
					return;
				}
				
				data = JSON.parse( data );

				if ( data.data.length > 0 && typeof data.data !== 'null' && typeof data.oldestMarker !== 'undefined' )
				{
					/* Reload cache if it's older than 24 hrs */
					var date = new Date( data.time * 1000 ),
					nowdate = new Date;
					if ( ( ( nowdate.getTime() - date.getTime() ) / 1000 ) > 86400 )
					{
						reloadMarkers();
						return;
					}

					allMarkers = data.data;
					showMarkers( false, data.data );
					
					/* Inform that we're showing markers from browser cache */
					if ( oldMarkersIndicator === null && ! isMobileDevice )
					{
						oldMarkersIndicator = new L.Control.MembermapOldMarkers({ callback: reloadMarkers, time: date });
						ips.membermap.map.addControl( oldMarkersIndicator );
				
					}
				}
				else
				{
					reloadMarkers();
				}
			}

		},
		
		initEvents = function()
		{
			/* And adjust it if we resize our browser */
			if ( isMobileDevice === false && isEmbedded === false )
			{
				$( "#mapWrapper" ).resizable(
				{
					zIndex: 15000,
					handles: 's',
					stop: function(event, ui) 
					{
						$(this).css("width", '');
					},
					resize: function( event, ui )
					{
						map.invalidateSize();
					}
				});
			}
			

			
			/* Get by member */
			$( '#elInput_membermap_memberName' ).on( 'tokenAdded tokenDeleted', function()
			{
				reloadMap();
			});

			$( '#membermap_button_addLocation' ).click( function()
			{
				if ( typeof popups['addLocationPopup'] === 'object' )
				{
					popups['addLocationPopup'].destruct();
					popups['addLocationPopup'].remove();
					delete popups['addLocationPopup'];
				}

				popups['addLocationPopup'] = ips.ui.dialog.create({
					title: ips.getString( 'membermap_location_title' ),
					url: ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=showmap&do=add',
					callback: function()
					{
						if( ! navigator.geolocation )
						{
							$( '#membermap_geolocation_wrapper' ).hide();
						}
						else
						{
							$( '#membermap_currentLocation' ).click( processGeolocation );
						}

						$( '#membermap_location' ).on( 'submit', function(e)
						{
							if ( $( '#membermap_location input[name="lat"]' ).val().length == 0 || $( '#membermap_location input[name="lng"]' ).val().length == 0 )
							{
								e.preventDefault();
								return false;
							}
						});
					}
				});

				popups['addLocationPopup'].show();
				Debug.log( popups );
			})
		},



		processGeolocation = function(e)
		{
			e.preventDefault();
			if(navigator.geolocation)
			{
				navigator.geolocation.getCurrentPosition( function( position )
				{
					$( '#membermap_location input[name="lat"]' ).val( position.coords.latitude );
					$( '#membermap_location input[name="lng"]' ).val( position.coords.longitude );

					$( '#membermap_location' ).submit();
				},
				function( error )
				{
					jQuery('currentLocation').hide();
				},
				{
					maximumAge: (1000 * 60 * 15),
					enableHighAccuracy: true
				});
			}
		},

		setZoomLevel = function( setZoomLevel )
		{
			zoomLevel = parseInt( setZoomLevel, 10 );
		},

		setCenter = function( setLat, setLng )
		{
			initialCenter = new L.LatLng( parseFloat( setLat ), parseFloat( setLng ) );
		},
		
		setMapHeight = function()
		{
			if ( stuffSize === 0 )
			{
				stuffSize = $( '#membermapWrapper' ).offset().top;
			}
			
			var browserHeight = $( window ).height();
			
			var scrollY = ( window.pageYOffset !== undefined ) ? window.pageYOffset : (document.documentElement || document.body.parentNode || document.body).scrollTop; /* DIE IE */
			var leftForMe;
			
			if ( scrollY > stuffSize )
			{
				leftForMe = $( window ).height();
			}
			else
			{
				leftForMe = browserHeight - stuffSize + scrollY;
			}
			if ( $( '#mapWrapper' ).height() !== leftForMe )
			{
				$( '#mapWrapper' ).css( { height: leftForMe } );
				
				return true;
			}
			
			return false;
		},
	
		showMarkers = function( dontRepan, markers )
		{
			dontRepan = typeof dontRepan !== 'undefined' ? dontRepan : false;
			markers = typeof markers !== 'undefined' ? markers : false;

			if ( markers === false )
			{
				markers = allMarkers;
			}

			if ( markers.length === 0 )
			{
				return false;
			}

			var memberSearch = $( '#elInput_membermap_memberName_wrapper .cToken' ).eq(0).attr( 'data-value' );

			$.each( markers, function() 
			{		
				/* Report written by selected member? */
				if ( typeof memberSearch !== 'undefined' )
				{
					/* Names of 'null' are deleted members */
					if (this.name == null || memberSearch.toLowerCase() !== this.name.toLowerCase() )
					{
						return;
					}
				}
				

				var icon = L.AwesomeMarkers.icon({
					icon: 'male', 
					color: 'darkblue'
				});

				var spiderifiedIcon = L.AwesomeMarkers.icon({
					icon: 'male', 
					color: 'blue'
				});
				
				var mapMarker = new L.Marker( 
					[ this.lat, this.lon ], 
					{ 
						title: this.title,
						icon: icon,
						spin: true,
						spiderifiedIcon: spiderifiedIcon,
						defaultIcon: icon,
						contextmenu: false,
					}
				);
				
				mapMarker.markerData = this;

				oms.addMarker( mapMarker );
				mapMarkers.addLayer( mapMarker );
			});
			

			
			if ( dontRepan === false )
			{
				if ( initialCenter instanceof L.LatLng )
				{
					if ( zoomLevel )
					{
						map.flyTo( initialCenter, zoomLevel, { duration: 1.4 } );
					}
					else
					{
						map.flyTo( initialCenter );
					}
				}
				else
				{
					map.flyTo( bounds.getCenter() );
					
					map.flyToBounds( bounds );
				}
			}
		},
		
		markerClickFunction = function( marker )
		{
			var hidingMarker = currentPlace;
			
	
			var zoomIn = function( info ) 
			{
				previousZoomLevel = map.getZoom();
				
				//map.setCenter( marker.getLatLng() );
				map.flyTo( marker.getLatLng() );
				if ( map.getZoom() <= 11 )
				{
					map.setZoom( 11 );
				}
				
			};
			
			if ( currentPlace ) 
			{
				if ( hidingMarker !== marker ) 
				{
					zoomIn( marker.markerData );
				}
				else
				{
					currentPlace = null;
					map.setZoom( previousZoomLevel );
				}
			} 
			else 
			{
				zoomIn( marker.markerData );
			}
			
			currentPlace = marker;
		};

		return {
			initMap: initMap,
			setDefaultMap: setDefaultMap,
			setMarkers: setMarkers,
			setCenter: setCenter,
			setZoomLevel: setZoomLevel,
			map: map,
			loadMarkers: loadMarkers
		};
	});
}(jQuery, _));


L.Control.MembermapOldMarkers = L.Control.extend({
    options: {
        position: 'topleft',
        time: null,
        callback: null
    },
    initialize: function( options )
    {
    	L.setOptions(this, options);
    }, 
    onAdd: function (map) {
        // create the control container with a particular class name
        var container = L.DomUtil.create('div', 'leaflet-control-layers leaflet-control-layers-expanded leaflet-control-regobs-warning');
		//container.setOpacity( 1 );
        /* Date */
        var date = this.options.time.toLocaleString();
		var info = L.DomUtil.create('p', '', container);
		info.innerHTML = 'Showing cached markers<br /> from ' + date;
		
        var link = L.DomUtil.create('a', 'test', container);
		link.innerHTML = 'Refresh';
		link.href = '#';
		link.title = 'Tittel';
		
		L.DomEvent
		    .on(link, 'click', L.DomEvent.preventDefault)
		    .on(link, 'click', this.options.callback );
        // ... initialize other DOM elements, add listeners, etc.

        return container;
    }
});