/**
 * Member Map, by Stuart Silvester & Martin Aronsen
 */
;( function($, _, undefined){
	"use strict";

	ips.createModule( 'ips.membermap', function() 
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
			isEmbedded = false,
			
			bounds = null,
			forceBounds = false,
			
			stuffSize = 0,
			popups = [],

			oldMarkersIndicator = null,

			counter = 0,

			hasLocation = false,

			dontRepan = false;
	
		var initMap = function()
		{
			/* Default bounding box */
			var southWest = new L.LatLng( 56.83, -7.14 );
			var northEast = new L.LatLng( 74.449, 37.466 );

			/* Bounding Box */
			var bbox = ips.getSetting( 'membermap_bbox' );

			if ( bbox !== null && bbox.minLat && bbox.minLng && bbox.maxLat && bbox.maxLng )
			{
				southWest = new L.LatLng( bbox.minLat, bbox.minLng );
				northEast = new L.LatLng( bbox.maxLat, bbox.maxLng );

				forceBounds = true;

				if ( ips.getSetting( 'membermap_bbox_zoom' ) )
				{
					setZoomLevel( ips.getSetting( 'membermap_bbox_zoom' ) );
				}
			}

			bounds = new L.LatLngBounds(southWest, northEast);

			/* Safari gets cranky if this is loaded after the map is set up */
			$( window ).on( 'scroll resize', function()
			{
				/* Attempting to scroll above viewport caused flickering */
				if ( window.scrollY < 0 )
				{
					return false;
				}
				
				setMapHeight();
				
				map.invalidateSize( { debounceMoveend: true } );
			});

			defaultMaps = ips.getSetting( 'membermap_defaultMaps' );


			/* Showing a single user or online user, get the markers from DOM */
			if ( !!$( '#mapMarkers' ).attr( 'data-markers' ) )
			{
				try {
					var markersJSON = $.parseJSON( $( '#mapMarkers' ).attr( 'data-markers' ) );
					if ( markersJSON.length > 0 )
					{
						setMarkers( markersJSON );
					}
				}
				catch(err) {
					Debug.log( err );
				}
			}

			/* Set lat, lon and zoom from URL */
			var centerLat = parseFloat( unescape( ips.utils.url.getParam( 'lat' ) ).replace( ',', '.' ) );
			var centerLng = parseFloat( unescape( ips.utils.url.getParam( 'lng' ) ).replace( ',', '.' ) );
			var initZoom = parseInt( ips.utils.url.getParam( 'zoom' ) );
			
			if ( centerLat && centerLng )
			{
				setCenter( centerLat, centerLng );
			}
			
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

				var memberListHeight = leftForMe + $( '.ipsPageHeader' ).height();

				/* Subtract the height of the club header if it's in the sidebar */
				if ( $( '#elClubHeader_small' ).length > 0 )
				{
					memberListHeight = memberListHeight - $( '#elClubHeader_small' ).height();
				}

				/* Add the height if the club header is on top */
				if ( $( '#elClubHeader' ).length > 0 )
				{
					memberListHeight = memberListHeight + $( '#elClubHeader' ).outerHeight();
				}

				memberListHeight = memberListHeight > 300 ? memberListHeight : 300;

				$( '#membermap_memberList' ).css( { height: memberListHeight } );
				return true;
			}
			
			return false;
		},
		
		clear = function()
		{
			mastergroup.clearLayers();

			/* Default bounding box */
			var southWest = new L.LatLng( 56.83, -7.14 );
			var northEast = new L.LatLng( 74.449, 37.466 );
		
			bounds = new L.LatLngBounds(southWest, northEast);
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
				minZoom: 1,
				zoom: ( zoomLevel || 7 ),
				layers: [ mapServices[ defaultMap ] ],
				maxBounds: L.latLngBounds( L.latLng( -89.98155760646617, -180 ), L.latLng( 89.99346179538875, 180 ) ),
				maxBoundsViscosity: 1.0,
				contextmenu: ( L.Browser.mobile ? false : true ),
				contextmenuWidth: 180,
				contextmenuItems: contextMenu,
				fullscreenControl: L.Browser.mobile ? false : true,
				attributionControl: true,
				crs: L.CRS.EPSG3857
			});

			if ( L.Browser.mobile === false ) 
			{
				L.control.scale().addTo(map);
			}

			map.fitBounds( bounds, { maxZoom: ( zoomLevel || 7 ) } );
			
			if ( ips.getSetting( 'membermap_enable_clustering' ) == 1 && ips.utils.url.getParam( 'filter' ) != 'getByUser' )
			{
				mastergroup = L.markerClusterGroup({ chunkedLoading: true, zoomToBoundsOnClick: true });
			}
			else
			{
				mastergroup = L.featureGroup();
			}

			map.addLayer( mastergroup );
			
			overlayControl = L.control.layers( baseMaps, overlayMaps, { collapsed: ( L.Browser.mobile || isEmbedded ? true : false ) } ).addTo( map );

			map.on( 'baselayerchange', function( baselayer )
			{
				ips.utils.cookie.set( 'membermap_baseMap', baselayer.name.toLowerCase().replace( /\s/g, '' ) );
			});

			/* Truncate popup content and format local time */
			map.on( 'contentupdate popupopen', function( e ) 
			{
				ips.ui.truncate.respond( $( '.membermap_popupContent' ), { type: 'hide', size: '3 lines' } );

				var localTimezoneElem 	= $( e.popup._contentNode ).find( '.localTime' );
				var localTimezone 		= $( localTimezoneElem ).attr( 'data-timezone' );

				if ( localTimezone !== '' && ! _.isUndefined( localTimezone ) && localTimezoneElem.attr( 'data-parsed' ) !== 1 )
				{
					var localTimeString = new Date().toLocaleTimeString( ( navigator.language || $( 'html' ).attr( 'lang' ) ), { timeZone: localTimezone, hour: '2-digit', minute:'2-digit'} );
					localTimezoneElem.attr( 'data-parsed', '1' ).html( ips.getString( 'membermap_localTime', { time: localTimeString } ) ).show();
				}
			});

			/* Add 'night and day' overlay */
			if ( ips.getSetting( 'membermap_showNightAndDay' ) && ips.utils.url.getParam( 'filter' ) != 'getByUser' )
			{
				var terminator = L.terminator();
				terminator.addTo(map);

				setInterval(function()
				{
					var terminator2 = L.terminator();
					terminator.setLatLngs( terminator2.getLatLngs() );
					terminator.redraw();
				}, 500);
			}
			
			ips.membermap.map = map;
		},
		
		loadMarkers = function( forceReload )
		{	
			var dbCacheDate;

			function loadNextFile( id, fromDb )
			{
				if ( fromDb )
				{
					/* Get data from browser storage */
					var data 		= localStorage.getItem( 'membermap.' + localStoragePrefix + 'markers_' + id );
					var cacheTime 	= ips.utils.db.get('membermap', localStoragePrefix + 'cacheTime' );
				
					if ( ( id === 0 && data === null ) || cacheTime < ips.getSetting( 'membermap_cacheTime' ) )
					{
						if ( id === 0 && data === null )
						{
							/* This is the first load after we split the localStorage into chunks. Delete the old storage */
							ips.utils.db.remove('membermap', localStoragePrefix + 'markers' );
						}

						reloadMarkers();
						return;
					}

					/* if data is null, and it's not the first one, we're finished */
					if ( data === null )
					{
						finished( true );
						return;
					}

					try
					{
						data = JSON.parse( LZString.decompressFromUTF16( data ) );
					}
					catch( e )
					{
						reloadMarkers();
						return;
					}

					if ( data.data !== null && data.data.length > 0 )
					{
						/* Reload cache if it's older than 24 hrs */
						dbCacheDate = new Date( data.time * 1000 );
						var nowdate = new Date();
						if ( ( ( nowdate.getTime() - dbCacheDate.getTime() ) / 1000 ) > 86400 )
						{
							reloadMarkers();
							return;
						}

						showMarkers( data.data );
						allMarkers = allMarkers.concat( data.data );

						loadNextFile( ++id, true );
						return;
					}
				}
				else
				{
					ips.getAjax()({
						url: ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=ajax&do=getCache&id=' + id,
						cache: false,
						async: true,
						dataType: 'json',
						success:function( res )
						{
							if( res.error )
							{
								finished();
								return;
							}

							var nextId = id + 1;

							if ( dbEnabled )
							{
								localStorage.setItem( 'membermap.' + localStoragePrefix + 'markers_' + id, LZString.compressToUTF16( JSON.stringify( { time: parseInt( ( new Date() ).getTime() / 1000, 10 ), data: res.markers }  ) ) );

								/* Delete the next localStorage. This one might be the last chunk */
								ips.utils.db.remove('membermap', localStoragePrefix + 'markers_' + nextId );
							}

							/* Show marker layer */
							showMarkers( res.markers );
							allMarkers = allMarkers.concat( res.markers );

							loadNextFile( nextId, false );
							return;
						},
						error:function (xhr, ajaxOptions, thrownError)
						{
							if ( xhr.status == 404 ) 
							{
								finished( false );
								return;
							}
						}
					});
				}
			}

			/* Store data in browser when all AJAX calls complete */
			function finished( fromDb )
			{
				updateOverlays();

				if ( fromDb )
				{
					/* Inform that we're showing markers from browser cache */
					if ( oldMarkersIndicator === null && ! isEmbedded && ! _.isUndefined( dbCacheDate ) )
					{
						oldMarkersIndicator = new L.Control.MembermapOldMarkers(
						{ 
							callback: function() 
							{ 
								window.location.href = ips.getSetting( 'baseURL' ) + 'index.php?app=membermap&module=membermap&controller=showmap&dropBrowserCache=1'; 
							}, 
							time: dbCacheDate 
						});

						map.addControl( oldMarkersIndicator );
					}
				}
				else
				{
					if ( dbEnabled )
					{
						ips.utils.db.set( 'membermap', localStoragePrefix + 'cacheTime', ips.getSetting( 'membermap_cacheTime' ) );
					}
				}
				
				var date = _.isUndefined( dbCacheDate ) ? new Date() : dbCacheDate;
				$( '#elToolsMenuBrowserCache a time' ).html( '(' + ips.getString( 'membermap_browserCache_update' ) + ': ' + ips.utils.time.readable( date.getTime() / 1000 ) + ')' );
			}

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
				updateOverlays();
				return;
			}

			var localStoragePrefix = "";
			if( !_.isUndefined( ips.getSetting('cookie_prefix') ) && ips.getSetting('cookie_prefix') !== '' )
			{
				localStoragePrefix = ips.getSetting('cookie_prefix') + '.';
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

				/* Get fresh from the server */
				loadNextFile( 0, false );
			}
			else
			{
				/* Use localStorage */
				loadNextFile( 0, true );
			}
		},

		updateOverlays = function()
		{
			/* Add marker groups to the map.
			 * If we do this on the showMarkers() function, MarkerCluster will not do it's "chunkedLoading" magic, 
			 * and the cluster icon will be created/updated for every single marker */

			$.each( overlayMaps, function( id, group )
			{
				if ( group.addToMap )
				{
					group.addTo( map );
					bounds.extend( group.getBounds() );
				}
			});

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

			overlayControl._update();


			/* Contextual menu */
			/* Needs to run this after the markers, as we need to know if we're editing or adding the location */
			if ( ips.getSetting( 'memberID' ) )
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
					map.fitBounds( bounds, { 
						padding: [50, 50],
						maxZoom: 11
					});
				}
			}
		},
		
		initEvents = function()
		{
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
						/* Other browsers are following this standard. */
						/* Will disallow geolocation on unsecure connections instead of keeping track of when the various browsers aren't supporting unsecured geolocation */
						if ( document.location.protocol !== 'https:' )
						{
							geolocationSupported = false;
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
									url: ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=ajax&do=mapquestSearch',
									dataType: 'json',
									data: {
										q: request.term,
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
	
		showMarkers = function( markers )
		{
			markers = typeof markers !== 'undefined' ? markers : false;		

			var getByUser 	= ips.utils.url.getParam( 'filter' ) == 'getByUser' ? true : false;
			var memberId 	= parseInt( ips.utils.url.getParam( 'member_id' ) );
			var flyToZoom 	= 8;

			/* Member List block */
			var showStaffMembersBlock	= false;
			var showFollowedUsersBlock 	= false;
			var showOtherUsersBlock		= false;

			if ( forceBounds )
			{
				dontRepan = true;
			}

			if ( markers === false )
			{
				markers = allMarkers;
			}

			var length = markers.length;
			if ( length > 0 )
			{
				for ( var i=0; i < length; i++ ) 
				{
   					var marker = markers[i];

					/* Don't show these, as they all end up in the middle of the South Atlantic Ocean. */
					if ( marker.lat === 0 && marker.lon === 0 )
					{
						continue;
					}

					/* Do we have permission to see marker marker? */
					if (marker.viewPerms !== '*' && $.inArray( ips.getSetting( 'member_group' ), marker.viewPerms ) === -1 )
					{
						continue;
					}
					
					var bgColour 	= 'darkblue';
					var icon 		= 'user';
					var iconColour 	= 'white';
					var popupOptions = {
						autoPan: false,
						minWidth: 175
					};

					if ( marker.type == 'member' )
					{
						if ( marker.member_id == ips.getSetting( 'memberID' ) )
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
								memberId 	= marker.member_id;
								flyToZoom 	= 10;

								removeURIParam( 'goHome' );
							}
							
						}
						else
						{
							if ( marker.markerColour )
							{
								bgColour = marker.markerColour;
							}
						}
					}
					else
					{
						if ( typeof marker.expiryDate === 'number' )
						{
							if ( parseInt( marker.expiryDate ) > 0 && parseInt( marker.expiryDate ) < Math.round( new Date().getTime() / 1000 ) )
							{
								Debug.log( "Cache expired" );

								window.location.href = ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=showmap&rebuildCache=1';
							}
						}

						iconColour 	= marker.colour;
						icon 		= marker.icon || 'fa-map-marker';
						bgColour 	= marker.bgColour;

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

					if ( marker.type == 'member' && ( ips.getSetting( 'canModerateMap' ) ||  ( ips.getSetting( 'memberID' ) == marker.member_id && ips.getSetting( 'membermap_canDelete' ) ) ) )
					{
						enableContextMenu = true;
						contextMenu = getMarkerContextMenu( marker );
					}
					
					/* Why 'let' and not 'var'? https://stackoverflow.com/questions/4091765/assign-click-handlers-in-for-loop */
					let mapMarker = L.marker( 
						L.latLng( marker.lat, marker.lon ), 
						{ 
							title: marker.title,
							icon: _icon,
							contextmenu: enableContextMenu,
						    contextmenuItems: contextMenu
						}
					);

					mapMarker.marker_id = marker.id;
					mapMarker.marker_ext = marker.ext;
					mapMarker.popupAjaxed = 0;

					if ( marker.popup.length > 0 )
					{
						mapMarker.bindPopup( marker.popup, ( popupOptions || {} ) );
					}
					else
					{
						mapMarker.bindPopup( ips.getString('loading'), ( popupOptions || {} ) );

						mapMarker.on( 'click', (e) => {
							var popup = e.target.getPopup();
							var url = ips.getSetting('baseURL') + 'index.php?app=membermap&module=membermap&controller=ajax&do=getPopup&id=' + e.target.marker_id + '&ext=' + e.target.marker_ext;

							if ( ! e.target.popupAjaxed )
							{
								ips.getAjax()( url )
									.done( function( res )
									{
										e.target.popupAjaxed = 1;
										popup.setContent( res );
										popup.update();

										map.fire( 'popupopen', {popup: popup} );
									});
							}

						});
					}

					if ( marker.type == 'member' )
					{
						/* Add to member list sidebar */
						if ( $( '#memberList_staff' ).length )
						{
							var title = $( '<li>' ).addClass( 'ipsCursor_pointer' ).append( marker.member_name );

							if ( marker.isStaff )
							{
								 $( '#memberList_staff div ul' ).append( title );
								 showStaffMembersBlock = true;
							}
							else if ( $.inArray( marker.member_id, ips.getSetting( 'membermap_membersIFollow' ) ) !== -1 )
							{
								 $( '#memberList_followers div ul' ).append( title );
								showFollowedUsersBlock = true;
							}
							else
							{
								$( '#memberList_others div ul' ).append( title );
								showOtherUsersBlock = true;
							}

							$( title ).click( function() 
							{
								if ( ips.getSetting( 'membermap_enable_clustering' ) == 1  )
								{
									mastergroup.zoomToShowLayer( mapMarker, function()
									{
										map.panTo( mapMarker.getLatLng() );
										mapMarker.fireEvent( 'click' ); 
									});
								}
								else
								{
									map.flyTo( mapMarker.getLatLng(), 8 );
									mapMarker.fireEvent( 'click' ); 
								}
							});
						}


						/* Group by member group */
						if ( ips.getSetting( 'membermap_groupByMemberGroup' ) && marker.parent_id > 0 )
						{
							if ( _.isUndefined( overlayMaps[ 'member-' + marker.parent_id ] ) )
							{
								overlayMaps[ 'member-' + marker.parent_id ] = L.featureGroup.subGroup( mastergroup );
								overlayControl.addOverlay( overlayMaps[ 'member-' + marker.parent_id  ], marker.parent_name );

								overlayMaps[ 'member-' + marker.parent_id ].addToMap = 0;

								/* This was a custom request. Add "&group=<groupname>,<groupname2>" to the URL to only show those marker groups. */
								if ( ips.getSetting( 'membermap_onlyShowGroup' ).length > 0 )
								{
									if ( $.inArray( marker.parent_name.toLowerCase(), ips.getSetting( 'membermap_onlyShowGroup' ) ) !== -1 )
									{
										overlayMaps[ 'member-' + marker.parent_id ].addToMap = 1;
									}
								}
								else
								{
									overlayMaps[ 'member-' + marker.parent_id ].addToMap = 1;
								}
							}


							mapMarker.addTo( overlayMaps[ 'member-' + marker.parent_id ] );
						}
						/* Show all in one group/layer */
						else
						{
							if ( _.isUndefined( overlayMaps['members'] ) )
							{
								overlayMaps['members'] = L.featureGroup.subGroup( mastergroup );
								overlayControl.addOverlay( overlayMaps['members'], ips.getString( 'membermap_overlay_members' ) );

								overlayMaps['members'].addToMap = 0;

								/* This was a custom request. Add "&group=<groupname>,<groupname2>" to the URL to only show those marker groups. */
								if ( ips.getSetting( 'membermap_onlyShowGroup' ).length > 0 )
								{
									if ( $.inArray( "members", ips.getSetting( 'membermap_onlyShowGroup' ) ) !== -1 )
									{
										overlayMaps['members'].addToMap = 1;
									}
								}
								else
								{
									overlayMaps['members'].addToMap = 1;
								}
							}

							
							mapMarker.addTo( overlayMaps['members'] );
						}
					}
					else
					{
						marker.parent_id = marker.appName || marker.parent_id;
						
						if ( _.isUndefined( overlayMaps[ 'custom-' + marker.parent_id ] ) )
						{
							var layerName = ips.getString( 'membermap_marker_group_' + marker.parent_id + '_JS' ) || ( marker.parent_name ? marker.parent_name : marker.appName );

							overlayMaps[ 'custom-' + marker.parent_id ] = L.featureGroup.subGroup( mastergroup );
							overlayControl.addOverlay( overlayMaps[ 'custom-' + marker.parent_id  ], layerName );

							overlayMaps[ 'custom-' + marker.parent_id ].addToMap = 0;

							/* This was a custom request. Add "&group=<groupname>,<groupname2>" to the URL to only show those marker groups. */
							if ( ips.getSetting( 'membermap_onlyShowGroup' ).length > 0 )
							{
								if ( $.inArray( layerName.toLowerCase(), ips.getSetting( 'membermap_onlyShowGroup' ) ) !== -1 )
								{
									overlayMaps[ 'custom-' + marker.parent_id ].addToMap = 1;
								}
							}
							else
							{
								overlayMaps[ 'custom-' + marker.parent_id ].addToMap = 1;
							}
						}

						
						mapMarker.addTo( overlayMaps[ 'custom-' + marker.parent_id ] );
					}

					/* Count the number of markers we have on the map */
					counter = counter + 1;

					/* Pan directly to our home location, if that's what we wanted */
					if ( getByUser && memberId > 0 && marker.type == 'member' && marker.member_id == memberId )
					{
						dontRepan = true;
						map.flyTo( mapMarker.getLatLng(), flyToZoom );
					}
				}

				/* Update the counter */
				$( '#membermap_counter span' ).html( counter );


				/* Show the sidebar blocks, if we have content in them */
				if ( showStaffMembersBlock && $( '#memberList_staff' ).is( ':hidden' ) )
				{
					$( '#memberList_staff' ).slideDown( 200 );
				}
				
				if ( showFollowedUsersBlock && $( '#memberList_followers' ).is( ':hidden' ) )
				{
					$( '#memberList_followers' ).slideDown( 200 );
				}
				
				if ( showOtherUsersBlock && $( '#memberList_others' ).is( ':hidden' ) )
				{
					$( '#memberList_others' ).slideDown( 200 );
				}

				/* Don't show the title unless there are staff members or users you follow in any other blocks */
				if ( showOtherUsersBlock && ( showStaffMembersBlock || showFollowedUsersBlock ) && $( '#memberList_others h3' ).is( ':hidden' ) )
				{
					$( '#memberList_others h3' ).show();
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
			if ( ips.getSetting( 'canModerateMap' ) ||  ( ips.getSetting( 'memberID' ) == marker.member_id && ips.getSetting( 'membermap_canDelete' ) ) ) 
			{
				return [{
					'text': ips.getString( 'delete' ),
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

		addCustomOverlay = function( id, name, defaultOn )
		{				
			if ( typeof overlayMaps[ 'custom-' + id ] === "undefined" )
			{
				overlayMaps[ 'custom-' + id ] = L.featureGroup();
				overlayControl.addOverlay( overlayMaps[ 'custom-' + id ], name );

				if ( defaultOn ) 
				{
					overlayMaps[ 'custom-' + id ].addTo( map );
				}

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