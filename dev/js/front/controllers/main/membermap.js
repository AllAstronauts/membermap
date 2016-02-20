/**
 * Member Map, by Stuart Silvester & Martin Aronsen
 */
;( function($, _, undefined){
	"use strict";

	ips.createModule('ips.membermap', function() 
	{
		var map = null,
			defaultMapTypeId = null,
			
			zoomLevel = null,
			
			initialCenter = null,
			
			mapServices = [],
			
			baseMaps = {},
			overlayMaps = {},
			overlayControl = null,
			
			memberMarkers = null,
			allMarkers = [],
			
			icons = [],
			isMobileDevice = false,
			isEmbedded = false,
			
			bounds = null,
			forceBounds = false,
			
			stuffSize = 0,
			popups = [],

			oldMarkersIndicator = null,

			hasLocation = false;
	
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


			/* Showing a single user or online user, get the markers from DOM */
			var getByUser = ips.utils.url.getParam( 'filter' ) == 'getByUser' ? true : false;
			var getOnlineUsers = ips.utils.url.getParam( 'filter' ) == 'getOnlineUsers' ? true : false;

			if ( getByUser || getOnlineUsers )
			{
				if ( !!$( '#mapMarkers' ).html() )
				{
					try {
						var markersJSON = $.parseJSON( $( '#mapMarkers' ).html() );
						if ( markersJSON.length > 0 )
						{
							setMarkers( markersJSON );
						}
						else
						{
							ips.ui.flashMsg.show( ips.getString( 'membermap_no_results' ), { timeout: 3, position: 'bottom' } );
						}
					}
					catch(err) {}
				}
			}

			/* Set lat/lon from URL */
			var centerLat = parseFloat( unescape( ips.utils.url.getParam( 'lat' ) ).replace( ',', '.' ) );
			var centerLng = parseFloat( unescape( ips.utils.url.getParam( 'lng' ) ).replace( ',', '.' ) );
			if ( centerLat && centerLng )
			{
				setCenter( centerLat, centerLng );
			}

			/* Set zoom level from URL */
			var initZoom = parseInt( ips.utils.url.getParam( 'zoom' ) );
			
			if ( initZoom )
			{
				setZoomLevel( initZoom );
			}

			/* Set default map from URL */
			var defaultMap = ips.utils.url.getParam( 'map' );
			if ( defaultMap )
			{
				setDefaultMap( defaultMap );
			}

			/* Are we embedding? */
			setEmbed( ips.utils.url.getParam( 'do' ) == 'embed' ? 1 : 0 );

			
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
			memberMarkers.clearLayers();
		},
		
		reloadMap = function()
		{
			clear();
			showMarkers( true );
		},

		setupMap = function()
		{
			var bbox = ips.getSetting( 'membermap_bbox' );

			if ( bbox.minLat && bbox.minLng && bbox.maxLat && bbox.maxLng )
			{
				var southWest = new L.LatLng( bbox.minLat, bbox.minLng );
				var northEast = new L.LatLng( bbox.maxLat, bbox.maxLng );

				forceBounds = true;
			}
			else
			{
				var southWest = new L.LatLng( 56.83, -7.14 );
				var northEast = new L.LatLng( 74.449, 37.466 );
			}
			bounds = new L.LatLngBounds(southWest, northEast);

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

			var contextMenu = [];

			contextMenu.push(
			{
				text: ips.getString( 'membermap_centerMap' ),
				callback: function(e) 
				{
					map.flyTo(e.latlng);
				}
			}, 
			'-', 
			{
				text: ips.getString( 'membermap_zoomIn' ),
				icon: icons.zoomIn,
				callback: function() 
				{
					map.zoomIn();
				}
			}, 
			{
				text: ips.getString( 'membermap_zoomOut' ),
				icon: icons.zoomOut,
				callback: function() 
				{
					map.zoomOut();
				}
			});
			

			var newDefault = '';
			
			if ( typeof ips.utils.cookie.get( 'membermap_baseMap' ) == 'string' && ips.utils.cookie.get( 'membermap_baseMap' ).length > 0 )
			{
				newDefault = ips.utils.cookie.get( 'membermap_baseMap' ).toLowerCase();
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
				contextmenu: ( isMobileDevice ? false : true ),
				contextmenuWidth: 180,
				contextmenuItems: contextMenu,
				fullscreenControl: isMobileDevice ? false : true,
				loadingControl: isMobileDevice ? false : true,
				attributionControl: true,
				crs: L.CRS.EPSG3857
			});
			
			if ( isMobileDevice === false ) 
			{
				L.control.scale().addTo(map);
			}

			map.fitBounds( bounds, { maxZoom: ( zoomLevel || 7 ) } );
			
			if ( ips.getSetting( 'membermap_enable_clustering' ) == 1 )
			{
				memberMarkers = new L.MarkerClusterGroup({ zoomToBoundsOnClick: true, disableClusteringAtZoom: ( $( '#mapWrapper' ).height() > 1000 ? 12 : 9 ) });
			}
			else
			{
				memberMarkers = new L.FeatureGroup();
			}

			map.addLayer( memberMarkers );
			

			overlayMaps[ ips.getString( 'membermap_overlay_members' ) ] = memberMarkers;

			$.each( defaultMaps.overlays, function( id, name )
			{
				try 
				{
					var prettyName = name.replace( '.', ' ' );

					overlayMaps[ prettyName ] = L.tileLayer.provider( name );
				}
				catch(e)
				{
					Debug.log( e.message );
				}
			});

			overlayControl = L.control.layers( baseMaps, overlayMaps, { collapsed: ( isMobileDevice || isEmbedded ? true : false ) } ).addTo( map );

			map.on( 'baselayerchange', function( baselayer )
			{
				ips.utils.cookie.set( 'membermap_baseMap', baselayer.name.toLowerCase().replace( /\s/g, '' ) );
			});
			
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

			if ( ips.utils.url.getParam( 'rebuildCache' ) == 1 || ips.utils.url.getParam( 'dropBrowserCache' ) == 1 )
			{
				forceReload = true;
			}

			/* Skip this if markers was loaded from DOM */
			if ( allMarkers && allMarkers.length > 0 )
			{
				showMarkers();
				return;
			}

			if ( ! ips.utils.db.isEnabled() )
			{
				$( '#elToolsMenuBrowserCache' ).addClass( 'ipsMenu_itemDisabled' );
				$( '#elToolsMenuBrowserCache a' ).append( '(Not supported)' );
			}

			if ( forceReload || ! ips.utils.db.isEnabled() )
			{
				allMarkers = [];

				$.ajax( ipsSettings.baseURL.replace('&amp;','&') + 'datastore/membermap_cache/membermap-index.json',
				{	
					cache : false,
					dataType: 'json',
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
									dataType: 'json',
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
							if ( ips.utils.db.isEnabled() )
							{
								var date = new Date();
								ips.utils.db.set( 'membermap', 'markers', { time: ( date.getTime() / 1000 ), data: allMarkers } );
								ips.utils.db.set( 'membermap', 'cacheTime', ips.getSetting( 'membermap_cacheTime' ) );


								$( '#elToolsMenuBrowserCache a time' ).html( '(Last update: ' + ips.utils.time.readable( date.getTime() / 1000 ) + ')' );
							}
						});
					}
				});
			}
			else
			{
				/* Get data from browser storage */
				var data 		= ips.utils.db.get('membermap', 'markers' );
				var cacheTime 	= ips.utils.db.get('membermap', 'cacheTime' );
			
				if ( data === null || cacheTime < ips.getSetting( 'membermap_cacheTime' ) )
				{
					reloadMarkers();
					return;
				}

				if ( data.data.length > 0 && typeof data.data !== null )
				{
					/* Reload cache if it's older than 24 hrs */
					var date = new Date( data.time * 1000 ),
					nowdate = new Date();
					if ( ( ( nowdate.getTime() - date.getTime() ) / 1000 ) > 86400 )
					{
						reloadMarkers();
						return;
					}

					allMarkers = data.data;
					showMarkers( false, data.data );
					
					/* Inform that we're showing markers from browser cache */
					if ( oldMarkersIndicator === null && ! isEmbedded )
					{
						oldMarkersIndicator = new L.Control.MembermapOldMarkers({ callback: reloadMarkers, time: date });
						ips.membermap.map.addControl( oldMarkersIndicator );
					}

					$( '#elToolsMenuBrowserCache a time' ).html( '(Last update: ' + ips.utils.time.readable( date / 1000 ) + ')' );
				}
				else
				{
					reloadMarkers();
					return;
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

			$( '#membermap_button_addLocation, #membermap_button_editLocation' ).click( function()
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

						$( '#elInput_membermap_location' ).autocomplete({
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
							select: function( event, ui ) {
								$( '#membermap_form_location input[name="lat"]' ).val( parseFloat( ui.item.latLng.lat).toFixed(6) );
								$( '#membermap_form_location input[name="lng"]' ).val( parseFloat( ui.item.latLng.lng).toFixed(6) );
							}
						});

						$( '#membermap_form_location' ).on( 'submit', function(e)
						{
							if ( $( '#membermap_form_location input[name="lat"]' ).val().length === 0 || $( '#membermap_form_location input[name="lng"]' ).val().length === 0 )
							{
								e.preventDefault();
								return false;
							}
						});
					}
				});

				popups['addLocationPopup'].show();
			});
		},



		processGeolocation = function(e)
		{
			e.preventDefault();
			if(navigator.geolocation)
			{
				navigator.geolocation.getCurrentPosition( function( position )
				{
					$( '#membermap_form_location input[name="lat"]' ).val( position.coords.latitude );
					$( '#membermap_form_location input[name="lng"]' ).val( position.coords.longitude );

					$( '#membermap_form_location' ).submit();
					return;

					/* Skip this for now, will have to see how many requests this app consumes per month */

					ips.getAjax()({ 
						url: '//www.mapquestapi.com/geocoding/v1/reverse', 
						type: 'get',
						dataType: 'json',
						data: {
							key: "pEPBzF67CQ8ExmSbV9K6th4rAiEc3wud",
							lat: position.coords.latitude,
							lng: position.coords.longitude

						},
						success: function( data ) 
						{
							// MapQuest
							/* If adminArea5 is empty, it's likely we don't have a result */
							if ( data.results[0].locations[0].adminArea5 )
							{
								var item = data.results[0].locations[0];
								var location = item.adminArea5 + 
											( item.adminArea4 ? ', ' + item.adminArea4 : '' ) + 
											( item.adminArea3 ? ', ' + item.adminArea3 : '' ) + 
											( item.adminArea2 ? ', ' + item.adminArea2 : '' ) +
											( item.adminArea1 ? ', ' + item.adminArea1 : '' );

								$( '#elInput_membermap_location' ).val( location );

								$( '#membermap_form_location' ).submit();
									
							}
							else
							{
								$( '#membermap_geolocation_wrapper' ).hide();
								$( '#membermap_addLocation_error' ).html( ips.getString( 'memebermap_geolocation_error' ) ).show();
							}

						}
					});

				},
				function( error )
				{
					$( '#membermap_addLocation_error' ).append( 'ERROR(' + error.code + '): ' + error.message ).append( '<br />' + ips.getString( 'memebermap_geolocation_error' ) ).show();
					$( '#membermap_geolocation_wrapper' ).hide();
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
			dontRepan = typeof dontRepan !== undefined ? dontRepan : false;
			markers = typeof markers !== undefined ? markers : false;

			var getByUser 	= ips.utils.url.getParam( 'filter' ) == 'getByUser' ? true : false;
			var memberId 	= parseInt( ips.utils.url.getParam( 'member_id' ) );
			var flyToZoom 	= 8;

			if ( forceBounds )
			{
				dontRepan = true;
			}

			if ( markers === false )
			{
				markers = allMarkers;
			}

			if ( markers.length > 0 )
			{
				var memberSearch = $( '#elInput_membermap_memberName_wrapper .cToken' ).eq(0).attr( 'data-value' );

				var counter = 0;

				$.each( markers, function() 
				{		
					/* Don't show these, as they all end up in the middle of the middle of the South Atlantic Ocean. */
					if ( this.lat === 0 && this.lon === 0 )
					{
						return;
					}

					/* Report written by selected member? */
					if ( typeof memberSearch !== 'undefined' )
					{
						/* Names of 'null' are deleted members */
						if (this.name === null || memberSearch.toLowerCase() !== this.name.toLowerCase() )
						{
							return;
						}
					}
					
					var bgColour 	= 'darkblue';
					var icon 		= 'user';
					var iconColour 	= 'white';

					if ( this.type == 'member' )
					{
						if ( this.member_id == ips.getSetting( 'member_id' ) )
						{
							/* This is me! */
							icon = 'home';
							bgColour = 'green';

							$( '#membermap_addLocation_wrapper' ).hide();
							$( '#membermap_myLocation_wrapper' ).show();

							/* Update the button label while we're here */
							if ( ! ips.getSetting( 'membermap_canEdit' ) )
							{
								$( 'li#membermap_button_addLocation' ).addClass( 'ipsMenu_itemDisabled' );
								$( 'li#membermap_button_addLocation' ).attr( 'data-ipsTooltip', '' ).attr( 'title', ips.getString( 'membermap_cannot_edit_location' ) );
							}

							hasLocation = true;

							if ( ips.utils.url.getParam( 'goHome' ) == 1 )
							{
								getByUser 	= true;
								memberId 	= this.member_id;
								flyToZoom 	= 10;
							}
							
						}
						else
						{
							if ( this.markerColour )
							{
								bgColour = this.markerColour;
							}
						}
					}
					else
					{
						iconColour 	= this.colour;
						icon 		= this.icon || 'fa-map-marker';
						bgColour 	= this.bgColour;

					}

					var _icon = L.AwesomeMarkers.icon({
						prefix: 'fa',
						icon: icon, 
						markerColor: bgColour,
						iconColor: iconColour
					});
					

					var contextMenu = [];
					var enableContextMenu = false;

					if ( this.type == 'member' && ( ips.getSetting( 'is_supmod' ) ||  ( ips.getSetting( 'member_id' ) == this.member_id && ips.getSetting( 'membermap_canDelete' ) ) ) )
					{
						enableContextMenu = true;
						contextMenu = getMarkerContextMenu( this );
					}
					
					var mapMarker = new L.Marker( 
						[ this.lat, this.lon ], 
						{ 
							title: this.title,
							icon: _icon,
							contextmenu: enableContextMenu,
						    contextmenuItems: contextMenu
						}
					).bindPopup( this.popup );
					
					mapMarker.markerData = this;

					if ( this.type == 'member' )
					{
						memberMarkers.addLayer( mapMarker );
					}
					else
					{
						if( typeof overlayMaps[ this.parent_id ] !== undefined )
						{
							overlayMaps[ this.parent_id ] = L.layerGroup().addTo( map );
							overlayControl.addOverlay( overlayMaps[ this.parent_id ], this.parent_name );
						}
						
						overlayMaps[ this.parent_id ].addLayer( mapMarker );
					}

					/* Count the number of markers we have on the map */
					counter = counter + 1;

					/* Pan directly to our home location, if that's what we wanted */
					if ( getByUser && memberId > 0 && this.type == 'member' && this.member_id == memberId )
					{
						dontRepan = true;
						map.flyTo( mapMarker.getLatLng(), flyToZoom );
					}
				});

				/* Update the counter */
				$( '#membermap_counter span' ).html( counter );
			}


			/* Contextual menu */
			/* Needs to run this after the markers, as we need to know if we're editing or adding the location */
			if ( ips.getSetting( 'member_id' ) )
			{
				if ( hasLocation && ips.getSetting( 'membermap_canEdit' ) )
				{
					map.contextmenu.insertItem(
					{
						'text': ips.getString( 'membermap_context_editLocation' ),
						callback: updateLocation
					}, 0 );
					map.contextmenu.insertItem( { separator: true }, 1 );
				}
				else if ( ! hasLocation && ips.getSetting( 'membermap_canAdd' ) )
				{
					map.contextmenu.insertItem(
					{
						'text': ips.getString( 'membermap_context_addLocation' ),
						callback: updateLocation
					}, 0 );
					map.contextmenu.insertItem( { separator: true }, 1 );
				}
			}


			/* We don't want to move the map around if we're changing filters or reloading markers */
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
					map.fitBounds( memberMarkers.getBounds(), { 
						padding: [50, 50],
						maxZoom: 11
					});
				}
			}
		},

		updateLocation = function( e )
		{
			ips.ui.alert.show({
				type: 'confirm',
				message: ips.getString( 'membermap_confirm_updateLocation' ),
				callbacks:
				{
					'ok': function() 
					{ 
						var url = ips.getSetting('baseURL') + "index.php?app=membermap&module=membermap&controller=showmap&do=add&csrfKey=" + ips.getSetting( 'csrfKey' );
						ips.getAjax()({ 
							url: url,
							data: {
								lat: e.latlng.lat,
								lng: e.latlng.lng,
								'membermap_form_location_submitted': 1
							},
							type: 'POST'
						}).done( function( data )
						{
							if ( data['error'] )
							{
								ips.ui.alert.show({ type: 'alert', message: data['error'] });
							}
							else
							{
								window.location.replace( ips.getSetting('baseURL') + "index.php?app=membermap&dropBrowserCache=1&goHome=1" );
							}
						}); 
					}
				}
			});
		},


		getMarkerContextMenu = function( marker, markerData )
		{
			
			if ( ips.getSetting( 'is_supmod' ) ||  ( ips.getSetting( 'member_id' ) == marker.member_id && ips.getSetting( 'membermap_canDelete' ) ) ) 
			{
				return [{
					'text': 'Delete',
					index: 0,
					callback: function(e)
					{
						ips.ui.alert.show({
							type: 'confirm',
							callbacks:
							{
								'ok': function() 
								{ 
									var url = ips.getSetting('baseURL') + "index.php?app=membermap&module=membermap&controller=showmap&do=delete&member_id="+ marker.member_id;
									ips.getAjax()({ 
										url: url, 
										type: 'GET'
									}).done( function( data )
									{
										if ( data['error'] )
										{
											ips.ui.alert.show({ type: 'alert', message: data['error'] });
										}
										else
										{
											window.location.replace( ips.getSetting('baseURL') + "index.php?app=membermap&dropBrowserCache=1" );
										}
									}); 
								}
							}
						});
					}
				},
				{
					separator: true,
					index: 1
				}];
			}

			return [];
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
        var container = L.DomUtil.create('div', 'leaflet-control-layers leaflet-control-layers-expanded leaflet-control-cached-warning');
		//container.setOpacity( 1 );
        /* Date */
		var info = L.DomUtil.create('p', '', container);
		info.innerHTML = ips.getString( 'membermap_cached_markers', {date: ips.utils.time.localeDateString( this.options.time, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric' } )} );
		
        var link = L.DomUtil.create('a', 'test', container);
		link.innerHTML = ips.getString( 'membermap_cached_markers_refresh' );
		link.href = '#';
		
		L.DomEvent
		    .on(link, 'click', L.DomEvent.preventDefault)
		    .on(link, 'click', this.options.callback );
        // ... initialize other DOM elements, add listeners, etc.

        return container;
    }
});