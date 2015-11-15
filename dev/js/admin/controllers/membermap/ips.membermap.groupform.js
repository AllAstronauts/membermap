;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.groupform', {

		initialize: function () {
			this.monitorColourPickers();
		},

		monitorColourPickers: function()
		{
			var self		= this;
			var icon 		= $( 'input[name="group_pin_icon"]' ).eq(0);
			var iconColour 	= $( 'input[name="group_pin_colour"]' ).eq(0);
			var bgColour 	= $( 'input[name="group_pin_bg_colour"]' );


			bgColour.on('change', function()
			{
				var colour = $( 'input[name="group_pin_bg_colour"]:checked' ).val();
				$( '#markerExample' ).removeClass().addClass( 'awesome-marker awesome-marker-icon-' + colour )
			});

			iconColour.on('change', function()
			{
				$('#markerExample i').css({ 'color': '#' + iconColour.val() });
			});

			icon.on('change', function()
			{
				$('#markerExample i').removeClass().addClass( 'fa fa-fw' ).addClass( icon.val() );
			});

		}
	});
}(jQuery, _));