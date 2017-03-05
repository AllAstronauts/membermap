/**
 * Member Map, by Stuart Silvester & Martin Aronsen
 */
;( function($, _, undefined){
	"use strict";

	ips.createModule('ips.membermap', function() 
	{
		var map = null,
			defaultMaps = {},
			
			zoomLevel = null,
			
			initialCenter = null,
			
			mapServices = [],
			
			baseMaps = {},
			overlayMaps = {},
			overlayControl = null,
			
			mastergroup = null,
			allMarkers = [],
			
			icons = [],
			isMobileDevice = false,
			isEmbedded = false,
			
			bounds = null,
			forceBounds = false,
			
			stuffSize = 0,
			popups = [],

			oldMarkersIndicator = null,

			counter = 0,

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


			defaultMaps = ips.getSetting( 'membermap_defaultMaps' );


			/* Showing a single user or online user, get the markers from DOM */
			var getByUser = ips.utils.url.getParam( 'filter' ) == 'getByUser' ? true : false;
			var getOnlineUsers = ips.utils.url.getParam( 'filter' ) == 'getOnlineUsers' ? true : false;

			if ( getByUser || getOnlineUsers )
			{
				if ( !!$( '#mapMarkers' ).attr( 'data-markers' ) )
				{
					try {
						var markersJSON = $.parseJSON( $( '#mapMarkers' ).attr( 'data-markers' ) );
						if ( markersJSON.length > 0 )
						{
							setMarkers( markersJSON );
						}
						else
						{
							ips.ui.flashMsg.show( ips.getString( 'membermap_no_results' ), { timeout: 3, position: 'bottom' } );
						}
					}
					catch(err) {
						Debug.log( err );
					}
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

			/* Are we embedding? */
			setEmbed( ips.utils.url.getParam( 'do' ) == 'embed' ? 1 : 0 );
		
			/* Set a height of the map that fits our browser height */
			setMapHeight();
			
			/* Prep the map */
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
		
		setEmbed = function( bool )
		{
			isEmbedded = bool;
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
		
		clear =function()
		{
			mastergroup.clearLayers();
		},
		
		setMarkers = function( markers )
		{
			allMarkers = markers;
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

		setupMap = function()
		{
			/* Bounding Box */
			var bbox = ips.getSetting( 'membermap_bbox' );

			if ( bbox !== null && bbox.minLat && bbox.minLng && bbox.maxLat && bbox.maxLng )
			{
				var southWest = new L.LatLng( bbox.minLat, bbox.minLng );
				var northEast = new L.LatLng( bbox.maxLat, bbox.maxLng );

				forceBounds = true;

				if ( ips.getSetting( 'membermap_bbox_zoom' ) )
				{
					setZoomLevel( ips.getSetting( 'membermap_bbox_zoom' ) );
				}
			}
			else
			{
				/* Default bounding box */
				var southWest = new L.LatLng( 56.83, -7.14 );
				var northEast = new L.LatLng( 74.449, 37.466 );
			}
			bounds = new L.LatLngBounds(southWest, northEast);

			var defaultMap = '';

			$.each( defaultMaps.basemaps, function( id, name )
			{
				try 
				{
					var key = name.toLowerCase().replace( '.', '' );
					var prettyName = name.replace( '.', ' ' );

					baseMaps[ prettyName ] = mapServices[ key ] = L.tileLayer.provider( name );

					if ( defaultMap === '' )
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
				mastergroup = L.markerClusterGroup({ zoomToBoundsOnClick: true /*, disableClusteringAtZoom: ( $( '#mapWrapper' ).height() > 1000 ? 13 : 11 )*/ });
			}
			else
			{
				mastergroup = L.featureGroup();
			}

			map.addLayer( mastergroup );
			


			overlayControl = L.control.layers( baseMaps, overlayMaps, { collapsed: ( isMobileDevice || isEmbedded ? true : false ) } ).addTo( map );

			map.on( 'baselayerchange', function( baselayer )
			{
				ips.utils.cookie.set( 'membermap_baseMap', baselayer.name.toLowerCase().replace( /\s/g, '' ) );
			});

			/* Truncate popup content */
			map.on( 'popupopen', function( popup ) 
			{
				ips.ui.truncate.respond( $( '.membermap_popupContent' ), { type: 'hide', size: '3 lines' } );
			});
			
			ips.membermap.map = map;
		},
		
		loadMarkers = function( forceReload )
		{
			function loadNextFile( id )
			{
				ips.getAjax()({
					url: '?app=membermap&module=membermap&controller=showmap&do=getCache&id=' + id,
					cache : false,
					async: true,
					dataType: 'json',
					success:function( res )
					{
						if( res.error )
						{
							finished();
							return;
						}

						/* Show marker layer */
						showMarkers( false, res.markers );
						allMarkers = allMarkers.concat( res.markers );

						loadNextFile( ++id );
					},
					error:function (xhr, ajaxOptions, thrownError)
					{
						if(xhr.status == 404) 
						{
							finished();
						}
					}
				});
			};

			/* Store data in browser when all AJAX calls complete */
			function finished()
			{
				updateOverlays();

				if ( dbEnabled )
				{
					var date = new Date();
					ips.utils.db.set( 'membermap', 'markers', { time: ( date.getTime() / 1000 ), data: allMarkers } );
					ips.utils.db.set( 'membermap', 'cacheTime', ips.getSetting( 'membermap_cacheTime' ) );


					$( '#elToolsMenuBrowserCache a time' ).html( '(' + ips.getString( 'membermap_browserCache_update' ) + ': ' + ips.utils.time.readable( date.getTime() / 1000 ) + ')' );
				}
			};

			forceReload = typeof forceReload !== 'undefined' ? forceReload : false;

			if ( ips.utils.url.getParam( 'rebuildCache' ) == 1 || ips.utils.url.getParam( 'dropBrowserCache' ) == 1 )
			{
				forceReload = true;
				removeURIParam( 'rebuildCache' );
				removeURIParam( 'dropBrowserCache' );
			}

			/* Skip this if markers was loaded from DOM */
			if ( allMarkers && allMarkers.length > 0 )
			{
				showMarkers();
				return;
			}

			var dbEnabled = ips.utils.db.isEnabled();

			if ( ! dbEnabled )
			{
				$( '#elToolsMenuBrowserCache' ).addClass( 'ipsMenu_itemDisabled' );
				$( '#elToolsMenuBrowserCache a' ).append( '(Not supported by your browser)' );
			}

			if ( forceReload || ! dbEnabled )
			{
				allMarkers = [];

				var startId = 0;

				loadNextFile( startId );
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
					updateOverlays();
					
					/* Inform that we're showing markers from browser cache */
					if ( oldMarkersIndicator === null && ! isEmbedded )
					{
						oldMarkersIndicator = new L.Control.MembermapOldMarkers({ callback: function() { window.location.href = ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=showmap&dropBrowserCache=1'; }, time: date });
						ips.membermap.map.addControl( oldMarkersIndicator );
					}

					$( '#elToolsMenuBrowserCache a time' ).html( '(' + ips.getString( 'membermap_browserCache_update' ) + ': ' + ips.utils.time.readable( date / 1000 ) + ')' );
				}
				else
				{
					reloadMarkers();
					return;
				}
			}

		},

		updateOverlays = function()
		{
			/* Count all markers in each overlay */
			$.each( overlayControl._layers, function( id, layer )
			{
				if ( layer.overlay === true && layer.layer.getLayers() !== 'undefined' && layer.layer.getLayers().length > 0 )
				{
					var count = layer.layer.getLayers().length;
					if ( count > 0 )
					{
						overlayControl._layers[ id ].name = overlayControl._layers[ id ].name + " (" + count + ")";
					}
				}
			});

			/* Insert 3rd-party overlay last */
			if ( $.isArray( defaultMaps.overlays ) && defaultMaps.overlays.length > 0 )
			{
				$.each( defaultMaps.overlays, function( id, name )
				{
					try 
					{
						var prettyName = name.replace( '.', ' ' );

						overlayControl.addOverlay( L.tileLayer.provider( name ), prettyName );
					}
					catch(e)
					{
						Debug.log( e.message );
					}
				});
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

			overlayControl._update();
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
						var geolocationSupported = true;

						if ( ! ( 'geolocation' in navigator ) )
						{
							geolocationSupported = false;
						}

						/* Chrom(e|ium) 50+ stops geolocation on unsecure protocols */
						if ( L.Browser.chrome === true && document.location.protocol !== 'https:' )
						{
							var chromeVersion = navigator.userAgent.match(/Chrom(e|ium)\/([0-9]+)\./);
							chromeVersion = chromeVersion ? parseInt( chromeVersion[2], 10 ) : false;

							if ( chromeVersion >= 50 )
							{
								geolocationSupported = false;
							}

						}

						if( ! navigator.geolocation || ! geolocationSupported )
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
									url: '//open.mapquestapi.com/nominatim/v1/search.php',
									dataType: 'jsonp',
									jsonp: 'json_callback',
									data: {
										key: ips.getSetting( 'membermap_mapquestAPI' ),

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
			if ( navigator.geolocation )
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
							key: ips.getSetting( 'membermap_mapquestAPI' ),
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
	
		showMarkers = function( dontRepan, markers )
		{
			dontRepan = typeof dontRepan !== 'undefined' ? dontRepan : false;
			markers = typeof markers !== 'undefined' ? markers : false;

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
				$.each( markers, function() 
				{		
					/* Don't show these, as they all end up in the middle of the middle of the South Atlantic Ocean. */
					if ( this.lat === 0 && this.lon === 0 )
					{
						return;
					}

					/* Do we have permission to see this marker? */
					if ( $.inArray( ips.getSetting( 'member_group' ), this.viewPerms ) === -1 && this.viewPerms !== '*' )
					{
						return;
					}
					
					var bgColour 	= 'darkblue';
					var icon 		= 'user';
					var iconColour 	= 'white';
					var popupOptions = {
						autoPan: false
					};

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

								removeURIParam( 'goHome' );
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
						if ( typeof this.expiryDate === 'number' )
						{
							if ( parseInt( this.expiryDate ) > 0 && parseInt( this.expiryDate ) < ( Date.now() / 1000 | 0 ) )
							{
								Debug.log( "Cache expired" );

								window.location.href = ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=showmap&rebuildCache=1';
							}
						}

						iconColour 	= this.colour;
						icon 		= this.icon || 'fa-map-marker';
						bgColour 	= this.bgColour;

						popupOptions.minWidth = 320;
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
					).bindPopup( this.popup, ( popupOptions || {} ) );
					
					mapMarker.markerData = this;

					if ( this.type == 'member' )
					{
						/* Group by member group */
						if ( ips.getSetting( 'membermap_groupByMemberGroup' ) && this.parent_id > 0 )
						{
							if ( typeof overlayMaps[ 'member-' + this.parent_id ] === "undefined" )
							{
								overlayMaps[ 'member-' + this.parent_id ] = L.featureGroup.subGroup( mastergroup );
								overlayControl.addOverlay( overlayMaps[ 'member-' + this.parent_id  ], this.parent_name );

								if ( ips.getSetting( 'membermap_onlyShowGroup' ).length > 0 )
								{
									if ( $.inArray( this.parent_name.toLowerCase(), ips.getSetting( 'membermap_onlyShowGroup' ) ) !== -1 )
									{
										overlayMaps[ 'member-' + this.parent_id ].addTo( map );
									}
								}
								else
								{
									overlayMaps[ 'member-' + this.parent_id ].addTo( map );
								}
							}

							overlayMaps[ 'member-' + this.parent_id ].addLayer( mapMarker );
						}
						/* Show all in one group/layer */
						else
						{
							if ( typeof overlayMaps['members'] === "undefined" )
							{
								overlayMaps['members'] = L.featureGroup.subGroup( mastergroup );
								overlayControl.addOverlay( overlayMaps['members'], ips.getString( 'membermap_overlay_members' ) );

								if ( ips.getSetting( 'membermap_onlyShowGroup' ).length > 0 )
								{
									if ( $.inArray( "members", ips.getSetting( 'membermap_onlyShowGroup' ) ) !== -1 )
									{
										overlayMaps['members'].addTo( map );
									}
								}
								else
								{
									overlayMaps['members'].addTo( map );
								}
							}
							
							overlayMaps['members'].addLayer( mapMarker );
						}
					}
					else
					{
						this.parent_id = this.appName || this.parent_id;
						
						if ( typeof overlayMaps[ 'custom-' + this.parent_id ] === "undefined" )
						{
							var layerName = ips.getString( 'membermap_marker_group_' + this.parent_id + '_JS' ) || ( this.parent_name ? this.parent_name : this.appName );

							overlayMaps[ 'custom-' + this.parent_id ] = L.featureGroup.subGroup( mastergroup );
							overlayControl.addOverlay( overlayMaps[ 'custom-' + this.parent_id  ], layerName );

							if ( ips.getSetting( 'membermap_onlyShowGroup' ).length > 0 )
							{
								if ( $.inArray( layerName.toLowerCase(), ips.getSetting( 'membermap_onlyShowGroup' ) ) !== -1 )
								{
									overlayMaps[ 'custom-' + this.parent_id ].addTo( map );
								}
							}
							else
							{
								overlayMaps[ 'custom-' + this.parent_id ].addTo( map );
							}
						}
						
						overlayMaps[ 'custom-' + this.parent_id ].addLayer( mapMarker );
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
					map.fitBounds( mastergroup.getBounds(), { 
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
								window.location.replace( ips.getSetting('baseURL') + "index.php?app=membermap&module=membermap&controller=showmap&dropBrowserCache=1&goHome=1" );
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
											window.location.replace( ips.getSetting('baseURL') + "index.php?app=membermap&module=membermap&controller=showmap&dropBrowserCache=1" );
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
		},
		
		paneZindex = 450,

		addCustomOverlay = function( id, name )
		{				
			if ( typeof overlayMaps[ 'custom-' + id ] === "undefined" )
			{
				overlayMaps[ 'custom-' + id ] = L.featureGroup();
				overlayControl.addOverlay( overlayMaps[ 'custom-' + id ], name );

				overlayMaps[ 'custom-' + id ].addTo( map );

				/* Create a pane for each */
				map.createPane( id + 'Pane' );
				map.getPane( id + 'Pane' ).style.zIndex = paneZindex;
				paneZindex = paneZindex - 1;
			}

			return overlayMaps[ 'custom-' + id ];
		},

		getMapObject = function()
		{
			return map;
		},

		removeURIParam = function( param )
		{
			var urlObject = ips.utils.url.getURIObject();
			var queryKeys = urlObject.queryKey;
			var newUrl;

			delete queryKeys[ param ];

			if( Object.keys( queryKeys ).length > 0 )
			{
				var newQuery = Object.keys( queryKeys ).reduce( function(a,k)
				{
					var v = ( queryKeys[k] !== "" ) ? k + '=' + encodeURIComponent( queryKeys[k] ) : k;
					a.push( v );
					return a;
				}, [] ).join( '&' );

				newUrl = window.location.origin + window.location.pathname + '?' + newQuery;
			}
			else
			{
				newUrl = window.location.origin + window.location.pathname;
			}

			History.replaceState( null, document.title, newUrl );
		};

		return {
			initMap: initMap,
			setMarkers: setMarkers,
			setCenter: setCenter,
			setZoomLevel: setZoomLevel,
			map: getMapObject,
			loadMarkers: loadMarkers,
			addCustomOverlay: addCustomOverlay
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

/* Translation of a few string in LeafletJS */
L.Control.Zoom.mergeOptions({
	zoomInTitle: ips.getString( 'leaflet_zoomIn' ) || 'Zoom in',
	zoomOutTitle: ips.getString( 'leaflet_zoomOut' ) || 'Zoom out'
});

L.Control.FullScreen.mergeOptions({
	title: ips.getString( 'leaflet_fullScreen' ) || 'Full Screen',
	titleCancel: ips.getString( 'leaflet_exitFullScreen' ) || 'Exit Full Screen',
});