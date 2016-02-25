;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.mapmanager', {
		baseLayers: {},
		overlays: {},

		initialize: function () 
		{
			this.fetchMapProviders();
			this.populateManager();
		},

		populateManager: function()
		{
			var that = this;
			var defaultMaps = ips.getSetting( 'membermap_defaultMaps' );

			$.each( this.baseLayers, function( name, layer )
			{
				var order = $.inArray( name, defaultMaps.basemaps );
				var noHTTPS = layer._url.indexOf('//') === 0 || layer._url.indexOf( 'https:' ) === 0 ? false : true;

				var item = ips.templates.render('mapManager.listItem', {
					name: name,
					type: 'basemap',
					order: ( order !== -1 ? order : '' ),
					noHTTPS: noHTTPS
				});
				
				if ( order == -1 )
				{
					$( '#mapManager_availMaps' ).append( item );
				}
				else
				{
					$( '#mapManager_activeMaps' ).append( item );
				}
			});

			$.each( this.overlays, function( name, layer )
			{
				var order = $.inArray( name, defaultMaps.overlays );
				var noHTTPS = layer._url.indexOf('//') === 0 || layer._url.indexOf( 'https:' ) === 0 ? false : true;

				var item = ips.templates.render('mapManager.listItem', {
					name: name,
					type: 'overlay',
					order: ( order !== -1 ? order : '' ),
					noHTTPS: noHTTPS
				});
				if ( order == -1 )
				{
					$( '#mapManager_availOverlays' ).append( item );
				}
				else
				{
					$( '#mapManager_activeOverlays' ).append( item );
				}
			});

			/* Sort the list of active maps, as they're now in the order they appear in leaflet-providers */
			var mapWrapper = $('#mapManager_activeMaps');

			mapWrapper.find('li').sort( function( a, b ) 
			{
				return ($(b).data('order')) < ($(a).data('order')) ? 1 : -1;   
			}).appendTo( mapWrapper );

			var overlayWrapper = $('#mapManager_activeOverlays');

			overlayWrapper.find('li').sort( function( a, b ) 
			{
				return ($(b).data('order')) < ($(a).data('order')) ? 1 : -1;   
			}).appendTo( overlayWrapper );

			$( '#mapManager_availMaps, #mapManager_activeMaps' ).sortable({
				connectWith: '.mapManager_baseMaps',
				placeholder: "cMenuManager_emptyHover",
				update: _.bind( this.update, this )
			}).disableSelection();

			$( '#mapManager_availOverlays, #mapManager_activeOverlays' ).sortable({
				connectWith: '.mapManager_overlays',
				placeholder: "cMenuManager_emptyHover",
				update: _.bind( this.update, this )
			}).disableSelection();
		},

		fetchMapProviders: function () 
		{
			var that = this;

			L.tileLayer.provider.eachLayer( function( name ) 
			{
				var layer = L.tileLayer.provider(name);

				if ( that.isOverlay( name, layer ) ) 
				{
					that.overlays[ name ] = layer;
				}
				else
				{
					that.baseLayers[ name ] = layer;
				}

			} );
		},

		isOverlay: function ( providerName, layer ) 
		{
			if ( layer.options.opacity && layer.options.opacity < 1 ) 
			{
				return true;
			}

			var overlayPatterns = [
				'^(OpenWeatherMap|OpenSeaMap)',
				'OpenMapSurfer.AdminBounds',
				'Stamen.Toner(Hybrid|Lines|Labels)',
				'Acetate.(foreground|labels|roads)',
				'Hydda.RoadsAndLabels'
			];

			return providerName.match('(' + overlayPatterns.join('|') + ')') !== null;
		},

		update: function()
		{
			this.save();
		},

		save: function()
		{
			var baseMaps = $( '#mapManager_activeMaps' ).sortable( 'toArray', { attribute: 'data-provider' } );
			var overlays = $( '#mapManager_activeOverlays' ).sortable( 'toArray', { attribute: 'data-provider' } );

			ips.getAjax()( '?app=membermap&module=membermap&controller=mapmanager&do=update', {
				data: {
					maps: { 'basemaps': baseMaps, 'overlays': overlays }
				}
			})
				.done( function () {
					// No need to do anything
				})
				.fail( function () {
					ips.ui.alert.show( {
						type: 'alert',
						icon: 'warn',
						message: ips.getString('membermap_mapmanager_cant_save'),
						callbacks: {}
					});
				});

		}
	});
}(jQuery, _));

if ( typeof L !== 'undefined' )
{
	L.tileLayer.provider.eachLayer = function (callback) {
		for (var provider in L.TileLayer.Provider.providers) {
			
			/* Ignore those who require an API key */
			if( provider === 'HERE' || provider === 'MapBox' )
			{
				continue;
			}
			if (L.TileLayer.Provider.providers[provider].variants) {
				for (var variant in L.TileLayer.Provider.providers[provider].variants) {
					callback(provider + '.' + variant);
				}
			} else {
				callback(provider);
			}
		}
	};
}