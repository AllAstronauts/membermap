;( function($, _, undefined){
	"use strict";

	ips.controller.register('membermap.admin.membermap.groupform', {

		initialize: function () {
			this.setup();
			this.monitorColourPickers();
		},

		setup: function()
		{
			var iconColour 	= $( 'input[name="group_pin_colour"]' ).eq(0).val();
			var bgColour 	= $( 'input[name="group_pin_bg_colour"]' ).eq(0).val();
			var icon 		= $( 'input[name="group_pin_icon"]' ).eq(0).val();

			$( '#markerExample' ).css(
				{
					'background-color': '#' + bgColour, 
					'border-top-color': '#' + bgColour
				}
			);

			$( '#markerExample i' ).css( 'color', '#' + iconColour ).addClass( icon );
		},


		/**
		 * Calculate contrast
		 *
		 * @see http://24ways.org/2010/calculating-color-contrast/
		 */
		getContrastYIQ: function(hexcolor)
		{
			var r = parseInt(hexcolor.substr(0,2),16);
			var g = parseInt(hexcolor.substr(2,2),16);
			var b = parseInt(hexcolor.substr(4,2),16);
			var yiq = ((r*299)+(g*587)+(b*114))/1000;
			return (yiq >= 128) ? 'black' : 'white';
		},

		monitorColourPickers: function()
		{
			var self		= this;
			var icon 		= $( 'input[name="group_pin_icon"]' ).eq(0);
			var iconColour 	= $( 'input[name="group_pin_colour"]' ).eq(0);
			var bgColour 	= $( 'input[name="group_pin_bg_colour"]' ).eq(0);


			bgColour.on('change', function()
			{
				$('#markerExample').css(
					{
						'background-color': '#' + bg.val(), 
						'border-top-color': '#' + bg.val()
					}
				);

				self.recalculateContrast(icon, bg);
			});

			iconColour.on('change', function()
			{
				$('#markerExample i').css('color', '#' + icon.val());
				self.recalculateContrast(icon, bg);
			});

			icon.on('change', function()
			{
				$('#markerExample i').removeClass().addClass( 'fa fa-fw' ).addClass( icon.val() );
			});

		},

		recalculateContrast: function(icon, bg)
		{
			var icoHex	= icon.val();
			var bgHex	= bg.val();

			var icoContrast = this.getContrastYIQ(icoHex);
			var bgContrast	= this.getContrastYIQ(bgHex);

			if(icoContrast === bgContrast)
			{
				$( '#contrastWarning' ).show();
			}
			else
			{
				$( '#contrastWarning' ).hide();
			}

		}
	});
}(jQuery, _));